<?php
// 1. 初始化会话
session_start();

// 启用错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ========== 记录/获取来源页 ==========
$fallbackUrl = 'index.html';
$self        = $_SERVER['PHP_SELF'];
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$fromUrl     = $_SESSION['register_from'] ?? $fallbackUrl;

if ($referer !== '' && strpos($referer, $self) === false) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost === null || $refHost === '' || strcasecmp($refHost, $curHost) === 0) {
        $_SESSION['register_from'] = $referer;
        $fromUrl = $referer;
    }
}

// 常量定义
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('DEFAULT_PORTRAIT', 'portrait-img/default-avatar.jpg');
define('UPLOAD_DIR', 'portrait-img/');

// 初始化变量
$msg = '';
$msgType = '';
$newSystemId = '';
$formData = [
    'pname' => '',
    'gender' => '',
    'portrait' => DEFAULT_PORTRAIT,
    'qq' => ''
];

// 2. 辅助函数
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 密码：6~20 位，只允许英文和数字
function validatePassword($password) {
    $len = strlen($password);
    if ($len < 6 || $len > 20) {
        return '密码长度需为6-20位';
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $password)) {
        return '密码只能包含英文和数字';
    }
    return true;
}

// 昵称：1~14 个字，允许中文/英文/数字/下划线
function validateUsername($username) {
    $charLen = mb_strlen($username, 'UTF-8');
    if ($charLen < 1 || $charLen > 14) {
        return '昵称需1-14个字';
    }
    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $username)) {
        return '昵称只能包含中文、英文、数字和下划线';
    }
    return true;
}

function validateQQ($qq) {
    if (!preg_match('/^[1-9][0-9]{4,11}$/', $qq)) {
        return 'QQ号码必须为5~12位数字，且不能以0开头';
    }
    return true;
}

function checkUsernameExists($username, $users) {
    foreach ($users as $user) {
        if ($user['pname'] === $username) {
            return true;
        }
    }
    return false;
}

function checkQQExists($qq, $users) {
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] == $qq) {
            return true;
        }
    }
    return false;
}

function getNextSystemId($users) {
    $maxId = 0;
    foreach ($users as $user) {
        $sid = isset($user['system_id']) ? $user['system_id'] : (isset($user['id']) && is_numeric($user['id']) ? $user['id'] : 0);
        if ($sid && (int)$sid > $maxId) {
            $maxId = (int)$sid;
        }
    }
    return str_pad($maxId + 1, 10, '0', STR_PAD_LEFT);
}

function handleFileUpload($fileField, &$msg, &$msgType) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        $msg = '请选择要上传的文件';
        $msgType = 'error';
        return null;
    }

    $file = $_FILES[$fileField];

    if ($file['size'] > MAX_FILE_SIZE) {
        $msg = '文件大小不能超过2MB';
        $msgType = 'error';
        return null;
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
        $msg = '只允许上传JPG、PNG、GIF格式的图片';
        $msgType = 'error';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mime, $allowedMimes)) {
        $msg = '文件类型不被允许';
        $msgType = 'error';
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $safeName = 'custom_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
    $targetPath = UPLOAD_DIR . $safeName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }

    $msg = '文件上传失败';
    $msgType = 'error';
    return null;
}

// 3. 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 处理头像上传 ---
    if ($action === 'upload_portrait') {
        if (isset($_FILES['custom_portrait'])) {
            $uploadedPath = handleFileUpload('custom_portrait', $msg, $msgType);
            if ($uploadedPath) {
                $_SESSION['temp_portrait'] = $uploadedPath;
                $msg = '头像上传成功！';
                $msgType = 'success';
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'portraitPath' => $uploadedPath
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $msg
                ]);
                exit;
            }
        }
    }

    // --- 处理头像选择 ---
    elseif ($action === 'select_portrait') {
        $selectedPortrait = $_POST['portrait'] ?? DEFAULT_PORTRAIT;
        if (isset($_SESSION['temp_portrait'])) {
            unset($_SESSION['temp_portrait']);
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'portraitPath' => $selectedPortrait
        ]);
        exit;
    }

    // --- 处理注册 ---
    elseif ($action === 'register') {
        $pname = sanitizeInput($_POST['pname'] ?? '');
        $qq = sanitizeInput($_POST['qq'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPwd = $_POST['confirmPwd'] ?? '';
        $gender = $_POST['gender'] ?? '';

        $finalPortrait = DEFAULT_PORTRAIT;
        if (isset($_SESSION['temp_portrait'])) {
            $finalPortrait = $_SESSION['temp_portrait'];
        } elseif (!empty($_POST['portrait'])) {
            $finalPortrait = $_POST['portrait'];
        }

        $errors = [];

        $usernameValidation = validateUsername($pname);
        if ($usernameValidation !== true) {
            $errors[] = $usernameValidation;
        }

        $qqValidation = validateQQ($qq);
        if ($qqValidation !== true) {
            $errors[] = $qqValidation;
        }

        $passwordValidation = validatePassword($password);
        if ($passwordValidation !== true) {
            $errors[] = $passwordValidation;
        } elseif ($password !== $confirmPwd) {
            $errors[] = '两次输入的密码不一致';
        }

        if (!in_array($gender, ['男', '女', '保密'])) {
            $errors[] = '请选择有效的性别';
        }

        if (!empty($errors)) {
            $msg = implode('<br>', $errors);
            $msgType = 'error';
        } else {
            $jsonFile = 'root.json';
            $users = [];
            if (file_exists($jsonFile)) {
                $jsonContent = file_get_contents($jsonFile);
                $users = json_decode($jsonContent, true) ?? [];
            }

            if (checkUsernameExists($pname, $users)) {
                $msg = '该昵称已被注册，请更换一个';
                $msgType = 'error';
            }
            elseif (checkQQExists($qq, $users)) {
                $msg = '该QQ号码已被绑定，请更换或联系管理员';
                $msgType = 'error';
            }
            else {
                $newSystemId = getNextSystemId($users);

                $newUser = [
                    'id' => $qq,
                    'system_id' => $newSystemId,
                    'pname' => $pname,
                    'password' => $password,
                    'gender' => $gender,
                    'portrait' => $finalPortrait,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $users[] = $newUser;

                if (file_put_contents(
                    $jsonFile,
                    json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    LOCK_EX
                )) {
                    if (isset($_SESSION['temp_portrait'])) {
                        unset($_SESSION['temp_portrait']);
                    }

                    $msg = '注册成功！您的QQ号码 ' . $qq . ' 已绑定，请使用QQ号登录。';
                    $msgType = 'success';
                } else {
                    $msg = '注册失败，无法写入用户数据文件。';
                    $msgType = 'error';
                }
            }
        }

        $formData = [
            'pname' => $pname,
            'gender' => $gender,
            'portrait' => $finalPortrait,
            'qq' => $qq
        ];
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
    <title>注册</title>
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
            --overlay: rgba(0, 0, 0, .45);
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
            --overlay: rgba(0, 0, 0, .7);
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
        .banner.success {
            background: var(--success-bg);
            color: var(--success-text);
            font-weight: 500;
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
        .field input[type="text"],
        .field input[type="password"] {
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
        .field-feedback {
            font-size: 12px;
            margin-top: 6px;
            min-height: 16px;
            line-height: 1.4;
        }
        .fb-ok   { color: #10B981; }
        .fb-err  { color: var(--danger-text); }
        .fb-wait { color: var(--text-faint); }
        html[data-theme="dark"] .fb-ok { color: #34D399; }

        /* ---------- Segmented control (性别) ---------- */
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
            transition: background-color .18s ease, color .18s ease, transform .1s ease;
        }
        .segmented-item input:checked + span {
            background: var(--accent);
            color: #fff;
        }
        .segmented-item input:focus-visible + span {
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .segmented-item span:active { transform: scale(.98); }

        /* ---------- Portrait ---------- */
        .portrait-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 14px;
        }
        .portrait-preview {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface);
            border: 2px solid var(--separator);
            flex: 0 0 auto;
            transition: border-color .25s ease;
        }
        .portrait-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        .upload-label,
        .portrait-select-btn {
            display: block;
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            transition: background-color .15s ease, transform .1s ease;
        }
        .upload-label {
            background: var(--accent);
            color: #fff;
        }
        .upload-label:hover { background: var(--accent-hi); }
        .upload-label:active { transform: scale(.98); }
        .portrait-select-btn {
            background: var(--surface);
            color: var(--text);
        }
        .portrait-select-btn:hover { background: var(--surface-hi); }
        .portrait-select-btn:active { transform: scale(.98); }
        .upload-portrait input[type="file"] { display: none; }

        /* ---------- Submit ---------- */
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
            transition: background-color .15s ease, transform .1s ease, opacity .15s ease;
        }
        .btn-primary:hover:not(:disabled) { background: var(--accent-hi); }
        .btn-primary:active:not(:disabled) { transform: scale(.98); }
        .btn-primary:disabled {
            background: var(--surface);
            color: var(--text-faint);
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover { text-decoration: underline; }

        /* ---------- 注册成功卡片 ---------- */
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
            margin-bottom: 16px;
        }
        .success-card .label {
            font-size: 13px;
            opacity: .8;
            margin-bottom: 6px;
        }
        .user-id {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1.5px;
            background: rgba(255, 255, 255, .35);
            border-radius: 10px;
            padding: 10px 14px;
            margin: 0 auto 12px;
            max-width: 260px;
            word-break: break-all;
        }
        html[data-theme="dark"] .user-id {
            background: rgba(255, 255, 255, .08);
        }
        .success-card .meta {
            font-size: 12px;
            opacity: .8;
            line-height: 1.6;
        }
        .success-card .meta a {
            color: var(--success-text);
            font-weight: 700;
            text-decoration: underline;
        }
        .copy-notification {
            display: inline-block;
            margin: 10px 0 4px;
            padding: 5px 12px;
            background: rgba(255, 255, 255, .4);
            color: var(--success-text);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        html[data-theme="dark"] .copy-notification {
            background: rgba(255, 255, 255, .1);
        }

        /* ---------- Modal ---------- */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background-color: var(--overlay);
            justify-content: center;
            align-items: center;
            padding: 20px;
            opacity: 0;
            transition: opacity .25s ease;
        }
        .modal.active { opacity: 1; }
        .modal-card {
            background: var(--surface);
            color: var(--text);
            width: 100%;
            max-width: 520px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .3);
            transform: translateY(20px) scale(.96);
            transition: transform .25s ease;
        }
        .modal.active .modal-card { transform: translateY(0) scale(1); }
        .modal-head {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid var(--separator);
        }
        .modal-head h3 {
            flex: 1;
            font-size: 16px;
            font-weight: 600;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 24px;
            line-height: 1;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color .15s ease, color .15s ease, transform .1s ease;
        }
        .modal-close:hover { background: var(--surface-hi); color: var(--danger-text); }
        .modal-close:active { transform: scale(.9); }
        .portrait-grid {
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 12px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .portrait-item {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            background: var(--surface-hi);
            transition: transform .15s ease, border-color .15s ease;
        }
        .portrait-item:hover { transform: scale(1.08); border-color: var(--accent); }
        .portrait-item:active { transform: scale(.95); }
        .portrait-item.selected {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        @media (max-width: 480px) {
            .portrait-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .portrait-preview {
                width: 64px;
                height: 64px;
            }
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
        <div class="app-bar-title">注册</div>
        <button type="button" id="theme-toggle" class="app-bar-btn" aria-label="切换深色模式">🌙</button>
    </header>

    <main class="app-main">
        <?php if (!empty($msg)): ?>
            <div class="banner <?= htmlspecialchars($msgType); ?>"><?= $msg; ?></div>
        <?php endif; ?>

        <?php if ($msgType === 'success' && !empty($newSystemId)): ?>
            <!-- 注册成功 -->
            <div class="success-card">
                <div class="check">✓</div>
                <h3>注册成功</h3>

                <div class="label">你绑定的 QQ 号码</div>
                <div class="user-id" id="qqNumber"><?= htmlspecialchars($formData['qq']); ?></div>

                <div class="meta">
                    系统内部 ID：<?= htmlspecialchars($newSystemId); ?>
                </div>

                <div class="meta" style="margin-top: 16px;">
                    2 秒后自动返回…<br>
                    <a href="<?= $fromUrlEsc ?>">点此立即返回</a>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var qqElement = document.getElementById('qqNumber');
                    var qqNumber = qqElement.textContent.trim();
                    try { localStorage.setItem('lastLoginId', qqNumber); } catch (e) {}
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(qqNumber).then(function () {
                            var n = document.createElement('div');
                            n.className = 'copy-notification';
                            n.textContent = '✓ QQ号码已自动复制到剪贴板';
                            qqElement.parentNode.insertBefore(n, qqElement.nextSibling);
                        }).catch(function (err) {
                            console.error('复制失败:', err);
                        });
                    }
                });
                setTimeout(function () {
                    window.location.href = <?= $fromUrlJs ?>;
                }, 2000);
            </script>
        <?php else: ?>
            <p class="lead">创建一个新账号，用于登录「武校圈」</p>

            <form id="registerForm" class="form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">
                <input type="hidden" id="portraitInput" name="portrait" value="<?= htmlspecialchars($formData['portrait']); ?>">

                <div class="field">
                    <label for="pname">昵称<span class="req">*（永久不可变更）</span></label>
                    <input type="text" id="pname" name="pname"
                           value="<?= htmlspecialchars($formData['pname']); ?>"
                           required placeholder="1-14个字，支持中文/英文/数字/下划线"
                           maxlength="14"
                           oninput="checkUsernameAvailability()">
                    <div id="usernameFeedback" class="field-feedback"></div>
                </div>

                <div class="field">
                    <label for="qq">QQ号<span class="req">*（永久不可变更）</span></label>
                    <input type="text" id="qq" name="qq"
                           value="<?= htmlspecialchars($formData['qq']); ?>"
                           required placeholder="5~12位数字，不能以0开头"
                           inputmode="numeric"
                           oninput="validateQQ()">
                    <div id="qqFeedback" class="field-feedback"></div>
                </div>

                <div class="field">
                    <label for="password">密码<span class="req">*（切勿填写QQ密码；注册后可替换）</span></label>
                    <input type="password" id="password" name="password" required
                           placeholder="6-20位英文或数字"
                           maxlength="20">
                    <div id="passwordLengthFeedback" class="field-feedback"></div>
                </div>

                <div class="field">
                    <label for="confirmPwd">确认密码<span class="req">*</span></label>
                    <input type="password" id="confirmPwd" name="confirmPwd" required
                           placeholder="再次输入密码"
                           maxlength="20">
                    <div id="passwordMatchFeedback" class="field-feedback"></div>
                </div>

                <div class="field">
                    <label>性别<span class="req">*（永久不可变更）</span></label>
                    <div class="segmented">
                        <label class="segmented-item">
                            <input type="radio" name="gender" value="男" <?= $formData['gender'] === '男' ? 'checked' : ''; ?> required>
                            <span>男</span>
                        </label>
                        <label class="segmented-item">
                            <input type="radio" name="gender" value="女" <?= $formData['gender'] === '女' ? 'checked' : ''; ?>>
                            <span>女</span>
                        </label>
                        <label class="segmented-item">
                            <input type="radio" name="gender" value="保密" <?= $formData['gender'] === '保密' ? 'checked' : ''; ?>>
                            <span>保密</span>
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label>头像<span class="req">（注册后可替换）</span></label>
                    <div class="portrait-row">
                        <img id="currentPortraitPreview" class="portrait-preview"
                             src="<?= htmlspecialchars($formData['portrait']); ?>" alt="头像预览" loading="lazy">
                        <div class="portrait-actions">
                            <div class="upload-portrait">
                                <label for="customPortraitInput" class="upload-label">上传自定义头像</label>
                                <input type="file" id="customPortraitInput" name="custom_portrait" accept="image/*">
                            </div>
                            <button type="button" class="portrait-select-btn" id="openPortraitModalBtn">从图库选择</button>
                        </div>
                    </div>
                    <div id="uploadStatus" class="field-feedback"></div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn" disabled>注 册</button>

                <div class="login-link">
                    <a href="<?= $fromUrlEsc ?>">已有账号？返回</a>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <!-- 头像选择模态框 -->
    <div id="portraitModal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>选择头像</h3>
                <button type="button" class="modal-close" aria-label="关闭">&times;</button>
            </div>
            <div class="portrait-grid" id="portraitGrid"></div>
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

        // ---------- 主逻辑 ----------
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('portraitModal');
            const modalBtn = document.getElementById('openPortraitModalBtn');
            const closeBtn = document.querySelector('.modal-close');
            const portraitGrid = document.getElementById('portraitGrid');
            const portraitInput = document.getElementById('portraitInput');
            const currentPreview = document.getElementById('currentPortraitPreview');
            const fileInput = document.getElementById('customPortraitInput');
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            const uploadStatus = document.getElementById('uploadStatus');

            if (!form) return; // 注册成功页没有表单

            // 打开头像选择模态框
            modalBtn.addEventListener('click', function () {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('active'), 10);
                renderPortraits();
            });

            function closeModal() {
                modal.classList.remove('active');
                setTimeout(() => modal.style.display = 'none', 300);
            }
            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });

            // 渲染系统头像列表
            function renderPortraits() {
                portraitGrid.innerHTML = '';
                const totalPortraits = 100;
                const selectedValue = portraitInput.value;
                for (let i = 1; i <= totalPortraits; i++) {
                    const imgSrc = `portrait-img/c${i}.jpg`;
                    const img = document.createElement('img');
                    img.src = imgSrc;
                    img.alt = `头像 ${i}`;
                    img.loading = 'lazy';
                    img.className = 'portrait-item' + (imgSrc === selectedValue ? ' selected' : '');
                    img.addEventListener('click', function () { selectSystemPortrait(imgSrc); });
                    portraitGrid.appendChild(img);
                }
            }

            function selectSystemPortrait(imgSrc) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=select_portrait&portrait=${encodeURIComponent(imgSrc)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        portraitInput.value = data.portraitPath;
                        currentPreview.src = data.portraitPath;
                        uploadStatus.innerHTML = '<span class="fb-ok">✓ 已选择系统头像</span>';
                        closeModal();
                    }
                })
                .catch(error => {
                    console.error(error);
                    uploadStatus.innerHTML = '<span class="fb-err">✗ 选择失败</span>';
                });
            }

            // 上传自定义头像
            fileInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const formData = new FormData();
                    formData.append('action', 'upload_portrait');
                    formData.append('custom_portrait', this.files[0]);
                    uploadStatus.innerHTML = '<span class="fb-wait">上传中…</span>';
                    fetch('', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentPreview.src = data.portraitPath;
                            portraitInput.value = data.portraitPath;
                            uploadStatus.innerHTML = '<span class="fb-ok">✓ ' + data.message + '</span>';
                        } else {
                            uploadStatus.innerHTML = '<span class="fb-err">✗ ' + data.message + '</span>';
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        uploadStatus.innerHTML = '<span class="fb-err">✗ 上传失败</span>';
                    });
                }
            });

            // 实时验证表单
            function validateForm() {
                const username = document.getElementById('pname').value.trim();
                const qq = document.getElementById('qq').value.trim();
                const password = document.getElementById('password').value;
                const confirmPwd = document.getElementById('confirmPwd').value;
                const gender = document.querySelector('input[name="gender"]:checked');

                // 昵称 1-14 字
                const isUsernameValid = (mbStrLen(username) >= 1 && mbStrLen(username) <= 14)
                    && /^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username);

                // QQ号
                const isQQValid = /^[1-9][0-9]{4,11}$/.test(qq);

                // 密码 6-20 位，只允许英文和数字
                const passwordRegex = /^[A-Za-z0-9]+$/;
                const isPasswordValid =
                    password.length >= 6 && password.length <= 20 && passwordRegex.test(password) &&
                    confirmPwd.length >= 6 && confirmPwd.length <= 20 && passwordRegex.test(confirmPwd);

                const isGenderValid = !!gender;

                const isFormValid = isUsernameValid && isQQValid && isPasswordValid && isGenderValid;
                submitBtn.disabled = !isFormValid;

                return isFormValid;
            }

            // 按 Unicode 字符计数（中英文都算 1 个字）
            function mbStrLen(str) {
                if (!str) return 0;
                return Array.from(str).length;
            }

            const allInputs = form.querySelectorAll('input');
            allInputs.forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm);
            });

            form.addEventListener('submit', function (e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('请正确填写所有必填字段');
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.textContent = '注册中…';
            });

            // 密码实时反馈
            document.getElementById('password').addEventListener('input', function () {
                const feedback = document.getElementById('passwordLengthFeedback');
                const val = this.value;
                if (val === '') { feedback.innerHTML = ''; return; }
                if (val.length < 6) {
                    feedback.innerHTML = '<span class="fb-err">✗ 密码至少6位</span>';
                    return;
                }
                if (val.length > 20) {
                    feedback.innerHTML = '<span class="fb-err">✗ 密码不能超过20位</span>';
                    return;
                }
                if (!/^[A-Za-z0-9]+$/.test(val)) {
                    feedback.innerHTML = '<span class="fb-err">✗ 密码只能包含英文和数字</span>';
                    return;
                }
                feedback.innerHTML = '<span class="fb-ok">✓ 密码格式正确</span>';
            });

            document.getElementById('confirmPwd').addEventListener('input', function () {
                const feedback = document.getElementById('passwordMatchFeedback');
                const pwd = document.getElementById('password').value;
                if (this.value === '') { feedback.innerHTML = ''; return; }
                if (this.value === pwd) {
                    feedback.innerHTML = '<span class="fb-ok">✓ 两次密码一致</span>';
                } else {
                    feedback.innerHTML = '<span class="fb-err">✗ 两次密码不一致</span>';
                }
            });

            validateForm();
        });

        // 昵称实时验证
        function checkUsernameAvailability() {
            const username = document.getElementById('pname').value.trim();
            const feedback = document.getElementById('usernameFeedback');
            const len = username ? Array.from(username).length : 0;

            if (username === '') {
                feedback.innerHTML = '';
                return;
            }
            if (len < 1 || len > 14) {
                feedback.innerHTML = '<span class="fb-err">✗ 昵称需1-14个字</span>';
                return;
            }
            if (!/^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username)) {
                feedback.innerHTML = '<span class="fb-err">✗ 只能包含中文、英文、数字和下划线</span>';
                return;
            }
            feedback.innerHTML = '<span class="fb-ok">✓ 格式正确</span>';
        }

        // QQ号实时验证
        function validateQQ() {
            const qq = document.getElementById('qq').value.trim();
            const feedback = document.getElementById('qqFeedback');
            if (qq === '') {
                feedback.innerHTML = '';
                return;
            }
            if (/^[1-9][0-9]{4,11}$/.test(qq)) {
                feedback.innerHTML = '<span class="fb-ok">✓ QQ号格式正确</span>';
            } else {
                feedback.innerHTML = '<span class="fb-err">✗ 请输入5~12位数字，不能以0开头</span>';
            }
        }
    </script>
</body>
</html>