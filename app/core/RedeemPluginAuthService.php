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
        $signature = trim((string)($_SERVER['HTTP_X_STELLAR_SIGNATURE'] ?? ''));

        if ($headerServerId === '' || $timestampRaw === '' || $signature === '') {
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

        $canonical = $timestampRaw . '.' . $rawBody;
        $expected = hash_hmac('sha256', $canonical, $configuredSecret);
        if (!hash_equals(strtolower($expected), strtolower($signature))) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid signature',
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
}
