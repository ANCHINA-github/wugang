<?php
/**
 * 帖子 + 评论点赞数自动刷赞脚本
 * 运行方式：通过 cron 每5小时执行一次，或通过 HTTP 访问触发
 * 时间窗口：北京时间 00:00, 05:00, 10:00, 15:00, 20:00
 *
 * 规则：
 * - 每隔5小时检测一次 ./posts.json
 * - 帖子：plikes < 100，则随机改为 100-300 之间的整数
 * - 评论：clikes < 10，则随机改为 20-60 之间的整数
 * - 已达到阈值的帖子/评论不做任何更改
 * - 5小时间隔从北京时间零点整开始计算，剩余4小时作废
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 配置文件路径
$postsFile = './posts.json';
$lockFile = './posts_update.lock';
$logFile = './update_likes.log';

// ========== 帖子刷赞配置 ==========
$postLikeThreshold = 100;   // 帖子 plikes 小于该值才修改
$postLikeMin = 100;         // 帖子新点赞最小值
$postLikeMax = 300;         // 帖子新点赞最大值

// ========== 评论刷赞配置 ==========
$commentLikeThreshold = 10; // 评论 clikes 小于该值才修改
$commentLikeMin = 20;       // 评论新点赞最小值
$commentLikeMax = 60;       // 评论新点赞最大值

// 检查文件是否存在
if (!file_exists($postsFile)) {
    writeLog('错误：posts.json 文件不存在于当前目录');
    exit(1);
}

// 使用文件锁防止并发冲突
$fp = fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX)) {
    writeLog('错误：无法获取文件锁，可能其他进程正在执行');
    exit(1);
}

try {
    // 读取 JSON 文件
    $jsonContent = file_get_contents($postsFile);
    if ($jsonContent === false) {
        throw new Exception('无法读取 posts.json 文件');
    }

    // 解析 JSON
    $posts = json_decode($jsonContent, true);
    if ($posts === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON 解析错误：' . json_last_error_msg());
    }

    if (!is_array($posts)) {
        throw new Exception('posts.json 格式错误：根节点应为数组');
    }

    // 计算当前时间窗口
    $currentHour = (int)date('H');
    $timeWindow = floor($currentHour / 5) * 5;
    $timeWindowStr = sprintf('%02d:00', $timeWindow);

    // 帖子统计
    $postModifiedCount = 0;
    $postUnchangedCount = 0;
    $totalPostCount = count($posts);

    // 评论统计
    $commentModifiedCount = 0;
    $commentUnchangedCount = 0;
    $totalCommentCount = 0;

    // 遍历所有帖子
    foreach ($posts as $index => &$post) {
        $pid = isset($post['pid']) ? $post['pid'] : 'index_' . $index;

        // ---------- 处理帖子 plikes ----------
        if (isset($post['plikes'])) {
            $currentLikes = (int)$post['plikes'];

            if ($currentLikes < $postLikeThreshold) {
                $newLikes = mt_rand($postLikeMin, $postLikeMax);
                $post['plikes'] = $newLikes;
                $postModifiedCount++;

                writeLog("修改帖子 [pid={$pid}] plikes: {$currentLikes} -> {$newLikes}");
            } else {
                $postUnchangedCount++;
            }
        }

        // ---------- 处理评论 clikes ----------
        if (isset($post['comments']) && is_array($post['comments'])) {
            foreach ($post['comments'] as $cIndex => &$comment) {
                if (!isset($comment['clikes'])) {
                    continue;
                }

                $totalCommentCount++;
                $currentCommentLikes = (int)$comment['clikes'];

                if ($currentCommentLikes < $commentLikeThreshold) {
                    $newCommentLikes = mt_rand($commentLikeMin, $commentLikeMax);
                    $comment['clikes'] = $newCommentLikes;
                    $commentModifiedCount++;

                    $cid = isset($comment['com_cid']) ? $comment['com_cid'] : 'index_' . $cIndex;
                    writeLog("修改评论 [pid={$pid}, cid={$cid}] clikes: {$currentCommentLikes} -> {$newCommentLikes}");
                } else {
                    $commentUnchangedCount++;
                }
            }
            unset($comment);
        }
    }
    unset($post);

    // 如果有修改，写回文件
    if ($postModifiedCount > 0 || $commentModifiedCount > 0) {
        // 保持原始格式：中文不转义，保留缩进，保留反斜杠
        $newJson = json_encode(
            $posts,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($newJson === false) {
            throw new Exception('JSON 编码错误：' . json_last_error_msg());
        }

        // 原子写入：先写入临时文件，再重命名，防止写入中断导致文件损坏
        $tempFile = $postsFile . '.tmp.' . uniqid();
        if (file_put_contents($tempFile, $newJson, LOCK_EX) === false) {
            throw new Exception('无法写入临时文件');
        }

        if (!rename($tempFile, $postsFile)) {
            @unlink($tempFile);
            throw new Exception('无法替换原文件');
        }

        writeLog("时间窗口 {$timeWindowStr}：成功修改 {$postModifiedCount} 条帖子、{$commentModifiedCount} 条评论；无需修改 {$postUnchangedCount} 条帖子、{$commentUnchangedCount} 条评论；总计 {$totalPostCount} 条帖子、{$totalCommentCount} 条评论");
        echo "执行完成：修改 {$postModifiedCount} 条帖子、{$commentModifiedCount} 条评论；跳过 {$postUnchangedCount} 条帖子、{$commentUnchangedCount} 条评论\n";
    } else {
        writeLog("时间窗口 {$timeWindowStr}：无需修改，所有 {$totalPostCount} 条帖子 plikes 均 >= {$postLikeThreshold}，所有 {$totalCommentCount} 条评论 clikes 均 >= {$commentLikeThreshold}");
        echo "执行完成：所有 {$totalPostCount} 条帖子、{$totalCommentCount} 条评论均无需修改\n";
    }

} catch (Exception $e) {
    writeLog('执行异常：' . $e->getMessage());
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);
    exit(1);
}

// 释放锁并清理
flock($fp, LOCK_UN);
fclose($fp);
@unlink($lockFile);

exit(0);

/**
 * 写入日志
 * @param string $message 日志内容
 */
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}" . PHP_EOL;

    // 同时输出到文件和标准错误
    error_log($logLine, 3, $logFile);
    error_log($logLine);
}