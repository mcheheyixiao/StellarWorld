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

$latestTimelineTime = $activityCount > 0 ? $formatTimelineTime($timelineEvents[0]['time'] ?? '') : '';
if ($latestTimelineTime === '' || $latestTimelineTime === '待同步') {
    $latestTimelineTime = '暂无记录';
}

$heroCards = [
    [
        'label' => '档案状态',
        'value' => $playerFound ? '已载入' : '暂无记录',
        'tone' => $playerFound ? 'sky' : 'amber',
    ],
    [
        'label' => '生涯记录',
        'value' => $statsMetricCount . '/6',
        'tone' => $hasStats ? 'emerald' : 'slate',
    ],
    [
        'label' => '最近足迹',
        'value' => $activityCount > 0 ? $activityCount . ' 条' : '暂无记录',
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
        'value' => $checkinCount > 0 ? ($checkinCount . ' 次') : '暂无记录',
        'tone' => $checkinCount > 0 ? 'emerald' : 'slate',
    ],
    [
        'label' => '登录足迹',
        'value' => $loginCount > 0 ? ($loginCount . ' 条') : '暂无记录',
        'tone' => $loginCount > 0 ? 'violet' : 'slate',
    ],
    [
        'label' => '生涯记录',
        'value' => $statsMetricCount > 0 ? ($statsMetricCount . ' 项') : '暂无记录',
        'tone' => $hasStats ? 'amber' : 'slate',
    ],
];

$overviewCards = [
    [
        'icon' => 'mdi-timer-sand',
        'label' => '游戏时长',
        'value' => $formatCount($playTimeHours, 'h'),
        'hint' => $playTimeHours > 0 ? '累计游玩时长' : '暂无足够数据',
    ],
    [
        'icon' => 'mdi-sword-cross',
        'label' => '击杀 / 死亡',
        'value' => ($kills > 0 || $deaths > 0) ? number_format($kills, 0, '.', ',') . ' / ' . number_format($deaths, 0, '.', ',') : '—',
        'hint' => ($kills > 0 || $deaths > 0) ? '已收录冒险中的战斗记录' : '暂无足够数据',
    ],
    [
        'icon' => 'mdi-wallet-outline',
        'label' => '网页硬币',
        'value' => number_format($coins),
        'hint' => $coins > 0 ? '当前站内资产' : '暂无记录',
    ],
    [
        'icon' => 'mdi-history',
        'label' => '最近活跃',
        'value' => $latestTimelineTime,
        'hint' => $activityCount > 0 ? '最近一次站内足迹' : '继续游玩后会逐步丰富',
    ],
];

$careerItems = [
    [
        'label' => '基础档案',
        'connected' => $playerFound || $playerId !== '' || $mcUsername !== '',
        'note' => $playerFound ? '玩家编号与角色资料已记录' : '站内资料会在这里显示',
    ],
    [
        'label' => '网页硬币',
        'connected' => true,
        'note' => $coins > 0 ? ('当前拥有 ' . number_format($coins) . ' 枚硬币') : '暂未积累站内硬币',
    ],
    [
        'label' => '战斗记录',
        'connected' => ($kills > 0 || $deaths > 0),
        'note' => ($kills > 0 || $deaths > 0) ? '击杀与死亡会持续更新' : '暂无足够数据',
    ],
    [
        'label' => '采集足迹',
        'connected' => $mined > 0,
        'note' => $mined > 0 ? '已留下采集记录' : '继续游玩后会逐步显示',
    ],
    [
        'label' => '建造足迹',
        'connected' => $placed > 0,
        'note' => $placed > 0 ? '已留下建造记录' : '继续游玩后会逐步显示',
    ],
    [
        'label' => '钓鱼记录',
        'connected' => $fishCaught > 0,
        'note' => $fishCaught > 0 ? '已留下钓鱼记录' : '继续游玩后会逐步显示',
    ],
    [
        'label' => '最近足迹',
        'connected' => $activityCount > 0,
        'note' => $activityCount > 0 ? ('已收录 ' . $activityCount . ' 条站内足迹') : '继续游玩后会逐步显示',
    ],
];
$careerRecordedLabels = [];
$careerPendingLabels = [];
foreach ($careerItems as $careerItem) {
    if (!empty($careerItem['connected'])) {
        $careerRecordedLabels[] = (string)$careerItem['label'];
        continue;
    }

    $careerPendingLabels[] = (string)$careerItem['label'];
}
$careerRecordedCount = count($careerRecordedLabels);
$careerPercent = (int)round(($careerRecordedCount / max(1, count($careerItems))) * 100);
$careerPercent = max(14, min(100, $careerPercent));

$preferenceBars = [
    [
        'label' => '战斗倾向',
        'value' => $kills,
        'note' => $kills > 0 ? '战斗记录' : '暂无足够数据',
        'tone' => 'rose',
    ],
    [
        'label' => '资源采集',
        'value' => $mined,
        'note' => $mined > 0 ? '采集记录' : '暂无足够数据',
        'tone' => 'sky',
    ],
    [
        'label' => '建造投入',
        'value' => $placed,
        'note' => $placed > 0 ? '建造记录' : '暂无足够数据',
        'tone' => 'emerald',
    ],
    [
        'label' => '休闲活动',
        'value' => $fishCaught,
        'note' => $fishCaught > 0 ? '休闲记录' : '暂无足够数据',
        'tone' => 'amber',
    ],
    [
        'label' => '生存压力',
        'value' => $deaths,
        'note' => $deaths > 0 ? '生存记录' : '暂无足够数据',
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
        $preferenceBar['display'] = '暂无足够数据';
        $preferenceBar['percentLabel'] = '暂无记录';
        continue;
    }

    $weight = log($rawValue + 1) / log($preferenceMax + 1);
    $preferenceBar['percent'] = max(18, (int)round($weight * 100));
    $preferenceBar['display'] = number_format($rawValue, 0, '.', ',');
    $preferenceBar['percentLabel'] = $preferenceBar['percent'] . '%';
}
unset($preferenceBar);

$achievementItems = [
    [
        'label' => 'Minecraft 角色',
        'state' => $mcUsername !== '' ? '已绑定' : '未绑定',
        'tone' => $mcUsername !== '' ? 'emerald' : 'slate',
    ],
    [
        'label' => '站内资产',
        'state' => $coins > 0 ? '已记录' : '暂无记录',
        'tone' => $coins > 0 ? 'sky' : 'slate',
    ],
    [
        'label' => '签到记录',
        'state' => $checkinCount > 0 ? '已记录' : '暂无记录',
        'tone' => $checkinCount > 0 ? 'amber' : 'slate',
    ],
    [
        'label' => '最近足迹',
        'state' => $activityCount > 0 ? '已收录' : '暂无记录',
        'tone' => $activityCount > 0 ? 'violet' : 'slate',
    ],
    [
        'label' => '冒险战绩',
        'state' => ($kills > 0 || $deaths > 0) ? '已记录' : '暂无足够数据',
        'tone' => ($kills > 0 || $deaths > 0) ? 'rose' : 'slate',
    ],
    [
        'label' => '游玩偏好',
        'state' => $hasStats ? '正在生成' : '继续游玩后显示',
        'tone' => $hasStats ? 'emerald' : 'slate',
    ],
];

$activityPlaceholders = [
    [
        'eyebrow' => '暂无记录',
        'title' => '最近还没有新的站内足迹',
        'description' => '签到、登录和重要站内动态会在这里整理显示。',
    ],
    [
        'eyebrow' => '继续探索',
        'title' => '继续游玩后会看到更多冒险轨迹',
        'description' => '你的签到、登录与重要动态会逐步汇聚到这里。',
    ],
];

$staticTabs = [
    ['id' => 'player-overview', 'label' => '总览'],
    ['id' => 'player-activity', 'label' => '最近足迹'],
    ['id' => 'player-preference', 'label' => '游玩偏好'],
    ['id' => 'player-achievements', 'label' => '成就收藏'],
];
$archiveStatusLabel = $playerFound ? '玩家档案已载入' : '站内资料暂无记录';
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

.player-profile-sidebar {
    position: sticky;
    top: var(--player-sticky-offset);
    align-self: flex-start;
    min-width: 0;
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
    border-radius: 1.75rem;
    padding: 1.15rem;
}

.player-profile-intro,
.player-name-block,
.player-pill-row {
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
    grid-template-columns: minmax(0, 1fr);
    gap: 1.1rem;
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

    .player-profile-sidebar {
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
    .player-tab-pill {
        transition: none;
    }

    .player-tab-pill:hover {
        transform: none;
    }
}
</style>

<div class="player-page">
    <div class="player-dashboard">
        <aside class="player-profile-sidebar">
            <div class="player-profile-card">
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

                <div class="player-name-block">
                    <div>
                        <h1 class="player-profile-name" title="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= htmlspecialchars($mcUsername !== '' ? ('Minecraft 角色：' . $mcUsername) : 'Minecraft 角色未绑定，当前使用默认 Steve/Alex 占位渲染。', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <div class="player-pill-row">
                    <span class="player-pill">玩家 ID · #<?= htmlspecialchars($playerIdDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="player-pill player-pill--status">在线状态 · 暂无记录</span>
                </div>

                <div class="player-sidebar-stats">
                    <?php foreach ($sidebarCards as $sidebarCard): ?>
                        <div class="player-sidebar-stat" data-tone="<?= htmlspecialchars((string)($sidebarCard['tone'] ?? 'sky'), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="player-sidebar-stat-label"><?= htmlspecialchars((string)($sidebarCard['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <strong><?= htmlspecialchars((string)($sidebarCard['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <section class="player-main">
            <header class="player-hero">
                <div>
                    <p class="player-hero-eyebrow">玩家档案 / 繁星World</p>
                    <h2>玩家档案总览</h2>
                    <p>这里收纳你在繁星World的基础资料、最近足迹与游玩偏好。页面只展示当前真实记录，不补造不存在的数据。</p>
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
                    <h3>玩家生涯</h3>
                    <p>这里记录你在繁星World的基础档案与游玩轨迹。</p>

                    <div class="player-overview-grid">
                        <?php foreach ($overviewCards as $overviewCard): ?>
                            <article class="player-overview-card">
                                <span><i class="mdi <?= htmlspecialchars((string)($overviewCard['icon'] ?? 'mdi-chart-box-outline'), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><?= htmlspecialchars((string)($overviewCard['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars((string)($overviewCard['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars((string)($overviewCard['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="player-data-coverage player-career-summary">
                        <div class="player-data-coverage-head">
                            <div>
                                <strong>玩家生涯</strong>
                                <p>这里会根据当前真实记录，整理你的基础档案、冒险战绩与站内足迹。</p>
                            </div>
                            <span class="player-data-coverage-ratio"><?= htmlspecialchars('已记录 ' . $careerRecordedCount . ' / ' . count($careerItems), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="player-data-coverage-bar" aria-hidden="true">
                            <span style="width: <?= (int)$careerPercent ?>%;"></span>
                        </div>
                        <div class="player-data-coverage-meta">
                            <div class="player-data-coverage-meta-item">
                                <span>已记录</span>
                                <strong><?= htmlspecialchars($careerRecordedLabels !== [] ? implode('、', $careerRecordedLabels) : '当前先展示基础档案', ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div class="player-data-coverage-meta-item">
                                <span>继续丰富</span>
                                <strong><?= htmlspecialchars($careerPendingLabels !== [] ? implode('、', $careerPendingLabels) : '当前可展示的生涯记录已经齐全', ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                        </div>
                        <div class="player-data-coverage-chips">
                            <?php foreach ($careerItems as $careerItem): ?>
                                <span class="player-coverage-chip<?= !empty($careerItem['connected']) ? ' is-connected' : '' ?>" title="<?= htmlspecialchars((string)($careerItem['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($careerItem['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="player-data-coverage-caption">继续游玩后，你的冒险足迹会逐步丰富。</div>
                    </div>
                </section>

                <section id="player-activity" class="player-panel player-anchor-section" data-player-anchor-section="player-activity">
                    <p class="player-panel-kicker">最近足迹</p>
                    <h3>最近足迹</h3>
                    <p>这里会整理你最近留下的签到、登录和重要站内动态。</p>

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
                                $eventBadge = '足迹';
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
                                    <div class="player-timeline-copy"><?= htmlspecialchars($eventCopy !== '' ? $eventCopy : '这条足迹已经收录到你的站内记录中。', ENT_QUOTES, 'UTF-8') ?></div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section id="player-preference" class="player-panel player-anchor-section" data-player-anchor-section="player-preference">
                    <p class="player-panel-kicker">游玩偏好</p>
                    <h3>当前游玩偏好</h3>
                    <p>这里会根据现有真实记录，轻量展示你目前更常留下哪些游玩足迹。</p>

                    <?php if (!$hasStats): ?>
                        <div class="player-preference-empty">暂无足够数据，继续游玩后这里会逐步显示你的战斗、采集、建造与休闲偏好。</div>
                    <?php endif; ?>

                    <div class="player-preference-list">
                        <?php foreach ($preferenceBars as $preferenceBar): ?>
                            <article class="player-preference-item" data-tone="<?= htmlspecialchars((string)($preferenceBar['tone'] ?? 'sky'), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="player-preference-head">
                                    <strong><?= htmlspecialchars((string)($preferenceBar['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string)($preferenceBar['display'] ?? '暂无足够数据'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="player-preference-track" aria-hidden="true">
                                    <div class="player-preference-fill" style="width: <?= (int)($preferenceBar['percent'] ?? 0) ?>%;"></div>
                                </div>
                                <div class="player-preference-foot">
                                    <span><?= htmlspecialchars((string)($preferenceBar['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><?= htmlspecialchars((string)($preferenceBar['percentLabel'] ?? '暂无记录'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section id="player-achievements" class="player-panel player-panel--achievements player-anchor-section" data-player-anchor-section="player-achievements">
                    <p class="player-panel-kicker">成就收藏</p>
                    <h3>成就与收藏</h3>
                    <p>这里展示你目前已经留下的站内徽记与收藏状态，更多记录会随游玩逐步丰富。</p>

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
    var navbar = document.getElementById('navbar');
    var navShell = document.querySelector('#navbar .mc-nav-shell') || navbar;
    var canvas = document.getElementById('player-skin-canvas');
    var fallbackImage = document.getElementById('player-skin-fallback');
    var anchorLinks = Array.prototype.slice.call(document.querySelectorAll('[data-player-anchor-link]'));
    var rawSkinUrl = <?= json_encode($skinRawUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
    var useProxy = <?= $skinUseProxy ? 'true' : 'false' ?>;
    var finalSkinUrl = useProxy ? ('/api/skin-proxy?url=' + encodeURIComponent(rawSkinUrl)) : rawSkinUrl;
    var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    var syncStickyOffset = function () {
        if (!pageRoot) {
            return;
        }

        var navBottom = 0;
        if (navbar && navShell && !navbar.classList.contains('hidden')) {
            navBottom = Math.max(0, Math.ceil(navShell.getBoundingClientRect().bottom));
        }

        var stickyOffset = Math.max(navBottom + 12, 16);
        pageRoot.style.setProperty('--player-sticky-offset', stickyOffset + 'px');
    };

    var queueStickySync = function () {
        syncStickyOffset();
        window.requestAnimationFrame(syncStickyOffset);
        window.setTimeout(syncStickyOffset, 320);
    };

    queueStickySync();
    window.addEventListener('resize', queueStickySync);
    document.addEventListener('navbarHide', queueStickySync);
    document.addEventListener('navbarShow', queueStickySync);

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

    showFallback();

    if (typeof skinview3d === 'undefined' || !rawSkinUrl) {
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
        return;
    }

    if (viewer.controls) {
        viewer.controls.enableRotate = true;
        viewer.controls.enablePan = false;
        viewer.controls.enableZoom = false;
    }

    var startDefaultAnimation = function () {
        if (!viewer || !viewer.animations) {
            return;
        }

        viewer.animations.clear();
        var walking = new skinview3d.WalkingAnimation();
        walking.speed = 0.82;
        viewer.animations.add(walking);
    };

    try {
        viewer.loadSkin(finalSkinUrl).then(function () {
            showCanvas();
            startDefaultAnimation();
        }).catch(function () {
            showFallback();
        });
    } catch (error) {
        showFallback();
    }
});
</script>
