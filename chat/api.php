<?php
require 'config.php';

$action = $_GET['action'] ?? '';
$my_id = $_SESSION['user_id'] ?? 0;

if ($action === 'login') {
    $pwd = $_POST['password'] ?? '';
    if ($pwd === '111111') {
        $_SESSION['user_id'] = 1; json_out(200, '登录成功');
    } elseif ($pwd === '222222') {
        $_SESSION['user_id'] = 2; json_out(200, '登录成功');
    } else {
        json_out(401, '密码错误');
    }
}

if ($action === 'logout') {
    session_destroy();
    json_out(200, '已登出');
}

if ($my_id === 0) {
    json_out(401, '未登录');
}

// 发送消息
if ($action === 'send') {
    $content = trim($_POST['content'] ?? '');
    if (!$content) json_out(400, '消息不能为空');

    $receiver_id = get_other_id($my_id);
    
    try {
        // 1. 插入数据
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, type, content) VALUES (?, ?, 1, ?)");
        $stmt->execute([$my_id, $receiver_id, $content]);
        $msg_id = $pdo->lastInsertId();

        // 2. 强制查一次，确认是否真的存进去了
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$msg_id]);
        $saved = $stmt->fetch();
        
        if (!$saved) {
            json_out(500, '严重错误：插入后查不到数据，数据库可能未提交');
        }

        json_out(200, '发送成功', $saved);
    } catch (PDOException $e) {
        json_out(500, '数据库报错: ' . $e->getMessage());
    }
}

// 拉取消息
if ($action === 'fetch') {
    $since_id = (int)($_GET['since_id'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE id > ? AND (sender_id = ? OR receiver_id = ?) 
            ORDER BY id ASC LIMIT 50
        ");
        $stmt->execute([$since_id, $my_id, $my_id]);
        $new_messages = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT id FROM messages WHERE sender_id = ? AND is_read = 1");
        $stmt->execute([$my_id]); 
        $read_updates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        json_out(200, '获取成功', [
            'new_messages' => $new_messages,
            'read_updates' => $read_updates,
            'debug_my_id' => $my_id,       // 调试用，看前端当前ID
            'debug_since_id' => $since_id  // 调试用，看前端拉取的起点
        ]);
    } catch (PDOException $e) {
        json_out(500, '查询报错: ' . $e->getMessage());
    }
}

if ($action === 'read') {
    $last_id = (int)($_POST['last_id'] ?? 0);
    if ($last_id > 0) {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND id <= ? AND is_read = 0");
        $stmt->execute([$my_id, get_other_id($my_id), $last_id]);
    }
    json_out(200, '已读');
}