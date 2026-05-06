<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class RedeemLog extends Model
{
    /**
     * @param array<string,mixed> $data
     */
    public function createExecuting(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO redeem_logs (key_id, redeem_code_hash, server_id, player_uuid, player_name, world_name, status, admin_status, rule_result, rule_reason, rule_snapshot_json, website_user_id, failure_reason, command_snapshot, executed_commands, created_at, completed_at) '
            . 'VALUES (:key_id, :redeem_code_hash, :server_id, :player_uuid, :player_name, :world_name, :status, :admin_status, :rule_result, :rule_reason, :rule_snapshot_json, :website_user_id, :failure_reason, :command_snapshot, :executed_commands, NOW(), NULL)'
        );

        $stmt->bindValue(':key_id', $data['key_id'] ?? null, ($data['key_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':redeem_code_hash', (string)($data['redeem_code_hash'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':server_id', (string)($data['server_id'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':player_uuid', $data['player_uuid'] ?? null, ($data['player_uuid'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':player_name', $data['player_name'] ?? null, ($data['player_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':world_name', $data['world_name'] ?? null, ($data['world_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':status', 'executing', PDO::PARAM_STR);
        $stmt->bindValue(':admin_status', 'pending', PDO::PARAM_STR);
        $stmt->bindValue(':rule_result', (string)($data['rule_result'] ?? 'passed'), PDO::PARAM_STR);
        $stmt->bindValue(':rule_reason', (string)($data['rule_reason'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':rule_snapshot_json', $data['rule_snapshot_json'] ?? null, ($data['rule_snapshot_json'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':website_user_id', $data['website_user_id'] ?? null, ($data['website_user_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':failure_reason', null, PDO::PARAM_NULL);
        $stmt->bindValue(':command_snapshot', $data['command_snapshot'] ?? null, ($data['command_snapshot'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':executed_commands', null, PDO::PARAM_NULL);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function createRuleRejected(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO redeem_logs (key_id, redeem_code_hash, server_id, player_uuid, player_name, world_name, status, admin_status, rule_result, rule_reason, rule_snapshot_json, website_user_id, failure_reason, command_snapshot, executed_commands, created_at, completed_at) '
            . "VALUES (:key_id, :redeem_code_hash, :server_id, :player_uuid, :player_name, :world_name, 'failed', :admin_status, 'rejected', :rule_reason, :rule_snapshot_json, :website_user_id, :failure_reason, NULL, NULL, NOW(), NOW())"
        );

        $stmt->bindValue(':key_id', $data['key_id'] ?? null, ($data['key_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':redeem_code_hash', (string)($data['redeem_code_hash'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':server_id', (string)($data['server_id'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':player_uuid', $data['player_uuid'] ?? null, ($data['player_uuid'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':player_name', $data['player_name'] ?? null, ($data['player_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':world_name', $data['world_name'] ?? null, ($data['world_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':admin_status', (string)($data['admin_status'] ?? 'ignored'), PDO::PARAM_STR);
        $stmt->bindValue(':rule_reason', (string)($data['rule_reason'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':rule_snapshot_json', $data['rule_snapshot_json'] ?? null, ($data['rule_snapshot_json'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':website_user_id', $data['website_user_id'] ?? null, ($data['website_user_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':failure_reason', (string)($data['failure_reason'] ?? ''), PDO::PARAM_STR);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM redeem_logs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByIdForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM redeem_logs WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function markSuccess(int $id, ?string $executedCommandsJson): void
    {
        $stmt = $this->db->prepare("UPDATE redeem_logs SET status = 'success', executed_commands = :executed_commands, failure_reason = NULL, completed_at = NOW() WHERE id = :id");
        $stmt->bindValue(':executed_commands', $executedCommandsJson, $executedCommandsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markFailed(int $id, string $failureReason, ?string $executedCommandsJson): void
    {
        $stmt = $this->db->prepare("UPDATE redeem_logs SET status = 'failed', failure_reason = :failure_reason, executed_commands = :executed_commands, completed_at = NOW() WHERE id = :id");
        $stmt->bindValue(':failure_reason', $failureReason, PDO::PARAM_STR);
        $stmt->bindValue(':executed_commands', $executedCommandsJson, $executedCommandsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markAdminStatus(int $id, string $adminStatus, string $adminNote, ?int $handledBy, ?string $handledAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE redeem_logs '
            . 'SET admin_status = :admin_status, admin_note = :admin_note, handled_by = :handled_by, handled_at = :handled_at '
            . 'WHERE id = :id'
        );
        $stmt->bindValue(':admin_status', $adminStatus, PDO::PARAM_STR);
        $stmt->bindValue(':admin_note', $adminNote, PDO::PARAM_STR);
        $stmt->bindValue(':handled_by', $handledBy, $handledBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':handled_at', $handledAt, $handledAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildFilterWhere($filters);

        $countSql = 'SELECT COUNT(*) FROM redeem_logs rl LEFT JOIN redeem_keys rk ON rk.id = rl.key_id ' . $whereSql;
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT rl.id, rl.key_id, rl.server_id, rl.player_uuid, rl.player_name, rl.world_name, rl.status, rl.admin_status, rl.admin_note, rl.handled_by, rl.handled_at, rl.rule_result, rl.rule_reason, rl.rule_snapshot_json, rl.website_user_id, rl.failure_reason, rl.command_snapshot, rl.executed_commands, rl.created_at, rl.completed_at, '
            . 'rk.plain_code, rk.channel, rc.name AS category_name, rb.batch_no '
            . 'FROM redeem_logs rl '
            . 'LEFT JOIN redeem_keys rk ON rk.id = rl.key_id '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rk.category_id '
            . 'LEFT JOIN redeem_batches rb ON rb.id = rk.batch_id '
            . $whereSql
            . ' ORDER BY rl.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function countFailed(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM redeem_logs WHERE status = 'failed'")->fetchColumn();
    }

    public function countFailedPending(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM redeem_logs WHERE status = 'failed' AND admin_status = 'pending'")->fetchColumn();
    }

    public function countTodayByStatus(string $status): int
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['success', 'failed'], true)) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM redeem_logs WHERE status = :status AND DATE(created_at) = CURDATE()');
        $stmt->execute([':status' => $status]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildFilterWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, ['executing', 'success', 'failed'], true)) {
            $where[] = 'rl.status = :status';
            $params[':status'] = $status;
        }

        $adminStatus = strtolower(trim((string)($filters['admin_status'] ?? '')));
        if ($adminStatus !== '' && in_array($adminStatus, ['pending', 'handled', 'ignored'], true)) {
            $where[] = 'rl.admin_status = :admin_status';
            $params[':admin_status'] = $adminStatus;
        }

        $ruleResult = strtolower(trim((string)($filters['rule_result'] ?? '')));
        if ($ruleResult !== '' && in_array($ruleResult, ['passed', 'rejected', 'skipped'], true)) {
            $where[] = 'rl.rule_result = :rule_result';
            $params[':rule_result'] = $ruleResult;
        }

        $ruleReason = trim((string)($filters['rule_reason'] ?? ''));
        if ($ruleReason !== '') {
            $where[] = 'rl.rule_reason LIKE :rule_reason';
            $params[':rule_reason'] = '%' . $ruleReason . '%';
        }

        $websiteUserId = (int)($filters['website_user_id'] ?? 0);
        if ($websiteUserId > 0) {
            $where[] = 'rl.website_user_id = :website_user_id';
            $params[':website_user_id'] = $websiteUserId;
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'rk.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $serverId = trim((string)($filters['server_id'] ?? ''));
        if ($serverId !== '') {
            $where[] = 'rl.server_id = :server_id';
            $params[':server_id'] = $serverId;
        }

        $playerUuid = trim((string)($filters['player_uuid'] ?? ''));
        if ($playerUuid !== '') {
            $where[] = 'rl.player_uuid = :player_uuid';
            $params[':player_uuid'] = $playerUuid;
        }

        $playerName = trim((string)($filters['player_name'] ?? ''));
        if ($playerName !== '') {
            $where[] = 'rl.player_name LIKE :player_name';
            $params[':player_name'] = '%' . $playerName . '%';
        }

        $createdFrom = trim((string)($filters['created_from'] ?? ''));
        if ($createdFrom !== '') {
            $where[] = 'rl.created_at >= :created_from';
            $params[':created_from'] = $createdFrom;
        }

        $createdTo = trim((string)($filters['created_to'] ?? ''));
        if ($createdTo !== '') {
            $where[] = 'rl.created_at <= :created_to';
            $params[':created_to'] = $createdTo;
        }

        $keyword = trim((string)($filters['q'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(rl.player_name LIKE :kw OR rl.player_uuid LIKE :kw OR rl.server_id LIKE :kw OR rb.batch_no LIKE :kw OR rk.channel LIKE :kw)';
            $params[':kw'] = '%' . $keyword . '%';
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        return [$whereSql, $params];
    }
}
