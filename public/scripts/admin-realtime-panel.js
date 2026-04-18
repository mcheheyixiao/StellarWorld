(function () {
    'use strict';

    var config = window.adminRealtimePanelConfig || {};
    var realtimeTab = document.getElementById('tab-realtime');

    if (!config || config.enable_realtime_panel !== true || !realtimeTab) {
        return;
    }

    var reconnectIntervalMs = Math.max(500, Number(config.reconnect_interval_ms || 3000));
    var maxChatMessages = 120;
    var socket = null;
    var reconnectTimer = null;
    var reconnectAttempts = 0;
    var manualClose = false;

    var dom = {
        connectionBanner: document.getElementById('realtime-connection-banner'),
        connectionText: document.getElementById('realtime-connection-text'),
        onlineCount: document.getElementById('realtime-online-count'),
        maxCount: document.getElementById('realtime-max-count'),
        tps: document.getElementById('realtime-tps'),
        mspt: document.getElementById('realtime-mspt'),
        cpu: document.getElementById('realtime-cpu'),
        memory: document.getElementById('realtime-memory'),
        serverStatus: document.getElementById('realtime-server-status'),
        lastUpdated: document.getElementById('realtime-last-updated'),
        playerTotal: document.getElementById('realtime-player-total'),
        playerList: document.getElementById('realtime-player-list'),
        pluginList: document.getElementById('realtime-plugin-list'),
        chatStream: document.getElementById('realtime-chat-stream')
    };

    function connect(isReconnect) {
        if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        clearReconnectTimer();
        var wsUrl = buildWsUrl(config.ws_url, config.ws_auth_token);
        if (!wsUrl) {
            setConnectionState('error', '未配置 ws_url');
            return;
        }

        manualClose = false;
        setConnectionState(isReconnect ? 'reconnecting' : 'connecting');

        try {
            socket = new WebSocket(wsUrl);
        } catch (err) {
            setConnectionState('error', '连接失败');
            reconnect();
            return;
        }

        socket.onopen = function () {
            reconnectAttempts = 0;
            setConnectionState('connected');
        };

        socket.onclose = function (event) {
            socket = null;
            if (manualClose) {
                setConnectionState('disconnected');
                return;
            }

            var closeReason = event && event.reason ? String(event.reason) : '';
            if (closeReason !== '') {
                setConnectionState('disconnected', closeReason);
            } else {
                setConnectionState('disconnected');
            }
            reconnect();
        };

        socket.onerror = function () {
            setConnectionState('error', '网络异常');
        };

        socket.onmessage = function (event) {
            var payload;
            try {
                payload = JSON.parse(event.data);
            } catch (err) {
                setConnectionState('error', '消息格式错误');
                return;
            }
            handleMessage(payload);
        };
    }

    function reconnect() {
        if (manualClose || reconnectTimer !== null) {
            return;
        }

        reconnectAttempts += 1;
        setConnectionState('reconnecting', reconnectAttempts + ' 次');
        reconnectTimer = window.setTimeout(function () {
            reconnectTimer = null;
            connect(true);
        }, reconnectIntervalMs);
    }

    function disconnect() {
        manualClose = true;
        clearReconnectTimer();
        if (socket) {
            socket.close();
            socket = null;
        }
        setConnectionState('disconnected');
    }

    function clearReconnectTimer() {
        if (reconnectTimer !== null) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
    }

    function setConnectionState(state, detail) {
        if (!dom.connectionBanner || !dom.connectionText) {
            return;
        }

        dom.connectionBanner.classList.remove(
            'ta-realtime-connection-connected',
            'ta-realtime-connection-pending',
            'ta-realtime-connection-reconnecting',
            'ta-realtime-connection-disconnected',
            'ta-realtime-connection-error'
        );

        var text = '';
        if (state === 'connected') {
            dom.connectionBanner.classList.add('ta-realtime-connection-connected');
            text = '已连接';
        } else if (state === 'connecting') {
            dom.connectionBanner.classList.add('ta-realtime-connection-pending');
            text = '连接中';
        } else if (state === 'reconnecting') {
            dom.connectionBanner.classList.add('ta-realtime-connection-reconnecting');
            text = '重连中';
        } else if (state === 'disconnected') {
            dom.connectionBanner.classList.add('ta-realtime-connection-disconnected');
            text = '已断开';
        } else {
            dom.connectionBanner.classList.add('ta-realtime-connection-error');
            text = '错误';
        }

        dom.connectionText.textContent = detail ? text + '（' + detail + '）' : text;
    }

    function handleMessage(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        var eventType = String(payload.type || payload.event || payload.action || '');
        var data = Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;

        switch (eventType) {
            case 'snapshot':
                applySnapshot(data);
                break;
            case 'stats_update':
                applyStatsUpdate(data);
                break;
            case 'players_update':
                applyPlayersUpdate(data);
                break;
            case 'plugins_update':
                applyPluginsUpdate(data);
                break;
            case 'chat_message':
                applyChatMessage(data);
                break;
            case 'server_status':
                applyServerStatus(data);
                break;
            default:
                // 兼容后端直接推送快照对象（无 type 字段）
                if (data && typeof data === 'object') {
                    if (data.stats || data.players || data.plugins || data.chat || data.messages) {
                        applySnapshot(data);
                    }
                }
                break;
        }
    }

    function applySnapshot(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        applyStatsUpdate(data.stats || data);

        if (data.players || data.online_players) {
            applyPlayersUpdate(data.players || data.online_players);
        }

        if (data.plugins) {
            applyPluginsUpdate(data.plugins);
        }

        if (data.chat || data.messages) {
            renderChatList(Array.isArray(data.chat) ? data.chat : data.messages);
        }

        if (data.server_status || data.status) {
            applyServerStatus(data.server_status || data.status);
        }
    }

    function applyStatsUpdate(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        var online = pickNumber(data, ['online', 'online_players', 'player_count']);
        var maxPlayers = pickNumber(data, ['max', 'max_players']);
        var tps = pickNumber(data, ['tps']);
        var mspt = pickNumber(data, ['mspt']);
        var cpu = pickNumber(data, ['cpu', 'cpu_percent']);
        var memory = pickString(data, ['memory', 'memory_usage', 'ram']);

        if (online !== null) {
            dom.onlineCount.textContent = String(online);
        }
        if (maxPlayers !== null) {
            dom.maxCount.textContent = String(maxPlayers);
        }
        if (tps !== null) {
            dom.tps.textContent = formatDecimal(tps, 2);
        }
        if (mspt !== null) {
            dom.mspt.textContent = formatDecimal(mspt, 2) + ' ms';
        }
        if (cpu !== null) {
            dom.cpu.textContent = formatDecimal(cpu, 1) + '%';
        }
        if (memory !== null) {
            dom.memory.textContent = memory;
        }

        setLastUpdated(
            pickValue(data, ['last_updated', 'updated_at', 'timestamp', 'time'])
        );
    }

    function applyPlayersUpdate(data) {
        var players = [];
        if (Array.isArray(data)) {
            players = data;
        } else if (data && Array.isArray(data.players)) {
            players = data.players;
        }
        renderPlayers(players);
    }

    function applyPluginsUpdate(data) {
        var plugins = [];
        if (Array.isArray(data)) {
            plugins = data;
        } else if (data && Array.isArray(data.plugins)) {
            plugins = data.plugins;
        }
        renderPlugins(plugins);
    }

    function applyChatMessage(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        var playerName = String(
            pickValue(data, ['player', 'username', 'name', 'sender']) || '未知玩家'
        );
        var message = String(
            pickValue(data, ['message', 'content', 'text']) || ''
        ).trim();
        var timeValue = pickValue(data, ['time', 'timestamp', 'created_at', 'at']);

        if (message === '') {
            return;
        }

        appendChatItem({
            player: playerName,
            message: message,
            time: timeValue
        });
    }

    function applyServerStatus(data) {
        var statusText = '未知';
        if (typeof data === 'string') {
            statusText = data;
        } else if (typeof data === 'boolean') {
            statusText = data ? '在线' : '离线';
        } else if (data && typeof data === 'object') {
            var raw = pickValue(data, ['status', 'state', 'online']);
            if (typeof raw === 'boolean') {
                statusText = raw ? '在线' : '离线';
            } else if (raw !== null && raw !== undefined) {
                statusText = String(raw);
            }
        }
        dom.serverStatus.textContent = statusText;
    }

    function renderPlayers(players) {
        if (!dom.playerList || !dom.playerTotal) {
            return;
        }

        dom.playerTotal.textContent = String(players.length) + ' 人';

        if (!players.length) {
            dom.playerList.innerHTML = '<li class="ta-realtime-empty">暂无在线玩家</li>';
            return;
        }

        var html = players.map(function (player, index) {
            var name = '';
            if (typeof player === 'string') {
                name = player;
            } else if (player && typeof player === 'object') {
                name = String(pickValue(player, ['name', 'username', 'player']) || '');
            }
            if (!name) {
                name = '玩家 #' + (index + 1);
            }
            return '<li class="ta-realtime-player-item"><span>' + escapeHtml(name) + '</span><span>#' + (index + 1) + '</span></li>';
        }).join('');

        dom.playerList.innerHTML = html;
    }

    function renderPlugins(plugins) {
        if (!dom.pluginList) {
            return;
        }

        if (!plugins.length) {
            dom.pluginList.innerHTML = '<tr><td colspan="2" class="ta-realtime-empty">暂无插件状态数据</td></tr>';
            return;
        }

        var html = plugins.map(function (plugin) {
            var name = '未命名插件';
            var enabled = false;

            if (typeof plugin === 'string') {
                name = plugin;
            } else if (plugin && typeof plugin === 'object') {
                name = String(pickValue(plugin, ['name', 'plugin', 'id']) || name);
                var raw = pickValue(plugin, ['enabled', 'is_enabled', 'active', 'status']);
                if (typeof raw === 'boolean') {
                    enabled = raw;
                } else if (typeof raw === 'string') {
                    enabled = ['enabled', 'active', 'online', 'on', 'true', '1'].indexOf(raw.toLowerCase()) !== -1;
                } else if (typeof raw === 'number') {
                    enabled = raw === 1;
                }
            }

            var badgeClass = enabled ? 'ta-realtime-plugin-enabled' : 'ta-realtime-plugin-disabled';
            var badgeText = enabled ? '已启用' : '已禁用';
            return '<tr><td>' + escapeHtml(name) + '</td><td><span class="ta-realtime-plugin-badge ' + badgeClass + '">' + badgeText + '</span></td></tr>';
        }).join('');

        dom.pluginList.innerHTML = html;
    }

    function renderChatList(messages) {
        if (!Array.isArray(messages)) {
            return;
        }

        if (!dom.chatStream) {
            return;
        }

        if (!messages.length) {
            dom.chatStream.innerHTML = '<div class="ta-realtime-empty">暂无聊天消息</div>';
            return;
        }

        var normalized = messages.map(function (item) {
            if (!item || typeof item !== 'object') {
                return null;
            }
            var player = String(pickValue(item, ['player', 'username', 'name', 'sender']) || '未知玩家');
            var message = String(pickValue(item, ['message', 'content', 'text']) || '').trim();
            var time = pickValue(item, ['time', 'timestamp', 'created_at', 'at']);
            if (message === '') {
                return null;
            }
            return {
                player: player,
                message: message,
                time: time
            };
        }).filter(function (item) {
            return item !== null;
        });

        normalized = normalized.slice(-maxChatMessages);

        var html = normalized.map(function (item) {
            return buildChatItemHtml(item);
        }).join('');

        dom.chatStream.innerHTML = html || '<div class="ta-realtime-empty">暂无聊天消息</div>';
        dom.chatStream.scrollTop = dom.chatStream.scrollHeight;
    }

    function appendChatItem(item) {
        if (!dom.chatStream) {
            return;
        }

        if (dom.chatStream.querySelector('.ta-realtime-empty')) {
            dom.chatStream.innerHTML = '';
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = buildChatItemHtml(item);
        var chatNode = wrapper.firstElementChild;
        if (chatNode) {
            dom.chatStream.appendChild(chatNode);
        }

        while (dom.chatStream.childElementCount > maxChatMessages) {
            dom.chatStream.removeChild(dom.chatStream.firstElementChild);
        }

        dom.chatStream.scrollTop = dom.chatStream.scrollHeight;
    }

    function buildChatItemHtml(item) {
        return (
            '<div class="ta-realtime-chat-item">' +
            '<div class="ta-realtime-chat-meta">' +
            '<span class="ta-realtime-chat-player">' + escapeHtml(item.player) + '</span>' +
            '<span class="ta-realtime-chat-time">' + escapeHtml(formatDate(item.time)) + '</span>' +
            '</div>' +
            '<p class="ta-realtime-chat-message">' + escapeHtml(item.message) + '</p>' +
            '</div>'
        );
    }

    function setLastUpdated(value) {
        if (!dom.lastUpdated) {
            return;
        }
        if (value === null || value === undefined || value === '') {
            dom.lastUpdated.textContent = formatDate(Date.now());
            return;
        }
        dom.lastUpdated.textContent = formatDate(value);
    }

    function formatDate(value) {
        var dateObj = normalizeDate(value);
        if (!dateObj) {
            return '--';
        }
        return dateObj.toLocaleString('zh-CN', { hour12: false });
    }

    function normalizeDate(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }
        var num = Number(value);
        if (!Number.isNaN(num) && Number.isFinite(num)) {
            if (num < 10000000000) {
                return new Date(num * 1000);
            }
            return new Date(num);
        }
        var dateObj = new Date(String(value));
        if (Number.isNaN(dateObj.getTime())) {
            return null;
        }
        return dateObj;
    }

    function pickValue(source, keys) {
        if (!source || typeof source !== 'object') {
            return null;
        }
        for (var i = 0; i < keys.length; i += 1) {
            var key = keys[i];
            if (Object.prototype.hasOwnProperty.call(source, key) && source[key] !== null && source[key] !== undefined) {
                return source[key];
            }
        }
        return null;
    }

    function pickString(source, keys) {
        var value = pickValue(source, keys);
        if (value === null || value === undefined) {
            return null;
        }
        return String(value);
    }

    function pickNumber(source, keys) {
        var value = pickValue(source, keys);
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var num = Number(value);
        return Number.isFinite(num) ? num : null;
    }

    function formatDecimal(value, digits) {
        if (!Number.isFinite(value)) {
            return '--';
        }
        return Number(value).toFixed(digits);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildWsUrl(url, token) {
        var rawUrl = String(url || '').trim();
        if (!rawUrl) {
            return '';
        }

        var normalizedUrl = rawUrl;
        if (!/^wss?:\/\//i.test(normalizedUrl)) {
            if (normalizedUrl.charAt(0) === '/') {
                var wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
                normalizedUrl = wsProtocol + window.location.host + normalizedUrl;
            } else {
                return '';
            }
        }

        if (!token) {
            return normalizedUrl;
        }

        try {
            var urlObj = new URL(normalizedUrl);
            if (!urlObj.searchParams.has('token')) {
                urlObj.searchParams.set('token', String(token));
            }
            return urlObj.toString();
        } catch (err) {
            return normalizedUrl;
        }
    }

    function isRealtimeTabActive() {
        return !realtimeTab.classList.contains('tab-hidden');
    }

    function maybeConnectForVisibleTab() {
        if (isRealtimeTabActive()) {
            connect(false);
        }
    }

    function watchTabVisibility() {
        if (typeof MutationObserver !== 'function') {
            return;
        }

        var observer = new MutationObserver(function () {
            maybeConnectForVisibleTab();
        });

        observer.observe(realtimeTab, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    function bindRealtimeTabEvent() {
        var realtimeTabButton = document.querySelector('.admin-tab-btn[data-tab-target="tab-realtime"]');
        if (!realtimeTabButton) {
            return;
        }
        realtimeTabButton.addEventListener('click', function () {
            window.setTimeout(function () {
                maybeConnectForVisibleTab();
            }, 0);
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && isRealtimeTabActive()) {
            connect(false);
        }
    });

    window.addEventListener('beforeunload', function () {
        disconnect();
    });

    // expose helpers for manual debug in browser console
    window.AdminRealtimePanel = {
        connect: function () {
            connect(false);
        },
        reconnect: reconnect,
        disconnect: disconnect
    };

    bindRealtimeTabEvent();
    watchTabVisibility();

    if (isRealtimeTabActive()) {
        connect(false);
    } else {
        setConnectionState('disconnected', '等待打开面板');
        window.setTimeout(function () {
            maybeConnectForVisibleTab();
        }, 60);
    }
})();
