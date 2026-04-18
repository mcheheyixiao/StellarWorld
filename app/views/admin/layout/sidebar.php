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
        <button type="button" class="admin-tab-btn ta-sidebar-item" data-tab-target="tab-players">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>玩家管理</span>
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
<!-- MOD: TailAdmin Sidebar End -->
