<?php
$qqGroupUrl = 'https://qun.qq.com/universal-share/share?ac=1&authKey=8UpEiMMKS7hiIpwIkQEaeUGAExHYfcrAy7%2B3Tby6s9kgaCxFeaAPeFx6OJcvtgcO&busi_data=eyJncm91cENvZGUiOiIxMDg3NzA2OTA5IiwidG9rZW4iOiJidVNIYUlHSlFTcktjZWdpQ3M5UUZhQVVER3NEUUZsRitMcVg5Z1FMZU5PdlRkdThRWHpYNkhyY0hEUkQ0Q1FOIiwidWluIjoiMjAyODIwNzM2OSJ9&data=gAUJbwpJpVWgPhS_YE7kB58ahTI8W7KOlk6_HZdN5aog3sEji3SMGYgrGCpxkkbwLPiFpkXtGLKR8zU8gKy9vg&svctype=4&tempid=h5_group_info';
$serverDisplayAddress = isset($serverDisplayAddress) ? (string)$serverDisplayAddress : '';
$serverVersion = isset($serverVersion) ? (string)$serverVersion : '';

$heroSectionImageUrl = '/images/HeroSection.webp';
$heroSectionImagePath = BASE_PATH . '/public' . $heroSectionImageUrl;
if (!is_file($heroSectionImagePath)) {
    $heroSectionImageUrl = '/images/HeroSection.png';
}

$aboutConfigPath = BASE_PATH . '/app/config/about_config.php';
if (is_file($aboutConfigPath)) {
    $aboutConfig = include $aboutConfigPath;
    if (is_array($aboutConfig) && isset($aboutConfig['contacts']['items']) && is_array($aboutConfig['contacts']['items'])) {
        foreach ($aboutConfig['contacts']['items'] as $contact) {
            $label = (string)($contact['label'] ?? '');
            $url = (string)($contact['url'] ?? '');
            if ($url !== '' && stripos($label, 'QQ') !== false) {
                $qqGroupUrl = $url;
                break;
            }
        }
    }
}
?>

<div class="mc-home-page flex flex-col gap-6" data-public-status-ws-url="<?= htmlspecialchars((string)($publicStatusWsUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <section class="mc-hero-stage reveal-on-scroll reveal-on-scroll--hero relative overflow-hidden">
        <div class="mc-hero-stage-layer absolute inset-0 z-0" aria-hidden="true">
            <div class="mc-hero-stage-bg"></div>
            <div class="mc-hero-stage-glow"></div>
            <div class="mc-hero-stage-gradient bg-gradient-to-b from-transparent via-[#020617]/70 to-[#020617]"></div>
        </div>
        <div class="page-container mc-hero-stage-inner relative z-10 w-full max-w-full md:max-w-3xl lg:max-w-5xl xl:max-w-[1320px] 2xl:max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-10">
            <div class="hero-container mc-hero-reference-container">
                <div class="hero-content mc-hero-reference-content">
                    <div class="hero-badge">
                        <i class="mdi mdi-lightning-bolt-outline"></i>
                        <span>Minecraft 公益服务器</span>
                    </div>

                    <h1 class="text-fusion-pixel">
                        欢迎来到<br>
                        <span class="highlight">繁星World</span>
                    </h1>

                    <p class="hero-subtitle">
                        原版 + 中世纪冒险生存。稳定、纯净、长期维护，支持长期定居与团队协作，适合慢节奏发展与持续探索。
                    </p>

                    <div class="hero-features">
                        <div class="h-feature"><i class="mdi mdi-check-circle-outline"></i>纯净生存</div>
                        <div class="h-feature"><i class="mdi mdi-check-circle-outline"></i>轻 RPG</div>
                        <div class="h-feature"><i class="mdi mdi-check-circle-outline"></i>长期运营</div>
                    </div>

                    <div class="hero-buttons">
                        <button id="copyAddressBtn" class="copy-btn mc-btn mc-hero-cta js-copy-ip" type="button">
                            <i class="mdi mdi-content-copy"></i>
                            <span>复制 IP 地址</span>
                        </button>
                        <a href="<?= htmlspecialchars($qqGroupUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mc-btn mc-hero-ghost">
                            <i class="mdi mdi-qqchat"></i>
                            <span>加入 QQ 社区</span>
                        </a>
                        <button id="refreshStatusBtn" class="status-refresh-btn mc-btn mc-hero-refresh" type="button" aria-label="刷新服务器状态">
                            <i class="mdi mdi-refresh"></i>
                            <span>刷新状态</span>
                        </button>
                    </div>

                    <div class="mc-hero-address mt-4 inline-flex max-w-full flex-wrap items-center gap-2 rounded-2xl border border-slate-300/65 border-opacity-10 bg-slate-200/90 px-3 py-2 text-sm text-slate-700 shadow-xl backdrop-blur-xl dark:border-white/15 dark:bg-slate-900/72 dark:text-slate-300">
                        <span class="mc-hero-address-label text-xs uppercase tracking-widest text-slate-600 dark:text-slate-300">服务器地址</span>
                        <code id="serverAddress" class="break-all font-mono text-sm text-slate-700 dark:text-slate-300"><?= htmlspecialchars($serverDisplayAddress, ENT_QUOTES, 'UTF-8') ?></code>
                        <div class="server-status server-status--inline">
                            <span class="status-dot" id="serverStatusDot"></span>
                            <span class="status-text">
                                <span id="serverStatusText">连接中...</span>
                                <span class="highlight-green" id="onlinePlayers"><?= htmlspecialchars((string)($serverInfo['players_online'] ?? '--'), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </div>
                    </div>
                    <div class="mc-hero-motd rounded-2xl border border-slate-300/60 border-opacity-10 bg-white/80 p-4 shadow-xl backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/68">
                        <div class="mb-2 text-xs uppercase tracking-widest text-slate-600 dark:text-slate-300">MOTD</div>
                        <div class="detail-value motd text-sm text-slate-700 dark:text-slate-300" id="motdDisplay">--</div>
                    </div>

                    <div class="mt-4 players-section" id="playersSection">
                        <h3 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">当前在线玩家</h3>
                        <div class="players-list grid max-h-52 grid-cols-2 gap-2 overflow-y-auto rounded-2xl border border-slate-300/60 border-opacity-10 bg-white/80 p-3 shadow-xl backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/72" id="playersList">
                            <div class="loading-players col-span-full text-center text-slate-500 dark:text-slate-300">加载中...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="page-container flex flex-col gap-6">
        <section class="mc-glass-card mc-card-tier-2 reveal-on-scroll rounded-2xl border border-white/10 border-opacity-10 p-6 shadow-xl backdrop-blur-xl md:p-8">
            <div class="mb-5">
                <h2 class="text-fusion-pixel text-xl text-slate-900 dark:text-white">🧭 玩法 + 特色</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">三条玩法路线 + 服务器核心设计原则，一眼看懂世界定位。</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article class="playstyle-card mc-hover-lift rounded-2xl border border-emerald-300/40 border-opacity-10 bg-gradient-to-br from-emerald-100/70 to-teal-100/50 p-5 shadow-xl backdrop-blur-xl dark:border-emerald-300/20 dark:from-emerald-500/15 dark:to-teal-500/10">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">🌲 Survival</h3>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">专注长期生存、城镇建设、资源经营与公共设施共建。</p>
                </article>
                <article class="playstyle-card mc-hover-lift rounded-2xl border border-rose-300/40 border-opacity-10 bg-gradient-to-br from-rose-100/70 to-orange-100/50 p-5 shadow-xl backdrop-blur-xl dark:border-rose-300/20 dark:from-rose-500/15 dark:to-orange-500/10">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">⚔️ PvP</h3>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">竞技擂台与活动对抗并存，兼顾公平与策略，不做数值碾压。</p>
                </article>
                <article class="playstyle-card mc-hover-lift rounded-2xl border border-sky-300/40 border-opacity-10 bg-gradient-to-br from-sky-100/70 to-indigo-100/50 p-5 shadow-xl backdrop-blur-xl dark:border-sky-300/20 dark:from-sky-500/15 dark:to-indigo-500/10">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">🧪 轻RPG</h3>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">保留原版生存节奏，加入任务线、成长目标与小型事件，强调长期游玩与团队协作。</p>
                </article>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <span class="mc-feature-badge">公平生态</span>
                <span class="mc-feature-badge">稳定运营</span>
                <span class="mc-feature-badge">社群活动</span>
                <span class="mc-feature-badge">持续迭代</span>
                <span class="mc-feature-badge">非 P2W</span>
                <span class="mc-feature-badge">存档安全优先</span>
            </div>
        </section>

        <section id="core-systems" class="mc-glass-card mc-card-tier-2 reveal-on-scroll rounded-2xl border border-white/10 border-opacity-10 p-6 shadow-xl backdrop-blur-xl md:p-8">
            <div class="mb-5">
                <h2 class="text-fusion-pixel text-xl text-slate-900 dark:text-white">📊 核心系统</h2>
                <p class="mt-2 max-w-4xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">围绕生存、定居、交易与协作构建的长期循环，让这个世界不仅能开荒，也值得久住。</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <article class="mc-hover-lift h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-white/15 dark:bg-slate-900/72">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">💰 经济系统</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">通过采集、建造、探索与交易获得收益，让资源与时间投入都具备长期价值。</p>
                </article>
                <article class="mc-hover-lift h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-white/15 dark:bg-slate-900/72">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">🏘️ 城镇系统</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">从个人生存走向聚落协作，在建设、分工与定居中形成真正的社区归属感。</p>
                </article>
                <article class="mc-hover-lift h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-white/15 dark:bg-slate-900/72">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">⚖️ 生存秩序</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">强调公平生态、稳定运营与长期保留，不做快餐式消耗服。</p>
                </article>
                <article class="mc-hover-lift h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-white/15 dark:bg-slate-900/72">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">📈 成长路径</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">从开荒到发展，再到参与聚落与社区，每一阶段都有明确目标与空间。</p>
                </article>
            </div>
        </section>

        <section id="newbie-guide" class="mc-glass-card mc-card-tier-3 reveal-on-scroll rounded-2xl border border-white/10 border-opacity-10 p-6 shadow-xl backdrop-blur-xl md:p-8">
            <div class="mb-5">
                <h2 class="text-fusion-pixel text-xl text-slate-900 dark:text-white">📜 新手引导</h2>
                <p class="mt-2 max-w-4xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">不需要一进服就懂完所有内容，你只需要按自己的节奏迈出第一步。</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <article class="h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl dark:border-white/15 dark:bg-slate-900/72">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-300/45 border-opacity-10 bg-cyan-500/10 text-base font-semibold text-cyan-700 shadow-xl backdrop-blur-xl dark:text-cyan-200">①</span>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">进入世界</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">从出生点开始认识服务器，快速了解规则、方向与主要玩法。</p>
                </article>
                <article class="h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl dark:border-white/15 dark:bg-slate-900/72">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-300/45 border-opacity-10 bg-cyan-500/10 text-base font-semibold text-cyan-700 shadow-xl backdrop-blur-xl dark:text-cyan-200">②</span>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">开始生存</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">收集资源、搭建庇护所，完成属于自己的第一段原版开荒。</p>
                </article>
                <article class="h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl dark:border-white/15 dark:bg-slate-900/72">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-300/45 border-opacity-10 bg-cyan-500/10 text-base font-semibold text-cyan-700 shadow-xl backdrop-blur-xl dark:text-cyan-200">③</span>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">融入社区</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">加入聚落、结识伙伴，或与朋友一起寻找适合长期发展的定居点。</p>
                </article>
                <article class="h-full rounded-2xl border border-white/10 border-opacity-10 bg-white/80 p-6 shadow-xl backdrop-blur-xl dark:border-white/15 dark:bg-slate-900/72">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-300/45 border-opacity-10 bg-cyan-500/10 text-base font-semibold text-cyan-700 shadow-xl backdrop-blur-xl dark:text-cyan-200">④</span>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">长期发展</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">当你拥有稳定据点后，交易、建设、探索与社区活动才会真正展开。</p>
                </article>
            </div>
        </section>

        <section class="mc-glass-card mc-card-tier-1 reveal-on-scroll rounded-2xl border border-cyan-300/30 border-opacity-10 p-6 shadow-xl backdrop-blur-xl md:p-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-fusion-pixel text-xl text-slate-900 dark:text-white md:text-2xl">🚀 立即加入服务器</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">加入我们，一起在新的世界里开荒</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="copyAddressBtnBottom" class="copy-btn mc-btn mc-btn-primary js-copy-ip inline-flex items-center gap-2 rounded-2xl border border-cyan-200/45 border-opacity-10 px-5 py-3 text-sm font-semibold shadow-xl backdrop-blur-xl">
                        <i class="mdi mdi-content-copy"></i>
                        <span>📋 复制 IP</span>
                    </button>
                    <a href="<?= htmlspecialchars($qqGroupUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mc-btn mc-btn-ghost inline-flex items-center gap-2 rounded-2xl border border-cyan-300/45 border-opacity-10 px-5 py-3 text-sm font-semibold shadow-xl backdrop-blur-xl">
                        <i class="mdi mdi-qqchat"></i>
                        <span>💬 加入QQ群</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="mc-glass-card mc-card-tier-3 reveal-on-scroll rounded-2xl border border-white/10 border-opacity-10 p-5 shadow-xl backdrop-blur-xl">
            <h2 class="mb-3 text-fusion-pixel text-lg text-slate-900 dark:text-white">🌍 友情链接</h2>
            <div class="friendly-links flex flex-wrap gap-2">
                <a href="https://started.ink/home" target="_blank" rel="noopener noreferrer" class="friendly-link-item inline-flex items-center rounded-xl border border-cyan-300/30 border-opacity-10 bg-cyan-500/10 px-3 py-1.5 text-sm text-cyan-700 shadow-xl backdrop-blur-xl transition-all hover:-translate-y-1 hover:bg-cyan-500/25 dark:text-cyan-200">星遥游戏</a>
                <a href="https://scarefree.cn/" target="_blank" rel="noopener noreferrer" class="friendly-link-item inline-flex items-center rounded-xl border border-cyan-300/30 border-opacity-10 bg-cyan-500/10 px-3 py-1.5 text-sm text-cyan-700 shadow-xl backdrop-blur-xl transition-all hover:-translate-y-1 hover:bg-cyan-500/25 dark:text-cyan-200">星遥工坊</a>
            </div>
        </section>
    </div>
</div>

<script src="/scripts/home-status.js"></script>

<style>
.mc-home-page {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
}

.mc-home-page.is-loaded {
    opacity: 1;
    transform: translateY(0);
}

.reveal-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
}

.reveal-on-scroll.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.mc-hero-stage {
    width: 100vw;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    min-height: min(82vh, 900px);
    isolation: isolate;
}

.mc-hero-stage-layer {
    pointer-events: none;
}

.mc-hero-stage-bg,
.mc-hero-stage-glow,
.mc-hero-stage-gradient {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.mc-hero-stage-bg {
    z-index: 0;
    background-image: url('<?= htmlspecialchars($heroSectionImageUrl, ENT_QUOTES, 'UTF-8') ?>');
    background-position: center;
    background-size: cover;
}

.mc-hero-stage-glow {
    z-index: 1;
    background: radial-gradient(circle at 15% 14%, rgba(56, 189, 248, 0.24), transparent 44%),
                radial-gradient(circle at 86% 4%, rgba(14, 165, 233, 0.22), transparent 40%);
}

.mc-hero-stage-gradient {
    z-index: 2;
}

/* light theme hero background adaptation: reduce dark overlay and preserve warm sunset layers in Hero */
:is(html.light, html[data-theme="light"]) .mc-home-page .mc-hero-stage-gradient {
    background-image: linear-gradient(
        to bottom,
        rgba(248, 250, 252, 0.02) 0%,
        rgba(255, 255, 255, 0.08) 48%,
        rgba(248, 250, 252, 0.20) 100%
    );
}

/* light theme hero background adaptation: add subtle warm highlight for better original-image fidelity */
:is(html.light, html[data-theme="light"]) .mc-home-page .mc-hero-stage-glow {
    background:
        radial-gradient(circle at 16% 14%, rgba(251, 191, 36, 0.14), transparent 46%),
        radial-gradient(circle at 84% 8%, rgba(249, 115, 22, 0.11), transparent 44%);
}

.mc-hero-stage-inner {
    min-height: min(82vh, 900px);
    display: flex;
    align-items: center;
}

.mc-hero-reference-container.hero-container {
    width: 100%;
    display: flex;
    padding-top: 4.2rem;
    position: relative;
    z-index: 2;
}

.mc-hero-reference-content.hero-content {
    max-width: 44rem;
}

.mc-hero-reference-content .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.02));
    border-left: 3px solid rgba(255, 255, 255, 0.9);
    padding: 0.42rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.84rem;
    color: #f8fafc;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.mc-hero-reference-content .server-status {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    padding: 0.48rem 0.9rem;
    margin-bottom: 1.25rem;
    color: #ffffff;
    font-size: 0.92rem;
}

.mc-hero-reference-content .server-status--inline {
    margin-bottom: 0;
    margin-left: 0.15rem;
    padding: 0.34rem 0.72rem;
    font-size: 0.86rem;
}

.mc-hero-reference-content .server-status--inline .status-text {
    gap: 0.4rem;
}

.mc-hero-reference-content .status-text {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
}

.mc-hero-reference-content .highlight-green {
    display: inline-block;
    font-weight: 800;
    background: linear-gradient(90deg, #22c55e, #86efac, #4ade80);
    background-size: 220% 220%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: rainbow-flow 4s ease infinite;
}

.mc-hero-reference-content h1 {
    font-size: clamp(2.3rem, 6vw, 4.1rem);
    line-height: 1.08;
    font-weight: 900;
    margin-bottom: 1rem;
    letter-spacing: -0.02em;
    text-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);
    color: #ffffff;
}

.mc-hero-reference-content h1 .highlight {
    background: linear-gradient(90deg, #22c55e, #4ade80, #86efac, #22c55e);
    background-size: 320% 320%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline-block;
    animation: rainbow-flow 5s ease infinite;
}

.mc-hero-reference-content .hero-subtitle {
    font-size: 1.02rem;
    color: rgba(241, 245, 249, 0.9);
    margin-bottom: 1.5rem;
    max-width: 37rem;
    border-left: 2px solid rgba(255, 255, 255, 0.5);
    padding-left: 1rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.45);
}

.mc-hero-reference-content .hero-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.mc-hero-reference-content .h-feature {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #e2e8f0;
    font-size: 0.88rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.08);
    padding: 0.45rem 0.75rem;
    border-radius: 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(10px);
}

.mc-hero-reference-content .h-feature i {
    color: #4ade80;
}

.mc-hero-reference-content .hero-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.mc-hero-cta,
.mc-hero-ghost,
.mc-hero-refresh {
    border-radius: 0.9rem;
    padding: 0.74rem 1rem;
    min-height: 2.85rem;
    font-size: 0.88rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}

.mc-hero-cta {
    background: rgba(255, 255, 255, 0.92);
    color: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.7);
}

.mc-hero-ghost,
.mc-hero-refresh {
    background: rgba(15, 23, 42, 0.62);
    color: #e2e8f0;
    border: 1px solid rgba(148, 163, 184, 0.4);
}

.mc-hero-refresh i {
    transition: transform 0.3s ease;
}

.mc-hero-refresh:hover i {
    transform: rotate(180deg);
}

.mc-hero-stats-grid {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.mc-hero-stats-grid .mc-status-line {
    padding: 0.58rem 0.68rem;
    font-size: 0.84rem;
}

.mc-hero-motd {
    margin-top: 0.75rem;
}

:is(html.light, html[data-theme="light"]) .mc-home-page .mc-hero-reference-content .hero-badge {
    color: #0f172a;
    border-left-color: rgba(15, 23, 42, 0.85);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.45));
}

:is(html.light, html[data-theme="light"]) .mc-home-page .mc-hero-reference-content .server-status {
    color: #0f172a;
    border-color: rgba(148, 163, 184, 0.42);
    background: rgba(248, 250, 252, 0.84);
}

:is(html.light, html[data-theme="light"]) .mc-home-page .mc-hero-reference-content .hero-subtitle {
    border-left-color: rgba(255, 255, 255, 0.75);
}

.mc-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    border: 1px solid rgba(148, 163, 184, 0.55);
    background: #e2e8f0;
    padding: 0.3rem 0.72rem;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    color: #1e293b;
}

html.dark .mc-badge {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.mc-feature-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    border: 1px solid rgba(14, 165, 233, 0.28);
    background: rgba(14, 165, 233, 0.08);
    color: #0e7490;
    padding: 0.32rem 0.78rem;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

html.dark .mc-feature-badge {
    border-color: rgba(34, 211, 238, 0.28);
    background: rgba(34, 211, 238, 0.11);
    color: #a5f3fc;
}

.mc-feature-badge:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -16px rgba(14, 165, 233, 0.8);
}

.mc-card-tier-1 {
    border: 1px solid rgba(34, 211, 238, 0.3) !important;
    box-shadow: 0 24px 52px -30px rgba(14, 116, 144, 0.35), 0 0 0 1px rgba(34, 211, 238, 0.18) inset !important;
}

html.dark .mc-card-tier-1 {
    box-shadow: 0 24px 52px -28px rgba(0, 0, 0, 0.84), 0 0 0 1px rgba(34, 211, 238, 0.24) inset, 0 0 32px -20px rgba(34, 211, 238, 0.5) !important;
}

.mc-card-tier-2 {
    border: 1px solid rgba(148, 163, 184, 0.26) !important;
}

.mc-card-tier-3 {
    border: 1px solid rgba(148, 163, 184, 0.18) !important;
    background: rgba(255, 255, 255, 0.68) !important;
}

html.dark .mc-card-tier-3 {
    background: rgba(15, 23, 42, 0.58) !important;
}

.mc-hover-lift {
    transition: color 0.3s ease, background-color 0.3s ease, border-color 0.3s ease;
}

.mc-btn {
    transition: all 0.3s ease;
}

.mc-btn:hover {
    transform: translateY(-2px);
}

.mc-btn-primary {
    background: linear-gradient(140deg, #06b6d4 0%, #0ea5e9 100%);
    color: #ffffff;
    box-shadow: 0 14px 30px -18px rgba(14, 116, 144, 0.6);
}

.mc-btn-primary:hover {
    box-shadow: 0 18px 34px -18px rgba(14, 116, 144, 0.75), 0 0 20px -8px rgba(34, 211, 238, 0.8);
}

.mc-btn-secondary,
.mc-btn-ghost {
    background: rgba(255, 255, 255, 0.88);
    color: #0f172a;
}

html.dark .mc-btn-secondary,
html.dark .mc-btn-ghost {
    background: rgba(15, 23, 42, 0.78);
    color: #e2e8f0;
}

.status-refresh-btn i {
    transition: transform 0.3s ease;
}

.status-refresh-btn:hover i {
    transform: rotate(180deg);
}

.mc-status-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.82);
    padding: 0.7rem 0.8rem;
    color: #334155;
    font-size: 0.9rem;
}

.mc-status-line strong {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

html.dark .mc-status-line {
    border-color: rgba(255, 255, 255, 0.12);
    background: rgba(15, 23, 42, 0.72);
    color: #cbd5e1;
}

html.dark .mc-status-line strong {
    color: #f8fafc;
}

.copy-btn.copied {
    background: #16a34a !important;
    color: #fff !important;
}

.join-btn.joined {
    background: #0891b2 !important;
    color: #fff !important;
}

.status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background: #f59e0b;
    animation: pulse 2s infinite;
}

.status-dot.online { background: #22c55e; animation: none; }
.status-dot.offline { background: #ef4444; animation: none; }
.refresh-btn .spinning,
.status-refresh-btn .spinning { animation: spin 1s linear infinite; }

.motd {
    white-space: pre-wrap;
    word-break: break-word;
    font-family: Consolas, monospace;
    text-align: center;
}

.motd * { text-align: center; }

.player-item {
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: rgba(255, 255, 255, 0.88);
    padding: 0.5rem 0.65rem;
    text-align: center;
    color: #334155;
    font-size: 0.8rem;
}

html.dark .player-item {
    border-color: rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.85);
    color: #e2e8f0;
}

.no-players,
.loading-players {
    grid-column: 1 / -1;
    text-align: center;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.85rem;
}

html.dark .no-players,
html.dark .loading-players {
    color: #94a3b8;
}

@media (max-width: 767.98px) {
    .mc-home-page {
        gap: 1rem;
    }

    .mc-hero-reference-container.hero-container {
        padding-top: 3.45rem;
    }

    .mc-hero-reference-content .hero-badge {
        font-size: 0.76rem;
        padding: 0.34rem 0.78rem;
    }

    .mc-hero-reference-content .server-status {
        font-size: 0.84rem;
    }

    .mc-hero-reference-content h1 {
        line-height: 1.18;
    }

    .mc-hero-reference-content .hero-subtitle {
        font-size: 0.95rem;
        margin-bottom: 1.15rem;
    }

    .mc-hero-reference-content .hero-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .mc-hero-cta,
    .mc-hero-ghost,
    .mc-hero-refresh {
        width: 100%;
    }

    .mc-hero-stats-grid {
        grid-template-columns: 1fr;
    }

    .mc-btn,
    .mc-btn-primary,
    .mc-btn-secondary,
    .mc-btn-ghost {
        width: 100%;
        justify-content: center;
        min-height: 2.9rem;
        font-size: 0.95rem;
    }

    .players-list {
        grid-template-columns: 1fr !important;
    }
}

@keyframes rainbow-flow {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
