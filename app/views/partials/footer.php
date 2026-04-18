<footer class="site-content-column<?= !empty($isAdminLayout) ? ' site-content-column--admin' : '' ?> mb-4 mt-12 rounded-3xl border border-white/10 bg-slate-950/75 shadow-[0_24px_60px_-34px_rgba(0,0,0,0.9)] backdrop-blur-xl md:mb-8">
    <div class="grid gap-6 px-5 py-6 md:grid-cols-3 md:px-8 md:py-8">
        <div class="space-y-2">
            <h3 class="text-fusion-pixel text-xs tracking-wide text-cyan-200">繁星World</h3>
            <p class="text-sm leading-6 text-slate-300">一个长期运营的 Minecraft 公益服务器社区，专注稳定体验与玩家共建。</p>
        </div>
        <div class="space-y-2">
            <h4 class="text-xs font-semibold uppercase tracking-widest text-slate-400">快速链接</h4>
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="/" class="rounded-lg border border-white/10 px-2.5 py-1 text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-500/15 hover:text-cyan-200">主页</a>
                <a href="/about" class="rounded-lg border border-white/10 px-2.5 py-1 text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-500/15 hover:text-cyan-200">关于</a>
                <a href="/leaderboard" class="rounded-lg border border-white/10 px-2.5 py-1 text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-500/15 hover:text-cyan-200">排行榜</a>
                <a href="/announcements" class="rounded-lg border border-white/10 px-2.5 py-1 text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-500/15 hover:text-cyan-200">公告</a>
            </div>
        </div>
        <div class="space-y-2 text-sm text-slate-400 md:text-right">
            <p>© <?= date('Y') ?> 繁星World 公益服务器</p>
            <p>Powered by StellarVan</p>
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" class="inline-flex text-slate-400 transition hover:text-cyan-200">闽ICP备2026007132号-1</a>
        </div>
    </div>
</footer>

