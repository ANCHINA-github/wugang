<?php

// ---------- 启动会话 ----------
session_start();

// ---------- 配置 ----------
$uploadDir = __DIR__ . '/uploads/';
$jsonFile = __DIR__ . '/files.json';
$usersFile = __DIR__ . '/users.json';

// 确保存储目录和 JSON 文件存在
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}

// ---------- 读取用户数据 ----------
$users = json_decode(file_get_contents($usersFile), true);
if (!is_array($users)) {
    $users = [];
}

// ---------- 获取当前登录用户 ----------
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    foreach ($users as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            $currentUser = $user;
            break;
        }
    }
    if (!$currentUser) {
        unset($_SESSION['user_id']);
    }
}

// ---------- 路由处理 ----------
$action = $_GET['action'] ?? '';

// 注册
if ($action === 'register') {
    header('Content-Type: application/json');
    $response = ['success' => false];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $response['error'] = '所有字段均为必填';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['error'] = '邮箱格式不正确';
        } elseif (strlen($password) < 6) {
            $response['error'] = '密码至少6位';
        } else {
            $exists = false;
            foreach ($users as $u) {
                if ($u['username'] === $username || $u['email'] === $email) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $response['error'] = '用户名或邮箱已被注册';
            } else {
                $newId = count($users) > 0 ? max(array_column($users, 'id')) + 1 : 1;
                $newUser = [
                    'id' => $newId,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $users[] = $newUser;
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

                $_SESSION['user_id'] = $newId;
                $response['success'] = true;
                $response['user'] = ['username' => $username, 'email' => $email];
            }
        }
    } else {
        $response['error'] = '无效请求方法';
    }

    echo json_encode($response);
    exit;
}

// 登录
if ($action === 'login') {
    header('Content-Type: application/json');
    $response = ['success' => false];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $response['error'] = '请填写登录凭证和密码';
        } else {
            $foundUser = null;
            foreach ($users as $u) {
                if ($u['username'] === $login || $u['email'] === $login) {
                    $foundUser = $u;
                    break;
                }
            }
            if ($foundUser && password_verify($password, $foundUser['password_hash'])) {
                $_SESSION['user_id'] = $foundUser['id'];
                $response['success'] = true;
                $response['user'] = ['username' => $foundUser['username'], 'email' => $foundUser['email']];
            } else {
                $response['error'] = '用户名/邮箱或密码错误';
            }
        }
    } else {
        $response['error'] = '无效请求方法';
    }

    echo json_encode($response);
    exit;
}

// 退出登录
if ($action === 'logout') {
    unset($_SESSION['user_id']);
    session_destroy();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// 获取当前用户信息
if ($action === 'profile') {
    header('Content-Type: application/json');
    if ($currentUser) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $currentUser['id'],
                'username' => $currentUser['username'],
                'email' => $currentUser['email'],
                'created_at' => $currentUser['created_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => '未登录']);
    }
    exit;
}

// 上传文件 (需登录)
if ($action === 'upload') {
    header('Content-Type: application/json');
    $response = ['success' => false];

    if (!$currentUser) {
        $response['error'] = '请先登录';
        echo json_encode($response);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['error'] = '上传错误：' . $file['error'];
            echo json_encode($response);
            exit;
        }

        $fileId = uniqid() . '_' . bin2hex(random_bytes(8));
        $originalName = basename($file['name']);

        $userDir = $uploadDir . 'user_' . $currentUser['id'] . '/';
        if (!file_exists($userDir)) {
            mkdir($userDir, 0777, true);
        }
        $targetPath = $userDir . $fileId;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $files = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($files)) {
                $files = [];
            }

            $fileInfo = [
                'id' => $fileId,
                'user_id' => $currentUser['id'],
                'original_name' => $originalName,
                'size' => $file['size'],
                'time' => date('Y-m-d H:i:s')
            ];
            $files[] = $fileInfo;

            file_put_contents($jsonFile, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

            $response['success'] = true;
            $response['file'] = $fileInfo;
        } else {
            $response['error'] = '保存文件失败';
        }
    } else {
        $response['error'] = '无效的请求';
    }

    echo json_encode($response);
    exit;
}

// 文件列表 (只返回当前用户的)
if ($action === 'list') {
    header('Content-Type: application/json');
    if (!$currentUser) {
        echo json_encode(['error' => '未登录', 'files' => []]);
        exit;
    }

    $files = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($files)) {
        $files = [];
    }

    $userFiles = array_filter($files, function($f) use ($currentUser) {
        return isset($f['user_id']) && $f['user_id'] == $currentUser['id'];
    });

    usort($userFiles, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    echo json_encode(array_values($userFiles), JSON_UNESCAPED_UNICODE);
    exit;
}

// 文件下载 (需登录且拥有权限)
if ($action === 'download') {
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        die('缺少文件 ID');
    }

    if (!$currentUser) {
        http_response_code(401);
        die('请先登录');
    }

    $files = json_decode(file_get_contents($jsonFile), true);
    $fileInfo = null;
    foreach ($files as $f) {
        if ($f['id'] === $id) {
            $fileInfo = $f;
            break;
        }
    }

    if (!$fileInfo) {
        http_response_code(404);
        die('文件不存在');
    }

    if ($fileInfo['user_id'] != $currentUser['id']) {
        http_response_code(403);
        die('无权访问此文件');
    }

    $filePath = $uploadDir . 'user_' . $currentUser['id'] . '/' . $id;
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('文件数据丢失');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileInfo['original_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

// -------------------- 注销账户 (新增) --------------------
if ($action === 'delete_account') {
    header('Content-Type: application/json');
    if (!$currentUser) {
        echo json_encode(['success' => false, 'error' => '未登录']);
        exit;
    }
    $uid = $currentUser['id'];

    // 1. 删除用户文件目录及所有文件
    $userDir = $uploadDir . 'user_' . $uid . '/';
    if (file_exists($userDir)) {
        $files = glob($userDir . '*');
        foreach ($files as $f) {
            if (is_file($f)) unlink($f);
        }
        rmdir($userDir);
    }

    // 2. 从 files.json 中移除该用户的所有记录
    $files = json_decode(file_get_contents($jsonFile), true);
    if (is_array($files)) {
        $newFiles = array_filter($files, function($f) use ($uid) {
            return !(isset($f['user_id']) && $f['user_id'] == $uid);
        });
        file_put_contents($jsonFile, json_encode(array_values($newFiles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // 3. 从 users.json 中移除该用户
    $users = json_decode(file_get_contents($usersFile), true);
    if (is_array($users)) {
        $newUsers = array_filter($users, function($u) use ($uid) {
            return $u['id'] != $uid;
        });
        file_put_contents($usersFile, json_encode(array_values($newUsers), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // 4. 销毁会话
    unset($_SESSION['user_id']);
    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}

// ---------- 默认输出 HTML 界面 (苹果简约风格，集成 Font Awesome) ----------
?><!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Ancloud 文件管理器</title>
    <!-- Font Awesome 6 (免费，简约线条图标) -->
    <link rel="stylesheet" href="/repository/awesome/css/all.min.css" onerror="this.href='https://cdn.staticfile.org/font-awesome/6.4.0/css/all.min.css'">
    <style>
        /* 全局重置与基础变量 (保持原样式，仅微调) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(145deg, #f0f4fa 0%, #e9eef5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.5;
            color: #1a2b3c;
        }

        .app-container {
            width: 100%;
            max-width: 1280px;
            height: 100vh;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: rgba(255,255,255,0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            box-shadow: 0 8px 32px rgba(0, 20, 30, 0.1);
            margin: 0 auto;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 24px 20px 16px 20px;
            scrollbar-width: thin;
            scrollbar-color: #b0c6da #eef3f9;
        }

        .content-area::-webkit-scrollbar {
            width: 6px;
        }
        .content-area::-webkit-scrollbar-track {
            background: #eef3f9;
        }
        .content-area::-webkit-scrollbar-thumb {
            background: #b0c6da;
            border-radius: 20px;
        }

        .panel {
            display: none;
        }
        .panel.active {
            display: block;
        }

        /* ----- 首页 (文件助手) 样式调整，图标使用FA ----- */
        .home-header {
            margin-bottom: 24px;
        }
        .home-header h2 {
            font-size: clamp(24px, 5vw, 36px);
            font-weight: 700;
            color: #0b2e4b;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .home-header h2 span {
            background: #ffd966;
            padding: 6px 14px;
            border-radius: 60px;
            font-size: 0.8em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .upload-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 36px;
            padding: 24px 22px;
            box-shadow: 0 10px 25px -8px rgba(0,40,70,0.15);
            margin-bottom: 28px;
            border: 1px solid rgba(255,255,255,0.7);
        }
        .upload-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f4970;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-input-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .custom-file-input {
            position: relative;
            flex: 2 1 240px;
        }
        .custom-file-input input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            left: 0;
            top: 0;
            cursor: pointer;
        }
        .file-label {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffffcc;
            color: #1f4f8a;
            padding: 14px 18px;
            border-radius: 60px;
            font-size: 0.95rem;
            font-weight: 500;
            border: 1px solid #b9d6f0;
            transition: 0.15s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .custom-file-input:hover .file-label {
            background: #ffffff;
            border-color: #8bb4e0;
        }
        .upload-btn {
            background: #2c6e9c;
            border: none;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 14px 30px;
            border-radius: 60px;
            cursor: pointer;
            box-shadow: 0 10px 18px -6px #1f4f8a80;
            transition: 0.15s;
            border: 1px solid #1f4f8a;
            flex: 1 0 auto;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .upload-btn:hover {
            background: #1f4f8a;
            transform: scale(0.98);
            box-shadow: 0 6px 12px -4px #1f4f8a;
        }
        .upload-status {
            margin-top: 16px;
            font-size: 0.9rem;
            color: #2e7d32;
            background: #e1f3e1;
            padding: 10px 20px;
            border-radius: 40px;
            display: inline-block;
            border: 1px solid #a5d6a5;
        }

        .file-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .file-list-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0b2e4b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .file-count {
            background: #d4e2f2;
            color: #1f4f8a;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        #fileContainer {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .file-card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 28px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 12px rgba(0,20,40,0.04);
            border: 1px solid rgba(255,255,255,0.9);
            transition: 0.2s;
        }
        .file-card:hover {
            box-shadow: 0 12px 24px -12px #2c6e9c60;
            border-color: #cddff5;
            background: rgba(255,255,255,0.9);
        }
        .file-info {
            flex: 2 1 240px;
            min-width: 0;
        }
        .file-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1a3b54;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            word-break: break-word;
        }
        .file-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.85rem;
            color: #4a6582;
        }
        .file-meta span {
            background: #eaf0f8;
            padding: 4px 14px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .download-btn {
            background: #ecf7ff;
            border-radius: 40px;
            padding: 10px 22px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f4f8a;
            text-decoration: none;
            border: 1px solid #b9d6f0;
            transition: 0.15s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .download-btn:hover {
            background: #1f4f8a;
            color: white;
            border-color: #1f4f8a;
        }
        .empty-message {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 40px 20px;
            text-align: center;
            color: #556f8c;
            border: 1px dashed #c2d4e8;
            font-size: 1.1rem;
        }

        /* 探索面板 (保留原卡片，图标可换) */
        .explore-grid {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 4px 0 10px;
        }
        .activity-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 36px;
            padding: 26px 24px;
            box-shadow: 0 8px 18px -6px rgba(0,40,70,0.12);
            border: 1px solid rgba(255,255,255,0.8);
            transition: all 0.28s cubic-bezier(0.2, 0.9, 0.3, 1.1);
            cursor: pointer;
        }
        .activity-card:hover {
            transform: scale(1.01) translateY(-4px);
            box-shadow: 0 22px 30px -12px #4a7aac;
            background: rgba(255,255,255,0.9);
            border-color: #d2e4ff;
        }
        .activity-card:active {
            transform: scale(0.98);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .activity-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .activity-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #183f5c;
            margin-bottom: 6px;
        }
        .activity-desc {
            font-size: 1rem;
            color: #365770;
        }
        .activity-meta {
            margin-top: 16px;
            display: flex;
            gap: 14px;
            color: #2c6e9c;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* 我的面板 (登录/注册 + 个人) */
        .profile-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 48px;
            padding: 32px 24px;
            box-shadow: 0 10px 24px -10px rgba(0,40,70,0.15);
            border: 1px solid rgba(255,255,255,0.7);
            max-width: 500px;
            margin: 0 auto;
        }
        .profile-avatar {
            width: 110px;
            height: 110px;
            background: linear-gradient(145deg, #c2dcff, #a0c6f0);
            border-radius: 50%;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            color: #1f4f8a;
            box-shadow: 0 12px 18px -6px #2c6e9c80;
            border: 3px solid white;
        }
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: #0b2b41;
            text-align: center;
            margin-bottom: 6px;
        }
        .profile-email {
            color: #3e5f7c;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .profile-action {
            background: #ffffffb3;
            border-radius: 60px;
            padding: 16px 22px;
            margin: 14px 0;
            font-weight: 500;
            color: #1f4f8a;
            border: 1px solid #c9dfff;
            transition: 0.15s;
            text-align: center;
            font-size: 1.05rem;
            backdrop-filter: blur(4px);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .profile-action:active {
            background: #d7e6ff;
        }
        .profile-action.logout-btn {
            background: #ffe5e5;
            color: #b33;
            border-color: #f0b2b2;
        }
        .profile-action.delete-account-btn {
            background: #ffe0e0;
            color: #c00;
            border-color: #ffa0a0;
            font-weight: 600;
        }
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 20px;
        }
        .auth-input {
            background: rgba(255,255,255,0.9);
            border: 1px solid #cddff0;
            border-radius: 60px;
            padding: 16px 22px;
            font-size: 1rem;
            outline: none;
            transition: 0.15s;
        }
        .auth-input:focus {
            border-color: #1f4f8a;
            box-shadow: 0 0 0 3px rgba(31,79,138,0.2);
        }
        .auth-btn {
            background: #2c6e9c;
            color: white;
            border: none;
            border-radius: 60px;
            padding: 16px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-btn:hover {
            background: #1f4f8a;
        }
        .auth-switch {
            text-align: center;
            margin-top: 20px;
            color: #3f5670;
        }
        .auth-switch span {
            color: #ff7b5c;
            font-weight: 600;
            cursor: pointer;
        }

        /* 底部导航 (苹果风格线条图标) */
        .bottom-nav {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid rgba(180, 200, 230, 0.5);
            padding: 10px 20px 100px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.02);
        }

        .nav-item {
            flex: 1;
            text-align: center;
            font-size: 1rem;
            font-weight: 550;
            color: #3f5670;
            padding: 8px 0;
            border-radius: 40px;
            transition: 0.15s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 1.2;
            max-width: 120px;
        }

        .nav-item i {
            font-size: 1.8rem;
            margin-bottom: 2px;
        }

        /* 中间项 — 酷狗风格 明显突出 */
        .nav-item.explore-item {
            background: #ff7b5c;
            color: white;
            font-weight: 700;
            transform: scale(1.15) translateY(-8px);
            box-shadow: 0 14px 20px -6px #ff7b5c, 0 0 0 2px rgba(255,255,255,0.8);
            padding: 12px 4px;
            border-radius: 60px;
            flex: 1.2;
            max-width: 140px;
            border: none;
        }

        .nav-item:not(.explore-item):hover {
            background: #ffffffcc;
            color: #1f4f8a;
        }

        @media (max-width: 500px) {
            .content-area {
                padding: 16px 12px;
            }
            .file-input-row {
                flex-direction: column;
                align-items: stretch;
            }
            .upload-btn {
                width: 100%;
            }
            .file-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .download-btn {
                align-self: flex-end;
            }
            .nav-item {
                font-size: 0.9rem;
            }
            .nav-item i {
                font-size: 1.5rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1024px) {
            .content-area {
                padding: 28px 32px;
            }
            .file-card {
                padding: 22px 28px;
            }
        }
        /* ===== 深色模式（自动适配系统主题） ===== */
@media (prefers-color-scheme: dark) {
    body {
        background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
        color: #e0e8f0;
    }

    .app-container {
        background-color: rgba(0, 0, 0, 0.7);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }

    /* 滚动条 */
    .content-area::-webkit-scrollbar-track {
        background: #2a2a3e;
    }
    .content-area::-webkit-scrollbar-thumb {
        background: #4a5a7a;
    }

    .home-header h2 {
        color: #d0d8e8;
    }
    .home-header h2 span {
        background: #b89a4a;
    }

    .upload-card {
        background: rgba(30, 35, 50, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 25px -8px rgba(0, 0, 0, 0.5);
    }
    .upload-card h3 {
        color: #7aabda;
    }

    .file-label {
        background: rgba(50, 55, 70, 0.9);
        color: #8ab4e0;
        border-color: #3a5a7a;
    }
    .custom-file-input:hover .file-label {
        background: rgba(60, 70, 90, 0.9);
        border-color: #5a8ab0;
    }

    .upload-btn {
        background: #3a7eb6;
        border-color: #2a5e8a;
        box-shadow: 0 10px 18px -6px #1f4f8a80;
        color: #ffffff;
    }
    .upload-btn:hover {
        background: #4a8ec6;
        box-shadow: 0 6px 12px -4px #1f4f8a;
    }

    .upload-status {
        background: #1e3a2e;
        color: #7ab87a;
        border-color: #2a5a3a;
    }

    .file-list-header h3 {
        color: #d0d8e8;
    }
    .file-count {
        background: #2a3a5a;
        color: #8ab4e0;
    }

    .file-card {
        background: rgba(30, 35, 50, 0.75);
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    .file-name {
        color: #cfd9e6;
    }
    .file-meta {
        color: #8ea4c0;
    }
    .file-meta span {
        background: #2a3a5a;
    }

    .download-btn {
        background: #2a4a6a;
        color: #8ab4e0;
        border-color: #3a5a7a;
    }
    .download-btn:hover {
        background: #3a6a8a;
        color: #ffffff;
        border-color: #4a7a9a;
    }

    .empty-message {
        background: rgba(30, 35, 50, 0.7);
        color: #8a9fb5;
        border-color: #3a4a5a;
    }

    .activity-card {
        background: rgba(30, 35, 50, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 8px 18px -6px rgba(0, 0, 0, 0.4);
    }
    .activity-card:hover {
        box-shadow: 0 22px 30px -12px #1a3a5a;
        background: rgba(40, 50, 70, 0.9);
        border-color: #3a5a7a;
    }
    .activity-title {
        color: #c0d0e8;
    }
    .activity-desc {
        color: #90a8c0;
    }
    .activity-meta {
        color: #5a8ab0;
    }

    .profile-card {
        background: rgba(30, 35, 50, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 10px 24px -10px rgba(0, 0, 0, 0.5);
    }
    .profile-avatar {
        background: linear-gradient(145deg, #3a5a8a, #2a4a7a);
        color: #8ab4e0;
        border-color: #4a6a8a;
    }
    .profile-name {
        color: #d0d8e8;
    }
    .profile-email {
        color: #90a8c0;
    }
    .profile-action {
        background: rgba(50, 60, 80, 0.8);
        color: #8ab4e0;
        border-color: #3a5a7a;
    }
    .profile-action.logout-btn {
        background: #4a2a2a;
        color: #e08080;
        border-color: #6a3a3a;
    }
    .profile-action.delete-account-btn {
        background: #5a2a2a;
        color: #ff6060;
        border-color: #8a3a3a;
    }
    .profile-action:active {
        background: #3a4a6a;
    }

    .auth-input {
        background: rgba(40, 45, 60, 0.9);
        border-color: #3a5a7a;
        color: #e0e8f0;
    }
    .auth-input:focus {
        border-color: #5a8ab0;
        box-shadow: 0 0 0 3px rgba(31, 79, 138, 0.3);
    }

    .auth-btn {
        background: #3a7eb6;
        color: white;
    }
    .auth-btn:hover {
        background: #4a8ec6;
    }

    .auth-switch {
        color: #8aa0b8;
    }
    .auth-switch span {
        color: #ff8a6a;
    }

    .bottom-nav {
        background: rgba(20, 25, 40, 0.7);
        border-top: 1px solid rgba(60, 80, 100, 0.3);
    }
    .nav-item {
        color: #8aa0b8;
    }
    .nav-item:not(.explore-item):hover {
        background: rgba(40, 55, 80, 0.6);
        color: #b0c8e0;
    }
    .nav-item.explore-item {
        background: #d96a4a;
        color: white;
        box-shadow: 0 14px 20px -6px #8a4a3a, 0 0 0 2px rgba(0, 0, 0, 0.6);
    }
}
    </style>
</head>
<body>
<div class="app-container">
    <div class="content-area" id="contentArea">
        <!-- 首页面板 -->
        <div class="panel" id="homePanel">
            <div class="home-header">
                <h2><span><i class="fas fa-folder-open"></i> Ancloud</span></h2>
            </div>

            <?php if ($currentUser): ?>
                <!-- 已登录：上传卡片 -->
                <div class="upload-card">
                    <h3><i class="fas fa-cloud-upload-alt"></i> 上传到我的存储</h3>
                    <div class="file-input-row">
                        <div class="custom-file-input">
                            <input type="file" id="fileInput">
                            <span class="file-label" id="fileLabel"><i class="fas fa-paperclip"></i> 选择文件...</span>
                        </div>
                        <button class="upload-btn" onclick="uploadFile()"><i class="fas fa-upload"></i> 上传</button>
                    </div>
                    <div id="uploadStatus" class="upload-status"></div>
                </div>

                <!-- 文件列表区 -->
                <div class="file-list-header">
                    <h3><i class="fas fa-list"></i> 我的文件</h3>
                    <span class="file-count" id="fileCount">0</span>
                </div>
                <div id="fileContainer">
                    <div class="empty-message">加载中…</div>
                </div>
            <?php else: ?>
                <!-- 未登录：提示登录 -->
                <div class="upload-card" style="text-align: center;">
                    <h3><i class="fas fa-lock"></i> 需要登录</h3>
                    <p style="margin: 20px 0; color: #4f6f8f;">请先登录以管理您的文件</p>
                    <div class="auth-switch" style="margin-top: 10px;">
                        <span onclick="switchToProfile()"><i class="fas fa-arrow-right"></i> 前往“我的”面板登录</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 探索面板 (保留emoji或换FA) -->
        <div class="panel" id="explorePanel">
            <div style="margin-bottom: 24px;">
                <h2 style="font-size: clamp(28px, 6vw, 42px); font-weight: 700; color:#1f3f5c;"><i class="fas fa-compass" style="margin-right: 10px;"></i>探索</h2>
                <p style="color:#4f6f8f; font-size:1.1rem;">超前体验新的功能</p>
            </div>
            <div class="explore-grid">
                <div class="activity-card" onclick="window.open('../music-player/player.php', '_blank')">
                    <div class="activity-icon"><i class="fas fa-headphones-alt"></i></div>
                    <div class="activity-title">Musical Night</div>
                    <div class="activity-desc">今晚枕着音乐入睡</div>
                    <div class="activity-meta"><i class="fas fa-fire"></i> 54人参与 · 30天后结束</div>
                </div>
                
            </div>
        </div>

        <!-- 我的面板 -->
        <div class="panel" id="profilePanel">
            <div class="profile-card" id="profileCard">
                <?php if ($currentUser): ?>
                    <!-- 已登录：用户信息 + 注销账户按钮 -->
                    <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="profile-name"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                    <div class="profile-action"><i class="fas fa-crown"></i> 我的会员 · 基础版</div>
                    <div class="profile-action" onclick="calculateSpace()"><i class="fas fa-chart-pie"></i> 计算已用空间</div>
                    <div class="profile-action logout-btn" onclick="logout()"><i class="fas fa-sign-out-alt"></i> 退出登录</div>
                    <!-- 新增注销账户按钮 -->
                    <div class="profile-action delete-account-btn" onclick="deleteAccount()"><i class="fas fa-trash-alt"></i> 注销账户</div>
                <?php else: ?>
                    <!-- 未登录：登录/注册表单 -->
                    <div id="authContainer">
                        <div class="profile-avatar"><i class="fas fa-lock"></i></div>
                        <h3 style="text-align:center; margin-bottom:20px;"><i class="fas fa-user-plus"></i> 登录 / 注册</h3>
                        <div class="auth-form" id="loginForm">
                            <input type="text" id="loginUsername" class="auth-input" placeholder="用户名或邮箱">
                            <input type="password" id="loginPassword" class="auth-input" placeholder="密码">
                            <button class="auth-btn" onclick="login()"><i class="fas fa-sign-in-alt"></i> 登录</button>
                            <div class="auth-switch">
                                还没有账号？ <span onclick="showRegister()">立即注册</span>
                            </div>
                        </div>
                        <div class="auth-form" id="registerForm" style="display:none;">
                            <input type="text" id="regUsername" class="auth-input" placeholder="用户名">
                            <input type="email" id="regEmail" class="auth-input" placeholder="邮箱">
                            <input type="password" id="regPassword" class="auth-input" placeholder="密码 (至少6位)">
                            <button class="auth-btn" onclick="register()"><i class="fas fa-user-check"></i> 注册</button>
                            <div class="auth-switch">
                                已有账号？ <span onclick="showLogin()">去登录</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 底部导航 (苹果风格线条图标) -->
    <div class="bottom-nav">
        <div class="nav-item" data-target="homePanel"><i class="fas fa-home"></i> 首页</div>
        <div class="nav-item explore-item" data-target="explorePanel"><i class="fas fa-compass"></i> 探索</div>
        <div class="nav-item" data-target="profilePanel"><i class="fas fa-user"></i> 我的</div>
    </div>
</div>

<script>
    (function() {
        // ---------- 面板切换 ----------
        const panels = {
            home: document.getElementById('homePanel'),
            explore: document.getElementById('explorePanel'),
            profile: document.getElementById('profilePanel')
        };
        const navItems = document.querySelectorAll('.nav-item');

        function showPanel(panelId) {
            Object.values(panels).forEach(p => p.classList.remove('active'));
            const activePanel = document.getElementById(panelId);
            if (activePanel) activePanel.classList.add('active');
        }

        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                const targetId = item.dataset.target;
                if (targetId) showPanel(targetId);
            });
        });

        showPanel('homePanel');

        window.switchToProfile = function() {
            showPanel('profilePanel');
        };

        // ---------- 文件助手逻辑 (仅登录后) ----------
        <?php if ($currentUser): ?>
        const baseUrl = window.location.pathname;
        const fileInput = document.getElementById('fileInput');
        const fileLabel = document.getElementById('fileLabel');
        const uploadStatus = document.getElementById('uploadStatus');
        const fileContainer = document.getElementById('fileContainer');
        const fileCountSpan = document.getElementById('fileCount');

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileLabel.innerHTML = '<i class="fas fa-paperclip"></i> ' + this.files[0].name;
            } else {
                fileLabel.innerHTML = '<i class="fas fa-paperclip"></i> 选择文件...';
            }
        });

        window.uploadFile = async function() {
            const file = fileInput.files[0];
            if (!file) {
                alert('请先选择文件');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            uploadStatus.innerText = '⏳ 上传中...';
            uploadStatus.style.color = '#1f4f8a';

            try {
                const res = await fetch(baseUrl + '?action=upload', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> 上传成功！';
                    uploadStatus.style.color = '#2e7d32';
                    fileInput.value = '';
                    fileLabel.innerHTML = '<i class="fas fa-paperclip"></i> 选择文件...';
                    loadFileList();
                } else {
                    uploadStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> 上传失败：' + (result.error || '未知错误');
                    uploadStatus.style.color = '#b33';
                }
            } catch (err) {
                uploadStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> 上传出错';
                uploadStatus.style.color = '#b33';
                console.error(err);
            }
        };

        async function loadFileList() {
            fileContainer.innerHTML = '<div class="empty-message"><i class="fas fa-spinner fa-pulse"></i> 加载文件列表...</div>';
            try {
                const res = await fetch(baseUrl + '?action=list');
                const files = await res.json();

                if (!files || files.length === 0) {
                    fileContainer.innerHTML = '<div class="empty-message"><i class="fas fa-folder-open"></i> 暂无文件，点击上传添加</div>';
                    fileCountSpan.innerText = '0';
                    return;
                }

                fileCountSpan.innerText = files.length;

                let htmlStr = '';
                files.forEach(file => {
                    const size = formatBytes(file.size);
                    const time = file.time || '未知时间';
                    const safeName = escapeHtml(file.original_name);
                    const safeId = escapeHtml(file.id);
                    htmlStr += `
                        <div class="file-card">
                            <div class="file-info">
                                <div class="file-name">
                                    <i class="fas fa-file"></i> ${safeName}
                                </div>
                                <div class="file-meta">
                                    <span><i class="fas fa-database"></i> ${size}</span>
                                    <span><i class="far fa-clock"></i> ${escapeHtml(time)}</span>
                                </div>
                            </div>
                            <a href="${baseUrl}?action=download&id=${safeId}" class="download-btn" target="_blank"><i class="fas fa-download"></i> 下载</a>
                        </div>
                    `;
                });
                fileContainer.innerHTML = htmlStr;
            } catch (err) {
                fileContainer.innerHTML = '<div class="empty-message" style="color:#b33;"><i class="fas fa-exclamation-triangle"></i> 加载失败，请刷新</div>';
                console.error(err);
            }
        }

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.replace(/[&<>"]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                if (m === '"') return '&quot;';
                return m;
            });
        }

        loadFileList();
        <?php endif; ?>

        // ---------- 账户操作函数 ----------
        window.showRegister = function() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'flex';
        };
        window.showLogin = function() {
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'flex';
        };

        window.login = async function() {
            const login = document.getElementById('loginUsername').value;
            const password = document.getElementById('loginPassword').value;
            if (!login || !password) {
                alert('请填写登录信息');
                return;
            }
            const formData = new FormData();
            formData.append('login', login);
            formData.append('password', password);
            try {
                const res = await fetch(window.location.pathname + '?action=login', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('登录失败：' + (result.error || '未知错误'));
                }
            } catch (err) {
                alert('请求出错');
            }
        };

        window.register = async function() {
            const username = document.getElementById('regUsername').value;
            const email = document.getElementById('regEmail').value;
            const password = document.getElementById('regPassword').value;
            if (!username || !email || !password) {
                alert('请填写所有字段');
                return;
            }
            const formData = new FormData();
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            try {
                const res = await fetch(window.location.pathname + '?action=register', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('注册失败：' + (result.error || '未知错误'));
                }
            } catch (err) {
                alert('请求出错');
            }
        };

        window.logout = async function() {
            if (!confirm('确定退出登录吗？')) return;
            try {
                const res = await fetch(window.location.pathname + '?action=logout');
                const result = await res.json();
                if (result.success) {
                    location.reload();
                }
            } catch (err) {
                alert('退出出错');
            }
        };

        // 新增：注销账户
        window.deleteAccount = async function() {
            if (!confirm('⚠️ 确定要注销账户吗？此操作不可逆，所有文件将被永久删除。')) return;
            try {
                const res = await fetch(window.location.pathname + '?action=delete_account');
                const result = await res.json();
                if (result.success) {
                    alert('账户已注销');
                    location.reload();
                } else {
                    alert('注销失败：' + (result.error || '未知错误'));
                }
            } catch (err) {
                alert('请求出错');
            }
        };

        window.calculateSpace = function() {
            alert('功能维护中，敬请期待！');
        };
    })();
</script>
<!-- 后端完整集成：注册、登录、注销账户、文件隔离。界面采用苹果风格线条图标（Font Awesome）。 -->
</body>
</html>