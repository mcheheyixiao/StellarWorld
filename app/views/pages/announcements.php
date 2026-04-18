<div class="page-container">
    <div class="mc-glass-card p-6 md:p-8">
        <h1 class="text-fusion-pixel text-2xl text-white md:text-3xl">公告板</h1>
        <p class="mt-3 text-sm text-slate-300">
            公告仅用于展示服务器相关信息，不提供玩家评论功能。
        </p>
        <?php if (!empty($announcements)): ?>
            <ul class="mt-5 list-none space-y-2 pl-0">
                <?php foreach ($announcements as $a): ?>
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-900/70 px-4 py-3">
                        <a href="/announcements/view?id=<?= (int)$a['id'] ?>"
                           class="text-sm font-semibold text-slate-100 transition hover:text-cyan-200">
                            <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <span class="text-xs text-slate-400">
                            <?= htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="mt-5 text-sm text-slate-300">暂时还没有公告。</p>
        <?php endif; ?>
    </div>
</div>

