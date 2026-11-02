<?php
require 'config.php';
$is_login = !empty($_SESSION['user_id']);
$my_id = $is_login ? $_SESSION['user_id'] : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>私人聊天</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: sans-serif; }
        body { background: #f0f2f5; height: 100vh; display: flex; flex-direction: column; }
        .login-box { width: 90%; max-width: 350px; margin: 20vh auto; background: #fff; padding: 30px; border-radius: 10px; text-align: center; }
        .login-box input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
        .login-box button { width: 100%; padding: 12px; background: #07c160; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        #chat-container { display: none; flex-direction: column; height: 100%; }
        .header { height: 50px; background: #ededed; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; }
        .chat-list { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 15px; }
        .msg-row { display: flex; gap: 10px; max-width: 80%; }
        .msg-row.me { align-self: flex-end; flex-direction: row-reverse; }
        .avatar { width: 40px; height: 40px; background: #ccc; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
        .msg-row.me .avatar { background: #07c160; }
        .bubble { padding: 10px 14px; border-radius: 6px; background: #fff; word-break: break-all; }
        .msg-row.me .bubble { background: #95ec69; }
        .msg-status { font-size: 12px; color: #999; text-align: right; margin-top: 5px; }
        .input-area { padding: 10px; background: #f7f7f7; display: flex; gap: 10px; }
        .input-area textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; resize: none; height: 40px; }
        .input-area button { padding: 0 20px; background: #07c160; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<?php if (!$is_login): ?>
    <div class="login-box">
        <h2>欢迎回来</h2>
        <input type="password" id="pwd" placeholder="请输入密码">
        <button onclick="doLogin()">进入聊天</button>
    </div>
<?php else: ?>
    <div id="chat-container" style="display:flex;">
        <div class="header">
            <span>私人聊天室</span>
            <a href="javascript:;" onclick="doLogout()">退出</a>
        </div>
        <div class="chat-list" id="chat-list"></div>
        <div class="input-area">
            <textarea id="msg-input" placeholder="输入消息..."></textarea>
            <button onclick="sendMsg()">发送</button>
        </div>
    </div>
<?php endif; ?>

<script>
    async function apiFetch(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            alert("服务器发生错误，返回了非JSON数据：\n\n" + text);
            throw new Error("JSON Error");
        }
    }

    function doLogin() {
        let pwd = document.getElementById('pwd').value;
        apiFetch('api.php?action=login', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `password=${encodeURIComponent(pwd)}`
        }).then(res => {
            if (res.code === 200) location.reload();
            else alert(res.msg);
        }).catch(()=>{});
    }

    function doLogout() {
        apiFetch('api.php?action=logout').then(() => location.reload());
    }

    <?php if ($is_login): ?>
    const MY_ID = <?= $my_id ?>;
    let lastMsgId = 0;
    let renderedMsgIds = new Set();

    function renderMsg(msg) {
        // 【核心修复】不管是否已经渲染过，都先更新 lastMsgId，防止拉取死循环
        if (msg.id > lastMsgId) {
            lastMsgId = msg.id;
        }

        if (renderedMsgIds.has(msg.id)) return;
        renderedMsgIds.add(msg.id);
        
        const list = document.getElementById('chat-list');
        const isMe = msg.sender_id == MY_ID;
        const row = document.createElement('div');
        row.className = `msg-row ${isMe ? 'me' : 'other'}`;
        row.id = `msg-${msg.id}`;
        
        let statusHtml = isMe ? `<div class="msg-status" id="status-${msg.id}">${msg.is_read == 1 ? '已读' : '未读'}</div>` : '';

        row.innerHTML = `
            <div class="avatar">${isMe ? '我' : '对'}</div>
            <div>
                <div class="bubble">${msg.content}</div>
                ${statusHtml}
            </div>
        `;
        list.appendChild(row);
        list.scrollTop = list.scrollHeight;
    }

    function fetchMessages() {
        if (document.visibilityState === 'hidden') return;
        
        apiFetch(`api.php?action=fetch&since_id=${lastMsgId}`)
            .then(res => {
                if (res.code === 200) {
                    res.data.new_messages.forEach(renderMsg);
                    
                    if (res.data.read_updates) {
                        res.data.read_updates.forEach(id => {
                            let st = document.getElementById(`status-${id}`);
                            if (st && st.textContent !== '已读') st.textContent = '已读';
                        });
                    }

                    if (res.data.new_messages.length > 0) {
                        let hasOtherMsg = res.data.new_messages.some(m => m.sender_id != MY_ID);
                        if (hasOtherMsg) markRead();
                    }
                } else if (res.code === 401) {
                    location.reload();
                } else {
                    console.error("拉取失败: ", res.msg);
                }
            }).catch(()=>{})
            .finally(() => {
                setTimeout(fetchMessages, 2000);
            });
    }

    function markRead() {
        if (!lastMsgId) return;
        apiFetch('api.php?action=read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `last_id=${lastMsgId}`
        }).catch(()=>{});
    }

    function sendMsg() {
        const input = document.getElementById('msg-input');
        const val = input.value.trim();
        if (!val) return;
        
        apiFetch('api.php?action=send', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `content=${encodeURIComponent(val)}`
        }).then(res => {
            if (res.code === 200) {
                input.value = ''; // 成功才清空
                
                // 【核心修复】绝不直接渲染，而是立刻强制拉取数据库的真实数据
                // 这样只要能拉出来，就证明数据库存进去了
                fetchMessages(); 
            } else {
                alert("发送失败：" + res.msg);
            }
        }).catch((e) => {
            alert("发送发生异常，请按F12查看");
        });
    }

    document.getElementById('msg-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
    });

    fetchMessages();
    <?php endif; ?>
</script>
</body>
</html>