 (function() {
        // ===== 配置区域 =====
        const adConfig = {
            imageUrl: 'http://an.kijk.top/wenjuan.png',
            linkUrl: 'http://an.kijk.top/wenjuan.php',
        };
        // =====================

        // ----- 检查今日是否已显示过 -----
        function shouldShowToday() {
            const today = new Date().toDateString();
            const lastShown = localStorage.getItem('adPopupDate');
            return lastShown !== today;
        }

        // ----- 记录今日已显示 -----
        function markShownToday() {
            localStorage.setItem('adPopupDate', new Date().toDateString());
        }

        // ----- 关闭弹窗 -----
        function closePopup() {
            const overlay = document.getElementById('adPopupOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        // ----- 创建弹窗 DOM -----
        function createPopup() {
            const overlay = document.createElement('div');
            overlay.className = 'ad-popup-overlay';
            overlay.id = 'adPopupOverlay';

            const content = document.createElement('div');
            content.className = 'ad-popup-content';

            // 广告图片（点击跳转 + 关闭弹窗）
            const imgLink = document.createElement('a');
            imgLink.href = adConfig.linkUrl;
            imgLink.target = '_blank';
            imgLink.rel = 'noopener noreferrer';

            const img = document.createElement('img');
            img.className = 'ad-popup-image';
            img.src = adConfig.imageUrl;
            img.alt = '广告';
            // 点击广告 → 跳转并关闭弹窗
            imgLink.addEventListener('click', function(e) {
                // 允许跳转，同时关闭弹窗
                closePopup();
            });

            imgLink.appendChild(img);

            // 关闭按钮（正下方）
            const closeBtn = document.createElement('button');
            closeBtn.className = 'ad-popup-close';
            closeBtn.textContent = '✕ 关闭';
            closeBtn.setAttribute('aria-label', '关闭');
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                closePopup();
            });

            content.appendChild(imgLink);
            content.appendChild(closeBtn);
            overlay.appendChild(content);

            // 注意：点击遮罩层【不】关闭弹窗（已按需求移除）
            // 只有关闭按钮 或 点击广告图片 才能关闭

            return overlay;
        }

        // ----- 显示弹窗（每日仅一次） -----
        function showPopup() {
            if (!shouldShowToday()) return;

            const existing = document.getElementById('adPopupOverlay');
            if (existing) existing.remove();

            const popup = createPopup();
            document.body.appendChild(popup);
            popup.style.display = 'flex';

            markShownToday();
        }

        // ----- DOM 就绪后执行 -----
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showPopup);
        } else {
            showPopup();
        }
    })();