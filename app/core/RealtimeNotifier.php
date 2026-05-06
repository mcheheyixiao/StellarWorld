<?php
declare(strict_types=1);

namespace Core;

final class RealtimeNotifier
{
    /**
     * Lightweight event hook placeholder.
     *
     * If your runtime defines a callable bridge (for example stellar_realtime_emit),
     * this method will forward events to it. Otherwise it is a no-op.
     */
    public static function emit(string $event, array $payload = []): void
    {
        $event = trim($event);
        if ($event === '') {
            return;
        }

        if (function_exists('stellar_realtime_emit')) {
            try {
                stellar_realtime_emit($event, $payload);
            } catch (\Throwable $e) {
                // Never block business flow for realtime side effects.
            }
            return;
        }

        $url = defined('REALTIME_INTERNAL_EVENT_URL') ? trim((string)REALTIME_INTERNAL_EVENT_URL) : '';
        $secret = defined('REALTIME_INTERNAL_SECRET') ? trim((string)REALTIME_INTERNAL_SECRET) : '';
        if ($url === '' || $secret === '') {
            return;
        }

        $timeoutMs = defined('REALTIME_INTERNAL_TIMEOUT_MS') ? max(100, (int)REALTIME_INTERNAL_TIMEOUT_MS) : 800;
        $body = [
            'type' => $event,
            'timestamp' => time(),
            'payload' => $payload,
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        if (function_exists('curl_init')) {
            self::postWithCurl($url, $secret, $json, $timeoutMs);
            return;
        }

        self::postWithStream($url, $secret, $json, $timeoutMs);
    }

    private static function postWithCurl(string $url, string $secret, string $json, int $timeoutMs): void
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        $safeSecret = str_replace(["\r", "\n"], '', $secret);
        $headers = [
            'Content-Type: application/json',
            'X-Stellar-Realtime-Secret: ' . $safeSecret,
        ];

        try {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeoutMs);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
            curl_exec($ch);
        } catch (\Throwable $e) {
            // Never block business flow for realtime side effects.
        } finally {
            curl_close($ch);
        }
    }

    private static function postWithStream(string $url, string $secret, string $json, int $timeoutMs): void
    {
        $safeSecret = str_replace(["\r", "\n"], '', $secret);
        $headers = "Content-Type: application/json\r\n"
            . 'X-Stellar-Realtime-Secret: ' . $safeSecret . "\r\n";

        $timeoutSeconds = max(0.1, $timeoutMs / 1000);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $json,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        try {
            @file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            // Never block business flow for realtime side effects.
        }
    }
}
