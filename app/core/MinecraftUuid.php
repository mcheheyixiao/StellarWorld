<?php
declare(strict_types=1);

namespace Core;

final class MinecraftUuid
{
    /**
     * 兼容旧调用：统一按离线模式 UUID 生成（Version 3, name-based MD5）。
     */
    public static function resolveUuid(string $username): string
    {
        return self::getOfflineUuid($username);
    }

    public static function getOfflineUuid(string $username): string
    {
        $data = hex2bin(md5('OfflinePlayer:' . $username));
        $data[6] = chr(ord($data[6]) & 0x0f | 0x30); // Version 3
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122
        $hex = bin2hex($data);
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * 将 32 位十六进制或带横线 UUID 规范为 RFC 横线格式；非法输入返回空字符串。
     */
    public static function normalizeToDashed(string $uuid): string
    {
        $uuid = strtolower(trim($uuid));
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid) === 1) {
            return $uuid;
        }
        $hex = preg_replace('/[^a-f0-9]/', '', $uuid) ?? '';
        if (strlen($hex) !== 32) {
            return '';
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

