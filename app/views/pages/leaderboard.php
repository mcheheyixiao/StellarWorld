<?php
/** @var array $leaderboards */
/** @var string|null $lastUpdate */
/** @var string|null $leaderboardError */

$leaderboards = $leaderboards ?? [];
$lastUpdate = $lastUpdate ?? null;
$leaderboardError = $leaderboardError ?? null;
$fallbackAvatar = 'https://minotar.net/helm/MHF_Steve/32.png';
?>

<style>
.leaderboard-tabs .tabs-container {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.22);
}
.leaderboard-tabs .tab-btn {
  flex-shrink: 0;
  padding: 0.5rem 1rem;
  border: 1px solid rgba(148, 163, 184, 0.25);
  border-radius: 0.75rem;
  background: rgba(15, 23, 42, 0.78);
  color: #cbd5e1;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.2s, background 0.2s;
}
.leaderboard-tabs .tab-btn:hover {
  background: rgba(14, 165, 233, 0.2);
  color: #a5f3fc;
}
.leaderboard-tabs .tab-btn.active {
  background: rgba(14, 165, 233, 0.25);
  color: #a5f3fc;
  font-weight: 600;
  border-color: rgba(34, 211, 238, 0.45);
}
.leaderboard-tabs .tab-content {
  display: none;
}
.leaderboard-tabs .tab-content.active {
  display: block;
  animation: tabFadeIn 0.3s var(--ease-smooth);
}
@keyframes tabFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.leaderboard-tabs .table-responsive {
  width: 100%;
  overflow-x: auto;
}
.leaderboard-tabs .table {
  width: 100%;
  min-width: 360px;
  border-collapse: collapse;
}
.leaderboard-tabs .table th {
  text-align: left;
  padding: 0.75rem 0.5rem;
  font-size: 0.85rem;
  color: #94a3b8;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}
.leaderboard-tabs .table td {
  padding: 0.55rem 0.5rem;
  font-size: 0.9rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  color: #e2e8f0;
}
.leaderboard-tabs .table .player-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.leaderboard-tabs .table .player-link:hover {
  color: #67e8f9 !important;
}
.leaderboard-tabs .table .rank-1 { color: #d4af37; font-weight: 600; }
.leaderboard-tabs .table .rank-2 { color: #c0c0c0; font-weight: 600; }
.leaderboard-tabs .table .rank-3 { color: #cd7f32; font-weight: 600; }
.leaderboard-search-wrap {
  margin-bottom: 1.25rem;
}
.leaderboard-search-wrap label {
  display: block;
  font-size: 0.85rem;
  color: #94a3b8;
  margin-bottom: 0.35rem;
}
.leaderboard-search-wrap input {
  width: 100%;
  max-width: 320px;
  padding: 0.55rem 0.75rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: rgba(15, 23, 42, 0.72);
  color: #e2e8f0;
}
.leaderboard-search-hint {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-top: 0.35rem;
}

.leaderboard-tabs .table .player-link {
  color: #e2e8f0;
}
.leaderboard-tabs .lb-col-score {
  text-align: right;
  color: #94a3b8;
}
.leaderboard-tabs .lb-empty-msg {
  text-align: center;
  padding: 1.5rem;
  color: #94a3b8;
}

.leaderboard-error-banner {
  margin: 0 0 1rem 0;
  padding: 0.75rem 1rem;
  border: 1px solid rgba(255, 120, 120, 0.35);
  background: rgba(255, 120, 120, 0.08);
  border-radius: 10px;
  color: #e2e8f0;
}
[data-theme="light"] .leaderboard-error-banner {
  background: rgba(254, 226, 226, 0.85);
  border-color: rgba(220, 38, 38, 0.35);
  color: #7f1d1d;
}
[data-theme="light"] .leaderboard-error-banner > div:last-child {
  color: #991b1b !important;
}

/* Light theme: this page uses its own dark-tinted components; override explicitly. */
[data-theme="light"] .leaderboard-tabs .tabs-container {
  border-bottom-color: rgba(148, 163, 184, 0.35);
}
[data-theme="light"] .leaderboard-tabs .tab-btn {
  background: rgba(255, 255, 255, 0.92);
  color: #334155;
  border-color: rgba(148, 163, 184, 0.4);
}
[data-theme="light"] .leaderboard-tabs .tab-btn:hover {
  background: rgba(224, 242, 254, 0.95);
  color: #0e7490;
}
[data-theme="light"] .leaderboard-tabs .tab-btn.active {
  background: rgba(186, 230, 253, 0.95);
  color: #0c4a6e;
  border-color: rgba(14, 165, 233, 0.55);
}
[data-theme="light"] .leaderboard-tabs .table th {
  color: #64748b;
  border-bottom-color: rgba(148, 163, 184, 0.28);
}
[data-theme="light"] .leaderboard-tabs .table td {
  color: #0f172a;
  border-bottom-color: rgba(148, 163, 184, 0.2);
}
[data-theme="light"] .leaderboard-tabs .table .player-link,
[data-theme="light"] .leaderboard-tabs .table .player-link span {
  color: #0f172a !important;
}
[data-theme="light"] .leaderboard-tabs .table .player-link:hover {
  color: #0369a1 !important;
}
[data-theme="light"] .leaderboard-tabs .table .rank-2 {
  color: #64748b;
}
[data-theme="light"] .leaderboard-tabs .lb-col-score {
  color: #475569;
}
[data-theme="light"] .leaderboard-tabs .lb-empty-msg {
  color: #64748b;
}
[data-theme="light"] .leaderboard-search-wrap label,
[data-theme="light"] .leaderboard-search-hint {
  color: #64748b;
}
[data-theme="light"] .leaderboard-search-wrap input {
  background: rgba(255, 255, 255, 0.95);
  color: #0f172a;
  border-color: rgba(148, 163, 184, 0.45);
}
[data-theme="light"] .leaderboard-search-wrap input::placeholder {
  color: #94a3b8;
}
</style>

<div class="page-container">
    <div class="mc-glass-card p-6 md:p-8">
        <div class="card-header" style="margin-bottom: 1.5rem;">
            <h1 class="text-fusion-pixel text-2xl text-white md:text-3xl" style="margin:0 0 .5rem 0;">服务器排行榜</h1>
            <p style="color:#94a3b8;margin:0;font-size:.9rem;">
                统计玩家数据 · 选项卡切换查看
            </p>
            <?php if ($lastUpdate !== null && $lastUpdate !== ''): ?>
                <p style="color:#94a3b8;margin:.5rem 0 0 0;font-size:.85rem;">
                    数据最后更新时间：<?php echo htmlspecialchars($lastUpdate, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($leaderboards)): ?>
            <div class="leaderboard-search-wrap">
                <label for="leaderboard-search-input">玩家名模糊搜索（当前选项卡）</label>
                <input type="search" id="leaderboard-search-input" placeholder="输入游戏名，实时匹配…" autocomplete="off">
            </div>
            <?php endif; ?>
        </div>

        <?php if ($leaderboardError): ?>
            <div class="leaderboard-error-banner">
                <div style="font-weight:600;margin-bottom:.25rem;">排行榜暂不可用</div>
                <div style="color:#94a3b8;font-size:.9rem;">
                    数据表可能尚未创建或未导入到当前数据库。错误信息：
                    <code style="word-break:break-all;"><?php echo htmlspecialchars((string)$leaderboardError, ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($leaderboards)): ?>
            <p style="color:#94a3b8; text-align:center; padding: 2rem 0;">
                暂无排行榜数据，请稍后再来看。
            </p>
        <?php else: ?>
            <div class="leaderboard-tabs">
                <div class="tabs-container">
                    <?php foreach ($leaderboards as $index => $board): ?>
                        <?php $tabId = 'tab-' . htmlspecialchars($board['key'] ?? ('board-' . $index), ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-target="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($board['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($leaderboards as $index => $board): ?>
                    <?php $tabId = 'tab-' . htmlspecialchars($board['key'] ?? ('board-' . $index), ENT_QUOTES, 'UTF-8'); ?>
                    <div id="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>" class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>" data-board-key="<?php echo htmlspecialchars((string)($board['key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (empty($board['entries'])): ?>
                            <p style="color:var(--color-text-light);font-size:.9rem;margin:0;text-align:center;padding:2rem 0;">
                                暂无数据
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width:4rem;">排名</th>
                                            <th>玩家</th>
                                            <th style="text-align:right;">分数</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lb-tbody-<?php echo htmlspecialchars((string)($board['key'] ?? 'board'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php
                                        $rank = 1;
                                        foreach ($board['entries'] as $entry):
                                            $username = (string)($entry['username'] ?? '');
                                            $avatarUrl = 'https://minotar.net/helm/' . rawurlencode($username) . '/32.png';
                                            $value = $entry['value'] ?? 0;
                                            $unit = (string)($board['unit'] ?? '');
                                            $rankClass = $rank <= 3 ? 'rank-' . $rank : '';
                                        ?>
                                            <tr>
                                                <td class="<?php echo htmlspecialchars($rankClass, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo (int)$rank; ?></td>
                                                <td>
                                                    <div class="player-cell">
                                                        <a
                                                            href="/player?username=<?php echo htmlspecialchars(rawurlencode($username), ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="player-link"
                                                            style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: color 0.2s;"
                                                        >
                                                            <img
                                                                src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                                alt="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?> 的头像"
                                                                width="32"
                                                                height="32"
                                                                style="border-radius:4px;image-rendering:pixelated;background:#1e1e1e;"
                                                                onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($fallbackAvatar, ENT_QUOTES, 'UTF-8'); ?>';"
                                                            >
                                                            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">
                                                                <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="lb-col-score">
                                                    <?php
                                                    $fmt = $board['format'] ?? 'int';
                                                    if ($fmt === 'float1') {
                                                        echo number_format((float)$value, 1);
                                                    } elseif ($fmt === 'float2') {
                                                        echo number_format((float)$value, 2);
                                                    } else {
                                                        echo number_format((int)$value);
                                                    }
                                                    ?><?php if ($unit !== ''): ?><?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php
                                            $rank++;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
            (function() {
                var container = document.querySelector('.leaderboard-tabs');
                if (!container) return;
                var buttons = container.querySelectorAll('.tab-btn');
                var contents = container.querySelectorAll('.tab-content');
                var boardsMeta = <?php echo json_encode(array_values(array_map(static function (array $b): array {
                    return [
                        'key' => (string)($b['key'] ?? ''),
                        'unit' => (string)($b['unit'] ?? ''),
                        'format' => (string)($b['format'] ?? 'int'),
                    ];
                }, $leaderboards)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

                function getActiveBoardKey() {
                    var active = container.querySelector('.tab-content.active');
                    return active ? (active.getAttribute('data-board-key') || '') : '';
                }

                function metaForKey(key) {
                    for (var i = 0; i < boardsMeta.length; i++) {
                        if (boardsMeta[i].key === key) return boardsMeta[i];
                    }
                    return { unit: '', format: 'int' };
                }

                function formatValue(fmt, val, unit) {
                    var n = Number(val);
                    if (fmt === 'float1') return n.toFixed(1) + unit;
                    if (fmt === 'float2') return n.toFixed(2) + unit;
                    return Math.round(n).toLocaleString('zh-CN') + unit;
                }

                function escHtml(s) {
                    return String(s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function buildRow(entry, rank, boardKey) {
                    var m = metaForKey(boardKey);
                    var username = entry.username || '';
                    var avatarUrl = 'https://minotar.net/helm/' + encodeURIComponent(username) + '/32.png';
                    var fallbackAvatar = '<?php echo htmlspecialchars($fallbackAvatar, ENT_QUOTES, 'UTF-8'); ?>';
                    var displayRank = typeof entry.rank === 'number' ? entry.rank : rank;
                    var rankClass = displayRank <= 3 ? 'rank-' + displayRank : '';
                    var valStr = formatValue(m.format, entry.value, m.unit || '');
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="' + escHtml(rankClass) + '">#' + displayRank + '</td>'
                        + '<td><div class="player-cell">'
                        + '<a href="/player?username=' + encodeURIComponent(username) + '" class="player-link" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: color 0.2s;">'
                        + '<img src="' + escHtml(avatarUrl) + '" alt="" width="32" height="32" style="border-radius:4px;image-rendering:pixelated;background:#1e1e1e;" onerror="this.onerror=null;this.src=\'' + fallbackAvatar + '\'">'
                        + '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">' + escHtml(username) + '</span>'
                        + '</a></div></td>'
                        + '<td class="lb-col-score">' + escHtml(valStr) + '</td>';
                    return tr;
                }

                var searchInput = document.getElementById('leaderboard-search-input');
                var searchTimer = null;

                function snapshotDefaultBodies() {
                    var map = {};
                    boardsMeta.forEach(function(b) {
                        var tb = document.getElementById('lb-tbody-' + b.key);
                        if (tb) map[b.key] = tb.innerHTML;
                    });
                    return map;
                }
                var defaultHtml = snapshotDefaultBodies();

                function runSearch(q) {
                    var key = getActiveBoardKey();
                    var tb = document.getElementById('lb-tbody-' + key);
                    if (!tb) return;
                    if (!q || !q.trim()) {
                        if (defaultHtml[key]) tb.innerHTML = defaultHtml[key];
                        return;
                    }
                    fetch('/api/leaderboard/search?board=' + encodeURIComponent(key) + '&q=' + encodeURIComponent(q.trim()))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data || !data.success || !Array.isArray(data.results)) return;
                            tb.innerHTML = '';
                            if (data.results.length === 0) {
                                var empty = document.createElement('tr');
                                empty.innerHTML = '<td colspan="3" class="lb-empty-msg">无匹配玩家</td>';
                                tb.appendChild(empty);
                                return;
                            }
                            data.results.forEach(function(entry, idx) {
                                tb.appendChild(buildRow(entry, idx + 1, key));
                            });
                        })
                        .catch(function() {});
                }

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimer);
                        var v = searchInput.value;
                        searchTimer = setTimeout(function() { runSearch(v); }, 280);
                    });
                }

                buttons.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var targetId = btn.getAttribute('data-target');
                        if (!targetId) return;
                        buttons.forEach(function(b) { b.classList.remove('active'); });
                        contents.forEach(function(c) { c.classList.remove('active'); });
                        btn.classList.add('active');
                        var target = document.getElementById(targetId);
                        if (target) target.classList.add('active');
                        if (searchInput && (!searchInput.value || !searchInput.value.trim())) {
                            var k = target ? (target.getAttribute('data-board-key') || '') : '';
                            var tb = k ? document.getElementById('lb-tbody-' + k) : null;
                            if (tb && defaultHtml[k]) tb.innerHTML = defaultHtml[k];
                        } else if (searchInput) {
                            runSearch(searchInput.value);
                        }
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>
