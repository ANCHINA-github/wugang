<?php
$error = '';
$success = '';
// 接收网址传过来的两个昵称，自动回填
$repName  = isset($_GET['self']) ? trim(urldecode($_GET['self'])) : '';
$beRepName= isset($_GET['target']) ? trim(urldecode($_GET['target'])) : '';
$content = '';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repName  = trim($_POST['reporter_name'] ?? '');
    $beRepName= trim($_POST['reported_name'] ?? '');
    $content  = trim($_POST['content'] ?? '');

    if (empty($repName) || empty($beRepName) || empty($content)) {
        $error = "请填写所有必填项";
    } else {
        $image_path = '';
        // 图片上传
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $file_name;
            $file_type = mime_content_type($_FILES['image']['tmp_name']);
            $allow = ['image/jpeg','image/png','image/gif'];

            if (in_array($file_type, $allow)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = 'uploads/' . $file_name;
                } else {
                    $error = "图片上传失败";
                }
            } else {
                $error = "仅支持 JPG / PNG / GIF 图片";
            }
        }

        // 写入JSON数据
        if (empty($error)) {
            $report = [
                'id' => time(),
                'reporter_name'  => $repName,
                'reported_name'  => $beRepName,
                'content' => $content,
                'image'   => $image_path,
                'time'    => date('Y-m-d H:i:s')
            ];
            $json_file = __DIR__ . '/jb.json';
            $lists = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
            $lists[] = $report;

            if (file_put_contents($json_file, json_encode($lists, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                $success = "举报提交成功！";
                $repName = $beRepName = $content = '';
            } else {
                $error = "数据保存失败";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提交举报</title>
    <style>
        /* ---------- 全局重置 ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f4f8;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            padding: 1rem;
        }

        /* 卡片容器 */
        .report-card {
            max-width: 32rem;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 100px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* 头部 */
        .card-header {
            background-color: #2563eb;
            color: white;
            padding: 1rem;
        }
        .card-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        /* 主体内容 */
        .card-body {
            padding: 1rem;
        }

        /* 表单组 */
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .required-star {
            color: #b91c1c;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        textarea.form-control {
            resize: vertical;
        }
        input[type="file"].form-control {
            padding-top: 0.3rem;
            padding-bottom: 0.3rem;
        }

        /* 提示信息 */
        .alert {
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* 图片提示文字 */
        .image-hint {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* 按钮和链接组 */
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            transition: background-color 0.2s, transform 0.05s;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-primary:active {
            transform: scale(0.98);
        }
        .link {
            color: #2563eb;
            text-decoration: none;
        }
        .link:hover {
            text-decoration: underline;
            color: #1e40af;
        }

        /* 简单响应式 */
        @media (max-width: 640px) {
            body {
                padding: 0.75rem;
            }
            .action-bar {
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="report-card">
        <div class="card-header">
            <h1>提交举报</h1>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>举报人昵称 <span class="required-star">*</span></label>
                    <input type="text" name="reporter_name" required class="form-control"
                           value="<?php echo htmlspecialchars($repName); ?>">
                </div>

                <div class="form-group">
                    <label>被举报人昵称 <span class="required-star">*</span></label>
                    <input type="text" name="reported_name" required class="form-control"
                           value="<?php echo htmlspecialchars($beRepName); ?>">
                </div>

                <div class="form-group">
                    <label>举报内容 <span class="required-star">*</span></label>
                    <textarea name="content" rows="4" required class="form-control"><?php echo htmlspecialchars($content); ?></textarea>
                </div>

                <div class="form-group">
                    <label>举证图片（选填）</label>
                    <input type="file" name="image" accept="image/*" class="form-control">
                    <div class="image-hint">支持 JPG / PNG / GIF</div>
                </div>

                <div class="action-bar">
                    <button type="submit" class="btn btn-primary">提交举报</button>
                    <a href="#" class="link" onclick="event.preventDefault(); history.length > 1 ? history.back() : window.location.href = '/'">返回</a>
                    <a href="report_view.php" class="link">举报列表</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>