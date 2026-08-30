<?php
// ========== 接收参数 ==========
$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if (empty($uid)) {
    echo "<script>alert('参数错误：未获取到用户 QQ 号！');history.back();</script>";
    exit;
}

// ========== 读取用户信息 ==========
$rootFile = __DIR__ . '/root.json';
$userData = [
    'uid' => $uid,
    'pname' => '未知用户',
    'portrait' => ''
];

if (file_exists($rootFile)) {
    $users = json_decode(file_get_contents($rootFile), true);
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] == $uid) {
            $userData['pname'] = $user['pname'] ?? '未知用户';
            $userData['portrait'] = $user['portrait'] ?? '';
            break;
        }
    }
}

// ========== 写入 cancel.json ==========
$cancelFile = __DIR__ . '/cancel.json';
$records = [];

// 读取已有的注销记录
if (file_exists($cancelFile)) {
    $content = file_get_contents($cancelFile);
    $records = json_decode($content, true);
    if (!is_array($records)) {
        $records = [];
    }
}

// 组装新申请数据
$newRecord = [
    'uid' => $uid,
    'pname' => $userData['pname'],
    'portrait' => $userData['portrait'],
    'apply_time' => date('Y-m-d H:i:s'),
    'status' => 'pending' // pending 代表待管理员审核
];

// 追加到数组中
$records[] = $newRecord;

// 写入 JSON 文件（加锁防止并发冲突）
file_put_contents($cancelFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5; url=wenjuan.php">
    <title>注销申请提交中</title>
    <style>
        /* 简约居中的加载页面 */
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4f7fc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .loading-box {
            background: #ffffff;
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e3e8ee;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h3 {
            margin: 0 0 10px;
            color: #1e293b;
            font-size: 20px;
        }
        p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="loading-box">
        <div class="spinner"></div>
        <h3>正在提交注销申请...</h3>
        <p>请稍候，正在向管理员发送您的数据...</p>
    </div>

<script>
    setTimeout(function() {
        // 1. 弹出提示
        alert("注销审核中");
        
        // 2. 尝试先打开一个空窗口
        let newWindow = window.open('about:blank', '_blank');
        
        // 3. 如果成功打开了空窗口，再强行把它的地址改成 wenjuan.php
        if (newWindow) {
            newWindow.location.href = 'wenjuan.php';
        } else {
            // 4. 如果依然被拦截（newWindow 为 null），则退而求其次在当前页面跳转
            window.location.href = 'wenjuan.php';
        }
    }, 1500);
</script>
</body>
</html>