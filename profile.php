<?php
// 设置页面编码
header('Content-Type: text/html; charset=utf-8');

/* ============================================================
 * 头像更新接口（同一个文件内处理，不新增页面）
 * ============================================================ */
define('ROOT_JSON',     __DIR__ . '/root.json');        // 数据文件（与本文件同目录）
define('PORTRAIT_DIR',  __DIR__ . '/portrait-img');     // 头像目录
define('PORTRAIT_URL',  'portrait-img');                // 头像相对路径前缀
define('MAX_AVATAR',    5 * 1024 * 1024);               // 头像最大 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {

    header('Content-Type: application/json; charset=utf-8');

    $reply = function ($ok, $msg, $extra = array()) {
        echo json_encode(array_merge(array('ok' => $ok, 'msg' => $msg), $extra),
                         JSON_UNESCAPED_UNICODE);
        exit;
    };

    // ---------- 1. 定位用户 ----------
    $uid = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
    if ($uid === '') $reply(false, '缺少用户标识');

    if (!is_file(ROOT_JSON))      $reply(false, '数据文件不存在');
    if (!is_writable(ROOT_JSON))  $reply(false, '数据文件不可写，请检查权限');

    $users = json_decode(file_get_contents(ROOT_JSON), true);
    if (!is_array($users)) $reply(false, '数据文件解析失败');

    $idx = -1;
    foreach ($users as $i => $u) {
        if (isset($u['id']) && (string)$u['id'] === $uid) { $idx = $i; break; }
    }
    if ($idx < 0) $reply(false, '用户不存在');

    // ---------- 2. 头像校验 ----------
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        $reply(false, '请先选择一张头像');
    }

    $f = $_FILES['avatar'];

    if ($f['error'] !== UPLOAD_ERR_OK) $reply(false, '头像上传失败（错误码 ' . $f['error'] . '）');
    if ($f['size'] > MAX_AVATAR)       $reply(false, '头像不能超过 5MB');

    $info  = @getimagesize($f['tmp_name']);
    $allow = array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    );
    if (!$info || !isset($allow[$info[2]])) $reply(false, '仅支持 JPG / PNG / GIF / WEBP 图片');

    if (!is_dir(PORTRAIT_DIR) && !@mkdir(PORTRAIT_DIR, 0755, true)) $reply(false, '头像目录创建失败');
    if (!is_writable(PORTRAIT_DIR))                                 $reply(false, '头像目录不可写');

    // ---------- 3. 落盘 ----------
    $ext   = $allow[$info[2]];
    $fname = 'custom_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest  = PORTRAIT_DIR . '/' . $fname;

    if (!@move_uploaded_file($f['tmp_name'], $dest)) $reply(false, '头像保存失败');

    $users[$idx]['portrait'] = PORTRAIT_URL . '/' . $fname;

    // ---------- 4. 原子写回 ----------
    $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) $reply(false, '数据序列化失败');

    $tmp = ROOT_JSON . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) $reply(false, '数据写入失败');
    if (!@rename($tmp, ROOT_JSON)) { @unlink($tmp); $reply(false, '数据保存失败'); }

    $reply(true, '头像已更新', array('portrait' => $users[$idx]['portrait']));
}

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
            --success: #10B981;
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
            --success: #34D399;
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

        /* 头像（含可点击包裹层）——块级 + 自动居中，独占一行 */
        .avatar-wrap {
            position: relative;
            display: block;
            width: 88px;
            height: 88px;
            margin: 0 auto 16px;
            border-radius: 50%;
            transition: opacity .15s ease, transform .1s ease;
        }
        .avatar-wrap.editable { cursor: pointer; }
        .avatar-wrap.editable:active { transform: scale(.94); }

        .avatar {
            position: relative;
            display: block;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface);
            border: 3px solid rgba(255, 255, 255, .85);
            box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
            transition: border-color .25s ease, background-color .25s ease;
        }
        html[data-theme="dark"] .avatar {
            border-color: rgba(255, 255, 255, .35);
            box-shadow: 0 6px 18px rgba(0, 0, 0, .5);
        }

        /* 头像右下角小相机徽标（仅登录后显示） */
        .avatar-edit-badge {
            display: none;
            position: absolute;
            right: -2px;
            bottom: -2px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            border: 2px solid var(--surface);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .25);
            pointer-events: none;
        }
        .avatar-wrap.editable .avatar-edit-badge { display: flex; }

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

        /* ---------- 更换头像弹层 ---------- */
        .modal-mask {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom));
            background: rgba(0, 0, 0, .45);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
            animation: maskIn .18s ease;
        }
        .modal-mask[hidden] { display: none; }
        @keyframes maskIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-card {
            width: 100%;
            max-width: 340px;
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--separator);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
            animation: cardIn .2s cubic-bezier(.2, .8, .3, 1);
        }
        @keyframes cardIn {
            from { opacity: 0; transform: scale(.94) translateY(8px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
        }

        .edit-avatar {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            background: var(--surface-hi);
            border: 2px dashed var(--separator);
            transition: border-color .15s ease, transform .1s ease;
        }
        .edit-avatar:hover { border-color: var(--accent); }
        .edit-avatar:active { transform: scale(.96); }
        .edit-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .edit-avatar-tip {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            padding: 5px 2px;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            font-size: 12px;
            line-height: 1.2;
            text-align: center;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-cancel,
        .btn-save {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: background-color .15s ease, opacity .15s ease, transform .1s ease;
        }
        .btn-cancel {
            background: var(--surface-hi);
            color: var(--text);
            border: 1px solid var(--separator);
        }
        .btn-cancel:active { transform: scale(.98); }
        .btn-save {
            background: var(--accent);
            color: #fff;
        }
        .btn-save:hover { background: var(--accent-hi); }
        .btn-save:active { transform: scale(.98); }
        .btn-save:disabled { opacity: .6; cursor: not-allowed; }

        .modal-msg {
            margin-top: 10px;
            min-height: 18px;
            font-size: 13px;
            text-align: center;
            color: var(--danger-solid);
        }
        .modal-msg.ok { color: var(--success); }
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
            <div class="avatar-wrap" id="avatarWrap">
                <img id="avatar" src="default-avatar.jpg" alt="头像" class="avatar"
                     loading="lazy" draggable="false"
                     onerror="this.src='default-avatar.jpg'">
                <span class="avatar-edit-badge" aria-hidden="true">
                    <i class="fa-solid fa-camera"></i>
                </span>
            </div>
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

            <a href="./notebook.html" class="link-row">
                <div class="row-icon"><i class="fas fa-sticky-note"></i></div>
                <div class="row-main">
                    <div class="row-title">笔记本</div>
                    <div class="row-desc">本地存储，安全便捷</div>
                </div>
                <span class="chevron">›</span>
            </a>

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

            <a href="about.html" class="link-row">
                <div class="row-icon"><i class="fa-solid fa-circle-info"></i></div>
                <div class="row-main">
                    <div class="row-title">关于</div>
                    <div class="row-desc">段游孤独一生</div>
                </div>
                <span class="chevron">›</span>
            </a>

            <a href="update-data.html" class="link-row">
                <div class="row-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="row-main">
                    <div class="row-title">更新日志</div>
                    <div class="row-desc">项目的更新历史</div>
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

    <!-- ================= 更换头像弹层 ================= -->
    <div class="modal-mask" id="editModal" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="editTitle">
            <div class="modal-title" id="editTitle">更换头像</div>
            <p style="text-align: center;font-size:small;">注意:已发表的帖子头像保持原样，保留记忆感</p>

            <div class="edit-avatar" id="editAvatarBox" title="点击选择图片">
                <img id="editAvatarPreview" src="default-avatar.jpg" alt="头像预览">
                <div class="edit-avatar-tip"><i class="fa-solid fa-camera"></i> 点击更换</div>
            </div>
            <input type="file" id="avatarFile" accept="image/*" hidden>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="editCancel">取消</button>
                <button type="button" class="btn-save" id="editSave">保存</button>
            </div>
            <div class="modal-msg" id="editMsg"></div>
        </div>
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

        // ---------- 通用引用 ----------
        var API_URL = window.location.pathname;   // 提交到当前页面（同一文件处理）

        var elAvatarWrap   = document.getElementById('avatarWrap');
        var elAvatar       = document.getElementById('avatar');
        var elUsername     = document.getElementById('username');
        var elUserId       = document.getElementById('user-id');
        var elSystemId     = document.getElementById('system_id');
        var elQqNumber     = document.getElementById('qq-number');
        var elGender       = document.getElementById('gender');
        var elHero         = document.getElementById('profileHero');

        var elModal        = document.getElementById('editModal');
        var elModalMsg     = document.getElementById('editMsg');
        var elAvatarBox    = document.getElementById('editAvatarBox');
        var elAvatarPrev   = document.getElementById('editAvatarPreview');
        var elAvatarFile   = document.getElementById('avatarFile');
        var elBtnCancel    = document.getElementById('editCancel');
        var elBtnSave      = document.getElementById('editSave');

        var pendingAvatarFile = null;   // 待上传的新头像文件
        var pendingPreviewUrl = null;   // 预览用的 objectURL

        // ---------- 读取本地用户 ----------
        function getStoredUser() {
            try { return JSON.parse(localStorage.getItem('userInfo')); } catch (e) { return null; }
        }

        // ---------- 加载用户信息 ----------
        document.addEventListener('DOMContentLoaded', function () {
            var user = getStoredUser();
            if (user && user.id) renderUser(user);
            else renderGuest();
        });

        function renderUser(userInfo) {
            elAvatar.src = userInfo.portrait || 'default-avatar.jpg';
            elUsername.textContent = userInfo.pname || '匿名用户';
            elUserId.textContent = 'ID: ' + (userInfo.id || '-');
            elSystemId.textContent = userInfo.system_id ? ('SYS-' + userInfo.system_id) : '未分配';
            elQqNumber.textContent = userInfo.id || '-';

            var g = userInfo.gender;
            elGender.textContent = (g === '男' || g === '女' || g === '保密') ? g : '未知';

            // 已登录：整个卡片不再跳转登录，只有头像可编辑
            elHero.classList.remove('clickable');
            elHero.onclick = null;

            elAvatarWrap.classList.add('editable');
            elAvatarWrap.onclick = function (e) {
                e.stopPropagation();
                openEditor();
            };
        }

        function renderGuest() {
            elAvatar.src = 'default-avatar.jpg';
            elUsername.textContent = '游客';
            elUserId.textContent = '未登录 · 点击登录';
            elSystemId.textContent = '-';
            elQqNumber.textContent = '请登录';
            elGender.textContent = '未知';

            elAvatarWrap.classList.remove('editable');
            elAvatarWrap.onclick = null;

            elHero.classList.add('clickable');
            elHero.onclick = function () {
                window.location.href = 'core.php';
            };
        }

        // ---------- 弹层控制 ----------
        function setMsg(text, ok) {
            elModalMsg.textContent = text || '';
            elModalMsg.className = 'modal-msg' + (ok ? ' ok' : '');
        }

        function openEditor() {
            var user = getStoredUser();
            if (!user || !user.id) {
                window.location.href = 'core.php';
                return;
            }

            // 重置临时状态
            pendingAvatarFile = null;
            if (pendingPreviewUrl) {
                URL.revokeObjectURL(pendingPreviewUrl);
                pendingPreviewUrl = null;
            }
            elAvatarFile.value = '';

            elAvatarPrev.src = user.portrait || 'default-avatar.jpg';
            setMsg('');

            elModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeEditor() {
            elModal.hidden = true;
            document.body.style.overflow = '';

            if (pendingPreviewUrl) {
                URL.revokeObjectURL(pendingPreviewUrl);
                pendingPreviewUrl = null;
            }
            pendingAvatarFile = null;
            elAvatarFile.value = '';
            setMsg('');
        }

        elBtnCancel.addEventListener('click', closeEditor);

        elModal.addEventListener('click', function (e) {
            if (e.target === elModal) closeEditor();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !elModal.hidden) closeEditor();
        });

        // ---------- 选择头像 ----------
        elAvatarBox.addEventListener('click', function () {
            elAvatarFile.click();
        });

        elAvatarFile.addEventListener('change', function (e) {
            var file = e.target.files && e.target.files[0];
            if (!file) return;

            if (!/^image\//.test(file.type)) {
                setMsg('请选择图片文件');
                elAvatarFile.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                setMsg('图片不能超过 5MB');
                elAvatarFile.value = '';
                return;
            }

            if (pendingPreviewUrl) URL.revokeObjectURL(pendingPreviewUrl);
            pendingPreviewUrl = URL.createObjectURL(file);
            elAvatarPrev.src = pendingPreviewUrl;
            pendingAvatarFile = file;
            setMsg('');
        });

        // ---------- 保存头像 ----------
        elBtnSave.addEventListener('click', function () {
            var user = getStoredUser();
            if (!user || !user.id) {
                window.location.href = 'core.php';
                return;
            }

            if (!pendingAvatarFile) {
                setMsg('请先点击圆形区域选择一张图片');
                return;
            }

            var fd = new FormData();
            fd.append('action', 'update_avatar');
            fd.append('id', user.id);
            fd.append('avatar', pendingAvatarFile);

            elBtnSave.disabled = true;
            elBtnSave.textContent = '保存中…';
            setMsg('');

            fetch(API_URL, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(function (res) {
                return res.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); }
                    catch (err) { throw new Error('服务器返回异常'); }
                    if (!res.ok && !data.msg) throw new Error('请求失败（' + res.status + '）');
                    return data;
                });
            })
            .then(function (data) {
                if (!data || !data.ok) throw new Error((data && data.msg) || '保存失败');

                // 同步本地缓存
                if (data.portrait) user.portrait = data.portrait;
                try { localStorage.setItem('userInfo', JSON.stringify(user)); } catch (e) {}

                renderUser(user);

                elBtnSave.disabled = false;
                elBtnSave.textContent = '保存';
                setMsg(data.msg || '头像已更新', true);
                setTimeout(closeEditor, 600);
            })
            .catch(function (err) {
                elBtnSave.disabled = false;
                elBtnSave.textContent = '保存';
                setMsg(err && err.message ? err.message : '网络错误，请稍后重试');
            });
        });

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