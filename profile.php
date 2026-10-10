<?php
// 设置页面编码
header('Content-Type: text/html; charset=utf-8');
// 获取当前文件名作为引用标识
$currentPage = basename(__FILE__, '.php');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>我的 - 武校圈</title>
    <!-- Font Awesome 图标 -->
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

        /* ---------- 主题变量 ---------- */
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
            --danger-solid: #EF4444;
            --danger-solid-hi: #DC2626;
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
            --danger-solid: #EF4444;
            --danger-solid-hi: #F87171;
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
        .app-bar-spacer {
            pointer-events: none;
            visibility: hidden;
        }

        /* ---------- Main ---------- */
        .app-main {
            max-width: 640px;
            margin: 0 auto;
            padding: 8px 16px calc(48px + env(safe-area-inset-bottom));
        }

        /* ---------- Profile hero ---------- */
.profile-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    border-radius: 20px;
    background: var(--surface);
    border: 1px solid var(--separator);
    padding: 40px 20px 28px;
    margin-top: 12px;
    text-align: center;
    transition: opacity .15s ease, border-color .25s ease, background-color .25s ease;
}
.profile-hero.clickable { cursor: pointer; }
.profile-hero.clickable:active { opacity: .75; }

/* 背景图 + 遮罩 */
.profile-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url('poster2.webp');
    background-size: cover;
    background-position: center;
    z-index: -2;
}
.profile-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    /* 浅色模式：偏白遮罩，让深色文字读得清 */
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, .55) 0%,
        rgba(255, 251, 214, .85) 100%
    );
    transition: background .25s ease;
}
html[data-theme="dark"] .profile-hero::after {
    /* 深色模式：偏黑遮罩，让浅色文字读得清 */
    background: linear-gradient(
        180deg,
        rgba(18, 18, 18, .55) 0%,
        rgba(18, 18, 18, .88) 100%
    );
}

.avatar {
    position: relative;
    width: 88px;
    height: 88px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--surface);
    border: 3px solid rgba(255, 255, 255, .85);
    box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
    margin-bottom: 16px;
    transition: border-color .25s ease, background-color .25s ease;
}
html[data-theme="dark"] .avatar {
    border-color: rgba(255, 255, 255, .35);
    box-shadow: 0 6px 18px rgba(0, 0, 0, .5);
}

.profile-name {
    position: relative;
    font-size: 20px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: .3px;
    line-height: 1.3;
    margin-bottom: 6px;
    max-width: 100%;
    word-break: break-word;
    text-shadow: 0 1px 3px rgba(255, 255, 255, .5);
}
html[data-theme="dark"] .profile-name {
    text-shadow: 0 1px 3px rgba(0, 0, 0, .6);
}

.profile-id {
    position: relative;
    font-size: 13px;
    color: var(--text-secondary);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
    letter-spacing: .5px;
    text-shadow: 0 1px 3px rgba(255, 255, 255, .45);
}
html[data-theme="dark"] .profile-id {
    text-shadow: 0 1px 3px rgba(0, 0, 0, .55);
}
        /* ---------- Section title ---------- */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            letter-spacing: .8px;
            text-transform: uppercase;
            margin: 24px 4px 10px;
        }

        /* ---------- List ---------- */
        .list {
            background: var(--surface);
            border-radius: 14px;
            overflow: hidden;
            transition: background-color .25s ease;
        }

        /* 信息行（左标签 + 右值） */
        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--separator);
            transition: border-color .25s ease;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            color: var(--text-secondary);
            font-size: 15px;
            flex: 0 0 auto;
        }
        .info-value {
            color: var(--text);
            font-size: 15px;
            font-weight: 500;
            text-align: right;
            word-break: break-all;
        }

        /* 链接行（图标 + 主副标题 + 箭头） */
        .link-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--separator);
            text-decoration: none;
            color: var(--text);
            transition: background-color .15s ease, border-color .25s ease;
        }
        .link-row:last-child { border-bottom: none; }
        .link-row:hover,
        .link-row:active { background: var(--surface-hi); }

        .row-icon {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: background-color .25s ease, color .25s ease;
        }
        .row-main {
            flex: 1;
            min-width: 0;
        }
        .row-title {
            font-size: 15px;
            font-weight: 500;
            color: var(--text);
            line-height: 1.3;
            margin-bottom: 2px;
        }
        .row-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .chevron {
            flex: 0 0 auto;
            color: var(--text-faint);
            font-size: 22px;
            line-height: 1;
            margin-left: 4px;
            font-weight: 300;
        }

        /* ---------- Logout button ---------- */
        .btn-logout {
            width: 100%;
            margin-top: 24px;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--danger-solid);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: .3px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background-color .15s ease, transform .1s ease;
        }
        .btn-logout:hover { background: var(--danger-solid-hi); }
        .btn-logout:active { transform: scale(.98); }
        .btn-logout:focus-visible { outline: 2px solid var(--danger-solid); outline-offset: 3px; }
    </style>
</head>
<body>

    <header class="app-bar">
        <span class="app-bar-btn app-bar-spacer" aria-hidden="true"></span>
        <div class="app-bar-title">我的</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">

        <!-- 用户信息 -->
        <section class="profile-hero" id="profileHero">
            <img id="avatar" src="default-avatar.jpg" alt="头像" class="avatar"
                 loading="lazy" draggable="false"
                 onerror="this.src='default-avatar.jpg'">
            <div id="username" class="profile-name">游客</div>
            <div id="user-id" class="profile-id">未登录</div>
        </section>

        <!-- 账号信息 -->
        <div class="section-title">账号信息</div>
        <div class="list">
            <div class="info-row">
                <span class="info-label">系统 ID</span>
                <span class="info-value" id="system_id">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">QQ 号码</span>
                <span class="info-value" id="qq-number">请登录</span>
            </div>
            <div class="info-row">
                <span class="info-label">性别</span>
                <span class="info-value" id="gender">未知</span>
            </div>
        </div>

        <!-- 服务 -->
        <div class="section-title">服务</div>
        <div class="list">

            <a href="wugang.apk" class="link-row">
                <div class="row-icon"><i class="fa-solid fa-download"></i></div>
                <div class="row-main">
                    <div class="row-title">下载武校圈 APP</div>
                    <div class="row-desc">更加快捷的访问（仅安卓）</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="login-data.php" class="link-row">
                <div class="row-icon"><i class="fa fa-calendar"></i></div>
                <div class="row-main">
                    <div class="row-title">账号登录记录</div>
                    <div class="row-desc">查看你的登录历史</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="posts_history.php" class="link-row">
                <div class="row-icon"><i class="fa fa-history"></i></div>
                <div class="row-main">
                    <div class="row-title">历史动态</div>
                    <div class="row-desc">查看和删除你的历史动态</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="service/problem/problem.php" class="link-row">
                <div class="row-icon"><i class="fas fa-question-circle"></i></div>
                <div class="row-main">
                    <div class="row-title">反馈问题</div>
                    <div class="row-desc">向开发者报告问题</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="find.php" class="link-row">
                <div class="row-icon"><i class="fas fa-lock"></i></div>
                <div class="row-main">
                    <div class="row-title">忘记密码</div>
                    <div class="row-desc">找回你的账户密码</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="change_password.php" class="link-row">
                <div class="row-icon"><i class="fas fa-key"></i></div>
                <div class="row-main">
                    <div class="row-title">修改密码</div>
                    <div class="row-desc">更改你的账号密码</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="wenjuan.php" class="link-row">
                <div class="row-icon"><i class="fas fa-crown"></i></div>
                <div class="row-main">
                    <div class="row-title">成为管理员</div>
                    <div class="row-desc">申请成为管理员</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="#about" class="link-row">
                <div class="row-icon"><i class="fa-solid fa-circle-info"></i></div>
                <div class="row-main">
                    <div class="row-title">关于我们</div>
                    <div class="row-desc">网站的历史</div>
                </div>
                <span class="chevron">›</span>
            </a>

        </div>

        <!-- 危险操作 -->
        <div class="section-title">危险操作</div>
        <div class="list">
            <a href="javascript:void(0);" onclick="applyForDeletion()" class="link-row">
                <div class="row-icon" style="background: rgba(239, 68, 68, .12); color: #EF4444;">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="row-main">
                    <div class="row-title">申请注销</div>
                    <div class="row-desc">申请注销你的账户（不可恢复）</div>
                </div>
                <span class="chevron">›</span>
            </a>
        </div>

        <!-- 退出登录 -->
        <button type="button" onclick="logout()" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> 退出登录
        </button>

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

        // ---------- 加载用户信息 ----------
        document.addEventListener('DOMContentLoaded', function () {
            loadUserProfile();
        });

        function loadUserProfile() {
            var userInfo = null;
            try { userInfo = JSON.parse(localStorage.getItem('userInfo')); } catch (e) {}

            var userAvatar = document.getElementById('avatar');
            var username   = document.getElementById('username');
            var userId     = document.getElementById('user-id');
            var systemId   = document.getElementById('system_id');
            var qqNumber   = document.getElementById('qq-number');
            var gender     = document.getElementById('gender');
            var hero       = document.getElementById('profileHero');

            if (userInfo) {
                userAvatar.src = userInfo.portrait || 'default-avatar.jpg';
                username.textContent = userInfo.pname || '匿名用户';
                userId.textContent = 'ID: ' + (userInfo.id || '-');
                systemId.textContent = userInfo.system_id ? ('SYS-' + userInfo.system_id) : '未分配';
                qqNumber.textContent = userInfo.id || '-';

                var g = userInfo.gender;
                if (g === '男' || g === '女' || g === '保密') {
                    gender.textContent = g;
                } else {
                    gender.textContent = '未知';
                }

                hero.classList.remove('clickable');
                hero.onclick = null;
            } else {
                userAvatar.src = 'default-avatar.jpg';
                username.textContent = '游客';
                userId.textContent = '未登录 · 点击登录';
                systemId.textContent = '-';
                qqNumber.textContent = '请登录';
                gender.textContent = '未知';

                hero.classList.add('clickable');
                hero.onclick = function () {
                    window.location.href = 'core.php';
                };
            }
        }

        // ---------- 退出登录 ----------
        function logout() {
            if (!confirm('确定要退出登录吗？')) return;
            try {
                localStorage.removeItem('userInfo');
                localStorage.removeItem('lastLoginId');
                localStorage.removeItem('likedPosts');
                localStorage.removeItem('likedComments');
            } catch (e) {}
            alert('已退出登录');
            window.location.href = 'core.php';
        }

        // ---------- 申请注销 ----------
        function applyForDeletion() {
            var userInfo = null;
            try { userInfo = JSON.parse(localStorage.getItem('userInfo')); } catch (e) {}
            if (!userInfo) {
                alert('请先登录');
                return;
            }
            if (confirm('确定要注销账户 [' + userInfo.pname + '] 吗？此操作不可恢复！')) {
                window.location.href = 'cancel.php?uid=' + encodeURIComponent(userInfo.id);
            }
        }
    </script>
</body>
</html>