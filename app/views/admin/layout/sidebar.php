<?php
$adminCurrentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$adminCurrentTab = (string)($_GET['tab'] ?? '');

$checkinTabMap = [
    'checkin-rewards' => 'tab-checkin-rewards',
    'checkin-logs' => 'tab-checkin-logs',
    'checkin-stats' => 'tab-checkin-stats',
];

$checkinPathMap = [
    '/admin/checkin/rewards' => 'tab-checkin-rewards',
    '/admin/checkin/logs' => 'tab-checkin-logs',
    '/admin/checkin/stats' => 'tab-checkin-stats',
];

$checkinActiveTarget = $checkinTabMap[$adminCurrentTab] ?? '';
if ($checkinActiveTarget === '') {
    foreach ($checkinPathMap as $pathPrefix => $targetId) {
        if (strpos($adminCurrentPath, $pathPrefix) === 0) {
            $checkinActiveTarget = $targetId;
            break;
        }
    }
}

$checkinExpanded = $checkinActiveTarget !== '' || strpos($adminCurrentPath, '/admin/checkin/') === 0 || $adminCurrentPath === '/admin/checkin';
?>
<!-- MOD: TailAdmin Sidebar Start -->
<aside id="admin-sidebar" aria-label="Admin Navigation" class="ta-sidebar-modern">
    <div class="mb-6 border-b border-white/20 pb-4">
        <div class="flex items-center gap-3">
            <img src="/images/logo.png" alt="繁星World Logo" class="h-8 w-8 rounded-md object-contain">
            <div>
                <p class="text-xs uppercase tracking-wider text-white/70">Admin Panel</p>
                <h2 class="text-lg font-semibold text-white">繁星World</h2>
            </div>
        </div>
    </div>

    <nav class="ta-sidebar-menu space-y-1 pr-1">
        <button type="button" class="admin-tab-btn ta-sidebar-item active" data-tab-target="tab-dashboard">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4A1.5 1.5 0 0 1 11 5.5v4A1.5 1.5 0 0 1 9.5 11h-4A1.5 1.5 0 0 1 4 9.5v-4ZM13 5.5A1.5 1.5 0 0 1 14.5 4h4A1.5 1.5 0 0 1 20 5.5v4A1.5 1.5 0 0 1 18.5 11h-4A1.5 1.5 0 0 1 13 9.5v-4ZM4 14.5A1.5 1.5 0 0 1 5.5 13h4a1.5 1.5 0 0 1 1.5 1.5v4A1.5 1.5 0 0 1 9.5 20h-4A1.5 1.5 0 0 1 4 18.5v-4ZM13 14.5A1.5 1.5 0 0 1 14.5 13h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a1.5 1.5 0 0 1-1.5-1.5v-4Z" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            <span>后台总览</span>
        </button>

        <?php if (!empty($realtimePanelEnabled)): ?>
            <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-realtime">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 12h4l2-5 4 10 2-5h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>实时监控</span>
            </button>
        <?php endif; ?>

        <div class="space-y-1" data-checkin-group>
            <button
                type="button"
                class="ta-sidebar-item <?= $checkinExpanded ? 'active' : '' ?>"
                data-checkin-toggle
                aria-expanded="<?= $checkinExpanded ? 'true' : 'false' ?>"
                aria-controls="checkin-menu-children"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 19h16M6 15h4m4 0h4M6 11h12M8 7h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="flex-1 text-left">签到系统</span>
                <svg
                    class="h-5 w-5 transition-all duration-200 <?= $checkinExpanded ? 'rotate-90' : 'rotate-0' ?>"
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                    data-checkin-chevron
                    style="width: 1rem; height: 1rem;"
                >
                    <path d="m7 5 6 5-6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div id="checkin-menu-children" class="mt-1 space-y-1 <?= $checkinExpanded ? '' : 'hidden' ?>" data-checkin-children>
                <button
                    type="button"
                    class="admin-tab-btn ta-sidebar-item ta-sidebar-subitem pl-8 text-sm <?= $checkinActiveTarget === 'tab-checkin-rewards' ? 'active' : '' ?>"
                    data-tab-target="tab-checkin-rewards"
                    data-checkin-item="tab-checkin-rewards"
                >
                    <span>奖励配置</span>
                </button>
                <button
                    type="button"
                    class="admin-tab-btn ta-sidebar-item ta-sidebar-subitem pl-8 text-sm <?= $checkinActiveTarget === 'tab-checkin-logs' ? 'active' : '' ?>"
                    data-tab-target="tab-checkin-logs"
                    data-checkin-item="tab-checkin-logs"
                >
                    <span>签到记录</span>
                </button>
                <button
                    type="button"
                    class="admin-tab-btn ta-sidebar-item ta-sidebar-subitem pl-8 text-sm <?= $checkinActiveTarget === 'tab-checkin-stats' ? 'active' : '' ?>"
                    data-tab-target="tab-checkin-stats"
                    data-checkin-item="tab-checkin-stats"
                >
                    <span>统计分析</span>
                </button>
            </div>
        </div>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-players">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>玩家管理</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-feedback">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9A1.5 1.5 0 0 1 18.5 16H9l-4 4v-4H5.5A1.5 1.5 0 0 1 4 14.5v-9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>举报反馈</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-announcements">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 11.5 21 5v14l-18-6v-1.5ZM8 13v4.5a2.5 2.5 0 0 0 5 0V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>公告管理</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-milestones">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 6h18M8 6v14m8-14v14M3 18h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>里程碑管理</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-gallery">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
                <path d="m21 16-5.2-5.2a1.5 1.5 0 0 0-2.1 0L8 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>相册管理</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-site-settings">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" stroke="currentColor" stroke-width="1.5"/>
                <path d="M3 12h2m14 0h2M12 3v2m0 14v2M5.6 5.6l1.4 1.4m10 10 1.4 1.4m0-12.8-1.4 1.4m-10 10-1.4 1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>站点设置</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-team">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>团队管理</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-ip-whitelist">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Z" stroke="currentColor" stroke-width="1.5"/>
                <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>IP 白名单</span>
        </button>

        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-ip-blacklist">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                <path d="M8 8l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>IP 黑名单</span>
        </button>
    </nav>
</aside>

<script>
(function () {
    var sidebar = document.getElementById('admin-sidebar');
    if (!sidebar) return;

    var toggle = sidebar.querySelector('[data-checkin-toggle]');
    var children = sidebar.querySelector('[data-checkin-children]');
    var chevron = sidebar.querySelector('[data-checkin-chevron]');
    if (!toggle || !children || !chevron) return;

    function hasActiveChild() {
        return !!children.querySelector('.admin-tab-btn.active');
    }

    function resolvePathTarget() {
        var path = window.location.pathname || '';
        if (path.indexOf('/admin/checkin/rewards') === 0) return 'tab-checkin-rewards';
        if (path.indexOf('/admin/checkin/logs') === 0) return 'tab-checkin-logs';
        if (path.indexOf('/admin/checkin/stats') === 0) return 'tab-checkin-stats';
        return '';
    }

    function setExpanded(expanded) {
        children.classList.toggle('hidden', !expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        chevron.classList.toggle('rotate-90', expanded);
        chevron.classList.toggle('rotate-0', !expanded);
        chevron.style.transform = expanded ? 'rotate(90deg)' : 'rotate(0deg)';

        var path = window.location.pathname || '';
        var pathInCheckin = path.indexOf('/admin/checkin/') === 0 || path === '/admin/checkin';
        toggle.classList.toggle('active', hasActiveChild() || (pathInCheckin && expanded));
    }

    var pathTarget = resolvePathTarget();
    if (pathTarget) {
        var pathBtn = children.querySelector('[data-checkin-item="' + pathTarget + '"]');
        if (pathBtn) pathBtn.classList.add('active');
    }

    var tabInCheckin = false;
    try {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || '';
        tabInCheckin = tab.indexOf('checkin-') === 0;
    } catch (err) {
        tabInCheckin = false;
    }

    var defaultExpanded = toggle.getAttribute('aria-expanded') === 'true' || tabInCheckin || pathTarget !== '' || hasActiveChild();
    setExpanded(defaultExpanded);

    toggle.addEventListener('click', function () {
        setExpanded(children.classList.contains('hidden'));
    });

    children.addEventListener('click', function (event) {
        var childBtn = event.target.closest('.admin-tab-btn');
        if (!childBtn) return;
        setExpanded(true);
    });

    var observer = new MutationObserver(function () {
        if (hasActiveChild()) {
            setExpanded(true);
        } else {
            toggle.classList.remove('active');
        }
    });

    children.querySelectorAll('.admin-tab-btn').forEach(function (btn) {
        observer.observe(btn, { attributes: true, attributeFilter: ['class'] });
    });
})();
</script>
<!-- MOD: TailAdmin Sidebar End -->
