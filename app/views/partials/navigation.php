<?php
$isLoggedIn = !empty($_SESSION['user_id']);
$navUsername = (string)($_SESSION['username'] ?? '');
$navAvatarUrl = '/api/avatar?username=' . rawurlencode($navUsername !== '' ? $navUsername : 'MHF_Steve') . '&size=32';
$navFallbackAvatar = '/images/owner_avatar.png';
?>

<nav id="navbar" class="mc-nav">
    <div class="mc-nav-shell">
        <a href="/" class="mc-nav-logo" aria-label="繁星World">
            <span class="mc-nav-logo-icon" aria-hidden="true">
                <img src="/images/logo.png" alt="" class="mc-nav-logo-image">
            </span>
            <span class="mc-nav-logo-text">繁星World</span>
        </a>

        <button
            id="mobile-menu-toggle"
            class="mobile-menu-toggle mc-nav-toggle"
            aria-label="切换菜单"
            aria-expanded="false"
            aria-controls="mcNavMenu"
            type="button"
        >
            <i class="mdi mdi-menu text-lg"></i>
        </button>

        <div class="navbar-menu mc-nav-menu" id="mcNavMenu">
            <ul class="mc-nav-links">
                <li><a href="/" class="nav-link">首页</a></li>
                <li><a href="/gallery" class="nav-link">相册</a></li>
                <li><a href="/leaderboard" class="nav-link">排行榜</a></li>
                <li><a href="/announcements" class="nav-link">公告</a></li>
                <li><a href="/about" class="nav-link">关于</a></li>
            </ul>

            <?php if ($isLoggedIn): ?>
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
                            <span>退出</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mobile-auth-links mc-nav-mobile-auth md:hidden">
                    <a href="/auth/login" class="mc-nav-auth-link mc-nav-auth-ghost">登录</a>
                    <a href="/auth/register" class="mc-nav-auth-link mc-nav-auth-primary">注册</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="mc-nav-actions">
            <?php if (!$isLoggedIn): ?>
                <a href="/auth/login" class="mc-nav-auth-link mc-nav-auth-ghost">登录</a>
                <a href="/auth/register" class="mc-nav-auth-link mc-nav-auth-primary">注册</a>
            <?php else: ?>
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
                            <span>退出</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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
            link.classList.add('is-active');
        }
    });

    var userMenuWrap = document.getElementById('userMenuWrap');
    var userMenuToggle = document.getElementById('user-menu-toggle');
    if (userMenuWrap && userMenuToggle) {
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
    if (mobileUserMenuWrap && mobileUserMenuToggle) {
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

    var themeToggle = document.getElementById('theme-toggle');
    var root = document.documentElement;
    function syncThemeFab() {
        if (!themeToggle) return;
        var activeTheme = root.classList.contains('dark') ? 'dark' : 'light';
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
#navbar.mc-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 80;
    transition: transform 0.28s ease, opacity 0.28s ease;
}

#navbar.mc-nav.hidden {
    transform: translateY(-118%);
    opacity: 0;
}

#navbar.mc-nav .mc-nav-shell {
    width: calc(100% - 1.5rem);
    max-width: 72rem;
    margin: 0.92rem auto 0;
    min-height: 4.1rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.3) !important;
    background: linear-gradient(90deg, rgba(30, 41, 59, 0.92) 0%, rgba(30, 58, 138, 0.5) 48%, rgba(15, 23, 42, 0.9) 100%) !important;
    box-shadow: 0 24px 46px -30px rgba(2, 6, 23, 0.9) !important;
    backdrop-filter: blur(16px);
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.5rem 0.82rem 0.5rem 1rem;
}

.mc-nav-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    text-decoration: none;
    color: #f8fafc;
    flex: 0 0 auto;
}

.mc-nav-logo-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    height: auto;
    border: 0;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    padding: 0;
}

.mc-nav-logo-icon i {
    font-size: 1.15rem;
    color: #e2e8f0;
}

.mc-nav-logo-image {
    width: 2.55rem;
    height: 2.55rem;
    object-fit: contain;
    display: block;
    image-rendering: auto;
}

.mc-nav-logo-text {
    font-size: 0.98rem;
    font-weight: 650;
    letter-spacing: 0.015em;
    line-height: 1.2;
    white-space: nowrap;
}

.mc-nav-menu {
    flex: 1 1 auto;
    min-width: 0;
}

.mc-nav-links {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.8rem;
}

.mc-nav-links .nav-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    color: rgba(241, 245, 249, 0.82);
    font-size: 0.98rem;
    font-weight: 650;
    letter-spacing: 0.015em;
    padding: 0.34rem 0;
    text-decoration: none;
    transition: color 0.25s ease;
}

.mc-nav-links .nav-link::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -0.05rem;
    width: 0;
    height: 2px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    transition: width 0.26s ease, left 0.26s ease;
}

.mc-nav-links .nav-link:hover,
.mc-nav-links .nav-link.is-active {
    color: #ffffff;
}

.mc-nav-links .nav-link:hover::after,
.mc-nav-links .nav-link.is-active::after {
    width: 100%;
    left: 0;
}

.mc-nav-actions {
    margin-left: auto;
    display: none;
    align-items: center;
    gap: 0.55rem;
}

.mc-nav-auth-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.2rem;
    padding: 0.35rem 0.95rem;
    border-radius: 999px;
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 700;
    transition: all 0.22s ease;
}

.mc-nav-auth-ghost {
    border: 1px solid rgba(148, 163, 184, 0.38);
    color: #f8fafc;
    background: rgba(15, 23, 42, 0.45);
}

.mc-nav-auth-ghost:hover {
    border-color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.14);
}

.mc-nav-auth-primary {
    border: 1px solid rgba(125, 211, 252, 0.65);
    color: #eff6ff;
    background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 100%);
    box-shadow: 0 8px 22px -12px rgba(56, 189, 248, 0.88);
}

.mc-nav-auth-primary:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, #3b82f6 0%, #0ea5e9 100%);
    box-shadow: 0 12px 26px -12px rgba(14, 165, 233, 0.96);
}

.user-menu-wrap {
    position: relative;
}

.user-menu-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.52rem 0.75rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.32);
    background: rgba(15, 23, 42, 0.52);
    color: #e2e8f0;
    font-size: 0.78rem;
    line-height: 1;
    cursor: pointer;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}

.user-menu-toggle:hover {
    border-color: rgba(203, 213, 225, 0.75);
    background: rgba(255, 255, 255, 0.16);
}

.user-menu-avatar {
    border-radius: 4px;
    image-rendering: pixelated;
    background: #1e1e1e;
    flex: 0 0 auto;
}

.user-menu-caret {
    opacity: 0.85;
    transition: transform 0.2s ease;
}

.user-menu-wrap.open .user-menu-caret {
    transform: rotate(180deg);
}

.user-menu-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    width: 13rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.26);
    background: rgba(2, 6, 23, 0.95);
    box-shadow: 0 24px 52px -28px rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(16px);
    padding: 0.45rem;
    opacity: 0;
    transform: translateY(-4px);
    pointer-events: none;
    transition: opacity 0.16s ease, transform 0.16s ease;
    z-index: 95;
}

.user-menu-wrap.open .user-menu-dropdown {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
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
    background: rgba(255, 255, 255, 0.13);
    color: #f8fafc;
}

.user-menu-item.admin:hover {
    background: rgba(16, 185, 129, 0.14);
    color: #d1fae5;
}

.user-menu-item.logout:hover {
    background: rgba(244, 63, 94, 0.15);
    color: #fecdd3;
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

.mc-nav-toggle {
    margin-left: auto;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.34);
    background: rgba(15, 23, 42, 0.68);
    color: #f8fafc;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.theme-fab {
    position: fixed;
    left: 1rem;
    bottom: 1rem;
    z-index: 90;
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

@media (max-width: 767.98px) {
    .mc-nav-shell {
        border-radius: 1.2rem;
        align-items: center;
    }

    .mc-nav-logo-text {
        font-size: 0.98rem;
    }

    .navbar-menu {
        position: absolute;
        top: calc(100% + 0.62rem);
        left: 0;
        right: 0;
        z-index: 45;
        display: none;
        border: 1px solid rgba(148, 163, 184, 0.24);
        border-radius: 1rem;
        background: rgba(2, 6, 23, 0.96);
        backdrop-filter: blur(20px);
        padding: 0.82rem;
    }

    .navbar-menu.active {
        display: block;
    }

    .mc-nav-links {
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        gap: 0.15rem;
    }

    .mc-nav-links .nav-link {
        justify-content: center;
        width: 100%;
        border-radius: 0.7rem;
        padding: 0.62rem 0.5rem;
        color: #e2e8f0;
        background: rgba(15, 23, 42, 0.42);
    }

    .mc-nav-links .nav-link::after {
        display: none;
    }

    .mc-nav-links .nav-link.is-active {
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
    }

    .mobile-user-menu {
        display: block;
    }

    .mobile-auth-links {
        display: grid;
    }
}

@media (min-width: 768px) {
    .mc-nav-toggle {
        display: none;
    }

    .mc-nav-actions {
        display: flex;
    }

    .navbar-menu {
        position: static;
        display: block !important;
        border: 0;
        background: transparent;
        backdrop-filter: none;
        padding: 0;
    }
}

@media (min-width: 768px) and (max-width: 980px) {
    .mc-nav-links {
        gap: 1.1rem;
    }

    .mc-nav-links .nav-link {
        font-size: 0.9rem;
    }
}

@media (min-width: 768px) {
    .theme-fab {
        left: 1.5rem;
        bottom: 1.5rem;
    }
}

html[data-theme="light"] #navbar.mc-nav .mc-nav-shell,
html.light #navbar.mc-nav .mc-nav-shell {
    border-color: rgba(148, 163, 184, 0.56) !important;
    background: linear-gradient(90deg, rgba(239, 246, 255, 0.95) 0%, rgba(224, 242, 254, 0.9) 48%, rgba(239, 246, 255, 0.95) 100%) !important;
    box-shadow: 0 22px 44px -30px rgba(15, 23, 42, 0.44) !important;
}

[data-theme="light"] .mc-nav-logo,
[data-theme="light"] .mc-nav-links .nav-link {
    color: #334155;
}

[data-theme="light"] .mc-nav-logo-icon {
    background: transparent;
    border-color: transparent;
    box-shadow: none;
}

[data-theme="light"] .mc-nav-logo-icon i {
    color: #334155;
}

[data-theme="light"] .mc-nav-links .nav-link.is-active,
[data-theme="light"] .mc-nav-links .nav-link:hover {
    color: #0f172a;
}

[data-theme="light"] .mc-nav-links .nav-link::after {
    background: rgba(15, 23, 42, 0.76);
}

[data-theme="light"] .mc-nav-auth-ghost,
[data-theme="light"] .user-menu-toggle,
[data-theme="light"] .mc-nav-toggle {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.88);
    border-color: rgba(148, 163, 184, 0.5);
}

[data-theme="light"] .user-menu-dropdown {
    background: rgba(255, 255, 255, 0.96);
    border-color: rgba(148, 163, 184, 0.4);
}

@media (max-width: 767.98px) {
    [data-theme="light"] .navbar-menu {
        background: rgba(255, 255, 255, 0.96);
        border-color: rgba(148, 163, 184, 0.4);
    }
}

[data-theme="light"] .user-menu-item {
    color: #0f172a;
}

[data-theme="light"] .mobile-user-menu,
[data-theme="light"] .mobile-auth-links,
[data-theme="light"] .user-menu-item.logout {
    border-top-color: rgba(148, 163, 184, 0.34);
}

[data-theme="light"] .theme-fab {
    background: rgba(255, 255, 255, 0.94);
    color: #0f172a;
    border-color: rgba(148, 163, 184, 0.48);
}
</style>
