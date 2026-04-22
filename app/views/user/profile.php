<?php
/** @var array $profile */
/** @var bool $canChangeMcName */
/** @var string $cooldownMessage */
/** @var string $emailMasked */
/** @var string|null $muaSkinUrl */

$profile = $profile ?? [];
$canChangeMcName = (bool)($canChangeMcName ?? false);
$cooldownMessage = (string)($cooldownMessage ?? '');
$emailMasked = (string)($emailMasked ?? '未绑定邮箱');
$muaSkinUrl = $muaSkinUrl ?? null;

$mcUsername = trim((string)($profile['mc_username'] ?? ''));
$hasMcName = $mcUsername !== '';
$renderName = $hasMcName ? $mcUsername : 'MHF_Steve';
$minotarBodyUrl = 'https://minotar.net/armor/body/' . rawurlencode($renderName) . '/300.png';
$minotarAvatarUrl = 'https://minotar.net/helm/' . rawurlencode($renderName) . '/96.png';
$craftheadSkinUrl = 'https://crafthead.net/skin/' . rawurlencode($renderName);
$muaSub = trim((string)($profile['mua_sub'] ?? ''));
$isMuaBound = $muaSub !== '';
$useMuaSkinRender = $isMuaBound && is_string($muaSkinUrl) && trim($muaSkinUrl) !== '';
$finalRawSkinUrl = $useMuaSkinRender ? (string)$muaSkinUrl : $craftheadSkinUrl;

$roleRaw = (string)($profile['role'] ?? 'player');
$roleLabel = $roleRaw === 'admin' ? '管理员' : '玩家';

$createdAtRaw = (string)($profile['created_at'] ?? '');
$createdAtDisplay = '--';
if ($createdAtRaw !== '') {
    $ts = strtotime($createdAtRaw);
    $createdAtDisplay = $ts !== false ? date('Y-m-d', $ts) : $createdAtRaw;
}

$qqValue = trim((string)($profile['qq'] ?? ($profile['qq_number'] ?? '')));
$uidValue = isset($profile['id']) ? (string)$profile['id'] : '';
$qqOrUidDisplay = $qqValue !== '' ? ('QQ ' . $qqValue) : ($uidValue !== '' ? ('UID #' . $uidValue) : '--');

$gameAccountCountDisplay = '--';
if (isset($profile['game_accounts_count']) && $profile['game_accounts_count'] !== '') {
    $gameAccountCountDisplay = (string)$profile['game_accounts_count'];
} elseif (isset($profile['mc_account_count']) && $profile['mc_account_count'] !== '') {
    $gameAccountCountDisplay = (string)$profile['mc_account_count'];
} elseif ($hasMcName) {
    $gameAccountCountDisplay = '1';
}

$onlineDisplay = '--';
if (isset($profile['online_count']) && $profile['online_count'] !== '') {
    $onlineDisplay = (string)$profile['online_count'];
} elseif (isset($profile['current_online']) && $profile['current_online'] !== '') {
    $onlineDisplay = (string)$profile['current_online'];
} elseif (isset($profile['is_online']) && $profile['is_online'] !== '') {
    $onlineDisplay = ((int)$profile['is_online'] === 1) ? '在线' : '离线';
}

$durationDisplay = '--';
if (isset($profile['total_play_time']) && $profile['total_play_time'] !== '') {
    $durationDisplay = (string)$profile['total_play_time'];
} elseif (isset($profile['play_time']) && $profile['play_time'] !== '') {
    $durationDisplay = (string)$profile['play_time'];
} elseif (isset($profile['total_duration']) && $profile['total_duration'] !== '') {
    $durationDisplay = (string)$profile['total_duration'];
}

$deathDisplay = '--';
if (isset($profile['death_count']) && $profile['death_count'] !== '') {
    $deathDisplay = (string)$profile['death_count'];
} elseif (isset($profile['deaths']) && $profile['deaths'] !== '') {
    $deathDisplay = (string)$profile['deaths'];
}
?>

<style>
.profile-page-root {
    color: var(--text-primary);
}
.profile-shell {
    display: grid;
    grid-template-columns: 1fr;
    --profile-surface: var(--card-bg-soft);
    --profile-surface-muted: var(--card-bg-muted);
    --profile-border: var(--border-color-strong);
    --profile-text: var(--text-primary);
    --profile-muted: var(--text-muted);
}
.profile-sidebar {
    border-bottom: 1px solid var(--profile-border);
}
.duration-200 {
    transition-duration: 200ms;
}
.ease-in-out {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.bg-gray-100 {
    background-color: var(--card-bg-muted) !important;
}
.text-gray-700 {
    color: var(--text-primary) !important;
}
[data-theme="dark"] .dark\:bg-gray-800 {
    background-color: var(--card-bg-muted) !important;
}
[data-theme="dark"] .dark\:text-gray-300 {
    color: var(--text-primary) !important;
}
@media (hover: hover) {
    .hover\:bg-blue-600:hover {
        background-color: var(--primary-strong) !important;
    }
    .hover\:bg-slate-700\/80:hover {
        background-color: var(--card-bg-muted) !important;
    }
    [data-theme="light"] .hover\:bg-slate-700\/80:hover {
        background-color: var(--card-bg-muted) !important;
    }
    .hover\:bg-gray-200:hover {
        background-color: var(--card-bg-muted) !important;
    }
    [data-theme="dark"] .dark\:hover\:bg-gray-700:hover {
        background-color: var(--card-bg-muted) !important;
    }
}
@media (min-width: 1024px) {
    .profile-shell {
        grid-template-columns: 280px minmax(0, 1fr);
        min-height: calc(100vh - 9rem);
    }
    .profile-sidebar {
        border-bottom: 0;
        border-right: 1px solid var(--profile-border);
    }
}

.profile-page-shell {
    background: var(--profile-surface) !important;
    border-color: var(--profile-border) !important;
}

.profile-page-root .profile-nav-btn.bg-slate-800\/70 {
    background: var(--card-bg-muted) !important;
    border: 1px solid var(--border-color);
    color: var(--profile-text) !important;
}

.profile-page-root .profile-nav-btn.bg-blue-500 {
    background: var(--primary) !important;
    color: var(--on-primary) !important;
    border: 1px solid color-mix(in srgb, var(--primary-strong) 75%, transparent);
}

.profile-page-root .profile-sidebar,
.profile-page-root main > div,
.profile-page-root [data-profile-panel] {
    color: var(--profile-text);
    border-color: var(--profile-border) !important;
    background: var(--profile-surface-muted) !important;
}

.profile-page-root [data-profile-panel] p,
.profile-page-root [data-profile-panel] span,
.profile-page-root [data-profile-panel] label {
    color: var(--profile-muted);
}

.profile-page-root [data-profile-panel] h3,
.profile-page-root [data-profile-panel] h4,
.profile-page-root [data-profile-panel] strong {
    color: var(--profile-text);
}

.profile-shell button:not(:disabled) {
    transition: all 0.2s ease;
}

.profile-shell button:not(:disabled):hover {
    transform: translateY(-2px);
}

#panel-player .checkin-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1rem;
}

#panel-player .checkin-card-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

#panel-player .checkin-card-main {
    background: linear-gradient(135deg, var(--primary), var(--primary-strong));
    border: 1px solid var(--primary-strong);
}

#panel-player .checkin-card-head {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: center;
}

#panel-player .checkin-card-status {
    font-size: 0.8rem;
    color: var(--on-primary);
    background: var(--on-primary-soft-bg);
    border: 1px solid var(--on-primary-soft-border);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    white-space: nowrap;
}

#panel-player .checkin-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 0.9rem;
}

#panel-player .checkin-kpi-grid::after {
    content: "再签到 2 天可获得额外奖励";
    grid-column: 1 / -1;
    display: block;
    margin-top: 0.2rem;
    font-size: 0.82rem;
    color: var(--on-primary-muted);
}

#panel-player .checkin-kpi-item {
    border-radius: 12px;
    padding: 0.75rem;
    background: var(--on-primary-panel-bg);
    border: 1px solid var(--on-primary-panel-border);
}

#panel-player .checkin-kpi-item span {
    display: block;
    font-size: 0.8rem;
    color: var(--on-primary-muted);
}

#panel-player .checkin-kpi-item strong {
    margin-top: 0.25rem;
    display: block;
    color: var(--on-primary);
    font-size: 1.2rem;
    font-weight: 700;
}

#panel-player .checkin-streak-tip {
    margin-top: 0.7rem;
    font-size: 0.82rem;
    color: var(--on-primary);
}

#panel-player .checkin-action-btn {
    margin-top: 1rem;
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 0.7rem 1rem;
    font-weight: 600;
    color: var(--on-primary);
    cursor: pointer;
}

#panel-player .checkin-action-btn.is-idle {
    background: linear-gradient(90deg, var(--primary), var(--primary-strong));
    box-shadow: 0 0 15px var(--primary-glow);
}

#panel-player .checkin-action-btn.is-idle:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 0 22px var(--primary-glow);
}

#panel-player .checkin-action-btn.is-done {
    background: linear-gradient(135deg, var(--button-done-from), var(--button-done-to));
    box-shadow: 0 10px 20px -14px var(--border-color-strong);
}

#panel-player .checkin-reward-row {
    margin-top: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

#panel-player .checkin-reward-item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 14px;
    border: 1px solid var(--border-color);
    padding: 0.45rem 0.8rem;
    color: var(--text-primary);
    font-size: 0.92rem;
    line-height: 1;
}

#panel-player .checkin-reward-item.reward-gold,
#panel-player .checkin-reward-item:nth-child(1) {
    background: var(--reward-gold-bg);
    border-color: var(--reward-gold-border);
    color: var(--reward-gold-text);
}

#panel-player .checkin-reward-item.reward-iron,
#panel-player .checkin-reward-item:nth-child(2) {
    background: var(--reward-iron-bg);
    border-color: var(--reward-iron-border);
    color: var(--reward-iron-text);
}

#panel-player .checkin-reward-icon {
    font-size: 1.3rem;
    line-height: 1;
}

#panel-player .checkin-reward-text {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

#panel-player .checkin-reward-label {
    font-size: 0.82rem;
    opacity: 0.85;
}

#panel-player .checkin-reward-value {
    font-size: 1.1rem;
    font-weight: 700;
}

#panel-player .checkin-calendar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

#panel-player .checkin-calendar-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

#panel-player .checkin-calendar-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 10px;
    border: 1px solid var(--border-color-strong);
    background: var(--card-bg-muted);
    color: var(--text-primary);
}

#panel-player .checkin-calendar-month {
    color: var(--text-muted);
    font-size: 0.85rem;
}

#panel-player .checkin-week-row {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.35rem;
    margin-top: 0.9rem;
    color: var(--text-muted);
    font-size: 0.75rem;
    text-align: center;
}

#panel-player .checkin-calendar-grid {
    margin-top: 0.45rem;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.35rem;
}

#panel-player .checkin-day {
    min-height: 2.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--day-muted-border);
    color: var(--text-primary);
    font-size: 0.85rem;
    background: var(--day-empty-bg);
    cursor: default;
}

#panel-player .checkin-day.is-empty {
    visibility: hidden;
}

#panel-player .checkin-day.is-missed {
    background: var(--day-muted-bg);
    border-color: var(--day-muted-border);
    color: var(--day-muted-text);
}

#panel-player .checkin-day.is-signed {
    background: linear-gradient(135deg, var(--primary), var(--primary-strong));
    box-shadow: 0 0 12px var(--primary-glow);
    border-color: var(--primary-strong);
    color: var(--on-primary);
}

#panel-player .checkin-day.is-today {
    background: var(--day-today-bg);
    border: 1px solid var(--day-today-border);
    color: var(--day-today-text);
    animation: checkinPulse 2s infinite;
}

#panel-player .checkin-day.is-clickable {
    cursor: pointer;
}

#panel-player .checkin-day.is-clickable:hover {
    filter: brightness(1.1);
}

#panel-player .checkin-calendar-legend {
    margin-top: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

#panel-player .checkin-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

#panel-player .checkin-legend-dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 999px;
    display: inline-block;
}

#panel-player .checkin-legend-dot.is-signed {
    background: linear-gradient(135deg, var(--primary), var(--primary-strong));
    box-shadow: 0 0 8px var(--primary-glow);
}

#panel-player .checkin-legend-dot.is-today {
    background: var(--day-today-border);
    border: 1px solid var(--day-today-border);
    animation: checkinPulse 2s infinite;
}

#panel-player .checkin-legend-dot.is-missed {
    background: var(--day-muted-border);
}

#panel-player .checkin-history-toggle {
    width: 100%;
    border-radius: 12px;
    border: 1px solid var(--border-color-strong);
    background: var(--card-bg-muted);
    color: var(--text-primary);
    font-weight: 600;
    padding: 0.7rem 0.9rem;
    text-align: left;
}

#panel-player .checkin-history-list {
    margin-top: 0.75rem;
}

#panel-player .checkin-history-item {
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 0.88rem;
}

#panel-player .checkin-history-empty {
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
    border: 1px dashed var(--border-color-strong);
    background: var(--card-bg);
    color: var(--text-muted);
    font-size: 0.88rem;
}

@keyframes checkinPulse {
    0% {
        box-shadow: 0 0 0 0 var(--pulse-color);
    }
    70% {
        box-shadow: 0 0 0 8px var(--pulse-fade);
    }
    100% {
        box-shadow: 0 0 0 0 var(--pulse-fade);
    }
}

@media (max-width: 640px) {
    #panel-player .checkin-card-head,
    #panel-player .checkin-calendar-head {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="profile-page-root max-w-7xl mx-auto p-4 md:p-6">
    <div class="profile-page-shell overflow-hidden rounded-2xl border border-slate-700/70 bg-slate-900/85 shadow-2xl backdrop-blur">
        <div class="profile-shell">
            <aside class="profile-sidebar p-4 md:p-5 flex flex-col gap-4">
                <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-center text-white">
                    <img
                        src="<?= htmlspecialchars($minotarAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="用户头像"
                        width="96"
                        height="96"
                        class="rounded-full w-24 h-24 mx-auto border-4 border-white/40 object-cover"
                        loading="eager"
                        decoding="async"
                    >
                    <h1 class="mt-3 text-2xl font-bold"><?= htmlspecialchars((string)($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-1 text-blue-100"><?= htmlspecialchars($qqOrUidDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="mt-3 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <p class="mt-3 text-sm text-blue-50">注册时间：<?= htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <nav class="grid grid-cols-1 gap-2">
                    <button type="button" data-profile-tab-btn data-target="panel-game" class="profile-nav-btn rounded-xl bg-blue-500 px-4 py-3 text-base font-medium text-white shadow transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-md">游戏数据</button>
                    <button type="button" data-profile-tab-btn data-target="panel-bind" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">账号绑定</button>
                    <button type="button" data-profile-tab-btn data-target="panel-security" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">安全中心</button>
                    <button type="button" data-profile-tab-btn data-target="panel-player" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">玩家功能</button>
                </nav>

                <div class="mt-auto rounded-xl border border-slate-700/80 bg-slate-800/60 p-3 text-xs text-slate-300">
                     
                </div>
            </aside>

            <main class="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <div class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-4">
                    <h2 class="text-lg font-semibold text-slate-100">个人仪表盘</h2>
                </div>

                <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">游戏账号数量</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($gameAccountCountDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">当前在线</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">累计时长</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </section>

                <div class="min-h-0 flex-1">
                    <section id="panel-game" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">A. 游戏数据</h3>
                        <p class="mt-1 text-sm text-slate-400">游戏账号信息、在线状态、累计时长与死亡次数。</p>

                        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1.4fr_1fr]">
                            <div class="space-y-3">
                                <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                    <p class="text-sm text-slate-400">游戏账号信息</p>
                                    <p class="mt-1 font-semibold text-slate-100"><?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">UUID：<?= htmlspecialchars((string)($profile['mc_uuid'] ?? '--'), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">在线状态</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">累计时长</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">死亡次数</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($deathDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!$hasMcName): ?>
                                    <p class="text-sm text-amber-400">未绑定游戏 UUID，当前显示 Steve 占位形象；绑定后自动更新。</p>
                                <?php endif; ?>
                            </div>

                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4 flex flex-col items-center justify-center">
                                <canvas id="skin_container_3d" class="w-full max-w-xs mx-auto"></canvas>
                                <img id="skin_container_2d" src="<?= htmlspecialchars($minotarBodyUrl, ENT_QUOTES, 'UTF-8') ?>" alt="玩家角色" class="w-full max-w-xs mx-auto" width="220" height="293" loading="eager" decoding="async">
                            </div>
                        </div>
                    </section>

                    <section id="panel-bind" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">B. 账号绑定</h3>
                        <p class="mt-1 text-sm text-slate-400">Minecraft 与 Union 绑定管理。</p>

                        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="font-semibold text-slate-100">Minecraft 绑定</h4>
                                <p class="mt-1 text-sm text-slate-400">当前绑定：<?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!$canChangeMcName): ?>
                                    <p class="mt-2 text-sm font-semibold text-red-400"><?= htmlspecialchars($cooldownMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>

                                <form id="mc-bind-form" method="post" action="/profile/mc-character/update" class="mt-4 space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <label for="mc-name-input" class="block text-sm text-slate-300">Minecraft Username</label>
                                    <input
                                        id="mc-name-input"
                                        name="mc_name"
                                        type="text"
                                        value="<?= htmlspecialchars((string)($profile['mc_username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $canChangeMcName ? '' : 'readonly' ?>
                                        required
                                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    >
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
                                        <?= $canChangeMcName ? '' : 'disabled' ?>
                                    >
                                        保存角色绑定
                                    </button>
                                </form>
                            </div>

                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="font-semibold text-slate-100">Union 绑定</h4>
                                <p class="mt-2 text-sm text-slate-400">
                                    绑定状态：
                                    <?php if ($isMuaBound): ?>
                                        <strong class="text-emerald-400">已绑定</strong>
                                    <?php else: ?>
                                        <strong class="text-amber-400">未绑定</strong>
                                    <?php endif; ?>
                                </p>
                                <?php if (!$isMuaBound): ?>
                                    <a href="/auth/mua" class="mt-4 inline-flex rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">去绑定</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section id="panel-security" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">C. 安全中心</h3>
                        <p class="mt-1 text-sm text-slate-400">修改密码与邮箱快速重置。</p>

                        <form id="password-form" method="post" action="/profile/password/update" class="mt-5 space-y-4 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <label for="old-password-input" class="block text-sm text-slate-300">旧密码</label>
                                <input
                                    id="old-password-input"
                                    name="old_password"
                                    type="password"
                                    required
                                    class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                >
                            </div>
                            <div>
                                <label for="new-password-input" class="block text-sm text-slate-300">新密码</label>
                                <input
                                    id="new-password-input"
                                    name="new_password"
                                    type="password"
                                    minlength="6"
                                    required
                                    class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                >
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">更新密码</button>
                        </form>

                        <div class="mt-4 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <button id="quick-reset-btn" type="button" class="w-full rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">
                                忘记旧密码？向绑定邮箱 (<?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?>) 发送重置链接
                            </button>
                        </div>
                    </section>

                    <section id="panel-player" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">D. 玩家签到系统</h3>
                        <p class="mt-1 text-sm text-slate-400">展示静态签到界面与交互，不涉及真实数据写入。</p>

                        <div class="player-checkin-wrap mt-5 space-y-4">
                            <article class="checkin-card checkin-card-main">
                                <div class="checkin-card-head">
                                    <h4 class="checkin-card-title">每日签到</h4>
                                    <span class="checkin-card-status" data-checkin-status>今日状态：未签到</span>
                                </div>
                                <div class="checkin-kpi-grid">
                                    <div class="checkin-kpi-item">
                                        <span>连续签到</span>
                                        <strong data-checkin-streak>1 天</strong>
                                    </div>
                                    <div class="checkin-kpi-item">
                                        <span>本月签到</span>
                                        <strong data-checkin-month-count>1 次</strong>
                                    </div>
                                </div>
                                <button type="button" class="checkin-action-btn is-idle" data-checkin-action data-state="idle">立即签到</button>
                            </article>

                            <article class="checkin-card checkin-card-reward">
                                <h4 class="checkin-card-title">今日奖励</h4>
                                <div class="checkin-reward-row">
                                    <div class="checkin-reward-item">
                                        <span class="checkin-reward-icon" aria-hidden="true">🪙</span>
                                        <span class="checkin-reward-text">金币 x120</span>
                                    </div>
                                    <div class="checkin-reward-item">
                                        <span class="checkin-reward-icon" aria-hidden="true">⛓️</span>
                                        <span class="checkin-reward-text">铁锭 x10</span>
                                    </div>
                                </div>
                            </article>

                            <article class="checkin-card checkin-card-calendar">
                                <div class="checkin-calendar-head">
                                    <h4 class="checkin-card-title">签到日历</h4>
                                    <div class="checkin-calendar-switch">
                                        <button type="button" class="checkin-calendar-btn" aria-label="上一月（静态）">‹</button>
                                        <span class="checkin-calendar-month">2026 年 4 月</span>
                                        <button type="button" class="checkin-calendar-btn" aria-label="下一月（静态）">›</button>
                                    </div>
                                </div>
                                <div class="checkin-week-row">
                                    <span>一</span>
                                    <span>二</span>
                                    <span>三</span>
                                    <span>四</span>
                                    <span>五</span>
                                    <span>六</span>
                                    <span>日</span>
                                </div>
                                <div class="checkin-calendar-grid">
                                    <div class="checkin-day is-empty"></div>
                                    <div class="checkin-day is-empty"></div>
                                    <div class="checkin-day is-signed">1</div>
                                    <div class="checkin-day is-signed">2</div>
                                    <div class="checkin-day is-signed">3</div>
                                    <div class="checkin-day is-signed">4</div>
                                    <div class="checkin-day is-missed">5</div>
                                    <div class="checkin-day is-missed">6</div>
                                    <div class="checkin-day is-signed">7</div>
                                    <div class="checkin-day is-missed">8</div>
                                    <div class="checkin-day is-signed">9</div>
                                    <div class="checkin-day is-signed">10</div>
                                    <div class="checkin-day is-missed">11</div>
                                    <div class="checkin-day is-missed">12</div>
                                    <div class="checkin-day is-missed">13</div>
                                    <div class="checkin-day is-signed">14</div>
                                    <div class="checkin-day is-missed">15</div>
                                    <div class="checkin-day is-missed">16</div>
                                    <div class="checkin-day is-signed">17</div>
                                    <div class="checkin-day is-missed">18</div>
                                    <div class="checkin-day is-today">19</div>
                                    <div class="checkin-day is-missed">20</div>
                                    <div class="checkin-day is-missed">21</div>
                                    <div class="checkin-day is-missed">22</div>
                                    <div class="checkin-day is-missed">23</div>
                                    <div class="checkin-day is-missed">24</div>
                                    <div class="checkin-day is-missed">25</div>
                                    <div class="checkin-day is-missed">26</div>
                                    <div class="checkin-day is-missed">27</div>
                                    <div class="checkin-day is-missed">28</div>
                                    <div class="checkin-day is-missed">29</div>
                                    <div class="checkin-day is-missed">30</div>
                                    <div class="checkin-day is-empty"></div>
                                    <div class="checkin-day is-empty"></div>
                                    <div class="checkin-day is-empty"></div>
                                </div>
                                <div class="checkin-calendar-legend">
                                    <span class="checkin-legend-item">
                                        <i class="checkin-legend-dot is-signed"></i>
                                        已签到
                                    </span>
                                    <span class="checkin-legend-item">
                                        <i class="checkin-legend-dot is-today"></i>
                                        今日
                                    </span>
                                    <span class="checkin-legend-item">
                                        <i class="checkin-legend-dot is-missed"></i>
                                        未签到
                                    </span>
                                </div>
                            </article>

                            <article class="checkin-card checkin-card-history">
                                <button
                                    type="button"
                                    class="checkin-history-toggle"
                                    data-checkin-history-toggle
                                    aria-expanded="false"
                                    aria-controls="checkin-history-list"
                                >
                                    最近记录
                                </button>
                                <div id="checkin-history-list" class="checkin-history-list" hidden>
                                    <div class="checkin-history-item">2026-04-17 +120金币</div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</div>

<script src="https://unpkg.com/skinview3d@3.4.1/bundles/skinview3d.bundle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('skin_container_3d');
    const img2d = document.getElementById('skin_container_2d');
    const rawSkinUrl = "<?= htmlspecialchars((string)$finalRawSkinUrl, ENT_QUOTES, 'UTF-8') ?>";

    const isMua = <?= $useMuaSkinRender ? 'true' : 'false' ?>;
    const finalSkinUrl = isMua
        ? ('/api/skin-proxy?url=' + encodeURIComponent(rawSkinUrl))
        : rawSkinUrl;

    if (!canvas || !img2d || typeof skinview3d === 'undefined') {
        return;
    }

    canvas.style.display = 'none';
    img2d.style.display = 'block';

    const skinViewer = new skinview3d.SkinViewer({
        canvas: canvas,
        width: 220,
        height: 293
    });

    try {
        skinViewer.loadSkin(finalSkinUrl)
            .then(() => {
                canvas.style.display = 'block';
                img2d.style.display = 'none';
                if (skinViewer.animations) {
                    skinViewer.animations.add(new skinview3d.IdleAnimation());
                }
            })
            .catch((err) => {
                console.warn('3D Skin load failed, falling back to 2D', err);
                canvas.style.display = 'none';
                img2d.style.display = 'block';
            });
    } catch (err) {
        console.warn('skinview3d loadSkin threw an exception, falling back to 2D', err);
        canvas.style.display = 'none';
        img2d.style.display = 'block';
    }
});
</script>
<script>
(() => {
    const navButtons = document.querySelectorAll('[data-profile-tab-btn]');
    const panels = document.querySelectorAll('[data-profile-panel]');

    const setActiveTab = (targetId) => {
        panels.forEach((panel) => {
            if (panel.id === targetId) {
                panel.style.display = 'block';
            } else {
                panel.style.display = 'none';
            }
        });

        navButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-target') === targetId;
            if (isActive) {
                btn.classList.remove('bg-slate-800/70', 'text-slate-200');
                btn.classList.add('bg-blue-500', 'text-white', 'shadow');
            } else {
                btn.classList.remove('bg-blue-500', 'text-white', 'shadow');
                btn.classList.add('bg-slate-800/70', 'text-slate-200');
            }
        });
    };

    navButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            if (targetId) {
                setActiveTab(targetId);
            }
        });
    });

    panels.forEach((panel) => {
        panel.style.display = 'none';
    });
    setActiveTab('panel-game');
})();
</script>
<script>
(() => {
    const actionBtn = document.querySelector('[data-checkin-action]');
    const statusText = document.querySelector('[data-checkin-status]');
    const streakText = document.querySelector('[data-checkin-streak]');
    const monthCountText = document.querySelector('[data-checkin-month-count]');
    const historyToggle = document.querySelector('[data-checkin-history-toggle]');
    const historyList = document.getElementById('checkin-history-list');
    let historyEmpty = historyList ? historyList.querySelector('[data-checkin-history-empty]') : null;
    const rewardTexts = document.querySelectorAll('#panel-player .checkin-reward-text');

    rewardTexts.forEach((node) => {
        if (!node || node.querySelector('.checkin-reward-value')) return;
        const raw = (node.textContent || '').trim();
        const matched = raw.match(/^(.+?)\s*x\s*(\d+)$/i);
        if (!matched) return;
        const label = document.createElement('span');
        label.className = 'checkin-reward-label';
        label.textContent = matched[1].trim();
        const value = document.createElement('strong');
        value.className = 'checkin-reward-value';
        value.textContent = matched[2];
        node.textContent = '';
        node.appendChild(label);
        node.appendChild(value);
    });

    const syncHistoryEmptyState = () => {
        if (!historyList) return;
        if (!historyEmpty) {
            historyEmpty = document.createElement('div');
            historyEmpty.className = 'checkin-history-empty';
            historyEmpty.setAttribute('data-checkin-history-empty', 'true');
            historyEmpty.textContent = '暂无签到记录';
            historyList.appendChild(historyEmpty);
        }
        const hasHistoryItem = historyList.querySelector('[data-checkin-history-item], .checkin-history-item') !== null;
        historyEmpty.hidden = hasHistoryItem;
    };

    const applyCheckinState = (isDone) => {
        if (!actionBtn || !statusText) return;

        actionBtn.dataset.state = isDone ? 'done' : 'idle';
        actionBtn.classList.toggle('is-idle', !isDone);
        actionBtn.classList.toggle('is-done', isDone);
        actionBtn.textContent = isDone ? '今日已签到' : '立即签到';
        statusText.textContent = isDone ? '今日状态：已签到' : '今日状态：未签到';

        if (streakText) {
            streakText.textContent = isDone ? '2 天' : '1 天';
        }
        if (monthCountText) {
            monthCountText.textContent = isDone ? '2 次' : '1 次';
        }
    };

    if (actionBtn) {
        actionBtn.addEventListener('click', () => {
            const done = actionBtn.dataset.state === 'done';
            applyCheckinState(!done);
        });
        applyCheckinState(false);
    }

    if (historyToggle && historyList) {
        syncHistoryEmptyState();
        historyToggle.addEventListener('click', () => {
            const willExpand = historyList.hidden;
            historyList.hidden = !willExpand;
            historyToggle.setAttribute('aria-expanded', String(willExpand));
        });
    }
})();
</script>
<script>
(() => {
    const parseJson = async (res) => {
        try {
            return await res.json();
        } catch (e) {
            return { success: false, message: '响应解析失败' };
        }
    };

    const submitForm = async (form, url) => {
        const formData = new FormData(form);
        const res = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return parseJson(res);
    };

    const mcForm = document.getElementById('mc-bind-form');
    if (mcForm) {
        mcForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await submitForm(mcForm, '/profile/mc-character/update');
            alert(result.message || (result.success ? '操作成功' : '操作失败'));
            if (result.success) {
                window.location.reload();
            }
        });
    }

    const pwdForm = document.getElementById('password-form');
    if (pwdForm) {
        pwdForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await submitForm(pwdForm, '/profile/password/update');
            alert(result.message || (result.success ? '操作成功' : '操作失败'));
            if (result.success) {
                pwdForm.reset();
            }
        });
    }

    const quickResetBtn = document.getElementById('quick-reset-btn');
    if (quickResetBtn) {
        quickResetBtn.addEventListener('click', async () => {
            quickResetBtn.disabled = true;
            try {
                const fd = new FormData();
                const csrf = document.querySelector('#password-form input[name="csrf_token"]');
                if (csrf) fd.append('csrf_token', csrf.value);
                const res = await fetch('/profile/password/quick-reset', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const result = await parseJson(res);
                alert(result.message || (result.success ? '邮件发送成功' : '发送失败'));
            } finally {
                setTimeout(() => {
                    quickResetBtn.disabled = false;
                }, 800);
            }
        });
    }
})();
</script>
