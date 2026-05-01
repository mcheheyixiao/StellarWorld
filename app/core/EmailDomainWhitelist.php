<?php
declare(strict_types=1);

namespace Core;

final class EmailDomainWhitelist
{
    /**
     * @return array<int,string>
     */
    public static function normalize(string $rawDomains): array
    {
        $segments = preg_split('/[\r\n,]+/', $rawDomains) ?: [];
        $normalized = [];

        foreach ($segments as $segment) {
            $domain = strtolower(trim((string)$segment));
            if ($domain === '') {
                continue;
            }
            if (!self::isValidDomain($domain)) {
                continue;
            }
            $normalized[$domain] = $domain;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int,string> $allowedDomains
     */
    public static function isAllowed(string $email, bool $whitelistEnabled, array $allowedDomains): bool
    {
        $normalizedEmail = trim($email);
        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (!$whitelistEnabled) {
            return true;
        }

        $domain = strtolower((string)substr(strrchr($normalizedEmail, '@'), 1));
        if ($domain === '') {
            return false;
        }

        return in_array($domain, $allowedDomains, true);
    }

    public static function isValidDomain(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        if (str_contains($domain, '://') || str_contains($domain, '/') || str_contains($domain, '*')) {
            return false;
        }

        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) === 1;
    }
}
