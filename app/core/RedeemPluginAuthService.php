<?php
declare(strict_types=1);

namespace Core;

final class RedeemPluginAuthService
{
    /**
     * @return array{ok:bool,status:int,message:string,server_id:string}
     */
    public function verify(string $rawBody, ?string $bodyServerId = null): array
    {
        $configuredServerId = defined('REDEEM_PLUGIN_SERVER_ID') ? trim((string)REDEEM_PLUGIN_SERVER_ID) : '';
        $configuredSecret = defined('REDEEM_PLUGIN_SERVER_SECRET') ? trim((string)REDEEM_PLUGIN_SERVER_SECRET) : '';
        $timeWindow = defined('REDEEM_PLUGIN_TIME_WINDOW_SECONDS')
            ? max(60, (int)REDEEM_PLUGIN_TIME_WINDOW_SECONDS)
            : 300;

        if ($configuredServerId === '' || $configuredSecret === '') {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Redeem plugin auth is not configured',
                'server_id' => '',
            ];
        }

        $headerServerId = trim((string)($_SERVER['HTTP_X_STELLAR_SERVER_ID'] ?? ''));
        $timestampRaw = trim((string)($_SERVER['HTTP_X_STELLAR_TIMESTAMP'] ?? ''));
        $nonce = trim((string)($_SERVER['HTTP_X_STELLAR_NONCE'] ?? ''));
        $signature = trim((string)($_SERVER['HTTP_X_STELLAR_SIGNATURE'] ?? ''));

        if ($headerServerId === '' || $timestampRaw === '' || $nonce === '' || $signature === '') {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Missing plugin auth headers',
                'server_id' => $headerServerId,
            ];
        }

        if (!hash_equals($configuredServerId, $headerServerId)) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Server id mismatch',
                'server_id' => $headerServerId,
            ];
        }

        if ($bodyServerId !== null) {
            $bodyServerId = trim($bodyServerId);
            if ($bodyServerId !== '' && !hash_equals($headerServerId, $bodyServerId)) {
                return [
                    'ok' => false,
                    'status' => 401,
                    'message' => 'Body serverId mismatch',
                    'server_id' => $headerServerId,
                ];
            }
        }

        if (preg_match('/^\d{10,13}$/', $timestampRaw) !== 1) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid timestamp format',
                'server_id' => $headerServerId,
            ];
        }

        $timestamp = (int)$timestampRaw;
        if (strlen($timestampRaw) > 10) {
            $timestamp = (int)floor($timestamp / 1000);
        }

        if (abs(time() - $timestamp) > $timeWindow) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Timestamp outside allowed window',
                'server_id' => $headerServerId,
            ];
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $nonce) !== 1) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid nonce format',
                'server_id' => $headerServerId,
            ];
        }

        $canonical = $timestampRaw . '.' . $nonce . '.' . $rawBody;
        $expected = hash_hmac('sha256', $canonical, $configuredSecret);
        if (!hash_equals(strtolower($expected), strtolower($signature))) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid signature',
                'server_id' => $headerServerId,
            ];
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        if (!$this->consumeNonceOnce($method, $path, $headerServerId, $nonce, $timeWindow + 30)) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Nonce already used',
                'server_id' => $headerServerId,
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'ok',
            'server_id' => $headerServerId,
        ];
    }

    private function consumeNonceOnce(string $method, string $path, string $actor, string $nonce, int $ttl): bool
    {
        $safeTtl = max(60, $ttl);
        $cacheKey = 'stellar:redeem_nonce:' . hash('sha256', $method . "\n" . $path . "\n" . $actor . "\n" . $nonce);

        $redis = Database::redis();
        if ($redis !== null) {
            try {
                $created = $redis->setnx($cacheKey, (string)time());
                if ($created === true || $created === 1) {
                    $redis->expire($cacheKey, $safeTtl);
                    return true;
                }
                if ($created === false || $created === 0) {
                    return false;
                }
            } catch (\Throwable $e) {
            }
        }

        $cacheDir = rtrim((string)CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'nonce';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (!is_dir($cacheDir)) {
            return false;
        }

        $file = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', $cacheKey) . '.lock';
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                return false;
            }

            $now = time();
            $raw = stream_get_contents($handle);
            $expiresAt = is_string($raw) ? (int)trim($raw) : 0;
            if ($expiresAt > $now) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }

            $newExpiresAt = $now + $safeTtl;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)$newExpiresAt);
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
            return true;
        } catch (\Throwable $e) {
            @fclose($handle);
            return false;
        }
    }
}
