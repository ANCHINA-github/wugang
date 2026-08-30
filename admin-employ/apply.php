<?php
// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 获取表单数据
    $name = trim($_POST["name"]);
    $class = trim($_POST["class"]);
    $qq = trim($_POST["qq"]);
    $reason = trim($_POST["reason"]);
    
    // 验证数据
    $errors = [];
    
    // 验证真实姓名
    if (empty($name)) {
        $errors[] = "真实姓名不能为空";
    } elseif (mb_strlen($name, 'UTF-8') > 4) {
        $errors[] = "真实姓名不多于四个字";
    } elseif (!preg_match("/^[\x{4e00}-\x{9fa5}]+$/u", $name)) {
        $errors[] = "真实姓名只能包含汉字";
    }
    
    // 验证班级
    if (empty($class)) {
        $errors[] = "班级不能为空";
    } elseif (!is_numeric($class)) {
        $errors[] = "班级只能是数字";
    }
    
    // 验证QQ号
    if (empty($qq)) {
        $errors[] = "QQ号不能为空";
    } elseif (!is_numeric($qq)) {
        $errors[] = "QQ号只能是数字";
    }
    
    // 验证申请理由
    if (empty($reason)) {
        $errors[] = "申请理由不能为空";
    } elseif (mb_strlen($reason, 'UTF-8') < 10) {
        $errors[] = "申请理由不少于10字";
    }
    
    // 如果没有错误，则保存数据
    if (empty($errors)) {
        // 创建数据数组
        $data = [
            "name" => $name,
            "class" => $class,
            "qq" => $qq,
            "reason" => $reason,
            "time" => date("Y-m-d H:i:s")
        ];
        
        // 读取现有数据
        $jsonFile = "admin.json";
        $existingData = [];
        
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $existingData = json_decode($jsonContent, true) ?: [];
        }
        
        // 添加新数据
        $existingData[] = $data;
        
        // 保存到文件
        file_put_contents($jsonFile, json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        // --- 主要修改点：修改跳转逻辑 ---
        // 显示成功消息并跳转
        echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>提交成功</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 flex items-center justify-center min-h-screen'>
    <div class='bg-white p-8 rounded-lg shadow-md max-w-md w-full'>
        <h2 class='text-2xl font-bold mb-4 text-center text-green-600'>提交成功</h2>
        <p class='text-center mb-6'>您的申请已成功提交，我们将尽快处理。</p>
        <div class='text-center'>
            <!-- 修正了 onclick 中的引号错误 -->
            <button onclick='goToLogin()' class='bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded'>前往登录页面</button>
        </div>
    </div>
    <script>
        function goToLogin() {
            // 跳转到指定页面
            window.location.href = '../login-service.php';
        }
        // 3秒后自动跳转
        setTimeout(goToLogin, 3000);
    </script>
</body>
</html>
";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安的小屋 | 管理员招募</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-md">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-6 text-center">安的小屋 | 管理员</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-bold mb-2">真实姓名</label>
                    <input type="text" id="name" name="name" maxlength="4" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="不多于四个字" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label for="class" class="block text-gray-700 font-bold mb-2">班级</label>
                    <input type="text" id="class" name="class" pattern="[0-9]+" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="只能是数字" value="<?php echo isset($class) ? htmlspecialchars($class) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label for="qq" class="block text-gray-700 font-bold mb-2">QQ号</label>
                    <input type="text" id="qq" name="qq" pattern="[0-9]+" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="您的QQ号码" value="<?php echo isset($qq) ? htmlspecialchars($qq) : ''; ?>">
                </div>
                
                <div class="mb-6">
                    <label for="reason" class="block text-gray-700 font-bold mb-2">申请理由</label>
                    <textarea id="reason" name="reason" rows="4" minlength="10" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="不少于10字"><?php echo isset($reason) ? htmlspecialchars($reason) : ''; ?></textarea>
                </div>
                
                <div class="flex items-center justify-center">
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        提交申请
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>