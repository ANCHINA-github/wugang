<?php
// ========== 配置 ==========
// JSON 文件直接存放在当前目录（与 survey.php 同级）
$dataFile = __DIR__ . '/surveys.json';

// ========== 处理表单提交 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并过滤数据
    $nickname   = trim($_POST['nickname'] ?? '');
    $qq         = trim($_POST['qq'] ?? '');
    $comfort    = $_POST['comfort'] ?? '';
    $suggestion = trim($_POST['suggestion'] ?? '');

    // 简单验证（根据新规则定制）
    $errors = [];
    if (empty($nickname)) {
        $errors[] = '请输入您的昵称。';
    } elseif (mb_strlen($nickname, 'UTF-8') > 8) {
        $errors[] = '昵称不能超过 8 个字。';
    }

    if (empty($qq)) {
        $errors[] = '请输入您的 QQ 号。';
    } elseif (!ctype_digit($qq)) {
        $errors[] = 'QQ 号必须为纯数字。';
    } elseif (strlen($qq) > 10) {
        $errors[] = 'QQ 号不能大于 10 位数字。';
    }

    if (empty($comfort)) {
        $errors[] = '请选择网站用着是否舒服。';
    }

    if (!empty($suggestion) && mb_strlen($suggestion, 'UTF-8') > 300) {
        $errors[] = '建议内容不能超过 300 字。';
    }

    if (empty($errors)) {
        // 准备新记录（字段结构已更新）
        $newEntry = [
            'nickname'    => $nickname,
            'qq'          => $qq,
            'comfort'     => $comfort,
            'suggestion'  => $suggestion,
            'submitted_at'=> date('Y-m-d H:i:s')
        ];

        // 读取现有数据（如果文件不存在则创建空数组）
        $existingData = [];
        if (file_exists($dataFile)) {
            $jsonContent = file_get_contents($dataFile);
            $existingData = json_decode($jsonContent, true) ?? [];
        }

        // 追加新记录
        $existingData[] = $newEntry;

        // 写入 JSON 文件（加锁防止并发冲突）
        $json = json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($dataFile, $json, LOCK_EX);

        // 成功提示
        $success = '感谢您的反馈！问卷已成功提交。';
        // 清空表单数据
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站使用反馈</title>
    <style>
        /* 统一使用现代、正式的系统字体 */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #eef2f7;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 580px;
            width: 100%;
            background: #ffffff;
            padding: 40px 40px 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            position: relative;
        }
        /* 返回链接 */
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2c3e50;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #1a252f;
            text-decoration: underline;
        }
        h2 {
            text-align: center;
            color: #1e293b;
            font-weight: 600;
            margin: 0 0 30px 0;
            font-size: 24px;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 22px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #334155;
            font-size: 15px;
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d9e6;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #f8fafc;
        }
        input:focus,
        textarea:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: #ffffff;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        /* 报错与成功提示 */
        .error-msg {
            color: #dc2626;
            font-size: 14px;
            background: #fef2f2;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #fee2e2;
        }
        .error-msg div {
            margin-top: 4px;
        }
        .error-msg div:first-child {
            margin-top: 0;
        }
        .success-msg {
            background: #ecfdf5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #a7f3d0;
            text-align: center;
        }
        /* 单选按钮 */
        .radio-group {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .radio-group label {
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #334155;
        }
        .radio-group input[type="radio"] {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #2563eb;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            letter-spacing: 1px;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
        .btn-submit:active {
            transform: scale(0.98);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .footer-note {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            margin-top: 20px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- 返回首页链接 -->
    <a href="index.html" class="back-link">忍痛返回</a>

    <h2>网站使用反馈</h2>

    <?php if (isset($success)): ?>
        <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <!-- 成功提交后，延迟 2 秒跳转回首页 -->
        <script>
            setTimeout(function() {
                window.location.href = 'index.html';
            }, 2000);
        </script>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-msg">
            <?php foreach ($errors as $err): ?>
                <div>• <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <!-- 1. 昵称（不是真实姓名）（必填）（限制为八个字） -->
        <div class="form-group">
            <label for="nickname">昵称 <span style="color:#dc2626;">*</span></label>
            <input type="text" id="nickname" name="nickname" placeholder="请输入您的昵称" maxlength="8" value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>" required>
        </div>

        <!-- 2. 本站ID（QQ号）（必填）（限制为数字，且不得大于10位数） -->
        <div class="form-group">
            <label for="qq">本站ID (QQ号) <span style="color:#dc2626;">*</span></label>
            <input type="text" id="qq" name="qq" inputmode="numeric" pattern="[0-9]*" maxlength="10" placeholder="请输入您的 QQ 号" oninput="value=value.replace(/[^0-9]/g,'')" value="<?= htmlspecialchars($_POST['qq'] ?? '') ?>" required>
        </div>

        <!-- 3. 这个网站用着是否舒服（必填）（选项为是或者否） -->
        <div class="form-group">
            <label>这个网站用着是否舒服 <span style="color:#dc2626;">*</span></label>
            <div class="radio-group">
                <label><input type="radio" name="comfort" value="是" <?= (isset($_POST['comfort']) && $_POST['comfort'] == '是') ? 'checked' : '' ?> required> 是</label>
                <label><input type="radio" name="comfort" value="否" <?= (isset($_POST['comfort']) && $_POST['comfort'] == '否') ? 'checked' : '' ?>> 否</label>
            </div>
        </div>

        <!-- 4. 填写你关于这个网站的建议（最多300字） -->
        <div class="form-group">
            <label for="suggestion">填写你关于这个网站的建议 <span style="color:#94a3b8; font-weight:400;">(最多300字)</span></label>
            <textarea id="suggestion" name="suggestion" maxlength="300" placeholder="你觉得哪些功能需要优化？
你建议增加什么功能？
哪些方面做的不够好看？
武冈美食榜和必玩榜怎么样？" rows="5"><?= htmlspecialchars($_POST['suggestion'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-submit">提交反馈</button>
    </form>
    <div class="footer-note">* 为必填项 | 您的反馈对我们至关重要</div>
</div>
</body>
</html>