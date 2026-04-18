<aside id="admin-sidebar" class="ta-sidebar" aria-label="Admin Navigation">
    <div class="ta-sidebar-brand">
        <h2 class="ta-sidebar-brand-title">
            <img src="/images/logo.png" alt="繁星World Logo" class="ta-sidebar-brand-logo">
            <span>繁星World</span>
        </h2>
    </div>

    <nav class="ta-sidebar-nav">
        <button type="button" class="ta-sidebar-link admin-tab-btn active" data-tab-target="tab-dashboard">后台总览</button>
        <?php if (!empty($realtimePanelEnabled)): ?>
            <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-realtime">实时监控</button>
        <?php endif; ?>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-players">玩家管理</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-announcements">公告管理</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-milestones">里程碑管理</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-gallery">相册管理</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-site-settings">站点设置</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-team">团队管理</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-ip-whitelist">IP 白名单</button>
        <button type="button" class="ta-sidebar-link admin-tab-btn" data-tab-target="tab-ip-blacklist">IP 黑名单</button>
    </nav>
</aside>
