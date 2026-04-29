<?php
declare(strict_types=1);

namespace Core;

final class AuthMePassword
{
    public static function hash(string $password): string
    {
        $salt = substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 16);
        $hash = hash('sha256', hash('sha256', $password) . $salt);

        return '$SHA$' . $salt . '$' . $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        $parts = explode('$', $hash);
        if (count($parts) !== 4 || $parts[1] !== 'SHA') {
            return false;
        }

        $salt = $parts[2];
        $validHash = hash('sha256', hash('sha256', $password) . $salt);

        return hash_equals($validHash, $parts[3]);
    }
}
