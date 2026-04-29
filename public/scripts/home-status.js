(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var homePage = document.querySelector('.mc-home-page');
        var onlinePlayersEl = document.getElementById('onlinePlayers');
        var copyButtons = Array.prototype.slice.call(document.querySelectorAll('.js-copy-ip'));
        var joinButtons = Array.prototype.slice.call(document.querySelectorAll('.js-join-server'));
        var serverAddress = document.getElementById('serverAddress');
        var refreshStatusBtn = document.getElementById('refreshStatusBtn');
        var serverStatusDot = document.getElementById('serverStatusDot');
        var serverStatusText = document.getElementById('serverStatusText');
        var versionDisplay = document.getElementById('versionDisplay');
        var playersDisplay = document.getElementById('playersDisplay');
        var tpsDisplay = document.getElementById('tpsDisplay');
        var motdDisplay = document.getElementById('motdDisplay');
        var playersList = document.getElementById('playersList');

        var publicStatusWsUrl = homePage ? String(homePage.getAttribute('data-public-status-ws-url') || '').trim() : '';
        var statusSocket = null;
        var statusWsReconnectTimer = null;
        var statusWsReconnectDelayMs = 3000;
        var statusWsManualClose = false;
        var statusPollTimer = null;

        if (homePage) {
            requestAnimationFrame(function () {
                homePage.classList.add('is-loaded');
            });
        }

        var revealItems = Array.prototype.slice.call(document.querySelectorAll('.reveal-on-scroll'));
        revealItems.forEach(function (item) {
            if (item.classList.contains('reveal-on-scroll--hero')) {
                item.classList.add('is-visible');
            }
        });

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });

            revealItems.forEach(function (item) {
                if (!item.classList.contains('is-visible')) {
                    observer.observe(item);
                }
            });
        } else {
            revealItems.forEach(function (item) {
                item.classList.add('is-visible');
            });
        }

        function getServerAddressText() {
            return serverAddress ? serverAddress.textContent.trim() : '';
        }

        function copyAddressToClipboard() {
            return new Promise(function (resolve, reject) {
                var address = getServerAddressText();
                if (!address) {
                    reject(new Error('empty address'));
                    return;
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(address).then(resolve).catch(reject);
                    return;
                }

                try {
                    var textArea = document.createElement('textarea');
                    textArea.value = address;
                    textArea.style.position = 'fixed';
                    textArea.style.top = '0';
                    textArea.style.left = '0';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    var successful = document.execCommand('copy');
                    document.body.removeChild(textArea);
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error('copy failed'));
                    }
                } catch (error) {
                    reject(error);
                }
            });
        }

        function setButtonFeedback(button, message, cssClass) {
            if (!button) {
                return;
            }
            var label = button.querySelector('span');
            if (!label) {
                return;
            }

            var originalText = button.getAttribute('data-original-label') || label.textContent;
            button.setAttribute('data-original-label', originalText);
            label.textContent = message;
            if (cssClass) {
                button.classList.add(cssClass);
            }

            setTimeout(function () {
                label.textContent = originalText;
                if (cssClass) {
                    button.classList.remove(cssClass);
                }
            }, 2000);
        }

        function tryJoinServer(button) {
            copyAddressToClipboard().then(function () {
                setButtonFeedback(button, 'Copied and launching', 'joined');
                var address = getServerAddressText();
                if (address) {
                    window.location.href = 'minecraft://?addExternalServer=' + encodeURIComponent('StellarWorld|' + address);
                }
            }).catch(function (error) {
                console.error('Join server failed', error);
                alert('Unable to launch Minecraft automatically. Please copy the IP and join manually.');
            });
        }

        copyButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                copyAddressToClipboard().then(function () {
                    setButtonFeedback(button, 'Copied', 'copied');
                }).catch(function (error) {
                    console.error('Copy failed', error);
                    alert('Clipboard write failed. Please copy the server address manually.');
                });
            });
        });

        joinButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                tryJoinServer(button);
            });
        });

        if (!onlinePlayersEl) {
            return;
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function normalizePlayerList(players) {
            if (!Array.isArray(players)) {
                return [];
            }

            return players.map(function (player) {
                if (typeof player === 'string') {
                    return { name_clean: player };
                }
                if (player && typeof player === 'object') {
                    var cleanedName = player.name_clean || player.name || player.username || '';
                    return Object.assign({}, player, { name_clean: cleanedName });
                }
                return { name_clean: '' };
            }).filter(function (player) {
                var name = String(player.name_clean || '').trim();
                return name && name !== 'Anonymous Player' && name.toLowerCase().indexOf('anonymous') === -1;
            });
        }

        function mergeStatusPatch(base, patch) {
            var merged = Object.assign({}, base || {}, patch || {});
            merged.players = Object.assign({}, (base && base.players) || {}, (patch && patch.players) || {});
            merged.stats = Object.assign({}, (base && base.stats) || {}, (patch && patch.stats) || {});

            if (!Array.isArray(merged.players.list)) {
                merged.players.list = [];
            }

            return merged;
        }

        function unwrapApiPayload(payload) {
            if (!payload || typeof payload !== 'object') {
                return payload;
            }
            if (payload.data && typeof payload.data === 'object') {
                return payload.data;
            }
            if (payload.payload && typeof payload.payload === 'object') {
                return payload.payload;
            }
            return payload;
        }

        function setLoadingState() {
            if (serverStatusText) {
                serverStatusText.textContent = 'Connecting...';
            }
            if (serverStatusDot) {
                serverStatusDot.className = 'status-dot';
                serverStatusDot.style.animation = 'pulse 2s infinite';
            }
            if (versionDisplay) {
                versionDisplay.textContent = '--';
            }
            if (playersDisplay) {
                playersDisplay.textContent = '--';
            }
            if (tpsDisplay) {
                tpsDisplay.textContent = '--';
            }
            if (motdDisplay) {
                motdDisplay.textContent = 'Loading...';
            }
            if (playersList) {
                playersList.innerHTML = '<div class="loading-players">Loading...</div>';
            }
        }

        function setOfflineState() {
            if (serverStatusText) {
                serverStatusText.textContent = 'Offline';
            }
            if (serverStatusDot) {
                serverStatusDot.className = 'status-dot offline';
                serverStatusDot.style.animation = 'none';
            }
            if (versionDisplay) {
                versionDisplay.textContent = '--';
            }
            if (playersDisplay) {
                playersDisplay.textContent = '0/?';
            }
            if (tpsDisplay) {
                tpsDisplay.textContent = '--';
            }
            if (motdDisplay) {
                motdDisplay.textContent = 'Server unavailable';
            }
            if (playersList) {
                playersList.innerHTML = '<div class="no-players">No players online</div>';
            }
        }

        function updateServerStatusUI(inputData) {
            var data = mergeStatusPatch(window.__homeRealtimeStatus || {}, inputData || {});
            window.__homeRealtimeStatus = data;

            if (!data.online) {
                setOfflineState();
                return;
            }

            if (serverStatusText) {
                serverStatusText.textContent = 'Online';
            }
            if (serverStatusDot) {
                serverStatusDot.className = 'status-dot online';
                serverStatusDot.style.animation = 'none';
            }

            var verText = '--';
            if (typeof data.version === 'string') {
                verText = data.version;
            } else if (data.version && data.version.name_clean) {
                verText = data.version.name_clean;
            }
            if (versionDisplay) {
                versionDisplay.textContent = verText;
            }

            var onlinePlayers = data.players && data.players.online ? Number(data.players.online) : 0;
            var maxPlayers = (data.players && data.players.max !== undefined && data.players.max !== null) ? data.players.max : '?';
            var tpsValue = null;

            if (data.stats && typeof data.stats === 'object') {
                if (data.stats.onlinePlayers !== undefined && data.stats.onlinePlayers !== null) {
                    onlinePlayers = Number(data.stats.onlinePlayers) || onlinePlayers;
                }
                if (data.stats.maxPlayers !== undefined && data.stats.maxPlayers !== null) {
                    maxPlayers = data.stats.maxPlayers;
                }
                if (data.stats.tps !== undefined && data.stats.tps !== null) {
                    tpsValue = Number(data.stats.tps);
                }
            }
            if (tpsValue === null && data.tps !== undefined && data.tps !== null) {
                tpsValue = Number(data.tps);
            }

            var realPlayers = normalizePlayerList((data.players && data.players.list) || []);
            if (onlinePlayers === 0 && realPlayers.length > 0) {
                onlinePlayers = realPlayers.length;
            }

            if (playersDisplay) {
                playersDisplay.textContent = onlinePlayers + '/' + maxPlayers;
            }
            if (onlinePlayersEl) {
                onlinePlayersEl.textContent = onlinePlayers + '/' + maxPlayers;
            }
            if (tpsDisplay) {
                tpsDisplay.textContent = Number.isFinite(tpsValue) ? tpsValue.toFixed(2) : '--';
            }

            if (motdDisplay) {
                if (data.motd && data.motd.html) {
                    motdDisplay.innerHTML = data.motd.html;
                } else if (data.motd && data.motd.clean) {
                    motdDisplay.textContent = data.motd.clean;
                } else {
                    motdDisplay.textContent = '--';
                }
            }

            if (playersList) {
                if (realPlayers.length > 0) {
                    playersList.innerHTML = realPlayers.map(function (player) {
                        return '<div class="player-item">' + escapeHtml(player.name_clean) + '</div>';
                    }).join('');
                } else {
                    playersList.innerHTML = '<div class="no-players">No players online</div>';
                }
            }
        }

        async function fetchServerStatus() {
            window.isFreshestDataFetched = false;
            var timestamp = Date.now();

            fetch('/api/status/cache?t=' + timestamp)
                .then(function (res) { return res.json(); })
                .then(function (cacheData) {
                    if (!window.isFreshestDataFetched) {
                        updateServerStatusUI(unwrapApiPayload(cacheData));
                    }
                })
                .catch(function (error) {
                    console.warn('Read cache status failed', error);
                });

            var internalController = new AbortController();
            var internalTimeoutId = setTimeout(function () {
                internalController.abort();
            }, 5000);
            var internalStatusData = null;

            try {
                var internalResponse = await fetch('/api/status?t=' + timestamp, {
                    signal: internalController.signal
                });
                if (internalResponse.ok) {
                    var internalFreshData = await internalResponse.json();
                    internalStatusData = unwrapApiPayload(internalFreshData);
                    if (internalStatusData && internalStatusData.online === true) {
                        window.isFreshestDataFetched = true;
                        updateServerStatusUI(internalStatusData);
                        return;
                    }
                }
            } catch (error) {
                console.warn('Fetch internal status failed, try external fallback', error);
            } finally {
                clearTimeout(internalTimeoutId);
            }

            var fallbackController = new AbortController();
            var fallbackTimeoutId = setTimeout(function () {
                fallbackController.abort();
            }, 5000);

            try {
                var directUrl = 'https://api.mcstatus.io/v2/status/java/mc.stellarvan.cn:11051?t=' + timestamp;
                var fallbackResponse = await fetch(directUrl, {
                    signal: fallbackController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                if (fallbackResponse.ok) {
                    var fallbackData = await fallbackResponse.json();
                    window.isFreshestDataFetched = true;
                    updateServerStatusUI(unwrapApiPayload(fallbackData));
                    return;
                }
            } catch (error) {
                console.warn('Fetch external fallback status failed, keep cache/internal view');
            } finally {
                clearTimeout(fallbackTimeoutId);
            }

            if (internalStatusData) {
                window.isFreshestDataFetched = true;
                updateServerStatusUI(internalStatusData);
            }
        }

        function normalizeRealtimeMessage(payload) {
            return unwrapApiPayload(payload);
        }

        function applyRealtimeSnapshot(snapshot) {
            if (!snapshot || typeof snapshot !== 'object') {
                return;
            }

            var patch = {};

            if (snapshot.server && typeof snapshot.server === 'object') {
                if (snapshot.server.online !== undefined) {
                    patch.online = !!snapshot.server.online;
                }
                if (snapshot.server.motd !== undefined) {
                    patch.motd = snapshot.server.motd;
                }
                if (snapshot.server.version !== undefined) {
                    patch.version = snapshot.server.version;
                }
            }

            if (snapshot.status && typeof snapshot.status === 'object') {
                patch = mergeStatusPatch(patch, snapshot.status);
            }

            if (snapshot.players !== undefined) {
                if (Array.isArray(snapshot.players)) {
                    patch.players = { list: snapshot.players };
                } else if (snapshot.players && typeof snapshot.players === 'object') {
                    patch.players = Object.assign({}, snapshot.players);
                }
            }

            if (snapshot.stats && typeof snapshot.stats === 'object') {
                patch.stats = Object.assign({}, snapshot.stats);
                if (snapshot.stats.onlinePlayers !== undefined) {
                    patch.players = patch.players || {};
                    patch.players.online = snapshot.stats.onlinePlayers;
                }
                if (snapshot.stats.maxPlayers !== undefined) {
                    patch.players = patch.players || {};
                    patch.players.max = snapshot.stats.maxPlayers;
                }
            }

            updateServerStatusUI(patch);
        }

        function scheduleStatusWsReconnect() {
            if (statusWsManualClose || statusWsReconnectTimer !== null) {
                return;
            }

            statusWsReconnectTimer = window.setTimeout(function () {
                statusWsReconnectTimer = null;
                connectStatusWs();
            }, statusWsReconnectDelayMs);
        }

        function closeStatusWs() {
            statusWsManualClose = true;

            if (statusWsReconnectTimer !== null) {
                clearTimeout(statusWsReconnectTimer);
                statusWsReconnectTimer = null;
            }

            if (statusSocket) {
                try {
                    statusSocket.close();
                } catch (error) {
                }
                statusSocket = null;
            }
        }

        function connectStatusWs() {
            var wsUrl = String(publicStatusWsUrl || '').trim();
            if (!wsUrl || statusWsManualClose || document.hidden) {
                return;
            }
            if (statusSocket && (statusSocket.readyState === WebSocket.OPEN || statusSocket.readyState === WebSocket.CONNECTING)) {
                return;
            }

            try {
                statusSocket = new WebSocket(wsUrl);
            } catch (error) {
                scheduleStatusWsReconnect();
                return;
            }

            statusSocket.onmessage = function (event) {
                var payload;
                try {
                    payload = JSON.parse(event.data);
                } catch (error) {
                    return;
                }

                var type = String(payload.type || payload.event || payload.action || '');
                var data = normalizeRealtimeMessage(payload);
                if (!data || typeof data !== 'object') {
                    return;
                }

                if (type === 'snapshot') {
                    applyRealtimeSnapshot(data);
                    return;
                }
                if (type === 'players_update') {
                    applyRealtimeSnapshot({ players: data.players || data });
                    return;
                }
                if (type === 'stats_update') {
                    applyRealtimeSnapshot({ stats: data.stats || data });
                    return;
                }
                if (type === 'server_status') {
                    applyRealtimeSnapshot({ server: data.server || data, status: data.status || data });
                    return;
                }

                if (data.server || data.status || data.players || data.stats) {
                    applyRealtimeSnapshot(data);
                }
            };

            statusSocket.onerror = function () {
                scheduleStatusWsReconnect();
            };

            statusSocket.onclose = function () {
                statusSocket = null;
                if (!statusWsManualClose) {
                    scheduleStatusWsReconnect();
                }
            };
        }

        if (refreshStatusBtn) {
            refreshStatusBtn.addEventListener('click', async function () {
                var icon = refreshStatusBtn.querySelector('i');
                refreshStatusBtn.disabled = true;
                if (icon) {
                    icon.classList.add('spinning');
                }

                await fetchServerStatus();

                refreshStatusBtn.disabled = false;
                if (icon) {
                    icon.classList.remove('spinning');
                }
            });
        }

        setLoadingState();
        fetchServerStatus();
        statusPollTimer = window.setInterval(fetchServerStatus, 30000);

        if (String(publicStatusWsUrl || '').trim() !== '') {
            statusWsManualClose = false;
            connectStatusWs();
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                closeStatusWs();
                return;
            }

            statusWsManualClose = false;
            connectStatusWs();
        });

        window.addEventListener('beforeunload', function () {
            if (statusPollTimer !== null) {
                clearInterval(statusPollTimer);
                statusPollTimer = null;
            }
            closeStatusWs();
        });
    });
})();
