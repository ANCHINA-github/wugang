<?php
// 记录/获取来源页 —— 与 report_view.php 共用 report_from
session_start();

$fallbackUrl = 'core.php';
$self        = basename($_SERVER['PHP_SELF']);
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$fromUrl     = $_SESSION['report_from'] ?? $fallbackUrl;

// 举报系统内部页面，不作为来源
$reportPages = ['report_submit.php', 'report_view.php'];

if ($referer !== '') {
    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    $refBase = basename(parse_url($referer, PHP_URL_PATH) ?: '');

    $sameHost = ($refHost === null || $refHost === '' || strcasecmp($refHost, $curHost) === 0);
    $isInternal = in_array($refBase, $reportPages);

    if ($sameHost && !$isInternal && $refBase !== $self) {
        $_SESSION['report_from'] = $referer;
        $fromUrl = $referer;
    }
}

$error = '';
$success = '';
// 接收网址传过来的两个昵称，自动回填
$repName   = isset($_GET['self']) ? trim(urldecode($_GET['self'])) : '';
$beRepName = isset($_GET['target']) ? trim(urldecode($_GET['target'])) : '';
$content   = '';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repName   = trim($_POST['reporter_name'] ?? '');
    $beRepName = trim($_POST['reported_name'] ?? '');
    $content   = trim($_POST['content'] ?? '');

    if (empty($repName) || empty($beRepName) || empty($content)) {
        $error = "请填写所有必填项";
    } else {
        $image_path = '';
        // 图片上传
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name   = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $file_name;
            $file_type   = mime_content_type($_FILES['image']['tmp_name']);
            $allow       = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($file_type, $allow)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = 'uploads/' . $file_name;
                } else {
                    $error = "图片上传失败";
                }
            } else {
                $error = "仅支持 JPG / PNG / GIF 图片";
            }
        }

        // 写入JSON数据
        if (empty($error)) {
            $report = [
                'id'             => time(),
                'reporter_name'  => $repName,
                'reported_name'  => $beRepName,
                'content'        => $content,
                'image'          => $image_path,
                'time'           => date('Y-m-d H:i:s')
            ];
            $json_file = __DIR__ . '/jb.json';
            $lists     = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
            if (!is_array($lists)) $lists = [];
            $lists[] = $report;

            if (file_put_contents($json_file, json_encode($lists, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX)) {
                $success = "举报提交成功！";
                $repName = $beRepName = $content = '';
            } else {
                $error = "数据保存失败";
            }
        }
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
    <title>提交举报</title>
    <link rel="stylesheet" href="repository/awesome/css/all.min.css">
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            --accent-soft: rgba(79, 70, 229, .12);
            --accent-ring: rgba(79, 70, 229, .18);
            --danger-bg: #FEE2E2;
            --danger-text: #DC2626;
            --success-bg: #ECFDF5;
            --success-text: #065F46;
            --success-border: #A7F3D0;
            --danger-solid: #EF4444;
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
            --accent-soft: rgba(129, 140, 248, .18);
            --accent-ring: rgba(129, 140, 248, .32);
            --danger-bg: #3A1F1F;
            --danger-text: #FCA5A5;
            --success-bg: #103028;
            --success-text: #6EE7B7;
            --success-border: #065F46;
            --danger-solid: #F87171;
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
        .field input:-webkit-autofill,
        .field textarea:-webkit-autofill {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
        }
        .field textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.55;
        }

        /* ---------- Upload zone ---------- */
        .upload-zone {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 28px 16px;
            border: 2px dashed var(--separator);
            border-radius: 12px;
            background: var(--surface);
            color: var(--text-secondary);
            cursor: pointer;
            text-align: center;
            transition: border-color .15s ease, background-color .15s ease,
                        color .15s ease, transform .1s ease;
        }
        .upload-zone:hover,
        .upload-zone:focus-within {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
        }
        .upload-zone:active { transform: scale(.99); }
        .upload-zone i {
            font-size: 28px;
            opacity: .85;
        }
        .upload-zone .upload-text {
            font-size: 14px;
            font-weight: 500;
        }
        .upload-zone .upload-hint {
            font-size: 12px;
            color: var(--text-faint);
            margin-top: 2px;
        }
        .upload-zone input[type="file"] {
            display: none;
        }

        /* 图片预览 */
        .upload-preview-wrap {
            position: relative;
            display: inline-block;
            margin-top: 10px;
            align-self: flex-start;
        }
        .upload-preview {
            display: block;
            max-width: 100%;
            max-height: 240px;
            border-radius: 12px;
            background: var(--surface);
        }
        .upload-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color .15s ease, transform .1s ease;
        }
        .upload-remove:hover { background: var(--danger-solid); }
        .upload-remove:active { transform: scale(.9); }

        /* ---------- Buttons ---------- */
        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: .3px;
            cursor: pointer;
            margin-top: 4px;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-primary:hover { background: var(--accent-hi); }
        .btn-primary:active { transform: scale(.98); }
        .btn-primary:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

        .btn-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--surface);
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: .3px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-secondary:hover { background: var(--surface-hi); }
        .btn-secondary:active { transform: scale(.98); }

        /* ---------- 成功卡片 ---------- */
        .success-card {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
            border-radius: 16px;
            padding: 26px 20px;
            text-align: center;
            animation: pop .35s cubic-bezier(.2, .8, .3, 1.2);
        }
        @keyframes pop {
            0%   { transform: scale(.94); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }
        .success-card .check {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: var(--success-text);
            color: var(--success-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            line-height: 1;
        }
        .success-card h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .success-card .meta {
            font-size: 13px;
            opacity: .85;
            line-height: 1.65;
        }
        .success-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 22px;
        }

        /* ---------- 底部链接 ---------- */
        .foot-links {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-top: 18px;
            font-size: 14px;
        }
        .foot-links a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .foot-links a:hover { text-decoration: underline; }
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
        <div class="app-bar-title">提交举报</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">

        <?php if ($success): ?>

            <!-- 提交成功 -->
            <div class="success-card">
                <div class="check">✓</div>
                <h3>举报已提交</h3>
                <div class="meta">
                    感谢你的反馈，管理员会尽快处理。<br>
                    <span id="countdown">2</span> 秒后自动返回…
                </div>
                <div class="success-actions">
                    <a href="report_view.php" class="btn-primary">
                        <i class="fas fa-list"></i> 查看举报列表
                    </a>
                    <a href="<?= $fromUrlEsc ?>" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> 立即返回
                    </a>
                </div>
            </div>

            <script>
                var n = 2;
                var el = document.getElementById('countdown');
                var t = setInterval(function () {
                    if (--n <= 0) {
                        clearInterval(t);
                        window.location.href = <?= $fromUrlJs ?>;
                    } else {
                        if (el) el.textContent = n;
                    }
                }, 1000);
            </script>

        <?php else: ?>

            <?php if ($error): ?>
                <div class="banner error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="form" id="reportForm">

                <div class="field">
                    <label for="reporter_name">举报人昵称<span class="req">*</span></label>
                    <input type="text" id="reporter_name" name="reporter_name"
                           placeholder="你的昵称"
                           value="<?php echo htmlspecialchars($repName); ?>"
                           required>
                </div>

                <div class="field">
                    <label for="reported_name">被举报人昵称<span class="req">*</span></label>
                    <input type="text" id="reported_name" name="reported_name"
                           placeholder="你举报谁"
                           value="<?php echo htmlspecialchars($beRepName); ?>"
                           required>
                </div>

                <div class="field">
                    <label for="content">举报内容<span class="req">*</span></label>
                    <textarea id="content" name="content"
                              placeholder="请描述你遇到的问题，越具体越好"
                              required><?php echo htmlspecialchars($content); ?></textarea>
                </div>

                <div class="field">
                    <label>举证图片<span class="optional">（选填）</span></label>

                    <label for="imageInput" class="upload-zone" id="uploadZone">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <span class="upload-text" id="uploadText">点击上传图片</span>
                        <span class="upload-hint">支持 JPG / PNG / GIF</span>
                    </label>
                    <input type="file" id="imageInput" name="image" accept="image/*">

                    <div class="upload-preview-wrap" id="previewWrap" hidden>
                        <img id="imagePreview" class="upload-preview" alt="等待上传">
                        <button type="button" class="upload-remove" id="removeImage" aria-label="移除图片">&times;</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> 提交举报
                </button>

                <div class="foot-links">
                    <a href="<?= $fromUrlEsc ?>">返回</a>
                    <a href="report_view.php">举报列表</a>
                </div>
            </form>

        <?php endif; ?>

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

        // ---------- 图片上传预览 ----------
        (function () {
            var input   = document.getElementById('imageInput');
            var preview = document.getElementById('imagePreview');
            var wrap    = document.getElementById('previewWrap');
            var zone    = document.getElementById('uploadZone');
            var txt     = document.getElementById('uploadText');
            var remove  = document.getElementById('removeImage');
            if (!input) return;

            function showPreview(file) {
                var url = URL.createObjectURL(file);
                preview.src = url;
                wrap.hidden = false;
                zone.hidden = true;
                txt.textContent = '点击上传图片';
            }

            function reset() {
                input.value = '';
                preview.src = '';
                wrap.hidden = true;
                zone.hidden = false;
            }

            input.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    showPreview(this.files[0]);
                }
            });

            remove.addEventListener('click', reset);
        })();

        // ---------- 提交时防止重复点击 ----------
        (function () {
            var form = document.getElementById('reportForm');
            if (!form) return;
            form.addEventListener('submit', function () {
                var b = form.querySelector('.btn-primary');
                if (b) {
                    b.disabled = true;
                    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 提交中…';
                }
            });
        })();
    </script>
</body>
</html>