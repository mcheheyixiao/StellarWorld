<nav id="navbar" class="fixed left-1/2 top-4 z-50 w-[calc(100%-1.5rem)] max-w-6xl -translate-x-1/2 transition-all duration-300 md:top-6">
    <div class="rounded-3xl border border-white/10 bg-slate-950/70 px-4 py-3 shadow-[0_22px_56px_-28px_rgba(0,0,0,0.8)] backdrop-blur-xl md:px-6">
        <div class="flex items-center justify-between gap-4">
            <a href="/" class="inline-flex items-center gap-3">
                <img src="/images/logo.png" alt="繁星World" class="h-10 w-auto object-contain md:h-12">
                <span class="hidden text-fusion-pixel text-sm text-cyan-200 md:inline">繁星World</span>
            </a>

            <div class="navbar-menu md:flex-1">
                <ul class="flex flex-col gap-2 md:flex-row md:items-center md:justify-center md:gap-2">
                    <li><a href="/" class="nav-link inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-200 transition hover:bg-cyan-400/15 hover:text-cyan-200"><i class="mdi mdi-home text-base"></i><span>主页</span></a></li>
                    <li><a href="/gallery" class="nav-link inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-200 transition hover:bg-cyan-400/15 hover:text-cyan-200"><i class="mdi mdi-image-multiple text-base"></i><span>相册</span></a></li>
                    <li><a href="/leaderboard" class="nav-link inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-200 transition hover:bg-cyan-400/15 hover:text-cyan-200"><i class="mdi mdi-format-list-numbered text-base"></i><span>排行榜</span></a></li>
                    <li><a href="/announcements" class="nav-link inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-200 transition hover:bg-cyan-400/15 hover:text-cyan-200"><i class="mdi mdi-bullhorn text-base"></i><span>公告板</span></a></li>
                    <li><a href="/about" class="nav-link inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-200 transition hover:bg-cyan-400/15 hover:text-cyan-200"><i class="mdi mdi-information text-base"></i><span>关于我们</span></a></li>
                </ul>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <?php
                        $mobileNavUsername = (string)($_SESSION['username'] ?? '');
                        $mobileNavAvatarUrl = '/api/avatar?username=' . rawurlencode($mobileNavUsername !== '' ? $mobileNavUsername : 'MHF_Steve') . '&size=32';
                        $mobileNavFallbackAvatar = '/images/owner_avatar.png';
                    ?>
                    <div class="mobile-user-menu user-menu-wrap md:hidden" id="mobileUserMenuWrap">
                        <button
                            type="button"
                            id="mobile-user-menu-toggle"
                            class="user-menu-toggle mobile-user-menu-toggle"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            aria-label="打开个人中心菜单"
                        >
                            <img
                                src="<?= htmlspecialchars($mobileNavAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="玩家头像"
                                width="22"
                                height="22"
                                class="user-menu-avatar"
                                onerror="this.onerror=null;this.src='<?= htmlspecialchars($mobileNavFallbackAvatar, ENT_QUOTES, 'UTF-8') ?>';"
                            >
                            <span><?= htmlspecialchars($mobileNavUsername, ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="mdi mdi-chevron-down user-menu-caret" aria-hidden="true"></i>
                        </button>
                        <div class="user-menu-dropdown mobile-user-menu-dropdown" id="mobileUserMenuDropdown" role="menu" aria-label="个人中心菜单">
                            <a href="/profile" class="user-menu-item" role="menuitem">
                                <i class="mdi mdi-view-dashboard-outline"></i>
                                <span>仪表盘</span>
                            </a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <a href="/admin" class="user-menu-item admin" role="menuitem">
                                    <i class="mdi mdi-shield-crown-outline"></i>
                                    <span>后台</span>
                                </a>
                            <?php endif; ?>
                            <a href="/auth/logout" class="user-menu-item logout" role="menuitem">
                                <i class="mdi mdi-logout"></i>
                                <span>登出</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mobile-auth-links md:hidden">
                        <a href="/auth/login" class="inline-flex items-center justify-center rounded-xl border border-white/15 px-3 py-2 text-xs text-slate-100 transition hover:border-cyan-300/30 hover:bg-cyan-500/15">登录</a>
                        <a href="/auth/register" class="inline-flex items-center justify-center rounded-xl bg-cyan-500/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-400">注册</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <div class="hidden items-center gap-2 md:flex">
                    <?php if (empty($_SESSION['user_id'])): ?>
                        <a href="/auth/login" class="inline-flex items-center rounded-xl border border-white/15 px-3 py-2 text-xs text-slate-100 transition hover:border-cyan-300/30 hover:bg-cyan-500/15">登录</a>
                        <a href="/auth/register" class="inline-flex items-center rounded-xl bg-cyan-500/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-400">注册</a>
                    <?php else: ?>
                        <?php
                            $navUsername = (string)($_SESSION['username'] ?? '');
                            $navAvatarUrl = '/api/avatar?username=' . rawurlencode($navUsername !== '' ? $navUsername : 'MHF_Steve') . '&size=32';
                            $navFallbackAvatar = '/images/owner_avatar.png';
                        ?>
                        <div class="user-menu-wrap" id="userMenuWrap">
                            <button
                                type="button"
                                id="user-menu-toggle"
                                class="user-menu-toggle"
                                aria-haspopup="menu"
                                aria-expanded="false"
                                aria-label="打开个人中心菜单"
                            >
                                <img
                                    src="<?= htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="玩家头像"
                                    width="22"
                                    height="22"
                                    class="user-menu-avatar"
                                    onerror="this.onerror=null;this.src='<?= htmlspecialchars($navFallbackAvatar, ENT_QUOTES, 'UTF-8') ?>';"
                                >
                                <span><?= htmlspecialchars($navUsername, ENT_QUOTES, 'UTF-8') ?></span>
                                <i class="mdi mdi-chevron-down user-menu-caret" aria-hidden="true"></i>
                            </button>
                            <div class="user-menu-dropdown" id="userMenuDropdown" role="menu" aria-label="个人中心菜单">
                                <a href="/profile" class="user-menu-item" role="menuitem">
                                    <i class="mdi mdi-view-dashboard-outline"></i>
                                    <span>仪表盘</span>
                                </a>
                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <a href="/admin" class="user-menu-item admin" role="menuitem">
                                        <i class="mdi mdi-shield-crown-outline"></i>
                                        <span>后台</span>
                                    </a>
                                <?php endif; ?>
                                <a href="/auth/logout" class="user-menu-item logout" role="menuitem">
                                    <i class="mdi mdi-logout"></i>
                                    <span>登出</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <button id="mobile-menu-toggle" class="mobile-menu-toggle inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-slate-900/80 text-slate-100 md:hidden" aria-label="切换菜单">
                    <i class="mdi mdi-menu text-lg"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<button id="theme-toggle" class="theme-fab" aria-label="切换主题" aria-pressed="true">
    <svg class="theme-icon-light" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
    <svg class="theme-icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(function (link) {
        var href = link.getAttribute('href') || '';
        var isActive = currentPath === href || (href !== '/' && currentPath.indexOf(href) === 0);
        if (isActive) {
            link.classList.add('bg-cyan-500/20', 'text-cyan-200');
        }
    });

    var userMenuWrap = document.getElementById('userMenuWrap');
    var userMenuToggle = document.getElementById('user-menu-toggle');
    var userMenuDropdown = document.getElementById('userMenuDropdown');
    if (userMenuWrap && userMenuToggle && userMenuDropdown) {
        function setUserMenuOpen(open) {
            userMenuWrap.classList.toggle('open', open);
            userMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        userMenuToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setUserMenuOpen(!userMenuWrap.classList.contains('open'));
        });

        document.addEventListener('click', function (event) {
            if (!userMenuWrap.contains(event.target)) {
                setUserMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setUserMenuOpen(false);
            }
        });
    }

    var mobileUserMenuWrap = document.getElementById('mobileUserMenuWrap');
    var mobileUserMenuToggle = document.getElementById('mobile-user-menu-toggle');
    var mobileUserMenuDropdown = document.getElementById('mobileUserMenuDropdown');
    if (mobileUserMenuWrap && mobileUserMenuToggle && mobileUserMenuDropdown) {
        function setMobileUserMenuOpen(open) {
            mobileUserMenuWrap.classList.toggle('open', open);
            mobileUserMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        mobileUserMenuToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setMobileUserMenuOpen(!mobileUserMenuWrap.classList.contains('open'));
        });

        document.addEventListener('click', function (event) {
            if (!mobileUserMenuWrap.contains(event.target)) {
                setMobileUserMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMobileUserMenuOpen(false);
            }
        });
    }

    // Keep icon state strictly synced with html[data-theme].
    var themeToggle = document.getElementById('theme-toggle');
    var root = document.documentElement;
    function syncThemeFab() {
        if (!themeToggle) return;
        var activeTheme = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        var isDark = activeTheme === 'dark';
        themeToggle.classList.toggle('is-dark', isDark);
        themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggle.setAttribute('title', isDark ? '当前深色模式' : '当前浅色模式');
    }
    syncThemeFab();
    if (window.MutationObserver) {
        new MutationObserver(syncThemeFab).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    }
    document.addEventListener('themeChange', syncThemeFab);
});
</script>

<style>
#navbar.hidden {
    transform: translateX(-50%) translateY(-115%);
    opacity: 0;
}
.navbar-menu {
    position: absolute;
    top: calc(100% + 0.6rem);
    left: 0;
    right: 0;
    z-index: 45;
    display: none;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 1rem;
    background: rgba(2, 6, 23, 0.95);
    backdrop-filter: blur(20px);
    padding: 0.75rem;
}
.navbar-menu.active {
    display: block;
}
.user-menu-wrap {
    position: relative;
}
.user-menu-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.52rem 0.75rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background: rgba(15, 23, 42, 0.7);
    color: #e2e8f0;
    font-size: 0.78rem;
    line-height: 1;
    cursor: pointer;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}
.user-menu-toggle:hover {
    border-color: rgba(34, 211, 238, 0.35);
    background: rgba(14, 165, 233, 0.15);
}
.user-menu-avatar {
    border-radius: 4px;
    image-rendering: pixelated;
    background: #1e1e1e;
    flex: 0 0 auto;
}
.user-menu-caret {
    opacity: 0.8;
    transition: transform 0.2s ease;
}
.user-menu-wrap.open .user-menu-caret {
    transform: rotate(180deg);
}
.user-menu-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    width: 13.2rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.3);
    background: rgba(2, 6, 23, 0.97);
    box-shadow: 0 24px 52px -28px rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(16px);
    padding: 0.45rem;
    opacity: 0;
    transform: translateY(-4px);
    pointer-events: none;
    transition: opacity 0.16s ease, transform 0.16s ease;
    z-index: 70;
}
.user-menu-wrap.open .user-menu-dropdown {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}
.mobile-user-menu {
    display: none;
    margin-top: 0.7rem;
    border-top: 1px solid rgba(148, 163, 184, 0.2);
    padding-top: 0.7rem;
}
.mobile-user-menu-toggle {
    width: 100%;
    justify-content: space-between;
}
.mobile-user-menu-dropdown {
    position: static;
    margin-top: 0.45rem;
    min-width: 100%;
    transform: none;
    opacity: 1;
    pointer-events: auto;
    display: none;
}
.mobile-user-menu.open .mobile-user-menu-dropdown {
    display: block;
}
.mobile-auth-links {
    display: none;
    margin-top: 0.7rem;
    border-top: 1px solid rgba(148, 163, 184, 0.2);
    padding-top: 0.7rem;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.user-menu-item {
    display: flex;
    align-items: center;
    gap: 0.48rem;
    border-radius: 0.72rem;
    padding: 0.56rem 0.64rem;
    color: #e2e8f0;
    font-size: 0.92rem;
    text-decoration: none;
    transition: background-color 0.18s ease, color 0.18s ease;
}
.user-menu-item i {
    font-size: 1.05rem;
}
.user-menu-item.logout {
    margin-top: 0.28rem;
    padding-top: 0.72rem;
    border-top: 1px solid rgba(148, 163, 184, 0.2);
    color: #fca5a5;
}
.user-menu-item:hover {
    background: rgba(14, 165, 233, 0.16);
    color: #a5f3fc;
}
.user-menu-item.admin:hover {
    background: rgba(16, 185, 129, 0.14);
    color: #d1fae5;
}
.user-menu-item.logout:hover {
    background: rgba(244, 63, 94, 0.15);
    color: #fecdd3;
}
.theme-fab {
    position: fixed;
    left: 1rem;
    bottom: 1rem;
    z-index: 60;
    width: 3rem;
    height: 3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(15, 23, 42, 0.86);
    color: #e2e8f0;
    box-shadow: 0 16px 34px -18px rgba(0, 0, 0, 0.92);
    backdrop-filter: blur(16px);
    transition: border-color 0.25s ease, background-color 0.25s ease, transform 0.25s ease;
}
.theme-fab:hover {
    border-color: rgba(34, 211, 238, 0.45);
    background: rgba(14, 116, 144, 0.4);
    transform: translateY(-1px);
}
.theme-fab .theme-icon-light,
.theme-fab .theme-icon-dark {
    position: absolute;
    width: 1.25rem;
    height: 1.25rem;
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.theme-fab .theme-icon-light {
    opacity: 1;
    transform: scale(1);
}
.theme-fab .theme-icon-dark {
    opacity: 0;
    transform: scale(0.7);
}
.theme-fab.is-dark .theme-icon-light {
    opacity: 0;
    transform: scale(0.7);
}
.theme-fab.is-dark .theme-icon-dark {
    opacity: 1;
    transform: scale(1);
}
@media (min-width: 768px) {
    .theme-fab {
        left: 1.5rem;
        bottom: 1.5rem;
    }
}
[data-theme="light"] .user-menu-toggle {
    background: rgba(255, 255, 255, 0.9);
    color: #0f172a;
    border-color: rgba(148, 163, 184, 0.45);
}
[data-theme="light"] .user-menu-dropdown {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(148, 163, 184, 0.45);
}
[data-theme="light"] .user-menu-item {
    color: #0f172a;
}
[data-theme="light"] .mobile-user-menu,
[data-theme="light"] .mobile-auth-links {
    border-top-color: rgba(148, 163, 184, 0.34);
}
[data-theme="light"] .user-menu-item.logout {
    border-top-color: rgba(148, 163, 184, 0.34);
    color: #dc2626;
}
[data-theme="light"] .user-menu-item:hover {
    background: rgba(14, 165, 233, 0.14);
    color: #0e7490;
}
@media (max-width: 767.98px) {
    .mobile-user-menu {
        display: block;
    }
    .mobile-auth-links {
        display: grid;
    }
}
@media (min-width: 768px) {
    .navbar-menu {
        position: static;
        display: block !important;
        border: 0;
        background: transparent;
        backdrop-filter: none;
        padding: 0;
    }
}
</style>

