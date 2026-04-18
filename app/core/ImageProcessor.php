<?php
declare(strict_types=1);

namespace Core;

/**
 * 基于 Intervention Image v3：生成多宽度 WebP，供 <picture> / srcset 使用。
 */
final class ImageProcessor
{
    /** @var list<int> */
    public const DEFAULT_WIDTHS = [480, 960, 1600];

    /** @var list<int> */
    public const BACKGROUND_WIDTHS = [480, 960, 1920];

    public static function isAvailable(): bool
    {
        return class_exists(\Intervention\Image\ImageManager::class) && extension_loaded('gd');
    }

    /**
     * 相册上传后：在同目录生成 {stem}-{width}.webp。
     */
    public static function generateGalleryResponsiveSet(string $absoluteSourcePath): void
    {
        if (!self::isAvailable() || !is_file($absoluteSourcePath) || !is_readable($absoluteSourcePath)) {
            return;
        }

        $dir = dirname($absoluteSourcePath);
        $stem = pathinfo($absoluteSourcePath, PATHINFO_FILENAME);

        try {
            $manager = \Intervention\Image\ImageManager::gd();
            foreach (self::DEFAULT_WIDTHS as $w) {
                $out = $dir . DIRECTORY_SEPARATOR . $stem . '-' . $w . '.webp';
                if (is_file($out)) {
                    continue;
                }
                $image = $manager->read($absoluteSourcePath);
                $image->scaleDown(width: $w);
                $image->toWebp(82)->save($out);
            }
        } catch (\Throwable $e) {
            // 生成失败不影响主图保存
        }
    }

    /**
     * 站点背景图：确保存在与 $absoluteSourcePath 同 stem 的 -{w}.webp。
     *
     * @param list<int> $widths
     */
    public static function ensureBackgroundWebpVariants(string $absoluteSourcePath, array $widths = self::BACKGROUND_WIDTHS): void
    {
        if (!self::isAvailable() || !is_file($absoluteSourcePath) || !is_readable($absoluteSourcePath)) {
            return;
        }

        $dir = dirname($absoluteSourcePath);
        $stem = pathinfo($absoluteSourcePath, PATHINFO_FILENAME);

        try {
            $manager = \Intervention\Image\ImageManager::gd();
            foreach ($widths as $w) {
                $out = $dir . DIRECTORY_SEPARATOR . $stem . '-' . $w . '.webp';
                if (is_file($out)) {
                    continue;
                }
                $image = $manager->read($absoluteSourcePath);
                $image->scaleDown(width: $w);
                $image->toWebp(80)->save($out);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param list<int> $widths
     * @return array{webpSrcset: string, fallback: string}|null
     */
    public static function buildWebpSrcsetForPublicPath(string $publicWebPath, array $widths = self::BACKGROUND_WIDTHS): ?array
    {
        $publicWebPath = '/' . ltrim($publicWebPath, '/');
        $stem = pathinfo($publicWebPath, PATHINFO_FILENAME);
        $dir = dirname($publicWebPath);
        if ($dir === '.' || $dir === '') {
            $dir = '';
        }
        $dirPrefix = $dir === '' || $dir === '/' ? '' : rtrim($dir, '/');

        $parts = [];
        foreach ($widths as $w) {
            $rel = ($dirPrefix === '' ? '' : $dirPrefix . '/') . $stem . '-' . $w . '.webp';
            $abs = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) {
                continue;
            }
            $parts[] = $rel . ' ' . $w . 'w';
        }

        if ($parts === []) {
            return null;
        }

        return [
            'webpSrcset' => implode(', ', array_map(static fn (string $p): string => $p, $parts)),
            'fallback' => $publicWebPath,
        ];
    }

    /**
     * 相册 <picture>：WebP srcset + 回退到原始上传路径。
     *
     * @return array{webpSrcset: string, fallback: string}|null
     */
    public static function galleryPictureSources(string $publicImagePath): ?array
    {
        $publicImagePath = '/' . ltrim($publicImagePath, '/');
        $stem = pathinfo($publicImagePath, PATHINFO_FILENAME);
        $dir = dirname($publicImagePath);
        $dirPrefix = $dir === '.' || $dir === '/' ? '' : rtrim($dir, '/');

        $parts = [];
        foreach (self::DEFAULT_WIDTHS as $w) {
            $rel = ($dirPrefix === '' ? '' : $dirPrefix . '/') . $stem . '-' . $w . '.webp';
            $abs = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) {
                continue;
            }
            $parts[] = $rel . ' ' . $w . 'w';
        }

        if ($parts === []) {
            return null;
        }

        return [
            'webpSrcset' => implode(', ', $parts),
            'fallback' => $publicImagePath,
        ];
    }
}
