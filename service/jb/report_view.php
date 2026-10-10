<?php
// 记录/获取来源页
session_start();

$fallbackUrl = 'core.php';
$self        = $_SERVER['PHP_SELF'];
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$fromUrl     = $_SESSION['report_list_from'] ?? $fallbackUrl;

if ($referer !== '' && strpos($referer, $self) === false) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost === null || $refHost === '' || strcasecmp($refHost, $curHost) === 0) {
        $_SESSION['report_list_from'] = $referer;
        $fromUrl = $referer;
    }
}

// 读取举报数据
$json_file = __DIR__ . '/jb.json';
$reports = [];

if (file_exists($json_file)) {
    $json_data = file_get_contents($json_file);
    $reports = json_decode($json_data, true) ?: [];
    // 按 id 降序排序（id 为时间戳）
    usort($reports, function ($a, $b) {
        return $b['id'] - $a['id'];
    });
}

$fromUrlEsc = htmlspecialchars($fromUrl, ENT_QUOTES, 'UTF-8');
$total = count($reports);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>举报列表</title>
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
            --danger-soft: rgba(239, 68, 68, .12);
            --danger: #EF4444;
            --danger-bg: #FEE2E2;
            --danger-text: #DC2626;
            --overlay: rgba(0, 0, 0, .8);
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
            --danger-soft: rgba(248, 113, 113, .18);
            --danger: #F87171;
            --danger-bg: #3A1F1F;
            --danger-text: #FCA5A5;
            --overlay: rgba(0, 0, 0, .9);
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .app-bar-title .count {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            background: var(--accent-soft);
            padding: 2px 8px;
            border-radius: 10px;
            letter-spacing: 0;
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
            padding: 16px 16px calc(48px + env(safe-area-inset-bottom));
        }

        /* ---------- 空状态 ---------- */
        .empty {
            text-align: center;
            padding: 60px 24px;
            color: var(--text-faint);
        }
        .empty i {
            font-size: 56px;
            color: var(--text-faint);
            opacity: .5;
            margin-bottom: 16px;
        }
        .empty-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .empty-desc {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        /* ---------- 举报卡片 ---------- */
        .report {
            background: var(--surface);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            transition: background-color .25s ease, border-color .25s ease;
        }
        .report-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .report-id {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-faint);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
            letter-spacing: .3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .report-time {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
            flex: 0 0 auto;
        }

        /* 关系链：举报人 → 被举报人 */
        .relation {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding: 12px;
            background: var(--surface-hi);
            border-radius: 10px;
        }
        .person {
            flex: 1;
            min-width: 0;
        }
        .person-role {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 2px;
            letter-spacing: .2px;
        }
        .person-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .person.reported .person-name { color: var(--danger); }
        .arrow {
            flex: 0 0 auto;
            color: var(--text-faint);
            font-size: 16px;
            line-height: 1;
        }

        /* 内容 */
        .report-content {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text);
            word-break: break-word;
            margin-bottom: 12px;
            white-space: pre-wrap;
        }

        /* 底部操作 */
        .report-foot {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-view-img {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 10px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-view-img:hover { background: var(--accent); color: #fff; }
        .btn-view-img:active { transform: scale(.96); }
        .no-image {
            font-size: 12px;
            color: var(--text-faint);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* ---------- 提交按钮 ---------- */
        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 20px;
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
            text-align: center;
            text-decoration: none;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-primary:hover { background: var(--accent-hi); }
        .btn-primary:active { transform: scale(.98); }
        .btn-primary:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

        /* 空状态里的主按钮不占满宽 */
        .empty .btn-primary {
            width: auto;
            display: inline-flex;
            padding: 12px 20px;
            margin-top: 0;
        }

        /* ---------- 图片查看器 ---------- */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: var(--overlay);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity .2s ease;
        }
        .modal.active {
            display: flex;
            opacity: 1;
        }
        .modal img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        .modal-close {
            position: absolute;
            top: calc(16px + env(safe-area-inset-top));
            right: calc(16px + env(safe-area-inset-right));
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, .15);
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color .15s ease, transform .1s ease;
        }
        .modal-close:hover { background: rgba(255, 255, 255, .28); }
        .modal-close:active { transform: scale(.9); }

        @media (max-width: 480px) {
            .person-name { font-size: 13px; }
            .report { padding: 14px; }
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
        <div class="app-bar-title">
            举报列表
            <?php if ($total > 0): ?>
                <span class="count"><?= $total ?></span>
            <?php endif; ?>
        </div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">

        <?php if (empty($reports)): ?>

            <!-- 空状态 -->
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <div class="empty-title">暂无举报数据</div>
                <div class="empty-desc">还没有提交任何举报记录</div>
                <a href="report_submit.php" class="btn-primary">
                    <i class="fas fa-plus"></i> 提交第一个举报
                </a>
            </div>

        <?php else: ?>

            <!-- 举报卡片列表 -->
            <?php foreach ($reports as $report): ?>
                <div class="report">
                    <div class="report-head">
                        <span class="report-id">#<?= htmlspecialchars($report['id']); ?></span>
                        <span class="report-time"><?= htmlspecialchars($report['time']); ?></span>
                    </div>

                    <div class="relation">
                        <div class="person">
                            <div class="person-role">举报人</div>
                            <div class="person-name"><?= htmlspecialchars($report['reporter_name']); ?></div>
                        </div>
                        <span class="arrow">›</span>
                        <div class="person reported">
                            <div class="person-role">被举报人</div>
                            <div class="person-name"><?= htmlspecialchars($report['reported_name']); ?></div>
                        </div>
                    </div>

                    <div class="report-content"><?= htmlspecialchars($report['content']); ?></div>

                    <div class="report-foot">
                        <?php
                        // 图片存在且文件可访问才显示按钮
                        $hasImage = !empty($report['image']) && file_exists(__DIR__ . '/' . ltrim($report['image'], '/'));
                        ?>
                        <?php if ($hasImage): ?>
                            <button type="button" class="btn-view-img"
                                    data-img="<?= htmlspecialchars($report['image'], ENT_QUOTES); ?>">
                                <i class="fas fa-image"></i> 查看图片
                            </button>
                        <?php else: ?>
                            <span class="no-image"><i class="fas fa-image"></i> 无图片</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 提交新举报 -->
            <a href="report_submit.php" class="btn-primary">
                <i class="fas fa-plus"></i> 提交新举报
            </a>

        <?php endif; ?>

    </main>

    <!-- 图片查看器 -->
    <div class="modal" id="image-modal">
        <button type="button" class="modal-close" id="modal-close" aria-label="关闭">&times;</button>
        <img id="modal-image" src="" alt="举报图片">
    </div>

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

        // ---------- 图片查看器 ----------
        (function () {
            var modal = document.getElementById('image-modal');
            var modalImg = document.getElementById('modal-image');
            var closeBtn = document.getElementById('modal-close');

            function showImage(url) {
                modalImg.src = url;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            function hideImage() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                // 等淡出后再清空 src，避免闪烁
                setTimeout(function () { modalImg.src = ''; }, 200);
            }

            // 委托绑定所有"查看图片"按钮
            document.querySelectorAll('.btn-view-img').forEach(function (b) {
                b.addEventListener('click', function () {
                    showImage(b.getAttribute('data-img'));
                });
            });

            closeBtn.addEventListener('click', hideImage);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) hideImage();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) hideImage();
            });
        })();
    </script>
</body>
</html>