<?php
declare(strict_types=1);

namespace Core;

final class ApiResponse
{
    private static ?string $requestId = null;

    public static function success(array $data = [], string $message = 'ok'): string
    {
        return self::build(true, ApiCode::SUCCESS, $message, $data);
    }

    public static function error(int $code, string $message = 'error', array $data = []): string
    {
        return self::build(false, $code, $message, $data);
    }

    public static function ensureRequestIdHeader(): string
    {
        return self::requestId();
    }

    private static function build(bool $success, int $code, string $message, array $data): string
    {
        $requestId = self::requestId();
        $payload = [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'requestId' => $requestId,
            'request_id' => $requestId,
            'timestamp' => time(),
            'data' => $data,
        ];

        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (array_key_exists($key, $payload)) {
                continue;
            }
            $payload[$key] = $value;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            return $json;
        }

        return '{"success":false,"code":5000,"message":"json encode failed","requestId":"","timestamp":0,"data":{}}';
    }

    private static function requestId(): string
    {
        if (self::$requestId === null) {
            try {
                self::$requestId = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                self::$requestId = substr(hash('sha256', uniqid('', true)), 0, 16);
            }
        }

        if (!headers_sent()) {
            header('X-Request-Id: ' . self::$requestId);
        }

        return self::$requestId;
    }
}

