<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\RedeemPluginAuthService;
use Core\RedeemService;

class MinecraftRedeemApiController extends Controller
{
    private RedeemService $redeemService;
    private RedeemPluginAuthService $pluginAuth;
    private ?string $rawBody = null;
    /** @var array<string,mixed>|null */
    private ?array $jsonBody = null;

    public function __construct()
    {
        parent::__construct();
        $this->redeemService = new RedeemService();
        $this->pluginAuth = new RedeemPluginAuthService();
    }

    public function claim(): void
    {
        if (!$this->ensurePostMethod()) {
            return;
        }

        $body = $this->readJsonBody();
        if (!$this->ensurePluginAuthorized(isset($body['serverId']) ? (string)$body['serverId'] : null)) {
            return;
        }
        if (!isset($body['serverId']) || trim((string)$body['serverId']) === '') {
            $body['serverId'] = trim((string)($_SERVER['HTTP_X_STELLAR_SERVER_ID'] ?? ''));
        }

        $result = $this->redeemService->claim($body);
        if (!empty($result['success'])) {
            $this->respondPluginJson($result, 200);
            return;
        }

        $reason = (string)($result['reason'] ?? 'internal_error');
        $status = $reason === 'internal_error' ? 500 : 200;
        $this->respondPluginJson($result, $status);
    }

    public function complete(string $redeemId): void
    {
        if (!$this->ensurePostMethod()) {
            return;
        }

        if (!$this->ensurePluginAuthorized(null)) {
            return;
        }

        $body = $this->readJsonBody();
        $result = $this->redeemService->complete((int)$redeemId, $body);

        $status = 200;
        if (empty($result['success'])) {
            $message = (string)($result['message'] ?? '');
            if (str_contains($message, '不存在')) {
                $status = 404;
            } elseif (str_contains($message, '内部错误')) {
                $status = 500;
            } else {
                $status = 409;
            }
        }

        $this->respondPluginJson($result, $status);
    }

    public function fail(string $redeemId): void
    {
        if (!$this->ensurePostMethod()) {
            return;
        }

        if (!$this->ensurePluginAuthorized(null)) {
            return;
        }

        $body = $this->readJsonBody();
        $result = $this->redeemService->fail((int)$redeemId, $body);

        $status = 200;
        if (empty($result['success'])) {
            $message = (string)($result['message'] ?? '');
            if (str_contains($message, '不存在')) {
                $status = 404;
            } elseif (str_contains($message, '内部错误')) {
                $status = 500;
            } else {
                $status = 409;
            }
        }

        $this->respondPluginJson($result, $status);
    }

    public function heartbeat(): void
    {
        if (!$this->ensurePostMethod()) {
            return;
        }

        if (!$this->ensurePluginAuthorized(null)) {
            return;
        }

        $this->respondPluginJson([
            'success' => true,
            'message' => 'ok',
            'serverTime' => time(),
        ]);
    }

    private function ensurePostMethod(): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            $this->respondPluginJson([
                'success' => false,
                'reason' => 'internal_error',
                'message' => 'Method Not Allowed',
            ], 405);
            return false;
        }

        return true;
    }

    private function ensurePluginAuthorized(?string $bodyServerId): bool
    {
        $auth = $this->pluginAuth->verify($this->getRawBody(), $bodyServerId);
        if (!($auth['ok'] ?? false)) {
            $this->respondPluginJson([
                'success' => false,
                'reason' => 'server_auth_failed',
                'message' => (string)($auth['message'] ?? 'Unauthorized'),
            ], (int)($auth['status'] ?? 401));
            return false;
        }

        return true;
    }

    private function getRawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        $raw = file_get_contents('php://input');
        $this->rawBody = is_string($raw) ? $raw : '';
        return $this->rawBody;
    }

    /**
     * @return array<string,mixed>
     */
    private function readJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $raw = $this->getRawBody();
        if (trim($raw) === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($raw, true);
        $this->jsonBody = is_array($decoded) ? $decoded : [];
        return $this->jsonBody;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function respondPluginJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            echo $json;
            return;
        }

        echo '{"success":false,"reason":"internal_error","message":"json encode failed"}';
    }
}
