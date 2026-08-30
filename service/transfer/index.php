<?php
// transfer.php - 带自动清理的个人文件传输系统

// ---------- 自动清理：每10小时执行一次 ----------
function cleanup_old_files() {
    $cleanupFile = __DIR__ . '/last_cleanup.txt';
    $jsonFile = __DIR__ . '/files.json';
    $uploadDir = __DIR__ . '/uploads/';
    $now = time();
    $interval = 10 * 3600; // 10小时

    // 读取上次清理时间
    $lastCleanup = 0;
    if (file_exists($cleanupFile)) {
        $lastCleanup = (int)file_get_contents($cleanupFile);
    }

    // 如果距离上次清理不足10小时，则跳过
    if ($now - $lastCleanup < $interval) {
        return;
    }

    // 如果没有文件列表，直接更新清理时间并返回
    if (!file_exists($jsonFile)) {
        file_put_contents($cleanupFile, $now);
        return;
    }

    $files = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($files)) {
        $files = [];
    }

    $newFiles = [];
    foreach ($files as $file) {
        // 检查文件上传时间（使用记录中的time字段）
        $uploadTime = strtotime($file['time']);
        if ($uploadTime === false) {
            // 时间格式异常，保留文件（保守处理）
            $newFiles[] = $file;
            continue;
        }

        // 如果上传时间距离现在超过10小时，则删除文件并跳过记录
        if ($now - $uploadTime >= $interval) {
            $filePath = $uploadDir . $file['id'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // 不保留该记录
        } else {
            // 检查物理文件是否存在，若不存在则移除记录
            $filePath = $uploadDir . $file['id'];
            if (file_exists($filePath)) {
                $newFiles[] = $file;
            }
            // 否则自动丢弃记录
        }
    }

    // 写入更新后的文件列表
    file_put_contents($jsonFile, json_encode($newFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // 更新清理时间
    file_put_contents($cleanupFile, $now);
}

// 每次请求都执行清理检查
cleanup_old_files();

// ---------- 配置 ----------
$uploadDir = __DIR__ . '/uploads/';
$jsonFile = __DIR__ . '/files.json';

// 确保存储目录和 JSON 文件存在
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}

// ---------- 路由处理 ----------
$action = $_GET['action'] ?? '';

if ($action === 'upload') {
    // ---------- 处理文件上传 ----------
    header('Content-Type: application/json');

    $response = ['success' => false];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['error'] = '上传错误：' . $file['error'];
            echo json_encode($response);
            exit;
        }

        // 生成唯一 ID 作为存储文件名
        $fileId = uniqid() . '_' . bin2hex(random_bytes(8));
        $originalName = basename($file['name']);
        $targetPath = $uploadDir . $fileId;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 读取现有文件列表
            $files = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($files)) {
                $files = [];
            }

            // 添加新文件信息
            $fileInfo = [
                'id' => $fileId,
                'original_name' => $originalName,
                'size' => $file['size'],
                'time' => date('Y-m-d H:i:s')
            ];
            $files[] = $fileInfo;

            // 写回 JSON
            file_put_contents($jsonFile, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $response['success'] = true;
            $response['file'] = $fileInfo;
        } else {
            $response['error'] = '保存文件失败';
        }
    } else {
        $response['error'] = '无效的请求';
    }

    echo json_encode($response);
    exit;

} elseif ($action === 'list') {
    // ---------- 返回文件列表 ----------
    header('Content-Type: application/json');

    $files = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($files)) {
        $files = [];
    }

    // 按时间倒序排列（最新的在前）
    usort($files, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    echo json_encode($files, JSON_UNESCAPED_UNICODE);
    exit;

} elseif ($action === 'download') {
    // ---------- 处理文件下载 ----------
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        die('缺少文件 ID');
    }

    $files = json_decode(file_get_contents($jsonFile), true);
    $fileInfo = null;
    foreach ($files as $f) {
        if ($f['id'] === $id) {
            $fileInfo = $f;
            break;
        }
    }

    if (!$fileInfo) {
        http_response_code(404);
        die('文件不存在');
    }

    $filePath = $uploadDir . $id;
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('文件数据丢失');
    }

    // 发送下载头
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileInfo['original_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

// ---------- 默认输出 HTML 界面 ----------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人文件传输助手（自动清理）</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .upload-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        #fileInput { margin-right: 10px; }
        button { background: #07c; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #069; }
        .file-list { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .download-link { color: #07c; text-decoration: none; }
        .download-link:hover { text-decoration: underline; }
        .size, .time { color: #666; font-size: 0.9em; }
        .status { margin-top: 10px; color: green; }
        .cleanup-note { font-size: 0.8em; color: #999; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <h1>📁 文件传输助手</h1>

    <div class="upload-box">
        <h3>上传新文件</h3>
        <input type="file" id="fileInput">
        <button onclick="uploadFile()">上传</button>
        <div id="uploadStatus" class="status"></div>
    </div>

    <div class="file-list">
        <h3>文件列表</h3>
        <table id="fileTable">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th>大小</th>
                    <th>上传时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="fileList">
                <tr><td colspan="4" style="text-align:center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="cleanup-note">
        ⏳ 文件保存10小时，超过后自动清理（每次访问时检查）
    </div>

    <script>
        const baseUrl = window.location.pathname;

        async function uploadFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            if (!file) {
                alert('请先选择文件');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            document.getElementById('uploadStatus').innerText = '上传中...';

            try {
                const res = await fetch(baseUrl + '?action=upload', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    document.getElementById('uploadStatus').innerText = '✅ 上传成功';
                    fileInput.value = '';
                    loadFileList();
                } else {
                    document.getElementById('uploadStatus').innerText = '❌ 上传失败：' + result.error;
                }
            } catch (err) {
                document.getElementById('uploadStatus').innerText = '❌ 上传出错';
                console.error(err);
            }
        }

        async function loadFileList() {
            const tbody = document.getElementById('fileList');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">加载中...</td></tr>';

            try {
                const res = await fetch(baseUrl + '?action=list');
                const files = await res.json();

                if (files.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">暂无文件</td></tr>';
                    return;
                }

                let html = '';
                files.forEach(file => {
                    const size = formatBytes(file.size);
                    html += `<tr>
                        <td>${escapeHtml(file.original_name)}</td>
                        <td class="size">${size}</td>
                        <td class="time">${file.time}</td>
                        <td><a href="${baseUrl}?action=download&id=${file.id}" class="download-link" target="_blank">下载</a></td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:red;">加载失败</td></tr>';
                console.error(err);
            }
        }

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function escapeHtml(unsafe) {
            return unsafe.replace(/[&<>"]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                if (m === '"') return '&quot;';
                return m;
            });
        }

        window.onload = loadFileList;
    </script>
</body>
</html>