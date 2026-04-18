<?php
/** @var array<string,mixed> $aboutConfig */
/** @var array<int,array<string,string|null>> $members */

$hero = $aboutConfig['hero'] ?? [];
$infoSections = $aboutConfig['info_sections'] ?? [];
$rules = $aboutConfig['rules'] ?? [];
$contacts = $aboutConfig['contacts'] ?? [];
$membersConfig = $aboutConfig['members'] ?? [];
?>

<div class="page-container">
    <div class="mc-glass-card mb-6 overflow-hidden p-6 md:p-10">
        <div class="grid items-center gap-6 md:grid-cols-2">
            <div>
                <h1 class="text-fusion-pixel text-2xl text-white md:text-3xl"><?= htmlspecialchars((string)($hero['title'] ?? '关于服务器'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-3 text-sm text-slate-300"><?= htmlspecialchars((string)($hero['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-3 text-sm leading-7 text-slate-300"><?= htmlspecialchars((string)($hero['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($hero['bullets']) && is_array($hero['bullets'])): ?>
                    <ul class="mt-4 grid gap-2">
                        <?php foreach ($hero['bullets'] as $bullet): ?>
                            <li class="inline-flex items-center gap-2 rounded-xl border border-cyan-300/25 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-200">
                                <i class="mdi mdi-star-four-points text-cyan-300"></i>
                                <span><?= htmlspecialchars((string)$bullet, ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                <p class="mb-2 text-xs uppercase tracking-wider text-slate-400">服务器愿景</p>
                <p class="text-sm leading-7 text-slate-300">稳定、公平、长期维护。让每位玩家都能在持续演进的世界中留下自己的建筑与故事。</p>
            </div>
        </div>
    </div>

    <div class="mc-glass-card mb-6 p-6 md:p-8">
        <h2 class="mb-4 text-fusion-pixel text-xl text-white">服务器定位 & 特色</h2>
        <div class="grid gap-3 md:grid-cols-3">
            <?php foreach ($infoSections as $section): ?>
                <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                    <div class="mb-2 text-cyan-300">
                        <?php if (!empty($section['icon'])): ?>
                            <i class="mdi <?= htmlspecialchars((string)$section['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <?php else: ?>
                            <i class="mdi mdi-cube-outline"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-base font-semibold text-white">
                        <?= htmlspecialchars((string)($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <p class="mt-2 text-sm leading-7 text-slate-300">
                        <?= htmlspecialchars((string)($section['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mc-glass-card mb-6 p-6 md:p-8">
        <h2 class="text-fusion-pixel text-xl text-white"><?= htmlspecialchars((string)($rules['title'] ?? '基本规则'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($rules['description'])): ?>
            <p class="mt-2 text-sm text-slate-300">
                <?= htmlspecialchars((string)$rules['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <?php if (!empty($rules['items']) && is_array($rules['items'])): ?>
                <?php foreach ($rules['items'] as $rule): ?>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                        <div class="flex items-center gap-2">
                            <div class="text-cyan-300">
                                <?php if (!empty($rule['icon'])): ?>
                                    <i class="mdi <?= htmlspecialchars((string)$rule['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <?php else: ?>
                                    <i class="mdi mdi-alert-circle-outline"></i>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-base font-semibold text-white">
                                <?= htmlspecialchars((string)($rule['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        </div>
                        <p class="mt-2 text-sm leading-7 text-slate-300">
                            <?= htmlspecialchars((string)($rule['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mc-glass-card mb-6 p-6 md:p-8">
        <h2 class="mb-4 text-fusion-pixel text-xl text-white">服务器发展纪事</h2>

        <?php if (!empty($milestones)): ?>
            <div class="space-y-3">
                <?php foreach ($milestones as $m): ?>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                            <div class="text-sm font-semibold text-cyan-200">
                                <?= htmlspecialchars((string)($m['milestone_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="mt-2 text-sm leading-7 text-slate-300">
                                <?= nl2br(htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-300">
                暂无纪事。你可以在后台「发展纪事管理」中添加内容后，这里会自动展示为时间轴。
            </p>
        <?php endif; ?>
    </div>

    <div class="mc-glass-card mb-6 p-6 md:p-8">
        <h2 class="text-fusion-pixel text-xl text-white"><?= htmlspecialchars((string)($membersConfig['title'] ?? '管理团队 / 成员名单'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($membersConfig['description'])): ?>
            <p class="mt-2 text-sm text-slate-300">
                <?= htmlspecialchars((string)$membersConfig['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($members)): ?>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <?php foreach ($members as $member): ?>
                    <div class="flex gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4">
                        <div class="h-14 w-14 overflow-hidden rounded-xl bg-slate-800">
                            <?php if (!empty($member['avatar'])): ?>
                                <img src="<?= htmlspecialchars((string)$member['avatar'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars((string)($member['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                     class="h-full w-full object-cover">
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center text-slate-400">
                                    <i class="mdi mdi-account-outline"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-white">
                                <?= htmlspecialchars((string)($member['username'] ?? '未知玩家'), ENT_QUOTES, 'UTF-8') ?>
                            </h4>
                            <p class="mt-1 text-sm text-slate-300">
                                <?= htmlspecialchars((string)($member['role'] ?? '服务器成员'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-300">
                当前尚未配置成员名单。请参考部署文档，在
                <code>public/assets/memberlist/whitelist.json</code>
                中添加管理团队与特殊成员信息。
            </p>
        <?php endif; ?>
    </div>

    <div class="mc-glass-card p-6 md:p-8">
        <h2 class="text-fusion-pixel text-xl text-white"><?= htmlspecialchars((string)($contacts['title'] ?? '联系与反馈'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($contacts['description'])): ?>
            <p class="mt-2 text-sm text-slate-300">
                <?= htmlspecialchars((string)$contacts['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <?php if (!empty($contacts['items']) && is_array($contacts['items'])): ?>
                <?php foreach ($contacts['items'] as $contact): ?>
                    <a class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4 transition hover:border-cyan-300/40 hover:bg-slate-900/90" href="<?= htmlspecialchars((string)($contact['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <div class="text-cyan-300">
                            <?php if (!empty($contact['icon'])): ?>
                                <i class="mdi <?= htmlspecialchars((string)$contact['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?php else: ?>
                                <i class="mdi mdi-link-variant"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-white">
                                <?= htmlspecialchars((string)($contact['label'] ?? '未命名渠道'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($contact['hint'])): ?>
                                <div class="text-xs text-slate-400">
                                    <?= htmlspecialchars((string)$contact['hint'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($contacts['tip'])): ?>
            <p class="mt-4 text-sm text-slate-400">
                <?= htmlspecialchars((string)$contacts['tip'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>
</div>

