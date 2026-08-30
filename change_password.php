<?php
// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $id = trim($_POST['id'] ?? '');
    $pname = trim($_POST['pname'] ?? '');
    
    if (empty($id) || empty($pname)) {
        echo json_encode(['status' => 'error', 'msg' => '账号和昵称不能为空']);
        exit;
    }

    // 读取 root.json
    $rootFile = 'root.json';
    if (!file_exists($rootFile)) {
        echo json_encode(['status' => 'error', 'msg' => '系统错误，请联系管理员']);
        exit;
    }
    $users = json_decode(file_get_contents($rootFile), true) ?? [];
    
    // 查找用户
    $userIndex = -1;
    $user = null;
    foreach ($users as $index => $u) {
        if (isset($u['id']) && $u['id'] === $id && isset($u['pname']) && $u['pname'] === $pname) {
            $userIndex = $index;
            $user = $u;
            break;
        }
    }
    
    if ($user === null) {
        echo json_encode(['status' => 'error', 'msg' => '账号或昵称不匹配，请检查']);
        exit;
    }

    // 处理验证操作
    if ($action === 'verify') {
        // 检查修改记录
        $logFile = 'change_password_data.json';
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        
        // 查找该用户最近一次修改记录（使用 id 字段）
        $lastChange = null;
        foreach ($logs as $log) {
            if (isset($log['id']) && $log['id'] === $id) {
                if ($lastChange === null || strtotime($log['timestamp']) > strtotime($lastChange['timestamp'])) {
                    $lastChange = $log;
                }
            }
        }
        
        // 检查是否在30天内
        if ($lastChange !== null) {
            $lastTime = strtotime($lastChange['timestamp']);
            $now = time();
            $daysDiff = floor(($now - $lastTime) / 86400);
            if ($daysDiff < 30) {
                $remain = 30 - $daysDiff;
                echo json_encode([
                    'status' => 'error',
                    'msg' => "您上次修改密码是在 {$lastChange['timestamp']}，距离现在不足30天，请等待 {$remain} 天后再试"
                ]);
                exit;
            }
        }
        
        // 验证通过
        echo json_encode(['status' => 'success', 'msg' => '身份验证通过，请设置新密码']);
        exit;
    }
    
    // 处理修改密码操作
    if ($action === 'change') {
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        
        if (empty($newPassword) || empty($confirmPassword)) {
            echo json_encode(['status' => 'error', 'msg' => '新密码不能为空']);
            exit;
        }
        
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['status' => 'error', 'msg' => '两次输入的密码不一致']);
            exit;
        }
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['status' => 'error', 'msg' => '密码长度至少6位']);
            exit;
        }
        
        // 更新密码
        $users[$userIndex]['password'] = $newPassword;
        $writeResult = file_put_contents($rootFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        if ($writeResult === false) {
            echo json_encode(['status' => 'error', 'msg' => '密码修改失败，请重试']);
            exit;
        }
        
        // 记录修改日志
        $logFile = 'change_password_data.json';
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        $logs[] = [
            'id' => $id,
            'pname' => $pname,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        // 返回成功，并指示前端清除 localStorage
        echo json_encode([
            'status' => 'success',
            'msg' => '密码修改成功！请重新登录',
            'clear_storage' => true
        ]);
        exit;
    }
    
    echo json_encode(['status' => 'error', 'msg' => '无效的操作']);
    exit;
}

// 否则显示页面
$staticVer = time();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>修改密码</title>
    <link rel="stylesheet" href="/repository/main.css?v=<?php echo $staticVer; ?>">
    <link rel="stylesheet" href="/repository/awesome/css/all.min.css">
    <style>
        body {
            padding: 16px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .back-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            padding: 8px 12px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 16px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-btn:hover {
            background: var(--hover-bg);
        }
        .page-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--text-primary);
        }
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--glass-shadow);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            background: var(--input-bg);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: var(--accent-blue);
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 16px;
            font-weight: bold;
            color: var(--text-light);
            background: var(--button-bg);
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: var(--button-hover);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .tip {
            margin-top: 10px;
            font-size: 14px;
            text-align: center;
            padding: 8px;
            border-radius: var(--border-radius-sm);
            display: none;
        }
        .tip.error {
            display: block;
            color: var(--accent-red);
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid var(--accent-red);
        }
        .tip.success {
            display: block;
            color: var(--accent-green);
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid var(--accent-green);
        }
        .tip.info {
            display: block;
            color: var(--accent-blue);
            background: rgba(74, 144, 226, 0.1);
            border: 1px solid var(--accent-blue);
        }
        .hidden {
            display: none !important;
        }
        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
            .card {
                padding: 15px;
            }
            .page-title {
                font-size: 18px;
            }
            .back-btn {
                font-size: 14px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button class="back-btn" onclick="history.back()">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
            <div class="page-title">修改密码</div>
        </div>

        <!-- 步骤1：身份验证 -->
        <div class="card" id="step1">
            <h3 style="margin-bottom: 15px; font-size: 16px; color: var(--text-primary);">验证身份</h3>
            <div class="form-group">
                <label for="loginId">账号（QQ号）</label>
                <input type="text" id="loginId" placeholder="请输入您的QQ号" required>
            </div>
            <div class="form-group">
                <label for="loginPname">昵称</label>
                <input type="text" id="loginPname" placeholder="请输入您的昵称" required>
            </div>
            <button class="btn" id="verifyBtn">验证身份</button>
            <div class="tip" id="verifyTip"></div>
        </div>

        <!-- 步骤2：修改密码（默认隐藏） -->
        <div class="card hidden" id="step2">
            <h3 style="margin-bottom: 15px; font-size: 16px; color: var(--text-primary);">设置新密码</h3>
            <div class="form-group">
                <label for="newPassword">新密码</label>
                <input type="password" id="newPassword" placeholder="至少6位" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword">确认新密码</label>
                <input type="password" id="confirmPassword" placeholder="再次输入新密码" required>
            </div>
            <button class="btn" id="changeBtn">确认修改</button>
            <div class="tip" id="changeTip"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const verifyBtn = document.getElementById('verifyBtn');
            const changeBtn = document.getElementById('changeBtn');
            const verifyTip = document.getElementById('verifyTip');
            const changeTip = document.getElementById('changeTip');

            // 验证身份
            verifyBtn.addEventListener('click', function() {
                const id = document.getElementById('loginId').value.trim();
                const pname = document.getElementById('loginPname').value.trim();
                if (!id || !pname) {
                    showTip(verifyTip, '请填写完整信息', 'error');
                    return;
                }

                verifyBtn.disabled = true;
                verifyBtn.textContent = '验证中...';
                showTip(verifyTip, '', '');

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=verify&id=${encodeURIComponent(id)}&pname=${encodeURIComponent(pname)}`
                })
                .then(response => response.json())
                .then(result => {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = '验证身份';
                    if (result.status === 'success') {
                        // 验证通过，显示步骤2，隐藏步骤1
                        step1.classList.add('hidden');
                        step2.classList.remove('hidden');
                        // 保存用户信息供后续提交
                        step2.dataset.id = id;
                        step2.dataset.pname = pname;
                        showTip(verifyTip, '✅ ' + result.msg, 'success');
                        // 清空之前可能的错误
                        showTip(changeTip, '', '');
                    } else {
                        showTip(verifyTip, '❌ ' + result.msg, 'error');
                    }
                })
                .catch(() => {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = '验证身份';
                    showTip(verifyTip, '网络错误，请重试', 'error');
                });
            });

            // 修改密码
            changeBtn.addEventListener('click', function() {
                const newPwd = document.getElementById('newPassword').value.trim();
                const confirmPwd = document.getElementById('confirmPassword').value.trim();
                const id = step2.dataset.id;
                const pname = step2.dataset.pname;

                if (!newPwd || !confirmPwd) {
                    showTip(changeTip, '请填写完整信息', 'error');
                    return;
                }
                if (newPwd.length < 6) {
                    showTip(changeTip, '密码长度至少6位', 'error');
                    return;
                }
                if (newPwd !== confirmPwd) {
                    showTip(changeTip, '两次输入的密码不一致', 'error');
                    return;
                }

                changeBtn.disabled = true;
                changeBtn.textContent = '提交中...';
                showTip(changeTip, '', '');

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=change&id=${encodeURIComponent(id)}&pname=${encodeURIComponent(pname)}&new_password=${encodeURIComponent(newPwd)}&confirm_password=${encodeURIComponent(confirmPwd)}`
                })
                .then(response => response.json())
                .then(result => {
                    changeBtn.disabled = false;
                    changeBtn.textContent = '确认修改';
                    if (result.status === 'success') {
                        showTip(changeTip, '✅ ' + result.msg, 'success');
                        // 清除本地存储
                        if (result.clear_storage) {
                            localStorage.clear();
                            // 可选：跳转到登录页或刷新
                            setTimeout(function() {
                                alert('密码修改成功，请重新登录！');
                                window.location.href = '/';
                            }, 1500);
                        }
                    } else {
                        showTip(changeTip, '❌ ' + result.msg, 'error');
                    }
                })
                .catch(() => {
                    changeBtn.disabled = false;
                    changeBtn.textContent = '确认修改';
                    showTip(changeTip, '网络错误，请重试', 'error');
                });
            });

            // 辅助函数：显示提示
            function showTip(element, message, type) {
                element.textContent = message;
                element.className = 'tip';
                if (type) element.classList.add(type);
                if (message) element.style.display = 'block';
                else element.style.display = 'none';
            }
        });
    </script>
</body>
</html>