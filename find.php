<?php
/**
 * 找回密码页面 - 液态玻璃效果版（纯色中性版）
 * 功能：输入ID和昵称，匹配后显示密码
 */
$message = '';
$foundPassword = '';
$debugInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $pname = isset($_POST['pname']) ? trim($_POST['pname']) : '';

    if (!preg_match('/^\d{10}$/', $id)) {
        $message = 'ID 必须为10位数字。';
    } elseif ($pname === '') {
        $message = '昵称不能为空。';
    } else {
        $jsonFile = __DIR__ . '/root.json';

        if (!file_exists($jsonFile)) {
            $message = "用户数据文件不存在，路径：" . htmlspecialchars($jsonFile);
        } elseif (!is_readable($jsonFile)) {
            $message = "用户数据文件不可读，请检查权限。";
        } else {
            $jsonContent = file_get_contents($jsonFile);
            if ($jsonContent === false) {
                $message = "读取文件失败。";
            } else {
                // 移除BOM头（兼容低版本PHP）
                $bom = pack('H*', 'EFBBBF');
                if (substr($jsonContent, 0, 3) === $bom) {
                    $jsonContent = substr($jsonContent, 3);
                    $debugInfo .= "检测到并移除了 BOM 头。";
                }

                $users = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = "JSON 解析失败：" . json_last_error_msg();
                } else {
                    $found = null;
                    foreach ($users as $user) {
                        if (isset($user['id'], $user['pname']) && $user['id'] === $id && $user['pname'] === $pname) {
                            $found = $user;
                            break;
                        }
                    }
                    if ($found) {
                        $foundPassword = $found['password'];
                    } else {
                        $message = "未找到匹配的用户，请检查ID和昵称是否完全一致（包括大小写和空格）。";
                        // 调试信息（可关闭）
                        $debugInfo .= "当前用户列表：";
                        foreach ($users as $u) {
                            $debugInfo .= " [ID:{$u['id']}, 昵称:{$u['pname']}]";
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码 · 液态玻璃</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(145deg, #1e3c3f 0%, #315d5f 40%, #5b8c8a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* 液态玻璃背景装饰 */
        body::before {
            content: '';
            position: absolute;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.4), rgba(200,230,240,0.1));
            border-radius: 50%;
            top: 10%;
            left: 5%;
            filter: blur(60px);
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle at 70% 80%, rgba(255,240,200,0.35), rgba(180,210,220,0.1));
            border-radius: 50%;
            bottom: 5%;
            right: 5%;
            filter: blur(70px);
            z-index: 0;
        }

        /* 主卡片 - 液态玻璃效果 */
        .glass-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 520px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(18px) saturate(180%);
            -webkit-backdrop-filter: blur(18px) saturate(180%);
            border-radius: 42px;
            padding: 40px 36px;
            box-shadow: 0 30px 50px rgba(0, 20, 20, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.2) inset,
                        0 0 30px rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 100px;
        }

        .glass-card:hover {
            box-shadow: 0 35px 60px rgba(0, 30, 30, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.35) inset;
            transform: translateY(-4px);
        }

        h1 {
            color: white;
            font-weight: 400;
            font-size: 2.4rem;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
            font-weight: 300;
        }

        .sub {
            text-align: center;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 30px;
            font-size: 1rem;
            font-weight: 300;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding-bottom: 18px;
        }

        .form-group {
            margin-bottom: 28px;
        }

        label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 400;
            letter-spacing: 0.3px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding-left: 6px;
        }

        .glass-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            border-radius: 34px;
            font-size: 1rem;
            color: white;
            outline: none;
            transition: all 0.25s ease;
            box-shadow: 0 6px 12px rgba(0, 20, 20, 0.2);
            backdrop-filter: blur(6px);
        }

        .glass-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-weight: 300;
            font-size: 0.95rem;
        }

        .glass-input:focus {
            border-color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 10px 22px rgba(0, 40, 40, 0.4), 0 0 0 3px rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }

        .glass-button {
            width: 100%;
            padding: 16px 22px;
            background: rgba(255, 255, 255, 0.22);
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-radius: 40px;
            color: white;
            font-size: 1.2rem;
            font-weight: 400;
            letter-spacing: 1px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.25s ease;
            box-shadow: 0 10px 20px rgba(0, 30, 30, 0.3);
            text-transform: uppercase;
        }

        .glass-button:hover {
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 30px rgba(0, 50, 50, 0.5);
            transform: translateY(-2px);
        }

        .glass-button:active {
            transform: translateY(2px);
            box-shadow: 0 5px 10px rgba(0, 20, 20, 0.4);
        }

        /* 按钮组：垂直排列，增加间距 */
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 8px;
        }

        /* 统一消息提示样式 - 纯玻璃效果，无彩色 */
        .message {
            margin-top: 28px;
            padding: 16px 20px;
            border-radius: 50px;
            font-weight: 400;
            background: rgba(0, 0, 0, 0.25);   /* 深色半透明，适配深色模式 */
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 18px rgba(0, 20, 20, 0.3);
            text-align: center;
            color: white;                      /* 白色文字，清晰可见 */
        }

        /* 调试信息额外样式（仍保持中性） */
        .debug {
            font-size: 0.9rem;
            word-break: break-word;
            background: rgba(0, 0, 0, 0.3);    /* 稍微深一点，但保持统一色调 */
        }

        .success strong {
            font-weight: 500;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 40px;
            margin-left: 6px;
        }

        /* 响应式 */
        @media (max-width: 480px) {
            .glass-card {
                padding: 30px 22px;
                border-radius: 32px;
            }
            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="glass-card">
        <h1>🔐 找回密码</h1>
        <div class="sub">输入QQ号·昵称验证</div>

        <form method="post">
            <div class="form-group">
                <label for="id">QQ号</label>
                <input type="text" id="id" name="id" class="glass-input" placeholder="例如 0000000001" value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="pname">🧑 昵称（忘记的话去搜索账号找到自己的昵称）</label>
                <input type="text" id="pname" name="pname" class="glass-input" placeholder="你的昵称" value="<?php echo isset($_POST['pname']) ? htmlspecialchars($_POST['pname']) : ''; ?>" required>
            </div>

            <!-- 按钮组：垂直排列两个按钮 -->
            <div class="button-group">
                <button type="submit" class="glass-button">验证并找回</button>
                <button type="button" class="glass-button" onclick="window.location.href='core.php'">返回登录</button>
            </div>
        </form>

        <?php if ($message !== ''): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($foundPassword !== ''): ?>
            <div class="message success">
                <span>🔑 你的密码是</span> <strong><?php echo htmlspecialchars($foundPassword); ?></strong>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>