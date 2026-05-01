<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class CaptchaService
{
    private const SESSION_CHALLENGES_KEY = '_auth_captcha_challenges';
    private const SESSION_REFRESH_KEY = '_auth_captcha_refresh';

    private int $ttlSeconds;
    private int $refreshCooldownSeconds;
    private int $refreshWindowSeconds;
    private int $maxRefreshesPerWindow;

    /**
     * @param array<string,int> $config
     */
    public function __construct(array $config = [])
    {
        $this->ttlSeconds = max(60, (int)($config['ttl_seconds'] ?? (defined('AUTH_CAPTCHA_EXPIRE_SECONDS') ? AUTH_CAPTCHA_EXPIRE_SECONDS : 300)));
        $this->refreshCooldownSeconds = max(1, (int)($config['refresh_cooldown_seconds'] ?? (defined('AUTH_CAPTCHA_REFRESH_COOLDOWN_SECONDS') ? AUTH_CAPTCHA_REFRESH_COOLDOWN_SECONDS : 3)));
        $this->refreshWindowSeconds = max(60, (int)($config['refresh_window_seconds'] ?? (defined('AUTH_CAPTCHA_REFRESH_LIMIT_WINDOW_SECONDS') ? AUTH_CAPTCHA_REFRESH_LIMIT_WINDOW_SECONDS : 300)));
        $this->maxRefreshesPerWindow = max(1, (int)($config['max_refreshes_per_window'] ?? (defined('AUTH_CAPTCHA_REFRESH_LIMIT_COUNT') ? AUTH_CAPTCHA_REFRESH_LIMIT_COUNT : 20)));
    }

    /**
     * @return array{svg:string,expires_at:int,ttl_seconds:int}
     */
    public function issue(string $purpose, string $ipAddress = ''): array
    {
        $this->ensureSessionIsActive();

        $purpose = $this->normalizePurpose($purpose);
        $now = time();
        $this->enforceRefreshRateLimit($purpose, $ipAddress, $now);

        [$expression, $answer] = $this->generateExpression();
        $salt = bin2hex(random_bytes(16));

        $_SESSION[self::SESSION_CHALLENGES_KEY][$purpose] = [
            'purpose' => $purpose,
            'salt' => $salt,
            'answer_hash' => $this->hashAnswer((string)$answer, $purpose, $salt),
            'expires_at' => $now + $this->ttlSeconds,
            'issued_at' => $now,
        ];

        return [
            'svg' => $this->renderSvg($expression),
            'expires_at' => $now + $this->ttlSeconds,
            'ttl_seconds' => $this->ttlSeconds,
        ];
    }

    public function verify(string $answer, string $purpose): bool
    {
        $this->ensureSessionIsActive();

        $purpose = $this->normalizePurpose($purpose);
        $challenge = $_SESSION[self::SESSION_CHALLENGES_KEY][$purpose] ?? null;
        if (!is_array($challenge)) {
            return false;
        }

        $expiresAt = (int)($challenge['expires_at'] ?? 0);
        $salt = (string)($challenge['salt'] ?? '');
        $expectedHash = (string)($challenge['answer_hash'] ?? '');
        if ($expiresAt <= time() || $salt === '' || $expectedHash === '') {
            unset($_SESSION[self::SESSION_CHALLENGES_KEY][$purpose]);
            return false;
        }

        $normalizedAnswer = trim($answer);
        if ($normalizedAnswer === '') {
            return false;
        }

        $actualHash = $this->hashAnswer($normalizedAnswer, $purpose, $salt);
        if (!hash_equals($expectedHash, $actualHash)) {
            return false;
        }

        unset($_SESSION[self::SESSION_CHALLENGES_KEY][$purpose]);
        return true;
    }

    private function ensureSessionIsActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session must be active before using CaptchaService.');
        }
    }

    /**
     * @return array{0:string,1:int}
     */
    private function generateExpression(): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $operator = random_int(0, 1) === 0 ? '+' : '-';

        if ($operator === '-' && $right > $left) {
            [$left, $right] = [$right, $left];
        }

        $answer = $operator === '-' ? ($left - $right) : ($left + $right);
        return [sprintf('%d %s %d = ?', $left, $operator, $right), $answer];
    }

    private function hashAnswer(string $answer, string $purpose, string $salt): string
    {
        return hash('sha256', $purpose . '|' . trim($answer) . '|' . $salt);
    }

    private function renderSvg(string $expression): string
    {
        $escapedExpression = htmlspecialchars($expression, ENT_QUOTES, 'UTF-8');
        $lineOne = random_int(6, 24) . ',' . random_int(8, 24) . ' ' . random_int(160, 220) . ',' . random_int(26, 44);
        $lineTwo = random_int(10, 36) . ',' . random_int(40, 52) . ' ' . random_int(180, 214) . ',' . random_int(6, 18);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="64" viewBox="0 0 240 64" role="img" aria-label="captcha">
  <rect width="240" height="64" rx="12" fill="#0f172a"/>
  <rect x="1" y="1" width="238" height="62" rx="11" fill="none" stroke="#67e8f9" stroke-opacity="0.35"/>
  <path d="M{$lineOne}" stroke="#38bdf8" stroke-opacity="0.25" stroke-width="2" />
  <path d="M{$lineTwo}" stroke="#22d3ee" stroke-opacity="0.18" stroke-width="2" />
  <text x="120" y="40" text-anchor="middle" fill="#e2e8f0" font-size="26" font-family="Arial, Helvetica, sans-serif">{$escapedExpression}</text>
</svg>
SVG;
    }

    private function normalizePurpose(string $purpose): string
    {
        $normalized = strtolower(trim($purpose));
        $allowed = ['login', 'register', 'forgot_password', 'email_code'];
        if (!in_array($normalized, $allowed, true)) {
            throw new RuntimeException('Unsupported captcha purpose.');
        }

        return $normalized;
    }

    private function enforceRefreshRateLimit(string $purpose, string $ipAddress, int $now): void
    {
        $sessionBucket = $_SESSION[self::SESSION_REFRESH_KEY][$purpose] ?? [];
        if (!is_array($sessionBucket)) {
            $sessionBucket = [];
        }

        $sessionBucket = $this->pruneTimestamps($sessionBucket, $now);
        $lastIssuedAt = $sessionBucket === [] ? 0 : (int)end($sessionBucket);
        if ($lastIssuedAt > 0 && ($now - $lastIssuedAt) < $this->refreshCooldownSeconds) {
            throw new RuntimeException('Captcha refreshed too quickly.');
        }
        if (count($sessionBucket) >= $this->maxRefreshesPerWindow) {
            throw new RuntimeException('Captcha refresh limit exceeded for session.');
        }
        $sessionBucket[] = $now;
        $_SESSION[self::SESSION_REFRESH_KEY][$purpose] = $sessionBucket;

        $normalizedIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        if ($normalizedIp === false) {
            return;
        }

        $cacheDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $filePath = $cacheDir . DIRECTORY_SEPARATOR . 'captcha_refresh_' . md5($purpose . '|' . $normalizedIp) . '.json';
        $fh = @fopen($filePath, 'c+');
        if ($fh === false) {
            return;
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($fh);
            $timestamps = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['timestamps']) && is_array($decoded['timestamps'])) {
                    $timestamps = $decoded['timestamps'];
                }
            }

            $timestamps = $this->pruneTimestamps($timestamps, $now);
            $lastIpIssuedAt = $timestamps === [] ? 0 : (int)end($timestamps);
            if ($lastIpIssuedAt > 0 && ($now - $lastIpIssuedAt) < $this->refreshCooldownSeconds) {
                throw new RuntimeException('Captcha refreshed too quickly.');
            }
            if (count($timestamps) >= $this->maxRefreshesPerWindow) {
                throw new RuntimeException('Captcha refresh limit exceeded for IP.');
            }

            $timestamps[] = $now;
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode(['timestamps' => $timestamps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fh);
        } finally {
            @flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * @param array<int,mixed> $timestamps
     * @return array<int,int>
     */
    private function pruneTimestamps(array $timestamps, int $now): array
    {
        $cutoff = $now - $this->refreshWindowSeconds;
        $normalized = array_map(static fn($value): int => (int)$value, $timestamps);

        return array_values(array_filter($normalized, static function (int $timestamp) use ($cutoff): bool {
            return $timestamp >= $cutoff;
        }));
    }
}
