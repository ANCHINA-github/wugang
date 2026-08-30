<?php
// 检查是否有POST提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证必填字段
    if (empty($_POST['reporter_id']) || empty($_POST['reported_id']) || empty($_POST['content'])) {
        $error = "请填写所有必填字段";
    } else {
        // 处理图片上传
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            // 确保上传目录存在
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $file_name;
            
            // 检查文件类型
            $file_type = mime_content_type($_FILES['image']['tmp_name']);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = 'uploads/' . $file_name;
                } else {
                    $error = "图片上传失败";
                }
            } else {
                $error = "只允许上传JPG、PNG和GIF图片";
            }
        }
        
        // 如果没有错误，保存数据
        if (!isset($error)) {
            $report = [
                'id' => time(),
                'reporter_id' => $_POST['reporter_id'],
                'reported_id' => $_POST['reported_id'],
                'content' => $_POST['content'],
                'image' => $image_path,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // 读取现有数据
            $json_file = __DIR__ . '/jb.json';
            $reports = [];
            
            if (file_exists($json_file)) {
                $json_data = file_get_contents($json_file);
                $reports = json_decode($json_data, true) ?: [];
            }
            
            // 添加新举报
            $reports[] = $report;
            
            // 保存到文件
            if (file_put_contents($json_file, json_encode($reports, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                $success = "举报提交成功！";
                // 清空表单
                $_POST = [];
            } else {
                $error = "保存举报数据失败";
            }
        }
    }
}

// 模拟用户数据，实际应用中应从数据库获取
$users = [
    ['id' => 'user001', 'name' => '张三'],
    ['id' => 'user002', 'name' => '李四'],
    ['id' => 'user003', 'name' => '王五'],
    ['id' => 'user004', 'name' => '赵六'],
    ['id' => 'user005', 'name' => '钱七'],
    ['id' => 'testuser', 'name' => '测试用户']
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提交举报</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-lg">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white p-4">
                <h1 class="text-xl font-bold">提交举报</h1>
            </div>
            
            <div class="p-4">
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="reporter_id" class="block text-gray-700 font-medium mb-2">举报人ID <span class="text-red-500">*</span></label>
                        <input type="text" id="reporter_id" name="reporter_id" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['reporter_id']) ? htmlspecialchars($_POST['reporter_id']) : ''; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="reported_id" class="block text-gray-700 font-medium mb-2">被举报人ID <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" id="reported_id" name="reported_id" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="<?php echo isset($_POST['reported_id']) ? htmlspecialchars($_POST['reported_id']) : ''; ?>"
                                   placeholder="输入ID或姓名搜索">
                            <div id="user-results" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden"></div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="content" class="block text-gray-700 font-medium mb-2">举报内容 <span class="text-red-500">*</span></label>
                        <textarea id="content" name="content" rows="5" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="请详细描述举报内容..."><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="image" class="block text-gray-700 font-medium mb-2">举报图片</label>
                        <input type="file" id="image" name="image" accept="image/*"
                               class="w-full text-gray-700 px-3 py-2 border border-gray-300 rounded-md">
                        <p class="text-sm text-gray-500 mt-1">支持JPG、PNG、GIF格式，最大5MB</p>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            提交举报
                        </button>
                         <a href="../login-service.php" 
                           class="text-blue-600 hover:text-blue-800 font-medium">
                            返回
                        </a>
                        <a href="report_view.php" 
                           class="text-blue-600 hover:text-blue-800 font-medium">
                            查看举报列表
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('reported_id');
            const resultsContainer = document.getElementById('user-results');
            const users = <?php echo json_encode($users); ?>;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toLowerCase();
                resultsContainer.innerHTML = '';
                
                if (searchTerm.length < 1) {
                    resultsContainer.classList.add('hidden');
                    return;
                }
                
                const matchedUsers = users.filter(user => 
                    user.id.toLowerCase().includes(searchTerm) || 
                    user.name.toLowerCase().includes(searchTerm)
                );
                
                if (matchedUsers.length > 0) {
                    resultsContainer.classList.remove('hidden');
                    matchedUsers.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
                        div.innerHTML = `<strong>${user.id}</strong> - ${user.name}`;
                        div.addEventListener('click', function() {
                            searchInput.value = user.id;
                            resultsContainer.classList.add('hidden');
                        });
                        resultsContainer.appendChild(div);
                    });
                } else {
                    resultsContainer.classList.remove('hidden');
                    const div = document.createElement('div');
                    div.className = 'px-4 py-2 text-gray-500';
                    div.textContent = '未找到匹配用户';
                    resultsContainer.appendChild(div);
                }
            });
            
            // 点击页面其他地方关闭搜索结果
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>