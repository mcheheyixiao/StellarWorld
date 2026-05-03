<?php
/** @var string $username */
/** @var bool $playerFound */
/** @var string|int $playerId */
/** @var int $coins */
/** @var string $mcUsername */
/** @var string $skinRenderName */
/** @var string $skinBodyUrl */
/** @var string $skinRawUrl */
/** @var bool $skinUseProxy */
/** @var array<string,mixed> $statsData */
/** @var array<int,array<string,mixed>> $timelineEvents */

$username = trim((string)($username ?? ''));
$playerFound = (bool)($playerFound ?? false);
$playerId = trim((string)($playerId ?? ''));
$playerIdDisplay = $playerId !== '' ? $playerId : '—';
$coins = (int)($coins ?? 0);
$mcUsername = trim((string)($mcUsername ?? ''));
$skinRenderName = trim((string)($skinRenderName ?? 'MHF_Steve'));
$skinBodyUrl = trim((string)($skinBodyUrl ?? 'https://minotar.net/armor/body/MHF_Steve/300.png'));
$skinRawUrl = trim((string)($skinRawUrl ?? 'https://crafthead.net/skin/MHF_Steve'));
$skinUseProxy = (bool)($skinUseProxy ?? false);
$statsData = is_array($statsData ?? null) ? $statsData : [];
$timelineEvents = is_array($timelineEvents ?? null) ? $timelineEvents : [];
$displayName = $username !== '' ? $username : 'Steve';

$readStat = static function (array $source, string $key): float {
    $value = $source[$key] ?? 0;
    return is_numeric($value) ? (float)$value : 0.0;
};

$playTimeHours = $readStat($statsData, 'playTimeHours');
$mined = $readStat($statsData, 'mined');
$placed = $readStat($statsData, 'placed');
$fishCaught = $readStat($statsData, 'fishCaught');
$kills = $readStat($statsData, 'kills');
$deaths = $readStat($statsData, 'deaths');

$formatCount = static function (float $value, string $suffix = ''): string {
    if ($value <= 0) {
        return '—';
    }

    $rounded = abs($value - round($value)) < 0.01
        ? number_format((float)round($value), 0, '.', ',')
        : number_format($value, 2, '.', ',');

    return $suffix !== '' ? $rounded . ' ' . $suffix : $rounded;
};

$formatTimelineTime = static function ($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '待同步';
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }

    return date('Y-m-d H:i', $timestamp);
};

$statsMetricCount = 0;
foreach ([$playTimeHours, $mined, $placed, $fishCaught, $kills, $deaths] as $metricValue) {
    if ($metricValue > 0) {
        $statsMetricCount++;
    }
}
$hasStats = $statsMetricCount > 0;

$activityCount = count($timelineEvents);
$checkinCount = 0;
$loginCount = 0;
foreach ($timelineEvents as $timelineEvent) {
    $title = trim((string)($timelineEvent['title'] ?? ''));
    if ($title !== '' && str_contains($title, '签到')) {
        $checkinCount++;
    }
    if ($title !== '' && str_contains($title, '登录')) {
        $loginCount++;
    }
}

$heroCards = [
    [
        'label' => '资料状态',
        'value' => $playerFound ? '已载入' : '待同步',
        'tone' => $playerFound ? 'sky' : 'amber',
    ],
    [
        'label' => '统计字段',
        'value' => $statsMetricCount . '/6',
        'tone' => $hasStats ? 'emerald' : 'slate',
    ],
    [
        'label' => '近期记录',
        'value' => $activityCount . ' 条',
        'tone' => $activityCount > 0 ? 'violet' : 'slate',
    ],
];

$sidebarCards = [
    [
        'label' => '网页硬币',
        'value' => number_format($coins),
        'tone' => 'sky',
    ],
    [
        'label' => '签到记录',
        'value' => $checkinCount . ' 次',
        'tone' => $checkinCount > 0 ? 'emerald' : 'slate',
    ],
    [
        'label' => '登录轨迹',
        'value' => $loginCount . ' 条',
        'tone' => $loginCount > 0 ? 'violet' : 'slate',
    ],
    [
        'label' => '统计接入',
        'value' => $statsMetricCount . ' 项',
        'tone' => $hasStats ? 'amber' : 'slate',
    ],
];

$overviewCards = [
    [
        'icon' => 'mdi-wallet-outline',
        'label' => '网页硬币',
        'value' => number_format($coins),
        'hint' => $coins > 0 ? '站内资产已同步' : '站内资产可用',
    ],
    [
        'icon' => 'mdi-pound',
        'label' => '玩家 ID',
        'value' => '#' . $playerIdDisplay,
        'hint' => $playerFound ? '站内账户编号' : '资料待收录',
    ],
    [
        'icon' => 'mdi-timer-sand',
        'label' => '游戏时长',
        'value' => $formatCount($playTimeHours, 'h'),
        'hint' => $playTimeHours > 0 ? '来自 player_stats' : '待同步',
    ],
    [
        'icon' => 'mdi-sword-cross',
        'label' => '击杀 / 死亡',
        'value' => ($kills > 0 || $deaths > 0) ? number_format($kills, 0, '.', ',') . ' / ' . number_format($deaths, 0, '.', ',') : '—',
        'hint' => ($kills > 0 || $deaths > 0) ? '战斗字段已接入' : '暂无战斗统计',
    ],
];

$preferenceBars = [
    [
        'label' => '战斗倾向',
        'value' => $kills,
        'note' => $kills > 0 ? '击杀统计' : '待接入',
        'tone' => 'rose',
    ],
    [
        'label' => '资源采集',
        'value' => $mined,
        'note' => $mined > 0 ? '挖掘统计' : '待接入',
        'tone' => 'sky',
    ],
    [
        'label' => '建造投入',
        'value' => $placed,
        'note' => $placed > 0 ? '放置统计' : '待接入',
        'tone' => 'emerald',
    ],
    [
        'label' => '休闲活动',
        'value' => $fishCaught,
        'note' => $fishCaught > 0 ? '钓鱼统计' : '待接入',
        'tone' => 'amber',
    ],
    [
        'label' => '生存损耗',
        'value' => $deaths,
        'note' => $deaths > 0 ? '死亡统计' : '待接入',
        'tone' => 'violet',
    ],
];

$preferenceMax = 1.0;
foreach ($preferenceBars as $preferenceBar) {
    $preferenceMax = max($preferenceMax, (float)$preferenceBar['value']);
}
foreach ($preferenceBars as &$preferenceBar) {
    $rawValue = (float)$preferenceBar['value'];
    if ($rawValue <= 0 || !$hasStats) {
        $preferenceBar['percent'] = 0;
        $preferenceBar['display'] = '待接入';
        continue;
    }

    $weight = log($rawValue + 1) / log($preferenceMax + 1);
    $preferenceBar['percent'] = max(18, (int)round($weight * 100));
    $preferenceBar['display'] = number_format($rawValue, 0, '.', ',');
}
unset($preferenceBar);

$achievementItems = [
    [
        'label' => 'MC 角色绑定',
        'state' => $mcUsername !== '' ? '已接入' : '未绑定',
        'tone' => $mcUsername !== '' ? 'emerald' : 'slate',
    ],
    [
        'label' => '站内资产',
        'state' => $coins > 0 ? '已同步' : '待积累',
        'tone' => $coins > 0 ? 'sky' : 'slate',
    ],
    [
        'label' => '签到记录',
        'state' => $checkinCount > 0 ? '已记录' : '待接入',
        'tone' => $checkinCount > 0 ? 'amber' : 'slate',
    ],
    [
        'label' => '行为档案',
        'state' => $activityCount > 0 ? '已生成' : '暂无记录',
        'tone' => $activityCount > 0 ? 'violet' : 'slate',
    ],
    [
        'label' => '成就系统',
        'state' => '待接入',
        'tone' => 'slate',
    ],
    [
        'label' => '收藏标签',
        'state' => $hasStats ? '基于已接入字段' : '待接入',
        'tone' => $hasStats ? 'emerald' : 'slate',
    ],
];

$activityPlaceholders = [
    [
        'eyebrow' => '待同步',
        'title' => '暂无近期行为记录',
        'description' => '签到、登录与关键行为会在这里汇总展示。',
    ],
    [
        'eyebrow' => '可接入',
        'title' => '后续可扩展任务与交易轨迹',
        'description' => '保持空状态可读，不再出现大面积深色占位。',
    ],
];

$coverageItems = [
    [
        'label' => '网页硬币',
        'connected' => true,
        'note' => $coins > 0 ? '站内资产可读' : '站内资产可用',
    ],
    [
        'label' => '玩家 ID',
        'connected' => $playerId !== '',
        'note' => $playerId !== '' ? '账号编号可读' : '资料待同步',
    ],
    [
        'label' => '游戏时长',
        'connected' => $playTimeHours > 0,
        'note' => $playTimeHours > 0 ? '来自 player_stats' : '待同步',
    ],
    [
        'label' => '击杀 / 死亡',
        'connected' => ($kills > 0 || $deaths > 0),
        'note' => ($kills > 0 || $deaths > 0) ? '战斗字段已接入' : '待接入',
    ],
    [
        'label' => '挖掘',
        'connected' => $mined > 0,
        'note' => $mined > 0 ? '挖掘字段已接入' : '待接入',
    ],
    [
        'label' => '建造',
        'connected' => $placed > 0,
        'note' => $placed > 0 ? '建造字段已接入' : '待接入',
    ],
    [
        'label' => '钓鱼',
        'connected' => $fishCaught > 0,
        'note' => $fishCaught > 0 ? '钓鱼字段已接入' : '待接入',
    ],
];
$coverageConnectedLabels = [];
$coveragePendingLabels = [];
foreach ($coverageItems as $coverageItem) {
    if (!empty($coverageItem['connected'])) {
        $coverageConnectedLabels[] = (string)$coverageItem['label'];
        continue;
    }

    $coveragePendingLabels[] = (string)$coverageItem['label'];
}
$coverageConnectedCount = count($coverageConnectedLabels);
$coveragePercent = (int)round(($coverageConnectedCount / max(1, count($coverageItems))) * 100);
$coveragePercent = max(14, min(100, $coveragePercent));
$staticTabs = [
    ['id' => 'player-overview', 'label' => '总览'],
    ['id' => 'player-activity', 'label' => '近期动态'],
    ['id' => 'player-preference', 'label' => '偏好雷达'],
    ['id' => 'player-achievements', 'label' => '成就收藏'],
];
$archiveStatusLabel = $playerFound ? '玩家档案已载入' : '站内资料待同步';
$renderSourceLabel = $skinUseProxy ? '代理纹理回退已启用' : '标准皮肤链路';
?>

<style>
.player-page {
    --player-page-max: 1260px;
    --player-sticky-offset: 5.9rem;
    --player-card-bg: rgba(255, 255, 255, 0.84);
    --player-card-bg-strong: rgba(255, 255, 255, 0.92);
    --player-card-bg-soft: rgba(239, 246, 255, 0.86);
    --player-card-border: rgba(148, 163, 184, 0.32);
    --player-card-border-strong: rgba(148, 163, 184, 0.44);
    --player-shadow: 0 22px 52px -34px rgba(37, 99, 235, 0.32);
    --player-shadow-soft: 0 16px 36px -28px rgba(15, 23, 42, 0.18);
    --player-text-main: #0f172a;
    --player-text-sub: #334155;
    --player-text-muted: #64748b;
    --player-accent: #0284c7;
    --player-accent-soft: rgba(14, 165, 233, 0.12);
    --player-accent-border: rgba(14, 165, 233, 0.24);
    --player-track: rgba(191, 219, 254, 0.68);
    --player-stage-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(223, 239, 255, 0.84));
    --player-stage-glow: radial-gradient(circle at 50% 18%, rgba(125, 211, 252, 0.4), transparent 62%);
    --player-empty-bg: rgba(248, 250, 252, 0.82);
    --player-empty-border: rgba(148, 163, 184, 0.24);
    --player-status-pending: #f59e0b;
    --player-status-good: #10b981;
    --player-status-info: #0ea5e9;
    --player-status-violet: #8b5cf6;
    max-width: var(--player-page-max);
    margin: 0 auto;
    padding: 1.25rem 0.75rem 2.75rem;
    color: var(--player-text-main);
}

:is(html.dark, html[data-theme="dark"]) .player-page {
    --player-card-bg: rgba(15, 23, 42, 0.78);
    --player-card-bg-strong: rgba(15, 23, 42, 0.9);
    --player-card-bg-soft: rgba(30, 41, 59, 0.82);
    --player-card-border: rgba(148, 163, 184, 0.2);
    --player-card-border-strong: rgba(148, 163, 184, 0.28);
    --player-shadow: 0 24px 56px -38px rgba(2, 132, 199, 0.42);
    --player-shadow-soft: 0 16px 34px -28px rgba(2, 6, 23, 0.72);
    --player-text-main: #e2e8f0;
    --player-text-sub: #cbd5e1;
    --player-text-muted: #94a3b8;
    --player-accent: #38bdf8;
    --player-accent-soft: rgba(56, 189, 248, 0.12);
    --player-accent-border: rgba(56, 189, 248, 0.2);
    --player-track: rgba(30, 41, 59, 0.86);
    --player-stage-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.82));
    --player-stage-glow: radial-gradient(circle at 50% 18%, rgba(14, 165, 233, 0.16), transparent 64%);
    --player-empty-bg: rgba(15, 23, 42, 0.74);
    --player-empty-border: rgba(148, 163, 184, 0.16);
}

.player-dashboard {
    display: grid;
    grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
    gap: 1rem;
    align-items: start;
}

.player-profile-card,
.player-hero,
.player-panel {
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg);
    box-shadow: var(--player-shadow);
    backdrop-filter: blur(18px);
}

.player-profile-card {
    position: sticky;
    top: var(--player-sticky-offset);
    align-self: flex-start;
    border-radius: 1.75rem;
    padding: 1.15rem;
}

.player-profile-intro,
.player-name-block,
.player-pill-row,
.player-action-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.player-profile-intro {
    margin-bottom: 0.85rem;
}

.player-profile-kicker,
.player-panel-kicker,
.player-hero-eyebrow {
    margin: 0;
    color: var(--player-accent);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0;
}

.player-profile-status {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.42rem 0.72rem;
    border: 1px solid var(--player-accent-border);
    border-radius: 999px;
    background: var(--player-accent-soft);
    color: var(--player-text-sub);
    font-size: 0.78rem;
    white-space: nowrap;
}

.player-status-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
    background: var(--player-status-info);
    box-shadow: 0 0 0 0.24rem rgba(56, 189, 248, 0.16);
    flex: 0 0 auto;
}

.player-skin-stage {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--player-card-border-strong);
    border-radius: 1.45rem;
    padding: 1rem;
    background: var(--player-stage-bg);
    box-shadow: var(--player-shadow-soft);
}

.player-skin-stage::before {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--player-stage-glow);
    pointer-events: none;
}

.player-skin-shell {
    position: relative;
    z-index: 1;
    min-height: 340px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#player-skin-canvas,
#player-skin-fallback {
    width: 100%;
    max-width: 260px;
    height: auto;
    display: block;
}

#player-skin-canvas {
    display: none;
}

.player-action-row {
    margin-top: 0.9rem;
    justify-content: center;
    flex-wrap: wrap;
}

.player-action-btn {
    appearance: none;
    border: 1px solid var(--player-card-border-strong);
    background: var(--player-card-bg-strong);
    color: var(--player-text-sub);
    border-radius: 999px;
    padding: 0.62rem 1rem;
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}

.player-action-btn[disabled],
.player-action-btn[aria-disabled="true"] {
    cursor: not-allowed;
    opacity: 0.62;
    box-shadow: none;
}

.player-action-btn:hover,
.player-action-btn:focus-visible,
.player-action-btn.is-active {
    outline: none;
    transform: translateY(-1px);
    border-color: var(--player-accent-border);
    background: var(--player-accent-soft);
    color: var(--player-accent);
    box-shadow: 0 10px 24px -18px rgba(2, 132, 199, 0.6);
}

.player-action-btn[disabled]:hover,
.player-action-btn[disabled]:focus-visible,
.player-action-btn[aria-disabled="true"]:hover,
.player-action-btn[aria-disabled="true"]:focus-visible,
.player-action-btn[disabled].is-active,
.player-action-btn[aria-disabled="true"].is-active {
    transform: none;
    outline: none;
    border-color: var(--player-card-border-strong);
    background: var(--player-card-bg-strong);
    color: var(--player-text-muted);
    box-shadow: none;
}

.player-name-block {
    margin-top: 1rem;
    align-items: flex-start;
    flex-direction: column;
}

.player-name-block > div {
    width: 100%;
    min-width: 0;
}

.player-profile-name {
    margin: 0;
    max-width: 100%;
    font-size: clamp(1.7rem, 1.4rem + 1vw, 2.08rem);
    line-height: 1.12;
    color: var(--player-text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-name-block p {
    margin: 0.35rem 0 0;
    font-size: 0.95rem;
    color: var(--player-text-muted);
}

.player-pill-row {
    margin-top: 0.95rem;
    justify-content: flex-start;
    flex-wrap: wrap;
}

.player-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.2rem;
    padding: 0.4rem 0.78rem;
    border-radius: 999px;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
    color: var(--player-text-sub);
    font-size: 0.84rem;
    white-space: nowrap;
}

.player-pill--status::before {
    content: "";
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 999px;
    background: var(--player-status-pending);
    box-shadow: 0 0 0 0.22rem rgba(245, 158, 11, 0.16);
}

.player-sidebar-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.player-sidebar-stat {
    padding: 0.82rem 0.85rem;
    border: 1px solid var(--player-card-border);
    border-radius: 1rem;
    background: var(--player-card-bg-strong);
    min-width: 0;
}

.player-sidebar-stat-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--player-text-muted);
    font-size: 0.8rem;
}

.player-sidebar-stat-label::before {
    content: "";
    width: 0.38rem;
    height: 1.6rem;
    border-radius: 999px;
    background: var(--player-status-info);
    flex: 0 0 auto;
}

.player-sidebar-stat[data-tone="emerald"] .player-sidebar-stat-label::before {
    background: var(--player-status-good);
}

.player-sidebar-stat[data-tone="amber"] .player-sidebar-stat-label::before {
    background: var(--player-status-pending);
}

.player-sidebar-stat[data-tone="violet"] .player-sidebar-stat-label::before {
    background: var(--player-status-violet);
}

.player-sidebar-stat[data-tone="slate"] .player-sidebar-stat-label::before {
    background: rgba(148, 163, 184, 0.8);
}

.player-sidebar-stat strong {
    display: block;
    margin-top: 0.35rem;
    color: var(--player-text-main);
    font-size: 1.28rem;
    line-height: 1.1;
    overflow-wrap: anywhere;
}

.player-main {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
}

.player-hero {
    border-radius: 1.75rem;
    padding: 1.3rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
}

.player-hero h2 {
    margin: 0.18rem 0 0;
    font-size: clamp(2rem, 3vw, 2.7rem);
    line-height: 1.08;
    color: var(--player-text-main);
}

.player-hero p {
    margin: 0.48rem 0 0;
    max-width: 52rem;
    color: var(--player-text-muted);
    font-size: 0.98rem;
    line-height: 1.65;
}

.player-tab-row,
.player-hero-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
}

.player-tab-row {
    margin-top: 1rem;
}

.player-tab-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.58rem 0.95rem;
    border-radius: 999px;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
    color: var(--player-text-sub);
    font-size: 0.86rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.player-tab-pill:hover,
.player-tab-pill:focus-visible,
.player-tab-pill.is-active,
.player-tab-pill[aria-current="page"] {
    outline: none;
    transform: translateY(-1px);
    border-color: var(--player-accent-border);
    background: var(--player-accent-soft);
    color: var(--player-accent);
}

.player-anchor-section {
    scroll-margin-top: calc(var(--player-sticky-offset) + 0.9rem);
}

.player-hero-stats {
    justify-content: flex-end;
    align-content: flex-start;
}

.player-hero-stat {
    min-width: 148px;
    padding: 0.88rem 1rem;
    border-radius: 1.2rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
    box-shadow: var(--player-shadow-soft);
}

.player-hero-stat-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    color: var(--player-text-muted);
    font-size: 0.82rem;
}

.player-hero-stat-label::after {
    content: "";
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 0.32rem;
    background: var(--player-status-info);
    flex: 0 0 auto;
}

.player-hero-stat[data-tone="emerald"] .player-hero-stat-label::after {
    background: var(--player-status-good);
}

.player-hero-stat[data-tone="amber"] .player-hero-stat-label::after {
    background: var(--player-status-pending);
}

.player-hero-stat[data-tone="violet"] .player-hero-stat-label::after {
    background: var(--player-status-violet);
}

.player-hero-stat[data-tone="slate"] .player-hero-stat-label::after {
    background: rgba(148, 163, 184, 0.86);
}

.player-hero-stat strong {
    display: block;
    margin-top: 0.5rem;
    color: var(--player-text-main);
    font-size: 1.5rem;
    line-height: 1.1;
}

.player-main-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.player-panel {
    border-radius: 1.55rem;
    padding: 1.18rem;
    min-width: 0;
}

.player-panel--overview,
.player-panel--achievements {
    grid-column: 1 / -1;
}

.player-panel h3 {
    margin: 0.2rem 0 0;
    color: var(--player-text-main);
    font-size: 1.45rem;
    line-height: 1.18;
}

.player-panel p {
    margin: 0.45rem 0 0;
    color: var(--player-text-muted);
    font-size: 0.94rem;
    line-height: 1.58;
}

.player-overview-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.8rem;
    margin-top: 1rem;
}

.player-overview-card {
    min-width: 0;
    padding: 0.92rem 0.95rem;
    border-radius: 1.15rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
}

.player-overview-card span {
    display: inline-flex;
    align-items: center;
    gap: 0.48rem;
    color: var(--player-text-muted);
    font-size: 0.82rem;
}

.player-overview-card i {
    color: var(--player-accent);
    font-size: 1rem;
}

.player-overview-card strong {
    display: block;
    margin-top: 0.55rem;
    color: var(--player-text-main);
    font-size: 1.5rem;
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.player-overview-card small {
    display: block;
    margin-top: 0.42rem;
    color: var(--player-text-muted);
    font-size: 0.82rem;
}

.player-data-coverage {
    margin-top: 1rem;
    padding: 0.9rem;
    border-radius: 1.2rem;
    border: 1px solid var(--player-card-border);
    background: linear-gradient(180deg, var(--player-card-bg-strong), var(--player-card-bg-soft));
}

.player-data-coverage-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.player-data-coverage-head strong {
    display: block;
    color: var(--player-text-main);
    font-size: 1rem;
    line-height: 1.25;
}

.player-data-coverage-head p {
    margin-top: 0.25rem;
    font-size: 0.84rem;
    line-height: 1.5;
}

.player-data-coverage-ratio {
    display: inline-flex;
    align-items: center;
    min-height: 2rem;
    padding: 0.35rem 0.72rem;
    border-radius: 999px;
    border: 1px solid var(--player-accent-border);
    background: var(--player-accent-soft);
    color: var(--player-accent);
    font-size: 0.84rem;
    font-weight: 700;
}

.player-data-coverage-bar {
    margin-top: 0.85rem;
    overflow: hidden;
    border-radius: 999px;
    background: var(--player-track);
    height: 0.72rem;
}

.player-data-coverage-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, rgba(14, 165, 233, 0.78), rgba(59, 130, 246, 0.96));
}

.player-data-coverage-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
    margin-top: 0.85rem;
}

.player-data-coverage-meta-item {
    min-width: 0;
    padding: 0.82rem 0.88rem;
    border-radius: 1rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
}

.player-data-coverage-meta-item span {
    display: block;
    color: var(--player-text-muted);
    font-size: 0.78rem;
}

.player-data-coverage-meta-item strong {
    display: block;
    margin-top: 0.35rem;
    color: var(--player-text-main);
    font-size: 0.9rem;
    line-height: 1.45;
}

.player-data-coverage-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-top: 0.85rem;
}

.player-coverage-chip {
    display: inline-flex;
    align-items: center;
    min-height: 2rem;
    padding: 0.34rem 0.72rem;
    border-radius: 999px;
    border: 1px dashed var(--player-empty-border);
    background: var(--player-empty-bg);
    color: var(--player-text-muted);
    font-size: 0.8rem;
    font-weight: 700;
}

.player-coverage-chip.is-connected {
    border-style: solid;
    border-color: var(--player-accent-border);
    background: var(--player-accent-soft);
    color: var(--player-accent);
}

.player-data-coverage-caption {
    margin-top: 0.75rem;
    color: var(--player-text-muted);
    font-size: 0.82rem;
    line-height: 1.55;
}

.player-timeline {
    margin-top: 1rem;
    position: relative;
    padding-left: 1rem;
}

.player-timeline::before {
    content: "";
    position: absolute;
    top: 0.2rem;
    bottom: 0.2rem;
    left: 0.2rem;
    width: 2px;
    border-radius: 999px;
    background: var(--player-card-border-strong);
}

.player-timeline-item {
    position: relative;
    margin-top: 0.82rem;
    padding: 0.95rem 1rem 0.95rem 1.05rem;
    border-radius: 1.15rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
    min-width: 0;
}

.player-timeline-item:first-child {
    margin-top: 0;
}

.player-timeline-item::before {
    content: "";
    position: absolute;
    left: -0.88rem;
    top: 1.05rem;
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 999px;
    background: var(--player-status-info);
    box-shadow: 0 0 0 0.26rem rgba(56, 189, 248, 0.18);
}

.player-timeline-item--empty::before {
    background: rgba(148, 163, 184, 0.9);
    box-shadow: 0 0 0 0.26rem rgba(148, 163, 184, 0.14);
}

.player-timeline-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.player-timeline-badge {
    display: inline-flex;
    align-items: center;
    min-height: 1.8rem;
    padding: 0.26rem 0.62rem;
    border-radius: 999px;
    border: 1px solid var(--player-card-border);
    background: var(--player-accent-soft);
    color: var(--player-accent);
    font-size: 0.76rem;
    font-weight: 700;
}

.player-timeline-time {
    color: var(--player-text-muted);
    font-size: 0.78rem;
}

.player-timeline-title {
    margin-top: 0.55rem;
    color: var(--player-text-main);
    font-size: 1rem;
    font-weight: 700;
}

.player-timeline-copy {
    margin-top: 0.4rem;
    color: var(--player-text-sub);
    font-size: 0.88rem;
    line-height: 1.55;
}

.player-preference-list {
    display: grid;
    gap: 0.85rem;
    margin-top: 1rem;
}

.player-preference-item {
    padding: 0.92rem 0.95rem;
    border-radius: 1.15rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
}

.player-preference-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.player-preference-head strong {
    color: var(--player-text-main);
    font-size: 0.98rem;
}

.player-preference-head span {
    color: var(--player-text-muted);
    font-size: 0.82rem;
}

.player-preference-track {
    width: 100%;
    height: 0.72rem;
    margin-top: 0.7rem;
    border-radius: 999px;
    overflow: hidden;
    background: var(--player-track);
}

.player-preference-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--player-status-info);
    transition: width 0.25s ease;
}

.player-preference-item[data-tone="rose"] .player-preference-fill {
    background: #f43f5e;
}

.player-preference-item[data-tone="emerald"] .player-preference-fill {
    background: var(--player-status-good);
}

.player-preference-item[data-tone="amber"] .player-preference-fill {
    background: var(--player-status-pending);
}

.player-preference-item[data-tone="violet"] .player-preference-fill {
    background: var(--player-status-violet);
}

.player-preference-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.55rem;
    color: var(--player-text-muted);
    font-size: 0.8rem;
}

.player-preference-empty {
    margin-top: 1rem;
    padding: 0.95rem 1rem;
    border-radius: 1.1rem;
    border: 1px dashed var(--player-empty-border);
    background: var(--player-empty-bg);
    color: var(--player-text-muted);
    font-size: 0.88rem;
}

.player-achievement-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.8rem;
    margin-top: 1rem;
}

.player-achievement-item {
    padding: 0.95rem;
    border-radius: 1.1rem;
    border: 1px solid var(--player-card-border);
    background: var(--player-card-bg-strong);
    min-width: 0;
}

.player-achievement-item strong {
    display: block;
    color: var(--player-text-main);
    font-size: 0.98rem;
}

.player-achievement-item span {
    display: block;
    margin-top: 0.35rem;
    color: var(--player-text-muted);
    font-size: 0.82rem;
}

.player-achievement-item[data-tone="emerald"] {
    border-color: rgba(16, 185, 129, 0.24);
    background: linear-gradient(180deg, var(--player-card-bg-strong), rgba(16, 185, 129, 0.08));
}

.player-achievement-item[data-tone="sky"] {
    border-color: rgba(14, 165, 233, 0.24);
    background: linear-gradient(180deg, var(--player-card-bg-strong), rgba(14, 165, 233, 0.08));
}

.player-achievement-item[data-tone="amber"] {
    border-color: rgba(245, 158, 11, 0.24);
    background: linear-gradient(180deg, var(--player-card-bg-strong), rgba(245, 158, 11, 0.08));
}

.player-achievement-item[data-tone="violet"] {
    border-color: rgba(139, 92, 246, 0.24);
    background: linear-gradient(180deg, var(--player-card-bg-strong), rgba(139, 92, 246, 0.08));
}

@media (max-width: 1120px) {
    .player-dashboard {
        grid-template-columns: 1fr;
    }

    .player-profile-card {
        position: static;
        top: auto;
    }

    .player-hero {
        grid-template-columns: 1fr;
    }

    .player-hero-stats {
        justify-content: flex-start;
    }
}

@media (max-width: 900px) {
    .player-main-grid,
    .player-overview-grid,
    .player-achievement-grid,
    .player-data-coverage-meta {
        grid-template-columns: 1fr;
    }

    .player-panel--overview,
    .player-panel--achievements {
        grid-column: auto;
    }
}

@media (max-width: 640px) {
    .player-page {
        padding-inline: 0.65rem;
    }

    .player-profile-card,
    .player-hero,
    .player-panel {
        border-radius: 1.35rem;
    }

    .player-sidebar-stats {
        grid-template-columns: 1fr;
    }

    .player-hero-stat {
        min-width: 0;
        flex: 1 1 100%;
    }

    .player-skin-shell {
        min-height: 290px;
    }

    .player-profile-name {
        font-size: clamp(1.5rem, 1.3rem + 1vw, 1.72rem);
    }
}

@media (prefers-reduced-motion: reduce) {
    .player-action-btn,
    .player-tab-pill {
        transition: none;
    }

    .player-action-btn:hover,
    .player-tab-pill:hover {
        transform: none;
    }
}
</style>

<div class="player-page">
    <div class="player-dashboard">
        <aside class="player-profile-card">
            <div class="player-profile-intro">
                <p class="player-profile-kicker">Player Archive</p>
                <div class="player-profile-status">
                    <span class="player-status-dot" aria-hidden="true"></span>
                    <span><?= htmlspecialchars($archiveStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <div class="player-skin-stage" data-player-skin-viewer>
                <div class="player-skin-shell">
                    <canvas
                        id="player-skin-canvas"
                        width="260"
                        height="360"
                        aria-label="<?= htmlspecialchars($displayName . ' 3D 玩家皮肤预览', ENT_QUOTES, 'UTF-8') ?>"
                    ></canvas>
                    <img
                        id="player-skin-fallback"
                        src="<?= htmlspecialchars($skinBodyUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($displayName . ' 2D 玩家皮肤预览', ENT_QUOTES, 'UTF-8') ?>"
                        width="260"
                        height="347"
                        loading="eager"
                        decoding="async"
                    >
                </div>
            </div>

            <div class="player-action-row">
                <button type="button" class="player-action-btn" id="player-skin-action" data-player-animation-mode="action" disabled aria-disabled="true" title="动画加载中">行动动画</button>
                <button type="button" class="player-action-btn" id="player-skin-run" data-player-animation-mode="run" disabled aria-disabled="true" title="动画加载中">跑步动画</button>
            </div>

            <div class="player-name-block">
                <div>
                    <h1 class="player-profile-name" title="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($mcUsername !== '' ? ('Minecraft 角色：' . $mcUsername) : 'Minecraft 角色未绑定，当前使用默认 Steve/Alex 占位渲染。', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="player-pill-row">
                <span class="player-pill">玩家 ID · #<?= htmlspecialchars($playerIdDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="player-pill player-pill--status">在线状态 · 待同步</span>
            </div>

            <div class="player-sidebar-stats">
                <?php foreach ($sidebarCards as $sidebarCard): ?>
                    <div class="player-sidebar-stat" data-tone="<?= htmlspecialchars((string)($sidebarCard['tone'] ?? 'sky'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="player-sidebar-stat-label"><?= htmlspecialchars((string)($sidebarCard['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <strong><?= htmlspecialchars((string)($sidebarCard['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="player-main">
            <header class="player-hero">
                <div>
                    <p class="player-hero-eyebrow">玩家档案 / 数据中心</p>
                    <h2>玩家数据中心</h2>
                    <p>整合已接入的站内资产、角色资料、近期行为与偏好统计。未接入字段会明确标注为空状态，不冒充真实游戏数据。</p>
                    <div class="player-tab-row" aria-label="玩家分区导航">
                        <?php foreach ($staticTabs as $tabIndex => $staticTab): ?>
                            <a
                                href="#<?= htmlspecialchars((string)($staticTab['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="player-tab-pill<?= $tabIndex === 0 ? ' is-active' : '' ?>"
                                data-player-anchor-link="<?= htmlspecialchars((string)($staticTab['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                <?= $tabIndex === 0 ? 'aria-current="page"' : '' ?>
                            ><?= htmlspecialchars((string)($staticTab['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="player-hero-stats">
                    <?php foreach ($heroCards as $heroCard): ?>
                        <div class="player-hero-stat" data-tone="<?= htmlspecialchars((string)($heroCard['tone'] ?? 'sky'), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="player-hero-stat-label"><?= htmlspecialchars((string)($heroCard['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <strong><?= htmlspecialchars((string)($heroCard['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </header>

            <div class="player-main-grid">
                <section id="player-overview" class="player-panel player-panel--overview player-anchor-section" data-player-anchor-section="player-overview">
                    <p class="player-panel-kicker">总览</p>
                    <h3>综合数据统计</h3>
                    <p>优先展示已接入的真实字段，其余位置使用清晰的待同步提示。下方改为接入覆盖状态，只说明当前页面有哪些真实字段，不伪造趋势图。</p>

                    <div class="player-overview-grid">
                        <?php foreach ($overviewCards as $overviewCard): ?>
                            <article class="player-overview-card">
                                <span><i class="mdi <?= htmlspecialchars((string)($overviewCard['icon'] ?? 'mdi-chart-box-outline'), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><?= htmlspecialchars((string)($overviewCard['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars((string)($overviewCard['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars((string)($overviewCard['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="player-data-coverage">
                        <div class="player-data-coverage-head">
                            <div>
                                <strong>数据接入覆盖度</strong>
                                <p>当前只展示站内真实可读的字段状态，避免把字段快照误导成时间序列趋势。</p>
                            </div>
                            <span class="player-data-coverage-ratio"><?= htmlspecialchars($coverageConnectedCount . ' / ' . count($coverageItems), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="player-data-coverage-bar" aria-hidden="true">
                            <span style="width: <?= (int)$coveragePercent ?>%;"></span>
                        </div>
                        <div class="player-data-coverage-meta">
                            <div class="player-data-coverage-meta-item">
                                <span>已接入字段</span>
                                <strong><?= htmlspecialchars($coverageConnectedLabels !== [] ? implode('、', $coverageConnectedLabels) : '当前仅保留基础资料', ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div class="player-data-coverage-meta-item">
                                <span>待接入字段</span>
                                <strong><?= htmlspecialchars($coveragePendingLabels !== [] ? implode('、', $coveragePendingLabels) : '当前展示字段均已接入', ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                        </div>
                        <div class="player-data-coverage-chips">
                            <?php foreach ($coverageItems as $coverageItem): ?>
                                <span class="player-coverage-chip<?= !empty($coverageItem['connected']) ? ' is-connected' : '' ?>" title="<?= htmlspecialchars((string)($coverageItem['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($coverageItem['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="player-data-coverage-caption">覆盖判定基于当前页真实数据源：站内资料、player_stats 字段和已归档行为记录。未接入字段继续保留为待接入状态。</div>
                    </div>
                </section>

                <section id="player-activity" class="player-panel player-anchor-section" data-player-anchor-section="player-activity">
                    <p class="player-panel-kicker">近期动态</p>
                    <h3>近期活跃记录</h3>
                    <p>即使暂时没有真实记录，也保持时间线结构可读，避免出现未加载的大黑框。</p>

                    <div class="player-timeline">
                        <?php if ($activityCount === 0): ?>
                            <?php foreach ($activityPlaceholders as $placeholder): ?>
                                <article class="player-timeline-item player-timeline-item--empty">
                                    <div class="player-timeline-meta">
                                        <span class="player-timeline-badge"><?= htmlspecialchars((string)($placeholder['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="player-timeline-time">暂无记录</span>
                                    </div>
                                    <div class="player-timeline-title"><?= htmlspecialchars((string)($placeholder['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="player-timeline-copy"><?= htmlspecialchars((string)($placeholder['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($timelineEvents as $timelineEvent): ?>
                                <?php
                                $eventTitle = trim((string)($timelineEvent['title'] ?? '行为记录'));
                                $eventCopy = trim((string)($timelineEvent['sub'] ?? ''));
                                $eventBadge = '活动';
                                if (str_contains($eventTitle, '签到')) {
                                    $eventBadge = '签到';
                                } elseif (str_contains($eventTitle, '登录')) {
                                    $eventBadge = '登录';
                                }
                                ?>
                                <article class="player-timeline-item">
                                    <div class="player-timeline-meta">
                                        <span class="player-timeline-badge"><?= htmlspecialchars($eventBadge, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="player-timeline-time"><?= htmlspecialchars($formatTimelineTime($timelineEvent['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="player-timeline-title"><?= htmlspecialchars($eventTitle !== '' ? $eventTitle : '行为记录', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="player-timeline-copy"><?= htmlspecialchars($eventCopy !== '' ? $eventCopy : '该记录已进入站内时间线归档。', ENT_QUOTES, 'UTF-8') ?></div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section id="player-preference" class="player-panel player-anchor-section" data-player-anchor-section="player-preference">
                    <p class="player-panel-kicker">偏好雷达</p>
                    <h3>PVP / PVE 偏好雷达</h3>
                    <p>当前用轻量参数条呈现已接入字段的相对权重。若字段为空，则明确显示为待接入，不伪造玩家倾向。</p>

                    <?php if (!$hasStats): ?>
                        <div class="player-preference-empty">暂无可计算的偏好数据，下面的槽位仅用于保留布局，等待后续接入更完整的游戏行为指标。</div>
                    <?php endif; ?>

                    <div class="player-preference-list">
                        <?php foreach ($preferenceBars as $preferenceBar): ?>
                            <article class="player-preference-item" data-tone="<?= htmlspecialchars((string)($preferenceBar['tone'] ?? 'sky'), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="player-preference-head">
                                    <strong><?= htmlspecialchars((string)($preferenceBar['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string)($preferenceBar['display'] ?? '待接入'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="player-preference-track" aria-hidden="true">
                                    <div class="player-preference-fill" style="width: <?= (int)($preferenceBar['percent'] ?? 0) ?>%;"></div>
                                </div>
                                <div class="player-preference-foot">
                                    <span><?= htmlspecialchars((string)($preferenceBar['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><?= htmlspecialchars(((int)($preferenceBar['percent'] ?? 0)) . '%', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section id="player-achievements" class="player-panel player-panel--achievements player-anchor-section" data-player-anchor-section="player-achievements">
                    <p class="player-panel-kicker">成就 / 收藏</p>
                    <h3>成就与收藏</h3>
                    <p>在真实成就系统接入前，这里先展示站内档案标签与同步状态，让右侧区域保持完整而不是空白占位。</p>

                    <div class="player-achievement-grid">
                        <?php foreach ($achievementItems as $achievementItem): ?>
                            <article class="player-achievement-item" data-tone="<?= htmlspecialchars((string)($achievementItem['tone'] ?? 'slate'), ENT_QUOTES, 'UTF-8') ?>">
                                <strong><?= htmlspecialchars((string)($achievementItem['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars((string)($achievementItem['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </section>
    </div>
</div>

<script src="https://unpkg.com/skinview3d@3.4.1/bundles/skinview3d.bundle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pageRoot = document.querySelector('.player-page');
    var navShell = document.querySelector('#navbar .mc-nav-shell') || document.getElementById('navbar');
    var canvas = document.getElementById('player-skin-canvas');
    var fallbackImage = document.getElementById('player-skin-fallback');
    var actionButton = document.getElementById('player-skin-action');
    var runButton = document.getElementById('player-skin-run');
    var anchorLinks = Array.prototype.slice.call(document.querySelectorAll('[data-player-anchor-link]'));
    var rawSkinUrl = <?= json_encode($skinRawUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
    var useProxy = <?= $skinUseProxy ? 'true' : 'false' ?>;
    var finalSkinUrl = useProxy ? ('/api/skin-proxy?url=' + encodeURIComponent(rawSkinUrl)) : rawSkinUrl;
    var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    var syncStickyOffset = function () {
        if (!pageRoot || !navShell) {
            return;
        }

        var navBottom = navShell.getBoundingClientRect().bottom;
        var stickyOffset = Math.max(Math.ceil(navBottom + 12), 84);
        pageRoot.style.setProperty('--player-sticky-offset', stickyOffset + 'px');
    };

    syncStickyOffset();
    window.addEventListener('resize', syncStickyOffset);

    var setActiveAnchor = function (targetId) {
        anchorLinks.forEach(function (link) {
            var isActive = (link.getAttribute('data-player-anchor-link') || '') === targetId;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'page');
                return;
            }

            link.removeAttribute('aria-current');
        });
    };

    var applyAnchorFromHash = function (hashValue) {
        var fallbackTargetId = anchorLinks.length > 0 ? (anchorLinks[0].getAttribute('data-player-anchor-link') || '') : '';
        var nextTargetId = (hashValue || '').replace(/^#/, '');
        if (!nextTargetId || !document.getElementById(nextTargetId)) {
            nextTargetId = fallbackTargetId;
        }

        if (nextTargetId) {
            setActiveAnchor(nextTargetId);
        }
    };

    anchorLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var targetId = link.getAttribute('data-player-anchor-link') || '';
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                return;
            }

            event.preventDefault();
            setActiveAnchor(targetId);
            target.scrollIntoView({
                behavior: reducedMotionQuery && reducedMotionQuery.matches ? 'auto' : 'smooth',
                block: 'start'
            });

            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', '#' + targetId);
                return;
            }

            window.location.hash = targetId;
        });
    });

    applyAnchorFromHash(window.location.hash);
    window.addEventListener('hashchange', function () {
        applyAnchorFromHash(window.location.hash);
    });

    var clearActiveButtons = function () {
        if (actionButton) {
            actionButton.classList.remove('is-active');
        }
        if (runButton) {
            runButton.classList.remove('is-active');
        }
    };

    var setAnimationAvailability = function (enabled, titleText) {
        [actionButton, runButton].forEach(function (button) {
            if (!button) {
                return;
            }

            button.disabled = !enabled;
            button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            if (enabled) {
                button.removeAttribute('title');
                return;
            }

            button.setAttribute('title', titleText || '动画功能待接入');
        });

        if (!enabled) {
            clearActiveButtons();
        }
    };

    setAnimationAvailability(false, '动画加载中');

    if (!canvas || !fallbackImage) {
        return;
    }

    var showFallback = function () {
        canvas.style.display = 'none';
        fallbackImage.style.display = 'block';
    };

    var showCanvas = function () {
        fallbackImage.style.display = 'none';
        canvas.style.display = 'block';
    };

    var setActiveButton = function (mode) {
        if (actionButton) {
            actionButton.classList.toggle('is-active', mode === 'action');
        }
        if (runButton) {
            runButton.classList.toggle('is-active', mode === 'run');
        }
    };

    showFallback();

    if (typeof skinview3d === 'undefined' || !rawSkinUrl) {
        setAnimationAvailability(false, '动画功能待接入');
        return;
    }

    var viewer;
    try {
        viewer = new skinview3d.SkinViewer({
            canvas: canvas,
            width: 260,
            height: 360
        });
    } catch (error) {
        setAnimationAvailability(false, '动画功能待接入');
        return;
    }

    if (viewer.controls) {
        viewer.controls.enableRotate = true;
        viewer.controls.enablePan = false;
        viewer.controls.enableZoom = false;
    }

    var setAnimation = function (mode) {
        if (!viewer || !viewer.animations) {
            return;
        }

        viewer.animations.clear();
        if (mode === 'run') {
            var running = new skinview3d.RunningAnimation();
            running.speed = 1.02;
            viewer.animations.add(running);
            setActiveButton('run');
            return;
        }

        var walking = new skinview3d.WalkingAnimation();
        walking.speed = 0.82;
        viewer.animations.add(walking);
        setActiveButton('action');
    };

    try {
        viewer.loadSkin(finalSkinUrl).then(function () {
            showCanvas();
            if (!viewer.animations) {
                setAnimationAvailability(false, '动画功能待接入');
                return;
            }

            setAnimationAvailability(true);
            setAnimation('action');
        }).catch(function () {
            showFallback();
            setAnimationAvailability(false, '动画功能待接入');
        });
    } catch (error) {
        showFallback();
        setAnimationAvailability(false, '动画功能待接入');
    }

    if (actionButton) {
        actionButton.addEventListener('click', function () {
            if (actionButton.disabled) {
                return;
            }

            setAnimation('action');
        });
    }

    if (runButton) {
        runButton.addEventListener('click', function () {
            if (runButton.disabled) {
                return;
            }

            setAnimation('run');
        });
    }
});
</script>
