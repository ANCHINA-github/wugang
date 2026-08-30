<?php
/**
 * 人机验证核心逻辑（30秒刷新一次验证码，修复验证失败问题）
 */
date_default_timezone_set('Asia/Shanghai');
$verifyFile = 'verify_data.json';

/**
 * 生成动态验证码（强制生成新码，取消缓存判断，保证刷新有效）
 * @return array 验证信息数组
 */
function generateVerifyCode() {
    global $verifyFile;
    $timestamp = time();
    $randomCode = mt_rand(100000, 999999);
    $verifyCode = substr($timestamp . $randomCode, -6);
    
    $verifyData = [
        'code' => $verifyCode,
        'create_time' => $timestamp,
        'expire_seconds' => 30
    ];
    
    // 强制写入新验证码，覆盖旧数据
    file_put_contents($verifyFile, json_encode($verifyData, JSON_UNESCAPED_UNICODE));
    return $verifyData;
}

/**
 * 校验验证码是否正确
 * @param string $inputCode 用户输入的验证码
 * @return array 校验结果
 */
function checkVerifyCode($inputCode) {
    global $verifyFile;
    
    // 检查文件是否存在
    if (!file_exists($verifyFile)) {
        return ['status' => false, 'msg' => '验证信息不存在，请刷新页面'];
    }
    
    $verifyData = json_decode(file_get_contents($verifyFile), true);
    if (empty($verifyData)) {
        return ['status' => false, 'msg' => '验证信息已失效，请刷新页面'];
    }
    
    // 检查是否过期
    $currentTime = time();
    $expireTime = $verifyData['create_time'] + $verifyData['expire_seconds'];
    if ($currentTime > $expireTime) {
        // 过期后清空文件，提示重新获取
        file_put_contents($verifyFile, json_encode([]));
        return ['status' => false, 'msg' => '验证码已过期（有效期30秒），请刷新页面'];
    }
    
    // 检查验证码匹配
    if ($inputCode !== $verifyData['code']) {
        return ['status' => false, 'msg' => '验证码错误，请重新输入'];
    }
    
    // 验证通过：不清空文件，仅标记已使用（避免刷新前验证失败）
    $verifyData['used'] = true;
    file_put_contents($verifyFile, json_encode($verifyData, JSON_UNESCAPED_UNICODE));
    return ['status' => true, 'msg' => '验证通过！'];
}

// 处理前端请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate') {
        $data = generateVerifyCode();
        echo json_encode(['status' => true, 'data' => $data]);
    }
    elseif ($action === 'check') {
        $inputCode = $_POST['code'] ?? '';
        $result = checkVerifyCode($inputCode);
        echo json_encode($result);
    }
    else {
        echo json_encode(['status' => false, 'msg' => '无效的请求动作']);
    }
    exit;
}
?>