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

        var MOTD_COLOR_MAP = {
            black: '#000000',
            dark_blue: '#0000AA',
            dark_green: '#00AA00',
            dark_aqua: '#00AAAA',
            dark_red: '#AA0000',
            dark_purple: '#AA00AA',
            gold: '#FFAA00',
            gray: '#AAAAAA',
            dark_gray: '#555555',
            blue: '#5555FF',
            green: '#55FF55',
            aqua: '#55FFFF',
            red: '#FF5555',
            light_purple: '#FF55FF',
            yellow: '#FFFF55',
            white: '#FFFFFF'
        };
        var MOTD_CLASS_WHITELIST = ['motd-bold', 'motd-italic', 'motd-underlined', 'motd-strikethrough'];

        function normalizeHexColor(colorValue) {
            var text = String(colorValue || '').trim();
            if (/^#[0-9a-fA-F]{6}$/.test(text)) {
                return text.toUpperCase();
            }
            return '';
        }

        function resolveMotdColor(colorValue) {
            var text = String(colorValue || '').trim().toLowerCase();
            if (!text) {
                return '';
            }
            if (MOTD_COLOR_MAP[text]) {
                return MOTD_COLOR_MAP[text];
            }
            return normalizeHexColor(text);
        }

        function normalizeMotdLine(lineNode) {
            if (typeof lineNode === 'string') {
                var textLine = lineNode.trim();
                if (!textLine) {
                    return null;
                }
                return { clean: textLine, plain: textLine, miniMessage: '', html: '' };
            }
            if (!lineNode || typeof lineNode !== 'object') {
                return null;
            }

            var clean = String(lineNode.clean || lineNode.plain || lineNode.text || lineNode.line || lineNode.message || '').trim();
            var plain = String(lineNode.plain || lineNode.clean || lineNode.text || '').trim();
            var miniMessage = String(lineNode.miniMessage || lineNode.mini_message || lineNode.minimessage || '').trim();
            var html = String(lineNode.html || '').trim();

            if (!plain && clean) {
                plain = clean;
            }
            if (!clean && plain) {
                clean = plain;
            }

            if (!clean && !plain && !miniMessage && !html) {
                return null;
            }
            return { clean: clean, plain: plain, miniMessage: miniMessage, html: html };
        }

        function normalizeMotdPayload(motdNode) {
            if (!motdNode) {
                return null;
            }
            if (typeof motdNode === 'string') {
                var text = motdNode.trim();
                return {
                    clean: text,
                    plain: text,
                    miniMessage: '',
                    html: '',
                    lines: []
                };
            }
            if (typeof motdNode !== 'object') {
                return null;
            }

            var clean = String(motdNode.clean || motdNode.plain || motdNode.text || '').trim();
            var plain = String(motdNode.plain || motdNode.clean || motdNode.text || '').trim();
            var miniMessage = String(motdNode.miniMessage || motdNode.mini_message || motdNode.minimessage || '').trim();
            var html = String(motdNode.html || '').trim();
            var linesSource = Array.isArray(motdNode.lines) ? motdNode.lines : (Array.isArray(motdNode.raw) ? motdNode.raw : []);
            var lines = linesSource.map(normalizeMotdLine).filter(function (line) { return !!line; });

            if ((!clean && !plain) && lines.length > 0) {
                var joined = lines.map(function (line) {
                    return String(line.clean || line.plain || '').trim();
                }).filter(function (lineText) {
                    return lineText !== '';
                }).join(' ');
                if (joined) {
                    clean = joined;
                    plain = joined;
                }
            }

            if (!plain && clean) {
                plain = clean;
            }
            if (!clean && plain) {
                clean = plain;
            }

            return {
                clean: clean,
                plain: plain,
                miniMessage: miniMessage,
                html: html,
                lines: lines
            };
        }

        function splitMotdLines(rawText) {
            return String(rawText == null ? '' : rawText)
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<newline>/gi, '\n')
                .replace(/\r\n?/g, '\n')
                .split('\n');
        }

        function firstDefinedValue(node, keys) {
            if (!node || typeof node !== 'object' || !Array.isArray(keys)) {
                return undefined;
            }

            for (var i = 0; i < keys.length; i += 1) {
                var key = keys[i];
                if (Object.prototype.hasOwnProperty.call(node, key) && node[key] !== undefined && node[key] !== null) {
                    return node[key];
                }
            }

            return undefined;
        }

        function normalizePlayerCount(value) {
            if (value === undefined || value === null || value === '') {
                return null;
            }

            var parsed = Number(value);
            if (!Number.isFinite(parsed)) {
                return null;
            }

            return Math.max(0, Math.floor(parsed));
        }

        function normalizePlayersMaxValue(value) {
            if (value === undefined || value === null) {
                return null;
            }

            if (typeof value === 'string') {
                var text = value.trim();
                if (!text) {
                    return null;
                }

                var numericText = normalizePlayerCount(text);
                return numericText !== null ? numericText : text;
            }

            var parsed = normalizePlayerCount(value);
            return parsed !== null ? parsed : null;
        }

        function applyMotdPlaceholders(textValue, context) {
            var text = String(textValue == null ? '' : textValue);
            if (!text) {
                return '';
            }

            var onlinePlayers = normalizePlayerCount(context && context.onlinePlayers);
            var maxPlayers = normalizePlayersMaxValue(context && context.maxPlayers);
            var replacements = {
                '<online>': String(onlinePlayers !== null ? onlinePlayers : 0),
                '<players_online>': String(onlinePlayers !== null ? onlinePlayers : 0),
                '%online%': String(onlinePlayers !== null ? onlinePlayers : 0),
                '%players_online%': String(onlinePlayers !== null ? onlinePlayers : 0),
                '{online}': String(onlinePlayers !== null ? onlinePlayers : 0),
                '{players_online}': String(onlinePlayers !== null ? onlinePlayers : 0),
                '<max>': String(maxPlayers !== null ? maxPlayers : '?'),
                '<players_max>': String(maxPlayers !== null ? maxPlayers : '?'),
                '%max%': String(maxPlayers !== null ? maxPlayers : '?'),
                '%players_max%': String(maxPlayers !== null ? maxPlayers : '?'),
                '{max}': String(maxPlayers !== null ? maxPlayers : '?'),
                '{players_max}': String(maxPlayers !== null ? maxPlayers : '?')
            };

            Object.keys(replacements).forEach(function (placeholder) {
                if (text.indexOf(placeholder) !== -1) {
                    text = text.split(placeholder).join(replacements[placeholder]);
                }
            });

            return text;
        }

        function applyMiniMessageTag(rawTag, state) {
            var content = String(rawTag || '').slice(1, -1).trim();
            if (!content) {
                return;
            }

            var lower = content.toLowerCase();
            if (lower === 'reset') {
                state.color = '';
                state.bold = false;
                state.italic = false;
                state.underlined = false;
                state.strikethrough = false;
                state.gradient = null;
                return;
            }

            if (lower.charAt(0) === '/') {
                var closeName = lower.slice(1).trim();
                if (closeName === 'bold' || closeName === 'b') {
                    state.bold = false;
                    return;
                }
                if (closeName === 'italic' || closeName === 'i') {
                    state.italic = false;
                    return;
                }
                if (closeName === 'underlined' || closeName === 'u') {
                    state.underlined = false;
                    return;
                }
                if (closeName === 'strikethrough' || closeName === 'st') {
                    state.strikethrough = false;
                    return;
                }
                if (closeName === 'gradient') {
                    state.gradient = null;
                    return;
                }
                if (closeName === 'color') {
                    state.color = '';
                    return;
                }
                if (MOTD_COLOR_MAP[closeName] || /^#[0-9a-f]{6}$/i.test(closeName)) {
                    state.color = '';
                    state.gradient = null;
                    return;
                }
                return;
            }

            if (lower === 'bold' || lower === 'b') {
                state.bold = true;
                return;
            }
            if (lower === 'italic' || lower === 'i') {
                state.italic = true;
                return;
            }
            if (lower === 'underlined' || lower === 'u') {
                state.underlined = true;
                return;
            }
            if (lower === 'strikethrough' || lower === 'st') {
                state.strikethrough = true;
                return;
            }

            var colorTagMatch = lower.match(/^color\s*:\s*(#[0-9a-f]{6})$/i);
            if (colorTagMatch) {
                state.color = normalizeHexColor(colorTagMatch[1]);
                state.gradient = null;
                return;
            }

            var gradientTagMatch = lower.match(/^gradient\s*:\s*(#[0-9a-f]{6})\s*:\s*(#[0-9a-f]{6})$/i);
            if (gradientTagMatch) {
                var start = normalizeHexColor(gradientTagMatch[1]);
                var end = normalizeHexColor(gradientTagMatch[2]);
                if (start && end) {
                    state.gradient = { start: start, end: end };
                }
                return;
            }

            var inlineHex = normalizeHexColor(lower);
            if (inlineHex) {
                state.color = inlineHex;
                state.gradient = null;
                return;
            }

            var namedColor = resolveMotdColor(lower);
            if (namedColor) {
                state.color = namedColor;
                state.gradient = null;
            }
        }

        function parseMiniMessageSegments(textValue) {
            var text = String(textValue == null ? '' : textValue);
            var tagRegex = /<[^<>]*>/g;
            var segments = [];
            var state = {
                color: '',
                bold: false,
                italic: false,
                underlined: false,
                strikethrough: false,
                gradient: null
            };
            var lastIndex = 0;
            var match;

            while ((match = tagRegex.exec(text)) !== null) {
                if (match.index > lastIndex) {
                    segments.push({
                        text: text.slice(lastIndex, match.index),
                        state: Object.assign({}, state, { gradient: state.gradient ? Object.assign({}, state.gradient) : null })
                    });
                }

                applyMiniMessageTag(match[0], state);
                lastIndex = match.index + match[0].length;
            }

            if (lastIndex < text.length) {
                segments.push({
                    text: text.slice(lastIndex),
                    state: Object.assign({}, state, { gradient: state.gradient ? Object.assign({}, state.gradient) : null })
                });
            }

            return segments;
        }

        function parseHexColor(hex) {
            var normalized = normalizeHexColor(hex);
            if (!normalized) {
                return null;
            }
            return {
                r: parseInt(normalized.slice(1, 3), 16),
                g: parseInt(normalized.slice(3, 5), 16),
                b: parseInt(normalized.slice(5, 7), 16)
            };
        }

        function toHexChannel(value) {
            var bounded = Math.max(0, Math.min(255, Math.round(value)));
            var hex = bounded.toString(16).toUpperCase();
            return hex.length === 1 ? '0' + hex : hex;
        }

        function interpolateHexColor(startHex, endHex, ratio) {
            var start = parseHexColor(startHex);
            var end = parseHexColor(endHex);
            if (!start || !end) {
                return '';
            }
            var r = start.r + (end.r - start.r) * ratio;
            var g = start.g + (end.g - start.g) * ratio;
            var b = start.b + (end.b - start.b) * ratio;
            return '#' + toHexChannel(r) + toHexChannel(g) + toHexChannel(b);
        }

        function buildMotdSpanAttrs(state, overrideColor) {
            var classes = [];
            if (state.bold) {
                classes.push('motd-bold');
            }
            if (state.italic) {
                classes.push('motd-italic');
            }
            if (state.underlined) {
                classes.push('motd-underlined');
            }
            if (state.strikethrough) {
                classes.push('motd-strikethrough');
            }

            var color = normalizeHexColor(overrideColor || '') || resolveMotdColor(state.color || '');
            var attrs = '';
            if (classes.length > 0) {
                attrs += ' class="' + classes.join(' ') + '"';
            }
            if (color) {
                attrs += ' style="color:' + color + '"';
            }
            return attrs;
        }

        function renderMotdSpan(text, state, overrideColor) {
            var safeText = escapeHtml(text);
            if (safeText === '') {
                return '';
            }

            var attrs = buildMotdSpanAttrs(state, overrideColor);
            if (!attrs) {
                return safeText;
            }
            return '<span' + attrs + '>' + safeText + '</span>';
        }

        function renderGradientMotdText(text, state) {
            var gradient = state.gradient;
            if (!gradient) {
                return renderMotdSpan(text, state, '');
            }

            var chars = Array.from(String(text == null ? '' : text));
            var output = '';
            for (var i = 0; i < chars.length; i += 1) {
                var ratio = chars.length <= 1 ? 0 : (i / (chars.length - 1));
                var color = interpolateHexColor(gradient.start, gradient.end, ratio);
                output += renderMotdSpan(chars[i], state, color);
            }
            return output;
        }

        function renderMiniMessageLine(textLine, context) {
            var segments = parseMiniMessageSegments(applyMotdPlaceholders(textLine, context));
            var html = '';

            segments.forEach(function (segment) {
                if (!segment.text) {
                    return;
                }
                if (segment.state.gradient) {
                    html += renderGradientMotdText(segment.text, segment.state);
                    return;
                }
                html += renderMotdSpan(segment.text, segment.state, '');
            });

            return html;
        }

        function renderTextLines(lines) {
            if (!Array.isArray(lines) || lines.length === 0) {
                return '';
            }
            return lines.map(function (line) {
                return '<div class="motd-line">' + escapeHtml(line) + '</div>';
            }).join('');
        }

        function extractSafeColorFromStyle(styleText) {
            var text = String(styleText || '');
            var match = text.match(/(?:^|;)\s*color\s*:\s*([^;]+)/i);
            if (!match) {
                return '';
            }

            var rawColor = String(match[1] || '').trim();
            var safeHex = normalizeHexColor(rawColor) || resolveMotdColor(rawColor);
            if (safeHex) {
                return safeHex;
            }

            var rgbMatch = rawColor.match(/^rgb\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*\)$/i);
            if (!rgbMatch) {
                return '';
            }

            var r = Number(rgbMatch[1]);
            var g = Number(rgbMatch[2]);
            var b = Number(rgbMatch[3]);
            if (!Number.isFinite(r) || !Number.isFinite(g) || !Number.isFinite(b)) {
                return '';
            }
            if (r < 0 || r > 255 || g < 0 || g > 255 || b < 0 || b > 255) {
                return '';
            }
            return '#' + toHexChannel(r) + toHexChannel(g) + toHexChannel(b);
        }

        function sanitizeMotdHtml(htmlText) {
            var raw = String(htmlText == null ? '' : htmlText).trim();
            if (!raw) {
                return '';
            }
            if (typeof DOMParser === 'undefined') {
                return '';
            }

            var parser = new DOMParser();
            var doc = parser.parseFromString('<div>' + raw + '</div>', 'text/html');
            var root = doc && doc.body ? doc.body.firstElementChild : null;
            if (!root) {
                return '';
            }
            var lineBreakToken = '__MOTD_LINE_BREAK__';

            function sanitizeNode(node) {
                if (!node) {
                    return '';
                }
                if (node.nodeType === 3) {
                    return escapeHtml(node.textContent || '');
                }
                if (node.nodeType !== 1) {
                    return '';
                }

                var tag = String(node.tagName || '').toLowerCase();
                if (tag === 'br') {
                    return lineBreakToken;
                }

                var childrenHtml = '';
                Array.prototype.forEach.call(node.childNodes || [], function (child) {
                    childrenHtml += sanitizeNode(child);
                });

                if (tag === 'span') {
                    var classes = String(node.getAttribute('class') || '')
                        .split(/\s+/)
                        .filter(function (cls) {
                            return MOTD_CLASS_WHITELIST.indexOf(cls) !== -1;
                        });
                    var color = extractSafeColorFromStyle(node.getAttribute('style') || '');

                    var attrs = '';
                    if (classes.length > 0) {
                        attrs += ' class="' + classes.join(' ') + '"';
                    }
                    if (color) {
                        attrs += ' style="color:' + color + '"';
                    }
                    return '<span' + attrs + '>' + childrenHtml + '</span>';
                }

                if (tag === 'div' || tag === 'p') {
                    return lineBreakToken + childrenHtml + lineBreakToken;
                }

                return childrenHtml;
            }

            var sanitized = '';
            Array.prototype.forEach.call(root.childNodes || [], function (child) {
                sanitized += sanitizeNode(child);
            });

            var lineParts = sanitized.split(lineBreakToken);
            while (lineParts.length > 0 && lineParts[0] === '') {
                lineParts.shift();
            }
            while (lineParts.length > 0 && lineParts[lineParts.length - 1] === '') {
                lineParts.pop();
            }
            if (lineParts.length === 0) {
                return '';
            }

            return lineParts.map(function (lineHtml) {
                return '<div class="motd-line">' + lineHtml + '</div>';
            }).join('');
        }

        function renderMotdHtml(motdNode, context) {
            var motd = normalizeMotdPayload(motdNode);
            if (!motd) {
                return '<div class="motd-line">--</div>';
            }

            var hasMiniMessageLines = motd.lines.some(function (line) {
                return String(line.miniMessage || '').trim() !== '';
            });
            if (hasMiniMessageLines) {
                return motd.lines.map(function (line) {
                    var mini = String(line.miniMessage || '').trim();
                    var fallbackText = applyMotdPlaceholders(String(line.clean || line.plain || '').trim(), context);
                    var rendered = mini ? renderMiniMessageLine(mini, context) : escapeHtml(fallbackText);
                    return '<div class="motd-line">' + rendered + '</div>';
                }).join('');
            }

            if (motd.miniMessage) {
                var miniLines = splitMotdLines(applyMotdPlaceholders(motd.miniMessage, context));
                return miniLines.map(function (lineText) {
                    return '<div class="motd-line">' + renderMiniMessageLine(lineText, context) + '</div>';
                }).join('');
            }

            if (motd.html) {
                var sanitizedHtml = sanitizeMotdHtml(applyMotdPlaceholders(motd.html, context));
                if (sanitizedHtml) {
                    return sanitizedHtml;
                }
            }

            var textFallback = applyMotdPlaceholders(String(motd.clean || motd.plain || '').trim(), context);
            if (!textFallback) {
                return '<div class="motd-line">--</div>';
            }

            return renderTextLines(splitMotdLines(textFallback)) || '<div class="motd-line">--</div>';
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

        function normalizePlayersEndpointPayload(payload) {
            var source = payload;
            if (Array.isArray(source)) {
                source = { list: source };
            }
            if (!source || typeof source !== 'object') {
                source = {};
            }

            var nestedPlayers = source.players && typeof source.players === 'object' && !Array.isArray(source.players)
                ? source.players
                : null;

            var listSource = [];
            if (Array.isArray(source.list)) {
                listSource = source.list;
            } else if (Array.isArray(source.sample)) {
                listSource = source.sample;
            } else if (Array.isArray(source.players)) {
                listSource = source.players;
            } else if (nestedPlayers && Array.isArray(nestedPlayers.list)) {
                listSource = nestedPlayers.list;
            } else if (nestedPlayers && Array.isArray(nestedPlayers.sample)) {
                listSource = nestedPlayers.sample;
            } else if (nestedPlayers && Array.isArray(nestedPlayers.players)) {
                listSource = nestedPlayers.players;
            }

            var list = normalizePlayerList(listSource);
            var online = normalizePlayerCount(firstDefinedValue(source, ['online', 'onlinePlayers', 'playerCount', 'player_count']));
            if (online === null && nestedPlayers) {
                online = normalizePlayerCount(firstDefinedValue(nestedPlayers, ['online', 'onlinePlayers', 'playerCount', 'player_count']));
            }
            if (online === null) {
                online = list.length;
            }
            if (online === 0 && list.length > 0) {
                online = list.length;
            }

            var max = normalizePlayersMaxValue(firstDefinedValue(source, ['max', 'maxPlayers', 'playerMax', 'player_max']));
            if (max === null && nestedPlayers) {
                max = normalizePlayersMaxValue(firstDefinedValue(nestedPlayers, ['max', 'maxPlayers', 'playerMax', 'player_max']));
            }

            return {
                online: online,
                max: max,
                list: list,
                players: list
            };
        }

        function extractPlayersPayload(payload) {
            var candidate = unwrapApiPayload(payload);
            if (Array.isArray(candidate)) {
                return normalizePlayersEndpointPayload({ list: candidate });
            }
            if (!candidate || typeof candidate !== 'object') {
                return normalizePlayersEndpointPayload({});
            }

            if (candidate.players && typeof candidate.players === 'object' && !Array.isArray(candidate.players)) {
                var hasTopLevelPlayersShape = (
                    candidate.online !== undefined
                    || candidate.max !== undefined
                    || candidate.onlinePlayers !== undefined
                    || candidate.maxPlayers !== undefined
                    || Array.isArray(candidate.list)
                    || Array.isArray(candidate.sample)
                );
                if (!hasTopLevelPlayersShape) {
                    return normalizePlayersEndpointPayload(candidate.players);
                }
            }

            return normalizePlayersEndpointPayload(candidate);
        }

        function getStatusOnlinePlayers(statusPayload, playersPayload) {
            var normalizedPlayers = playersPayload || extractPlayersPayload(statusPayload);
            var onlinePlayers = normalizedPlayers.online;

            if (statusPayload && statusPayload.stats && typeof statusPayload.stats === 'object') {
                var statsOnline = normalizePlayerCount(firstDefinedValue(
                    statusPayload.stats,
                    ['onlinePlayers', 'online_players', 'online', 'playerCount', 'player_count']
                ));
                if (statsOnline !== null) {
                    onlinePlayers = statsOnline;
                }
            }

            if ((onlinePlayers === null || onlinePlayers === 0) && normalizedPlayers.list.length > 0) {
                onlinePlayers = normalizedPlayers.list.length;
            }

            return onlinePlayers === null ? 0 : onlinePlayers;
        }

        function getStatusMaxPlayers(statusPayload, playersPayload) {
            var normalizedPlayers = playersPayload || extractPlayersPayload(statusPayload);
            var maxPlayers = normalizedPlayers.max;

            if (statusPayload && statusPayload.stats && typeof statusPayload.stats === 'object') {
                var statsMax = normalizePlayersMaxValue(firstDefinedValue(
                    statusPayload.stats,
                    ['maxPlayers', 'max_players', 'max', 'playerMax', 'player_max']
                ));
                if (statsMax !== null) {
                    maxPlayers = statsMax;
                }
            }

            return maxPlayers !== null ? maxPlayers : '?';
        }

        function needsPlayersFallback(statusPayload) {
            if (!statusPayload || typeof statusPayload !== 'object') {
                return false;
            }

            var playersPayload = extractPlayersPayload(statusPayload);
            var onlinePlayers = getStatusOnlinePlayers(statusPayload, playersPayload);
            return onlinePlayers > 0 && playersPayload.list.length === 0;
        }

        function mergePlayersFallbackIntoStatus(statusPayload, playersPayload) {
            var normalizedPlayers = normalizePlayersEndpointPayload(playersPayload);
            var patch = { players: {} };
            var currentOnlinePlayers = getStatusOnlinePlayers(statusPayload);
            var currentMaxPlayers = getStatusMaxPlayers(statusPayload);

            if (normalizedPlayers.list.length > 0) {
                patch.players.list = normalizedPlayers.list;
            }
            if ((currentOnlinePlayers === 0 || currentOnlinePlayers === null) && normalizedPlayers.online > 0) {
                patch.players.online = normalizedPlayers.online;
            }
            if ((currentMaxPlayers === '?' || currentMaxPlayers === null) && normalizedPlayers.max !== null) {
                patch.players.max = normalizedPlayers.max;
            }

            if (Object.keys(patch.players).length === 0) {
                return statusPayload;
            }

            return mergeStatusPatch(statusPayload, patch);
        }

        function renderPlayersListHtml(options) {
            var normalizedPlayers = normalizePlayerList((options && options.players) || []);
            var onlinePlayers = normalizePlayerCount(options && options.onlinePlayers);

            if ((onlinePlayers === null || onlinePlayers === 0) && normalizedPlayers.length > 0) {
                onlinePlayers = normalizedPlayers.length;
            }

            if (normalizedPlayers.length > 0) {
                return normalizedPlayers.map(function (player) {
                    return '<div class="player-item">' + escapeHtml(player.name_clean) + '</div>';
                }).join('');
            }

            if (onlinePlayers !== null && onlinePlayers > 0) {
                return '<div class="no-players">Player list unavailable (' + escapeHtml(String(onlinePlayers)) + ' online)</div>';
            }

            return '<div class="no-players">No players online</div>';
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

            var playersPayload = extractPlayersPayload(data);
            var onlinePlayers = getStatusOnlinePlayers(data, playersPayload);
            var maxPlayers = getStatusMaxPlayers(data, playersPayload);
            var tpsValue = null;

            if (data.stats && typeof data.stats === 'object') {
                if (data.stats.tps !== undefined && data.stats.tps !== null) {
                    tpsValue = Number(data.stats.tps);
                }
            }
            if (tpsValue === null && data.tps !== undefined && data.tps !== null) {
                tpsValue = Number(data.tps);
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
                motdDisplay.innerHTML = renderMotdHtml(data.motd, {
                    onlinePlayers: onlinePlayers,
                    maxPlayers: maxPlayers
                });
            }

            if (playersList) {
                playersList.innerHTML = renderPlayersListHtml({
                    onlinePlayers: onlinePlayers,
                    players: playersPayload.list
                });
            }
        }
        window.updateServerStatusUI = updateServerStatusUI;

        async function fetchPlayersFallback(statusData, timestamp) {
            if (!needsPlayersFallback(statusData)) {
                return statusData;
            }

            try {
                var playersResponse = await fetch('/api/players?t=' + timestamp, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!playersResponse.ok) {
                    return statusData;
                }

                var playersData = extractPlayersPayload(await playersResponse.json());
                return mergePlayersFallbackIntoStatus(statusData, playersData);
            } catch (error) {
                console.warn('Fetch players fallback failed', error);
                return statusData;
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
                        internalStatusData = await fetchPlayersFallback(internalStatusData, timestamp);
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

            if (internalStatusData) {
                internalStatusData = await fetchPlayersFallback(internalStatusData, timestamp);
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
