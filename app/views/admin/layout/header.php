<?php
$adminHeaderUsername = trim((string)($_SESSION['username'] ?? ''));
$adminHeaderAvatarUrl = '/api/avatar?username=' . rawurlencode($adminHeaderUsername !== '' ? $adminHeaderUsername : 'MHF_Steve') . '&size=32';
$adminHeaderAvatarFallback = '/images/owner_avatar.png';
?>
<!-- MOD: TailAdmin Header Start -->
<header class="ta-admin-header-modern">
    <div class="ta-admin-header-inner">
        <div class="ta-admin-header-heading min-w-0">
            <h1 id="admin-header-title" class="ta-admin-header-title truncate text-lg font-semibold">后台总览</h1>
            <p class="ta-admin-header-subtitle">SaaS Operations Console</p>
        </div>

        <div class="ta-admin-header-spacer"></div>

        <div class="ta-admin-header-actions">
            <button type="button" id="theme-toggle" class="ta-admin-theme-toggle" aria-label="切换主题" title="切换主题">
                <svg class="ta-theme-icon ta-theme-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M12 2v2.5M12 19.5V22M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M2 12h2.5M19.5 12H22M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <svg class="ta-theme-icon ta-theme-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M21 14.7a8.5 8.5 0 1 1-11.7-11.7A7 7 0 0 0 21 14.7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                <span class="ta-admin-theme-toggle-label">主题</span>
            </button>

            <div class="relative">
                <button type="button" id="admin-user-menu-trigger" class="ta-admin-user-trigger" aria-haspopup="menu" aria-expanded="false">
                    <img
                        src="<?= htmlspecialchars($adminHeaderAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="玩家头像"
                        width="32"
                        height="32"
                        class="ta-admin-user-avatar"
                        onerror="this.onerror=null;this.src='<?= htmlspecialchars($adminHeaderAvatarFallback, ENT_QUOTES, 'UTF-8') ?>';"
                    >
                    <span class="ta-admin-user-name"><?= htmlspecialchars($adminHeaderUsername, ENT_QUOTES, 'UTF-8') ?></span>
                    <svg class="ta-admin-user-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div id="admin-user-menu-dropdown" class="ta-admin-user-dropdown absolute right-0 mt-2 hidden w-48" role="menu">
                    <a href="/" class="ta-admin-user-link">返回站点</a>
                    <a href="/profile" class="ta-admin-user-link">个人设置</a>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- MOD: TailAdmin Header End -->
