<?php
date_default_timezone_set('Asia/Shanghai');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // 验证ID为1-12位数字
    if (!preg_match('/^\d{1,12}$/', $id)) {
        $error = "ID必须为1-12位数字";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "邮箱格式不正确";
    }
    elseif (strlen(trim($feedback)) > 300 || strlen(trim($feedback)) == 0) {
        $error = "反馈内容不能为空，且不能超过300字";
    }
    else {
        $data = [
            'id' => $id,
            'email' => $email,
            'feedback' => $feedback,
            'date' => date('Y-m-d H:i:s')
        ];
        
        $problems = [];
        if (file_exists('problem.json')) {
            $json = file_get_contents('problem.json');
            $problems = json_decode($json, true) ?: [];
        }
        
        array_unshift($problems, $data);
        
        if (file_put_contents('problem.json', json_encode($problems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $redirect_to = '../../../core.php';
            echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反馈成功</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .box { text-align: center; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.1); max-width: 400px; width: 90%; }
        .icon { font-size: 60px; color: #10B981; margin-bottom: 20px; }
        h1 { color: #1F2937; font-size: 24px; margin-bottom: 10px; }
        p { color: #6B7280; margin-bottom: 20px; }
        .tip { font-size: 14px; color: #9CA3AF; }
        .tip a { color: #4F46E5; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">✓</div>
        <h1>反馈成功！</h1>
        <p>感谢您的宝贵意见，我们将尽快处理。</p>
        <div class="tip">页面将在 <span id="c">2</span> 秒后跳转...<br>未自动跳转？<a href="javascript:history.back()">返回</a></div>
    </div>
    <script>
        let n = 2, el = document.getElementById('c');
        let t = setInterval(() => { if (--n <= 0) { clearInterval(t); location.href = '$redirect_to'; } else el.textContent = n; }, 1000);
    </script>
</body>
</html>
HTML;
            exit;
        } else {
            $error = "数据保存失败，请联系管理员。";
        }
    }
}

$problems = [];
if (file_exists('problem.json')) {
    $json = file_get_contents('problem.json');
    $problems = json_decode($json, true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>页面问题反馈</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f0f2f5; min-height: 100vh; padding: 2rem 1rem; }
        .container { max-width: 768px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        h1 { text-align: center; font-size: 1.8rem; margin-bottom: .5rem; }
        h2 { font-size: 1.2rem; margin-bottom: 1rem; }
        p.lead { text-align: center; color: #666; margin-bottom: 2rem; }
        label { display: block; font-weight: 500; margin-bottom: .4rem; color: #333; }
        input, textarea { width: 100%; padding: .75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; margin-bottom: .25rem; }
        input:focus, textarea:focus { outline: none; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }
        textarea { min-height: 100px; resize: vertical; }
        .char-count { font-size: .8rem; color: #999; text-align: right; margin-bottom: 1rem; }
        button { width: 100%; padding: .75rem; background: #4F46E5; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #4338ca; }
        .alert { padding: 1rem; background: #fee2e2; color: #dc2626; border-radius: 8px; margin-bottom: 1rem; }
        .problem-item { border: 1px solid #eee; border-radius: 8px; padding: 1rem; margin-bottom: .75rem; }
        .problem-header { display: flex; justify-content: space-between; font-size: .85rem; color: #888; margin-bottom: .5rem; }
        .problem-id { font-weight: 600; color: #333; }
        .empty { text-align: center; color: #aaa; padding: 2rem; }
        footer { text-align: center; font-size: .8rem; color: #aaa; margin-top: 2rem; }
        @media (max-width: 600px) { .container { padding: 0; } .card { padding: 1.2rem; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>问题反馈</h1>
        <p class="lead">我们会在第一时间处理您的反馈<a href="" class="text-blue-600" onclick="event.preventDefault(); history.length &gt; 1 ? history.back() : window.location.href = '/'"> 返回</a></p>

        <div class="card">
            <?php if (isset($error)): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>用户ID *</label>
                <input type="text" name="id" pattern="\d{1,12}" required placeholder="1-12位数字ID" value="<?php echo htmlspecialchars($_POST['id'] ?? ''); ?>">

                <label>邮箱 *</label>
                <input type="email" name="email" required placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label>反馈内容 *</label>
                <textarea name="feedback" required maxlength="300" placeholder="请描述您遇到的问题（最多300字）"><?php echo htmlspecialchars($_POST['feedback'] ?? ''); ?></textarea>
                <div class="char-count"><span id="cnt">0</span>/300</div>

                <button type="submit">提交反馈</button>
            </form>
        </div>

        <div class="card">
            <h2>已提交的问题</h2>
            <?php if (empty($problems)): ?>
                <div class="empty">暂无反馈记录</div>
            <?php else: ?>
                <?php foreach ($problems as $item): ?>
                    <div class="problem-item">
                        <div class="problem-header">
                            <span class="problem-id">ID: <?php echo htmlspecialchars($item['id']); ?></span>
                            <span><?php echo $item['date']; ?></span>
                        </div>
                        <div><?php echo nl2br(htmlspecialchars($item['feedback'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer>&copy; <?php echo date('Y'); ?> 问题反馈系统</footer>
    </div>

    <script>
        const ta = document.querySelector('textarea'), cnt = document.getElementById('cnt');
        if (ta && cnt) {
            const update = () => cnt.textContent = ta.value.length;
            update(); ta.addEventListener('input', update);
        }
    </script>
</body>
</html>