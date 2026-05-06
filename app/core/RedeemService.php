<?php
declare(strict_types=1);

namespace Core;

use DateTimeImmutable;
use DateTimeInterface;
use Model\RedeemAdminLog;
use Model\RedeemBatch;
use Model\RedeemCategory;
use Model\RedeemKey;
use Model\RedeemLog;
use PDO;
use PDOException;

class RedeemService
{
    private RedeemCategory $categoryModel;
    private RedeemBatch $batchModel;
    private RedeemAdminLog $adminLogModel;
    private RedeemKey $keyModel;
    private RedeemLog $logModel;
    private RedeemRuleService $ruleService;
    private RedeemCodeGenerator $codeGenerator;
    private PDO $db;

    public function __construct()
    {
        $this->categoryModel = new RedeemCategory();
        $this->batchModel = new RedeemBatch();
        $this->adminLogModel = new RedeemAdminLog();
        $this->keyModel = new RedeemKey();
        $this->logModel = new RedeemLog();
        $this->ruleService = new RedeemRuleService();
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
    public function createCategory(array $input, ?int $adminId = null, string $ipAddress = ''): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $template = trim((string)($input['defaultCommandTemplate'] ?? ($input['default_command_template'] ?? '')));
        $status = strtolower(trim((string)($input['status'] ?? 'enabled')));

        if ($name === '' || mb_strlen($name) > 128) {
            return ['ok' => false, 'status' => 400, 'message' => 'Category name is required and must be <= 128 chars'];
        }
        if ($template === '') {
            return ['ok' => false, 'status' => 400, 'message' => 'Default command template is required'];
        }
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $status = 'enabled';
        }

        $id = $this->categoryModel->create($name, $description, $template, $status);
        $this->createAdminLog(
            $adminId,
            'category_create',
            'category',
            $id,
            [
                'name' => $name,
                'status' => $status,
            ],
            $ipAddress
        );

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Category created',
            'data' => ['id' => $id],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string}
     */
    public function updateCategory(int $id, array $input, ?int $adminId = null, string $ipAddress = ''): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid category id'];
        }

        $existing = $this->categoryModel->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Category not found'];
        }

        $name = trim((string)($input['name'] ?? $existing['name'] ?? ''));
        $description = trim((string)($input['description'] ?? $existing['description'] ?? ''));
        $template = trim((string)($input['defaultCommandTemplate'] ?? ($input['default_command_template'] ?? ($existing['default_command_template'] ?? ''))));
        $status = strtolower(trim((string)($input['status'] ?? ($existing['status'] ?? 'enabled'))));

        if ($name === '' || mb_strlen($name) > 128) {
            return ['ok' => false, 'status' => 400, 'message' => 'Category name is required and must be <= 128 chars'];
        }
        if ($template === '') {
            return ['ok' => false, 'status' => 400, 'message' => 'Default command template is required'];
        }
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $status = 'enabled';
        }

        $this->categoryModel->update($id, $name, $description, $template, $status);
        $this->createAdminLog(
            $adminId,
            'category_update',
            'category',
            $id,
            [
                'name' => $name,
                'status' => $status,
            ],
            $ipAddress
        );

        return ['ok' => true, 'status' => 200, 'message' => 'Category updated'];
    }

    /**
     * @return array{ok:bool,status:int,message:string}
     */
    public function deleteCategory(int $id, ?int $adminId = null, string $ipAddress = ''): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid category id'];
        }

        $existing = $this->categoryModel->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Category not found'];
        }

        $keyCount = $this->categoryModel->countKeysByCategory($id);
        if ($keyCount > 0) {
            return ['ok' => false, 'status' => 409, 'message' => 'Category still has keys'];
        }

        $this->categoryModel->delete($id);
        $this->createAdminLog(
            $adminId,
            'category_delete',
            'category',
            $id,
            [
                'name' => (string)($existing['name'] ?? ''),
            ],
            $ipAddress
        );

        return ['ok' => true, 'status' => 200, 'message' => 'Category deleted'];
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
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listBatches(array $filters, int $page, int $perPage): array
    {
        return $this->batchModel->listPaginated($filters, $page, $perPage);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBatchById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->batchModel->findById($id);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function statsByBatch(int $id): ?array
    {
        $batch = $this->findBatchById($id);
        if ($batch === null) {
            return null;
        }

        return [
            'batch' => $batch,
            'stats' => $this->batchModel->statsByBatch($id),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string,items?:array<int,array<string,mixed>>,csv?:string,batch?:array<string,mixed>}
     */
    public function batchGenerateKeys(array $input, ?int $createdBy, string $ipAddress = ''): array
    {
        $pepper = $this->readCodePepper();
        if ($pepper === '') {
            return ['ok' => false, 'status' => 500, 'message' => 'Missing REDEEM_CODE_PEPPER'];
        }

        $categoryId = (int)($input['categoryId'] ?? ($input['category_id'] ?? 0));
        $count = (int)($input['count'] ?? 0);
        $length = (int)($input['length'] ?? 16);
        $maxUses = (int)($input['maxUses'] ?? ($input['max_uses'] ?? 1));
        $remark = trim((string)($input['remark'] ?? ''));
        $expiresAtRaw = trim((string)($input['expiresAt'] ?? ($input['expires_at'] ?? '')));
        $commandTemplateInput = trim((string)($input['commandTemplate'] ?? ($input['command_template'] ?? '')));
        $batchName = mb_substr(trim((string)($input['batchName'] ?? ($input['batch_name'] ?? ''))), 0, 128);
        $channel = mb_substr(trim((string)($input['channel'] ?? '')), 0, 128);
        $allowedServerIds = $this->normalizeAllowedServerIds((string)($input['allowedServerIds'] ?? ($input['allowed_server_ids'] ?? '')));
        $allowedServerIdsSerialized = $allowedServerIds === [] ? null : $this->encodeJsonArray($allowedServerIds);
        $boundPlayerUuid = $this->normalizePlayerUuid((string)($input['boundPlayerUuid'] ?? ($input['bound_player_uuid'] ?? '')));
        $boundPlayerName = mb_substr(trim((string)($input['boundPlayerName'] ?? ($input['bound_player_name'] ?? ''))), 0, 64);
        $requireBoundAccount = $this->toBoolInt($input['requireBoundAccount'] ?? ($input['require_bound_account'] ?? 0));
        $requireEmailVerified = $this->toBoolInt($input['requireEmailVerified'] ?? ($input['require_email_verified'] ?? 0));
        $requireAccountActive = $this->toBoolInt($input['requireAccountActive'] ?? ($input['require_account_active'] ?? 0));
        $perPlayerLimit = max(0, (int)($input['perPlayerLimit'] ?? ($input['per_player_limit'] ?? 0)));
        $perAccountLimit = max(0, (int)($input['perAccountLimit'] ?? ($input['per_account_limit'] ?? 0)));
        $ruleNote = mb_substr(trim((string)($input['ruleNote'] ?? ($input['rule_note'] ?? ''))), 0, 255);

        if ($requireEmailVerified === 1 || $requireAccountActive === 1) {
            $requireBoundAccount = 1;
        }

        if ($count < 1 || $count > 500) {
            return ['ok' => false, 'status' => 400, 'message' => 'Count must be between 1 and 500'];
        }

        $length = max(8, min(64, $length));
        $maxUses = max(1, min(100000, $maxUses));
        $remark = mb_substr($remark, 0, 255);

        $category = null;
        if ($categoryId > 0) {
            $category = $this->categoryModel->findById($categoryId);
            if ($category === null) {
                return ['ok' => false, 'status' => 404, 'message' => 'Category not found'];
            }
        }

        $resolvedTemplate = $commandTemplateInput;
        if ($resolvedTemplate === '' && is_array($category)) {
            $resolvedTemplate = trim((string)($category['default_command_template'] ?? ''));
        }
        if ($resolvedTemplate === '') {
            return ['ok' => false, 'status' => 400, 'message' => 'Command template is required when category is not selected'];
        }

        $expiresAt = null;
        if ($expiresAtRaw !== '') {
            $parsed = $this->parseDateTime($expiresAtRaw);
            if ($parsed === null) {
                return ['ok' => false, 'status' => 400, 'message' => 'Invalid expiresAt'];
            }
            $expiresAt = $parsed;
        }

        $groupSize = $length >= 20 ? 5 : 4;
        $createdByValue = $createdBy !== null && $createdBy > 0 ? $createdBy : null;
        $categoryIdValue = $categoryId > 0 ? $categoryId : null;

        $insert = $this->db->prepare(
            'INSERT INTO redeem_keys (category_id, batch_id, channel, allowed_server_ids, bound_player_uuid, bound_player_name, require_bound_account, require_email_verified, require_account_active, per_player_limit, per_account_limit, rule_note, code_hash, plain_code, command_template, max_uses, used_count, status, expires_at, remark, created_by, created_at, updated_at) '
            . 'VALUES (:category_id, :batch_id, :channel, :allowed_server_ids, :bound_player_uuid, :bound_player_name, :require_bound_account, :require_email_verified, :require_account_active, :per_player_limit, :per_account_limit, :rule_note, :code_hash, :plain_code, :command_template, :max_uses, 0, :status, :expires_at, :remark, :created_by, NOW(), NOW())'
        );

        $items = [];
        $attempts = 0;
        $maxAttempts = max($count * 30, 200);
        $batchPayload = null;
        $batchId = null;
        $batchNo = '';

        $this->db->beginTransaction();
        try {
            [$batchId, $batchNo] = $this->createBatchForGeneration(
                $batchName,
                $channel,
                $categoryIdValue,
                $count,
                $createdByValue,
                $remark
            );

            while (count($items) < $count && $attempts < $maxAttempts) {
                $attempts++;
                $plainCode = $this->codeGenerator->generate($length, $groupSize);
                $normalized = $this->normalizeCode($plainCode);
                $codeHash = $this->hashNormalizedCode($normalized, $pepper);

                try {
                    $insert->bindValue(':category_id', $categoryIdValue, $categoryIdValue === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insert->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
                    $insert->bindValue(':channel', $channel, PDO::PARAM_STR);
                    $insert->bindValue(':allowed_server_ids', $allowedServerIdsSerialized, $allowedServerIdsSerialized === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $insert->bindValue(':bound_player_uuid', $boundPlayerUuid !== '' ? $boundPlayerUuid : null, $boundPlayerUuid !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    $insert->bindValue(':bound_player_name', $boundPlayerName !== '' ? $boundPlayerName : null, $boundPlayerName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    $insert->bindValue(':require_bound_account', $requireBoundAccount, PDO::PARAM_INT);
                    $insert->bindValue(':require_email_verified', $requireEmailVerified, PDO::PARAM_INT);
                    $insert->bindValue(':require_account_active', $requireAccountActive, PDO::PARAM_INT);
                    $insert->bindValue(':per_player_limit', $perPlayerLimit, PDO::PARAM_INT);
                    $insert->bindValue(':per_account_limit', $perAccountLimit, PDO::PARAM_INT);
                    $insert->bindValue(':rule_note', $ruleNote, PDO::PARAM_STR);
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
                    'category_id' => $categoryIdValue,
                    'batch_id' => $batchId,
                    'batch_no' => $batchNo,
                    'batch_name' => $batchName,
                    'channel' => $channel,
                    'allowed_server_ids' => $allowedServerIdsSerialized,
                    'bound_player_uuid' => $boundPlayerUuid,
                    'bound_player_name' => $boundPlayerName,
                    'require_bound_account' => $requireBoundAccount,
                    'require_email_verified' => $requireEmailVerified,
                    'require_account_active' => $requireAccountActive,
                    'per_player_limit' => $perPlayerLimit,
                    'per_account_limit' => $perAccountLimit,
                    'rule_note' => $ruleNote,
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                    'remark' => $remark,
                ];
            }

            if (count($items) < $count) {
                $this->db->rollBack();
                return ['ok' => false, 'status' => 500, 'message' => 'Failed to generate keys, please retry'];
            }

            $this->db->commit();
            $batchPayload = [
                'id' => $batchId,
                'batchNo' => $batchNo,
                'name' => $batchName,
                'channel' => $channel,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->createAdminLog(
            $createdByValue,
            'batch_generate',
            'batch',
            $batchId,
            [
                'batch_no' => $batchNo,
                'category_id' => $categoryIdValue,
                'count' => count($items),
                'channel' => $channel,
            ],
            $ipAddress
        );
        $this->createAdminLog(
            $createdByValue,
            'v3_rule_batch_generate',
            'batch',
            $batchId,
            [
                'batch_no' => $batchNo,
                'count' => count($items),
                'rules' => [
                    'allowed_server_ids' => $allowedServerIds,
                    'bound_player_uuid' => $boundPlayerUuid,
                    'bound_player_name' => $boundPlayerName,
                    'require_bound_account' => $requireBoundAccount,
                    'require_email_verified' => $requireEmailVerified,
                    'require_account_active' => $requireAccountActive,
                    'per_player_limit' => $perPlayerLimit,
                    'per_account_limit' => $perAccountLimit,
                    'rule_note' => $ruleNote,
                ],
            ],
            $ipAddress
        );

        RealtimeNotifier::emit('redeem.key.generated', [
            'count' => count($items),
            'category_id' => $categoryIdValue,
        ]);
        RealtimeNotifier::emit('redeem.batch.generated', [
            'batch_id' => $batchId,
            'batch_no' => $batchNo,
            'count' => count($items),
            'category_id' => $categoryIdValue,
            'channel' => $channel,
        ]);
        RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Keys generated',
            'batch' => $batchPayload,
            'items' => $items,
            'csv' => $this->buildCsv($items),
        ];
    }

    /**
     * @return array{ok:bool,status:int,message:string}
     */
    public function revokeKey(int $id, ?int $adminId = null, string $ipAddress = ''): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid key id'];
        }

        $ok = $this->keyModel->revokeById($id);
        if (!$ok) {
            return ['ok' => false, 'status' => 404, 'message' => 'Key not found or cannot be revoked'];
        }

        $this->createAdminLog($adminId, 'key_revoke', 'key', $id, null, $ipAddress);

        RealtimeNotifier::emit('redeem.key.revoked', ['id' => $id]);
        RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        return ['ok' => true, 'status' => 200, 'message' => 'Revoked'];
    }

    /**
     * @param array<int,int> $ids
     * @return array{ok:bool,status:int,message:string,affected:int}
     */
    public function revokeKeys(array $ids, ?int $adminId = null, string $ipAddress = ''): array
    {
        $affected = $this->keyModel->revokeBatch($ids);
        if ($affected > 0) {
            $this->createAdminLog(
                $adminId,
                'key_revoke_batch',
                'key',
                null,
                [
                    'count' => $affected,
                    'ids' => array_slice(array_values($ids), 0, 100),
                ],
                $ipAddress
            );

            RealtimeNotifier::emit('redeem.key.revoked', ['ids' => array_values($ids), 'count' => $affected]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Batch revoke finished',
            'affected' => $affected,
        ];
    }

    /**
     * @param array<int,int> $ids
     * @return array{ok:bool,status:int,message:string,affected:int}
     */
    public function deleteKeys(array $ids, ?int $adminId = null, string $ipAddress = ''): array
    {
        $affected = $this->keyModel->deleteBatch($ids);
        if ($affected > 0) {
            $this->createAdminLog(
                $adminId,
                'key_soft_delete_batch',
                'key',
                null,
                [
                    'count' => $affected,
                    'ids' => array_slice(array_values($ids), 0, 100),
                ],
                $ipAddress
            );

            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Batch soft delete finished',
            'affected' => $affected,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{ok:bool,status:int,message:string,csv?:string,filename?:string,count?:int}
     */
    public function exportKeysCsv(array $filters, ?int $adminId = null, string $ipAddress = ''): array
    {
        $rows = $this->keyModel->listForExport($filters, 10000);
        $csv = $this->buildExportCsv($rows);
        $filename = 'redeem_keys_export_' . date('Ymd_His') . '.csv';
        $summary = $this->summarizeFilters($filters);

        $this->createAdminLog(
            $adminId,
            'export_keys_csv',
            'key',
            null,
            [
                'count' => count($rows),
                'filters' => $summary,
            ],
            $ipAddress
        );
        $this->createAdminLog(
            $adminId,
            'v3_rule_export',
            'key',
            null,
            [
                'count' => count($rows),
                'filters' => $summary,
            ],
            $ipAddress
        );

        RealtimeNotifier::emit('redeem.key.exported', [
            'count' => count($rows),
            'filters' => $summary,
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Export ready',
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($rows),
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
     * @param array<string,mixed> $input
     * @return array{ok:bool,status:int,message:string,data?:array<string,mixed>}
     */
    public function updateLogAdminStatus(int $logId, array $input, ?int $adminId, string $ipAddress = ''): array
    {
        if ($logId <= 0) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid log id'];
        }

        $adminStatus = strtolower(trim((string)($input['adminStatus'] ?? ($input['admin_status'] ?? 'pending'))));
        if (!in_array($adminStatus, ['pending', 'handled', 'ignored'], true)) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid admin status'];
        }

        $adminNote = mb_substr(trim((string)($input['adminNote'] ?? ($input['admin_note'] ?? ''))), 0, 500);
        $log = $this->logModel->findById($logId);
        if ($log === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Log not found'];
        }

        if ((string)($log['status'] ?? '') !== 'failed') {
            return ['ok' => false, 'status' => 409, 'message' => 'Only failed logs can be handled manually'];
        }

        $handledBy = null;
        $handledAt = null;
        if ($adminStatus !== 'pending') {
            $handledBy = $adminId !== null && $adminId > 0 ? $adminId : null;
            $handledAt = date('Y-m-d H:i:s');
        }

        $this->logModel->markAdminStatus($logId, $adminStatus, $adminNote, $handledBy, $handledAt);

        $action = 'log_mark_pending';
        if ($adminStatus === 'handled') {
            $action = 'log_mark_handled';
        } elseif ($adminStatus === 'ignored') {
            $action = 'log_mark_ignored';
        }

        $this->createAdminLog(
            $adminId,
            $action,
            'redeem_log',
            $logId,
            [
                'status' => 'failed',
                'admin_status' => $adminStatus,
                'note' => $adminNote,
            ],
            $ipAddress
        );

        RealtimeNotifier::emit('redeem.log.admin_status_updated', [
            'log_id' => $logId,
            'admin_status' => $adminStatus,
            'handled_by' => $handledBy,
        ]);
        RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Admin status updated',
            'data' => [
                'id' => $logId,
                'admin_status' => $adminStatus,
                'admin_note' => $adminNote,
                'handled_by' => $handledBy,
                'handled_at' => $handledAt,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listAdminLogs(array $filters, int $page, int $perPage): array
    {
        $result = $this->adminLogModel->listPaginated($filters, $page, $perPage);
        $items = [];
        foreach ($result['items'] as $item) {
            $detailRaw = (string)($item['detail_json'] ?? '');
            $detail = null;
            if ($detailRaw !== '') {
                $decoded = json_decode($detailRaw, true);
                if (is_array($decoded)) {
                    $detail = $decoded;
                }
            }

            $item['detail'] = $detail;
            $items[] = $item;
        }

        $result['items'] = $items;
        return $result;
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
            'batch_count' => $this->batchModel->countTotal(),
            'pending_failed_claims' => $this->logModel->countFailedPending(),
            'today_success_claims' => $this->logModel->countTodayByStatus('success'),
            'today_failed_claims' => $this->logModel->countTodayByStatus('failed'),
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
                'message' => 'Redeem service misconfigured: missing REDEEM_CODE_PEPPER',
            ];
        }

        $code = $this->normalizeCode((string)($payload['code'] ?? ''));
        if ($code === '') {
            return [
                'success' => false,
                'reason' => 'invalid_code',
                'message' => 'Invalid or unavailable code',
            ];
        }

        $codeHash = $this->hashNormalizedCode($code, $pepper);
        $serverId = trim((string)($payload['serverId'] ?? ($payload['server_id'] ?? '')));
        $playerName = trim((string)($payload['playerName'] ?? ($payload['player_name'] ?? '')));
        $playerUuid = $this->normalizePlayerUuid((string)($payload['playerUuid'] ?? ($payload['player_uuid'] ?? '')));
        $world = trim((string)($payload['world'] ?? ($payload['world_name'] ?? '')));

        try {
            $this->db->beginTransaction();

            $key = $this->keyModel->findByCodeHashForUpdate($codeHash);
            if ($key === null || (string)($key['status'] ?? '') === 'deleted') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'invalid_code',
                    'message' => 'Invalid or unavailable code',
                ];
            }

            $keyStatus = (string)($key['status'] ?? '');
            if ($keyStatus === 'revoked') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'revoked',
                    'message' => 'Code revoked',
                ];
            }

            if ($keyStatus !== 'available') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'invalid_code',
                    'message' => 'Invalid or unavailable code',
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
                        'message' => 'Code expired',
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
                    'message' => 'Code usage limit reached',
                ];
            }

            $categoryId = (int)($key['category_id'] ?? 0);
            $categoryStatus = strtolower(trim((string)($key['category_status'] ?? 'enabled')));
            if ($categoryId > 0 && $categoryStatus !== 'enabled') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'category_disabled',
                    'message' => 'Category disabled',
                ];
            }

            $commands = $this->parseCommands((string)($key['command_template'] ?? ''));
            if ($commands === []) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'reason' => 'internal_error',
                    'message' => 'Invalid command template',
                ];
            }

            $ruleDecision = $this->ruleService->evaluate($key, [
                'server_id' => $serverId,
                'player_uuid' => $playerUuid,
                'player_name' => $playerName,
            ]);

            $rulePassed = (bool)($ruleDecision['passed'] ?? false);
            $ruleReason = trim((string)($ruleDecision['reason'] ?? ''));
            $ruleMessage = trim((string)($ruleDecision['message'] ?? ''));
            $ruleResult = trim((string)($ruleDecision['result'] ?? 'passed'));
            if (!in_array($ruleResult, ['passed', 'rejected', 'skipped'], true)) {
                $ruleResult = $rulePassed ? 'passed' : 'rejected';
            }
            $websiteUserId = null;
            $candidateWebsiteUserId = (int)($ruleDecision['website_user_id'] ?? 0);
            if ($candidateWebsiteUserId > 0) {
                $websiteUserId = $candidateWebsiteUserId;
            }
            $ruleSnapshotJson = $this->encodeJsonObject($ruleDecision['snapshot'] ?? null);

            if (!$rulePassed) {
                if ($ruleReason === '') {
                    $ruleReason = 'rule_invalid';
                }
                if ($ruleMessage === '') {
                    $ruleMessage = 'Redeem rule rejected this claim.';
                }

                $this->logModel->createRuleRejected([
                    'key_id' => (int)$key['id'],
                    'redeem_code_hash' => $codeHash,
                    'server_id' => $serverId,
                    'player_uuid' => $playerUuid !== '' ? $playerUuid : null,
                    'player_name' => $playerName !== '' ? $playerName : null,
                    'world_name' => $world !== '' ? $world : null,
                    'rule_reason' => $ruleReason,
                    'rule_snapshot_json' => $ruleSnapshotJson,
                    'website_user_id' => $websiteUserId,
                    'failure_reason' => $ruleMessage,
                    'admin_status' => 'ignored',
                ]);

                $this->db->commit();

                RealtimeNotifier::emit('redeem.claim.rejected', [
                    'key_id' => (int)$key['id'],
                    'server_id' => $serverId,
                    'player_uuid' => $playerUuid,
                    'player_name' => $playerName,
                    'reason' => $ruleReason,
                ]);
                RealtimeNotifier::emit('redeem.rule.denied', [
                    'key_id' => (int)$key['id'],
                    'server_id' => $serverId,
                    'player_uuid' => $playerUuid,
                    'player_name' => $playerName,
                    'website_user_id' => $websiteUserId,
                    'reason' => $ruleReason,
                ]);
                RealtimeNotifier::emit('redeem.rule.stats.updated', [
                    'key_id' => (int)$key['id'],
                    'result' => 'rejected',
                    'reason' => $ruleReason,
                ]);
                RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

                return [
                    'success' => false,
                    'reason' => $ruleReason,
                    'message' => $ruleMessage,
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
                'rule_result' => $ruleResult,
                'rule_reason' => $ruleReason,
                'rule_snapshot_json' => $ruleSnapshotJson,
                'website_user_id' => $websiteUserId,
                'command_snapshot' => $commandSnapshot,
            ]);

            $this->db->commit();

            RealtimeNotifier::emit('redeem.rule.matched', [
                'key_id' => (int)$key['id'],
                'server_id' => $serverId,
                'player_uuid' => $playerUuid,
                'player_name' => $playerName,
                'website_user_id' => $websiteUserId,
            ]);
            RealtimeNotifier::emit('redeem.rule.stats.updated', [
                'key_id' => (int)$key['id'],
                'result' => $ruleResult,
            ]);

            return [
                'success' => true,
                'redeemId' => $logId,
                'message' => 'Redeem executing',
                'commands' => $commands,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'reason' => 'internal_error',
                'message' => 'Internal server error',
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
                return ['success' => false, 'message' => 'redeemId not found'];
            }

            $status = (string)($log['status'] ?? '');
            if ($status === 'success') {
                $this->db->commit();
                return ['success' => true, 'message' => 'already completed'];
            }
            if ($status === 'failed') {
                $this->db->commit();
                return ['success' => false, 'message' => 'already failed'];
            }
            if ($status !== 'executing') {
                $this->db->commit();
                return ['success' => false, 'message' => 'invalid redeem status'];
            }

            $this->logModel->markSuccess($redeemId, $executedCommandsJson);
            $this->db->commit();

            RealtimeNotifier::emit('redeem.claim.success', ['redeem_id' => $redeemId]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

            return ['success' => true, 'message' => 'completed'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'internal server error'];
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
                return ['success' => false, 'message' => 'redeemId not found'];
            }

            $status = (string)($log['status'] ?? '');
            if ($status === 'success') {
                $this->db->commit();
                return ['success' => false, 'message' => 'already succeeded'];
            }
            if ($status === 'failed') {
                $this->db->commit();
                return ['success' => true, 'message' => 'already failed'];
            }
            if ($status !== 'executing') {
                $this->db->commit();
                return ['success' => false, 'message' => 'invalid redeem status'];
            }

            $this->logModel->markFailed($redeemId, $failureReason, $executedCommandsJson);
            $this->db->commit();

            RealtimeNotifier::emit('redeem.claim.failed', ['redeem_id' => $redeemId, 'reason' => $failureReason]);
            RealtimeNotifier::emit('redeem.stats.updated', $this->getPublishStats());

            return ['success' => true, 'message' => 'failed recorded'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'internal server error'];
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

    private function normalizePlayerUuid(string $uuid): string
    {
        $normalized = strtolower(str_replace('-', '', trim($uuid)));
        $normalized = preg_replace('/[^0-9a-f]/', '', $normalized) ?? '';
        return mb_substr($normalized, 0, 64);
    }

    /**
     * @return array<int,string>
     */
    private function normalizeAllowedServerIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = [];
                foreach ($decoded as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $value = trim((string)$item);
                    if ($value !== '') {
                        $items[] = $value;
                    }
                }
                return array_values(array_unique($items));
            }
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $value = trim((string)$part);
            if ($value !== '') {
                $items[] = $value;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param mixed $raw
     */
    private function toBoolInt($raw): int
    {
        if (is_bool($raw)) {
            return $raw ? 1 : 0;
        }
        if (is_numeric($raw)) {
            return ((int)$raw) > 0 ? 1 : 0;
        }

        $value = strtolower(trim((string)$raw));
        return in_array($value, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
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
     * @param mixed $value
     */
    private function encodeJsonObject($value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : null;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function buildCsv(array $items): string
    {
        $lines = [];
        $lines[] = 'code,category,max_uses,expires_at,remark,batch_no,batch_name,channel,allowed_server_ids,bound_player_uuid,bound_player_name,require_bound_account,require_email_verified,require_account_active,per_player_limit,per_account_limit,rule_note';

        foreach ($items as $item) {
            $row = [
                (string)($item['code'] ?? ''),
                (string)($item['category'] ?? ''),
                (string)($item['max_uses'] ?? ''),
                (string)($item['expires_at'] ?? ''),
                (string)($item['remark'] ?? ''),
                (string)($item['batch_no'] ?? ''),
                (string)($item['batch_name'] ?? ''),
                (string)($item['channel'] ?? ''),
                (string)($item['allowed_server_ids'] ?? ''),
                (string)($item['bound_player_uuid'] ?? ''),
                (string)($item['bound_player_name'] ?? ''),
                (string)($item['require_bound_account'] ?? '0'),
                (string)($item['require_email_verified'] ?? '0'),
                (string)($item['require_account_active'] ?? '0'),
                (string)($item['per_player_limit'] ?? '0'),
                (string)($item['per_account_limit'] ?? '0'),
                (string)($item['rule_note'] ?? ''),
            ];

            $escaped = array_map(static function (string $cell): string {
                $cell = str_replace('"', '""', $cell);
                return '"' . $cell . '"';
            }, $row);
            $lines[] = implode(',', $escaped);
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function buildExportCsv(array $rows): string
    {
        $lines = [];
        $lines[] = 'id,code,category,batch_no,batch_name,channel,status,max_uses,used_count,expires_at,remark,allowed_server_ids,bound_player_uuid,bound_player_name,require_bound_account,require_email_verified,require_account_active,per_player_limit,per_account_limit,rule_note,created_at';

        foreach ($rows as $row) {
            $cells = [
                (string)($row['id'] ?? ''),
                (string)($row['plain_code'] ?? ''),
                (string)($row['category_name'] ?? ''),
                (string)($row['batch_no'] ?? ''),
                (string)($row['batch_name'] ?? ''),
                (string)($row['channel'] ?? ''),
                (string)($row['status'] ?? ''),
                (string)($row['max_uses'] ?? ''),
                (string)($row['used_count'] ?? ''),
                (string)($row['expires_at'] ?? ''),
                (string)($row['remark'] ?? ''),
                (string)($row['allowed_server_ids'] ?? ''),
                (string)($row['bound_player_uuid'] ?? ''),
                (string)($row['bound_player_name'] ?? ''),
                (string)($row['require_bound_account'] ?? '0'),
                (string)($row['require_email_verified'] ?? '0'),
                (string)($row['require_account_active'] ?? '0'),
                (string)($row['per_player_limit'] ?? '0'),
                (string)($row['per_account_limit'] ?? '0'),
                (string)($row['rule_note'] ?? ''),
                (string)($row['created_at'] ?? ''),
            ];

            $escaped = array_map(static function (string $cell): string {
                $cell = str_replace('"', '""', $cell);
                return '"' . $cell . '"';
            }, $cells);
            $lines[] = implode(',', $escaped);
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function summarizeFilters(array $filters): array
    {
        $keys = [
            'status',
            'category_id',
            'batch_id',
            'channel',
            'bound_player_uuid',
            'allowed_server_id',
            'require_bound_account',
            'require_email_verified',
            'require_account_active',
            'q',
            'created_from',
            'created_to',
            'expires_from',
            'expires_to',
            'admin_status',
            'rule_result',
            'rule_reason',
            'website_user_id',
            'server_id',
            'player_uuid',
            'player_name',
        ];

        $summary = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            $summary[$key] = $value;
        }

        return $summary;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function createBatchForGeneration(
        string $batchName,
        string $channel,
        ?int $categoryId,
        int $count,
        ?int $createdBy,
        string $remark
    ): array {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $batchNo = $this->generateBatchNo();
            try {
                $id = $this->batchModel->createBatch(
                    $batchNo,
                    $batchName,
                    $channel,
                    $categoryId,
                    $count,
                    $createdBy,
                    $remark
                );
                return [$id, $batchNo];
            } catch (PDOException $e) {
                if ($this->isDuplicateEntry($e)) {
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to allocate batch number');
    }

    private function generateBatchNo(): string
    {
        $datePart = date('YmdHis');
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return 'RB' . $datePart . $rand;
    }

    /**
     * @param array<string,mixed>|null $detail
     */
    private function createAdminLog(
        ?int $adminId,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $detail,
        string $ipAddress
    ): void {
        if ($action === '') {
            return;
        }

        $safeIp = mb_substr(trim($ipAddress), 0, 64);
        if ($safeIp === '') {
            $safeIp = '0.0.0.0';
        }

        $logId = $this->adminLogModel->create($adminId, $action, $targetType, $targetId, $detail, $safeIp);
        RealtimeNotifier::emit('redeem.admin_log.created', [
            'id' => $logId,
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
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
