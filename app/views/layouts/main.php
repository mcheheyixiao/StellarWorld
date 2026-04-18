<?php
/** @var string $title */
/** @var string $description */
/** @var string $content */

$pageTitle = $title ?? '主页';
$pageDescription = $description ?? '欢迎来到我们的 Minecraft 公益服务器';
$pageKeywords = $keywords ?? 'Minecraft, 公益服务器, MC服务器, 我的世界';

$layoutRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdminLayout = str_starts_with($layoutRequestPath, '/admin');

// Background images：生成多宽度 WebP 变体（Intervention Image 未安装时仅使用原图）
$backgroundImages = [
    '/images/backgrounds/bg1.webp',
    '/images/backgrounds/bg2.webp',
    '/images/backgrounds/bg3.webp',
];

$backgroundResponsiveSets = [];
foreach ($backgroundImages as $rel) {
    $abs = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        \Core\ImageProcessor::ensureBackgroundWebpVariants($abs);
        $set = \Core\ImageProcessor::buildWebpSrcsetForPublicPath($rel);
        if ($set !== null) {
            $backgroundResponsiveSets[] = $set;
        } else {
            $backgroundResponsiveSets[] = ['webpSrcset' => '', 'fallback' => $rel];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - 繁星World</title>
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <link rel="shortcut icon" href="/images/favicon.ico">

    <link rel="preconnect" href="https://fonts.loli.net">
    <link rel="preconnect" href="https://gstatic.loli.net" crossorigin>
    <link rel="preload" href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;500;600;700;800&family=Quicksand:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">

    <link rel="stylesheet" href="https://cdn.staticfile.net/MaterialDesign-Webfont/7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="/styles/tailwind-dist.css">
    <style>
        body {
            position: relative;
            background-color: #020617;
            color: #e2e8f0;
        }
        [data-theme="light"] body {
            background-color: #edf4ff;
            color: #1e293b;
        }
        .mc-site-shell {
            position: relative;
            min-height: 100vh;
            isolation: isolate;
        }
        .mc-site-bg {
            position: fixed;
            inset: 0;
            z-index: -2;
            overflow: hidden;
        }
        .mc-site-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 12% 20%, rgba(34, 211, 238, 0.22), transparent 42%),
                        radial-gradient(circle at 82% 0%, rgba(14, 165, 233, 0.18), transparent 38%),
                        linear-gradient(135deg, #020617 0%, #0f172a 52%, #030712 100%);
        }
        [data-theme="light"] .mc-site-bg::before {
            background: radial-gradient(circle at 12% 20%, rgba(59, 130, 246, 0.15), transparent 42%),
                        radial-gradient(circle at 82% 0%, rgba(14, 165, 233, 0.12), transparent 38%),
                        linear-gradient(135deg, #f8fbff 0%, #eef5ff 52%, #e5efff 100%);
        }
        .mc-site-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.58), transparent 90%);
            opacity: 0.5;
        }
        [data-theme="light"] .mc-site-bg::after {
            opacity: 0.2;
        }
        /*
         * 与 #navbar 同宽：顶栏为 position:fixed，其宽度百分比相对视口；
         * 主内容若在普通流里单独写 calc(100%-1.5rem)，可能与顶栏产生 1～数像素偏差。
         * 使用与顶栏一致的公式居中一栏，内部 .page-container 只负责内边距。
         */
        .site-content-column {
            box-sizing: border-box;
            width: calc(100% - 1.5rem);
            max-width: 72rem;
            margin-left: auto;
            margin-right: auto;
        }
        .main-content {
            min-height: calc(100vh - 120px);
            padding: 7rem 0 2.5rem;
        }
        .main-content--admin {
            min-height: 100vh;
            padding: 0.75rem;
        }
        .main-content--admin .ta-admin-shell {
            height: calc(100vh - 1.5rem);
        }
        [data-theme="light"] #navbar > div,
        [data-theme="light"] footer,
        [data-theme="light"] .mc-glass-card {
            background: rgba(255, 255, 255, 0.78) !important;
            border-color: rgba(148, 163, 184, 0.28) !important;
            box-shadow: 0 16px 34px -24px rgba(15, 23, 42, 0.28) !important;
        }
        /* Global light-theme harmonization for Tailwind utility heavy pages. */
        [data-theme="light"] [class*="bg-slate-950"],
        [data-theme="light"] [class*="bg-slate-900"],
        [data-theme="light"] [class*="bg-slate-800"] {
            background: rgba(255, 255, 255, 0.78) !important;
        }
        [data-theme="light"] [class*="border-white/"],
        [data-theme="light"] [class*="border-slate-"] {
            border-color: rgba(148, 163, 184, 0.34) !important;
        }
        [data-theme="light"] .text-white,
        [data-theme="light"] .text-slate-100,
        [data-theme="light"] .text-slate-200,
        [data-theme="light"] .text-slate-300 {
            color: #1e293b !important;
        }
        [data-theme="light"] .text-slate-400 {
            color: #475569 !important;
        }
        [data-theme="light"] [class*="text-cyan-200"],
        [data-theme="light"] [class*="text-cyan-300"],
        [data-theme="light"] [class*="text-cyan-400"] {
            color: #0e7490 !important;
        }
        /* text-cyan-100 在浅色底上几乎不可见，需单独加深 */
        [data-theme="light"] .text-cyan-100,
        [data-theme="light"] [class*="text-cyan-100"] {
            color: #0f766e !important;
        }
        /* 登录/注册等 auth 表单：浅色模式下输入框与图标对比度 */
        [data-theme="light"] .custom-input {
            background: rgba(255, 255, 255, 0.98) !important;
            border-color: rgba(100, 116, 139, 0.5) !important;
            color: #0f172a !important;
        }
        [data-theme="light"] .custom-input::placeholder {
            color: #64748b !important;
        }
        [data-theme="light"] .input-group .input-icon {
            color: #64748b !important;
            fill: currentColor !important;
        }
        [data-theme="light"] .input-group:focus-within .input-icon {
            color: #0e7490 !important;
        }
        [data-theme="light"] .input-group:focus-within .custom-input {
            border-color: rgba(14, 116, 144, 0.55) !important;
            box-shadow: 0 0 0 1px rgba(14, 116, 144, 0.2), 0 0 20px -6px rgba(14, 116, 144, 0.35) !important;
        }
        [data-theme="light"] .modern-checkbox .label-text {
            color: #334155 !important;
        }
        [data-theme="light"] .auth-remember-label {
            color: #334155 !important;
        }
        [data-theme="light"] .modern-checkbox .custom-checkmark {
            background: rgba(255, 255, 255, 0.95) !important;
            border-color: rgba(100, 116, 139, 0.45) !important;
        }
        [data-theme="light"] [class*="bg-cyan-500/10"] {
            background: rgba(14, 116, 144, 0.08) !important;
        }
        [data-theme="light"] [class*="hover:bg-cyan-500/20"]:hover,
        [data-theme="light"] [class*="hover:bg-cyan-500/25"]:hover,
        [data-theme="light"] [class*="hover:bg-cyan-400/15"]:hover {
            background: rgba(14, 116, 144, 0.14) !important;
        }
        [data-theme="light"] .theme-fab {
            background: rgba(255, 255, 255, 0.9) !important;
            color: #0f172a !important;
            border-color: rgba(148, 163, 184, 0.42) !important;
        }
        [data-theme="light"] .mc-glass-card h1.text-fusion-pixel {
            color: #0f172a !important;
        }
        /* 登录/注册主按钮、次按钮：浅色下固定对比度（避免 text-cyan-200 与浅底发灰、bg-slate-900 被全局规则冲掉） */
        [data-theme="light"] .auth-action-btn--primary {
            background: rgba(6, 182, 212, 0.2) !important;
            border-color: rgba(8, 145, 178, 0.55) !important;
            color: #0c4a6e !important;
        }
        [data-theme="light"] .auth-action-btn--secondary {
            background: rgba(241, 245, 249, 0.98) !important;
            border-color: rgba(148, 163, 184, 0.45) !important;
            color: #0f172a !important;
        }
    </style>

    <script>
        window.websiteConfig = {
            fonts: {
                main: {
                    family: '像素体',
                    file: '/fonts/像素体.ttf',
                    fallback: "'Quicksand', 'Noto Sans SC', sans-serif",
                    weight: 'normal'
                },
                title: {
                    family: '像素体',
                    file: '/fonts/像素体.ttf',
                    fallback: "'Quicksand', 'Noto Sans SC', sans-serif",
                    weight: 'bold'
                }
            },
            background: {
                sets: <?= json_encode($backgroundResponsiveSets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
                defaultImage: '/images/backgrounds/bg1.webp'
            }
        };
    </script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);

            function initializeRandomBackground() {
                const pic = document.getElementById('background-picture');
                const sourceEl = document.getElementById('background-webp-source');
                const imgEl = document.getElementById('background-fallback-img');
                if (!pic || !sourceEl || !imgEl || !window.websiteConfig || !window.websiteConfig.background) {
                    return;
                }
                const sets = window.websiteConfig.background.sets || [];
                const def = window.websiteConfig.background.defaultImage || '/images/backgrounds/bg1.webp';
                if (sets.length === 0) {
                    sourceEl.removeAttribute('srcset');
                    imgEl.src = def;
                    setTimeout(function () { pic.classList.add('active'); }, 100);
                    return;
                }
                const pick = sets[Math.floor(Math.random() * sets.length)];
                if (pick && pick.webpSrcset) {
                    sourceEl.srcset = pick.webpSrcset;
                    sourceEl.setAttribute('sizes', '100vw');
                } else {
                    sourceEl.removeAttribute('srcset');
                }
                imgEl.src = (pick && pick.fallback) ? pick.fallback : def;
                setTimeout(function () { pic.classList.add('active'); }, 100);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeRandomBackground);
            } else {
                initializeRandomBackground();
            }
        })();
    </script>
</head>
<body class="antialiased">
<?php include BASE_PATH . '/app/views/partials/loading.php'; ?>
<div class="mc-site-shell">
    <div class="mc-site-bg" aria-hidden="true"></div>
    <?php if ($isAdminLayout): ?>
        <main class="main-content main-content--admin">
            <?= $content ?? '' ?>
        </main>
    <?php else: ?>
        <?php include BASE_PATH . '/app/views/partials/navigation.php'; ?>
        <main class="main-content">
            <div class="site-content-column">
                <?= $content ?? '' ?>
            </div>
        </main>
        <?php include BASE_PATH . '/app/views/partials/back_to_top.php'; ?>
        <?php include BASE_PATH . '/app/views/partials/music_player.php'; ?>
        <?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
    <?php endif; ?>
</div>

<script src="/scripts/fonts.js"></script>
<script src="/scripts/theme.js"></script>
<script src="/scripts/navigation.js"></script>
<script src="/scripts/loading.js"></script>
</body>
</html>
