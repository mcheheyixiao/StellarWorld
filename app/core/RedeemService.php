<?php
declare(strict_types=1);

namespace Core;

use DateTimeImmutable;
use DateTimeInterface;
use Model\RedeemCategory;
use Model\RedeemKey;
use Model\RedeemLog;
use PDO;
use PDOException;

class RedeemService
{
    private RedeemCategory $categoryModel;
    private RedeemKey $keyModel;
    private RedeemLog $logModel;
    private RedeemCodeGenerator $codeGenerator;
    private PDO $db;

    public function __construct()
    {
        $this->categoryModel = new RedeemCategory();
        $this->keyModel = new RedeemKey();
        $this->logModel = new RedeemLog();
        $this->codeGenerator = new RedeemCodeGenerator();
        $this->db = Database::connection();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listCategories(): array
    {
        return $this->categoryModel->all();
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string,data?:array<string,mixed>}
     */
    public function createCategory(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $template = trim((string)($input['defaultCommandTemplate'] ?? ($input['default_command_template'] ?? '')));
        $status = strtolower(trim((string)($input['status'] ?? 'enabled')));

        if ($name === '' || mb_strlen($name) > 128) {
            return ['ok' => false, 'status' => 400, 'message' => '分类名称不能为空且不超过128字符'];
        }
        if ($template === '') {
            return ['ok' => false, 'status' => 400, 'message' => '默认命令模板不能为空'];
        }
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $status = 'enabled';
        }

        $id = $this->categoryModel->create($name, $description, $template, $status);
        return [
            'ok' => true,
            'status' => 200,
            'message' => '分类创建成功',
            'data' => ['id' => $id],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string}
     */
    public function updateCategory(int $id, array $input): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => '分类ID无效'];
        }

        $existing = $this->categoryModel->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'status' => 404, 'message' => '分类不存在'];
        }

        $name = trim((string)($input['name'] ?? $existing['name'] ?? ''));
        $description = trim((string)($input['description'] ?? $existing['description'] ?? ''));
        $template = trim((string)($input['defaultCommandTemplate'] ?? ($input['default_command_template'] ?? ($existing['default_command_template'] ?? ''))));
        $status = strtolower(trim((string)($input['status'] ?? ($existing['status'] ?? 'enabled'))));

        if ($name === '' || mb_strlen($name) > 128) {
            return ['ok' => false, 'status' => 400, 'message' => '分类名称不能为空且不超过128字符'];
        }
        if ($template === '') {
            return ['ok' => false, 'status' => 400, 'message' => '默认命令模板不能为空'];
        }
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $status = 'enabled';
        }

        $this->categoryModel->update($id, $name, $description, $template, $status);
        return ['ok' => true, 'status' => 200, 'message' => '分类更新成功'];
    }

    /**
     * @return array{ok:bool,status:int,message:string}
     */
    public function deleteCategory(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => '分类ID无效'];
        }

        $existing = $this->categoryModel->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'status' => 404, 'message' => '分类不存在'];
        }

        $keyCount = $this->categoryModel->countKeysByCategory($id);
        if ($keyCount > 0) {
            return ['ok' => false, 'status' => 409, 'message' => '分类下仍有卡密，不能删除'];
        }

        $this->categoryModel->delete($id);
        return ['ok' => true, 'status' => 200, 'message' => '分类删除成功'];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listKeys(array $filters, int $page, int $perPage): array
    {
        return $this->keyModel->listPaginated($filters, $page, $perPage);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string,items?:array<int,array<string,mixed>>,csv?:string}
     */
    public function batchGenerateKeys(array $input, ?int $createdBy): array
    {
        $pepper = $this->readCodePepper();
        if ($pepper === '') {
            return ['ok' => false, 'status' => 500, 'message' => '缺少 REDEEM_CODE_PEPPER 配置'];
        }

        $categoryId = (int)($input['categoryId'] ?? ($input['category_id'] ?? 0));
        $count = (int)($input['count'] ?? 0);
        $length = (int)($input['length'] ?? 16);
        $maxUses = (int)($input['maxUses'] ?? ($input['max_uses'] ?? 1));
        $remark = trim((string)($input['remark'] ?? ''));
        $expiresAtRaw = trim((string)($input['expiresAt'] ?? ($input['expires_at'] ?? '')));
        $commandTemplateInput = trim((string)($input['commandTemplate'] ?? ($input['command_template'] ?? '')));

        if ($count < 1 || $count > 500) {
            return ['ok' => false, 'status' => 400, 'message' => '生成数量必须在1到500之间'];
        }

        $length = max(8, min(64, $length));
        $maxUses = max(1, min(100000, $maxUses));
        $remark = mb_substr($remark, 0, 255);

        $category = null;
        if ($categoryId > 0) {
            $category = $this->categoryModel->findById($categoryId);
            if ($category === null) {
                return ['ok' => false, 'status' => 404, 'message' => '分类不存在'];
            }
        }

        $resolvedTemplate = $commandTemplateInput;
        if ($resolvedTemplate === '' && is_array($category)) {
            $resolvedTemplate = trim((string)($category['default_command_template'] ?? ''));
        }
        if ($resolvedTemplate === '') {
            return ['ok' => false, 'status' => 400, 'message' => '未选择分类时必须填写命令模板'];
        }

        $expiresAt = null;
        if ($expiresAtRaw !== '') {
            $parsed = $this->parseDateTime($expiresAtRaw);
            if ($parsed === null) {
                return ['ok' => false, 'status' => 400, 'message' => '过期时间格式无效'];
            }
            $expiresAt = $parsed;
        }

        $groupSize = $length >= 20 ? 5 : 4;
        $createdByValue = $createdBy !== null && $createdBy > 0 ? $createdBy : null;

        $insert = $this->db->prepare(
            'INSERT INTO redeem_keys (category_id, code_hash, plain_code, command_template, max_uses, used_count, status, expires_at, remark, created_by, created_at, updated_at) '
            . 'VALUES (:category_id, :code_hash, :plain_code, :command_template, :max_uses, 0, :status, :expires_at, :remark, :created_by, NOW(), NOW())'
        );

        $items = [];
        $attempts = 0;
        $maxAttempts = max($count * 30, 200);

        $this->db->beginTransaction();
        try {
            while (count($items) < $count && $attempts < $maxAttempts) {
                $attempts++;
                $plainCode = $this->codeGenerator->generate($length, $groupSize);
                $normalized = $this->normalizeCode($plainCode);
                $codeHash = $this->hashNormalizedCode($normalized, $pepper);

                try {
                    $insert->bindValue(':category_id', $categoryId > 0 ? $categoryId : null, $categoryId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $insert->bindValue(':code_hash', $codeHash, PDO::PARAM_STR);
                    $insert->bindValue(':plain_code', $plainCode, PDO::PARAM_STR);
                    $insert->bindValue(':command_template', $resolvedTemplate, PDO::PARAM_STR);
                    $insert->bindValue(':max_uses', $maxUses, PDO::PARAM_INT);
                    $insert->bindValue(':status', 'available', PDO::PARAM_STR);
                    $insert->bindValue(':expires_at', $expiresAt, $expiresAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $insert->bindValue(':remark', $remark !== '' ? $remark : null, $remark !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    $insert->bindValue(':created_by', $createdByValue, $createdByValue === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insert->execute();
                } catch (PDOException $e) {
                    if ($this->isDuplicateEntry($e)) {
                        continue;
                    }
                    throw $e;
                }

                $items[] = [
                    'id' => (int)$this->db->lastInsertId(),
                    'code' => $plainCode,
                    'category' => is_array($category) ? (string)($category['name'] ?? '') : '',
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                    'remark' => $remark,
                ];
            }

            if (count($items) < $count) {
                $this->db->rollBack();
                return ['ok' => false, 'status' => 500, 'message' => '卡密生成失败，请重试'];
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        RealtimeNotifier::emit('redeem.key.generated', [
            'count' => count($items),
            'category_id' => $categoryId > 0 ? $categoryId : null,
        ]);
        RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

        return [
            'ok' => true,
            'status' => 200,
            'message' => '卡密生成成功',
            'items' => $items,
            'csv' => $this->buildCsv($items),
        ];
    }

    /**
     * @return array{ok:bool,status:int,message:string}
     */
    public function revokeKey(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => '卡密ID无效'];
        }

        $ok = $this->keyModel->revokeById($id);
        if (!$ok) {
            return ['ok' => false, 'status' => 404, 'message' => '卡密不存在或不可吊销'];
        }

        RealtimeNotifier::emit('redeem.key.revoked', ['id' => $id]);
        RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        return ['ok' => true, 'status' => 200, 'message' => '吊销成功'];
    }

    /**
     * @param array<int,int> $ids
     * @return array{ok:bool,status:int,message:string,affected:int}
     */
    public function revokeKeys(array $ids): array
    {
        $affected = $this->keyModel->revokeBatch($ids);
        if ($affected > 0) {
            RealtimeNotifier::emit('redeem.key.revoked', ['ids' => array_values($ids), 'count' => $affected]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '批量吊销完成',
            'affected' => $affected,
        ];
    }

    /**
     * @param array<int,int> $ids
     * @return array{ok:bool,status:int,message:string,affected:int}
     */
    public function deleteKeys(array $ids): array
    {
        $affected = $this->keyModel->deleteBatch($ids);
        if ($affected > 0) {
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '批量删除完成',
            'affected' => $affected,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listLogs(array $filters, int $page, int $perPage): array
    {
        return $this->logModel->listPaginated($filters, $page, $perPage);
    }

    /**
     * @return array<string,int>
     */
    public function getPublishStats(): array
    {
        return [
            'total_keys' => $this->keyModel->countTotal(),
            'publishable_keys' => $this->keyModel->countPublishable(),
            'used_count' => $this->keyModel->sumUsedCount(),
            'failed_claims' => $this->logModel->countFailed(),
            'revoked_keys' => $this->keyModel->countRevoked(),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function claim(array $payload): array
    {
        $pepper = $this->readCodePepper();
        if ($pepper === '') {
            return [
                'success' => false,
                'reason' => 'internal_error',
                'message' => '卡密服务配置错误：缺少 REDEEM_CODE_PEPPER',
            ];
        }

        $code = $this->normalizeCode((string)($payload['code'] ?? ''));
        if ($code === '') {
            return [
                'success' => false,
                'reason' => 'invalid_code',
                'message' => '卡密不存在或已失效',
            ];
        }

        $codeHash = $this->hashNormalizedCode($code, $pepper);
        $serverId = trim((string)($payload['serverId'] ?? ($payload['server_id'] ?? '')));
        $playerName = trim((string)($payload['playerName'] ?? ($payload['player_name'] ?? '')));
        $playerUuid = trim((string)($payload['playerUuid'] ?? ($payload['player_uuid'] ?? '')));
        $world = trim((string)($payload['world'] ?? ($payload['world_name'] ?? '')));

        try {
            $this->db->beginTransaction();

            $key = $this->keyModel->findByCodeHashForUpdate($codeHash);
            if ($key === null || (string)($key['status'] ?? '') === 'deleted') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'invalid_code',
                    'message' => '卡密不存在或已失效',
                ];
            }

            $keyStatus = (string)($key['status'] ?? '');
            if ($keyStatus === 'revoked') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'revoked',
                    'message' => '卡密已被吊销',
                ];
            }

            if ($keyStatus !== 'available') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'invalid_code',
                    'message' => '卡密不存在或已失效',
                ];
            }

            $expiresAt = trim((string)($key['expires_at'] ?? ''));
            if ($expiresAt !== '') {
                $expiresTs = strtotime($expiresAt);
                if ($expiresTs !== false && $expiresTs <= time()) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'reason' => 'expired',
                        'message' => '卡密已过期',
                    ];
                }
            }

            $usedCount = (int)($key['used_count'] ?? 0);
            $maxUses = (int)($key['max_uses'] ?? 1);
            if ($usedCount >= max(1, $maxUses)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'used_up',
                    'message' => '卡密使用次数已达上限',
                ];
            }

            $categoryId = (int)($key['category_id'] ?? 0);
            $categoryStatus = strtolower(trim((string)($key['category_status'] ?? 'enabled')));
            if ($categoryId > 0 && $categoryStatus !== 'enabled') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'category_disabled',
                    'message' => '卡密分类已禁用',
                ];
            }

            $commands = $this->parseCommands((string)($key['command_template'] ?? ''));
            if ($commands === []) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'internal_error',
                    'message' => '卡密命令模板无效',
                ];
            }

            $this->keyModel->incrementUsage((int)$key['id']);

            $commandSnapshot = $this->encodeJsonArray($commands);
            $logId = $this->logModel->createExecuting([
                'key_id' => (int)$key['id'],
                'redeem_code_hash' => $codeHash,
                'server_id' => $serverId,
                'player_uuid' => $playerUuid !== '' ? $playerUuid : null,
                'player_name' => $playerName !== '' ? $playerName : null,
                'world_name' => $world !== '' ? $world : null,
                'command_snapshot' => $commandSnapshot,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'redeemId' => $logId,
                'message' => '兑换处理中，请稍候',
                'commands' => $commands,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'reason' => 'internal_error',
                'message' => '服务器内部错误',
            ];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function complete(int $redeemId, array $payload): array
    {
        $executedCommands = $this->normalizeCommandList($payload['executedCommands'] ?? ($payload['executed_commands'] ?? []));
        $executedCommandsJson = $executedCommands === [] ? null : $this->encodeJsonArray($executedCommands);

        try {
            $this->db->beginTransaction();
            $log = $this->logModel->findByIdForUpdate($redeemId);
            if ($log === null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'redeemId 不存在'];
            }

            $status = (string)($log['status'] ?? '');
            if ($status === 'success') {
                $this->db->commit();
                return ['success' => true, 'message' => '已完成'];
            }
            if ($status === 'failed') {
                $this->db->commit();
                return ['success' => false, 'message' => '该兑换已标记失败'];
            }
            if ($status !== 'executing') {
                $this->db->commit();
                return ['success' => false, 'message' => '兑换状态不允许完成'];
            }

            $this->logModel->markSuccess($redeemId, $executedCommandsJson);
            $this->db->commit();

            RealtimeNotifier::emit('redeem.claim.success', ['redeem_id' => $redeemId]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

            return ['success' => true, 'message' => '兑换完成'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => '服务器内部错误'];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function fail(int $redeemId, array $payload): array
    {
        $executedCommands = $this->normalizeCommandList($payload['executedCommands'] ?? ($payload['executed_commands'] ?? []));
        $executedCommandsJson = $executedCommands === [] ? null : $this->encodeJsonArray($executedCommands);
        $error = trim((string)($payload['error'] ?? ''));
        $failedCommand = trim((string)($payload['failedCommand'] ?? ($payload['failed_command'] ?? '')));

        $failureReason = $error;
        if ($failureReason === '' && $failedCommand !== '') {
            $failureReason = 'Failed command: ' . $failedCommand;
        }
        if ($failureReason === '') {
            $failureReason = 'Plugin reported redeem failure';
        }
        $failureReason = mb_substr($failureReason, 0, 255);

        try {
            $this->db->beginTransaction();
            $log = $this->logModel->findByIdForUpdate($redeemId);
            if ($log === null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'redeemId 不存在'];
            }

            $status = (string)($log['status'] ?? '');
            if ($status === 'success') {
                $this->db->commit();
                return ['success' => false, 'message' => '该兑换已成功，不能标记失败'];
            }
            if ($status === 'failed') {
                $this->db->commit();
                return ['success' => true, 'message' => '已是失败状态'];
            }
            if ($status !== 'executing') {
                $this->db->commit();
                return ['success' => false, 'message' => '兑换状态不允许失败回传'];
            }

            $this->logModel->markFailed($redeemId, $failureReason, $executedCommandsJson);
            $this->db->commit();

            RealtimeNotifier::emit('redeem.claim.failed', ['redeem_id' => $redeemId, 'reason' => $failureReason]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

            return ['success' => true, 'message' => '已记录失败'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => '服务器内部错误'];
        }
    }

    public function normalizeCode(string $code): string
    {
        $normalized = trim($code);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
        if ($this->isCaseInsensitiveEnabled()) {
            $normalized = strtoupper($normalized);
        }
        return $normalized;
    }

    private function isCaseInsensitiveEnabled(): bool
    {
        if (!defined('REDEEM_CODE_CASE_INSENSITIVE')) {
            return true;
        }
        return (bool)REDEEM_CODE_CASE_INSENSITIVE;
    }

    private function readCodePepper(): string
    {
        return defined('REDEEM_CODE_PEPPER') ? trim((string)REDEEM_CODE_PEPPER) : '';
    }

    private function hashNormalizedCode(string $normalizedCode, string $pepper): string
    {
        return hash('sha256', $normalizedCode . $pepper);
    }

    /**
     * @return array<int,string>
     */
    private function parseCommands(string $template): array
    {
        $template = trim($template);
        if ($template === '') {
            return [];
        }

        if (str_starts_with($template, '[')) {
            $decoded = json_decode($template, true);
            if (is_array($decoded)) {
                return $this->normalizeCommandList($decoded);
            }
        }

        return $this->normalizeCommandList(preg_split('/\r\n|\n|\r/', $template) ?: []);
    }

    /**
     * @param mixed $raw
     * @return array<int,string>
     */
    private function normalizeCommandList($raw): array
    {
        $items = [];

        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        }

        if (!is_array($raw)) {
            return [];
        }

        foreach ($raw as $row) {
            if (!is_scalar($row)) {
                continue;
            }
            $line = trim((string)$row);
            if ($line === '') {
                continue;
            }
            $items[] = mb_substr($line, 0, 500);
        }

        return array_values($items);
    }

    private function parseDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function encodeJsonArray(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '[]';
        }
        return $json;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function buildCsv(array $items): string
    {
        $lines = [];
        $lines[] = 'code,category,max_uses,expires_at,remark';

        foreach ($items as $item) {
            $row = [
                (string)($item['code'] ?? ''),
                (string)($item['category'] ?? ''),
                (string)($item['max_uses'] ?? ''),
                (string)($item['expires_at'] ?? ''),
                (string)($item['remark'] ?? ''),
            ];

            $escaped = array_map(static function (string $cell): string {
                $cell = str_replace('"', '""', $cell);
                return '"' . $cell . '"';
            }, $row);
            $lines[] = implode(',', $escaped);
        }

        return implode("\r\n", $lines);
    }

    private function isDuplicateEntry(PDOException $e): bool
    {
        $sqlState = $e->getCode();
        if ($sqlState === '23000') {
            return true;
        }

        return str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
