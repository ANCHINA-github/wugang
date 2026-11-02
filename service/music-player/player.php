<?php
/**
 * 苹果风格 PHP 音乐播放器（App 全屏布局 · 单文件）
 * 自动扫描 ./music 文件夹中的 MP3 文件并生成播放列表
 * 歌曲名直接读取原始文件名，不做美化/智能识别
 * 背景渐变 + 深色模式完美适配
 */
// ==================== 配置 ====================
$musicDir = 'music';                // 音乐文件夹（相对于本文件）
$title    = '音乐播放器 · 苹果风格';  // 页面标题

// ==================== 扫描 MP3 ====================
$mp3Files = [];
$error    = '';

if (!is_dir($musicDir)) {
    if (!mkdir($musicDir, 0755, true)) {
        $error = "目录 '{$musicDir}' 不存在且无法自动创建，请手动创建并放入 MP3 文件。";
    } else {
        $error = "目录 '{$musicDir}' 已自动创建，请上传 MP3 文件。";
    }
} else {
    $files = scandir($musicDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'mp3') {
            $mp3Files[] = $file;
        }
    }
    natsort($mp3Files);
    $mp3Files = array_values($mp3Files);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#FFBCBD">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== 全局变量 & 深色模式 ========== */
        :root {
            --bg-gradient-start: #FFBCBD;
            --bg-gradient-end: #8BEEDE;
            --app-bg: rgba(255, 255, 255, 0.86);
            --app-border: rgba(255, 255, 255, 0.6);
            --text-primary: #1d1c1f;
            --text-secondary: #8e8e93;
            --text-active: #007aff;
            --btn-bg: rgba(240, 240, 245, 0.75);
            --play-bg: #1d1c1f;
            --play-color: #ffffff;
            --list-active-bg: rgba(0, 122, 255, 0.1);
            --list-hover-bg: rgba(120, 120, 128, 0.1);
            --scrollbar-thumb: #c6c6c8;
            --slider-track: #e0e0e0;
            --sheet-bg: rgba(255, 255, 255, 0.96);
            --artwork-gradient: linear-gradient(135deg, #FFBCBD, #8BEEDE);
            --header-border: rgba(0, 0, 0, 0.06);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient-start: #1e2a2e;
                --bg-gradient-end: #0d1117;
                --app-bg: rgba(22, 22, 24, 0.9);
                --app-border: rgba(255, 255, 255, 0.1);
                --text-primary: #f5f5f7;
                --text-secondary: #8e8e93;
                --text-active: #0a84ff;
                --btn-bg: rgba(80, 80, 90, 0.6);
                --play-bg: #ffffff;
                --play-color: #1d1c1f;
                --list-active-bg: rgba(10, 132, 255, 0.16);
                --list-hover-bg: rgba(120, 120, 128, 0.16);
                --scrollbar-thumb: #3a3a3c;
                --slider-track: #3a3a3c;
                --sheet-bg: rgba(28, 28, 30, 0.97);
                --artwork-gradient: linear-gradient(135deg, #2b3a40, #12181d);
                --header-border: rgba(255, 255, 255, 0.08);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(145deg, var(--bg-gradient-start), var(--bg-gradient-end));
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
            overflow: hidden;
        }

        /* ========== App 容器 ========== */
        .app {
            position: relative;
            width: 100%;
            max-width: 430px;
            height: 100vh;
            height: 100dvh;
            background: var(--app-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: background 0.3s ease;
        }

        /* 桌面端：收成手机尺寸，带圆角阴影 */
        @media (min-width: 500px) {
            body {
                padding: 24px;
            }
            .app {
                height: min(880px, calc(100dvh - 48px));
                border-radius: 44px;
                border: 1px solid var(--app-border);
                box-shadow: 0 30px 60px rgba(0, 0, 0, 0.22), 0 6px 18px rgba(0, 0, 0, 0.1);
            }
        }

        /* ========== 顶部栏 ========== */
        .app-header {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px 6px;
            padding-top: max(14px, env(safe-area-inset-top));
        }

        .header-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: transparent;
            border-radius: 50%;
            color: var(--text-primary);
            font-size: 17px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
        }

        .icon-btn:hover {
            background: var(--btn-bg);
        }

        .icon-btn:active {
            transform: scale(0.92);
        }

        .icon-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        /* ========== 正在播放区 ========== */
        .now-playing {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 28px 24px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .artwork {
            width: min(100%, 300px);
            aspect-ratio: 1 / 1;
            flex-shrink: 0;
            border-radius: 28px;
            background: var(--artwork-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: rgba(255, 255, 255, 0.85);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18), 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 26px;
        }

        .track-info {
            width: 100%;
            text-align: center;
        }

        /* 歌名完整显示 + 强制换行 */
        .track-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            line-height: 1.3;
            white-space: normal;
            word-break: break-all;
            overflow-wrap: break-word;
        }

        .track-artist {
            margin-top: 6px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* 小屏适配 */
        @media (max-height: 760px) {
            .artwork {
                width: min(100%, 200px);
                margin-bottom: 18px;
            }
            .track-title {
                font-size: 20px;
            }
        }

        /* ========== 进度条 ========== */
        .progress-area {
            width: 100%;
            margin-top: 26px;
        }

        .progress-time {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            font-variant-numeric: tabular-nums;
            margin-bottom: 6px;
        }

        input[type=range] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            background: transparent;
            cursor: pointer;
        }

        input[type=range]::-webkit-slider-runnable-track {
            height: 5px;
            background: var(--slider-track);
            border-radius: 10px;
            border: none;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            margin-top: -5.5px;
            border: 0.5px solid rgba(0, 0, 0, 0.04);
        }

        input[type=range]::-moz-range-track {
            height: 5px;
            background: var(--slider-track);
            border-radius: 10px;
        }

        input[type=range]::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border: none;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        /* ========== 控制按钮 ========== */
        .controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
        }

        .control-btn {
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 26px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
        }

        .control-btn:hover {
            background: var(--btn-bg);
        }

        .control-btn:active {
            transform: scale(0.9);
        }

        .play-pause {
            width: 74px;
            height: 74px;
            font-size: 28px;
            background: var(--play-bg);
            color: var(--play-color);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.24);
        }

        .play-pause:hover {
            background: var(--play-bg);
            transform: scale(1.04);
        }

        .play-pause:active {
            transform: scale(0.96);
        }

        .control-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ========== 音量 ========== */
        .volume-control {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            max-width: 300px;
            margin-top: 22px;
        }

        .volume-icon {
            font-size: 15px;
            color: var(--text-secondary);
            cursor: pointer;
            width: 22px;
            text-align: center;
        }

        .volume-control input[type=range] {
            flex: 1;
        }

        /* ========== 遮罩 & 底部播放列表抽屉 ========== */
        .sheet-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 10;
        }

        .sheet-backdrop.open {
            opacity: 1;
            pointer-events: auto;
        }

        .playlist-sheet {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 20;
            max-height: 72%;
            display: flex;
            flex-direction: column;
            background: var(--sheet-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 24px 24px 0 0;
            box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(102%);
            transition: transform 0.34s cubic-bezier(0.32, 0.72, 0, 1);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }

        .playlist-sheet.open {
            transform: translateY(0);
        }

        .sheet-handle {
            width: 38px;
            height: 5px;
            border-radius: 3px;
            background: var(--scrollbar-thumb);
            opacity: 0.6;
            margin: 10px auto 2px;
            flex-shrink: 0;
        }

        .sheet-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 24px 12px;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 1px solid var(--header-border);
            flex-shrink: 0;
        }

        .sheet-count {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-secondary);
        }

        /* ========== 播放列表 ========== */
        .playlist {
            list-style: none;
            overflow-y: auto;
            padding: 8px 10px 20px;
            margin: 0;
            flex: 1;
            min-height: 0;
        }

        .playlist::-webkit-scrollbar {
            width: 4px;
        }

        .playlist::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 10px;
        }

        .playlist li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 15px;
            transition: background 0.15s ease;
        }

        .playlist li:hover {
            background: var(--list-hover-bg);
        }

        .playlist li.active {
            background: var(--list-active-bg);
            color: var(--text-active);
            font-weight: 500;
        }

        .playlist li i {
            width: 20px;
            font-size: 15px;
            text-align: center;
            color: var(--text-secondary);
            opacity: 0.7;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .playlist li.active i {
            color: var(--text-active);
            opacity: 1;
        }

        /* 歌名完整显示 */
        .playlist .track-name {
            flex: 1;
            white-space: normal;
            word-break: break-all;
            overflow-wrap: break-word;
            line-height: 1.4;
        }

        /* ========== 空状态 ========== */
        .empty-message {
            text-align: center;
            padding: 50px 24px 60px;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        .empty-message i {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
            opacity: 0.7;
        }
    </style>
</head>
<body>
<div class="app">

    <!-- 顶部栏 -->
    <header class="app-header">
        <button class="icon-btn" id="playAllBtn" title="播放全部"><i class="fas fa-play"></i></button>
        <span class="header-title">正在播放</span>
        <button class="icon-btn" id="playlistToggleBtn" title="播放列表"><i class="fas fa-list-ul"></i></button>
    </header>

    <!-- 正在播放区 -->
    <main class="now-playing">
        <div class="artwork"><i class="fas fa-music"></i></div>

        <div class="track-info">
            <h1 class="track-title" id="currentTrackName">选择一首歌曲</h1>
            <p class="track-artist">本地音乐 · MP3</p>
        </div>

        <audio id="audioPlayer" preload="metadata"></audio>

        <div class="progress-area">
            <div class="progress-time">
                <span id="currentTime">0:00</span>
                <span id="durationTime">0:00</span>
            </div>
            <input type="range" id="progressBar" value="0" step="0.1" min="0" max="100">
        </div>

        <div class="controls">
            <button class="control-btn" id="prevBtn" title="上一首"><i class="fas fa-backward-step"></i></button>
            <button class="control-btn play-pause" id="playPauseBtn" title="播放/暂停"><i class="fas fa-play"></i></button>
            <button class="control-btn" id="nextBtn" title="下一首"><i class="fas fa-forward-step"></i></button>
        </div>

        <div class="volume-control">
            <i class="fas fa-volume-high volume-icon" id="volumeIcon"></i>
            <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="0.7">
        </div>
    </main>

    <!-- 播放列表：底部抽屉 -->
    <div class="sheet-backdrop" id="sheetBackdrop"></div>
    <section class="playlist-sheet" id="playlistSheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <span>播放列表</span>
            <span class="sheet-count"><?php echo count($mp3Files); ?> 首</span>
        </div>

        <?php if (empty($mp3Files)): ?>
            <div class="empty-message">
                <i class="fas fa-headphones-simple"></i>
                <?php echo $error ? htmlspecialchars($error) : '暂无 MP3 文件<br>请放入 music/ 文件夹'; ?>
            </div>
        <?php else: ?>
            <ul class="playlist" id="playlist">
                <?php foreach ($mp3Files as $index => $file):
                    // 直接显示原始文件名（含扩展名），不做任何美化
                    $displayName = $file;
                    $encodedFile = $musicDir . '/' . rawurlencode($file);
                ?>
                <li data-index="<?php echo $index; ?>"
                    data-src="<?php echo htmlspecialchars($encodedFile); ?>"
                    data-title="<?php echo htmlspecialchars($displayName); ?>">
                    <i class="fas fa-music"></i>
                    <span class="track-name"><?php echo htmlspecialchars($displayName); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<script>
    (function () {
        const audio = document.getElementById('audioPlayer');
        const playPauseBtn = document.getElementById('playPauseBtn');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const progressBar = document.getElementById('progressBar');
        const currentTimeEl = document.getElementById('currentTime');
        const durationEl = document.getElementById('durationTime');
        const volumeSlider = document.getElementById('volumeSlider');
        const volumeIcon = document.getElementById('volumeIcon');
        const playlistEl = document.getElementById('playlist');
        const currentTrackName = document.getElementById('currentTrackName');
        const sheet = document.getElementById('playlistSheet');
        const backdrop = document.getElementById('sheetBackdrop');
        const playlistToggleBtn = document.getElementById('playlistToggleBtn');
        const playAllBtn = document.getElementById('playAllBtn');

        const playlistItems = playlistEl ? Array.from(playlistEl.querySelectorAll('li')) : [];
        let currentIndex = 0;
        let isDragging = false;
        let lastVolume = 0.7;

        /* ---------- 播放列表抽屉 ---------- */
        function openSheet() {
            sheet.classList.add('open');
            backdrop.classList.add('open');
        }
        function closeSheet() {
            sheet.classList.remove('open');
            backdrop.classList.remove('open');
        }
        playlistToggleBtn.addEventListener('click', function () {
            if (sheet.classList.contains('open')) {
                closeSheet();
            } else {
                openSheet();
            }
        });
        backdrop.addEventListener('click', closeSheet);

        /* ---------- 工具函数 ---------- */
        function formatTime(seconds) {
            if (isNaN(seconds) || seconds < 0) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        function setItemIcon(item, active) {
            const icon = item.querySelector('i');
            if (!icon) return;
            icon.classList.toggle('fa-music', !active);
            icon.classList.toggle('fa-volume-high', active);
        }

        function highlightCurrentItem(index) {
            if (!playlistItems.length) return;
            playlistItems.forEach(function (item) {
                item.classList.remove('active');
                setItemIcon(item, false);
            });
            const activeItem = playlistItems.find(function (item) {
                return parseInt(item.dataset.index, 10) === index;
            });
            if (activeItem) {
                activeItem.classList.add('active');
                setItemIcon(activeItem, true);
                currentTrackName.textContent = activeItem.dataset.title || '未知曲目';
                if (sheet.classList.contains('open')) {
                    activeItem.scrollIntoView({ block: 'nearest' });
                }
            }
        }

        /* ---------- 播放控制 ---------- */
        function loadSong(index) {
            if (!playlistItems.length) return false;
            if (index < 0) index = playlistItems.length - 1;
            if (index >= playlistItems.length) index = 0;
            const item = playlistItems[index];
            if (!item) return false;
            const src = item.dataset.src;
            const title = item.dataset.title;
            if (!src) return false;
            audio.src = src;
            currentTrackName.textContent = title || '未知曲目';
            highlightCurrentItem(index);
            progressBar.value = 0;
            currentTimeEl.textContent = '0:00';
            durationEl.textContent = '0:00';
            currentIndex = index;
            return true;
        }

        function playSong(index) {
            if (index === undefined) index = currentIndex;
            if (!playlistItems.length) return;
            if (index !== currentIndex || !audio.src) {
                loadSong(index);
            }
            audio.play().catch(function (e) { console.log('播放错误:', e); });
            updatePlayPauseIcon(true);
        }

        function togglePlay() {
            if (audio.paused) {
                if (!audio.src && playlistItems.length) {
                    loadSong(currentIndex);
                }
                audio.play().catch(function (e) { console.log(e); });
            } else {
                audio.pause();
            }
        }

        function updatePlayPauseIcon(isPlaying) {
            const icon = playPauseBtn.querySelector('i');
            if (isPlaying) {
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
            } else {
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
            }
        }

        function prevSong() {
            if (!playlistItems.length) return;
            let newIndex = currentIndex - 1;
            if (newIndex < 0) newIndex = playlistItems.length - 1;
            playSong(newIndex);
        }

        function nextSong() {
            if (!playlistItems.length) return;
            let newIndex = currentIndex + 1;
            if (newIndex >= playlistItems.length) newIndex = 0;
            playSong(newIndex);
        }

        function setVolume(vol) {
            vol = Math.max(0, Math.min(1, vol));
            audio.volume = vol;
            volumeSlider.value = vol;
            if (vol === 0) {
                volumeIcon.classList.remove('fa-volume-high', 'fa-volume-low');
                volumeIcon.classList.add('fa-volume-xmark');
            } else if (vol < 0.5) {
                volumeIcon.classList.remove('fa-volume-high', 'fa-volume-xmark');
                volumeIcon.classList.add('fa-volume-low');
            } else {
                volumeIcon.classList.remove('fa-volume-low', 'fa-volume-xmark');
                volumeIcon.classList.add('fa-volume-high');
            }
        }

        function toggleMute() {
            if (audio.volume > 0) {
                lastVolume = audio.volume;
                setVolume(0);
            } else {
                setVolume(lastVolume > 0 ? lastVolume : 0.5);
            }
        }

        /* ---------- 事件绑定 ---------- */
        playPauseBtn.addEventListener('click', togglePlay);
        prevBtn.addEventListener('click', prevSong);
        nextBtn.addEventListener('click', nextSong);

        playAllBtn.addEventListener('click', function () {
            if (playlistItems.length) playSong(0);
        });

        progressBar.addEventListener('input', function (e) {
            isDragging = true;
            if (audio.duration) {
                const seekTime = (e.target.value / 100) * audio.duration;
                audio.currentTime = seekTime;
                currentTimeEl.textContent = formatTime(seekTime);
            }
        });
        progressBar.addEventListener('change', function () { isDragging = false; });

        volumeSlider.addEventListener('input', function (e) {
            setVolume(parseFloat(e.target.value));
        });
        volumeIcon.addEventListener('click', toggleMute);

        audio.addEventListener('play', function () { updatePlayPauseIcon(true); });
        audio.addEventListener('pause', function () { updatePlayPauseIcon(false); });
        audio.addEventListener('ended', nextSong);
        audio.addEventListener('loadedmetadata', function () {
            if (audio.duration && isFinite(audio.duration)) {
                durationEl.textContent = formatTime(audio.duration);
                progressBar.max = 100;
            }
        });
        audio.addEventListener('timeupdate', function () {
            if (!isDragging && audio.duration && isFinite(audio.duration)) {
                progressBar.value = (audio.currentTime / audio.duration) * 100;
                currentTimeEl.textContent = formatTime(audio.currentTime);
            }
        });
        audio.addEventListener('volumechange', function () { setVolume(audio.volume); });

        if (playlistEl) {
            playlistEl.addEventListener('click', function (e) {
                const li = e.target.closest('li');
                if (!li) return;
                const index = parseInt(li.dataset.index, 10);
                if (!isNaN(index) && index >= 0) {
                    if (index === currentIndex) {
                        audio.currentTime = 0;
                        audio.play().catch(function (err) { console.log(err); });
                    } else {
                        playSong(index);
                    }
                }
            });
        }

        /* ---------- 初始化 ---------- */
        if (playlistItems.length > 0) {
            loadSong(0);
            audio.pause();
            updatePlayPauseIcon(false);
            setVolume(0.7);
            lastVolume = 0.7;
        } else {
            currentTrackName.textContent = '暂无音乐';
            playPauseBtn.disabled = true;
            prevBtn.disabled = true;
            nextBtn.disabled = true;
            volumeSlider.disabled = true;
            playlistToggleBtn.disabled = true;
            playAllBtn.disabled = true;
        }

        /* ---------- 键盘快捷键 ---------- */
        window.addEventListener('keydown', function (e) {
            if (e.code === 'Escape') closeSheet();
            if (e.code === 'Space' && !e.target.matches('input, button, textarea')) {
                e.preventDefault();
                togglePlay();
            }
            if (!e.target.matches('input, textarea')) {
                if (e.code === 'ArrowLeft') {
                    e.preventDefault();
                    prevSong();
                } else if (e.code === 'ArrowRight') {
                    e.preventDefault();
                    nextSong();
                }
            }
        });
    })();
</script>
</body>
</html>