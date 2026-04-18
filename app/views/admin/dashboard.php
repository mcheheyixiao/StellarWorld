<?php
$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<style>
.ta-admin-shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(18px);
}
.ta-sidebar {
    width: 16rem;
    flex-shrink: 0;
    background: rgba(2, 6, 23, 0.84);
    border-right: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.25rem 1rem;
    overflow-y: auto;
}
.ta-sidebar-brand {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}
.ta-sidebar-kicker {
    margin: 0;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #67e8f9;
}
.ta-sidebar-brand h2 {
    margin: 0.25rem 0 0;
    font-size: 1.05rem;
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
    color: inherit;
    cursor: pointer;
    transition: all 0.18s ease;
}
.ta-sidebar-link:hover {
    background: rgba(14, 165, 233, 0.14);
    border-color: rgba(34, 211, 238, 0.36);
}
.ta-sidebar-link.active {
    background: rgba(14, 165, 233, 0.22);
    border-color: rgba(34, 211, 238, 0.48);
    color: #a5f3fc;
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
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(2, 6, 23, 0.92);
}
.ta-header-toggle {
    display: none;
    flex-direction: column;
    gap: 3px;
    padding: 0.45rem;
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 0.45rem;
    background: transparent;
}
.ta-header-toggle span {
    width: 18px;
    height: 2px;
    border-radius: 3px;
    background: #cbd5e1;
}
.ta-header-info h1 {
    margin: 0;
    font-size: 1.05rem;
}
.ta-header-eyebrow {
    margin: 0;
    font-size: 0.74rem;
    color: #94a3b8;
}
.ta-header-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.88rem;
}
.ta-header-link {
    padding: 0.35rem 0.6rem;
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 0.5rem;
    text-decoration: none;
}
.ta-header-link:hover {
    border-color: rgba(34, 211, 238, 0.45);
    color: #a5f3fc;
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
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 0.9rem;
    background: rgba(15, 23, 42, 0.72);
    box-shadow: 0 22px 58px -34px rgba(0, 0, 0, 0.74);
    padding: 1rem;
}
.ta-alert {
    border-radius: 0.75rem;
    padding: 0.9rem 1rem;
}
.ta-alert-danger {
    border: 1px solid rgba(248, 113, 113, 0.45);
    background: rgba(248, 113, 113, 0.12);
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
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    padding: 0.55rem 0.5rem;
    text-align: left;
    vertical-align: top;
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
    color: #94a3b8;
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
    border: 1px solid rgba(148, 163, 184, 0.3);
    padding: 0.38rem 0.72rem;
    font-size: 0.84rem;
    background: rgba(15, 23, 42, 0.75);
    color: inherit;
    cursor: pointer;
    text-decoration: none;
}
.ta-btn:hover {
    border-color: rgba(34, 211, 238, 0.45);
    color: #a5f3fc;
}
.ta-btn-primary {
    background: rgba(14, 165, 233, 0.28);
    border-color: rgba(34, 211, 238, 0.42);
    color: #e0f2fe;
}
.ta-tab-content.tab-hidden {
    display: none !important;
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
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 0.55rem;
    color: inherit;
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
