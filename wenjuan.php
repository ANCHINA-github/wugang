<?php
session_start();

// ========== 配置 ==========
$dataFile = __DIR__ . '/surveys.json';

// ========== 记录/获取来源页 ==========
$fallbackUrl = 'index.html';
$self        = $_SERVER['PHP_SELF'];
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$fromUrl     = $_SESSION['survey_from'] ?? $fallbackUrl;

if ($referer !== '' && strpos($referer, $self) === false) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost === null || $refHost === '' || strcasecmp($refHost, $curHost) === 0) {
        $_SESSION['survey_from'] = $referer;
        $fromUrl = $referer;
    }
}

// ========== 处理表单提交 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname   = trim($_POST['nickname'] ?? '');
    $qq         = trim($_POST['qq'] ?? '');
    $comfort    = $_POST['comfort'] ?? '';
    $suggestion = trim($_POST['suggestion'] ?? '');

    $errors = [];
    if (empty($nickname)) {
        $errors[] = '请输入您的昵称。';
    } elseif (mb_strlen($nickname, 'UTF-8') > 8) {
        $errors[] = '昵称不能超过 8 个字。';
    }

    if (empty($qq)) {
        $errors[] = '请输入您的 QQ 号。';
    } elseif (!ctype_digit($qq)) {
        $errors[] = 'QQ 号必须为纯数字。';
    } elseif (strlen($qq) > 10) {
        $errors[] = 'QQ 号不能大于 10 位数字。';
    }

    if (empty($comfort)) {
        $errors[] = '请选择网站用着是否舒服。';
    }

    if (!empty($suggestion) && mb_strlen($suggestion, 'UTF-8') > 300) {
        $errors[] = '建议内容不能超过 300 字。';
    }

    if (empty($errors)) {
        $newEntry = [
            'nickname'     => $nickname,
            'qq'           => $qq,
            'comfort'      => $comfort,
            'suggestion'   => $suggestion,
            'submitted_at' => date('Y-m-d H:i:s')
        ];

        $existingData = [];
        if (file_exists($dataFile)) {
            $jsonContent  = file_get_contents($dataFile);
            $existingData = json_decode($jsonContent, true) ?? [];
        }

        $existingData[] = $newEntry;

        $json = json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($dataFile, $json, LOCK_EX);

        $success = '感谢您的反馈！问卷已成功提交。';
        $_POST = [];
    }
}

$fromUrlEsc = htmlspecialchars($fromUrl, ENT_QUOTES, 'UTF-8');
$fromUrlJs  = json_encode($fromUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>网站使用反馈</title>
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
            --accent: #2563EB;
            --accent-hi: #1D4ED8;
            --accent-ring: rgba(37, 99, 235, .18);
            --danger-bg: #FEE2E2;
            --danger-text: #DC2626;
            --success-bg: #ECFDF5;
            --success-text: #065F46;
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

        /* ---------- Banner ---------- */
        .banner {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.55;
            margin-bottom: 18px;
            text-align: center;
        }
        .banner.success {
            background: var(--success-bg);
            color: var(--success-text);
            font-weight: 500;
        }
        .banner.error {
            background: var(--danger-bg);
            color: var(--danger-text);
            text-align: left;
        }
        .banner.error div { margin-top: 4px; }
        .banner.error div:first-child { margin-top: 0; }

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
        .field .optional {
            color: var(--text-faint);
            font-weight: 400;
            font-size: 12px;
            margin-left: 4px;
        }
        .field input[type="text"],
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
            min-height: 130px;
            resize: vertical;
            line-height: 1.55;
        }
        .field input:-webkit-autofill,
        .field textarea:-webkit-autofill {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
        }

        /* ---------- Segmented control ---------- */
        .segmented {
            display: flex;
            gap: 4px;
            background: var(--surface);
            border-radius: 12px;
            padding: 4px;
            transition: background-color .25s ease;
        }
        .segmented-item {
            position: relative;
            flex: 1;
            display: block;
        }
        .segmented-item input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .segmented-item span {
            display: block;
            padding: 11px 12px;
            text-align: center;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
            transition: background-color .18s ease, color .18s ease;
        }
        .segmented-item input:checked + span {
            background: var(--accent);
            color: #fff;
        }
        .segmented-item input:focus-visible + span {
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .segmented-item span:active { transform: scale(.98); }

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

        /* ---------- Footer note ---------- */
        .footer-note {
            text-align: center;
            color: var(--text-faint);
            font-size: 12px;
            margin-top: 24px;
            line-height: 1.6;
            letter-spacing: .2px;
        }
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
        <div class="app-bar-title">网站使用反馈</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">
        <?php if (isset($success)): ?>
            <div class="banner success"><?= htmlspecialchars($success) ?></div>
            <script>
                setTimeout(function () {
                    window.location.href = <?= $fromUrlJs ?>;
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="banner error">
                <?php foreach ($errors as $err): ?>
                    <div>• <?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" class="form">
            <div class="field">
                <label for="nickname">昵称<span class="req">*</span></label>
                <input type="text" id="nickname" name="nickname" placeholder="请输入您的昵称"
                       maxlength="8" value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label for="qq">本站 ID (QQ号)<span class="req">*</span></label>
                <input type="text" id="qq" name="qq" inputmode="numeric" pattern="[0-9]*"
                       maxlength="10" placeholder="请输入您的 QQ 号"
                       oninput="value=value.replace(/[^0-9]/g,'')"
                       value="<?= htmlspecialchars($_POST['qq'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label>这个网站用着是否舒服<span class="req">*</span></label>
                <div class="segmented">
                    <label class="segmented-item">
                        <input type="radio" name="comfort" value="是"
                               <?= (isset($_POST['comfort']) && $_POST['comfort'] == '是') ? 'checked' : '' ?> required>
                        <span>是</span>
                    </label>
                    <label class="segmented-item">
                        <input type="radio" name="comfort" value="否"
                               <?= (isset($_POST['comfort']) && $_POST['comfort'] == '否') ? 'checked' : '' ?>>
                        <span>否</span>
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="suggestion">关于网站的建议<span class="optional">最多 300 字</span></label>
                <textarea id="suggestion" name="suggestion" maxlength="300"
                          placeholder="你觉得哪些功能需要优化？
你建议增加什么功能？
哪些方面做得不够好看？
武冈美食榜和必玩榜怎么样？"
                          rows="5"><?= htmlspecialchars($_POST['suggestion'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-primary">提交反馈</button>
        </form>

        <div class="footer-note">* 为必填项<br>您的反馈对我们至关重要</div>
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