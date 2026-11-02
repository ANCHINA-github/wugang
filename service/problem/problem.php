<?php
date_default_timezone_set('Asia/Shanghai');
session_start();
$error = '';
$fromUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../../../core.php';
$self = $_SERVER['PHP_SELF'];
// 兼容低版本PHP替换str_contains
if (strpos($fromUrl, $self) !== false) {
    $fromUrl = isset($_SESSION['feedback_from']) ? $_SESSION['feedback_from'] : '../../../core.php';
} else {
    $_SESSION['feedback_from'] = $fromUrl;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (!preg_match('/^\d{1,12}$/', $id)) {
        $error = "ID必须为1-12位数字";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "邮箱格式不正确";
    }
    elseif (strlen(trim($feedback)) > 300 || strlen(trim($feedback)) == 0) {
        $error = "反馈内容不能为空，且不能超过300字";
    }
    else {
        $data = [
            'id' => $id,
            'email' => $email,
            'feedback' => $feedback,
            'date' => date('Y-m-d H:i:s')
        ];
        $problems = [];
        if (file_exists('problem.json')) {
            $json = file_get_contents('problem.json');
            $problems = json_decode($json, true) ?: [];
        }
        array_unshift($problems, $data);
        if (file_put_contents('problem.json', json_encode($problems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $fromUrlEsc = htmlspecialchars($fromUrl, ENT_QUOTES, 'UTF-8');
            $fromUrlJs  = json_encode($fromUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>反馈成功</title>
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'dark' && t !== 'light') {
                t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --bg: #FFFBD6;
            --text: #1F2937;
            --text-secondary: #6B7280;
            --text-faint: #9CA3AF;
            --accent: #4F46E5;
            --success: #10B981;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #121212;
            --text: #E8E8E8;
            --text-secondary: #A0A0A0;
            --text-faint: #707070;
            --accent: #818CF8;
            --success: #34D399;
        }
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 24px calc(32px + env(safe-area-inset-bottom));
            text-align: center;
            -webkit-font-smoothing: antialiased;
            transition: background-color .25s ease, color .25s ease;
        }
        .check {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 44px;
            line-height: 1;
            margin-bottom: 24px;
            animation: pop .4s cubic-bezier(.2, .8, .3, 1.2);
        }
        @keyframes pop {
            0%   { transform: scale(.4); opacity: 0; }
            100% { transform: scale(1);  opacity: 1; }
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: .3px;
        }
        p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.55;
            max-width: 320px;
        }
        .tip {
            margin-top: 32px;
            font-size: 13px;
            color: var(--text-faint);
            line-height: 1.7;
        }
        .tip a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="check">✓</div>
    <h1>反馈成功</h1>
    <p>感谢您的宝贵意见，我们会尽快处理。</p>
    <div class="tip">
        <span id="c">2</span> 秒后自动返回…<br>
        未跳转？<a href="{$fromUrlEsc}">点此返回</a>
    </div>
    <script>
        var target = {$fromUrlJs};
        var n = 2, el = document.getElementById('c');
        var t = setInterval(function () {
            if (--n <= 0) {
                clearInterval(t);
                location.href = target;
            } else {
                el.textContent = n;
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
            exit;
        } else {
            $error = "数据保存失败，请联系管理员。";
        }
    }
}
$problems = [];
if (file_exists('problem.json')) {
    $json = file_get_contents('problem.json');
    $problems = json_decode($json, true) ?: [];
}
$fromUrlEsc = htmlspecialchars($fromUrl, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>问题反馈</title>
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'dark' && t !== 'light') {
                t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        :root {
            color-scheme: light;
            --bg: #FFFBD6;
            --surface: #FFFFFF;
            --surface-hi: #FFFEF0;
            --text: #1F2937;
            --text-secondary: #6B7280;
            --text-faint: #9CA3AF;
            --separator: #ECE6C2;
            --accent: #4F46E5;
            --accent-hi: #4338CA;
            --accent-ring: rgba(79, 70, 229, .18);
            --danger-bg: #FEE2E2;
            --danger-text: #DC2626;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #121212;
            --surface: #1E1E1E;
            --surface-hi: #2A2A2A;
            --text: #E8E8E8;
            --text-secondary: #A0A0A0;
            --text-faint: #707070;
            --separator: #2E2E2E;
            --accent: #818CF8;
            --accent-hi: #A5B4FC;
            --accent-ring: rgba(129, 140, 248, .32);
            --danger-bg: #3A1F1F;
            --danger-text: #FCA5A5;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            transition: background-color .25s ease, color .25s ease;
        }
        /* ---------- App bar ---------- */
        .app-bar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 4px;
            height: 56px;
            padding: 0 8px;
            padding-top: env(safe-area-inset-top);
            background: var(--bg);
            border-bottom: 1px solid var(--separator);
            transition: background-color .25s ease, border-color .25s ease;
        }
        .app-bar-title {
            flex: 1;
            font-size: 17px;
            font-weight: 600;
            text-align: center;
            letter-spacing: .2px;
            color: var(--text);
        }
        .app-bar-btn {
            flex: 0 0 auto;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--text);
            border-radius: 12px;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            text-decoration: none;
            transition: background-color .15s ease, transform .1s ease;
        }
        .app-bar-btn:hover { background: var(--surface); }
        .app-bar-btn:active { transform: scale(.9); }
        .app-bar-btn svg { display: block; }
        /* ---------- Main ---------- */
        .app-main {
            max-width: 640px;
            margin: 0 auto;
            padding: 20px 16px calc(48px + env(safe-area-inset-bottom));
        }
        /* ---------- Form ---------- */
        .form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .field {
            display: flex;
            flex-direction: column;
        }
        .field label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: .3px;
        }
        .field .req { color: var(--danger-text); margin-left: 2px; }
        .field input,
        .field textarea {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            border: 1px solid transparent;
            border-radius: 12px;
            transition: border-color .15s ease, box-shadow .15s ease,
                        background-color .25s ease, color .25s ease;
        }
        .field input::placeholder,
        .field textarea::placeholder { color: var(--text-faint); }
        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
            background: var(--surface-hi);
        }
        .field textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        .field input:-webkit-autofill,
        .field textarea:-webkit-autofill {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
        }
        .counter {
            align-self: flex-end;
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-faint);
        }
        /* ---------- Button ---------- */
        .btn-primary {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: .5px;
            cursor: pointer;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-primary:hover { background: var(--accent-hi); }
        .btn-primary:active { transform: scale(.98); }
        .btn-primary:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        /* ---------- Alert ---------- */
        .alert {
            padding: 12px 14px;
            background: var(--danger-bg);
            color: var(--danger-text);
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 18px;
        }
        /* ---------- Section ---------- */
        .section { margin-top: 32px; }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            letter-spacing: .8px;
            text-transform: uppercase;
            margin: 0 4px 10px;
        }
        /* ---------- List ---------- */
        .list {
            background: var(--surface);
            border-radius: 14px;
            overflow: hidden;
            transition: background-color .25s ease;
        }
        .list-item {
            padding: 14px 16px;
            border-bottom: 1px solid var(--separator);
            transition: border-color .25s ease;
        }
        .list-item:last-child { border-bottom: none; }
        .list-item-head {
            display: flex;
            justify-content: flex-end;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 6px;
        }
        .list-item-time {
            font-size: 12px;
            color: var(--text-faint);
            white-space: nowrap;
        }
        .list-item-body {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.55;
            word-break: break-word;
        }
        .empty {
            text-align: center;
            padding: 40px 16px;
            color: var(--text-faint);
            font-size: 14px;
            background: var(--surface);
            border-radius: 14px;
        }
    </style>
</head>
<body>
    <header class="app-bar">
        <a class="app-bar-btn" href="<?php echo $fromUrlEsc; ?>" aria-label="返回">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
        <div class="app-bar-title">问题反馈</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>
    <main class="app-main">
        <?php if (isset($error) && $error !== ''): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" class="form">
            <!-- 隐藏域提交id和email，前端不展示输入框 -->
            <input type="hidden" name="id" value="123456">
            <input type="hidden" name="email" value="demo@example.com">
            <div class="field">
                <label for="f-feedback">反馈内容<span class="req">*</span></label>
                <textarea id="f-feedback" name="feedback" required maxlength="300"
                          placeholder="请描述您遇到的问题（最多300字）"><?php echo isset($error) ? htmlspecialchars($_POST['feedback'] ?? '') : ''; ?></textarea>
                <div class="counter"><span id="cnt">0</span>/300</div>
            </div>
            <button class="btn-primary" type="submit">提交反馈</button>
        </form>
        <section class="section">
            <div class="section-title">已提交的问题</div>
            <?php if (empty($problems)): ?>
                <div class="empty">暂无反馈记录</div>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($problems as $item): ?>
                        <div class="list-item">
                            <div class="list-item-head">
                                <span class="list-item-time"><?php echo htmlspecialchars($item['date']); ?></span>
                            </div>
                            <div class="list-item-body"><?php echo nl2br(htmlspecialchars($item['feedback'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        // ---------- 主题切换 ----------
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('theme-toggle');
            function sync() {
                var dark = root.getAttribute('data-theme') === 'dark';
                btn.textContent = dark ? '☀️' : '🌙';
                var label = dark ? '切换到浅色模式' : '切换到深色模式';
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
            }
            btn.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try { localStorage.setItem('theme', next); } catch (e) {}
                sync();
            });
            sync();
            try {
                var mq = window.matchMedia('(prefers-color-scheme: dark)');
                var onSysChange = function (e) {
                    var saved = null;
                    try { saved = localStorage.getItem('theme'); } catch (err) {}
                    if (saved) return;
                    root.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                    sync();
                };
                if (mq.addEventListener) mq.addEventListener('change', onSysChange);
                else if (mq.addListener) mq.addListener(onSysChange);
            } catch (e) {}
        })();
        // ---------- 字数统计 ----------
        (function () {
            var ta = document.querySelector('textarea');
            var cnt = document.getElementById('cnt');
            if (ta && cnt) {
                var update = function () { cnt.textContent = ta.value.length; };
                update();
                ta.addEventListener('input', update);
            }
        })();
    </script>
</body>
</html>
