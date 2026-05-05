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
                'message' => '卡密分类读取失败，请先执行 redeem_v1.sql',
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
            $result = $this->redeemService->createCategory($input);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'data' => $result['data'] ?? [],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => '创建分类失败',
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
            $result = $this->redeemService->updateCategory((int)$id, $input);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => '更新分类失败',
            ], 500);
        }
    }

    public function deleteCategory(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        try {
            $result = $this->redeemService->deleteCategory((int)$id);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'code' => ApiCode::SERVER_ERROR,
                'message' => '删除分类失败',
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
            'q' => (string)($_GET['q'] ?? ''),
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
                'message' => '卡密列表读取失败',
            ], 500);
        }
    }

    public function batchGenerateKeys(): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        $input = $this->readInput();
        $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        try {
            $result = $this->redeemService->batchGenerateKeys($input, $adminId);
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
            $this->json($payload, (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => '批量生成失败',
            ], 500);
        }
    }

    public function revokeKey(string $id): void
    {
        if (!$this->validateApiCsrf()) {
            return;
        }

        try {
            $result = $this->redeemService->revokeKey((int)$id);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => '吊销失败',
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

        try {
            $result = $this->redeemService->revokeKeys($ids);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'affected' => (int)($result['affected'] ?? 0),
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => '批量吊销失败',
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

        try {
            $result = $this->redeemService->deleteKeys($ids);
            $this->json([
                'success' => (bool)$result['ok'],
                'message' => (string)$result['message'],
                'affected' => (int)($result['affected'] ?? 0),
            ], (int)$result['status']);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => '批量删除失败',
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
            'q' => (string)($_GET['q'] ?? ''),
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
                'message' => '日志读取失败',
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
                'message' => '统计读取失败',
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
}
