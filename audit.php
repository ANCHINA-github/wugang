<?php
// audit.php - 图片审核页面

// 开启错误显示（开发环境）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 定义数据文件路径
$postsJsonPath = __DIR__ . '/posts.json';
$banImage = 'banned_image.webp'; // 违规图片占位图（请确保该文件存在）

// 处理 AJAX 请求：标记图片违规
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_banned') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // 接收参数
    $pid = $_POST['pid'] ?? '';
    $type = $_POST['type'] ?? ''; // post_image, comment_image, portrait, com_portrait
    $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
    $cid = $_POST['cid'] ?? ''; // 评论ID，仅当 type 为 comment_image 或 com_portrait 时需要

    if (empty($pid) || empty($type)) {
        $response['message'] = '参数不完整';
        echo json_encode($response);
        exit;
    }

    // 读取 posts.json
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    if (!is_array($postsData)) {
        $response['message'] = '数据文件损坏';
        echo json_encode($response);
        exit;
    }

    $found = false;
    // 遍历帖子
    foreach ($postsData as &$post) {
        if ($post['pid'] != $pid) continue;

        switch ($type) {
            case 'post_image':
                // 帖子图片数组
                if (isset($post['images']) && is_array($post['images']) && isset($post['images'][$index])) {
                    $post['images'][$index] = $banImage;
                    $found = true;
                }
                break;

            case 'comment_image':
                // 评论图片
                if (isset($post['comments']) && is_array($post['comments'])) {
                    foreach ($post['comments'] as &$comment) {
                        if ($comment['com_cid'] == $cid && isset($comment['com_images'][$index])) {
                            $comment['com_images'][$index] = $banImage;
                            $found = true;
                            break 2;
                        }
                    }
                }
                break;

            case 'portrait':
                // 帖子作者头像
                if (isset($post['portrait'])) {
                    $post['portrait'] = $banImage;
                    $found = true;
                }
                break;

            case 'com_portrait':
                // 评论作者头像
                if (isset($post['comments']) && is_array($post['comments'])) {
                    foreach ($post['comments'] as &$comment) {
                        if ($comment['com_cid'] == $cid && isset($comment['com_portrait'])) {
                            $comment['com_portrait'] = $banImage;
                            $found = true;
                            break 2;
                        }
                    }
                }
                break;

            default:
                $response['message'] = '未知类型';
                echo json_encode($response);
                exit;
        }

        if ($found) break;
    }

    if ($found) {
        // 保存修改
        if (file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
            $response['success'] = true;
            $response['message'] = '已标记为违规';
        } else {
            $response['message'] = '保存文件失败';
        }
    } else {
        $response['message'] = '未找到对应图片';
    }

    echo json_encode($response);
    exit;
}

// 读取所有帖子数据
$posts = json_decode(file_get_contents($postsJsonPath), true);
if (!is_array($posts)) {
    $posts = [];
}

// 收集所有图片信息
$imageList = [];
foreach ($posts as $post) {
    $pid = $post['pid'] ?? '';

    // 1. 帖子作者头像
    if (!empty($post['portrait']) && $post['portrait'] !== $banImage) {
        $imageList[] = [
            'pid' => $pid,
            'type' => 'portrait',
            'src' => $post['portrait'],
            'label' => "帖子作者头像 (pid: $pid)",
            'index' => -1,
            'cid' => ''
        ];
    }

    // 2. 帖子图片
    if (!empty($post['images']) && is_array($post['images'])) {
        foreach ($post['images'] as $idx => $img) {
            if ($img !== $banImage) {
                $imageList[] = [
                    'pid' => $pid,
                    'type' => 'post_image',
                    'src' => $img,
                    'label' => "帖子图片 (pid: $pid, idx: $idx)",
                    'index' => $idx,
                    'cid' => ''
                ];
            }
        }
    }

    // 3. 评论
    if (!empty($post['comments']) && is_array($post['comments'])) {
        foreach ($post['comments'] as $comment) {
            $cid = $comment['com_cid'] ?? '';

            // 评论作者头像
            if (!empty($comment['com_portrait']) && $comment['com_portrait'] !== $banImage) {
                $imageList[] = [
                    'pid' => $pid,
                    'type' => 'com_portrait',
                    'src' => $comment['com_portrait'],
                    'label' => "评论头像 (pid: $pid, cid: $cid)",
                    'index' => -1,
                    'cid' => $cid
                ];
            }

            // 评论图片
            if (!empty($comment['com_images']) && is_array($comment['com_images'])) {
                foreach ($comment['com_images'] as $idx => $img) {
                    if ($img !== $banImage) {
                        $imageList[] = [
                            'pid' => $pid,
                            'type' => 'comment_image',
                            'src' => $img,
                            'label' => "评论图片 (pid: $pid, cid: $cid, idx: $idx)",
                            'index' => $idx,
                            'cid' => $cid
                        ];
                    }
                }
            }
        }
    }
}

// 如果图片列表为空，显示提示
$hasImages = count($imageList) > 0;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片审核管理</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }
        .stats {
            background: white;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .stats span {
            font-weight: 600;
            color: #0f172a;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        .image-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s;
            cursor: pointer;
            position: relative;
        }
        .image-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .image-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
            background: #f1f5f9;
        }
        .image-card .info {
            padding: 10px 12px;
            font-size: 13px;
            color: #475569;
            border-top: 1px solid #e9edf2;
            background: #fafbfc;
            word-break: break-all;
        }
        .image-card .info .pid {
            font-weight: 600;
            color: #0f172a;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .empty-state i {
            font-size: 48px;
            color: #94a3b8;
            margin-bottom: 16px;
        }
        .empty-state p {
            color: #64748b;
            font-size: 18px;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 90vw;
            max-height: 90vh;
            padding: 20px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 28px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.15s;
        }
        .modal-close:hover {
            color: #1e293b;
        }
        .modal img {
            max-width: 80vw;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #f1f5f9;
        }
        .modal .actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .modal .btn {
            padding: 10px 28px;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-danger:active {
            transform: scale(0.96);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .modal .context {
            font-size: 14px;
            color: #475569;
            background: #f8fafc;
            padding: 8px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            max-width: 100%;
            word-break: break-all;
        }
        /* 加载状态 */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            font-size: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 2000;
        }
        .toast.show {
            opacity: 1;
        }

        @media (max-width: 600px) {
            .image-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            .image-card img {
                height: 130px;
            }
            .modal img {
                max-width: 95vw;
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🖼️ 图片审核</h1>
    <div class="subtitle">点击任意图片放大查看，确认违规后标记为违规图片</div>
    <div class="stats">
        <div>待审核图片：<span><?php echo count($imageList); ?></span></div>
        <div>涉及帖子数：<span><?php echo count($posts); ?></span></div>
    </div>

    <?php if ($hasImages): ?>
    <div class="image-grid" id="imageGrid">
        <?php foreach ($imageList as $idx => $item): ?>
        <div class="image-card" data-index="<?php echo $idx; ?>">
            <img src="<?php echo htmlspecialchars($item['src']); ?>" alt="图片" loading="lazy" onerror="this.src='banned_image.webp'">
            <div class="info">
                <span class="pid"><?php echo htmlspecialchars($item['label']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div>📭</div>
        <p>暂无图片需要审核（所有图片均已被标记或不存在）</p>
    </div>
    <?php endif; ?>
</div>

<!-- 模态框 -->
<div class="modal" id="previewModal">
    <div class="modal-content">
        <button class="modal-close" id="modalClose">&times;</button>
        <img id="modalImage" src="" alt="预览">
        <div class="context" id="modalContext"></div>
        <div class="actions">
            <button class="btn btn-danger" id="markBannedBtn">🚫 标记为违规</button>
            <button class="btn btn-secondary" id="modalCancelBtn">关闭</button>
        </div>
    </div>
</div>

<!-- Toast 提示 -->
<div class="toast" id="toast"></div>

<script>
    (function() {
        // 存储当前图片数据
        let currentItem = null;
        const imageItems = <?php echo json_encode($imageList); ?>;

        // DOM 元素
        const grid = document.getElementById('imageGrid');
        const modal = document.getElementById('previewModal');
        const modalImg = document.getElementById('modalImage');
        const modalContext = document.getElementById('modalContext');
        const modalClose = document.getElementById('modalClose');
        const modalCancel = document.getElementById('modalCancelBtn');
        const markBtn = document.getElementById('markBannedBtn');
        const toast = document.getElementById('toast');

        // 显示 Toast 消息
        function showToast(msg, isSuccess = true) {
            toast.textContent = msg;
            toast.style.background = isSuccess ? '#0f172a' : '#b91c1c';
            toast.classList.add('show');
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // 打开模态框
        function openModal(index) {
            const item = imageItems[index];
            if (!item) return;
            currentItem = item;
            modalImg.src = item.src;
            modalImg.alt = item.label;
            modalContext.textContent = '来源：' + item.label;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // 关闭模态框
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            currentItem = null;
        }

        // 标记违规
        function markBanned() {
            if (!currentItem) return;

            // 禁用按钮防止重复点击
            markBtn.disabled = true;
            markBtn.textContent = '处理中...';

            const data = new URLSearchParams();
            data.append('action', 'mark_banned');
            data.append('pid', currentItem.pid);
            data.append('type', currentItem.type);
            data.append('index', currentItem.index);
            data.append('cid', currentItem.cid);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data.toString()
            })
            .then(res => res.json())
            .then(result => {
                markBtn.disabled = false;
                markBtn.textContent = '🚫 标记为违规';
                if (result.success) {
                    showToast('✅ 已标记为违规，图片将替换为占位图', true);
                    // 更新页面上的缩略图
                    const cards = document.querySelectorAll('.image-card');
                    cards.forEach(card => {
                        const idx = parseInt(card.dataset.index);
                        if (idx === imageItems.indexOf(currentItem)) {
                            const img = card.querySelector('img');
                            img.src = 'banned_image.webp';
                        }
                    });
                    // 更新模态框中的图片为占位图
                    modalImg.src = 'banned_image.webp';
                    // 将当前项标记为已处理（避免重复操作）
                    currentItem.src = 'banned_image.webp';
                    // 延迟关闭模态框
                    setTimeout(closeModal, 1200);
                } else {
                    showToast('❌ 操作失败：' + result.message, false);
                }
            })
            .catch(err => {
                markBtn.disabled = false;
                markBtn.textContent = '🚫 标记为违规';
                showToast('❌ 网络错误，请重试', false);
                console.error(err);
            });
        }

        // 事件绑定
        // 图片卡片点击
        if (grid) {
            grid.addEventListener('click', function(e) {
                const card = e.target.closest('.image-card');
                if (card) {
                    const idx = parseInt(card.dataset.index);
                    if (!isNaN(idx)) {
                        openModal(idx);
                    }
                }
            });
        }

        // 关闭模态框事件
        modalClose.addEventListener('click', closeModal);
        modalCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // 标记违规按钮
        markBtn.addEventListener('click', markBanned);

        // 如果图片加载失败，显示占位图
        document.querySelectorAll('.image-card img').forEach(img => {
            img.addEventListener('error', function() {
                this.src = 'banned_image.webp';
            });
        });
    })();
</script>

</body>
</html>