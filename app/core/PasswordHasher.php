<?php
declare(strict_types=1);

namespace Core;

final class PasswordHasher
{
    public static function hash(string $password): string
    {
        $algo = self::preferredAlgorithm();
        $hash = password_hash($password, $algo);
        if ($hash === false && $algo !== PASSWORD_BCRYPT) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public static function verify(string $password, string $storedHash): bool
    {
        if (!self::isModernHash($storedHash)) {
            return false;
        }

        return password_verify($password, $storedHash);
    }

    public static function isModernHash(string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        $info = password_get_info($storedHash);
        return (int)($info['algo'] ?? 0) !== 0;
    }

    public static function needsRehash(string $storedHash): bool
    {
        if (!self::isModernHash($storedHash)) {
            return false;
        }

        return password_needs_rehash($storedHash, self::preferredAlgorithm());
    }

    private static function preferredAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
