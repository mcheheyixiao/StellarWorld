<?php
$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<style>
:root {
    --ta-surface-shell: rgba(2, 6, 23, 0.82);
    --ta-surface-frame: rgba(15, 23, 42, 0.76);
    --ta-surface-sidebar: rgba(2, 6, 23, 0.84);
    --ta-surface-header: rgba(15, 23, 42, 0.86);
    --ta-surface-card: rgba(15, 23, 42, 0.84);
    --ta-surface-input: rgba(15, 23, 42, 0.9);
    --ta-border: rgba(148, 163, 184, 0.26);
    --ta-border-strong: rgba(148, 163, 184, 0.42);
    --ta-shadow: 0 24px 56px -30px rgba(2, 6, 23, 0.72);
    --ta-text-strong: #f8fafc;
    --ta-text-body: #e2e8f0;
    --ta-text-muted: #cbd5e1;
    --ta-header-title: #e2e8f0;
    --ta-user-name: #e2e8f0;
    --ta-toggle-bg: rgba(30, 41, 59, 0.82);
    --ta-toggle-border: rgba(148, 163, 184, 0.42);
    --ta-toggle-icon: #67e8f9;
    --ta-accent: #67e8f9;
    --ta-accent-bg: rgba(14, 165, 233, 0.22);
    --ta-accent-border: rgba(34, 211, 238, 0.48);
    --ta-accent-soft-bg: rgba(14, 165, 233, 0.14);
    --ta-btn-primary-bg: rgba(14, 165, 233, 0.28);
    --ta-btn-primary-border: rgba(34, 211, 238, 0.42);
    --ta-btn-primary-text: #e0f2fe;
    --ta-danger-border: rgba(248, 113, 113, 0.45);
    --ta-danger-bg: rgba(248, 113, 113, 0.12);
    --ta-danger-text: #fecaca;
}
[data-theme="light"] {
    --ta-surface-shell: rgba(241, 245, 249, 0.94);
    --ta-surface-frame: rgba(241, 245, 249, 0.92);
    --ta-surface-sidebar: rgba(248, 250, 252, 0.96);
    --ta-surface-header: rgba(255, 255, 255, 0.97);
    --ta-surface-card: rgba(255, 255, 255, 0.96);
    --ta-surface-input: rgba(255, 255, 255, 0.99);
    --ta-border: rgba(148, 163, 184, 0.35);
    --ta-border-strong: rgba(148, 163, 184, 0.48);
    --ta-shadow: 0 16px 36px -24px rgba(15, 23, 42, 0.2);
    --ta-text-strong: #0f172a;
    --ta-text-body: #1e293b;
    --ta-text-muted: #475569;
    --ta-header-title: #0f172a;
    --ta-user-name: #1e293b;
    --ta-toggle-bg: rgba(241, 245, 249, 0.95);
    --ta-toggle-border: rgba(148, 163, 184, 0.44);
    --ta-toggle-icon: #0e7490;
    --ta-accent: #0e7490;
    --ta-accent-bg: rgba(14, 165, 233, 0.16);
    --ta-accent-border: rgba(14, 116, 144, 0.48);
    --ta-accent-soft-bg: rgba(14, 165, 233, 0.1);
    --ta-btn-primary-bg: rgba(14, 165, 233, 0.16);
    --ta-btn-primary-border: rgba(14, 116, 144, 0.45);
    --ta-btn-primary-text: #0c4a6e;
    --ta-danger-border: rgba(220, 38, 38, 0.34);
    --ta-danger-bg: rgba(254, 226, 226, 0.9);
    --ta-danger-text: #991b1b;
}
.ta-admin-layout {
    display: flex;
    height: calc(100vh - 1.5rem);
    border-radius: 10px;
    overflow: hidden;
}
.ta-admin-frame {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    background: var(--ta-surface-frame);
    transition: background-color 0.22s ease, color 0.22s ease;
}
.ta-admin-main {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    color: var(--ta-text-body);
}
@media (min-width: 768px) {
    .ta-admin-main {
        padding: 1.5rem;
    }
}
.ta-sidebar-modern {
    width: 18rem;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
    color: #fff;
    background: linear-gradient(to bottom, #4f46e5, #3b82f6);
}
.ta-sidebar-menu {
    flex: 1;
    overflow-y: auto;
}
.ta-sidebar-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 0;
    border-radius: 0.6rem;
    background: transparent;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.9rem;
    font-weight: 500;
    text-align: left;
    padding: 0.72rem 0.88rem;
    cursor: pointer;
    transition: background-color 0.18s ease;
}
.ta-sidebar-item:hover {
    background: rgba(255, 255, 255, 0.15);
}
.ta-sidebar-item.active {
    background: rgba(255, 255, 255, 0.2);
}
.ta-admin-header-modern {
    position: sticky;
    top: 0;
    z-index: 30;
    background: var(--ta-surface-header);
    border-bottom: 1px solid var(--ta-border);
    box-shadow: 0 8px 22px -18px rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
}
.ta-admin-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 1rem;
}
@media (min-width: 768px) {
    .ta-admin-header-inner {
        padding: 0.85rem 1.5rem;
    }
}
.ta-admin-header-spacer {
    flex: 1;
}
.ta-admin-header-title {
    color: var(--ta-header-title);
}
.ta-admin-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.ta-admin-theme-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--ta-toggle-border);
    border-radius: 0.62rem;
    background: var(--ta-toggle-bg);
    color: var(--ta-toggle-icon);
    padding: 0.45rem 0.62rem;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}
.ta-admin-theme-toggle:hover {
    border-color: var(--ta-accent-border);
    color: var(--ta-accent);
}
.ta-admin-theme-toggle .ta-theme-icon {
    width: 1rem;
    height: 1rem;
}
.ta-admin-theme-toggle .ta-theme-icon-sun {
    display: inline-block;
}
.ta-admin-theme-toggle .ta-theme-icon-moon {
    display: none;
}
[data-theme="light"] .ta-admin-theme-toggle .ta-theme-icon-sun {
    display: none;
}
[data-theme="light"] .ta-admin-theme-toggle .ta-theme-icon-moon {
    display: inline-block;
}
.ta-admin-theme-toggle-label {
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1;
}
.ta-admin-user-trigger {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.6rem;
    background: var(--ta-surface-card);
    color: var(--ta-user-name);
    padding: 0.45rem 0.7rem;
    transition: border-color 0.18s ease, background-color 0.18s ease;
}
.ta-admin-user-trigger:hover {
    border-color: var(--ta-accent-border);
}
.ta-admin-user-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid var(--ta-border-strong);
    object-fit: cover;
}
.ta-admin-user-name {
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ta-user-name);
}
.ta-admin-user-icon {
    width: 1rem;
    height: 1rem;
    color: var(--ta-text-muted);
}
.ta-admin-user-dropdown {
    border: 1px solid var(--ta-border);
    border-radius: 0.78rem;
    background: var(--ta-surface-card);
    box-shadow: var(--ta-shadow);
    padding: 0.4rem;
}
.ta-admin-user-link {
    display: block;
    border-radius: 0.55rem;
    padding: 0.45rem 0.65rem;
    color: var(--ta-text-body);
    font-size: 0.86rem;
    text-decoration: none;
}
.ta-admin-user-link:hover {
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
.ta-admin-user-link-btn {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
@media (max-width: 1023px) {
    .ta-sidebar-modern {
        display: none;
    }
}
.ta-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.9rem;
    background: var(--ta-surface-card);
    box-shadow: var(--ta-shadow);
    padding: 1rem;
    color: var(--ta-text-body);
}
.ta-card h1,
.ta-card h2,
.ta-card h3 {
    color: var(--ta-text-strong);
}
.ta-card p,
.ta-card label,
.ta-card span,
.ta-card div,
.ta-table td,
.ta-table th {
    color: inherit;
}
.ta-card p {
    color: var(--ta-text-body);
}
.ta-help-text {
    color: var(--ta-text-muted);
}
.ta-table-wrap {
    overflow-x: auto;
    width: 100%;
}
.ta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}
.ta-table-wide {
    min-width: 900px;
}
.ta-table-team {
    min-width: 520px;
}
.ta-table th,
.ta-table td {
    border-bottom: 1px solid var(--ta-border);
    padding: 0.55rem 0.5rem;
    text-align: left;
    vertical-align: top;
}
.ta-table th {
    color: var(--ta-text-muted);
    font-weight: 600;
}
.ta-table td {
    color: var(--ta-text-body);
}
.ta-table th:last-child,
.ta-table td:last-child {
    text-align: right;
}
.ta-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}
.ta-gallery-item {
    padding: 0.9rem;
}
.ta-gallery-image-wrap {
    border-radius: 0.65rem;
    overflow: hidden;
    margin-bottom: 0.75rem;
}
.ta-file-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
}
.ta-stack {
    display: flex;
    flex-direction: column;
}
.ta-stack-sm {
    gap: 0.3rem;
}
.ta-stack-limit {
    max-width: 180px;
}
.ta-action-stack {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: flex-end;
}
.ta-action-stack form {
    width: 100%;
}
.ta-inline-options {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}
.ta-help-text {
    font-size: 0.88rem;
    color: var(--ta-text-muted);
}
.ta-hidden-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
}
.ta-datetime-input {
    display: none;
}
.ta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    border: 1px solid var(--ta-border-strong);
    padding: 0.38rem 0.72rem;
    font-size: 0.84rem;
    background: var(--ta-surface-input);
    color: var(--ta-text-strong);
    cursor: pointer;
    text-decoration: none;
    transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}
.ta-btn:hover {
    border-color: var(--ta-accent-border);
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
.ta-btn-secondary {
    background: var(--ta-surface-input);
    color: var(--ta-text-strong);
}
.ta-btn-primary {
    background: var(--ta-btn-primary-bg);
    border-color: var(--ta-btn-primary-border);
    color: var(--ta-btn-primary-text);
}
.ta-btn-primary:hover {
    background: var(--ta-accent-bg);
    color: var(--ta-accent);
}
.ta-kpi-shell,
.ta-kpi-card {
    border: 1px solid var(--ta-border);
    background: var(--ta-surface-card) !important;
    box-shadow: var(--ta-shadow);
}
.ta-kpi-card {
    border-radius: 0.8rem;
}
.ta-kpi-shell h1 {
    color: var(--ta-text-strong);
}
.ta-kpi-shell > p {
    color: var(--ta-text-body);
}
.ta-kpi-label {
    color: var(--ta-text-muted) !important;
}
.ta-kpi-value {
    color: var(--ta-text-strong) !important;
}
.ta-kpi-icon {
    background: var(--ta-accent-soft-bg) !important;
    color: var(--ta-accent) !important;
}
@media (max-width: 640px) {
    .ta-admin-theme-toggle-label {
        display: none;
    }
}
.ta-tab-content.tab-hidden {
    display: none !important;
}
.ta-realtime-panel {
    display: grid;
    gap: 1rem;
}
.ta-realtime-head {
    display: flex;
    justify-content: space-between;
    gap: 0.85rem;
    align-items: center;
    flex-wrap: wrap;
}
.ta-realtime-connection {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--ta-border-strong);
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
    background: rgba(148, 163, 184, 0.1);
}
.ta-realtime-connection-dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 999px;
    background: currentColor;
    box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.16);
}
.ta-realtime-connection-pending {
    color: #f59e0b !important;
}
.ta-realtime-connection-connected {
    color: #22c55e !important;
}
.ta-realtime-connection-reconnecting {
    color: #f59e0b !important;
}
.ta-realtime-connection-disconnected {
    color: #ef4444 !important;
}
.ta-realtime-connection-error {
    color: #ef4444 !important;
}
.ta-realtime-connection-connected .ta-realtime-connection-dot {
    background: #22c55e;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.22);
}
.ta-realtime-connection-pending .ta-realtime-connection-dot,
.ta-realtime-connection-reconnecting .ta-realtime-connection-dot {
    background: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.22);
}
.ta-realtime-connection-disconnected .ta-realtime-connection-dot,
.ta-realtime-connection-error .ta-realtime-connection-dot {
    background: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.22);
}
.ta-realtime-metrics {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
}
.ta-realtime-metric {
    padding: 0.85rem;
}
.ta-realtime-metric h3 {
    margin: 0 0 0.4rem;
    font-size: 0.8rem;
    color: var(--ta-text-muted);
}
.ta-realtime-metric p {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--ta-text-strong);
}
.ta-realtime-metric p.ta-metric-good {
    color: #22c55e !important;
}
.ta-realtime-metric p.ta-metric-warn {
    color: #f59e0b !important;
}
.ta-realtime-metric p.ta-metric-bad {
    color: #ef4444 !important;
}
.ta-realtime-content-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}
.ta-realtime-section {
    padding: 0.9rem;
}
.ta-realtime-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    margin-bottom: 0.75rem;
}
.ta-realtime-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.15rem 0.58rem;
    font-size: 0.76rem;
    border: 1px solid var(--ta-accent-border);
    background: var(--ta-accent-soft-bg);
    color: var(--ta-accent);
}
.ta-realtime-player-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.5rem;
}
.ta-realtime-player-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid var(--ta-border);
    border-radius: 0.65rem;
    padding: 0.5rem 0.7rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-realtime-plugin-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 4.5rem;
    border-radius: 999px;
    padding: 0.2rem 0.62rem;
    font-size: 0.76rem;
    border: 1px solid var(--ta-border-strong);
}
.ta-realtime-plugin-enabled {
    color: #22c55e;
    border-color: rgba(34, 197, 94, 0.5);
    background: rgba(34, 197, 94, 0.12);
}
.ta-realtime-plugin-disabled {
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.46);
    background: rgba(148, 163, 184, 0.14);
}
.ta-realtime-chat-stream {
    display: grid;
    gap: 0.5rem;
    max-height: 22rem;
    overflow-y: auto;
}
.ta-realtime-chat-item {
    border: 1px solid var(--ta-border);
    border-radius: 0.65rem;
    padding: 0.55rem 0.7rem;
    background: rgba(148, 163, 184, 0.08);
}
.ta-realtime-chat-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    margin-bottom: 0.28rem;
    font-size: 0.78rem;
}
.ta-realtime-chat-player {
    font-weight: 600;
    color: var(--ta-text-strong);
}
.ta-realtime-chat-time {
    color: var(--ta-text-muted);
}
.ta-realtime-chat-message {
    margin: 0;
    font-size: 0.9rem;
    color: var(--ta-text-body);
    white-space: pre-wrap;
    word-break: break-word;
}
.ta-realtime-empty {
    color: var(--ta-text-muted);
    font-size: 0.9rem;
    text-align: center;
    padding: 0.9rem 0.65rem;
}
#admin-main-content input[type="text"],
#admin-main-content input[type="number"],
#admin-main-content input[type="datetime-local"],
#admin-main-content input[type="file"],
#admin-main-content input[type="search"],
#admin-main-content input[type="email"],
#admin-main-content input[type="password"],
#admin-main-content textarea,
#admin-main-content select {
    width: 100%;
    margin-top: 0.3rem;
    padding: 0.5rem 0.65rem;
    background: var(--ta-surface-input);
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.55rem;
    color: var(--ta-text-body);
}
#admin-main-content input::placeholder,
#admin-main-content textarea::placeholder {
    color: var(--ta-text-muted);
}
[data-theme="light"] #admin-main-content input:focus,
[data-theme="light"] #admin-main-content textarea:focus,
[data-theme="light"] #admin-main-content select:focus {
    border-color: rgba(14, 116, 144, 0.5);
    box-shadow: 0 0 0 1px rgba(14, 116, 144, 0.18), 0 0 18px -8px rgba(14, 116, 144, 0.28);
}
[data-theme="light"] .ta-card code {
    background: rgba(226, 232, 240, 0.85);
    color: #0f172a;
    border-radius: 0.35rem;
    padding: 0.08rem 0.3rem;
}
#admin-main-content textarea {
    resize: vertical;
}
#admin-main-content td > form {
    display: inline-block;
    margin-right: 0.35rem;
}
#admin-main-content td > form:last-child {
    margin-right: 0;
}
@media (max-width: 1024px) {
    .ta-realtime-head {
        align-items: flex-start;
    }
}
</style>

<!-- MOD: TailAdmin Layout Start -->
<div class="ta-admin-layout">
    <?php include BASE_PATH . '/app/views/admin/layout/sidebar.php'; ?>

    <div class="ta-admin-frame">
        <?php include BASE_PATH . '/app/views/admin/layout/header.php'; ?>

        <main class="ta-admin-main">
            <?php include BASE_PATH . '/app/views/admin/layout/layout.php'; ?>
        </main>
    </div>
</div>
<!-- MOD: TailAdmin Layout End -->

<script>
window.adminRealtimePanelConfig = <?= json_encode(
    $realtimeWsConfig ?? [
        'enable_realtime_panel' => false,
        'ws_url' => '',
        'ws_auth_token' => '',
        'reconnect_interval_ms' => 3000,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;
</script>
<script src="/scripts/admin-realtime-panel.js"></script>

<script>
function toDatetimeLocalValue(mysqlDatetime) {
    if (!mysqlDatetime) return '';
    var s = String(mysqlDatetime).trim().replace(' ', 'T');
    if (s.length >= 16) return s.slice(0, 16);
    return s;
}

function updatePublishUi(form) {
    if (!form) return;
    var isPublishedEl = form.querySelector('input[name="is_published"]');
    var publishModeEls = form.querySelectorAll('input[name="publish_mode"]');
    var publishTimeEl = form.querySelector('input[name="publish_time"]');
    if (!isPublishedEl || !publishTimeEl || !publishModeEls || publishModeEls.length === 0) return;

    var isPublished = !!isPublishedEl.checked;
    var mode = 'immediate';
    publishModeEls.forEach(function (el) { if (el.checked) mode = el.value; });

    publishModeEls.forEach(function (el) { el.disabled = !isPublished; });

    var shouldShowTime = isPublished && mode === 'scheduled';
    publishTimeEl.style.display = shouldShowTime ? 'inline-block' : 'none';
    publishTimeEl.required = shouldShowTime;
    if (!shouldShowTime) publishTimeEl.value = '';
}

function editAnnouncement(id, title, content, isPublished, createdAt) {
    var form = document.querySelector('form[action="/admin/announcements/save"]');
    if (!form) return;

    var isPublishedEl = form.querySelector('input[name="is_published"]');
    var publishTimeEl = form.querySelector('input[name="publish_time"]');
    var modeImmediateEl = form.querySelector('input[name="publish_mode"][value="immediate"]');
    var modeScheduledEl = form.querySelector('input[name="publish_mode"][value="scheduled"]');

    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="title"]').value = title;
    form.querySelector('textarea[name="content"]').value = content;
    if (isPublishedEl) isPublishedEl.checked = (isPublished === 1);

    var createdMs = createdAt ? Date.parse(String(createdAt).replace(' ', 'T')) : NaN;
    var nowMs = Date.now();
    var isFuture = !isNaN(createdMs) && createdMs > nowMs;

    if (modeImmediateEl && modeScheduledEl) {
        if ((isPublished === 1) && isFuture) {
            modeScheduledEl.checked = true;
            if (publishTimeEl) publishTimeEl.value = toDatetimeLocalValue(createdAt);
        } else {
            modeImmediateEl.checked = true;
            if (publishTimeEl) publishTimeEl.value = '';
        }
    }

    updatePublishUi(form);

    var btn = document.getElementById('announcement-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editMilestone(id, milestoneDate, description) {
    var form = document.querySelector('form[action="/admin/milestones/save"]');
    if (!form) return;
    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="milestone_date"]').value = milestoneDate || '';
    form.querySelector('textarea[name="description"]').value = description || '';
    var btn = document.getElementById('milestone-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editTeamMember(id, username, role) {
    var form = document.querySelector('form[action="/admin/team-members/save"]');
    if (!form) return;
    form.querySelector('input[name="id"]').value = id;
    form.querySelector('input[name="username"]').value = username || '';
    form.querySelector('input[name="role"]').value = role || '';
    var btn = document.getElementById('team-member-submit-btn');
    if (btn) btn.textContent = '保存修改';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

(function () {
    // 公告表单交互
    var form = document.querySelector('form[action="/admin/announcements/save"]');
    if (form) {
        form.addEventListener('change', function (e) {
            if (!e || !e.target) return;
            var name = e.target.name;
            if (name === 'publish_mode' || name === 'is_published') {
                updatePublishUi(form);
            }
        });
        updatePublishUi(form);
    }

    // Tabs 切换逻辑
    var tabButtons = document.querySelectorAll('.admin-tab-btn');
    var tabContents = document.querySelectorAll('.ta-tab-content');

    function activateTab(targetId) {
        tabContents.forEach(function (content) {
            if (content.id === targetId) {
                content.classList.remove('tab-hidden');
            } else {
                content.classList.add('tab-hidden');
            }
        });

        tabButtons.forEach(function (btn) {
            if (btn.getAttribute('data-tab-target') === targetId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-tab-target');
            if (target) {
                activateTab(target);
            }
        });
    });

    // 根据 URL ?tab=xxx 选择初始 Tab
    var params = new URLSearchParams(window.location.search);
    var initial = params.get('tab');
    var map = {
        dashboard: 'tab-dashboard',
        realtime: 'tab-realtime',
        players: 'tab-players',
        users: 'tab-players',
        announcements: 'tab-announcements',
        milestones: 'tab-milestones',
        gallery: 'tab-gallery',
        'site-settings': 'tab-site-settings',
        team: 'tab-team',
        'ip-whitelist': 'tab-ip-whitelist',
        'ip-blacklist': 'tab-ip-blacklist'
    };
    var initialId = map[initial] || 'tab-dashboard';
    if (!document.getElementById(initialId)) {
        initialId = 'tab-dashboard';
    }
    activateTab(initialId);
})();

(function () {
    var userMenuTrigger = document.getElementById('admin-user-menu-trigger');
    var userMenuDropdown = document.getElementById('admin-user-menu-dropdown');
    if (!userMenuTrigger || !userMenuDropdown) return;

    function closeUserMenu() {
        userMenuDropdown.classList.add('hidden');
        userMenuTrigger.setAttribute('aria-expanded', 'false');
    }

    function openUserMenu() {
        userMenuDropdown.classList.remove('hidden');
        userMenuTrigger.setAttribute('aria-expanded', 'true');
    }

    userMenuTrigger.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (userMenuDropdown.classList.contains('hidden')) {
            openUserMenu();
        } else {
            closeUserMenu();
        }
    });

    document.addEventListener('click', function (event) {
        if (userMenuDropdown.contains(event.target) || userMenuTrigger.contains(event.target)) {
            return;
        }
        closeUserMenu();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeUserMenu();
        }
    });
})();

(function () {
    var fileInput = document.getElementById('gallery-upload-input');
    var fileNameSpan = document.getElementById('gallery-file-name');
    if (!fileInput || !fileNameSpan) return;

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length > 0) {
            fileNameSpan.textContent = fileInput.files[0].name;
        } else {
            fileNameSpan.textContent = '未选择任何文件';
        }
    });
})();
</script>

