<?php
// 开启所有报错，方便直接看到问题
ini_set('display_errors', '1');
error_reporting(E_ALL);

$host = 'an.kijk.top';
$port = 33060;
$dbname = 'andb';
$username = 'an';
$password = 'dca2025~A';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username, $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败：" . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 统一的输出函数，强制返回 JSON
function json_out($code, $msg, $data = null) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取对方ID
function get_other_id($my_id) {
    return $my_id == 1 ? 2 : 1;
}
?>