<?php
declare(strict_types=1);

namespace Core;

use PDO;

final class PasswordResetService
{
    public const TOKEN_TTL_HOURS = 1;

    public static function issueTokenForEmail(PDO $db, string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($token);

        // Keep single active reset request per email and invalidate previous token(s).
        $deleteStmt = $db->prepare('DELETE FROM password_resets WHERE email = :email');
        $deleteStmt->execute([':email' => $email]);

        // Compatibility: `password_resets.token` column now stores token hash (SHA-256), not plain token.
        $insertStmt = $db->prepare('
            INSERT INTO password_resets (email, token, created_at)
            VALUES (:email, :token, NOW())
        ');
        $insertStmt->execute([
            ':email' => $email,
            ':token' => $tokenHash,
        ]);

        return $token;
    }

    /**
     * @return array{email:string}|null
     */
    public static function findValidRequestByToken(PDO $db, string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $tokenHash = self::hashToken($token);
        $stmt = $db->prepare('
            SELECT email
            FROM password_resets
            WHERE token = :token
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1
        ');
        $stmt->execute([':token' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || empty($row['email'])) {
            return null;
        }

        return [
            'email' => (string)$row['email'],
        ];
    }

    public static function consumeToken(PDO $db, string $token): void
    {
        if ($token === '') {
            return;
        }

        $tokenHash = self::hashToken($token);
        $stmt = $db->prepare('DELETE FROM password_resets WHERE token = :token');
        $stmt->execute([':token' => $tokenHash]);
    }

    public static function buildResetUrl(string $token): string
    {
        $baseUrl = defined('SITE_BASE_URL') ? trim((string)SITE_BASE_URL) : '';
        if ($baseUrl === '') {
            $baseUrl = 'http://localhost';
        }

        return rtrim($baseUrl, '/') . '/reset-password?token=' . rawurlencode($token);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
