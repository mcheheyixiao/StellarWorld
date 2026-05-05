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
            'INSERT INTO redeem_logs (key_id, redeem_code_hash, server_id, player_uuid, player_name, world_name, status, failure_reason, command_snapshot, executed_commands, created_at, completed_at) '
            . 'VALUES (:key_id, :redeem_code_hash, :server_id, :player_uuid, :player_name, :world_name, :status, :failure_reason, :command_snapshot, :executed_commands, NOW(), NULL)'
        );

        $stmt->bindValue(':key_id', $data['key_id'] ?? null, ($data['key_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':redeem_code_hash', (string)($data['redeem_code_hash'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':server_id', (string)($data['server_id'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':player_uuid', $data['player_uuid'] ?? null, ($data['player_uuid'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':player_name', $data['player_name'] ?? null, ($data['player_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':world_name', $data['world_name'] ?? null, ($data['world_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':status', 'executing', PDO::PARAM_STR);
        $stmt->bindValue(':failure_reason', null, PDO::PARAM_NULL);
        $stmt->bindValue(':command_snapshot', $data['command_snapshot'] ?? null, ($data['command_snapshot'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':executed_commands', null, PDO::PARAM_NULL);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
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

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, ['executing', 'success', 'failed'], true)) {
            $where[] = 'rl.status = :status';
            $params[':status'] = $status;
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'rk.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $keyword = trim((string)($filters['q'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(rl.player_name LIKE :kw OR rl.player_uuid LIKE :kw OR rl.server_id LIKE :kw)';
            $params[':kw'] = '%' . $keyword . '%';
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $countSql = 'SELECT COUNT(*) FROM redeem_logs rl LEFT JOIN redeem_keys rk ON rk.id = rl.key_id ' . $whereSql;
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT rl.id, rl.key_id, rl.server_id, rl.player_uuid, rl.player_name, rl.world_name, rl.status, rl.failure_reason, rl.command_snapshot, rl.executed_commands, rl.created_at, rl.completed_at, rk.plain_code, rc.name AS category_name '
            . 'FROM redeem_logs rl '
            . 'LEFT JOIN redeem_keys rk ON rk.id = rl.key_id '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rk.category_id '
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
}
