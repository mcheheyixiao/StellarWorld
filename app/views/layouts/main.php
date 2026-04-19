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
        :root {
            --mc-bg: #f8fafc;
            --mc-bg-alt: #eef2ff;
            --mc-surface: #ffffff;
            --mc-surface-soft: rgba(255, 255, 255, 0.86);
            --mc-border: #e2e8f0;
            --mc-text: #0f172a;
            --mc-muted: #475569;
            --mc-accent: #22d3ee;
            --mc-accent-strong: #0891b2;
            --mc-glow: rgba(34, 211, 238, 0.3);
        }

        html.dark {
            --mc-bg: #020617;
            --mc-bg-alt: #0b1120;
            --mc-surface: rgba(15, 23, 42, 0.6);
            --mc-surface-soft: rgba(15, 23, 42, 0.76);
            --mc-border: rgba(255, 255, 255, 0.08);
            --mc-text: #e2e8f0;
            --mc-muted: #94a3b8;
            --mc-glow: rgba(34, 211, 238, 0.4);
        }

        body {
            position: relative;
            background-color: var(--mc-bg);
            color: var(--mc-text);
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
        }

        body *,
        body *::before,
        body *::after {
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
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
            background: radial-gradient(circle at 10% 18%, rgba(14, 165, 233, 0.18), transparent 42%),
                        radial-gradient(circle at 82% 0%, rgba(56, 189, 248, 0.16), transparent 40%),
                        linear-gradient(135deg, #f8fbff 0%, #eef4ff 55%, #e8f0ff 100%);
        }

        html.dark .mc-site-bg::before {
            background: radial-gradient(circle at 12% 20%, rgba(34, 211, 238, 0.24), transparent 42%),
                        radial-gradient(circle at 82% 0%, rgba(14, 165, 233, 0.22), transparent 40%),
                        linear-gradient(135deg, #020617 0%, #0b1120 58%, #020617 100%);
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
        html.dark .mc-site-bg::after {
            opacity: 0.46;
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

        #navbar > div,
        footer,
        .mc-glass-card {
            background: var(--mc-surface-soft) !important;
            border-color: var(--mc-border) !important;
            box-shadow: 0 16px 36px -24px rgba(15, 23, 42, 0.34) !important;
            backdrop-filter: blur(24px);
        }

        html.dark #navbar > div,
        html.dark footer,
        html.dark .mc-glass-card {
            box-shadow: 0 24px 56px -30px rgba(0, 0, 0, 0.85) !important;
        }

        :is(html.light, html[data-theme="light"]) [class*="bg-slate-950"],
        :is(html.light, html[data-theme="light"]) [class*="bg-slate-900"],
        :is(html.light, html[data-theme="light"]) [class*="bg-slate-800"] {
            background: rgba(255, 255, 255, 0.86) !important;
        }

        :is(html.light, html[data-theme="light"]) [class*="border-white/"],
        :is(html.light, html[data-theme="light"]) [class*="border-slate-"] {
            border-color: #cbd5e1 !important;
        }

        :is(html.light, html[data-theme="light"]) .text-white,
        :is(html.light, html[data-theme="light"]) .text-slate-100,
        :is(html.light, html[data-theme="light"]) .text-slate-200,
        :is(html.light, html[data-theme="light"]) .text-slate-300 {
            color: #0f172a !important;
        }

        :is(html.light, html[data-theme="light"]) .text-slate-400 {
            color: #475569 !important;
        }

        :is(html.light, html[data-theme="light"]) [class*="text-cyan-200"],
        :is(html.light, html[data-theme="light"]) [class*="text-cyan-300"],
        :is(html.light, html[data-theme="light"]) [class*="text-cyan-400"] {
            color: #0e7490 !important;
        }

        :is(html.light, html[data-theme="light"]) .custom-input {
            background: #fff !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        :is(html.light, html[data-theme="light"]) .custom-input::placeholder,
        :is(html.light, html[data-theme="light"]) .input-group .input-icon,
        :is(html.light, html[data-theme="light"]) .modern-checkbox .label-text,
        :is(html.light, html[data-theme="light"]) .auth-remember-label {
            color: #64748b !important;
            fill: currentColor !important;
        }

        :is(html.light, html[data-theme="light"]) .theme-fab {
            background: rgba(255, 255, 255, 0.92) !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
    </style>

    <script>
        window.websiteConfig = {
            fonts: {
                main: {
                    family: 'HarmonyOS Sans SC',
                    file: '/fonts/HarmonyOS_Sans_SC_Regular.woff2',
                    format: 'woff2',
                    fallback: "'FusionPixel', 'Quicksand', 'Noto Sans SC', sans-serif",
                    weight: 'normal'
                },
                title: {
                    family: 'HarmonyOS Sans SC',
                    file: '/fonts/HarmonyOS_Sans_SC_Regular.woff2',
                    format: 'woff2',
                    fallback: "'FusionPixel', 'Quicksand', 'Noto Sans SC', sans-serif",
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
            let savedTheme = null;
            try {
                savedTheme = localStorage.getItem('theme');
            } catch (e) {
                savedTheme = null;
            }
            const followsSystemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const resolvedTheme = (savedTheme === 'dark' || savedTheme === 'light')
                ? savedTheme
                : (followsSystemDark ? 'dark' : 'light');
            const root = document.documentElement;
            root.classList.toggle('dark', resolvedTheme === 'dark');
            root.classList.toggle('light', resolvedTheme !== 'dark');
            root.setAttribute('data-theme', resolvedTheme);
            root.style.colorScheme = resolvedTheme;

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
