// notification-core.js - 通知核心功能模块
class NotificationHandler {
    // 初始化：检测浏览器支持性
    constructor() {
        this.isSupported = 'Notification' in window;
        this.permission = this.isSupported ? Notification.permission : 'unsupported';
    }

    // 申请通知权限
    async requestPermission() {
        if (!this.isSupported) return Promise.reject('当前浏览器不支持通知功能');
        if (this.permission === 'granted') return Promise.resolve('已拥有通知权限');
        if (this.permission === 'denied') return Promise.reject('已拒绝通知权限，需手动在浏览器设置开启');

        // 申请权限并更新状态
        const result = await Notification.requestPermission();
        this.permission = result;
        return result === 'granted' 
            ? Promise.resolve('通知权限申请成功') 
            : Promise.reject(`权限申请失败：${result === 'denied' ? '用户拒绝' : '用户取消'}`);
    }

    // 发送自定义通知（核心方法）
    sendNotification(title, options = {}) {
        return new Promise((resolve, reject) => {
            // 前置校验
            if (!this.isSupported) reject('浏览器不支持通知功能');
            if (this.permission !== 'granted') reject('未获得通知权限，请先申请');

            // 默认配置（可被覆盖）
            const defaultOptions = {
                icon: 'https://cdn-icons-png.flaticon.com/128/7834/7834616.png', // 通用通知图标
                tag: 'function-trigger-notice', // 同tag通知覆盖，避免堆积
                renotify: true, // 强制触发通知提示
                requireInteraction: false // 无需用户手动关闭
            };

            // 合并配置
            const finalOptions = { ...defaultOptions, ...options };
            
            // 创建通知
            const notice = new Notification(title, finalOptions);
            
            // 通知成功回调
            notice.onshow = () => resolve({ status: 'success', notice });
            // 通知错误回调
            notice.onerror = (err) => reject(`通知发送失败：${err.message}`);
            // 通知点击回调
            notice.onclick = () => {
                resolve({ status: 'clicked', notice });
                notice.close();
            };
            // 自动关闭（默认5秒）
            setTimeout(() => notice.close(), finalOptions.autoClose || 5000);
        });
    }

    // 获取当前权限状态
    getPermissionStatus() {
        return this.isSupported ? this.permission : 'unsupported';
    }
}

// 暴露全局实例，方便页面直接使用
window.notificationCore = new NotificationHandler();