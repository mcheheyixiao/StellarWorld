<?php
declare(strict_types=1);

namespace Core;

final class SensitiveDataSanitizer
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function sanitize($value)
    {
        return self::sanitizeValue($value, null);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function sanitizeValue($value, ?string $key)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = self::sanitizeValue(
                    $childValue,
                    is_string($childKey) ? $childKey : null
                );
            }

            return $sanitized;
        }

        if ($key !== null && self::isSensitiveKey($key)) {
            return self::REDACTED;
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return in_array($normalized, [
            'password',
            'password_hash',
            'token',
            'csrf_token',
            'smtp_pass',
            'smtp_password',
            'email_code',
            'captcha_answer',
            'remember_me',
            'authorization',
            'http_authorization',
            'x_api_token',
            'http_x_api_token',
            'http_x_server_token',
            'server_token',
        ], true);
    }
}
