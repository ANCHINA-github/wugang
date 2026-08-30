<?php
// 如果接收到 POST 请求且包含 action 和 id，则当作 API 返回 JSON 数据
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_history' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $id = trim($_POST['id']);
    // 简单验证 ID 格式（纯数字）
    if (!preg_match('/^\d+$/', $id)) {
        echo json_encode(['status' => 'error', 'msg' => 'ID 格式无效']);
        exit;
    }
    $logFile = 'login-data.json';
    $records = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $allRecords = json_decode($content, true) ?? [];
        // 筛选该用户的记录
        $filtered = array_filter($allRecords, function($item) use ($id) {
            return isset($item['id']) && $item['id'] === $id;
        });
        // 按时间倒序（最新在前）
        usort($filtered, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        $records = array_values($filtered);
    }
    echo json_encode(['status' => 'success', 'data' => $records]);
    exit;
}

// 否则显示页面
$staticVer = time();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>我的登录记录</title>
    <link rel="stylesheet" href="/repository/main.css?v=<?php echo $staticVer; ?>">
    <link rel="stylesheet" href="/repository/awesome/css/all.min.css">
    <style>
        /* 页面独立样式 */
        body {
            padding: 16px;
        }
        .history-container {
            max-width: 800px;
            margin: 0 auto;
            margin-bottom: 85px; /* 底部留空，避免遮挡 */
        }
        .history-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .back-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            padding: 8px 12px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 16px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-btn:hover {
            background: var(--hover-bg);
        }
        .history-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--text-primary);
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .history-item {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 14px 16px;
            box-shadow: var(--glass-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-time {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .history-pname {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-tertiary);
            font-size: 16px;
        }
        .loading-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        /* 移动端适配 */
        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
            .history-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
                padding: 12px 14px;
            }
            .history-title {
                font-size: 18px;
            }
            .back-btn {
                font-size: 14px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="history-container">
        <div class="history-header">
            <button class="back-btn" onclick="history.back()">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
            <div class="history-title">我的登录记录</div>
        </div>
        <div id="historyList" class="history-list">
            <div class="loading-state">加载中...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 从 localStorage 获取用户信息
            const userInfo = JSON.parse(localStorage.getItem('userInfo'));
            if (!userInfo || !userInfo.id) {
                document.getElementById('historyList').innerHTML = 
                    '<div class="empty-state">请先登录查看记录</div>';
                return;
            }

            const listContainer = document.getElementById('historyList');
            listContainer.innerHTML = '<div class="loading-state">加载中...</div>';

            // 请求数据（请求当前页面自身，但带 POST 参数）
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_history&id=${encodeURIComponent(userInfo.id)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    const records = result.data || [];
                    if (records.length === 0) {
                        listContainer.innerHTML = '<div class="empty-state">暂无登录记录</div>';
                        return;
                    }
                    let html = '';
                    records.forEach(record => {
                        html += `
                            <div class="history-item">
                                <span class="history-pname">${escapeHtml(record.pname || '未知')}</span>
                                <span class="history-time">${escapeHtml(record.time || '')}</span>
                            </div>
                        `;
                    });
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = `<div class="empty-state">加载失败：${escapeHtml(result.msg || '未知错误')}</div>`;
                }
            })
            .catch(error => {
                listContainer.innerHTML = '<div class="empty-state">网络错误，请重试</div>';
                console.error('请求失败:', error);
            });
        });

        // 简单转义防止 XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>