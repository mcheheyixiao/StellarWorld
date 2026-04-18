<?php
$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<style>
.ta-admin-shell {
    --ta-surface-shell: rgba(2, 6, 23, 0.72);
    --ta-surface-sidebar: rgba(2, 6, 23, 0.84);
    --ta-surface-header: rgba(2, 6, 23, 0.92);
    --ta-surface-card: rgba(15, 23, 42, 0.72);
    --ta-surface-input: rgba(15, 23, 42, 0.72);
    --ta-border: rgba(148, 163, 184, 0.22);
    --ta-border-strong: rgba(148, 163, 184, 0.3);
    --ta-shadow: 0 22px 58px -34px rgba(0, 0, 0, 0.74);
    --ta-text-strong: #f8fafc;
    --ta-text-body: #cbd5e1;
    --ta-text-muted: #94a3b8;
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
    display: flex;
    height: 100vh;
    overflow: hidden;
    border: 1px solid var(--ta-border);
    border-radius: 1rem;
    background: var(--ta-surface-shell);
    backdrop-filter: blur(18px);
    color: var(--ta-text-body);
}
[data-theme="light"] .ta-admin-shell {
    --ta-surface-shell: rgba(255, 255, 255, 0.9);
    --ta-surface-sidebar: rgba(248, 250, 252, 0.95);
    --ta-surface-header: rgba(255, 255, 255, 0.96);
    --ta-surface-card: rgba(255, 255, 255, 0.95);
    --ta-surface-input: rgba(255, 255, 255, 0.98);
    --ta-border: rgba(148, 163, 184, 0.38);
    --ta-border-strong: rgba(148, 163, 184, 0.5);
    --ta-shadow: 0 16px 34px -24px rgba(15, 23, 42, 0.24);
    --ta-text-strong: #0f172a;
    --ta-text-body: #1e293b;
    --ta-text-muted: #475569;
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
.ta-sidebar {
    width: 16rem;
    flex-shrink: 0;
    background: var(--ta-surface-sidebar);
    border-right: 1px solid var(--ta-border);
    padding: 1.25rem 1rem;
    overflow-y: auto;
}
.ta-sidebar-brand {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--ta-border);
}
.ta-sidebar-kicker {
    margin: 0;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ta-accent);
}
.ta-sidebar-brand h2 {
    margin: 0.25rem 0 0;
    font-size: 1.05rem;
    color: var(--ta-text-strong);
}
.ta-sidebar-nav {
    display: grid;
    gap: 0.35rem;
}
.ta-sidebar-link {
    width: 100%;
    text-align: left;
    border: 1px solid transparent;
    border-radius: 0.65rem;
    padding: 0.55rem 0.75rem;
    background: transparent;
    color: var(--ta-text-body);
    cursor: pointer;
    transition: all 0.18s ease;
}
.ta-sidebar-link:hover {
    background: var(--ta-accent-soft-bg);
    border-color: var(--ta-accent-border);
}
.ta-sidebar-link.active {
    background: var(--ta-accent-bg);
    border-color: var(--ta-accent-border);
    color: var(--ta-accent);
}
.ta-main-frame {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}
.ta-header {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1.2rem;
    border-bottom: 1px solid var(--ta-border);
    background: var(--ta-surface-header);
}
.ta-header-toggle {
    display: none;
    flex-direction: column;
    gap: 3px;
    padding: 0.45rem;
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.45rem;
    background: transparent;
}
.ta-header-toggle span {
    width: 18px;
    height: 2px;
    border-radius: 3px;
    background: var(--ta-text-body);
}
.ta-header-info h1 {
    margin: 0;
    font-size: 1.05rem;
    color: var(--ta-text-strong);
}
.ta-header-eyebrow {
    margin: 0;
    font-size: 0.74rem;
    color: var(--ta-text-muted);
}
.ta-header-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.88rem;
    color: var(--ta-text-strong);
}
.ta-header-user-name {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}
.ta-header-user-name span {
    max-width: 14rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ta-header-avatar {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    border: 1px solid var(--ta-border);
    background: #1e1e1e;
    image-rendering: pixelated;
    flex: 0 0 auto;
}
.ta-header-link {
    padding: 0.35rem 0.6rem;
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.5rem;
    text-decoration: none;
    color: var(--ta-text-body);
}
.ta-header-link:hover {
    border-color: var(--ta-accent-border);
    color: var(--ta-accent);
}
.ta-main {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 1rem;
}
.ta-main-stack {
    display: grid;
    gap: 1rem;
}
.ta-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.9rem;
    background: var(--ta-surface-card);
    box-shadow: var(--ta-shadow);
    padding: 1rem;
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
.ta-card p,
.ta-help-text {
    color: var(--ta-text-muted);
}
.ta-alert {
    border-radius: 0.75rem;
    padding: 0.9rem 1rem;
}
.ta-alert-danger {
    border: 1px solid var(--ta-danger-border);
    background: var(--ta-danger-bg);
    color: var(--ta-danger-text);
}
.ta-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.ta-stat-card {
    padding: 1.1rem;
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
    color: var(--ta-text-body);
    cursor: pointer;
    text-decoration: none;
}
.ta-btn:hover {
    border-color: var(--ta-accent-border);
    color: var(--ta-accent);
}
.ta-btn-primary {
    background: var(--ta-btn-primary-bg);
    border-color: var(--ta-btn-primary-border);
    color: var(--ta-btn-primary-text);
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
    color: #f59e0b;
}
.ta-realtime-connection-connected {
    color: #22c55e;
}
.ta-realtime-connection-reconnecting {
    color: #f97316;
}
.ta-realtime-connection-disconnected {
    color: #94a3b8;
}
.ta-realtime-connection-error {
    color: #ef4444;
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
    .ta-admin-shell {
        position: relative;
    }
    .ta-realtime-head {
        align-items: flex-start;
    }
    .ta-sidebar {
        position: absolute;
        inset: 0 auto 0 0;
        z-index: 30;
        transform: translateX(-100%);
        transition: transform 0.2s ease;
    }
    .ta-sidebar.is-open {
        transform: translateX(0);
    }
    .ta-header-toggle {
        display: inline-flex;
    }
}
</style>

<div class="ta-admin-shell">
    <?php include BASE_PATH . '/app/views/admin/layout/sidebar.php'; ?>

    <div class="ta-main-frame">
        <?php include BASE_PATH . '/app/views/admin/layout/header.php'; ?>

        <main class="ta-main">
            <?php include BASE_PATH . '/app/views/admin/layout/layout.php'; ?>
        </main>
    </div>
</div>

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

<script>
(function () {
    var sidebar = document.getElementById('admin-sidebar');
    var toggle = document.getElementById('admin-sidebar-toggle');
    if (!sidebar || !toggle) return;

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('is-open');
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 1024) return;
        if (!sidebar.classList.contains('is-open')) return;
        if (sidebar.contains(event.target) || toggle.contains(event.target)) return;
        sidebar.classList.remove('is-open');
    });
})();
</script>
