<?php
$isLoggedIn = !empty($_SESSION['user_id']);
$navUsername = (string)($_SESSION['username'] ?? '');
$navAvatarUrl = '/api/avatar?username=' . rawurlencode($navUsername !== '' ? $navUsername : 'MHF_Steve') . '&size=32';
$navFallbackAvatar = '/images/owner_avatar.png';
$navCsrfToken = (string)($_SESSION['csrf_token'] ?? '');
if ($isLoggedIn && $navCsrfToken === '') {
    try {
        $navCsrfToken = bin2hex(random_bytes(32));
    } catch (\Throwable $e) {
        $navCsrfToken = hash('sha256', uniqid('csrf', true));
    }
    $_SESSION['csrf_token'] = $navCsrfToken;
}
?>

<nav id="navbar" class="mc-nav">
    <div class="mc-nav-shell">
        <a href="/" class="mc-nav-logo" aria-label="繁星World">
            <span class="mc-nav-logo-icon" aria-hidden="true">
                <img src="/images/logo.png" alt="" class="mc-nav-logo-image">
            </span>
            <span class="mc-nav-logo-text">繁星World</span>
        </a>

        <div class="navbar-menu mc-nav-menu" id="mcNavMenu">
            <ul class="mc-nav-links">
                <li><a href="/" class="nav-link">首页</a></li>
                <li><a href="/gallery" class="nav-link">相册</a></li>
                <li><a href="/leaderboard" class="nav-link">&#25490;&#34892;&#27036;</a></li>
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
                            <span>&#20202;&#34920;&#30424;</span>
                        </a>
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <a href="/admin" class="user-menu-item admin" role="menuitem">
                                <i class="mdi mdi-shield-crown-outline"></i>
                                <span>后台</span>
                            </a>
                        <?php endif; ?>
                        <form method="post" action="/auth/logout" class="user-menu-logout-form" role="none">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="user-menu-item user-menu-item-button logout" role="menuitem">
                                <i class="mdi mdi-logout"></i>
                                <span>&#36864;&#20986;</span>
                            </button>
                        </form>
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
            <button id="theme-toggle" class="theme-fab mc-nav-theme-toggle" aria-label="切换主题" aria-pressed="true">
                <svg class="theme-icon-light" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
                <svg class="theme-icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
            </button>
            <div class="mc-nav-desktop-auth">
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
                                <span>&#20202;&#34920;&#30424;</span>
                            </a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <a href="/admin" class="user-menu-item admin" role="menuitem">
                                    <i class="mdi mdi-shield-crown-outline"></i>
                                    <span>后台</span>
                                </a>
                            <?php endif; ?>
                            <form method="post" action="/auth/logout" class="user-menu-logout-form" role="none">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="user-menu-item user-menu-item-button logout" role="menuitem">
                                    <i class="mdi mdi-logout"></i>
                                    <span>&#36864;&#20986;</span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
    </div>
</nav>
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
    inset: 0 0 auto 0;
    z-index: 50;
    border-bottom: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    transition: background-color 0.25s ease, border-color 0.25s ease;
}

html.dark #navbar.mc-nav,
html[data-theme="dark"] #navbar.mc-nav {
    border-bottom-color: rgba(255, 255, 255, 0.1);
    background: rgba(2, 6, 23, 0.8);
}

#navbar.mc-nav.hidden {
    transform: translateY(0);
    opacity: 1;
}

#navbar.mc-nav .mc-nav-shell {
    width: 100%;
    max-width: 72rem;
    min-height: 4rem;
    margin: 0 auto;
    padding: 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
}

.mc-nav-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    color: #334155;
    text-decoration: none;
    flex: 0 0 auto;
}

html.dark .mc-nav-logo,
html[data-theme="dark"] .mc-nav-logo {
    color: #e2e8f0;
}

.mc-nav-logo-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    height: auto;
    border: 0;
    background: transparent;
    padding: 0;
}

.mc-nav-logo-image {
    width: 2.2rem;
    height: 2.2rem;
    object-fit: contain;
    display: block;
    image-rendering: auto;
}

.mc-nav-logo-text {
    font-size: 0.95rem;
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
    gap: 0.15rem;
}

.mc-nav-links .nav-link {
    display: inline-flex;
    align-items: center;
    border-radius: 0.6rem;
    padding: 0.45rem 0.72rem;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1;
    text-decoration: none;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.mc-nav-links .nav-link::after {
    display: none;
}

.mc-nav-links .nav-link:hover {
    color: #0f172a;
    background: rgba(148, 163, 184, 0.16);
}

.mc-nav-links .nav-link.is-active {
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.15);
}

html.dark .mc-nav-links .nav-link,
html[data-theme="dark"] .mc-nav-links .nav-link {
    color: #cbd5e1;
}

html.dark .mc-nav-links .nav-link:hover,
html[data-theme="dark"] .mc-nav-links .nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

html.dark .mc-nav-links .nav-link.is-active,
html[data-theme="dark"] .mc-nav-links .nav-link.is-active {
    color: #818cf8;
    background: rgba(129, 140, 248, 0.2);
}

.mc-nav-actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 0 0 auto;
}

.mc-nav-desktop-auth {
    display: none;
    align-items: center;
    gap: 0.55rem;
}

.mc-nav-auth-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.2rem;
    padding: 0.36rem 0.88rem;
    border-radius: 0.62rem;
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 650;
    line-height: 1;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.mc-nav-auth-ghost {
    border: 1px solid rgba(148, 163, 184, 0.45);
    color: #334155;
    background: rgba(255, 255, 255, 0.72);
}

.mc-nav-auth-ghost:hover {
    color: #0f172a;
    border-color: rgba(148, 163, 184, 0.75);
    background: rgba(241, 245, 249, 0.95);
}

.mc-nav-auth-primary {
    border: 1px solid rgba(79, 70, 229, 0.25);
    color: #eef2ff;
    background: #4f46e5;
}

.mc-nav-auth-primary:hover {
    background: #4338ca;
}

html.dark .mc-nav-auth-ghost,
html[data-theme="dark"] .mc-nav-auth-ghost {
    border-color: rgba(255, 255, 255, 0.2);
    color: #cbd5e1;
    background: rgba(15, 23, 42, 0.4);
}

html.dark .mc-nav-auth-ghost:hover,
html[data-theme="dark"] .mc-nav-auth-ghost:hover {
    border-color: rgba(255, 255, 255, 0.35);
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

html.dark .mc-nav-auth-primary,
html[data-theme="dark"] .mc-nav-auth-primary {
    border-color: rgba(129, 140, 248, 0.35);
    background: #4338ca;
    color: #e0e7ff;
}

html.dark .mc-nav-auth-primary:hover,
html[data-theme="dark"] .mc-nav-auth-primary:hover {
    background: #3730a3;
}

.user-menu-wrap {
    position: relative;
}

.user-menu-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.7rem;
    border-radius: 0.62rem;
    border: 1px solid rgba(148, 163, 184, 0.44);
    background: rgba(255, 255, 255, 0.72);
    color: #334155;
    font-size: 0.78rem;
    line-height: 1;
    cursor: pointer;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.user-menu-toggle:hover {
    color: #0f172a;
    border-color: rgba(148, 163, 184, 0.75);
    background: rgba(241, 245, 249, 0.95);
}

html.dark .user-menu-toggle,
html[data-theme="dark"] .user-menu-toggle {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(15, 23, 42, 0.4);
    color: #cbd5e1;
}

html.dark .user-menu-toggle:hover,
html[data-theme="dark"] .user-menu-toggle:hover {
    border-color: rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
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
    top: calc(100% + 0.55rem);
    right: 0;
    width: 13rem;
    border-radius: 0.9rem;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 18px 38px -24px rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(14px);
    padding: 0.45rem;
    opacity: 0;
    transform: translateY(-4px);
    pointer-events: none;
    transition: opacity 0.16s ease, transform 0.16s ease;
    z-index: 95;
}

html.dark .user-menu-dropdown,
html[data-theme="dark"] .user-menu-dropdown {
    border-color: rgba(255, 255, 255, 0.16);
    background: rgba(2, 6, 23, 0.94);
    box-shadow: 0 24px 46px -28px rgba(0, 0, 0, 0.88);
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
    border-radius: 0.68rem;
    padding: 0.56rem 0.64rem;
    color: #334155;
    font-size: 0.9rem;
    text-decoration: none;
    transition: background-color 0.18s ease, color 0.18s ease;
}

html.dark .user-menu-item,
html[data-theme="dark"] .user-menu-item {
    color: #cbd5e1;
}

.user-menu-item:hover {
    background: rgba(148, 163, 184, 0.18);
    color: #0f172a;
}

html.dark .user-menu-item:hover,
html[data-theme="dark"] .user-menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.user-menu-logout-form {
    margin: 0;
    padding: 0;
}

.user-menu-item-button {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font: inherit;
}

.user-menu-item i {
    font-size: 1.05rem;
}

.user-menu-item.logout {
    margin-top: 0.28rem;
    padding-top: 0.72rem;
    border-top: 1px solid rgba(148, 163, 184, 0.32);
    color: #dc2626;
}

html.dark .user-menu-item.logout,
html[data-theme="dark"] .user-menu-item.logout {
    border-top-color: rgba(148, 163, 184, 0.25);
    color: #fca5a5;
}

.user-menu-item.admin:hover {
    background: rgba(16, 185, 129, 0.12);
    color: #065f46;
}

html.dark .user-menu-item.admin:hover,
html[data-theme="dark"] .user-menu-item.admin:hover {
    background: rgba(16, 185, 129, 0.18);
    color: #d1fae5;
}

.user-menu-item.logout:hover {
    background: rgba(248, 113, 113, 0.16);
    color: #b91c1c;
}

html.dark .user-menu-item.logout:hover,
html[data-theme="dark"] .user-menu-item.logout:hover {
    background: rgba(244, 63, 94, 0.2);
    color: #fecdd3;
}

.mobile-user-menu {
    display: none;
    margin-top: 0.7rem;
    border-top: 1px solid rgba(148, 163, 184, 0.28);
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
    border-top: 1px solid rgba(148, 163, 184, 0.28);
    padding-top: 0.7rem;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.mc-nav-theme-toggle {
    position: relative;
    width: 2.35rem;
    height: 2.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 0.62rem;
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: rgba(255, 255, 255, 0.72);
    color: #334155;
    box-shadow: none;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.mc-nav-theme-toggle:hover {
    border-color: rgba(148, 163, 184, 0.75);
    background: rgba(241, 245, 249, 0.95);
    color: #0f172a;
    transform: none;
}

html.dark .mc-nav-theme-toggle,
html[data-theme="dark"] .mc-nav-theme-toggle {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(15, 23, 42, 0.4);
    color: #cbd5e1;
}

html.dark .mc-nav-theme-toggle:hover,
html[data-theme="dark"] .mc-nav-theme-toggle:hover {
    border-color: rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.theme-fab .theme-icon-light,
.theme-fab .theme-icon-dark {
    position: absolute;
    width: 1.1rem;
    height: 1.1rem;
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

.mc-nav-toggle {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.62rem;
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: rgba(255, 255, 255, 0.72);
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.mc-nav-toggle:hover {
    border-color: rgba(148, 163, 184, 0.75);
    background: rgba(241, 245, 249, 0.95);
    color: #0f172a;
}

html.dark .mc-nav-toggle,
html[data-theme="dark"] .mc-nav-toggle {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(15, 23, 42, 0.4);
    color: #cbd5e1;
}

html.dark .mc-nav-toggle:hover,
html[data-theme="dark"] .mc-nav-toggle:hover {
    border-color: rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

@media (max-width: 767.98px) {
    #navbar.mc-nav .mc-nav-shell {
        padding: 0 0.75rem;
        gap: 0.55rem;
    }

    .mc-nav-logo-text {
        font-size: 0.9rem;
    }

    .navbar-menu {
        position: absolute;
        top: calc(100% + 0.55rem);
        left: 0.75rem;
        right: 0.75rem;
        z-index: 55;
        display: none;
        border: 1px solid rgba(148, 163, 184, 0.32);
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 18px 36px -24px rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        padding: 0.75rem;
    }

    html.dark .navbar-menu,
    html[data-theme="dark"] .navbar-menu {
        border-color: rgba(255, 255, 255, 0.16);
        background: rgba(2, 6, 23, 0.94);
        box-shadow: 0 24px 40px -26px rgba(0, 0, 0, 0.88);
    }

    .navbar-menu.active {
        display: block;
    }

    .mc-nav-links {
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        gap: 0.2rem;
    }

    .mc-nav-links .nav-link {
        justify-content: flex-start;
        width: 100%;
        padding: 0.65rem 0.68rem;
    }

    .mc-nav-desktop-auth {
        display: none !important;
    }

    .mobile-user-menu {
        display: block;
    }

    .mobile-auth-links {
        display: grid;
    }

    .mc-nav-theme-toggle,
    .mc-nav-toggle {
        width: 2.2rem;
        height: 2.2rem;
    }
}

@media (min-width: 768px) {
    .mc-nav-toggle {
        display: none;
    }

    .mc-nav-desktop-auth {
        display: flex;
    }

    .navbar-menu {
        position: static;
        display: block !important;
        border: 0;
        background: transparent;
        box-shadow: none;
        backdrop-filter: none;
        padding: 0;
    }

    .mobile-user-menu,
    .mobile-auth-links {
        display: none !important;
    }
}
</style>
