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
$feedbackList = is_array($feedbackList ?? null) ? $feedbackList : [];
$feedbackLoadError = trim((string)($feedbackLoadError ?? ''));
$feedbackFlash = is_array($feedbackFlash ?? null) ? $feedbackFlash : null;

$feedbackStatusLabels = [
    'pending' => '待处理',
    'reviewing' => '处理中',
    'need_more_info' => '需要补充',
    'resolved' => '已处理',
    'rejected' => '已驳回',
];
$feedbackCategoryLabels = [
    'report' => '举报玩家',
    'bug' => '漏洞问题',
    'account' => '账号相关',
    'suggestion' => '玩法建议',
    'other' => '其他',
];
$feedbackStatusCounters = [
    'pending' => 0,
    'need_more_info' => 0,
    'resolved' => 0,
];
foreach ($feedbackList as $feedbackItem) {
    $statusKey = strtolower(trim((string)($feedbackItem['status'] ?? 'pending')));
    if (array_key_exists($statusKey, $feedbackStatusCounters)) {
        $feedbackStatusCounters[$statusKey]++;
    }
}
$feedbackPendingCount = (string)$feedbackStatusCounters['pending'];
$feedbackNeedMoreInfoCount = (string)$feedbackStatusCounters['need_more_info'];
$feedbackResolvedCount = (string)$feedbackStatusCounters['resolved'];
$formatFeedbackTime = static function ($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '--';
    }
    $ts = strtotime($text);
    if ($ts === false) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    return date('Y-m-d H:i', $ts);
};

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
    position: relative;
    color: var(--text-primary);
    isolation: isolate;
}
.profile-page-root::before {
    content: "";
    position: absolute;
    inset: -80px -40px auto;
    height: 360px;
    pointer-events: none;
    background:
        radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--primary) 26%, transparent) 0%, transparent 38%),
        radial-gradient(circle at 80% 28%, color-mix(in srgb, var(--primary-strong) 22%, transparent) 0%, transparent 36%),
        radial-gradient(circle at 50% 82%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 42%);
    filter: blur(12px);
    z-index: 0;
}
.profile-shell {
    display: grid;
    grid-template-columns: 1fr;
    position: relative;
    z-index: 1;
    --profile-glass-bg: color-mix(in srgb, var(--card-bg) 86%, transparent);
    --profile-panel-bg: color-mix(in srgb, var(--card-bg) 94%, transparent);
    --profile-soft-bg: color-mix(in srgb, var(--card-bg-muted) 82%, transparent);
    --profile-blue-soft: color-mix(in srgb, var(--primary) 16%, transparent);
    --profile-blue-border: color-mix(in srgb, var(--primary) 35%, transparent);
    --profile-shadow: 0 22px 56px -40px rgba(15, 23, 42, 0.45);
    --profile-text: var(--text-primary);
    --profile-muted: var(--text-muted);
    --profile-border: color-mix(in srgb, var(--border-color-strong) 78%, transparent);
}
[data-theme="dark"] .profile-shell {
    --profile-glass-bg: rgba(15, 23, 42, 0.78);
    --profile-panel-bg: rgba(15, 23, 42, 0.72);
    --profile-soft-bg: rgba(30, 41, 59, 0.72);
    --profile-blue-soft: rgba(59, 130, 246, 0.22);
    --profile-blue-border: rgba(59, 130, 246, 0.4);
}
.profile-sidebar {
    border-bottom: 1px solid var(--profile-border);
    background: var(--profile-soft-bg);
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
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    background: var(--profile-glass-bg) !important;
    border: 1px solid var(--profile-border) !important;
    box-shadow: var(--profile-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.42);
    backdrop-filter: blur(12px);
}
[data-theme="dark"] .profile-page-shell {
    box-shadow: 0 24px 70px -42px rgba(2, 6, 23, 0.78), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.profile-page-root .profile-sidebar {
    color: var(--profile-text);
    border-color: var(--profile-border) !important;
}
.profile-page-root [data-profile-panel] {
    border-radius: 24px;
    background: var(--profile-panel-bg) !important;
    border: 1px solid var(--profile-border) !important;
    box-shadow: 0 18px 40px -34px rgba(15, 23, 42, 0.36);
}
.profile-page-root [data-profile-panel] p,
.profile-page-root [data-profile-panel] label,
.profile-page-root [data-profile-panel] li {
    color: var(--profile-muted);
}
.profile-page-root [data-profile-panel] h3,
.profile-page-root [data-profile-panel] h4,
.profile-page-root [data-profile-panel] strong {
    color: var(--profile-text);
}
.profile-identity-card {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.22);
    background: linear-gradient(146deg, rgba(37, 99, 235, 0.96), rgba(79, 70, 229, 0.94));
    box-shadow: 0 20px 48px -30px rgba(30, 64, 175, 0.7);
}
.profile-identity-card::before,
.profile-identity-card::after {
    content: "";
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}
.profile-identity-card::before {
    width: 180px;
    height: 180px;
    right: -60px;
    top: -85px;
    background: rgba(255, 255, 255, 0.22);
}
.profile-identity-card::after {
    width: 128px;
    height: 128px;
    left: -38px;
    bottom: -56px;
    background: rgba(255, 255, 255, 0.14);
}
.profile-identity-content {
    position: relative;
    z-index: 1;
}
.profile-nav-btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    text-align: left;
    border-radius: 0.95rem;
    border: 1px solid var(--profile-border);
    background: var(--profile-soft-bg) !important;
    color: var(--profile-text) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}
.profile-nav-btn::before {
    content: "";
    position: absolute;
    left: 0.45rem;
    top: 0.65rem;
    bottom: 0.65rem;
    width: 3px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.75);
    opacity: 0;
    transition: opacity 0.2s ease;
}
.profile-nav-icon {
    flex: 0 0 auto;
    width: 1.2rem;
    text-align: center;
    line-height: 1;
}
.profile-page-root .profile-nav-btn.bg-slate-800\/70 {
    background: var(--profile-soft-bg) !important;
    border-color: var(--profile-border);
    color: var(--profile-text) !important;
}
.profile-page-root .profile-nav-btn.bg-blue-500,
.profile-page-root .profile-nav-btn.is-active {
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 88%, white 12%), color-mix(in srgb, var(--primary-strong) 84%, white 16%)) !important;
    color: #ffffff !important;
    border-color: color-mix(in srgb, var(--primary-strong) 75%, transparent);
    box-shadow: 0 14px 28px -22px color-mix(in srgb, var(--primary) 72%, transparent);
}
.profile-page-root .profile-nav-btn.bg-blue-500::before,
.profile-page-root .profile-nav-btn.is-active::before {
    opacity: 1;
}
.profile-sidebar-status {
    border-radius: 0.95rem;
    border: 1px solid var(--profile-border);
    background: var(--profile-soft-bg);
}
.profile-sidebar-status-label {
    font-size: 0.73rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--profile-muted);
}
.profile-sidebar-status-value {
    margin-top: 0.25rem;
    font-size: 0.82rem;
    color: var(--profile-text);
}
.profile-hero-card {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    align-items: flex-start;
    border-radius: 1.35rem;
    border: 1px solid var(--profile-blue-border);
    background: linear-gradient(112deg, color-mix(in srgb, var(--primary) 12%, var(--card-bg) 88%), color-mix(in srgb, var(--card-bg) 95%, white 5%));
    box-shadow: 0 18px 40px -32px rgba(15, 23, 42, 0.34), inset 0 1px 0 rgba(255, 255, 255, 0.45);
    padding: 1.2rem 1.4rem;
}
[data-theme="dark"] .profile-hero-card {
    background: linear-gradient(112deg, rgba(30, 64, 175, 0.35), rgba(15, 23, 42, 0.9));
    box-shadow: 0 18px 42px -32px rgba(2, 6, 23, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.profile-hero-eyebrow {
    margin: 0;
    font-size: 0.73rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--primary) 60%, var(--text-muted) 40%);
}
.profile-hero-title {
    margin-top: 0.35rem;
    font-size: clamp(1.22rem, 2.5vw, 1.62rem);
    font-weight: 700;
    color: var(--profile-text);
}
.profile-hero-subtitle {
    margin-top: 0.35rem;
    font-size: 0.93rem;
    line-height: 1.6;
    color: var(--profile-muted);
}
.profile-hero-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
}
.profile-hero-badges span {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid var(--profile-blue-border);
    background: var(--profile-blue-soft);
    color: var(--profile-text);
    padding: 0.35rem 0.72rem;
    font-size: 0.76rem;
    font-weight: 600;
    white-space: nowrap;
}
.profile-status-strip {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    text-align: left;
    border-radius: 0.95rem;
    border: 1px solid var(--profile-blue-border);
    background: color-mix(in srgb, var(--card-bg-muted) 86%, transparent);
    color: var(--profile-text);
    padding: 0.75rem 0.9rem;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.profile-status-strip-title {
    font-size: 0.83rem;
    font-weight: 600;
}
.profile-status-strip-meta {
    font-size: 0.78rem;
    color: var(--profile-muted);
}
.profile-kpi-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.8rem;
}
.profile-kpi-card {
    border-radius: 1rem;
    border: 1px solid var(--profile-border);
    background: color-mix(in srgb, var(--card-bg) 92%, transparent);
    padding: 1rem;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.profile-kpi-header {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.profile-kpi-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.72rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--profile-blue-soft);
    border: 1px solid var(--profile-blue-border);
    font-size: 1rem;
}
.profile-kpi-title {
    font-size: 0.86rem;
    color: var(--profile-muted);
}
.profile-kpi-value {
    margin-top: 0.65rem;
    font-size: clamp(1.52rem, 3.2vw, 1.9rem);
    font-weight: 700;
    color: var(--profile-text);
}
.profile-kpi-note {
    margin-top: 0.3rem;
    font-size: 0.8rem;
    color: var(--profile-muted);
}
.profile-player-archive {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
.profile-player-card {
    border-radius: 1rem;
    border: 1px solid var(--profile-border);
    background: color-mix(in srgb, var(--card-bg) 92%, transparent);
    padding: 1rem;
}
.profile-player-stat-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}
.profile-player-stat {
    border-radius: 0.9rem;
    border: 1px solid var(--profile-border);
    background: color-mix(in srgb, var(--card-bg-muted) 86%, transparent);
    padding: 0.78rem 0.9rem;
}
.profile-skin-card {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    border: 1px solid var(--profile-border);
    background: linear-gradient(180deg, color-mix(in srgb, var(--primary) 8%, var(--card-bg) 92%), color-mix(in srgb, var(--card-bg) 96%, transparent));
    padding: 1rem;
}
.profile-skin-card::after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 0.6rem;
    width: 58%;
    height: 12px;
    transform: translateX(-50%);
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary) 30%, transparent);
    filter: blur(10px);
    opacity: 0.46;
    pointer-events: none;
}
.profile-page-root [data-profile-panel] input:not([type="file"]),
.profile-page-root [data-profile-panel] select,
.profile-page-root [data-profile-panel] textarea {
    border-color: var(--profile-border) !important;
    background: color-mix(in srgb, var(--card-bg-muted) 88%, transparent) !important;
    color: var(--profile-text) !important;
}
.profile-page-root [data-profile-panel] input:not([type="file"]):focus,
.profile-page-root [data-profile-panel] select:focus,
.profile-page-root [data-profile-panel] textarea:focus {
    border-color: var(--profile-blue-border) !important;
}
.profile-page-root [data-profile-panel] button:not(:disabled),
.profile-page-root [data-profile-panel] a {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
@media (hover: hover) {
    .profile-nav-btn:hover {
        transform: translateY(-1px);
        border-color: var(--profile-blue-border);
        box-shadow: var(--profile-shadow);
    }
    .profile-kpi-card:hover,
    .profile-status-strip:hover {
        transform: translateY(-2px);
        border-color: var(--profile-blue-border);
        box-shadow: var(--profile-shadow);
    }
}
@media (min-width: 768px) {
    .profile-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .profile-player-stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (min-width: 1280px) {
    .profile-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .profile-player-archive {
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    }
}
@media (max-width: 1023px) {
    .profile-sidebar {
        border-right: 0;
    }
}
@media (max-width: 767px) {
    .profile-hero-card {
        flex-direction: column;
    }
    .profile-hero-badges {
        justify-content: flex-start;
    }
    .profile-status-strip {
        flex-direction: column;
        align-items: flex-start;
    }
}
@media (prefers-reduced-motion: reduce) {
    .profile-page-root *,
    .profile-page-root *::before,
    .profile-page-root *::after {
        transition: none !important;
        animation: none !important;
    }
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

#panel-feedback .profile-feedback-layout {
    display: grid;
    gap: 1rem;
    grid-template-columns: 1fr;
}
#panel-feedback .profile-feedback-guide ul {
    margin: 0;
    padding-left: 1.1rem;
    display: grid;
    gap: 0.45rem;
}
#panel-feedback .profile-feedback-guide li {
    color: var(--text-muted);
    font-size: 0.88rem;
    line-height: 1.5;
}
#panel-feedback .profile-feedback-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: 1fr;
}
#panel-feedback .profile-feedback-table-wrap {
    overflow-x: auto;
    width: 100%;
}
#panel-feedback .profile-feedback-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}
#panel-feedback .profile-feedback-table th,
#panel-feedback .profile-feedback-table td {
    border-bottom: 1px solid var(--border-color);
    padding: 0.6rem 0.45rem;
    text-align: left;
    vertical-align: top;
    color: var(--text-primary);
    font-size: 0.86rem;
}
#panel-feedback .profile-feedback-table th {
    color: var(--text-muted);
    font-weight: 600;
}
#panel-feedback .profile-feedback-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid var(--border-color-strong);
    padding: 0.2rem 0.6rem;
    font-size: 0.78rem;
    line-height: 1;
    white-space: nowrap;
}
#panel-feedback .profile-feedback-status--pending {
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.5);
    background: rgba(245, 158, 11, 0.12);
}
#panel-feedback .profile-feedback-status--reviewing {
    color: #38bdf8;
    border-color: rgba(56, 189, 248, 0.5);
    background: rgba(56, 189, 248, 0.12);
}
#panel-feedback .profile-feedback-status--need_more_info {
    color: #fb7185;
    border-color: rgba(251, 113, 133, 0.5);
    background: rgba(251, 113, 133, 0.12);
}
#panel-feedback .profile-feedback-status--resolved {
    color: #22c55e;
    border-color: rgba(34, 197, 94, 0.5);
    background: rgba(34, 197, 94, 0.12);
}
#panel-feedback .profile-feedback-status--rejected {
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.5);
    background: rgba(239, 68, 68, 0.12);
}
#panel-feedback .profile-feedback-file-input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    border: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    clip-path: inset(50%);
    white-space: nowrap;
}
#panel-feedback .profile-feedback-upload {
    border: 1px dashed var(--border-color-strong);
    border-radius: 0.75rem;
    padding: 0.7rem;
    background: rgba(15, 23, 42, 0.36);
}
#panel-feedback .profile-feedback-upload-list {
    margin-top: 0.6rem;
    display: grid;
    gap: 0.55rem;
}
#panel-feedback .profile-feedback-upload-item {
    display: grid;
    grid-template-columns: 52px 1fr auto;
    gap: 0.55rem;
    align-items: center;
    border: 1px solid var(--border-color);
    border-radius: 0.65rem;
    padding: 0.45rem;
    background: rgba(15, 23, 42, 0.45);
}
#panel-feedback .profile-feedback-upload-thumb {
    width: 52px;
    height: 52px;
    border-radius: 0.5rem;
    object-fit: cover;
    border: 1px solid var(--border-color);
}
#panel-feedback .profile-feedback-upload-meta {
    min-width: 0;
}
#panel-feedback .profile-feedback-upload-name {
    font-size: 0.82rem;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#panel-feedback .profile-feedback-upload-size {
    font-size: 0.74rem;
    color: var(--text-muted);
}
#panel-feedback .profile-feedback-supplement-row td {
    background: rgba(15, 23, 42, 0.3);
}
#panel-feedback .profile-feedback-supplement-form {
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    padding: 0.8rem;
    background: rgba(15, 23, 42, 0.35);
}
@media (min-width: 768px) {
    #panel-feedback .profile-feedback-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
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
                <div class="profile-identity-card p-6 text-center text-white">
                    <div class="profile-identity-content">
                        <img
                            src="<?= htmlspecialchars($minotarAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="用户头像"
                            width="96"
                            height="96"
                            class="rounded-full w-24 h-24 mx-auto border-4 border-white/50 object-cover"
                            loading="eager"
                            decoding="async"
                        >
                        <h1 class="mt-3 text-2xl font-bold"><?= htmlspecialchars((string)($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="mt-1 text-blue-100"><?= htmlspecialchars($qqOrUidDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                        <span class="mt-3 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <p class="mt-3 text-sm text-blue-50">注册时间：<?= htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-2 text-xs text-blue-100/95"><?= $hasMcName ? '已绑定 Minecraft 角色' : '暂未绑定 Minecraft 角色' ?></p>
                    </div>
                </div>

                <nav class="grid grid-cols-1 gap-2">
                    <button type="button" data-profile-tab-btn data-target="panel-game" class="profile-nav-btn rounded-xl bg-blue-500 px-4 py-3 text-base font-medium text-white shadow transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-md"><span class="profile-nav-icon" aria-hidden="true">🎮</span><span>游戏数据</span></button>
                    <button type="button" data-profile-tab-btn data-target="panel-bind" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md"><span class="profile-nav-icon" aria-hidden="true">🔗</span><span>账号绑定</span></button>
                    <button type="button" data-profile-tab-btn data-target="panel-security" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md"><span class="profile-nav-icon" aria-hidden="true">🛡</span><span>安全中心</span></button>
                    <button type="button" data-profile-tab-btn data-target="panel-player" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md"><span class="profile-nav-icon" aria-hidden="true">🎁</span><span>玩家功能</span></button>
                    <button type="button" data-profile-tab-btn data-target="panel-feedback" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md"><span class="profile-nav-icon" aria-hidden="true">📮</span><span>举报反馈</span></button>
                </nav>

                <div class="profile-sidebar-status mt-auto p-3 text-xs">
                    <p class="profile-sidebar-status-label">账号状态</p>
                    <p class="profile-sidebar-status-value">正常 · <?= $hasMcName ? '已绑定 Minecraft' : '未绑定 Minecraft' ?></p>
                    <p class="profile-sidebar-status-label mt-3">服务器身份</p>
                    <p class="profile-sidebar-status-value"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?> · 已认证</p>
                </div>
            </aside>

            <main class="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <div class="profile-hero-card">
                    <div>
                        <p class="profile-hero-eyebrow">Stellar Player Hub</p>
                        <h2 class="profile-hero-title">欢迎回来，<?= htmlspecialchars((string)($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="profile-hero-subtitle">这里是你的繁星World个人中心，管理游戏数据、账号安全、签到与反馈工单。</p>
                    </div>
                    <div class="profile-hero-badges">
                        <span><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span>账号正常</span>
                        <span><?= $hasMcName ? '已绑定 Minecraft' : '未绑定 Minecraft' ?></span>
                    </div>
                </div>

                <button type="button" class="profile-status-strip" data-profile-status-target="panel-feedback">
                    <span class="profile-status-strip-title">📮 举报反馈状态</span>
                    <span class="profile-status-strip-meta">待处理 <?= htmlspecialchars($feedbackPendingCount, ENT_QUOTES, 'UTF-8') ?> · 需要补充 <?= htmlspecialchars($feedbackNeedMoreInfoCount, ENT_QUOTES, 'UTF-8') ?> · 已处理 <?= htmlspecialchars($feedbackResolvedCount, ENT_QUOTES, 'UTF-8') ?></span>
                </button>

                <section class="profile-kpi-grid">
                    <div class="profile-kpi-card">
                        <div class="profile-kpi-header">
                            <span class="profile-kpi-icon" aria-hidden="true">🎮</span>
                            <p class="profile-kpi-title">游戏账号</p>
                        </div>
                        <p class="profile-kpi-value"><?= htmlspecialchars($gameAccountCountDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="profile-kpi-note"><?= $hasMcName ? ('已绑定 ' . htmlspecialchars($mcUsername, ENT_QUOTES, 'UTF-8')) : '尚未绑定 Minecraft 角色' ?></p>
                    </div>
                    <div class="profile-kpi-card">
                        <div class="profile-kpi-header">
                            <span class="profile-kpi-icon" aria-hidden="true">🟢</span>
                            <p class="profile-kpi-title">在线状态</p>
                        </div>
                        <p class="profile-kpi-value"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="profile-kpi-note">等待服务器同步</p>
                    </div>
                    <div class="profile-kpi-card">
                        <div class="profile-kpi-header">
                            <span class="profile-kpi-icon" aria-hidden="true">⏱</span>
                            <p class="profile-kpi-title">累计时长</p>
                        </div>
                        <p class="profile-kpi-value"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="profile-kpi-note">插件数据同步后显示</p>
                    </div>
                </section>

                <div class="min-h-0 flex-1">
                    <section id="panel-game" data-profile-panel class="profile-panel-card rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">玩家档案</h3>
                        <p class="mt-1 text-sm text-slate-400">游戏账号信息、在线状态、累计时长与死亡次数。</p>

                        <div class="profile-player-archive mt-5">
                            <div class="space-y-3">
                                <div class="profile-player-card">
                                    <p class="text-sm text-slate-400">玩家身份信息</p>
                                    <p class="mt-1 font-semibold text-slate-100"><?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">UUID：<?= htmlspecialchars((string)($profile['mc_uuid'] ?? '--'), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="profile-player-stat-grid">
                                    <div class="profile-player-stat">
                                        <p class="text-xs text-slate-400">在线状态</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="profile-player-stat">
                                        <p class="text-xs text-slate-400">累计时长</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="profile-player-stat">
                                        <p class="text-xs text-slate-400">死亡次数</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($deathDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!$hasMcName): ?>
                                    <p class="text-sm text-amber-400">未绑定游戏 UUID，当前显示 Steve 占位形象；绑定后自动更新。</p>
                                <?php endif; ?>
                            </div>

                            <div class="profile-skin-card flex flex-col items-center justify-center">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Skin Preview</p>
                                <canvas id="skin_container_3d" class="w-full max-w-xs mx-auto"></canvas>
                                <img id="skin_container_2d" src="<?= htmlspecialchars($minotarBodyUrl, ENT_QUOTES, 'UTF-8') ?>" alt="玩家角色" class="w-full max-w-xs mx-auto" width="220" height="293" loading="eager" decoding="async">
                            </div>
                        </div>
                    </section>

                    <section id="panel-bind" data-profile-panel class="profile-panel-card rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">账号绑定</h3>
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

                    <section id="panel-security" data-profile-panel class="profile-panel-card rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">安全中心</h3>
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

                    <?php if (false): ?>
                    <section id="panel-player" data-profile-panel class="profile-panel-card rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">玩家签到系统</h3>
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
                    <?php endif; ?>

                    <section
                        id="panel-player"
                        data-profile-panel
                        data-checkin-csrf="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6"
                    >
                        <h3 class="text-lg font-semibold text-slate-100">玩家签到系统</h3>
                        <p class="mt-1 text-sm text-slate-400">参与每日签到,可以获取海量资源</p>

                        <div class="player-checkin-wrap mt-5 space-y-4">
                            <article class="checkin-card checkin-card-main">
                                <div class="checkin-card-head">
                                    <h4 class="checkin-card-title">每日签到</h4>
                                    <span class="checkin-card-status" data-checkin-status>今日状态：加载中</span>
                                </div>
                                <div class="checkin-kpi-grid">
                                    <div class="checkin-kpi-item">
                                        <span>连续签到</span>
                                        <strong data-checkin-streak>0 天</strong>
                                    </div>
                                    <div class="checkin-kpi-item">
                                        <span>本月签到</span>
                                        <strong data-checkin-month-count>0 次</strong>
                                    </div>
                                </div>
                                <p class="checkin-streak-tip" data-checkin-summary-tip>累计签到 0 天，最近发放状态 --</p>
                                <button type="button" class="checkin-action-btn is-idle" data-checkin-action data-state="idle" disabled>加载中...</button>
                                <p class="mt-3 text-xs text-slate-400" data-checkin-bind-hint>正在读取 MC 绑定状态...</p>
                            </article>

                            <article class="checkin-card checkin-card-reward">
                                <h4 class="checkin-card-title">今日奖励预览</h4>
                                <div class="checkin-reward-row" data-checkin-reward-list>
                                    <div class="checkin-reward-item">
                                        <span class="checkin-reward-icon" aria-hidden="true">🎁</span>
                                        <span class="checkin-reward-text">正在加载奖励...</span>
                                    </div>
                                </div>
                            </article>

                            <article class="checkin-card checkin-card-calendar">
                                <div class="checkin-calendar-head">
                                    <h4 class="checkin-card-title">签到日历</h4>
                                    <div class="checkin-calendar-switch">
                                        <span class="checkin-calendar-month" data-checkin-month-label>--</span>
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
                                <div class="checkin-calendar-grid" data-checkin-calendar-grid></div>
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
                                    最近签到记录
                                </button>
                                <div id="checkin-history-list" class="checkin-history-list" hidden>
                                    <div class="checkin-history-empty" data-checkin-history-empty="true">正在加载...</div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section id="panel-feedback" data-profile-panel class="profile-panel-card rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">举报反馈</h3>
                        <p class="mt-1 text-sm text-slate-400">提交举报、漏洞反馈或建议，并在站内追踪处理状态。</p>

                        <?php if (is_array($feedbackFlash) && isset($feedbackFlash['type'], $feedbackFlash['message'])): ?>
                            <div class="mt-4 rounded-lg border px-3 py-2 text-sm <?= (string)$feedbackFlash['type'] === 'success' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300' : 'border-red-500/50 bg-red-500/10 text-red-300' ?>">
                                <?= htmlspecialchars((string)$feedbackFlash['message'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($feedbackLoadError !== ''): ?>
                            <div class="mt-4 rounded-lg border border-red-500/50 bg-red-500/10 px-3 py-2 text-sm text-red-300">
                                <?= htmlspecialchars($feedbackLoadError, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <div class="profile-feedback-layout mt-5">
                            <article class="profile-feedback-guide rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="text-base font-semibold text-slate-100">填写指南</h4>
                                <ul class="mt-3">
                                    <li>标题尽量具体，建议包含场景、时间和对象。</li>
                                    <li>举报玩家时请填写对方游戏名，便于管理员快速核查。</li>
                                    <li>证据支持外链与图片上传，图片最多 3 张，每张不超过 5MB。</li>
                                    <li>状态说明：待处理、处理中、需要补充、已处理、已驳回。</li>
                                </ul>
                            </article>

                            <article class="profile-feedback-form rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="text-base font-semibold text-slate-100">提交反馈</h4>
                                <form id="profile-feedback-form" method="post" action="/profile/feedback/create" enctype="multipart/form-data" class="mt-3 space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                                    <div class="profile-feedback-grid">
                                        <div>
                                            <label for="feedback-category" class="block text-sm text-slate-300">类型</label>
                                            <select id="feedback-category" name="category" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                                <option value="report">举报玩家</option>
                                                <option value="bug">漏洞问题</option>
                                                <option value="account">账号相关</option>
                                                <option value="suggestion">玩法建议</option>
                                                <option value="other">其他</option>
                                            </select>
                                        </div>
                                        <div data-feedback-optional-field data-feedback-categories="report">
                                            <label for="feedback-target-player" class="block text-sm text-slate-300">被举报玩家（可选）</label>
                                            <input id="feedback-target-player" name="target_player" type="text" maxlength="64" placeholder="仅字母数字下划线" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        </div>
                                        <div data-feedback-optional-field data-feedback-categories="report,bug,account">
                                            <label for="feedback-occurred-at" class="block text-sm text-slate-300">发生时间（可选）</label>
                                            <input id="feedback-occurred-at" name="occurred_at" type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        </div>
                                        <div data-feedback-optional-field data-feedback-categories="bug,suggestion">
                                            <label for="feedback-world" class="block text-sm text-slate-300">世界（可选）</label>
                                            <input id="feedback-world" name="world" type="text" maxlength="64" placeholder="例如：主世界 / 资源服" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        </div>
                                        <div data-feedback-optional-field data-feedback-categories="report,bug">
                                            <label for="feedback-coordinates" class="block text-sm text-slate-300">坐标（可选）</label>
                                            <input id="feedback-coordinates" name="coordinates" type="text" maxlength="64" placeholder="例如：X:123 Y:64 Z:-12" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        </div>
                                        <div data-feedback-optional-field data-feedback-categories="report,bug,account,other">
                                            <label for="feedback-evidence-url" class="block text-sm text-slate-300">证据链接（可选）</label>
                                            <input id="feedback-evidence-url" name="evidence_url" type="url" maxlength="500" placeholder="https://..." class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="feedback-title" class="block text-sm text-slate-300">标题</label>
                                        <input id="feedback-title" name="title" type="text" minlength="3" maxlength="120" required class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    </div>

                                    <div>
                                        <label for="feedback-content" class="block text-sm text-slate-300">详细内容</label>
                                        <textarea id="feedback-content" name="content" rows="5" minlength="10" maxlength="5000" required class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                    </div>

                                    <div>
                                        <label for="feedback-attachments" class="block text-sm text-slate-300">图片上传（可选）</label>
                                        <div data-feedback-upload class="profile-feedback-upload mt-1">
                                            <input
                                                id="feedback-attachments"
                                                name="attachments[]"
                                                type="file"
                                                multiple
                                                accept="image/jpeg,image/png,image/webp"
                                                class="profile-feedback-file-input"
                                            >
                                            <button type="button" class="rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:border-slate-500 hover:bg-slate-700/80" data-feedback-upload-trigger>点击上传图片</button>
                                            <p class="mt-1 text-xs text-slate-400" data-feedback-upload-hint>最多 3 张，仅支持 jpg/jpeg/png/webp，单张最大 5MB。</p>
                                            <div class="profile-feedback-upload-list" data-feedback-upload-list hidden></div>
                                        </div>
                                    </div>

                                    <button type="submit" class="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-md">提交反馈</button>
                                </form>
                            </article>
                        </div>

                        <article class="mt-5 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <h4 class="text-base font-semibold text-slate-100">我的反馈记录</h4>
                            <?php if (empty($feedbackList)): ?>
                                <p class="mt-3 text-sm text-slate-400">暂时没有反馈记录，提交后会在这里显示处理状态。</p>
                            <?php else: ?>
                                <div class="profile-feedback-table-wrap mt-3">
                                    <table class="profile-feedback-table">
                                        <thead>
                                        <tr>
                                            <th>编号</th>
                                            <th>类型</th>
                                            <th>标题</th>
                                            <th>状态</th>
                                            <th>管理员回复</th>
                                            <th>创建时间</th>
                                            <th>更新时间</th>
                                            <th>操作</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($feedbackList as $feedbackItem): ?>
                                            <?php
                                            $feedbackId = (int)($feedbackItem['id'] ?? 0);
                                            $feedbackCategory = strtolower(trim((string)($feedbackItem['category'] ?? 'other')));
                                            $feedbackCategoryLabel = $feedbackCategoryLabels[$feedbackCategory] ?? $feedbackCategory;
                                            $feedbackTitle = (string)($feedbackItem['title'] ?? '');
                                            $feedbackStatus = strtolower(trim((string)($feedbackItem['status'] ?? 'pending')));
                                            $feedbackStatusLabel = $feedbackStatusLabels[$feedbackStatus] ?? $feedbackStatus;
                                            $feedbackStatusClass = 'profile-feedback-status--' . (preg_match('/^[a-z_]+$/', $feedbackStatus) === 1 ? $feedbackStatus : 'pending');
                                            $feedbackReply = trim((string)($feedbackItem['admin_reply'] ?? ''));
                                            $feedbackUserSupplement = trim((string)($feedbackItem['user_supplement'] ?? ''));
                                            $canSupplement = $feedbackStatus === 'need_more_info';
                                            ?>
                                            <tr>
                                                <td>#<?= $feedbackId ?></td>
                                                <td><?= htmlspecialchars($feedbackCategoryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($feedbackTitle, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span class="profile-feedback-status <?= htmlspecialchars($feedbackStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($feedbackStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="text-xs text-slate-400">管理员回复：</div>
                                                        <div><?= $feedbackReply === '' ? '<span class="text-slate-500">--</span>' : nl2br(htmlspecialchars($feedbackReply, ENT_QUOTES, 'UTF-8')) ?></div>
                                                    </div>
                                                    <?php if ($feedbackUserSupplement !== ''): ?>
                                                        <div class="mt-2">
                                                            <div class="text-xs text-slate-400">我的补充：</div>
                                                            <div><?= nl2br(htmlspecialchars($feedbackUserSupplement, ENT_QUOTES, 'UTF-8')) ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $formatFeedbackTime($feedbackItem['created_at'] ?? '') ?></td>
                                                <td><?= $formatFeedbackTime($feedbackItem['updated_at'] ?? '') ?></td>
                                                <td>
                                                    <?php if ($canSupplement): ?>
                                                        <button
                                                            type="button"
                                                            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-slate-900 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-amber-400 hover:shadow-md"
                                                            data-feedback-supplement-toggle
                                                            data-feedback-supplement-target="feedback-supplement-row-<?= $feedbackId ?>"
                                                            aria-expanded="false"
                                                        >补充材料</button>
                                                    <?php else: ?>
                                                        <span class="text-slate-500">--</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php if ($canSupplement): ?>
                                                <tr id="feedback-supplement-row-<?= $feedbackId ?>" class="profile-feedback-supplement-row" hidden>
                                                    <td colspan="8">
                                                        <form method="post" action="/profile/feedback/supplement" enctype="multipart/form-data" class="profile-feedback-supplement-form space-y-3">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="feedback_id" value="<?= $feedbackId ?>">
                                                            <div>
                                                                <label for="feedback-supplement-content-<?= $feedbackId ?>" class="block text-sm text-slate-300">补充说明</label>
                                                                <textarea id="feedback-supplement-content-<?= $feedbackId ?>" name="supplement_content" rows="4" minlength="10" maxlength="5000" required class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                                            </div>
                                                            <div>
                                                                <label for="feedback-supplement-attachments-<?= $feedbackId ?>" class="block text-sm text-slate-300">补充图片（可选）</label>
                                                                <div data-feedback-upload class="profile-feedback-upload mt-1">
                                                                    <input
                                                                        id="feedback-supplement-attachments-<?= $feedbackId ?>"
                                                                        name="attachments[]"
                                                                        type="file"
                                                                        multiple
                                                                        accept="image/jpeg,image/png,image/webp"
                                                                        class="profile-feedback-file-input"
                                                                    >
                                                                    <button type="button" class="rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:border-slate-500 hover:bg-slate-700/80" data-feedback-upload-trigger>点击上传图片</button>
                                                                    <p class="mt-1 text-xs text-slate-400" data-feedback-upload-hint>最多 3 张，仅支持 jpg/jpeg/png/webp，单张最大 5MB。</p>
                                                                    <div class="profile-feedback-upload-list" data-feedback-upload-list hidden></div>
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-wrap gap-2">
                                                                <button type="submit" class="rounded-lg bg-blue-500 px-3 py-2 text-sm font-medium text-white transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-md">提交补充</button>
                                                                <button type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200 transition-all duration-200 ease-in-out hover:border-slate-500 hover:bg-slate-700/60" data-feedback-supplement-cancel data-feedback-supplement-target="feedback-supplement-row-<?= $feedbackId ?>">取消</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>
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
    const root = document.getElementById('panel-player');
    const actionBtn = document.querySelector('[data-checkin-action]');
    const statusText = document.querySelector('[data-checkin-status]');
    const streakText = document.querySelector('[data-checkin-streak]');
    const monthCountText = document.querySelector('[data-checkin-month-count]');
    const summaryTip = document.querySelector('[data-checkin-summary-tip]');
    const bindHint = document.querySelector('[data-checkin-bind-hint]');
    const monthLabel = document.querySelector('[data-checkin-month-label]');
    const calendarGrid = document.querySelector('[data-checkin-calendar-grid]');
    const rewardList = document.querySelector('[data-checkin-reward-list]');
    const historyToggle = document.querySelector('[data-checkin-history-toggle]');
    const historyList = document.getElementById('checkin-history-list');
    const historyEmpty = historyList ? historyList.querySelector('[data-checkin-history-empty]') : null;

    if (!root || !actionBtn || !statusText || !streakText || !monthCountText || !summaryTip || !bindHint || !monthLabel || !calendarGrid || !rewardList || !historyToggle || !historyList || !historyEmpty) {
        return;
    }

    const csrfToken = String(root.getAttribute('data-checkin-csrf') || '');
    const today = new Date();
    let historyItems = [];

    const parseJson = async (res) => {
        try {
            return await res.json();
        } catch (e) {
            return { success: false, message: '响应解析失败' };
        }
    };

    const unwrap = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return {};
        }
        if (payload.data && typeof payload.data === 'object') {
            return payload.data;
        }
        return payload;
    };

    const escapeHtml = (value) => String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const setButtonState = (mode, text, disabled) => {
        actionBtn.dataset.state = mode;
        actionBtn.disabled = !!disabled;
        actionBtn.classList.toggle('is-idle', mode !== 'done');
        actionBtn.classList.toggle('is-done', mode === 'done');
        actionBtn.textContent = text;
    };

    const createRewardNode = (icon, text) => {
        const item = document.createElement('div');
        item.className = 'checkin-reward-item';
        item.innerHTML = '<span class="checkin-reward-icon" aria-hidden="true">' + icon + '</span><span class="checkin-reward-text">' + escapeHtml(text) + '</span>';
        return item;
    };

    const renderRewards = (reward, isBound) => {
        rewardList.innerHTML = '';

        if (!isBound) {
            rewardList.appendChild(createRewardNode('🔒', '绑定 MC 账号后可预览奖励'));
            return;
        }

        if (!reward || typeof reward !== 'object') {
            rewardList.appendChild(createRewardNode('🎁', '当前没有可发放的奖励规则'));
            return;
        }

        let hasAny = false;

        if (Number(reward.coins || 0) > 0) {
            rewardList.appendChild(createRewardNode('🪙', '金币 x' + Number(reward.coins || 0)));
            hasAny = true;
        }

        if (Number(reward.points || 0) > 0) {
            rewardList.appendChild(createRewardNode('⭐', '积分 x' + Number(reward.points || 0)));
            hasAny = true;
        }

        if (Array.isArray(reward.items)) {
            reward.items.forEach((item) => {
                const itemId = String((item && (item.id || item.name)) || '').trim();
                if (!itemId) {
                    return;
                }
                const amount = Math.max(1, Number((item && item.amount) || 1));
                rewardList.appendChild(createRewardNode('📦', itemId + ' x' + amount));
                hasAny = true;
            });
        }

        if (Array.isArray(reward.commands) && reward.commands.length > 0) {
            rewardList.appendChild(createRewardNode('⚙️', '命令 x' + reward.commands.length));
            hasAny = true;
        }

        if (!hasAny) {
            rewardList.appendChild(createRewardNode('🎁', '今日没有额外奖励'));
        }
    };

    const renderCalendar = () => {
        const year = today.getFullYear();
        const month = today.getMonth();
        const firstDay = new Date(year, month, 1);
        const totalDays = new Date(year, month + 1, 0).getDate();
        const mondayIndex = (firstDay.getDay() + 6) % 7;
        const signedDays = new Set(
            historyItems
                .map((item) => String(item.checkin_date || ''))
                .filter((date) => date.startsWith(String(year) + '-' + String(month + 1).padStart(2, '0') + '-'))
                .map((date) => Number(date.slice(-2)))
                .filter((day) => Number.isFinite(day) && day > 0)
        );

        monthLabel.textContent = year + ' 年 ' + String(month + 1).padStart(2, '0') + ' 月';
        calendarGrid.innerHTML = '';

        for (let i = 0; i < mondayIndex; i += 1) {
            const empty = document.createElement('div');
            empty.className = 'checkin-day is-empty';
            calendarGrid.appendChild(empty);
        }

        for (let day = 1; day <= totalDays; day += 1) {
            const node = document.createElement('div');
            node.className = 'checkin-day';
            const isSigned = signedDays.has(day);
            const isToday = day === today.getDate();

            if (isSigned) {
                node.classList.add('is-signed');
            } else if (isToday) {
                node.classList.add('is-today');
            } else if (day < today.getDate()) {
                node.classList.add('is-missed');
            }

            if (isSigned && isToday) {
                node.classList.add('is-today');
            }

            node.textContent = String(day);
            calendarGrid.appendChild(node);
        }
    };

    const rewardSummaryText = (reward) => {
        if (!reward || typeof reward !== 'object') {
            return '无奖励';
        }

        const parts = [];
        if (Number(reward.coins || 0) > 0) {
            parts.push('金币 +' + Number(reward.coins || 0));
        }
        if (Number(reward.points || 0) > 0) {
            parts.push('积分 +' + Number(reward.points || 0));
        }
        if (Array.isArray(reward.items) && reward.items.length > 0) {
            parts.push('物品 x' + reward.items.length);
        }
        if (Array.isArray(reward.commands) && reward.commands.length > 0) {
            parts.push('命令 x' + reward.commands.length);
        }
        return parts.length > 0 ? parts.join(' / ') : '无奖励';
    };

    const renderHistory = () => {
        historyList.querySelectorAll('.checkin-history-item').forEach((node) => node.remove());

        if (historyItems.length === 0) {
            historyEmpty.hidden = false;
            historyEmpty.textContent = '暂无签到记录';
            return;
        }

        historyEmpty.hidden = true;
        historyItems.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'checkin-history-item';
            row.textContent = String(item.checkin_date || '--') + ' | 连续 ' + Number(item.streak_days || 0) + ' 天 | ' + rewardSummaryText(item.reward);
            historyList.appendChild(row);
        });
    };

    const applyStatus = (status) => {
        const isBound = !!status.bound_mc;
        const checkedIn = !!status.checked_in_today;
        const deliveryStatus = status.latest_delivery_status ? String(status.latest_delivery_status) : '--';

        statusText.textContent = checkedIn ? '今日状态：已签到' : '今日状态：未签到';
        streakText.textContent = String(Number(status.streak_days || 0)) + ' 天';
        monthCountText.textContent = String(Number(status.month_days || 0)) + ' 次';
        summaryTip.textContent = '累计签到 ' + String(Number(status.total_days || 0)) + ' 天，最近发放状态 ' + deliveryStatus;

        if (!isBound) {
            bindHint.textContent = '请先在上一个标签页绑定有效的 MC 用户名和 UUID。';
            setButtonState('idle', '先绑定 MC 账号', true);
        } else if (checkedIn) {
            bindHint.textContent = '奖励已进入发放队列，等待插件拉取并 ACK。';
            setButtonState('done', '今日已签到', true);
        } else {
            bindHint.textContent = '会校验今日是否已签到，并创建一条 pending 奖励发放任务。';
            setButtonState('idle', '立即签到', false);
        }

        renderRewards(status.today_reward || null, isBound);
    };

    const loadCheckinData = async () => {
        setButtonState('idle', '加载中...', true);

        const [statusRes, historyRes] = await Promise.all([
            fetch('/api/checkin/status', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }),
            fetch('/api/checkin/history', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
        ]);

        const statusPayload = await parseJson(statusRes);
        if (!statusPayload.success) {
            throw new Error(statusPayload.message || '加载签到状态失败');
        }

        const historyPayload = await parseJson(historyRes);
        historyItems = historyPayload.success ? (unwrap(historyPayload).items || []) : [];

        applyStatus(unwrap(statusPayload));
        renderCalendar();
        renderHistory();
    };

    actionBtn.addEventListener('click', async () => {
        if (actionBtn.disabled) {
            return;
        }

        setButtonState('idle', '签到中...', true);
        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            const res = await fetch('/api/checkin/claim', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const payload = await parseJson(res);
            if (!payload.success) {
                throw new Error(payload.message || '签到失败');
            }

            await loadCheckinData();
            alert(payload.message || '签到成功');
        } catch (error) {
            console.error('Check-in request failed', error);
            alert(error instanceof Error ? error.message : '签到失败');
            try {
                await loadCheckinData();
            } catch (reloadError) {
                console.error('Reload check-in state failed', reloadError);
            }
        }
    });

    historyToggle.addEventListener('click', () => {
        const willExpand = historyList.hidden;
        historyList.hidden = !willExpand;
        historyToggle.setAttribute('aria-expanded', String(willExpand));
    });

    loadCheckinData().catch((error) => {
        console.error('Load check-in state failed', error);
        statusText.textContent = '今日状态：加载失败';
        summaryTip.textContent = '签到状态读取失败，请稍后重试。';
        bindHint.textContent = error instanceof Error ? error.message : '加载失败';
        renderRewards(null, false);
        renderCalendar();
        renderHistory();
    });
})();
</script>
<script>
(() => {
    const navButtons = document.querySelectorAll('[data-profile-tab-btn]');
    const panels = document.querySelectorAll('[data-profile-panel]');
    const statusStripButtons = document.querySelectorAll('[data-profile-status-target]');

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
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
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
    statusStripButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-profile-status-target');
            if (targetId) {
                setActiveTab(targetId);
            }
        });
    });

    panels.forEach((panel) => {
        panel.style.display = 'none';
    });
    const tabParam = String(new URLSearchParams(window.location.search).get('tab') || '').toLowerCase();
    const tabMap = {
        game: 'panel-game',
        bind: 'panel-bind',
        security: 'panel-security',
        player: 'panel-player',
        feedback: 'panel-feedback'
    };
    setActiveTab(tabMap[tabParam] || 'panel-game');
})();
</script>
<?php if (false): ?>
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
<?php endif; ?>
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

    const feedbackForm = document.getElementById('profile-feedback-form');
    const feedbackCategory = document.getElementById('feedback-category');
    const feedbackOptionalFields = Array.from(document.querySelectorAll('[data-feedback-optional-field]'));

    const normalizeFeedbackCategory = (value) => String(value || '').trim().toLowerCase();

    const shouldShowFeedbackField = (field, category) => {
        const categoriesRaw = String(field.getAttribute('data-feedback-categories') || '').trim();
        if (categoriesRaw === '' || categoriesRaw === '*') {
            return true;
        }

        const categories = categoriesRaw
            .split(',')
            .map((item) => normalizeFeedbackCategory(item))
            .filter((item) => item !== '');
        return categories.includes(category);
    };

    const syncFeedbackOptionalFields = () => {
        const category = normalizeFeedbackCategory(feedbackCategory ? feedbackCategory.value : 'other');
        for (const field of feedbackOptionalFields) {
            const visible = shouldShowFeedbackField(field, category);
            field.hidden = !visible;
            field.setAttribute('aria-hidden', visible ? 'false' : 'true');

            const controls = field.querySelectorAll('input, select, textarea');
            controls.forEach((control) => {
                control.disabled = !visible;
                if (!visible) {
                    control.value = '';
                }
            });
        }
    };

    if (feedbackCategory && feedbackOptionalFields.length > 0) {
        syncFeedbackOptionalFields();
        feedbackCategory.addEventListener('change', syncFeedbackOptionalFields);
    }

    const formatFileSize = (bytes) => {
        const size = Math.max(0, Number(bytes || 0));
        if (size >= 1024 * 1024) {
            return (size / (1024 * 1024)).toFixed(2) + ' MB';
        }
        if (size >= 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }
        return size + ' B';
    };

    const updateFileInput = (input, files) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (typeof DataTransfer === 'undefined') {
            return;
        }
        const transfer = new DataTransfer();
        files.forEach((file) => {
            transfer.items.add(file);
        });
        input.files = transfer.files;
    };

    const initFeedbackUploadWidget = (root) => {
        if (!(root instanceof HTMLElement)) return;

        const input = root.querySelector('input[type="file"][name="attachments[]"]');
        const trigger = root.querySelector('[data-feedback-upload-trigger]');
        const listNode = root.querySelector('[data-feedback-upload-list]');
        const hintNode = root.querySelector('[data-feedback-upload-hint]');
        if (!(input instanceof HTMLInputElement) || !(trigger instanceof HTMLElement) || !(listNode instanceof HTMLElement)) {
            return;
        }

        const maxCount = 3;
        const maxBytes = 5 * 1024 * 1024;
        const allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        const allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        const defaultHint = '最多 3 张，仅支持 jpg/jpeg/png/webp，单张最大 5MB。';
        const selectedFiles = [];
        let previewUrls = [];

        const setHint = (text) => {
            if (!hintNode) return;
            hintNode.textContent = text || defaultHint;
        };

        const syncFiles = () => {
            updateFileInput(input, selectedFiles);
        };

        const cleanupPreviewUrls = () => {
            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            previewUrls = [];
        };

        const renderList = () => {
            cleanupPreviewUrls();
            listNode.innerHTML = '';
            if (selectedFiles.length === 0) {
                listNode.hidden = true;
                setHint(defaultHint);
                return;
            }

            listNode.hidden = false;
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'profile-feedback-upload-item';

                const thumb = document.createElement('img');
                thumb.className = 'profile-feedback-upload-thumb';
                thumb.alt = '已选图片预览';
                const previewUrl = URL.createObjectURL(file);
                previewUrls.push(previewUrl);
                thumb.src = previewUrl;

                const meta = document.createElement('div');
                meta.className = 'profile-feedback-upload-meta';
                const name = document.createElement('div');
                name.className = 'profile-feedback-upload-name';
                name.textContent = String(file.name || '未命名文件');
                const size = document.createElement('div');
                size.className = 'profile-feedback-upload-size';
                size.textContent = formatFileSize(file.size || 0);
                meta.appendChild(name);
                meta.appendChild(size);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'rounded-lg border border-red-500/50 bg-red-500/10 px-2 py-1 text-xs text-red-300 transition hover:bg-red-500/20';
                removeBtn.textContent = '移除';
                removeBtn.addEventListener('click', () => {
                    selectedFiles.splice(index, 1);
                    syncFiles();
                    renderList();
                });

                item.appendChild(thumb);
                item.appendChild(meta);
                item.appendChild(removeBtn);
                listNode.appendChild(item);
            });

            setHint('已选择 ' + selectedFiles.length + ' 张图片');
        };

        const addFiles = (incomingFiles) => {
            incomingFiles.forEach((file) => {
                if (selectedFiles.length >= maxCount) {
                    return;
                }

                const size = Number(file.size || 0);
                if (size > maxBytes) {
                    alert('图片大小不能超过 5MB：' + (file.name || '未知文件'));
                    return;
                }

                const filename = String(file.name || '').toLowerCase();
                const ext = filename.includes('.') ? String(filename.split('.').pop() || '') : '';
                const mime = String(file.type || '').toLowerCase();
                if (!allowedExt.includes(ext) || !allowedMime.includes(mime)) {
                    alert('仅支持 jpg/jpeg/png/webp 图片：' + (file.name || '未知文件'));
                    return;
                }

                selectedFiles.push(file);
            });
        };

        trigger.addEventListener('click', () => {
            input.click();
        });

        input.addEventListener('change', () => {
            const incoming = Array.from(input.files || []);
            if (incoming.length === 0) {
                return;
            }

            const availableSlots = maxCount - selectedFiles.length;
            if (availableSlots <= 0) {
                alert('最多只能上传 3 张图片');
                input.value = '';
                return;
            }
            if (incoming.length > availableSlots) {
                alert('最多只能上传 3 张图片，超出部分已忽略。');
            }

            addFiles(incoming.slice(0, availableSlots));
            syncFiles();
            renderList();
            input.value = '';
        });

        root.addEventListener('submit', () => {
            syncFiles();
        });

        renderList();
    };

    const uploadWidgets = Array.from(document.querySelectorAll('[data-feedback-upload]'));
    uploadWidgets.forEach((widget) => {
        initFeedbackUploadWidget(widget);
    });

    const supplementToggles = Array.from(document.querySelectorAll('[data-feedback-supplement-toggle]'));
    const supplementCancelButtons = Array.from(document.querySelectorAll('[data-feedback-supplement-cancel]'));
    supplementToggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = String(btn.getAttribute('data-feedback-supplement-target') || '');
            if (targetId === '') return;
            const row = document.getElementById(targetId);
            if (!row) return;
            const willShow = row.hidden;
            row.hidden = !willShow;
            btn.setAttribute('aria-expanded', String(willShow));
        });
    });
    supplementCancelButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = String(btn.getAttribute('data-feedback-supplement-target') || '');
            if (targetId === '') return;
            const row = document.getElementById(targetId);
            if (row) {
                row.hidden = true;
            }
            const toggleBtn = document.querySelector('[data-feedback-supplement-toggle][data-feedback-supplement-target="' + targetId + '"]');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    if (feedbackForm) {
        feedbackForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await submitForm(feedbackForm, '/profile/feedback/create');
            alert(result.message || (result.success ? '反馈提交成功' : '反馈提交失败'));
            if (result.success) {
                window.location.href = '/profile?tab=feedback';
            }
        });
    }
})();
</script>
