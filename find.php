<?php
/**
 * 找回密码页面 - App 风格
 * 功能：输入 QQ号 和昵称，匹配后显示密码
 * 注意：系统 ID 不可用于找回密码（防止被猜到其他用户账号）
 */
session_start();

// ========== 记录/获取来源页 ==========
$fallbackUrl = 'core.php';
$self        = $_SERVER['PHP_SELF'];
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$fromUrl     = $_SESSION['recover_from'] ?? $fallbackUrl;

if ($referer !== '' && strpos($referer, $self) === false) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost === null || $refHost === '' || strcasecmp($refHost, $curHost) === 0) {
        $_SESSION['recover_from'] = $referer;
        $fromUrl = $referer;
    }
}

$message = '';
$foundPassword = '';
$debugInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = isset($_POST['id']) ? trim($_POST['id']) : '';
    $pname = isset($_POST['pname']) ? trim($_POST['pname']) : '';

    // 只接受真实 QQ 号：5~11 位、不以 0 开头
    if (!preg_match('/^[1-9]\d{4,10}$/', $id)) {
        $message = 'QQ号必须为 5~11 位数字，且不能以 0 开头。';
    } elseif ($pname === '') {
        $message = '昵称不能为空。';
    } else {
        $jsonFile = __DIR__ . '/root.json';

        if (!file_exists($jsonFile)) {
            $message = "用户数据文件不存在，路径：" . htmlspecialchars($jsonFile);
        } elseif (!is_readable($jsonFile)) {
            $message = "用户数据文件不可读，请检查权限。";
        } else {
            $jsonContent = file_get_contents($jsonFile);
            if ($jsonContent === false) {
                $message = "读取文件失败。";
            } else {
                // 移除BOM头（兼容低版本PHP）
                $bom = pack('H*', 'EFBBBF');
                if (substr($jsonContent, 0, 3) === $bom) {
                    $jsonContent = substr($jsonContent, 3);
                    $debugInfo .= "检测到并移除了 BOM 头。";
                }

                $users = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = "JSON 解析失败：" . json_last_error_msg();
                } else {
                    $found = null;
                    foreach ($users as $user) {
                        // 只匹配 id(QQ号) + pname(昵称)，绝不匹配 system_id
                        if (isset($user['id'], $user['pname'])
                            && (string)$user['id'] === $id
                            && $user['pname'] === $pname) {
                            $found = $user;
                            break;
                        }
                    }
                    if ($found) {
                        $foundPassword = $found['password'];
                    } else {
                        $message = "未找到匹配的用户，请检查 QQ号 和昵称是否完全一致（包括大小写和空格）。";
                        // 调试信息（上线时建议关掉）
                        $debugInfo .= "当前用户列表：";
                        foreach ($users as $u) {
                            $debugInfo .= " [QQ:{$u['id']}, 昵称:{$u['pname']}]";
                        }
                    }
                }
            }
        }
    }
}

$fromUrlEsc = htmlspecialchars($fromUrl, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>找回密码</title>
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
            --success-bg: #ECFDF5;
            --success-text: #065F46;
            --success-border: #A7F3D0;
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
            --success-bg: #103028;
            --success-text: #6EE7B7;
            --success-border: #065F46;
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
            padding: 24px 16px calc(48px + env(safe-area-inset-bottom));
        }

        .lead {
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* ---------- Banner ---------- */
        .banner {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.55;
            margin-bottom: 18px;
        }
        .banner.error {
            background: var(--danger-bg);
            color: var(--danger-text);
            text-align: left;
        }

        /* ---------- Result card ---------- */
        .result {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
            border-radius: 16px;
            padding: 22px 20px;
            margin-bottom: 22px;
            text-align: center;
            animation: pop .35s cubic-bezier(.2, .8, .3, 1.2);
        }
        @keyframes pop {
            0%   { transform: scale(.94); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }
        .result-label {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .5px;
            opacity: .85;
            margin-bottom: 10px;
        }
        .result-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1.5px;
            word-break: break-all;
            line-height: 1.3;
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
        .field input[type="text"] {
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
        .field input::placeholder { color: var(--text-faint); }
        .field input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
            background: var(--surface-hi);
        }
        .field input:-webkit-autofill {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
        }
        .hint {
            margin-top: 8px;
            padding-left: 4px;
            font-size: 12px;
            color: var(--text-faint);
            line-height: 1.5;
        }
        .hint a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .hint a:hover { text-decoration: underline; }

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
            margin-top: 4px;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-primary:hover { background: var(--accent-hi); }
        .btn-primary:active { transform: scale(.98); }
        .btn-primary:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

        .btn-secondary {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--surface);
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: .5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-secondary:hover { background: var(--surface-hi); }
        .btn-secondary:active { transform: scale(.98); }
    </style>
</head>
<body>
    <header class="app-bar">
        <a class="app-bar-btn" href="<?= $fromUrlEsc ?>" aria-label="返回">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
        <div class="app-bar-title">找回密码</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">
        <p class="lead">输入 QQ号 和昵称来验证身份</p>

        <?php if ($message !== ''): ?>
            <div class="banner error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($foundPassword !== ''): ?>
            <div class="result">
                <div class="result-label">🔑 你的密码</div>
                <div class="result-value"><?php echo htmlspecialchars($foundPassword); ?></div>
            </div>
        <?php endif; ?>

        <form method="post" class="form">
            <div class="field">
                <label for="id">QQ号<span class="req">*</span></label>
                <input type="text" id="id" name="id"
                       inputmode="numeric" pattern="[1-9]\d{4,10}" maxlength="11"
                       placeholder="例如 9*******3"
                       value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>"
                       oninput="value=value.replace(/[^0-9]/g,'').replace(/^0+/, '')"
                       required>
            </div>

            <div class="field">
                <label for="pname">昵称<span class="req">*</span></label>
                <input type="text" id="pname" name="pname"
                       placeholder="你的昵称"
                       value="<?php echo isset($_POST['pname']) ? htmlspecialchars($_POST['pname']) : ''; ?>"
                       required>
                <div class="hint">忘记昵称？去 <a href="mp.html">搜索账号</a> 找到自己的昵称</div>
            </div>

            <button type="submit" class="btn-primary">验证并找回</button>
            <a href="<?= $fromUrlEsc ?>" class="btn-secondary">返回</a>
        </form>
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
    </script>
</body>
</html>