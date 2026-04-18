<header class="ta-header">
    <button type="button" id="admin-sidebar-toggle" class="ta-header-toggle" aria-label="切换侧边栏">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="ta-header-info">
        <p class="ta-header-eyebrow">控制台</p>
        <h1>后台总览</h1>
    </div>

    <div class="ta-header-user">
        <span><?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="/" class="ta-header-link">返回站点</a>
    </div>
</header>