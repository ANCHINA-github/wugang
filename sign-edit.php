<?php
// 定义JSON文件路径
$jsonFile = __DIR__ . '/notice-content.json';

// ========== 核心强化：优先读取JSON文件，增加异常处理 ==========
// 初始化内容变量
$currentContent = [];

// 第一步：尝试读取JSON文件（最高优先级）
try {
    // 强制读取最新文件内容，禁用缓存
    $jsonContent = file_get_contents($jsonFile, false, stream_context_create([
        'http' => [
            'header' => 'Cache-Control: no-cache'
        ]
    ]));
    
    // 验证JSON格式是否有效
    if ($jsonContent === false) {
        throw new Exception('无法读取JSON文件');
    }
    
    $decodedContent = json_decode($jsonContent, true);
    
    // 验证解码结果是否为有效数组
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedContent)) {
        throw new Exception('JSON文件格式错误或内容无效');
    }
    
    // 验证必要字段是否存在，缺失则用默认值补充
    $currentContent = [
        'title' => isset($decodedContent['title']) ? trim($decodedContent['title']) : '📱 iOS风格每日提醒',
        'content' => isset($decodedContent['content']) ? trim($decodedContent['content']) : '这是你今日首次访问的专属提示',
        'subContent' => isset($decodedContent['subContent']) ? trim($decodedContent['subContent']) : '明天再次访问会重新显示哦～'
    ];
    
} catch (Exception $e) {
    // 读取失败时：1. 记录错误 2. 初始化默认JSON文件 3. 使用默认内容
    error_log('读取JSON文件失败：' . $e->getMessage());
    
    // 重新生成合法的JSON文件
    $defaultContent = [
        'title' => '📱 iOS风格每日提醒',
        'content' => '这是你今日首次访问的专属提示',
        'subContent' => '明天再次访问会重新显示哦～'
    ];
    file_put_contents($jsonFile, json_encode($defaultContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 使用默认内容
    $currentContent = $defaultContent;
}

// 处理保存请求（保存后会立即重新读取最新内容）
$message = '';
$messageType = '';
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $subContent = trim($_POST['subContent'] ?? '');
    
    // 验证必填项
    if (empty($title) || empty($content)) {
        $message = '标题和正文不能为空！';
        $messageType = 'error';
    } else {
        // 组装内容并保存到JSON
        $newContent = [
            'title' => $title,
            'content' => $content,
            'subContent' => $subContent
        ];
        
        // 保存时增加写入验证
        $saveResult = file_put_contents($jsonFile, json_encode($newContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($saveResult === false) {
            $message = '保存失败！请检查JSON文件写入权限';
            $messageType = 'error';
        } else {
            // 保存成功后，立即重新读取JSON文件（确保页面显示最新内容）
            $currentContent = $newContent;
            $message = '内容保存成功！';
            $messageType = 'success';
            $saveSuccess = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/a.svg">
    <title>通知后台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: url('./poster.webp');
            background-size: cover;
            background-repeat: no-repeat;
            position: relative;
            transition: background 0.3s;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a1c20, #25292e);
            }
        }

        /* 核心液态玻璃卡片 */
        .glass-card {
            width: 100%;
            max-width: 720px;
            padding: 36px 32px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.49);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
        }

        @media (prefers-color-scheme: dark) {
            .glass-card {
                background: rgba(22, 24, 28, 0.65);
                border: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            }
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-header h2 {
            font-size: 22px;
            font-weight: 600;
            color: #1d1d1f;
        }

        .view-link {
            font-size: 15px;
            color: #0071e3;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 12px;
            background: rgba(0, 113, 227, 0.1);
            transition: all 0.2s;
        }

        .view-link:hover {
            background: rgba(0, 113, 227, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            .card-header h2 {
                color: #f5f5f7;
            }
        }

        /* 消息提示 - 动态颜色类 */
        .message {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .msg-color-1 { color: #0071e3; } /* 蓝色 */
        .msg-color-2 { color: #2e7d32; } /* 绿色 */
        .msg-color-3 { color: #e67700; } /* 橙色 */
        .msg-color-4 { color: #7e57c2; } /* 紫色 */

        .message.error {
            color: #c62828;
            background: rgba(198, 40, 40, 0.08);
            border-color: rgba(198, 40, 40, 0.15);
        }

        .message.warning {
            color: #e67700;
            background: rgba(251, 192, 45, 0.08);
            border-color: rgba(251, 192, 45, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            .message { background: rgba(0,0,0,0.2); }
            .msg-color-1 { color: #42a5f5; }
            .msg-color-2 { color: #81c784; }
            .msg-color-3 { color: #ffb74d; }
            .msg-color-4 { color: #b39ddb; }
            .message.error { color: #e57373; }
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 500;
            color: #1d1d1f;
        }

        @media (prefers-color-scheme: dark) {
            label {
                color: #f5f5f7;
            }
        }

        input, textarea {
            width: 100%;
            padding: 16px 18px;
            border-radius: 16px;
            font-size: 16px;
            border: none;
            outline: none;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1d1d1f;
            transition: all 0.2s;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        input:focus, textarea:focus {
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.2);
        }

        @media (prefers-color-scheme: dark) {
            input, textarea {
                background: rgba(40, 44, 52, 0.5);
                color: #f5f5f7;
            }
            input:focus, textarea:focus {
                background: rgba(50, 54, 62, 0.7);
            }
        }

        /* 按钮 */
        .save-btn {
            width: 100%;
            padding: 16px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            color: #fff;
            background: #0071e3;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 24px;
        }

        .save-btn:hover:not(:disabled) {
            background: #0077ed;
        }

        .save-btn:disabled {
            background: #90caf9;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* 预览 */
        .preview {
            padding: 22px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        @media (prefers-color-scheme: dark) {
            .preview {
                background: rgba(30, 34, 40, 0.4);
                border-color: rgba(255,255,255,0.05);
            }
        }

        .preview h3 {
            font-size: 19px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 8px;
        }

        .preview p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
        }

        .preview small {
            display: block;
            margin-top: 8px;
            font-size: 14px;
            color: #888;
        }

        @media (prefers-color-scheme: dark) {
            .preview h3 { color: #f5f5f7; }
            .preview p { color: #d1d1d6; }
            .preview small { color: #98989f; }
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #888;
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 24px 20px;
            }
            .card-header h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="glass-card">
        <div class="card-header">
            <h2>通知编辑</h2>
            <a href="../" class="view-link" target="_blank">去看看</a>
        </div>

        <!-- 状态提示 -->
        <?php if (!file_exists($jsonFile)): ?>
            <div class="message warning">未找到配置文件，已自动创建</div>
        <?php elseif (isset($e)): ?>
            <div class="message error">读取失败：<?php echo htmlspecialchars($e->getMessage()); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType === 'error' ? 'error' : ''; ?> dynamic-success-color">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="editForm">
            <div class="form-group">
                <label>标题</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($currentContent['title']); ?>" required>
            </div>

            <div class="form-group">
                <label>正文</label>
                <textarea name="content" required><?php echo htmlspecialchars($currentContent['content']); ?></textarea>
            </div>

            <div class="form-group">
                <label>补充说明</label>
                <input type="text" name="subContent" value="<?php echo htmlspecialchars($currentContent['subContent']); ?>">
            </div>

            <button type="submit" class="save-btn" id="saveBtn">保存修改</button>
        </form>

        <!-- 预览 -->
        <div class="preview">
            <h3><?php echo htmlspecialchars($currentContent['title']); ?></h3>
            <p><?php echo htmlspecialchars($currentContent['content']); ?></p>
            <?php if (!empty($currentContent['subContent'])): ?>
                <small><?php echo htmlspecialchars($currentContent['subContent']); ?></small>
            <?php endif; ?>
        </div>

        <div class="footer">段游孤独一生</div>
    </div>

    <script>
        const form = document.getElementById('editForm');
        const saveBtn = document.getElementById('saveBtn');
        const successMsgEl = document.querySelector('.dynamic-success-color');
        
        // 保存成功计数（用于切换颜色）
        let saveCount = parseInt(sessionStorage.getItem('saveCount') || 0);
        // 标记是否已经保存成功过
        const hasSavedBefore = saveCount > 0;

        // 页面加载：如果之前保存过，直接禁用按钮
        window.addEventListener('load', () => {
            if(hasSavedBefore) {
                saveBtn.disabled = true;
                saveBtn.textContent = '已保存';
            }
        });

        // 提交后禁用按钮，防止重复提交
        form.addEventListener('submit', (e) => {
            if(saveBtn.disabled) {
                e.preventDefault();
                return;
            }
            
            saveBtn.disabled = true;
            saveBtn.textContent = '保存中...';
            
            // 保存成功后计数+1，存入本地会话
            saveCount++;
            sessionStorage.setItem('saveCount', saveCount);
        });

        // 动态切换成功提示颜色（蓝→绿→橙→紫循环）
        if(successMsgEl && '<?php echo $messageType; ?>' === 'success') {
            const colorClasses = ['msg-color-1', 'msg-color-2', 'msg-color-3', 'msg-color-4'];
            const currentColorIndex = (saveCount - 1) % colorClasses.length;
            successMsgEl.classList.add(colorClasses[currentColorIndex]);
        }

        // 禁用 Ctrl+S 浏览器保存，改为表单提交
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (!saveBtn.disabled) form.submit();
            }
        });
    </script>

</body>
</html>