<?php
date_default_timezone_set('Asia/Shanghai');
// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $id = $_POST['id'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // 验证ID是否为10位数
    if (!preg_match('/^\d{10}$/', $id)) {
        $error = "ID必须为10位数字";
    }
    // 验证邮箱格式
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "邮箱格式不正确";
    }
    // 验证反馈内容长度
    elseif (strlen(trim($feedback)) > 300 || strlen(trim($feedback)) == 0) {
        $error = "反馈内容不能为空，且不能超过300字";
    }
    else {
        // 准备要保存的数据
        $data = [
            'id' => $id,
            'email' => $email,
            'feedback' => $feedback,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // 读取现有的问题数据
        $problems = [];
        if (file_exists('problem.json')) {
            $json = file_get_contents('problem.json');
            $problems = json_decode($json, true) ?: [];
        }
        
        // 添加新的问题到数组开头
        array_unshift($problems, $data);
        
        // 保存到JSON文件
        if (file_put_contents('problem.json', json_encode($problems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            
            // --- 核心改动：输出成功提示页面并使用JS跳转 ---
            
            // 定义跳转目标页面
            $redirect_to = '../login-service.php';
            
            // 直接输出一个完整的成功提示页面
            echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反馈成功</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 全局样式重置 */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Noto Sans SC", "Microsoft YaHei", Arial, sans-serif;
    -webkit-tap-highlight-color: transparent;
}
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
                overflow-x: hidden;
        }
        body > video {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: -1;
    object-fit: cover;
    filter: brightness(0.6);
}
        .success-container {
            text-align: center;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 90%;
        }
        .success-icon {
            font-size: 60px;
            color: #10B981; /* 绿色 */
            margin-bottom: 20px;
        }
        h1 {
            color: #1F2937;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        p {
            color: #6B7280;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .redirect-message {
            font-size: 14px;
            color: #9CA3AF;
        }
        .redirect-message a {
            color: #4F46E5; /* 蓝色 */
            text-decoration: none;
        }
        .redirect-message a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
        <video autoplay loop muted playsinline>
        <source src="background.mp4" type="video/mp4">
        您的浏览器不支持视频播放，请升级浏览器。
    </video>
    <div class="success-container">
        <div class="success-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h1>反馈成功！</h1>
        <p>感谢您的宝贵意见，我们将尽快处理。</p>
        <div class="redirect-message">
            页面将在 <span id="countdown">2</span> 秒后自动跳转...<br>
            如果没有自动跳转，请 <a href="$redirect_to">点击这里</a>。
        </div>
    </div>

    <script>
        let countdown = 2;
        const countdownElement = document.getElementById('countdown');
        const redirectUrl = '$redirect_to';

        const interval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                window.location.href = redirectUrl;
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
            // 终止脚本执行，确保只显示成功页面
            exit;
            
        } else {
            $error = "数据保存失败，请联系管理员。";
        }
    }
}

// 如果不是POST请求或POST请求失败，则显示表单页面
// 读取问题数据用于显示
$problems = [];
if (file_exists('problem.json')) {
    $json = file_get_contents('problem.json');
    $problems = json_decode($json, true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>页面问题反馈</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- 全局与基础样式 --- */
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #10B981;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --light-color: #F9FAFB;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }
body > video {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: -1;
    object-fit: cover;
    filter: brightness(0.6);
}
        .container {
            max-width: 768px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* --- 组件样式 --- */
        .card {
            background-color: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            transition: var(--transition);
        }
        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        h1, h2, h3 {
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        h1 {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            font-weight: 700;
            text-align: center;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        h2 i {
            color: var(--primary-color);
            margin-right: 0.75rem;
        }

        p {
            margin-bottom: 1rem;
            color: var(--gray-600);
        }

        .text-center {
            text-align: center;
        }

        /* --- 表单样式 --- */
        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }

        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .char-count {
            font-size: 0.875rem;
            color: var(--gray-500);
            text-align: right;
            margin-top: 0.25rem;
        }

        .char-count.limit {
            color: var(--danger-color);
        }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        button:hover {
            background-color: #4338ca;
        }

        button i {
            margin-right: 0.5rem;
        }

        .btn-block {
            width: 100%;
        }

        /* --- 消息提示 --- */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-error {
            background-color: #fee2e2;
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        /* --- 问题列表 --- */
        .problem-list {
            margin-top: 1.5rem;
        }

        .problem-item {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .problem-item:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .problem-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .problem-id {
            font-weight: 600;
            color: var(--dark-color);
        }

        .problem-date {
            font-size: 0.875rem;
            color: var(--gray-500);
            background-color: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
        }

        .problem-content {
            color: var(--gray-700);
            line-height: 1.7;
        }

        /* --- 折叠/展开 --- */
        .toggle-btn {
            width: 100%;
            background-color: transparent;
            color: var(--primary-color);
            font-weight: 500;
            padding: 0.5rem 0;
        }

        .toggle-btn:hover {
            background-color: transparent;
            color: #4338ca;
        }

        #oldProblems {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }

        /* --- 空状态 --- */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }
        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--gray-800);
        }
        
        /* --- 页脚 --- */
        footer {
            text-align: center;
            padding-top: 2rem;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* --- 响应式设计 --- */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem;
            }
            .card {
                padding: 1.5rem;
            }
            .problem-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .problem-date {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
        <video autoplay loop muted playsinline>
        <source src="background.mp4" type="video/mp4">
        您的浏览器不支持视频播放，请升级浏览器。
    </video>
    <div class="container">
        <!-- 页面标题 -->
        <header class="text-center mb-8" >
            <h1><i class="fa-solid fa-comments" style="color:white;"></i>问题反馈</h1>
            <p style="color:white;">我们会在第一时间内解决或回复你<a href="../login-service.php" style="color:blue;">返回</a></p>
        </header>
        
        <main>
            <!-- 反馈表单 -->
            <section class="card">
                <h2><i class="fa-solid fa-pencil-alt"></i> 提交反馈</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <strong>错误：</strong><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="id">用户ID <span style="color:var(--danger-color)">*</span></label>
                        <input type="text" id="id" name="id" required placeholder="请输入10位数字ID" pattern="\d{10}" title="请输入10位数字" value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱 <span style="color:var(--danger-color)">*</span></label>
                        <input type="email" id="email" name="email" required placeholder="请输入您的邮箱地址" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="feedback">反馈内容 <span style="color:var(--danger-color)">*</span></label>
                        <textarea id="feedback" name="feedback" required placeholder="请详细描述您遇到的问题（最多300字）" maxlength="300"><?php echo isset($_POST['feedback']) ? htmlspecialchars($_POST['feedback']) : ''; ?></textarea>
                        <p class="char-count"><span id="charCount"><?php echo isset($_POST['feedback']) ? strlen($_POST['feedback']) : '0'; ?></span>/300 字符</p>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn-block">
                            <i class="fa-solid fa-paper-plane"></i> 提交反馈
                        </button>
                    </div>
                </form>
            </section>
            
            <!-- 已提交问题展示区 -->
            <section class="card">
                <h2><i class="fa-solid fa-list-check"></i> 已提交的问题</h2>
                
                <?php if (empty($problems)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-comments"></i>
                        <h3>暂无反馈</h3>
                        <p>这里将展示用户提交的问题反馈。</p>
                    </div>
                <?php else: ?>
                    <div class="problem-list">
                        <!-- 最新的问题（总是展开） -->
                        <div class="problem-item">
                            <div class="problem-header">
                                <span class="problem-id">用户ID: <?php echo htmlspecialchars($problems[0]['id']); ?></span>
                                <span class="problem-date"><?php echo $problems[0]['date']; ?></span>
                            </div>
                            <div class="problem-content">
                                <?php echo nl2br(htmlspecialchars($problems[0]['feedback'])); ?>
                            </div>
                        </div>
                        
                        <!-- 其余问题（默认折叠） -->
                        <?php if (count($problems) > 1): ?>
                            <div id="oldProblems" class="problem-list">
                                <?php for ($i = 1; $i < count($problems); $i++): ?>
                                    <div class="problem-item">
                                        <div class="problem-header">
                                            <span class="problem-id">用户ID: <?php echo htmlspecialchars($problems[$i]['id']); ?></span>
                                            <span class="problem-date"><?php echo $problems[$i]['date']; ?></span>
                                        </div>
                                        <div class="problem-content">
                                            <?php echo nl2br(htmlspecialchars($problems[$i]['feedback'])); ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <button type="button" id="toggleProblems" class="toggle-btn">
                                <i class="fa-solid fa-chevron-down"></i> 查看更多 (<?php echo count($problems) - 1; ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> 页面问题反馈系统</p>
        </footer>
    </div>
    
    <script>
        // 字符计数功能
        const feedbackTextarea = document.getElementById('feedback');
        const charCountSpan = document.getElementById('charCount');
        
        if (feedbackTextarea && charCountSpan) {
            function updateCount() {
                const count = feedbackTextarea.value.length;
                charCountSpan.textContent = count;
                if (count > 280) {
                    charCountSpan.parentElement.classList.add('limit');
                } else {
                    charCountSpan.parentElement.classList.remove('limit');
                }
            }
            updateCount();
            feedbackTextarea.addEventListener('input', updateCount);
        }
        
        // 折叠/展开功能
        const toggleBtn = document.getElementById('toggleProblems');
        const oldProblems = document.getElementById('oldProblems');
        
        if (toggleBtn && oldProblems) {
            toggleBtn.addEventListener('click', function() {
                const isExpanded = oldProblems.style.maxHeight !== '0px' && oldProblems.style.maxHeight !== '';
                
                if (isExpanded) {
                    oldProblems.style.maxHeight = '0px';
                    this.innerHTML = '<i class="fa-solid fa-chevron-down"></i> 查看更多 (<?php echo count($problems) - 1; ?>)';
                } else {
                    oldProblems.style.maxHeight = oldProblems.scrollHeight + 'px';
                    this.innerHTML = '<i class="fa-solid fa-chevron-up"></i> 收起';
                }
            });
        }
    </script>
</body>
</html>