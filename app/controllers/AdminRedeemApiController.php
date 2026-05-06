<?php
declare(strict_types=1);

namespace Controller;

use Core\ApiCode;
use Core\ApiResponse;
use Core\Controller;
use Core\RedeemService;

class AdminRedeemApiController extends Controller
{
    private RedeemService $redeemService;
    /** @var array<string,mixed>|null */
    private ?array $inputCache = null;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->redeemService = new RedeemService();
    }

    public function categories(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => [
                    'items' => $this->redeemService->listCategories(),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => 'Failed to load categories',
            ], 500);
        }
    }

    public function createCategory(): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        try {
            $result = $this->redeemService->createCategory($input, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'data' => $result['data'] ?? [],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => 'Failed to create category',
            ], 500);
        }
    }

    public function updateCategory(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        try {
            $result = $this->redeemService->updateCategory((int)$id, $input, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => 'Failed to update category',
            ], 500);
        }
    }

    public function deleteCategory(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        try {
            $result = $this->redeemService->deleteCategory((int)$id, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => 'Failed to delete category',
            ], 500);
        }
    }

    public function keys(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));

        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'batch_id' => (int)($_GET['batch_id'] ?? 0),
            'channel' => (string)($_GET['channel'] ?? ''),
            'bound_player_uuid' => (string)($_GET['bound_player_uuid'] ?? ''),
            'allowed_server_id' => (string)($_GET['allowed_server_id'] ?? ''),
            'require_bound_account' => (string)($_GET['require_bound_account'] ?? ''),
            'require_email_verified' => (string)($_GET['require_email_verified'] ?? ''),
            'require_account_active' => (string)($_GET['require_account_active'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'created_from' => (string)($_GET['created_from'] ?? ''),
            'created_to' => (string)($_GET['created_to'] ?? ''),
            'expires_from' => (string)($_GET['expires_from'] ?? ''),
            'expires_to' => (string)($_GET['expires_to'] ?? ''),
        ];

        try {
            $result = $this->redeemService->listKeys($filters, $page, $perPage);
            $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load keys',
            ], 500);
        }
    }

    public function exportKeys(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'batch_id' => (int)($_GET['batch_id'] ?? 0),
            'channel' => (string)($_GET['channel'] ?? ''),
            'bound_player_uuid' => (string)($_GET['bound_player_uuid'] ?? ''),
            'allowed_server_id' => (string)($_GET['allowed_server_id'] ?? ''),
            'require_bound_account' => (string)($_GET['require_bound_account'] ?? ''),
            'require_email_verified' => (string)($_GET['require_email_verified'] ?? ''),
            'require_account_active' => (string)($_GET['require_account_active'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'created_from' => (string)($_GET['created_from'] ?? ''),
            'created_to' => (string)($_GET['created_to'] ?? ''),
            'expires_from' => (string)($_GET['expires_from'] ?? ''),
            'expires_to' => (string)($_GET['expires_to'] ?? ''),
        ];

        try {
            $result = $this->redeemService->exportKeysCsv($filters, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'csv' => (string)($result['csv'] ?? ''),
                'filename' => (string)($result['filename'] ?? ('redeem_keys_export_' . date('Ymd_His') . '.csv')),
                'count' => (int)($result['count'] ?? 0),
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to export keys',
            ], 500);
        }
    }

    public function batchGenerateKeys(): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        try {
            $result = $this->redeemService->batchGenerateKeys($input, $this->currentAdminId(), $this->getClientIp());
            $payload = [
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ];
            if (isset($result['items'])) {
                $payload['items'] = $result['items'];
            }
            if (isset($result['csv'])) {
                $payload['csv'] = (string)$result['csv'];
                $payload['filename'] = 'redeem_keys_' . date('Ymd_His') . '.csv';
            }
            if (isset($result['batch']) && is_array($result['batch'])) {
                $payload['batch'] = $result['batch'];
            }
            $this->json($payload, (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to batch generate keys',
            ], 500);
        }
    }

    public function revokeKey(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        try {
            $result = $this->redeemService->revokeKey((int)$id, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to revoke key',
            ], 500);
        }
    }

    public function revokeBatchKeys(): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        $ids = $this->parseIds($input['ids'] ?? []);
        $validationError = $this->validateBatchIds($ids);
        if ($validationError !== null) {
            $this->json($validationError, 400);
            return;
        }

        try {
            $result = $this->redeemService->revokeKeys($ids, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'affected' => (int)($result['affected'] ?? 0),
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to batch revoke keys',
            ], 500);
        }
    }

    public function deleteBatchKeys(): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        $ids = $this->parseIds($input['ids'] ?? []);
        $validationError = $this->validateBatchIds($ids);
        if ($validationError !== null) {
            $this->json($validationError, 400);
            return;
        }

        try {
            $result = $this->redeemService->deleteKeys($ids, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'affected' => (int)($result['affected'] ?? 0),
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to batch soft-delete keys',
            ], 500);
        }
    }

    public function batches(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
        $filters = [
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'channel' => (string)($_GET['channel'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'created_from' => (string)($_GET['created_from'] ?? ''),
            'created_to' => (string)($_GET['created_to'] ?? ''),
        ];

        try {
            $result = $this->redeemService->listBatches($filters, $page, $perPage);
            $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load batches',
            ], 500);
        }
    }

    public function batchDetail(string $id): void
    {
        try {
            $batch = $this->redeemService->findBatchById((int)$id);
            if ($batch === null) {
                $this->json([
                    'success' => false,
                    'message' => 'Batch not found',
                ], 404);
                return;
            }

            $this->json([
                'success' => true,
                'data' => $batch,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load batch detail',
            ], 500);
        }
    }

    public function batchStats(string $id): void
    {
        try {
            $stats = $this->redeemService->statsByBatch((int)$id);
            if ($stats === null) {
                $this->json([
                    'success' => false,
                    'message' => 'Batch not found',
                ], 404);
                return;
            }
            $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load batch stats',
            ], 500);
        }
    }

    public function logs(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));

        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'admin_status' => (string)($_GET['admin_status'] ?? ''),
            'rule_result' => (string)($_GET['rule_result'] ?? ''),
            'rule_reason' => (string)($_GET['rule_reason'] ?? ''),
            'website_user_id' => (int)($_GET['website_user_id'] ?? 0),
            'server_id' => (string)($_GET['server_id'] ?? ''),
            'player_uuid' => (string)($_GET['player_uuid'] ?? ''),
            'player_name' => (string)($_GET['player_name'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'created_from' => (string)($_GET['created_from'] ?? ''),
            'created_to' => (string)($_GET['created_to'] ?? ''),
        ];

        try {
            $result = $this->redeemService->listLogs($filters, $page, $perPage);
            $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load logs',
            ], 500);
        }
    }

    public function updateLogAdminStatus(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        try {
            $result = $this->redeemService->updateLogAdminStatus((int)$id, $input, $this->currentAdminId(), $this->getClientIp());
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'data' => $result['data'] ?? [],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to update admin status',
            ], 500);
        }
    }

    public function adminLogs(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));

        $filters = [
            'action' => (string)($_GET['action'] ?? ''),
            'target_type' => (string)($_GET['target_type'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'created_from' => (string)($_GET['created_from'] ?? ''),
            'created_to' => (string)($_GET['created_to'] ?? ''),
        ];

        try {
            $result = $this->redeemService->listAdminLogs($filters, $page, $perPage);
            $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load admin logs',
            ], 500);
        }
    }

    public function statsPublish(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->redeemService->getPublishStats(),
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to load publish stats',
            ], 500);
        }
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo ApiResponse::error(ApiCode::AUTH_INVALID, 'Unauthorized');
            exit;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readInput(): array
    {
        if ($this->inputCache !== null) {
            return $this->inputCache;
        }

        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->inputCache = $decoded;
                return $this->inputCache;
            }
        }

        $this->inputCache = is_array($_POST) ? $_POST : [];
        return $this->inputCache;
    }

    private function validateApiCsrf(): bool
    {
        $this->generateCsrfToken();
        $input = $this->readInput();

        $token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($token === '') {
            $token = trim((string)($input['csrf_token'] ?? ''));
        }

        $sessionToken = trim((string)($_SESSION['csrf_token'] ?? ''));
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $this->json(['success' => false, 'message' => 'CSRF token mismatch'], 403);
            return false;
        }

        return true;
    }

    private function currentAdminId(): ?int
    {
        $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        return $id > 0 ? $id : null;
    }

    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    private function parseIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int,int> $ids
     * @return array<string,mixed>|null
     */
    private function validateBatchIds(array $ids): ?array
    {
        $count = count($ids);
        if ($count === 0) {
            return [
                'success' => false,
                'message' => 'Please select keys to operate',
            ];
        }

        if ($count > 500) {
            return [
                'success' => false,
                'message' => 'A maximum of 500 keys can be processed per request',
            ];
        }

        return null;
    }
}
