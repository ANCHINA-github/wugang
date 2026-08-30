<?php
// 1. 初始化会话
session_start();

// 启用错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 常量定义
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('DEFAULT_PORTRAIT', 'portrait-img/default-avatar.jpg');
define('UPLOAD_DIR', 'portrait-img/');

// 初始化变量
$msg = '';
$msgType = '';
$newSystemId = '';   // 新生成的10位数字ID
$formData = [
    'pname' => '',
    'gender' => '',
    'portrait' => DEFAULT_PORTRAIT,
    'qq' => ''        // 新增QQ号字段
];

// 2. 辅助函数
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validatePassword($password) {
    if (strlen($password) < 4) {
        return '密码不对'; // 修改：统一提示密码不对
    }
    return true;
}

function validateUsername($username) {
    // 修改：昵称限制8个字（16个字符，中文占2字符）
    $charLen = mb_strlen($username, 'UTF-8');
    if ($charLen < 2 || $charLen > 8) {
        return '昵称需2-8个字';
    }
    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $username)) {
        return '昵称只能包含中文、英文、数字和下划线';
    }
    return true;
}

// 新增：验证QQ号码
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

// 检查QQ号是否已被注册（id字段）
function checkQQExists($qq, $users) {
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] == $qq) {
            return true;
        }
    }
    return false;
}

// 生成新的系统ID（10位数字），基于system_id字段
function getNextSystemId($users) {
    $maxId = 0;
    foreach ($users as $user) {
        // 优先使用system_id，兼容旧数据（旧数据可能没有system_id，则用id转换为数字）
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
        // 获取并清理表单数据
        $pname = sanitizeInput($_POST['pname'] ?? '');
        $qq = sanitizeInput($_POST['qq'] ?? '');          // 新增QQ号
        $password = $_POST['password'] ?? '';
        $confirmPwd = $_POST['confirmPwd'] ?? '';
        $gender = $_POST['gender'] ?? '';
        
        // 确定最终头像路径
        $finalPortrait = DEFAULT_PORTRAIT;
        if (isset($_SESSION['temp_portrait'])) {
            $finalPortrait = $_SESSION['temp_portrait'];
        } elseif (!empty($_POST['portrait'])) {
            $finalPortrait = $_POST['portrait'];
        }
        
        // 表单验证
        $errors = [];
        
        // 验证昵称
        $usernameValidation = validateUsername($pname);
        if ($usernameValidation !== true) {
            $errors[] = $usernameValidation;
        }
        
        // 验证QQ号
        $qqValidation = validateQQ($qq);
        if ($qqValidation !== true) {
            $errors[] = $qqValidation;
        }
        
        // 验证密码
        $passwordValidation = validatePassword($password);
        if ($passwordValidation !== true) {
            $errors[] = $passwordValidation;
        } elseif ($password !== $confirmPwd) {
            $errors[] = '密码不对'; // 修改：统一提示密码不对
        }
        
        // 验证性别
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
            
            // 检查昵称是否已存在
            if (checkUsernameExists($pname, $users)) {
                $msg = '该昵称已被注册，请更换一个';
                $msgType = 'error';
            }
            // 检查QQ号是否已被注册
            elseif (checkQQExists($qq, $users)) {
                $msg = '该QQ号码已被绑定，请更换或联系管理员';
                $msgType = 'error';
            }
            else {
                // 生成新的系统ID（10位数字），存储到system_id字段
                $newSystemId = getNextSystemId($users);
                
                // 构建新用户数据（注意：id字段存储QQ号码，system_id存储10位数字ID）
                $newUser = [
                    'id' => $qq,                      // 登录凭证：QQ号码
                    'system_id' => $newSystemId,      // 系统内部10位数字ID
                    'pname' => $pname,
                    'password' => $password,          // 明文密码（建议改为哈希）
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
                    // 清除会话中的临时头像
                    if (isset($_SESSION['temp_portrait'])) {
                        unset($_SESSION['temp_portrait']);
                    }
                    
                    $msg = '注册成功！您的QQ号码 ' . $qq . ' 已绑定，请使用QQ号登录。系统ID：' . $newSystemId . '（可用于找回账号）';
                    $msgType = 'success';
                } else {
                    $msg = '注册失败，无法写入用户数据文件。';
                    $msgType = 'error';
                }
            }
        }
        
        // 保存表单数据用于回显
        $formData = [
            'pname' => $pname,
            'gender' => $gender,
            'portrait' => $finalPortrait,
            'qq' => $qq
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 | 武冈</title>
    <style>
        /* 1. 删除动态背景，改为纯色 */
        * { 
            -webkit-tap-highlight-color: transparent; 
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 15px; 
            color: #333; 
            overflow-x: hidden;
            /* 纯色背景 */
            background-image: url('./poster.webp');
            background-size: cover;
            background-position: center;
             /* 添加一个半透明的叠层，增加对比度 */
            position: relative;
        }
        
        /* 2. 液态玻璃效果容器，简化布局，兼容多端 */
        .register-container { 
            background: rgba(255, 255, 255, 0.41); 
            padding: 30px 20px; 
            border-radius: 20px; 
            width: 100%; 
            max-width: 420px; 
            /* 液态玻璃核心样式 */
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
            transition: all 0.3s ease;
        }
        
        h2 { 
            text-align: center; 
            color: #4a4a8c; 
            margin: 0 0 25px 0; 
            font-weight: 700; 
            font-size: 20px;
        }
        
        .msg-box { 
            padding: 10px 12px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: 500; 
            animation: fadeIn 0.5s ease; 
            font-size: 14px; 
        }
        .msg-box.success { 
            background-color: #edf7ed; 
            color: #155724; 
            border: 1px solid #d5e6d5; 
        }
        .msg-box.error { 
            background-color: #fef3f2; 
            color: #721c24; 
            border: 1px solid #fde6e3; 
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(-10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #555; 
            font-weight: 500; 
            font-size: 14px; 
        }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid #e0e0e0; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-size: 14px; 
            transition: all 0.3s ease; 
            background: rgba(255, 255, 255, 0.9);
        }
        input[type="text"]:focus, input[type="password"]:focus { 
            border-color: #667eea; 
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15); 
            outline: none; 
        }
        
        .gender-options { 
            display: flex; 
            gap: 15px; 
            margin-top: 8px; 
            flex-wrap: wrap;
        }
        .gender-options label { 
            display: flex; 
            align-items: center; 
            cursor: pointer; 
            transition: color 0.2s ease; 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 14px; 
        }
        .gender-options label:hover { 
            color: #667eea; 
            background: rgba(102, 126, 234, 0.05); 
        }
        
        /* 3. 头像区域：优先上传，布局优化 */
        .portrait-section { 
            margin-bottom: 20px; 
        }
        .portrait-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        .upload-btn, .portrait-select-btn { 
            padding: 10px 12px; 
            background-color: #667eea; 
            color: white; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            text-align: center; 
            font-size: 14px; 
            transition: all 0.3s ease; 
            font-weight: 500;
        }
        .upload-btn:hover, .portrait-select-btn:hover { 
            background-color: #556cd6; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2); 
        }
        .portrait-preview-container { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            margin-top: 10px;
        }
        .portrait-preview { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #e0e0e0; 
            transition: all 0.3s ease; 
        }
        
        .upload-portrait { 
            border: 2px dashed #ccc; 
            padding: 12px; 
            text-align: center; 
            border-radius: 10px; 
            transition: all 0.3s ease; 
            margin-top: 5px; 
        }
        .upload-portrait:hover { 
            border-color: #667eea; 
            background: rgba(102, 126, 234, 0.03); 
        }
        .upload-portrait input[type="file"] { 
            display: none; 
        }
        .upload-portrait p { 
            margin: 0 0 8px 0; 
            font-size: 13px; 
            color: #666;
        }
        .upload-label { 
            display: inline-block; 
            padding: 8px 15px; 
            background-color: #28a745; 
            color: white; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 13px; 
            transition: all 0.3s ease; 
            font-weight: 500; 
        }
        .upload-label:hover { 
            background-color: #218838; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2); 
        }
        
        .submit-btn { 
            width: 100%; 
            padding: 14px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            margin-top: 10px;
        }
        .submit-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 20px rgba(118, 75, 162, 0.2); 
        }
        .submit-btn:disabled { 
            background: #e0e0e0; 
            cursor: not-allowed; 
            transform: none; 
            box-shadow: none;
            color: #999;
        }
        
        /* 头像选择模态框 */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            justify-content: center; 
            align-items: center; 
            backdrop-filter: blur(5px); 
            opacity: 0; 
            transition: opacity 0.3s ease; 
        }
        .modal.active { 
            opacity: 1; 
        }
        .modal-content { 
            background-color: #fff; 
            margin: auto; 
            padding: 20px; 
            border: 1px solid #888; 
            width: 90%; 
            max-width: 600px; 
            max-height: 80vh; 
            overflow-y: auto; 
            border-radius: 15px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); 
            transform: translateY(30px) scale(0.95); 
            transition: all 0.3s ease; 
        }
        .modal.active .modal-content { 
            transform: translateY(0) scale(1); 
        }
        .close { 
            color: #aaa; 
            float: right; 
            font-size: 24px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            position: absolute; 
            top: 15px; 
            right: 15px; 
            width: 25px; 
            height: 25px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
        }
        .close:hover { 
            color: #dc3545; 
            background: rgba(220, 53, 69, 0.1); 
        }
        .portrait-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); 
            gap: 10px; 
            margin-top: 15px; 
        }
        .portrait-item { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            cursor: pointer; 
            border: 2px solid transparent; 
            transition: all 0.3s ease; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        .portrait-item:hover { 
            transform: scale(1.1) rotate(5deg); 
            border-color: #667eea; 
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2); 
        }
        .portrait-item.selected { 
            border-color: #667eea; 
            transform: scale(1.05); 
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2); 
        }
        
        /* 注册成功样式 */
        .success-message { 
            text-align: center; 
            margin: 15px 0; 
        }
        .success-message .user-id { 
            font-size: 22px; 
            font-weight: bold; 
            color: #764ba2; 
            letter-spacing: 2px; 
            margin: 10px 0; 
            padding: 10px; 
            background: rgba(118, 75, 162, 0.1); 
            border-radius: 10px; 
        }
        .copy-notification { 
            margin-top: 10px; 
            padding: 5px 10px; 
            background-color: #e3f2fd; 
            color: #0d47a1; 
            border-radius: 6px; 
            font-size: 13px; 
            display: inline-block; 
        }
        
        /* 反馈提示样式 */
        .field-feedback {
            font-size: 12px;
            margin-top: 5px;
            height: 16px; /* 固定高度避免布局跳动 */
        }
        
        /* 登录链接 */
        .login-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #667eea;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>注册</h2>
        <?php if (!empty($msg)): ?>
            <div class="msg-box <?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($msgType === 'success' && !empty($newSystemId)): ?>
            <!-- 注册成功后的提示 -->
            <div class="success-message">
                <p>注册成功！</p>
                <p>您绑定的QQ号码：</p>
                <div class="user-id" id="qqNumber"><?php echo htmlspecialchars($formData['qq']); ?></div>
                <p style="font-size: 13px; color: #6c757d;">系统内部ID：<?php echo $newSystemId; ?>（仅用于找回账号）</p>
                <p style="font-size: 13px; color: #6c757d; margin-top: 10px;">
                    验证中...
                    <br>
                    <a href="index.html" style="color: #667eea;">立即登录</a>
                </p>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const qqElement = document.getElementById('qqNumber');
                    const qqNumber = qqElement.textContent.trim();
                    localStorage.setItem('lastLoginId', '<?php echo htmlspecialchars($formData['qq']); ?>');
                    navigator.clipboard.writeText(qqNumber).then(() => {
                        const notification = document.createElement('div');
                        notification.className = 'copy-notification';
                        notification.textContent = '✓ QQ号码已自动复制到剪贴板';
                        qqElement.parentNode.insertBefore(notification, qqElement.nextSibling);
                    }).catch(err => {
                        console.error('复制失败:', err);
                    });
                });
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 2000);
            </script>
        <?php else: ?>
            <form id="registerForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">
                <input type="hidden" id="portraitInput" name="portrait" value="<?php echo htmlspecialchars($formData['portrait']); ?>">
                
                <div class="form-group">
                    <label for="pname">昵称 <span style="color:#dc3545;">*</span></label>
                    <input type="text" id="pname" name="pname" 
                           value="<?php echo htmlspecialchars($formData['pname']); ?>" 
                           required placeholder="2-8个字，支持中文/英文/数字/下划线"
                           oninput="checkUsernameAvailability()">
                    <div id="usernameFeedback" class="field-feedback"></div>
                </div>

                <!-- QQ号码输入框 -->
                <div class="form-group">
                    <label for="qq">QQ号码 <span style="color:#dc3545;">*</span></label>
                    <input type="text" id="qq" name="qq" 
                           value="<?php echo htmlspecialchars($formData['qq']); ?>" 
                           required placeholder="5~12位数字，不能以0开头"
                           oninput="validateQQ()">
                    <div id="qqFeedback" class="field-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="password">密码 <span style="color:#dc3545;">*</span></label>
                    <input type="password" id="password" name="password" required placeholder="至少4位">
                    <div id="passwordLengthFeedback" class="field-feedback"></div> <!-- 移除实时验证 -->
                </div>
                
                <div class="form-group">
                    <label for="confirmPwd">确认密码 <span style="color:#dc3545;">*</span></label>
                    <input type="password" id="confirmPwd" name="confirmPwd" required placeholder="再次输入密码">
                    <div id="passwordMatchFeedback" class="field-feedback"></div> <!-- 移除实时验证 -->
                </div>
                
                <div class="form-group">
                    <label>性别 <span style="color:#dc3545;">*</span></label>
                    <div class="gender-options">
                        <label><input type="radio" name="gender" value="男" <?php echo $formData['gender'] === '男' ? 'checked' : ''; ?> required> 男</label>
                        <label><input type="radio" name="gender" value="女" <?php echo $formData['gender'] === '女' ? 'checked' : ''; ?>> 女</label>
                        <label><input type="radio" name="gender" value="保密" <?php echo $formData['gender'] === '保密' ? 'checked' : ''; ?>> 保密</label>
                    </div>
                </div>

                <div class="portrait-section">
                    <label>头像</label>
                    <div class="portrait-preview-container">
                        <img id="currentPortraitPreview" class="portrait-preview" src="<?php echo htmlspecialchars($formData['portrait']); ?>" alt="头像预览" loading="lazy">
                    </div>
                    <!-- 优先显示上传按钮，布局更合理 -->
                    <div class="portrait-actions">
                        <div class="upload-portrait">
                            <p>上传自定义头像（小于2MB，支持JPG/PNG/GIF）</p>
                            <label for="customPortraitInput" class="upload-label">自定义头像</label>
                            <input type="file" id="customPortraitInput" name="custom_portrait" accept="image/*">
                            <div id="uploadStatus" class="field-feedback"></div>
                        </div>
                        <button type="button" class="portrait-select-btn" id="openPortraitModalBtn">从图库选择头像</button>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn" disabled>注 册</button>
                
                <div class="login-link">
                    <a href="./index.html">已有账号？去登录</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- 头像选择模态框 -->
    <div id="portraitModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>选择头像</h3>
            <div class="portrait-grid" id="portraitGrid"></div>
        </div>
    </div>

    <script>
        // 初始化逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('portraitModal');
            const modalBtn = document.getElementById('openPortraitModalBtn');
            const closeBtn = document.querySelector('.close');
            const portraitGrid = document.getElementById('portraitGrid');
            const portraitInput = document.getElementById('portraitInput');
            const currentPreview = document.getElementById('currentPortraitPreview');
            const fileInput = document.getElementById('customPortraitInput');
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            const uploadStatus = document.getElementById('uploadStatus');

            // 打开头像选择模态框
            modalBtn.addEventListener('click', function() {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('active'), 10);
                renderPortraits();
            });

            // 关闭模态框
            function closeModal() {
                modal.classList.remove('active');
                setTimeout(() => modal.style.display = 'none', 300);
            }
            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', function(event) {
                if (event.target === modal) closeModal();
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
                    img.className = 'portrait-item' + (imgSrc === selectedValue ? ' selected' : '');
                    img.addEventListener('click', function() { selectSystemPortrait(imgSrc); });
                    portraitGrid.appendChild(img);
                }
            }

            // 选择系统头像
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
                        uploadStatus.innerHTML = '<span style="color:#28a745;">✓ 已选择系统头像</span>';
                        closeModal();
                    }
                })
                .catch(error => { 
                    console.error(error); 
                    uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ 选择失败</span>'; 
                });
            }

            // 上传自定义头像
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const formData = new FormData();
                    formData.append('action', 'upload_portrait');
                    formData.append('custom_portrait', this.files[0]);
                    uploadStatus.innerHTML = '<span style="color:#666;">上传中...</span>';
                    fetch('', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentPreview.src = data.portraitPath;
                            portraitInput.value = data.portraitPath;
                            uploadStatus.innerHTML = '<span style="color:#28a745;">✓ ' + data.message + '</span>';
                        } else {
                            uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ ' + data.message + '</span>';
                        }
                    })
                    .catch(error => { 
                        console.error(error); 
                        uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ 上传失败</span>'; 
                    });
                }
            });

            // 实时验证表单，控制按钮状态
            function validateForm() {
                const username = document.getElementById('pname').value.trim();
                const qq = document.getElementById('qq').value.trim();
                const password = document.getElementById('password').value;
                const confirmPwd = document.getElementById('confirmPwd').value;
                const gender = document.querySelector('input[name="gender"]:checked');
                
                // 昵称验证（2-8字）
                const isUsernameValid = (mbStrLen(username) >= 2 && mbStrLen(username) <= 8) 
                    && /^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username);
                
                // QQ验证
                const isQQValid = /^[1-9][0-9]{4,11}$/.test(qq);
                
                // 密码验证（仅检查长度，不检查匹配）
                const isPasswordValid = password.length >= 4 && confirmPwd.length >= 4;
                
                // 性别验证
                const isGenderValid = !!gender;
                
                // 所有项都有效才启用按钮
                const isFormValid = isUsernameValid && isQQValid && isPasswordValid && isGenderValid;
                submitBtn.disabled = !isFormValid;
                
                return isFormValid;
            }

            // 辅助函数：计算中文字符长度（中文算1字，英文/数字算1字）
            function mbStrLen(str) {
                return str ? mb_strlen(str, 'UTF-8') : 0;
            }
            // 兼容非PHP环境的mb_strlen
            if (typeof mb_strlen === 'undefined') {
                function mb_strlen(str, encoding) {
                    if (encoding !== 'UTF-8') return str.length;
                    return str.replace(/[^\x00-\xff]/g, 'aa').length / 2;
                }
            }

            // 监听所有输入事件，实时验证
            const allInputs = form.querySelectorAll('input');
            allInputs.forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm); // 单选框change事件
            });

            // 表单提交验证
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('请正确填写所有必填字段');
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.textContent = '注册中...';
            });

            // 初始化按钮状态
            validateForm();
        });

        // 昵称实时验证
        function checkUsernameAvailability() {
            const username = document.getElementById('pname').value.trim();
            const feedback = document.getElementById('usernameFeedback');
            const len = typeof mb_strlen !== 'undefined' ? mb_strlen(username, 'UTF-8') : username.replace(/[^\x00-\xff]/g, 'aa').length / 2;
            
            if (username === '') {
                feedback.innerHTML = '';
                return;
            }
            if (len < 2 || len > 8) {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 昵称需2-8个字</span>';
                return;
            }
            if (!/^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username)) {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 只能包含中文、英文、数字和下划线</span>';
                return;
            }
            feedback.innerHTML = '<span style="color:#28a745;">✓ 格式正确</span>';
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
                feedback.innerHTML = '<span style="color:#28a745;">✓ QQ号格式正确</span>';
            } else {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 请输入5~12位数字，不能以0开头</span>';
            }
        }

        // 移除密码长度和匹配的实时验证函数
        function validatePasswordLength() {}
        function validatePasswordMatch() {}
    </script>
</body>
</html>