<div class="page-container">
    <div class="mc-glass-card p-6 md:p-8">
        <h1 class="text-fusion-pixel text-2xl text-white md:text-3xl">服务器相册</h1>
        <p class="mt-3 text-sm text-slate-300">
            这里收集了玩家们在服务器中的精彩瞬间。
        </p>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $img): ?>
                    <?php
                    $path = (string)($img['image_path'] ?? '');
                    $pic = \Core\ImageProcessor::galleryPictureSources($path);
                    ?>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-3">
                        <div class="mb-3 overflow-hidden rounded-xl">
                            <?php if ($pic !== null): ?>
                                <picture>
                                    <source type="image/webp" srcset="<?= htmlspecialchars($pic['webpSrcset'], ENT_QUOTES, 'UTF-8') ?>" sizes="(max-width:600px) 100vw, 220px">
                                    <img src="<?= htmlspecialchars($pic['fallback'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($img['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy"
                                         decoding="async"
                                         class="block w-full">
                                </picture>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($img['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy"
                                     decoding="async"
                                     class="block w-full">
                            <?php endif; ?>
                        </div>
                        <h2 class="text-base font-semibold text-white">
                            <?= htmlspecialchars($img['title'] ?? '未命名截图', ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <?php if (!empty($img['description'])): ?>
                            <p class="mt-2 text-sm text-slate-300">
                                <?= htmlspecialchars($img['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-slate-300">暂时还没有截图，欢迎率先上传你的 MC 瞬间！</p>
            <?php endif; ?>
        </div>
    </div>
</div>
