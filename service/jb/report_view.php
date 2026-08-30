<?php
// 读取举报数据
$json_file = __DIR__ . '/jb.json';
$reports = [];

if (file_exists($json_file)) {
    $json_data = file_get_contents($json_file);
    $reports = json_decode($json_data, true) ?: [];
    // 按 id 降序排序（id 为时间戳）
    usort($reports, function($a, $b) {
        return $b['id'] - $a['id'];
    });
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>举报列表</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white p-4 flex justify-between items-center">
                <h1 class="text-xl font-bold">举报列表</h1>
                <a href="report_submit.php" 
                   class="bg-white text-blue-600 hover:bg-gray-100 font-medium py-1 px-3 rounded-md text-sm">
                    提交新举报
                </a>
            </div>
            
            <div class="p-4">
                <?php if (empty($reports)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 010-5.656L5.5 10.5a2 2 0 012.828-2.828l6.856 6.856a4 4 0 01-5.656 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V8a1 1 0 00-1-1H4a1 1 0 00-1 1v8a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 16h14a1 1 0 001-1V8a1 1 0 00-1-1H5a1 1 0 00-1 1v8a1 1 0 001 1z" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900">暂无举报数据</h3>
                        <p class="mt-1 text-sm text-gray-500">还没有提交任何举报记录</p>
                        <div class="mt-6">
                            <a href="report_submit.php" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                提交第一个举报
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">举报时间</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">举报人</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">被举报人</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">举报内容</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">图片</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($report['id']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($report['time']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($report['reporter_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($report['reported_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($report['content']); ?>">
                                                <?php echo htmlspecialchars($report['content']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if (!empty($report['image']) && file_exists($report['image'])): ?>
                                                <button onclick="showImage('<?php echo htmlspecialchars($report['image']); ?>')" 
                                                        class="text-blue-600 hover:text-blue-800">查看图片</button>
                                            <?php else: ?>
                                                <span class="text-gray-400">无图片</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 图片查看模态框 -->
    <div id="image-modal" class="fixed inset-0 bg-black bg-opacity-80 z-50 flex items-center justify-center hidden">
        <button onclick="hideImage()" class="absolute top-4 right-4 text-white text-3xl font-bold">&times;</button>
        <img id="modal-image" src="" alt="举报图片" class="max-w-full max-h-full object-contain p-4">
    </div>

    <script>
        function showImage(imageUrl) {
            document.getElementById('modal-image').src = imageUrl;
            document.getElementById('image-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function hideImage() {
            document.getElementById('image-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // 点击背景关闭
        document.getElementById('image-modal').addEventListener('click', function(e) {
            if (e.target === this) hideImage();
        });
        
        // ESC 关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideImage();
        });
    </script>
</body>
</html>