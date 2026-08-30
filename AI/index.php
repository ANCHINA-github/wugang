<?php
// index.php - 智能数据助手

// 文件路径配置
define('DATA_FILE', 'data.json');
define('CHAT_FILE', 'chat-data.json');

// 初始化数据文件（空数组）
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
if (!file_exists(CHAT_FILE)) {
    file_put_contents(CHAT_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 处理获取历史记录的请求
if (isset($_GET['action']) && $_GET['action'] === 'getHistory') {
    header('Content-Type: application/json; charset=utf-8');
    $history = json_decode(file_get_contents(CHAT_FILE), true);
    echo json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 处理消息发送请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');
    
    if ($message === '') {
        echo json_encode(['reply' => '请输入有效内容']);
        exit;
    }
    
    // 生成助手回复
    $reply = processMessage($message);
    
    // 保存对话历史（一天一组）
    saveChatHistory($message, $reply);
    
    echo json_encode(['reply' => $reply]);
    exit;
}

/**
 * 处理用户消息，返回助手回复
 */
function processMessage($message) {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    if (!is_array($data)) $data = [];
    
    // 情况1：调用关键词
    if (mb_strpos($message, '调用') === 0) {
        $keyword = trim(mb_substr($message, mb_strlen('调用')));
        if ($keyword === '') {
            return '请输入规范的查询文本';
        }
        foreach ($data as $item) {
            if ($item['keyword'] === $keyword) {
                return $item['text'];
            }
        }
        return '未找到相应关键词，请检查查询文本';
    }
    
    // 情况2：更改关键词为内容
    if (mb_strpos($message, '更改') === 0) {
        // 查找“为”的位置
        $weiPos = mb_strpos($message, '为');
        if ($weiPos === false) {
            return '请输入规范的查询文本';
        }
        // 提取关键词部分（去掉“更改”前缀，截取到“为”之前）
        $keywordPart = mb_substr($message, mb_strlen('更改'), $weiPos - mb_strlen('更改'));
        $keyword = trim($keywordPart);
        $newText = trim(mb_substr($message, $weiPos + mb_strlen('为')));
        
        if ($keyword === '' || $newText === '') {
            return '请输入规范的查询文本';
        }
        
        $found = false;
        foreach ($data as &$item) {
            if ($item['keyword'] === $keyword) {
                $item['text'] = $newText;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return '未找到相应关键词，请检查查询文本';
        }
        // 写回文件
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return "已更新：关键词「{$keyword}」的内容已修改为「{$newText}」";
    }
    
    // 情况3：添加关键词 : 文本（支持中英文冒号）
    if (mb_strpos($message, '添加关键词') === 0) {
        // 去掉前缀
        $content = trim(mb_substr($message, mb_strlen('添加关键词')));
        // 查找冒号位置（中文冒号“：”或英文冒号“:”）
        $colonPos = mb_strpos($content, '：'); // 中文冒号
        if ($colonPos === false) {
            $colonPos = mb_strpos($content, ':'); // 英文冒号
        }
        if ($colonPos === false) {
            return '请输入规范的添加格式：添加关键词 关键词:文本（支持中英文冒号）';
        }
        
        $keyword = trim(mb_substr($content, 0, $colonPos));
        $text = trim(mb_substr($content, $colonPos + 1));
        
        if ($keyword === '' || $text === '') {
            return '关键词和文本不能为空，请重新输入';
        }
        
        // 检查关键词是否已存在
        foreach ($data as $item) {
            if ($item['keyword'] === $keyword) {
                return "关键词「{$keyword}」已存在，请使用「更改」操作进行修改";
            }
        }
        
        // 添加新条目
        $data[] = [
            'keyword' => $keyword,
            'text' => $text
        ];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return "已添加：关键词「{$keyword}」→「{$text}」";
    }
    
    // 不符合任何规范
    return '请输入规范的查询文本（调用/更改/添加关键词）';
}

/**
 * 保存对话历史到 chat-data.json（一天一组）
 */
function saveChatHistory($question, $answer) {
    $history = json_decode(file_get_contents(CHAT_FILE), true);
    if (!is_array($history)) $history = [];
    
    $today = date('Y-m-d');
    $found = false;
    foreach ($history as &$day) {
        if ($day['date'] === $today) {
            $day['conversations'][] = [
                'question' => $question,
                'answer'   => $answer
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $history[] = [
            'date' => $today,
            'conversations' => [
                ['question' => $question, 'answer' => $answer]
            ]
        ];
    }
    
    file_put_contents(CHAT_FILE, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 以下为前端界面（HTML/CSS/JS）
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>智能数据助手</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 全局样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Icons", "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        /* 浅色模式变量 */
        :root {
            --bg-color: #f5f5f7;
            --text-color: #1d1d1f;
            --suggestion-bg: #ffffff;
            --suggestion-text: #1d1d1f;
            --suggestion-hover: #f0f0f2;
            --input-bg: #ffffff;
            --input-border: #e6e6e6;
            --input-border-hover: #d1d1d6;
            --input-border-focus: #0071e3;
            --input-text: #1d1d1f;
            --placeholder-text: #86868b;
            --icon-color: #86868b;
            --btn-bg: #0071e3;
            --btn-hover: #0077ed;
            --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.1);
            --user-message-bg: #0071e3;
            --user-message-text: #ffffff;
            --bot-message-bg: #e5e5e7;
            --bot-message-text: #1d1d1f;
            --message-radius: 18px;
            --message-padding: 12px 18px;
            --modal-bg: rgba(0,0,0,0.5);
            --modal-content-bg: #ffffff;
            --modal-text: #1d1d1f;
            --modal-border: #e6e6e6;
        }

        /* 深色模式变量 */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #1c1c1e;
                --text-color: #ffffff;
                --suggestion-bg: #2c2c2e;
                --suggestion-text: #ffffff;
                --suggestion-hover: #3a3a3c;
                --input-bg: #2c2c2e;
                --input-border: #3a3a3c;
                --input-border-hover: #444446;
                --input-border-focus: #0071e3;
                --input-text: #ffffff;
                --placeholder-text: #a1a1aa;
                --icon-color: #a1a1aa;
                --btn-bg: #0071e3;
                --btn-hover: #0077ed;
                --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.2);
                --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.15);
                --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.25);
                --user-message-bg: #0071e3;
                --user-message-text: #ffffff;
                --bot-message-bg: #3a3a3c;
                --bot-message-text: #ffffff;
                --modal-bg: rgba(0,0,0,0.7);
                --modal-content-bg: #2c2c2e;
                --modal-text: #ffffff;
                --modal-border: #3a3a3c;
            }
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 20px 100px;
            transition: background-color 0.3s ease;
        }

        .title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
            letter-spacing: -0.5px;
            transition: all 0.3s ease;
        }

        .chat-container {
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding-bottom: 10px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .message.user {
            align-self: flex-end;
            background-color: var(--user-message-bg);
            color: var(--user-message-text);
            border-radius: var(--message-radius);
            border-bottom-right-radius: 4px;
            padding: var(--message-padding);
            max-width: 80%;
            font-size: 15px;
            box-shadow: var(--shadow-light);
            animation: fadeIn 0.3s ease;
        }

        .message.bot {
            align-self: flex-start;
            background-color: var(--bot-message-bg);
            color: var(--bot-message-text);
            border-radius: var(--message-radius);
            border-bottom-left-radius: 4px;
            padding: var(--message-padding);
            max-width: 80%;
            font-size: 15px;
            box-shadow: var(--shadow-light);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .suggestions-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            max-width: 900px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .suggestion-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .suggestion-item {
            background-color: var(--suggestion-bg);
            color: var(--suggestion-text);
            padding: 10px 18px;
            border-radius: 18px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-light);
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .suggestion-item:hover {
            background-color: var(--suggestion-hover);
            transform: translateY(-1px);
        }

        .input-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 800px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 24px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-medium);
            transition: all 0.25s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            z-index: 10;
        }

        .input-container:hover {
            border-color: var(--input-border-hover);
            box-shadow: var(--shadow-hover);
            transform: translateX(-50%) translateY(-1px);
        }

        .input-container:focus-within {
            border-color: var(--input-border-focus);
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
            outline: none;
        }

        .input-field {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            color: var(--input-text);
            font-size: 15px;
            padding: 4px 0;
            transition: color 0.2s ease;
        }

        .input-field::placeholder {
            color: var(--placeholder-text);
            font-size: 14px;
        }

        .input-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--btn-bg);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
        }

        .icon-button:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }

        .send-button {
            opacity: 0.5;
            pointer-events: none;
        }

        .input-field:not(:placeholder-shown) + .input-actions .send-button {
            opacity: 1;
            pointer-events: all;
        }

        .hidden {
            opacity: 0;
            height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
            pointer-events: none;
        }

        /* 历史记录模态框样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--modal-bg);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--modal-content-bg);
            color: var(--modal-text);
            width: 90%;
            max-width: 800px;
            max-height: 80%;
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            overflow: auto;
            padding: 20px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid var(--modal-border);
            padding-bottom: 12px;
        }

        .close-modal {
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
        }

        .history-day {
            margin-bottom: 24px;
        }

        .history-date {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            color: var(--input-border-focus);
        }

        .history-item {
            background-color: var(--suggestion-bg);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            border-left: 3px solid var(--input-border-focus);
        }

        .history-question {
            font-weight: 500;
            margin-bottom: 8px;
        }

        .history-answer {
            color: var(--placeholder-text);
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .suggestion-item {
                white-space: normal;
                word-break: keep-all;
            }
            .message.user, .message.bot {
                max-width: 90%;
            }
            .input-container {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <h1 class="title" id="mainTitle">有什么我能帮你的吗?</h1>

    <div class="chat-container" id="chatContainer"></div>

    <div class="suggestions-container" id="suggestionsContainer">
        <div class="suggestion-row">
            <div class="suggestion-item">调用 今日天气</div>
            <div class="suggestion-item">更改 今日天气 为 晴朗，气温22℃</div>
        </div>
        <div class="suggestion-row">
            <div class="suggestion-item">添加关键词 公司电话 : 010-12345678</div>
            <div class="suggestion-item">添加关键词 项目截止日 ： 2025年12月31日</div>
        </div>
        <div class="suggestion-row">
            <div class="suggestion-item">调用 公司电话</div>
            <div class="suggestion-item">调用 项目截止日</div>
        </div>
    </div>

    <div class="input-container">
        <input type="text" class="input-field" id="messageInput" placeholder="发消息...">
        <div class="input-actions">
            <button class="icon-button" id="historyButton"><i class="fas fa-clock"></i></button>
            <button class="icon-button send-button" id="sendButton"><i class="fas fa-paper-plane"></i></button>
            <button class="icon-button" id="voiceButton"><i class="fas fa-microphone"></i></button>
        </div>
    </div>

    <!-- 历史记录模态框 -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>历史对话记录</span>
                <span class="close-modal">&times;</span>
            </div>
            <div id="historyContent"></div>
        </div>
    </div>

    <script>
        // DOM 元素
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const chatContainer = document.getElementById('chatContainer');
        const suggestionsContainer = document.getElementById('suggestionsContainer');
        const mainTitle = document.getElementById('mainTitle');
        const historyButton = document.getElementById('historyButton');
        const historyModal = document.getElementById('historyModal');
        const historyContent = document.getElementById('historyContent');
        const closeModal = document.querySelector('.close-modal');

        // 显示消息到界面
        function appendMessage(text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${type}`;
            msgDiv.textContent = text;
            chatContainer.appendChild(msgDiv);
            chatContainer.scrollTo({ top: chatContainer.scrollHeight, behavior: 'smooth' });
        }

        // 发送消息到后端
        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;

            // 隐藏标题和推荐问题
            mainTitle.classList.add('hidden');
            suggestionsContainer.classList.add('hidden');

            // 显示用户消息
            appendMessage(message, 'user');
            messageInput.value = '';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message })
                });
                const data = await response.json();
                const reply = data.reply || '抱歉，出错了。';
                appendMessage(reply, 'bot');
            } catch (err) {
                appendMessage('网络错误，请重试', 'bot');
            }
        }

        // 获取并显示历史记录
        async function showHistory() {
            try {
                const response = await fetch(window.location.href + '?action=getHistory');
                const history = await response.json();
                if (!Array.isArray(history) || history.length === 0) {
                    historyContent.innerHTML = '<p>暂无历史对话</p>';
                } else {
                    let html = '';
                    // 按日期倒序显示（最新的在上）
                    history.slice().reverse().forEach(day => {
                        html += `<div class="history-day">
                                    <div class="history-date">📅 ${day.date}</div>`;
                        day.conversations.forEach(conv => {
                            html += `<div class="history-item">
                                        <div class="history-question">👤 ${escapeHtml(conv.question)}</div>
                                        <div class="history-answer">🤖 ${escapeHtml(conv.answer)}</div>
                                     </div>`;
                        });
                        html += `</div>`;
                    });
                    historyContent.innerHTML = html;
                }
                historyModal.style.display = 'flex';
            } catch (err) {
                alert('加载历史失败');
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
                return c;
            });
        }

        // 事件绑定
        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
        historyButton.addEventListener('click', showHistory);
        closeModal.addEventListener('click', () => { historyModal.style.display = 'none'; });
        window.addEventListener('click', (e) => { if (e.target === historyModal) historyModal.style.display = 'none'; });

        // 推荐问题点击
        document.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                messageInput.value = item.textContent;
                sendMessage();
            });
        });

        // 语音按钮仅为装饰（演示用）
        document.getElementById('voiceButton').addEventListener('click', () => {
            alert('语音输入功能待集成，当前仅做界面展示');
        });
    </script>
</body>
</html>