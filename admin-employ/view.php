<?php
session_start();

// 设置密码（明文）
$password = "wugang10"; // 请修改为您的密码

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 处理密码提交
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
        if ($_POST['password'] === $password) {
            // 登录成功
            $_SESSION['logged_in'] = true;
            // 刷新页面以显示内容
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "密码错误，请重试";
        }
    }
    
    // 显示登录表单
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
            <h2 class="text-2xl font-bold mb-6 text-center">管理员登录</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-bold mb-2">请输入密码</label>
                    <input type="password" id="password" name="password" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                
                <div class="flex items-center justify-center">
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        登录
                    </button>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 读取申请数据
$jsonFile = "admin.json";
$applications = [];

if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $applications = json_decode($jsonContent, true) ?: [];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员申请列表</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-2xl font-bold mb-6 text-center">管理员申请列表</h1>
            
            <?php if (empty($applications)): ?>
                <p class="text-center text-gray-500">暂无申请记录</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="py-3 px-4 text-left">姓名</th>
                                <th class="py-3 px-4 text-left">班级</th>
                                <th class="py-3 px-4 text-left">QQ号</th>
                                <th class="py-3 px-4 text-left">申请理由</th>
                                <th class="py-3 px-4 text-left">提交时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($app['name']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($app['class']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($app['qq']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($app['reason']); ?></td>
                                    <td class="py-3 px-4 text-gray-500"><?php echo htmlspecialchars($app['time']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center">
            <a href="apply.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-block">返回申请页面</a>
        </div>
    </div>
</body>
</html>