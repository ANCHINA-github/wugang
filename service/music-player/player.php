<?php
/**
 * 苹果风格 PHP 音乐播放器
 * 自动扫描 ./music 文件夹中的 MP3 文件并生成播放列表
 * 修复：歌曲名（尤其是中文）显示不全的问题，强制完整换行
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

function beautifyFilename($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['_', '-'], ' ', $name);
    $name = ucwords($name);
    return $name ?: '未知曲目';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== 全局变量 & 深色模式 ========== */
        :root {
            --bg-gradient-start: #FFBCBD;
            --bg-gradient-end: #8BEEDE;
            --card-bg: rgba(255, 255, 255, 0.85);
            --card-border: rgba(255, 255, 255, 0.6);
            --text-primary: #1d1c1f;
            --text-secondary: #8e8e93;
            --text-active: #007aff;
            --btn-bg: rgba(240, 240, 245, 0.7);
            --btn-hover-bg: rgba(220, 220, 230, 0.9);
            --btn-active-bg: rgba(200, 200, 210, 0.95);
            --play-pause-bg: #1d1c1f;
            --play-pause-color: white;
            --list-hover-bg: rgba(224, 224, 230, 0.6);
            --list-active-bg: rgba(0, 0, 0, 0.04);
            --scrollbar-thumb: #c6c6c8;
            --slider-track: #e0e0e0;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient-start: #1e2a2e;
                --bg-gradient-end: #0d1117;
                --card-bg: rgba(28, 28, 30, 0.88);
                --card-border: rgba(255, 255, 255, 0.1);
                --text-primary: #f5f5f7;
                --text-secondary: #8e8e93;
                --text-active: #0a84ff;
                --btn-bg: rgba(80, 80, 90, 0.6);
                --btn-hover-bg: rgba(100, 100, 110, 0.8);
                --btn-active-bg: rgba(120, 120, 130, 0.9);
                --play-pause-bg: #ffffff;
                --play-pause-color: #1d1c1f;
                --list-hover-bg: rgba(80, 80, 90, 0.5);
                --list-active-bg: rgba(255, 255, 255, 0.08);
                --scrollbar-thumb: #3a3a3c;
                --slider-track: #3a3a3c;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(145deg, var(--bg-gradient-start), var(--bg-gradient-end));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
            transition: background 0.3s ease;
        }

        .player-card {
            max-width: 680px;
            width: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 38px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 6px 12px rgba(0, 0, 0, 0.08);
            padding: 30px 28px;
            border: 1px solid var(--card-border);
            transition: all 0.2s ease;
        }

        /* 当前播放区：歌名完整显示 + 强制换行 */
        .now-playing {
            text-align: left;
            margin-bottom: 28px;
        }

        .track-title {
            font-size: 26px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            line-height: 1.3;
            margin-bottom: 6px;
            /* 核心修复：允许任意位置换行，完整显示长汉字/英文 */
            white-space: normal;
            word-break: break-all;
            overflow-wrap: break-word;
        }

        .track-artist {
            font-size: 15px;
            font-weight: 400;
            color: var(--text-secondary);
            letter-spacing: -0.01em;
        }

        .progress-area {
            margin: 20px 0 16px;
        }

        .progress-time {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 400;
            margin-bottom: 6px;
        }

        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            margin-top: -5.5px;
            border: 0.5px solid rgba(0, 0, 0, 0.04);
            cursor: pointer;
            transition: 0.1s;
        }

        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.1);
            background: #f9f9f9;
        }

        .controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 18px 0 22px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .control-buttons {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .control-btn {
            background: var(--btn-bg);
            border: none;
            font-size: 28px;
            color: var(--text-primary);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            backdrop-filter: blur(5px);
        }

        .control-btn:hover {
            background: var(--btn-hover-bg);
            transform: scale(1.02);
        }

        .play-pause {
            background: var(--play-pause-bg);
            color: var(--play-pause-color);
            font-size: 30px;
            width: 64px;
            height: 64px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 130px;
        }

        .volume-icon {
            font-size: 22px;
            color: var(--text-secondary);
            width: 32px;
            cursor: pointer;
        }

        .playlist-section {
            margin-top: 28px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding-top: 18px;
        }

        .playlist-title {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 14px;
            padding-left: 6px;
        }

        .playlist {
            list-style: none;
            max-height: 260px;
            overflow-y: auto;
            border-radius: 20px;
            background: rgba(250, 250, 252, 0.3);
            padding: 6px 4px;
        }

        .playlist::-webkit-scrollbar {
            width: 5px;
        }
        .playlist::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 10px;
        }

        .playlist li {
            display: flex;
            align-items: flex-start;  /* 顶部对齐，适应多行文本 */
            padding: 12px 18px;
            margin: 4px 0;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 400;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.1s ease;
            gap: 14px;
        }

        .playlist li:hover {
            background: var(--list-hover-bg);
        }

        .playlist li.active {
            background: var(--list-active-bg);
            font-weight: 500;
            color: var(--text-active);
        }

        .playlist i {
            font-size: 16px;
            width: 24px;
            text-align: center;
            color: var(--text-secondary);
            opacity: 0.6;
            margin-top: 2px;  /* 微调图标与文字对齐 */
        }

        /* 修复播放列表歌名显示不全：强制换行 + 完整展示 */
        .playlist .track-name {
            flex: 1;
            white-space: normal;
            word-break: break-all;
            overflow-wrap: break-word;
            line-height: 1.4;
        }

        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
            background: rgba(255,255,255,0.4);
            border-radius: 40px;
        }

        .footer-note {
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-secondary);
            text-align: center;
            opacity: 0.8;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="player-card">
    <div class="now-playing">
        <div class="track-title" id="currentTrackName">选择一首歌曲</div>
        <div class="track-artist">MP3 · 本地音乐</div>
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
        <div class="control-buttons">
            <button class="control-btn" id="prevBtn" title="上一首"><i class="fas fa-backward-step"></i></button>
            <button class="control-btn play-pause" id="playPauseBtn" title="播放/暂停"><i class="fas fa-play"></i></button>
            <button class="control-btn" id="nextBtn" title="下一首"><i class="fas fa-forward-step"></i></button>
        </div>
        <div class="volume-control">
            <i class="fas fa-volume-high volume-icon" id="volumeIcon"></i>
            <input type="range" id="volumeSlider" class="volume-slider" min="0" max="1" step="0.01" value="0.7">
        </div>
    </div>

    <div class="playlist-section">
        <div class="playlist-title"><i class="fas fa-music" style="margin-right: 8px;"></i>播放列表 · <?php echo count($mp3Files); ?> 首歌曲</div>
        <?php if (empty($mp3Files) && !empty($error)): ?>
            <div class="empty-message">
                <i class="fas fa-folder-open"></i>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif (empty($mp3Files)): ?>
            <div class="empty-message">
                <i class="fas fa-headphones-simple"></i>
                <p>暂无 MP3 文件，请放入 <strong>music/</strong> 文件夹</p>
            </div>
        <?php else: ?>
            <ul class="playlist" id="playlist">
                <?php foreach ($mp3Files as $index => $file):
                    $beauty = beautifyFilename($file);
                    $encodedFile = $musicDir . '/' . rawurlencode($file);
                ?>
                <li data-index="<?php echo $index; ?>" data-src="<?php echo htmlspecialchars($encodedFile); ?>" data-title="<?php echo htmlspecialchars($beauty); ?>">
                    <i class="fas fa-music"></i>
                    <span class="track-name"><?php echo htmlspecialchars($beauty); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="footer-note">
        <i class="fas fa-circle-check"></i> 自动扫描 · 点击列表播放
    </div>
</div>

<script>
    (function() {
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

        const playlistItems = playlistEl ? Array.from(playlistEl.querySelectorAll('li')) : [];
        let currentIndex = 0;
        let isDragging = false;
        let lastVolume = 0.7;

        function formatTime(seconds) {
            if (isNaN(seconds) || seconds < 0) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        function highlightCurrentItem(index) {
            if (!playlistItems.length) return;
            playlistItems.forEach(item => item.classList.remove('active'));
            const activeItem = playlistItems.find(item => parseInt(item.dataset.index) === index);
            if (activeItem) {
                activeItem.classList.add('active');
                const title = activeItem.dataset.title || '未知曲目';
                currentTrackName.textContent = title;
            }
        }

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

        function playSong(index = currentIndex) {
            if (!playlistItems.length) return;
            if (index !== currentIndex || !audio.src) {
                loadSong(index);
            }
            audio.play().catch(e => console.log('播放错误:', e));
            updatePlayPauseIcon(true);
        }

        function pauseSong() {
            audio.pause();
            updatePlayPauseIcon(false);
        }

        function togglePlay() {
            if (audio.paused) {
                if (!audio.src && playlistItems.length) {
                    loadSong(currentIndex);
                }
                audio.play().catch(e => console.log(e));
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

        playPauseBtn.addEventListener('click', togglePlay);
        prevBtn.addEventListener('click', prevSong);
        nextBtn.addEventListener('click', nextSong);

        progressBar.addEventListener('input', (e) => {
            isDragging = true;
            if (audio.duration) {
                const seekTime = (e.target.value / 100) * audio.duration;
                audio.currentTime = seekTime;
                currentTimeEl.textContent = formatTime(seekTime);
            }
        });
        progressBar.addEventListener('change', () => { isDragging = false; });

        volumeSlider.addEventListener('input', (e) => setVolume(parseFloat(e.target.value)));
        volumeIcon.addEventListener('click', toggleMute);

        audio.addEventListener('play', () => updatePlayPauseIcon(true));
        audio.addEventListener('pause', () => updatePlayPauseIcon(false));
        audio.addEventListener('ended', nextSong);
        audio.addEventListener('loadedmetadata', () => {
            if (audio.duration && isFinite(audio.duration)) {
                durationEl.textContent = formatTime(audio.duration);
                progressBar.max = 100;
            }
        });
        audio.addEventListener('timeupdate', () => {
            if (!isDragging && audio.duration && isFinite(audio.duration)) {
                progressBar.value = (audio.currentTime / audio.duration) * 100;
                currentTimeEl.textContent = formatTime(audio.currentTime);
            }
        });
        audio.addEventListener('volumechange', () => setVolume(audio.volume));

        if (playlistEl) {
            playlistEl.addEventListener('click', (e) => {
                const li = e.target.closest('li');
                if (!li) return;
                const index = parseInt(li.dataset.index);
                if (!isNaN(index) && index >= 0) {
                    if (index === currentIndex) {
                        audio.currentTime = 0;
                        audio.play().catch(e => console.log(e));
                    } else {
                        playSong(index);
                    }
                }
            });
        }

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
        }

        window.addEventListener('keydown', (e) => {
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