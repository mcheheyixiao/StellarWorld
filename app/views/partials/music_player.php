<div style="position: fixed !important; bottom: 1.5rem !important; right: 1.5rem !important; left: auto !important; z-index: 999999 !important;">
<div id="music-player" class="music-player">
    <!-- 播放器按钮 -->
    <button id="music-toggle" class="music-toggle" aria-label="音乐播放器">
        <i class="mdi mdi-music"></i>
    </button>

    <!-- 播放器界面 -->
    <div id="music-panel" class="music-panel">
        <div class="music-header">
            <h3>音乐播放器</h3>
            <button id="music-close" class="music-close" aria-label="关闭播放器">
                <i class="mdi mdi-close"></i>
            </button>
        </div>

        <div class="music-content">
            <!-- 自定义音乐播放器 -->
            <div id="custom-music-player" class="custom-music-player">
                <div class="player-controls">
                    <div class="song-info">
                        <img id="song-cover" src="" alt="歌曲封面" class="song-cover">
                        <div class="song-details">
                            <div id="song-title" class="song-title">选择歌曲</div>
                            <div id="song-artist" class="song-artist">-</div>
                        </div>
                    </div>

                    <div class="progress-container">
                        <div class="time-display">
                            <span id="current-time">0:00</span>
                            <span id="duration">0:00</span>
                        </div>
                        <div class="progress-bar" id="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                    </div>

                    <div class="control-buttons">
                        <button id="prev-btn" class="control-btn" aria-label="上一首">
                            <i class="mdi mdi-skip-previous"></i>
                        </button>
                        <button id="play-btn" class="control-btn play-btn" aria-label="播放">
                            <i class="mdi mdi-play"></i>
                        </button>
                        <button id="next-btn" class="control-btn" aria-label="下一首">
                            <i class="mdi mdi-skip-next"></i>
                        </button>
                        <div class="volume-control">
                            <button id="mute-btn" class="control-btn" aria-label="静音">
                                <i class="mdi mdi-volume-high"></i>
                            </button>
                            <div class="volume-bar" id="volume-bar">
                                <div class="volume-fill" id="volume-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="playlist-container">
                    <div class="playlist-header">
                        <h4>播放列表</h4>
                        <span id="playlist-count">0 首歌曲</span>
                    </div>
                    <div id="playlist" class="playlist">
                        <!-- 播放列表将在这里动态生成 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 物理音频标签，保持与原项目兼容 -->
    <audio id="bgm-audio" loop>
        <source src="/assets/music/bgm.mp3" type="audio/mpeg">
        <!-- 如果浏览器不支持 audio，将自动静默失败 -->
    </audio>
</div>
</div>

<script>
// 音乐播放器状态（从 Astro 组件迁移，移除框架语法）
let currentSong = {
    id: 0,
    title: '选择歌曲',
    artist: '-',
    cover: '',
    url: '',
    duration: 0
};

let playlist = [];
let currentIndex = 0;
let audio = null;
let isPlaying = false;
let isMuted = false;
let volume = 0.8;
let isShuffled = false;
let isRepeating = 0; // 0: 不循环, 1: 单曲循环, 2: 列表循环

// 初始化音乐播放器
document.addEventListener('DOMContentLoaded', function () {
    var musicToggle = document.getElementById('music-toggle');
    var musicPanel = document.getElementById('music-panel');
    var musicClose = document.getElementById('music-close');

    // 使用页面上的物理 <audio> 元素，保持对 /assets/music/bgm.mp3 的支持
    audio = document.getElementById('bgm-audio');
    if (!audio) {
        return;
    }
    audio.volume = volume;

    // 设置音频事件监听器
    audio.addEventListener('loadedmetadata', updateDuration);
    audio.addEventListener('timeupdate', updateProgress);
    audio.addEventListener('ended', handleSongEnd);
    audio.addEventListener('play', function () { isPlaying = true; updatePlayButton(); });
    audio.addEventListener('pause', function () { isPlaying = false; updatePlayButton(); });

    // 初始化UI事件监听器
    initUIEvents();

    // 加载播放列表
    loadPlaylist();

    if (musicToggle && musicPanel) {
        musicToggle.addEventListener('click', function () {
            musicPanel.classList.toggle('active');
            musicToggle.classList.toggle('active');
        });

        if (musicClose) {
            musicClose.addEventListener('click', function () {
                musicPanel.classList.remove('active');
                musicToggle.classList.remove('active');
            });
        }

        // 点击外部关闭播放器
        document.addEventListener('click', function (e) {
            var mp = document.getElementById('music-player');
            if (mp && !mp.contains(e.target) && musicPanel.classList.contains('active')) {
                musicPanel.classList.remove('active');
                musicToggle.classList.remove('active');
            }
        });
    }
});

// 初始化UI事件
function initUIEvents() {
    var playBtn = document.getElementById('play-btn');
    var prevBtn = document.getElementById('prev-btn');
    var nextBtn = document.getElementById('next-btn');
    var muteBtn = document.getElementById('mute-btn');
    var progressBar = document.getElementById('progress-bar');
    var volumeBar = document.getElementById('volume-bar');

    if (playBtn) playBtn.addEventListener('click', togglePlay);
    if (prevBtn) prevBtn.addEventListener('click', previousSong);
    if (nextBtn) nextBtn.addEventListener('click', nextSong);
    if (muteBtn) muteBtn.addEventListener('click', toggleMute);

    // 进度条点击事件
    if (progressBar) {
        progressBar.addEventListener('click', function (e) {
            if (!audio || !audio.src) return;
            var rect = progressBar.getBoundingClientRect();
            var percent = (e.clientX - rect.left) / rect.width;
            audio.currentTime = percent * (audio.duration || 0);
        });
    }

    // 音量条点击事件
    if (volumeBar) {
        volumeBar.addEventListener('click', function (e) {
            var rect = volumeBar.getBoundingClientRect();
            var percent = (e.clientX - rect.left) / rect.width;
            setVolume(percent);
        });
    }
}

// 加载播放列表
async function loadPlaylist() {
    try {
        const response = await fetch('https://api.i-meto.com/meting/api?server=netease&type=playlist&id=5186526688');
        if (!response.ok) throw new Error('API请求失败');

        const data = await response.json();
        playlist = data.map((song, index) => ({
            id: song.id || index,
            title: song.name || song.title || '未知歌曲',
            artist: song.artist || song.author || '未知艺术家',
            cover: song.pic || '',
            url: song.url || '',
            duration: song.duration || 0
        }));

        updatePlaylistUI();

        // 如果有歌曲，加载第一首
        if (playlist.length > 0) {
            loadSong(0);
        }
    } catch (error) {
        console.error('加载播放列表失败:', error);
        // 使用备用播放列表（首个为项目原有 bgm.mp3）
        loadFallbackPlaylist();
    }
}

// 备用播放列表，确保本地 /assets/music/bgm.mp3 可播放
function loadFallbackPlaylist() {
    playlist = [
        {
            id: 1,
            title: '服务器背景音乐',
            artist: 'Wan\'s MC Web',
            cover: '',
            url: '/assets/music/bgm.mp3',
            duration: 0
        }
    ];

    updatePlaylistUI();
    if (playlist.length > 0) {
        loadSong(0);
    }
}

// 加载歌曲
function loadSong(index) {
    if (index < 0 || index >= playlist.length || !audio) return;

    currentIndex = index;
    currentSong = Object.assign({}, playlist[index]);

    // 更新UI
    var titleEl = document.getElementById('song-title');
    var artistEl = document.getElementById('song-artist');
    var coverEl = document.getElementById('song-cover');
    if (titleEl) titleEl.textContent = currentSong.title;
    if (artistEl) artistEl.textContent = currentSong.artist;
    if (coverEl) coverEl.src = currentSong.cover || '';

    // 设置音频源（依然基于页面上的 <audio>）
    audio.src = currentSong.url || '/assets/music/bgm.mp3';
    audio.load();

    // 高亮当前播放的歌曲
    updatePlaylistUI();
}

// 切换播放状态
function togglePlay() {
    if (!audio || !audio.src) return;

    if (isPlaying) {
        audio.pause();
    } else {
        audio.play().catch(function (error) {
            console.error('播放失败:', error);
        });
    }
}

// 上一首
function previousSong() {
    if (!audio || playlist.length <= 1) return;

    var newIndex = currentIndex > 0 ? currentIndex - 1 : playlist.length - 1;
    loadSong(newIndex);
    if (isPlaying) {
        audio.play();
    }
}

// 下一首
function nextSong() {
    if (!audio || playlist.length <= 1) return;

    var newIndex;
    if (isShuffled) {
        do {
            newIndex = Math.floor(Math.random() * playlist.length);
        } while (newIndex === currentIndex && playlist.length > 1);
    } else {
        newIndex = currentIndex < playlist.length - 1 ? currentIndex + 1 : 0;
    }

    loadSong(newIndex);
    if (isPlaying) {
        audio.play();
    }
}

// 歌曲结束处理
function handleSongEnd() {
    if (!audio) return;

    if (isRepeating === 1) {
        // 单曲循环
        audio.currentTime = 0;
        audio.play();
    } else if (isRepeating === 2 || currentIndex < playlist.length - 1) {
        // 列表循环或还有下一首
        nextSong();
    }
}

// 切换静音
function toggleMute() {
    if (!audio) return;

    isMuted = !isMuted;
    audio.muted = isMuted;

    var muteIcon = document.querySelector('#mute-btn i');
    if (!muteIcon) return;

    if (isMuted || volume === 0) {
        muteIcon.className = 'mdi mdi-volume-off';
    } else {
        muteIcon.className = volume > 0.5 ? 'mdi mdi-volume-high' : 'mdi mdi-volume-medium';
    }
}

// 设置音量
function setVolume(value) {
    if (!audio) return;

    volume = Math.max(0, Math.min(1, value));
    audio.volume = volume;

    var volumeFill = document.getElementById('volume-fill');
    if (volumeFill) {
        volumeFill.style.width = (volume * 100) + '%';
    }

    var muteIcon = document.querySelector('#mute-btn i');
    if (!muteIcon) return;

    if (volume === 0) {
        muteIcon.className = 'mdi mdi-volume-off';
    } else if (volume > 0.5) {
        muteIcon.className = 'mdi mdi-volume-high';
    } else {
        muteIcon.className = 'mdi mdi-volume-medium';
    }
}

// 更新播放按钮状态
function updatePlayButton() {
    var playIcon = document.querySelector('#play-btn i');
    if (!playIcon) return;
    if (isPlaying) {
        playIcon.className = 'mdi mdi-pause';
    } else {
        playIcon.className = 'mdi mdi-play';
    }
}

// 更新时长显示
function updateDuration() {
    var durationElement = document.getElementById('duration');
    if (!durationElement || !audio) return;
    durationElement.textContent = formatTime(audio.duration || 0);
}

// 更新进度条
function updateProgress() {
    var currentTimeElement = document.getElementById('current-time');
    var progressFill = document.getElementById('progress-fill');
    if (!audio || !currentTimeElement || !progressFill) return;

    currentTimeElement.textContent = formatTime(audio.currentTime || 0);

    if (audio.duration) {
        var progress = (audio.currentTime / audio.duration) * 100;
        progressFill.style.width = progress + '%';
    }
}

// 格式化时间
function formatTime(seconds) {
    seconds = seconds || 0;
    var mins = Math.floor(seconds / 60);
    var secs = Math.floor(seconds % 60);
    return mins + ':' + (secs < 10 ? '0' : '') + secs;
}

// 更新播放列表UI
function updatePlaylistUI() {
    var playlistElement = document.getElementById('playlist');
    var countElement = document.getElementById('playlist-count');

    if (!playlistElement || !countElement) return;

    countElement.textContent = playlist.length + ' 首歌曲';

    playlistElement.innerHTML = playlist.map(function (song, index) {
        var isActive = index === currentIndex ? 'active' : '';
        return (
            '<div class="playlist-item ' + isActive + '" data-index="' + index + '">' +
                '<div class="playlist-content">' +
                    '<span class="playlist-title">' + song.title + '</span>' +
                    '<span class="playlist-separator">-</span>' +
                    '<span class="playlist-artist">' + song.artist + '</span>' +
                '</div>' +
            '</div>'
        );
    }).join('');

    // 添加点击事件
    playlistElement.querySelectorAll('.playlist-item').forEach(function (item, index) {
        item.addEventListener('click', function () {
            loadSong(index);
            if (isPlaying && audio) {
                audio.play();
            }
        });
    });
}
</script>

<style>
.music-player {
    position: static;
}

.music-toggle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(14, 165, 233, 0.34);
    border: 1px solid rgba(34, 211, 238, 0.42);
    color: #e0f2fe;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 16px 36px -20px rgba(0, 0, 0, 0.7), 0 0 20px -10px rgba(34, 211, 238, 0.45);
    transition: all 0.3s var(--ease-smooth);
    position: relative;
    z-index: 2;
}

.music-toggle:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-xl);
}

.music-toggle.active {
    background: rgba(14, 165, 233, 0.55);
}

.music-panel {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 350px;
    max-width: 90vw;
    background: rgba(2, 6, 23, 0.86);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 1rem;
    box-shadow: 0 24px 60px -34px rgba(0, 0, 0, 0.88), 0 0 22px -14px rgba(34, 211, 238, 0.32);
    transform: translateY(20px) scale(0.9);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s var(--ease-smooth);
    z-index: 1;
}

.music-panel.active {
    transform: translateY(0) scale(1);
    opacity: 1;
    visibility: visible;
}

.music-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}

.music-header h3 {
    margin: 0;
    color: #f8fafc;
    font-size: 1.1rem;
    font-weight: 600;
}

.music-close {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: var(--radius-full);
    transition: all 0.3s var(--ease-smooth);
}

.music-close:hover {
    background: rgba(14, 165, 233, 0.2);
    color: #a5f3fc;
}

.music-content {
    padding: 1.5rem;
    height: 400px;
    overflow: hidden;
}

/* 自定义音乐播放器样式 */
.custom-music-player {
    width: 100%;
    height: 100%;
}

.player-controls {
    padding: 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}

.song-info {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.song-cover {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-md);
    object-fit: cover;
    margin-right: 1rem;
}

.song-details {
    flex: 1;
}

.song-title {
    font-size: 1rem;
    font-weight: 600;
    color: #f8fafc;
    margin-bottom: 0.25rem;
}

.song-artist {
    font-size: 0.875rem;
    color: #94a3b8;
}

.progress-container {
    margin-bottom: 1rem;
}

.time-display {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #94a3b8;
    margin-bottom: 0.5rem;
}

.progress-bar {
    width: 100%;
    height: 4px;
    background: rgba(148, 163, 184, 0.2);
    border-radius: 2px;
    cursor: pointer;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #06b6d4, #38bdf8);
    border-radius: 2px;
    width: 0%;
    transition: width 0.1s ease;
}

.control-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.control-btn {
    background: rgba(15, 23, 42, 0.76);
    border: 1px solid rgba(148, 163, 184, 0.26);
    border-radius: var(--radius-full);
    color: #e2e8f0;
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s var(--ease-smooth);
}

.control-btn:hover {
    background: rgba(14, 165, 233, 0.34);
    color: #ecfeff;
    transform: scale(1.05);
}

.control-btn.play-btn {
    width: 40px;
    height: 40px;
    background: rgba(14, 165, 233, 0.4);
    color: #ecfeff;
}

.control-btn.play-btn:hover {
    background: rgba(14, 165, 233, 0.56);
    transform: scale(1.1);
}

.volume-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.volume-bar {
    width: 60px;
    height: 4px;
    background: rgba(148, 163, 184, 0.2);
    border-radius: 2px;
    cursor: pointer;
    position: relative;
}

.volume-fill {
    height: 100%;
    background: linear-gradient(90deg, #06b6d4, #38bdf8);
    border-radius: 2px;
    width: 80%;
}

.playlist-container {
    padding: 0.75rem;
}

.playlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}

.playlist-header h4 {
    margin: 0;
    color: #f8fafc;
    font-size: 0.85rem;
    font-weight: 600;
}

#playlist-count {
    font-size: 0.65rem;
    color: #94a3b8;
    background: rgba(15, 23, 42, 0.72);
    padding: 0.15rem 0.4rem;
    border-radius: var(--radius-full);
}

.playlist {
    height: 160px;
    overflow-y: auto;
    overflow-x: hidden;
    border-radius: var(--radius-sm);
    background: rgba(15, 23, 42, 0.66);
    border: 1px solid rgba(148, 163, 184, 0.22);
}

.playlist-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    cursor: pointer;
    transition: all 0.3s var(--ease-smooth);
    border-bottom: 1px solid rgba(148, 163, 184, 0.14);
    min-height: 32px;
    line-height: 1;
}

.playlist-item:nth-child(even) {
    background: rgba(15, 23, 42, 0.55) !important;
}

.playlist-item:nth-child(odd) {
    background: rgba(30, 41, 59, 0.55) !important;
}

.playlist-item:last-child {
    border-bottom: none;
}

.playlist-item:hover {
    background: rgba(14, 165, 233, 0.2) !important;
    transform: translateX(2px);
}

.playlist-item.active {
    background: rgba(14, 165, 233, 0.34) !important;
    color: #ecfeff;
    border-left: 3px solid #22d3ee;
}

.playlist-item.active:hover {
    background: rgba(14, 165, 233, 0.46) !important;
}

.playlist-content {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    gap: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
}

.playlist-title {
    font-size: 0.8rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
    max-width: 60%;
}

.playlist-separator {
    font-size: 0.75rem;
    opacity: 0.6;
    flex-shrink: 0;
}

.playlist-artist {
    font-size: 0.75rem;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
    max-width: 40%;
}

/* 滚动条样式 */
.playlist::-webkit-scrollbar {
    width: 4px;
}

.playlist::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.7);
    border-radius: 2px;
}

.playlist::-webkit-scrollbar-thumb {
    background: #0ea5e9;
    border-radius: 2px;
}

.playlist::-webkit-scrollbar-thumb:hover {
    background: #06b6d4;
}

[data-theme="light"] .music-panel {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(148, 163, 184, 0.34);
    box-shadow: 0 22px 52px -30px rgba(15, 23, 42, 0.36), 0 0 20px -14px rgba(14, 165, 233, 0.25);
}

[data-theme="light"] .music-header,
[data-theme="light"] .player-controls,
[data-theme="light"] .playlist-header {
    border-bottom-color: rgba(148, 163, 184, 0.28);
}

[data-theme="light"] .music-header h3,
[data-theme="light"] .song-title,
[data-theme="light"] .playlist-header h4 {
    color: #0f172a;
}

[data-theme="light"] .music-close,
[data-theme="light"] .song-artist,
[data-theme="light"] .time-display,
[data-theme="light"] #playlist-count {
    color: #475569;
}

[data-theme="light"] #playlist-count {
    background: rgba(226, 232, 240, 0.7);
}

[data-theme="light"] .control-btn {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(148, 163, 184, 0.32);
    color: #0f172a;
}

[data-theme="light"] .playlist {
    background: rgba(248, 250, 252, 0.9);
    border-color: rgba(148, 163, 184, 0.3);
}

[data-theme="light"] .playlist-item {
    border-bottom-color: rgba(148, 163, 184, 0.2);
    color: #1e293b;
}

[data-theme="light"] .playlist-item:nth-child(even) {
    background: rgba(248, 250, 252, 0.95) !important;
}

[data-theme="light"] .playlist-item:nth-child(odd) {
    background: rgba(241, 245, 249, 0.95) !important;
}

[data-theme="light"] .playlist::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.75);
}

@media (max-width: 768px) {
    .music-player {
        bottom: 1rem;
        left: 1rem;
    }

    .music-toggle {
        width: 50px;
        height: 50px;
        font-size: 1.3rem;
    }

    .music-panel {
        width: 300px;
        right: -50px;
    }
}
</style>