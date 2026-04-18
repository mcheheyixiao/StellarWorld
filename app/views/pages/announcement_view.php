<div class="page-container">
    <div class="mc-glass-card p-6 md:p-8">
        <h1 class="text-fusion-pixel text-2xl text-white md:text-3xl">
            <?= htmlspecialchars($announcement['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="mt-2 text-xs text-slate-400">
            发布时间：<?= htmlspecialchars($announcement['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="mt-5 whitespace-pre-wrap text-sm leading-7 text-slate-200">
            <?= nl2br(htmlspecialchars($announcement['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <div class="mt-6">
            <a href="/announcements" class="inline-flex items-center rounded-xl border border-white/15 px-4 py-2 text-sm text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-500/15 hover:text-cyan-200">
                返回公告列表
            </a>
        </div>
    </div>
</div>

