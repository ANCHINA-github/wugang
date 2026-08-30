<?php
/**
 * 帖子点赞数自动刷赞脚本
 * 运行方式：通过 cron 每5小时执行一次，或通过 HTTP 访问触发
 * 时间窗口：北京时间 00:00, 05:00, 10:00, 15:00, 20:00
 * 
 * 规则：
 * - 每隔5小时检测一次 ./posts.json 中 plikes 的值
 * - 如果 plikes < 20，则随机改为 45-55 之间的整数
 * - 如果 plikes >= 20，不做任何更改
 * - 5小时间隔从北京时间零点整开始计算，剩余4小时作废
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 配置文件路径
$postsFile = './posts.json';
$lockFile = './posts_update.lock';
$logFile = './update_likes.log';

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

    $modifiedCount = 0;
    $totalCount = count($posts);
    $unchangedCount = 0;

    // 遍历所有帖子，仅检查并修改 plikes 字段
    foreach ($posts as $index => &$post) {
        // 跳过没有 plikes 字段的条目
        if (!isset($post['plikes'])) {
            continue;
        }

        $currentLikes = (int)$post['plikes'];

        // 判断条件：plikes < 20 才修改
        if ($currentLikes < 20) {
            // 随机生成 21-65 之间的整数
            $newLikes = mt_rand(21, 65);
            $post['plikes'] = $newLikes;
            $modifiedCount++;

            // 记录修改详情
            $pid = isset($post['pid']) ? $post['pid'] : 'index_' . $index;
            writeLog("修改帖子 [pid={$pid}] plikes: {$currentLikes} -> {$newLikes}");
        } else {
            $unchangedCount++;
        }
        // 如果 plikes >= 20，不做任何更改，保持原值
    }

    // 如果有修改，写回文件
    if ($modifiedCount > 0) {
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

        writeLog("时间窗口 {$timeWindowStr}：成功修改 {$modifiedCount} 条帖子，{$unchangedCount} 条无需修改，总计 {$totalCount} 条");
        echo "执行完成：修改 {$modifiedCount} 条帖子，跳过 {$unchangedCount} 条\n";
    } else {
        writeLog("时间窗口 {$timeWindowStr}：无需修改，所有 {$totalCount} 条帖子 plikes 均 >= 20");
        echo "执行完成：所有 {$totalCount} 条帖子均无需修改\n";
    }

} catch (Exception $e) {
    writeLog('执行异常：' . $e->getMessage());
    flock($fp, LOCK_UN);
    fclose($fp);
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