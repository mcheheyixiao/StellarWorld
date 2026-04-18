<?php
$adminHeaderUsername = trim((string)($_SESSION['username'] ?? ''));
$adminHeaderAvatarUrl = 'https://minotar.net/helm/' . rawurlencode($adminHeaderUsername !== '' ? $adminHeaderUsername : 'MHF_Steve') . '/32.png';
$adminHeaderAvatarFallback = 'https://minotar.net/helm/MHF_Steve/32.png';
?>
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
        <div class="ta-header-user-name">
            <img
                src="<?= htmlspecialchars($adminHeaderAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                alt="玩家头像"
                width="24"
                height="24"
                class="ta-header-avatar"
                onerror="this.onerror=null;this.src='<?= htmlspecialchars($adminHeaderAvatarFallback, ENT_QUOTES, 'UTF-8') ?>';"
            >
            <span><?= htmlspecialchars($adminHeaderUsername, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="/" class="ta-header-link">返回站点</a>
    </div>
</header>
