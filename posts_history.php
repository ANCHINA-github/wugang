<?php
// ========== 处理删除评论请求（仅限帖子作者本人操作） ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    header('Content-Type: application/json');
    $pid   = trim($_POST['pid'] ?? '');
    $pname = trim($_POST['pname'] ?? '');
    $cid   = trim($_POST['cid'] ?? '');

    if ($pid === '' || $pname === '' || $cid === '') {
        echo json_encode(['status' => 'error', 'msg' => '参数错误']);
        exit;
    }

    $postsFile = 'posts.json';
    if (!file_exists($postsFile)) {
        echo json_encode(['status' => 'error', 'msg' => '数据文件不存在']);
        exit;
    }

    $postsData = json_decode(file_get_contents($postsFile), true);
    if (!is_array($postsData)) {
        echo json_encode(['status' => 'error', 'msg' => '数据读取失败']);
        exit;
    }

    // 1. 定位帖子
    $postIndex = -1;
    foreach ($postsData as $index => $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            $postIndex = $index;
            break;
        }
    }
    if ($postIndex === -1) {
        echo json_encode(['status' => 'error', 'msg' => '未找到该帖子']);
        exit;
    }

    // 2. 权限校验：必须是该帖子的作者
    if (!isset($postsData[$postIndex]['pname']) || $postsData[$postIndex]['pname'] !== $pname) {
        echo json_encode(['status' => 'error', 'msg' => '您没有权限删除此帖的评论']);
        exit;
    }

    // 3. 定位评论
    $comments = $postsData[$postIndex]['comments'] ?? [];
    if (!is_array($comments)) {
        $comments = [];
    }
    $comIndex = -1;
    foreach ($comments as $index => $comment) {
        if (isset($comment['com_cid']) && $comment['com_cid'] === $cid) {
            $comIndex = $index;
            break;
        }
    }
    if ($comIndex === -1) {
        echo json_encode(['status' => 'error', 'msg' => '未找到该评论']);
        exit;
    }

    // 4. 收集该评论的图片（稍后物理删除）
    $imagesToDelete = $comments[$comIndex]['com_images'] ?? [];
    if (!is_array($imagesToDelete)) {
        $imagesToDelete = [];
    }

    // 5. 移除评论
    array_splice($comments, $comIndex, 1);
    $postsData[$postIndex]['comments'] = array_values($comments);

    // 6. 保存数据
    $result = file_put_contents(
        $postsFile,
        json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    if ($result === false) {
        echo json_encode(['status' => 'error', 'msg' => '删除失败，请重试']);
        exit;
    }

    // 7. 删除评论图片文件
    foreach ($imagesToDelete as $imagePath) {
        if (!empty($imagePath) && file_exists($imagePath)) {
            @unlink($imagePath);
        }
    }

    // 8. 清除缓存
    $postsCachePath = 'cache/posts_cache.json';
    if (file_exists($postsCachePath)) {
        @unlink($postsCachePath);
    }

    echo json_encode([
        'status'      => 'success',
        'msg'         => '评论已删除',
        'comments_num' => count($postsData[$postIndex]['comments'])
    ]);
    exit;
}

// ========== 处理删除帖子请求（自包含，不依赖 core.php） ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    header('Content-Type: application/json');
    $pid = trim($_POST['pid'] ?? '');
    $pname = trim($_POST['pname'] ?? '');

    if (empty($pid) || empty($pname)) {
        echo json_encode(['status' => 'error', 'msg' => '参数错误']);
        exit;
    }

    $postsFile = 'posts.json';
    if (!file_exists($postsFile)) {
        echo json_encode(['status' => 'error', 'msg' => '数据文件不存在']);
        exit;
    }

    $postsData = json_decode(file_get_contents($postsFile), true);
    if (!is_array($postsData)) {
        echo json_encode(['status' => 'error', 'msg' => '数据读取失败']);
        exit;
    }

    $foundIndex = -1;
    $postToDelete = null;
    foreach ($postsData as $index => $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            if (isset($post['pname']) && $post['pname'] === $pname) {
                $foundIndex = $index;
                $postToDelete = $post;
                break;
            } else {
                echo json_encode(['status' => 'error', 'msg' => '您没有权限删除此帖子']);
                exit;
            }
        }
    }

    if ($foundIndex === -1) {
        echo json_encode(['status' => 'error', 'msg' => '未找到该帖子']);
        exit;
    }

    // 收集所有图片（帖子图片 + 评论图片）
    $imagesToDelete = $postToDelete['images'] ?? [];
    foreach ($postToDelete['comments'] ?? [] as $comment) {
        if (isset($comment['com_images']) && is_array($comment['com_images'])) {
            $imagesToDelete = array_merge($imagesToDelete, $comment['com_images']);
        }
    }

    // 移除帖子
    array_splice($postsData, $foundIndex, 1);

    // 保存数据
    $result = file_put_contents($postsFile, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($result === false) {
        echo json_encode(['status' => 'error', 'msg' => '删除失败，请重试']);
        exit;
    }

    // 删除图片文件（可选）
    foreach ($imagesToDelete as $imagePath) {
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }
    }

    // 清除缓存（如果有）
    $cacheDir = 'cache';
    $postsCachePath = $cacheDir . '/posts_cache.json';
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }

    echo json_encode(['status' => 'success', 'msg' => '帖子已永久删除']);
    exit;
}

// ========== 获取历史动态 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_my_posts' && isset($_POST['pname'])) {
    header('Content-Type: application/json');
    $pname = trim($_POST['pname']);
    if (empty($pname)) {
        echo json_encode(['status' => 'error', 'msg' => '用户名不能为空']);
        exit;
    }

    $postsFile = 'posts.json';
    $myPosts = [];
    if (file_exists($postsFile)) {
        $content = file_get_contents($postsFile);
        $allPosts = json_decode($content, true) ?? [];
        $filtered = array_filter($allPosts, function($post) use ($pname) {
            return isset($post['pname']) && $post['pname'] === $pname;
        });
        usort($filtered, function($a, $b) {
            return strtotime($b['pdate']) - strtotime($a['pdate']);
        });
        $myPosts = array_values($filtered);
    }
    echo json_encode(['status' => 'success', 'data' => $myPosts]);
    exit;
}

// ========== 显示页面 ==========
$staticVer = time();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>我的历史动态</title>
    <link rel="stylesheet" href="/repository/main.css?v=<?php echo $staticVer; ?>">
    <link rel="stylesheet" href="/repository/awesome/css/all.min.css">
    <style>
        body {
            padding: 16px;
        }
        .history-container {
            max-width: 800px;
            margin: 0 auto;
            margin-bottom: 85px;
        }
        .history-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .back-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            padding: 8px 12px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 16px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-btn:hover {
            background: var(--hover-bg);
        }
        .history-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--text-primary);
        }
        .posts-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .post-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 14px;
            box-shadow: var(--glass-shadow);
            transition: opacity 0.3s ease, transform 0.3s ease;
            position: relative;
        }
        /* 头部：flex布局，左侧头像+信息，右侧删除按钮 */
        .post-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .post-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        .post-header-right {
            flex-shrink: 0;
            margin-left: auto;
        }
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            padding: 2px;
            background: conic-gradient(from 0deg, #ff6b6b, #feca57, #48dbfb, #ff6b6b);
        }
        .post-user-info {
            flex: 1;
        }
        .post-username {
            font-size: 14px;
            font-weight: 500;
        }
        .post-date {
            font-size: 12px;
            color: var(--text-tertiary);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .post-device {
            font-size: 11px;
            color: var(--text-secondary);
            background: var(--input-bg);
            padding: 2px 8px;
            border-radius: 10px;
            border: 1px solid var(--glass-border);
        }
        .post-content {
            font-size: 15px;
            color: var(--text-primary);
            line-height: 1.7;
            margin-bottom: 12px;
            padding: 0 2px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .post-images-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .post-image-item {
            width: calc(33.33% - 4px);
            aspect-ratio: 1/1;
            overflow: hidden;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            background: #eee;
        }
        .post-image-item.single {
            width: 100%;
            max-width: 400px;
            aspect-ratio: 16/9;
        }
        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .post-stats {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .post-stats i {
            margin-right: 4px;
        }
        .comments-container {
            margin-top: 10px;
            border-top: 1px solid var(--glass-border);
            padding-top: 10px;
        }
        .comments-title {
            font-size: 13px;
            font-weight: bold;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .comment-item {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            padding: 6px 0;
            border-bottom: 1px solid var(--glass-border);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .comment-item:last-child {
            border-bottom: none;
        }
        .comment-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            padding: 1px;
            background: conic-gradient(from 0deg, #ff6b6b, #feca57, #48dbfb, #ff6b6b);
        }
        .comment-content-wrap {
            flex: 1;
            min-width: 0;
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        .comment-header-right {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .comment-username {
            font-size: 13px;
            font-weight: 500;
        }
        .comment-date {
            font-size: 11px;
            color: var(--text-tertiary);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .comment-body {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .comment-images-container {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }
        .comment-image-item {
            width: 50px;
            height: 50px;
            overflow: hidden;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
        }
        .comment-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .comment-device {
            font-size: 10px;
            color: var(--text-secondary);
            background: var(--input-bg);
            padding: 1px 6px;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
        }
        /* 评论删除按钮（红色文字） */
        .comment-delete-btn {
            background: none;
            border: none;
            color: var(--accent-red);
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
            display: inline-flex;
            align-items: center;
            opacity: 0.85;
        }
        .comment-delete-btn:hover {
            background: rgba(255, 68, 68, 0.15);
            opacity: 1;
        }
        .comment-delete-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        /* 帖子删除按钮（红色文字） */
        .post-delete-btn {
            background: none;
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            font-size: 13px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: var(--border-radius-sm);
            transition: background 0.2s, color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            opacity: 0.9;
        }
        .post-delete-btn:hover {
            background: rgba(255, 68, 68, 0.15);
            opacity: 1;
        }
        .post-delete-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-tertiary);
            font-size: 16px;
        }
        .loading-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
            .post-card {
                padding: 12px;
            }
            .post-avatar {
                width: 36px;
                height: 36px;
            }
            .post-content {
                font-size: 14px;
            }
            .post-image-item {
                width: calc(50% - 4px);
            }
            .post-image-item.single {
                max-width: 100%;
            }
            .comment-avatar {
                width: 26px;
                height: 26px;
            }
            .history-title {
                font-size: 18px;
            }
            .back-btn {
                font-size: 14px;
                padding: 6px 10px;
            }
            .post-delete-btn {
                font-size: 12px;
                padding: 5px 8px;
            }
            .comment-delete-btn {
                font-size: 11px;
                padding: 2px 3px;
            }
        }
    </style>
</head>
<body>
    <div class="history-container">
        <div class="history-header">
            <button class="back-btn" onclick="history.back()">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
            <div class="history-title">我的历史动态</div>
        </div>
        <div id="postsList" class="posts-list">
            <div class="loading-state">加载中...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            if (!userInfo || !userInfo.pname) {
                document.getElementById('postsList').innerHTML = 
                    '<div class="empty-state">请先登录查看动态</div>';
                return;
            }

            const listContainer = document.getElementById('postsList');
            listContainer.innerHTML = '<div class="loading-state">加载中...</div>';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_my_posts&pname=${encodeURIComponent(userInfo.pname)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    const posts = result.data || [];
                    if (posts.length === 0) {
                        listContainer.innerHTML = '<div class="empty-state">暂无历史动态</div>';
                        return;
                    }
                    let html = '';
                    posts.forEach(post => {
                        html += renderPostCard(post);
                    });
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = `<div class="empty-state">加载失败：${escapeHtml(result.msg || '未知错误')}</div>`;
                }
            })
            .catch(error => {
                listContainer.innerHTML = '<div class="empty-state">网络错误，请重试</div>';
                console.error('请求失败:', error);
            });
        });

        // 只保留安全字符，防止注入到 onclick 属性中
        function safeId(v) {
            return String(v == null ? '' : v).replace(/[^0-9A-Za-z_\-]/g, '');
        }

        function renderPostCard(post) {
            const pname = escapeHtml(post.pname || '未知');
            const portrait = post.portrait || 'default-avatar.png';
            const pdate = escapeHtml(post.pdate || '');
            const device = post.device ? escapeHtml(post.device) : '';
            const content = escapeHtml(post.content || '').replace(/\n/g, '<br>');
            const likes = post.plikes || 0;
            const pid = safeId(post.pid || '');
            const images = post.images || [];
            const comments = post.comments || [];

            let imagesHtml = '';
            if (images.length > 0) {
                const imgClass = images.length === 1 ? 'single' : '';
                imagesHtml = '<div class="post-images-container">';
                images.forEach(src => {
                    imagesHtml += `
                        <div class="post-image-item ${imgClass}" onclick="openImage('${escapeHtml(src)}')">
                            <img src="${escapeHtml(src)}" alt="图片" class="post-image" loading="lazy" onerror="this.src='a.svg'">
                        </div>
                    `;
                });
                imagesHtml += '</div>';
            }

            let commentsHtml = '';
            if (comments.length > 0) {
                commentsHtml = `<div class="comments-container"><div class="comments-title">评论 (<span class="js-comment-count">${comments.length}</span>)</div>`;
                comments.forEach(comment => {
                    const comPname = escapeHtml(comment.com_pname || '未知');
                    const comPortrait = comment.com_portrait || 'default-avatar.png';
                    const comDate = escapeHtml(comment.com_date || '');
                    const comContent = escapeHtml(comment.com_content || '').replace(/\n/g, '<br>');
                    const comDevice = comment.com_device ? escapeHtml(comment.com_device) : '';
                    const comImages = comment.com_images || [];
                    const comCid = safeId(comment.com_cid || '');

                    let comImagesHtml = '';
                    if (comImages.length > 0) {
                        comImagesHtml = '<div class="comment-images-container">';
                        comImages.forEach(src => {
                            comImagesHtml += `
                                <div class="comment-image-item" onclick="openImage('${escapeHtml(src)}')">
                                    <img src="${escapeHtml(src)}" alt="评论图片" class="comment-image" loading="lazy" onerror="this.src='a.svg'">
                                </div>
                            `;
                        });
                        comImagesHtml += '</div>';
                    }

                    // 只有存在评论ID时才显示删除按钮（本页所有帖子均为本人所发）
                    const delBtnHtml = comCid
                        ? `<button class="comment-delete-btn" onclick="deleteComment('${pid}','${comCid}')" title="删除此评论">删除此评论</button>`
                        : '';

                    commentsHtml += `
                        <div class="comment-item" data-cid="${comCid}">
                            <img src="${comPortrait}" alt="头像" class="comment-avatar" onerror="this.src='default-avatar.jpg'">
                            <div class="comment-content-wrap">
                                <div class="comment-header">
                                    <span class="comment-username">${comPname}</span>
                                    <span class="comment-header-right">
                                        <span class="comment-date">
                                            ${comDate}
                                            ${comDevice ? `<span class="comment-device">${comDevice}</span>` : ''}
                                        </span>
                                        ${delBtnHtml}
                                    </span>
                                </div>
                                <div class="comment-body">${comContent}</div>
                                ${comImagesHtml}
                            </div>
                        </div>
                    `;
                });
                commentsHtml += '</div>';
            }

            // 卡片HTML：头部左侧是头像+信息，右侧是删除按钮
            return `
                <div class="post-card" data-pid="${pid}">
                    <div class="post-header">
                        <div class="post-header-left">
                            <img src="${portrait}" alt="头像" class="post-avatar" onerror="this.src='default-avatar.jpg'">
                            <div class="post-user-info">
                                <div class="post-username">${pname}</div>
                                <div class="post-date">
                                    ${pdate}
                                    ${device ? `<span class="post-device">${device}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="post-header-right">
                            <button class="post-delete-btn" onclick="deletePost('${pid}')" title="永久删除此帖">删除此帖子</button>
                        </div>
                    </div>
                    <div class="post-content">${content}</div>
                    ${imagesHtml}
                    <div class="post-stats">
                        <span><i class="fas fa-heart" style="color: var(--accent-red);"></i> ${likes}</span>
                        <span><i class="fas fa-comment"></i> <span class="js-comment-count">${comments.length}</span></span>
                    </div>
                    ${commentsHtml}
                </div>
            `;
        }

        // ---------- 删除评论 ----------
        function deleteComment(pid, cid) {
            if (!pid || !cid) {
                showMessage('评论ID无效', 'error');
                return;
            }

            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            if (!userInfo || !userInfo.pname) {
                showMessage('请先登录', 'error');
                return;
            }

            if (!confirm('确定要删除这条评论吗？\n删除后不可恢复，评论图片也会一并删除！')) {
                return;
            }

            const item = document.querySelector(`.comment-item[data-cid="${cid}"]`);
            const btn = item ? item.querySelector('.comment-delete-btn') : null;
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_comment&pid=${encodeURIComponent(pid)}&pname=${encodeURIComponent(userInfo.pname)}&cid=${encodeURIComponent(cid)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    showMessage('评论已删除', 'success');

                    const card = document.querySelector(`.post-card[data-pid="${pid}"]`);

                    if (item) {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(-12px)';
                        setTimeout(() => {
                            item.remove();
                            updateCommentCount(card, -1);
                        }, 250);
                    } else {
                        updateCommentCount(card, -1);
                    }
                } else {
                    showMessage(result.msg, 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '删除此评论';
                    }
                }
            })
            .catch(() => {
                showMessage('网络错误，请重试', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '删除此评论';
                }
            });
        }

        // 同步更新评论计数；当评论数为 0 时移除评论区
        function updateCommentCount(card, delta) {
            if (!card) return;
            const countEls = card.querySelectorAll('.js-comment-count');
            if (countEls.length === 0) return;

            let newCount = 0;
            countEls.forEach(el => {
                const cur = parseInt(el.textContent, 10) || 0;
                newCount = Math.max(0, cur + delta);
                el.textContent = newCount;
            });

            if (newCount === 0) {
                const container = card.querySelector('.comments-container');
                if (container) {
                    container.style.transition = 'opacity 0.2s ease';
                    container.style.opacity = '0';
                    setTimeout(() => container.remove(), 200);
                }
            }
        }

        // ---------- 删除帖子 ----------
        function deletePost(pid) {
            if (!pid) {
                showMessage('帖子ID无效', 'error');
                return;
            }

            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            if (!userInfo || !userInfo.pname) {
                showMessage('请先登录', 'error');
                return;
            }

            if (!confirm('确定要永久删除此帖吗？\n删除后不可恢复，包括所有评论和图片！')) {
                return;
            }

            const btn = document.querySelector(`.post-delete-btn[onclick*="deletePost('${pid}')"]`);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_post&pid=${encodeURIComponent(pid)}&pname=${encodeURIComponent(userInfo.pname)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    showMessage('帖子已永久删除', 'success');
                    const card = document.querySelector(`.post-card[data-pid="${pid}"]`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            card.remove();
                            const list = document.getElementById('postsList');
                            if (list && list.children.length === 0) {
                                list.innerHTML = '<div class="empty-state">暂无历史动态</div>';
                            }
                        }, 300);
                    }
                } else {
                    showMessage(result.msg, 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '删除此帖子';
                    }
                }
            })
            .catch(() => {
                showMessage('网络错误，请重试', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '删除此帖子';
                }
            });
        }

        // ---------- 辅助 ----------
        function openImage(src) {
            window.open(src, '_blank');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showMessage(msg, type) {
            if (typeof showGlobalTip === 'function') {
                showGlobalTip(msg, type);
            } else {
                alert(msg);
            }
        }
    </script>
</body>
</html>