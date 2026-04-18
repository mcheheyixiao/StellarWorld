<div class="page-container flex flex-col gap-6">
    <section class="relative overflow-hidden rounded-3xl border border-cyan-300/20 bg-slate-950/65 p-6 shadow-[0_24px_54px_-30px_rgba(0,0,0,0.85)] backdrop-blur-xl md:p-10">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_20%,rgba(34,211,238,0.2),transparent_36%),radial-gradient(circle_at_88%_0%,rgba(59,130,246,0.2),transparent_38%)]"></div>
        <p class="mb-3 inline-flex rounded-full border border-cyan-300/30 bg-cyan-500/10 px-3 py-1 text-xs text-cyan-200">Minecraft 公益服务器</p>
        <h1 class="text-fusion-pixel text-2xl text-white md:text-4xl">繁星World · 现代生存社区</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300 md:text-base">
            长期维护、慢节奏发展、重视玩家共建。你可以在这里安心生存、自由建造、参与活动，并与社区一起创造持续演进的服务器世界。
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <code id="serverAddress" class="rounded-xl border border-white/20 bg-slate-900/80 px-4 py-2 font-mono text-sm text-cyan-200"><?= htmlspecialchars($serverDisplayAddress, ENT_QUOTES, 'UTF-8') ?></code>
            <button id="copyAddressBtn" class="copy-btn inline-flex items-center gap-2 rounded-xl bg-cyan-500/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-400">
                <i class="mdi mdi-content-copy"></i>
                <span>点击复制 IP</span>
            </button>
        </div>
        <div class="mt-6 grid gap-3 md:grid-cols-3">
            <div class="mc-glass-card mc-glow-ring p-4">
                <div class="text-xs uppercase tracking-widest text-slate-400">在线玩家</div>
                <div class="mt-2 text-xl font-bold text-white" id="onlinePlayers"><?= htmlspecialchars((string)($serverInfo['players_online'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="mc-glass-card p-4">
                <div class="text-xs uppercase tracking-widest text-slate-400">游戏版本</div>
                <div class="mt-2 text-xl font-bold text-white" id="gameVersion"><?= htmlspecialchars($serverVersion, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="mc-glass-card p-4">
                <div class="text-xs uppercase tracking-widest text-slate-400">服务器状态</div>
                <div class="mt-2 inline-flex items-center gap-2 text-base text-slate-200"><span class="status-dot" id="serverStatusDot"></span><span id="serverStatusText">正在连接...</span></div>
            </div>
        </div>
    </section>

    <section class="mc-glass-card p-6 md:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-fusion-pixel text-xl text-white md:text-2xl"><i class="mdi mdi-server-network mr-2 text-cyan-300"></i>服务器实时状态</h2>
            <button id="refreshStatusBtn" class="refresh-btn inline-flex items-center gap-2 rounded-xl border border-cyan-300/35 bg-cyan-500/10 px-3 py-2 text-sm text-cyan-200 transition hover:bg-cyan-500/20">
                <i class="mdi mdi-refresh"></i>
                <span>刷新</span>
            </button>
        </div>

        <div class="mb-6 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                <div class="mb-2 text-xs uppercase tracking-widest text-slate-400">服务器版本</div>
                <div class="text-sm text-slate-200" id="versionDisplay">—</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                <div class="mb-2 text-xs uppercase tracking-widest text-slate-400">在线人数</div>
                <div class="text-sm text-slate-200" id="playersDisplay">—</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 md:col-span-2">
                <div class="mb-2 text-xs uppercase tracking-widest text-slate-400">服务器 MOTD</div>
                <div class="detail-value motd text-sm text-cyan-200" id="motdDisplay">—</div>
            </div>
        </div>

        <div class="players-section" id="playersSection">
            <h3 class="mb-3 text-sm font-semibold text-slate-200">当前在线玩家</h3>
            <div class="players-list grid max-h-56 grid-cols-2 gap-2 overflow-y-auto rounded-2xl border border-white/10 bg-slate-900/70 p-3 md:grid-cols-4" id="playersList">
                <div class="loading-players col-span-full text-center text-slate-400">加载中...</div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2">
        <article class="mc-glass-card p-5">
            <h2 class="mb-2 text-fusion-pixel text-lg text-white">服务器特色</h2>
            <p class="text-sm leading-7 text-slate-300">公平生态、稳定运营、强社群活动与持续迭代玩法。我们不售卖破坏平衡的内容，优先保证玩家体验和存档安全。</p>
        </article>
        <article class="mc-glass-card p-5">
            <h2 class="mb-3 text-fusion-pixel text-lg text-white">友情链接</h2>
            <div class="friendly-links flex flex-wrap gap-2">
                <a href="https://started.ink/home" target="_blank" rel="noopener noreferrer" class="friendly-link-item rounded-lg border border-cyan-300/25 bg-cyan-500/10 px-3 py-1.5 text-sm text-cyan-200 transition hover:bg-cyan-500/25">星遥游戏</a>
                <a href="https://scarefree.cn/" target="_blank" rel="noopener noreferrer" class="friendly-link-item rounded-lg border border-cyan-300/25 bg-cyan-500/10 px-3 py-1.5 text-sm text-cyan-200 transition hover:bg-cyan-500/25">星遥工坊</a>
            </div>
        </article>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var onlinePlayersEl = document.getElementById('onlinePlayers');
    var copyAddressBtn = document.getElementById('copyAddressBtn');
    var serverAddress = document.getElementById('serverAddress');
    var refreshStatusBtn = document.getElementById('refreshStatusBtn');
    var serverStatusDot = document.getElementById('serverStatusDot');
    var serverStatusText = document.getElementById('serverStatusText');
    var versionDisplay = document.getElementById('versionDisplay');
    var playersDisplay = document.getElementById('playersDisplay');
    var motdDisplay = document.getElementById('motdDisplay');
    var playersList = document.getElementById('playersList');

    if (copyAddressBtn) {
        copyAddressBtn.addEventListener('click', async function () {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    // 现代浏览器 HTTPS 环境
                    await navigator.clipboard.writeText(serverAddress.textContent);
                } else {
                    // 传统的 HTTP 环境降级方案
                    var textArea = document.createElement("textarea");
                    textArea.value = serverAddress.textContent;
                    // 确保隐藏 textarea 不会导致页面滚动或影响布局
                    textArea.style.position = "fixed";
                    textArea.style.top = "0";
                    textArea.style.left = "0";
                    textArea.style.opacity = "0";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    var successful = document.execCommand('copy');
                    document.body.removeChild(textArea);
                    if (!successful) {
                        throw new Error('降级复制机制失败');
                    }
                }

                var originalText = copyAddressBtn.querySelector('span').textContent;
                copyAddressBtn.querySelector('span').textContent = '已复制';
                copyAddressBtn.classList.add('copied');
                setTimeout(function () {
                    copyAddressBtn.querySelector('span').textContent = originalText;
                    copyAddressBtn.classList.remove('copied');
                }, 2000);
            } catch (e) {
                console.error('复制失败', e);
                alert('剪贴板写入失败，请手动长按或全选复制。');
            }
        });
    }

    if (!onlinePlayersEl) {
        return;
    }

    function setLoadingState() {
        if (serverStatusText) serverStatusText.textContent = '正在连接...';
        if (serverStatusDot) {
            serverStatusDot.className = 'status-dot';
            serverStatusDot.style.animation = 'pulse 2s infinite';
        }
        if (versionDisplay) versionDisplay.textContent = '—';
        if (playersDisplay) playersDisplay.textContent = '—';
        if (motdDisplay) motdDisplay.textContent = '加载中...';
        if (playersList) playersList.innerHTML = '<div class="loading-players">加载中...</div>';
    }

    function setOfflineState() {
        if (serverStatusText) serverStatusText.textContent = '服务器离线';
        if (serverStatusDot) {
            serverStatusDot.className = 'status-dot offline';
            serverStatusDot.style.animation = 'none';
        }
        if (versionDisplay) versionDisplay.textContent = '—';
        if (playersDisplay) playersDisplay.textContent = '0/?';
        if (motdDisplay) motdDisplay.textContent = '服务器当前不可用';
        if (playersList) playersList.innerHTML = '<div class="no-players">服务器暂无玩家在线(在线列表不显示假人)</div>';
    }

    function updateServerStatusUI(data) {
        if (data.online) {
            if (serverStatusText) serverStatusText.textContent = '服务器在线';
            if (serverStatusDot) {
                serverStatusDot.className = 'status-dot online';
                serverStatusDot.style.animation = 'none';
            }
            var verText = '—';
            if (typeof data.version === 'string') {
                verText = data.version;
            } else if (data.version && data.version.name_clean) {
                verText = data.version.name_clean;
            }
            if (versionDisplay) {
                versionDisplay.textContent = verText !== '—' ? verText + ' (支持跨版本)' : verText;
            }
            var onlinePlayers = data.players && data.players.online ? data.players.online : 0;
            var maxPlayers = data.players && data.players.max ? data.players.max : '?';
            if (motdDisplay) {
                if (data.motd && data.motd.html) {
                    // mcstatus.io 返回的 html 是纯字符串，直接渲染
                    motdDisplay.innerHTML = data.motd.html;
                } else {
                    motdDisplay.textContent = data.motd && data.motd.clean ? data.motd.clean : '—';
                }
            }
            if (playersList) {
                var players = (data.players && data.players.list) || [];
                var realPlayers = players.filter(function (player) {
                    var name = player.name_clean || '';
                    return name && name !== 'Anonymous Player' && name.toLowerCase().indexOf('anonymous') === -1;
                });
                if (onlinePlayers === 0 && realPlayers.length > 0) {
                    onlinePlayers = realPlayers.length;
                }
                if (playersDisplay) playersDisplay.textContent = onlinePlayers + '/' + maxPlayers;
                if (onlinePlayersEl) onlinePlayersEl.textContent = onlinePlayers + '/' + maxPlayers;
                if (realPlayers.length > 0) {
                    playersList.innerHTML = realPlayers.map(function (player) {
                        return '<div class="player-item">' + player.name_clean + '</div>';
                    }).join('');
                } else {
                    playersList.innerHTML = '<div class="no-players">服务器暂无玩家在线(在线列表不显示假人)</div>';
                }
            }
        } else {
            setOfflineState();
        }
    }

    async function fetchServerStatus() {
        // 用于防止请求竞态：如果海外接口比本地接口还快返回（罕见但可能），阻止本地旧数据覆盖它
        window.isFreshestDataFetched = false; 
        const timestamp = new Date().getTime();

        // [赛道 1：本地缓存] 瞬间加载（预期耗时 < 50ms）
        fetch('/api/status/cache?t=' + timestamp)
            .then(res => res.json())
            .then(cacheData => {
                if (!window.isFreshestDataFetched) {
                    updateServerStatusUI(cacheData);
                }
            }).catch(e => console.warn('读取本地缓存垫底失败', e));

        // [赛道 2：海外最新数据] 强制直连（预期耗时 2-3s）
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        try {
            const directUrl = 'https://api.mcstatus.io/v2/status/java/mc.stellarvan.cn:11051?t=' + timestamp;
            const response = await fetch(directUrl, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });

            clearTimeout(timeoutId);

            if (response.ok) {
                const freshData = await response.json();
                window.isFreshestDataFetched = true; // 锁定更新权
                updateServerStatusUI(freshData);
            }
        } catch (error) {
            console.warn('获取海外最新状态超时或被拦截，继续维持缓存显示');
        }
    }

    if (refreshStatusBtn) {
        refreshStatusBtn.addEventListener('click', async function () {
            var icon = refreshStatusBtn.querySelector('i');
            refreshStatusBtn.disabled = true;
            if (icon) icon.classList.add('spinning');
            await fetchServerStatus();
            refreshStatusBtn.disabled = false;
            if (icon) icon.classList.remove('spinning');
        });
    }

    fetchServerStatus();
    setInterval(fetchServerStatus, 30000);
});
</script>

<style>
.copy-btn.copied { background: #16a34a; }
.status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background: #f59e0b;
    animation: pulse 2s infinite;
}
.status-dot.online { background: #22c55e; animation: none; }
.status-dot.offline { background: #ef4444; animation: none; }
.refresh-btn .spinning { animation: spin 1s linear infinite; }
.motd {
    white-space: pre-wrap;
    word-break: break-word;
    font-family: Consolas, monospace;
    text-align: center;
}
.motd * { text-align: center; }
.player-item {
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.85);
    padding: 0.5rem 0.65rem;
    text-align: center;
    color: #e2e8f0;
    font-size: 0.8rem;
}
.no-players {
    grid-column: 1 / -1;
    text-align: center;
    color: #cbd5e1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.85rem;
}
@keyframes pulse { 0%,100%{opacity:1;}50%{opacity:.45;} }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

