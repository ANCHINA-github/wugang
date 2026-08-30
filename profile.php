<?php
// 设置页面编码
header('Content-Type: text/html; charset=utf-8');
// 获取当前文件名作为引用标识
$currentPage = basename(__FILE__, '.php');
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的 - 武冈</title>
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="repository/awesome/css/all.min.css">
    <style>
        :root {
            --bg-gradient-start: #FFFBD6;
            --bg-gradient-end: #FFFBD6;
            --text-primary: #333;
            --text-secondary: #666;
            --card-bg: rgba(255, 255, 255, 0.9);
            --border-color: rgba(255, 255, 255, 0.3);
            --shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        /* 深色模式 */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient-start: #121212;
                --bg-gradient-end: #121212;
                --text-primary: #e0e0e0;
                --text-secondary: #aaaaaa;
                --card-bg: rgba(30, 30, 45, 0.8);
                --border-color: rgba(255, 255, 255, 0.1);
                --shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-primary);
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* 液态玻璃卡片 */
        .profile-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 25px;
            margin: 20px auto;
            max-width: 400px;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .profile-card:active {
            transform: scale(0.98);
        }

        .user-header {
            background: url('poster2.webp');
            background-size: cover;
            background-position: center;
            border-radius: 20px 20px 0 0;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--border-color);
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--bg-gradient-start);
            margin: 0 auto 15px;
             padding: 4px; /* 边框厚度 */
    background: conic-gradient(from 0deg, #ff6b6b, #feca57, #48dbfb, #ff6b6b); /* 炫彩渐变 */
    display: inline-block;
        }

        .user-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

        .shimmer-text , .user-name {
            /* 1.1 设置一条超长的、包含多种鲜艳颜色的渐变带 */
            background: linear-gradient(90deg, #ff0080, #ff8c00, #40e0d0, #7f00ff, #ff0080);
            /* 1.2 把背景宽度拉长到 300%，以便有足够的空间进行滑动 */
            background-size: 300% 100%;
            
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            
            /* 1.3 调用动画，让背景无限循环流动 */
            animation: flowShimmer 4s linear infinite;
            letter-spacing: 5px;
        }

        @keyframes flowShimmer {
            0% {
                background-position: 0% 50%;
            }
            100% {
                background-position: 300% 50%; /* 滑过整条长背景 */
            }
        }

        .user-id {
            font-size: 14px;
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .user-meta {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 15px;
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .service-list {
            margin-top: 30px;
        }

        .service-item {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.3s;
        }

        .service-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .service-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--bg-gradient-start);
            display: flex;
            align-items: center;
            justify-content: center;
            color: goldenrod;
            font-size: 18px;
            margin-right: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .service-content {
            flex: 1;
        }

        .service-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .service-desc {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .logout-btn {
            width: 100%;
            padding: 15px;
            margin-bottom: 85px;
            background: #ff4757;
            color: #fff;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 20px;
        }

        .logout-btn:hover {
            background: #ff6b6b;
        }

        .loading {
            color: var(--text-secondary);
            text-align: center;
            padding: 40px;
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            .profile-card {
                padding: 20px;
            }
            .user-name {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <!-- 用户信息卡片 -->
        <div class="profile-card">
            <div class="user-header">
                <img id="avatar" src="default-avatar.jpg" alt="头像" class="user-avatar" loading="lazy" onerror="this.src='default-avatar.jpg'" draggable="false">
                <span id="username" class="user-name">游客</span>
                <div id="user-id" class="user-id">未登录</div>
            </div>
            <div class="user-meta">
                <div class="meta-item">
                    <span class="meta-label">系统ID:</span>
                    <span id="system_id">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">QQ号码:</span>
                    <span id="qq-number">请登录</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">性别:</span>
                    <span id="gender">未知</span>
                </div>
            </div>
        </div>

        <!-- 服务列表 -->
        <div class="service-list">
            <a href="login-data.php" class="service-item">
                <div class="service-icon"><i class="fa fa-calendar"></i></div>
                <div class="service-content">
                    <div class="service-title">账号登录记录</div>
                    <div class="service-desc">查看你的登录历史</div>
                </div>
            </a>

            <a href="posts_history.php" class="service-item">
                <div class="service-icon"><i class="fa fa-history"></i></div>
                <div class="service-content">
                    <div class="service-title">历史动态</div>
                    <div class="service-desc">查看你的历史动态</div>
                </div>
            </a>

            <a href="service/problem/problem.php" class="service-item">
                <div class="service-icon"><i class="fas fa-question-circle"></i></div>
                <div class="service-content">
                    <div class="service-title">反馈问题</div>
                    <div class="service-desc">向开发者报告问题</div>
                </div>
            </a>

            <a href="find.php" class="service-item">
                <div class="service-icon"><i class="fas fa-lock"></i></div>
                <div class="service-content">
                    <div class="service-title">忘记密码</div>
                    <div class="service-desc">找回你的账户密码</div>
                </div>
            </a>

            <a href="javascript:void(0);" onclick="applyForDeletion()" class="service-item">
                <div class="service-icon"><i class="fas fa-trash-alt"></i></div>
                <div class="service-content">
                    <div class="service-title">申请注销</div>
                    <div class="service-desc">申请注销你的账户（不可恢复）</div>
                </div>
            </a>

            <a href="change_password.php" class="service-item">
                <div class="service-icon"><i class="fas fa-key"></i></div>
                <div class="service-content">
                    <div class="service-title">修改密码</div>
                    <div class="service-desc">更改你的账号密码</div>
                </div>
            </a>

            <a href="wenjuan.php" class="service-item">
                <div class="service-icon"><i class="fas fa-crown"></i></div>
                <div class="service-content">
                    <div class="service-title">成为管理员</div>
                    <div class="service-desc">申请成为管理员</div>
                </div>
            </a>



        </div>

        <!-- 退出登录按钮 -->
        <button onclick="logout()" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> 退出登录
        </button>
    </div>

    <script>
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            loadUserProfile();
        });

        // 加载用户信息
        function loadUserProfile() {
            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            const userAvatar = document.getElementById('avatar');
            const username = document.getElementById('username');
            const userId = document.getElementById('user-id');
            const systemId = document.getElementById('system_id');
            const qqNumber = document.getElementById('qq-number');
            const gender = document.getElementById('gender');

            if (userInfo) {
                // 更新页面元素
                userAvatar.src = userInfo.portrait || 'default-avatar.jpg';
                username.textContent = userInfo.pname || '匿名用户';
                userId.textContent = `ID: ${userInfo.id}`;
                systemId.textContent = userInfo.system_id ? `SYS-${userInfo.system_id}` : '未分配';
                qqNumber.textContent = userInfo.id;
                gender.textContent = userInfo.gender === '保密' ? '保密' : (userInfo.gender === '男' ? '男' : '女');
            } else {
                // 未登录状态
                username.textContent = '游客';
                userId.textContent = '未登录';
                qqNumber.textContent = '请先登录';
                // 如果没有登录，点击卡片跳转到登录页
                document.querySelector('.profile-card').onclick = function() {
                    window.location.href = 'core.php'; // 假设core.php是你的主页或登录入口
                }
            }
        }

        // 退出登录
        function logout() {
            if (confirm('确定要退出登录吗？')) {
                // 清除本地存储
                localStorage.removeItem('userInfo');
                localStorage.removeItem('lastLoginId');
                localStorage.removeItem('likedPosts');
                localStorage.removeItem('likedComments');
                
                // 提示并刷新
                alert('已退出登录');
                window.location.href = 'core.php'; // 返回主页或登录页
            }
        }

        // 申请注销（示例，实际需连接后端）
        function applyForDeletion() {
            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            if (!userInfo) {
                alert('请先登录');
                return;
            }
           if (confirm(`确定要注销账户 [${userInfo.pname}] 吗？此操作不可恢复！`)) {
            window.location.href = 'cancel.php?uid=' + userInfo.id;
            }
        }
    </script>
</body>
</html>