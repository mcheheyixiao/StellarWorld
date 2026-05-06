<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class RedeemBatch extends Model
{
    public function createBatch(
        string $batchNo,
        string $name,
        string $channel,
        ?int $categoryId,
        int $totalCount,
        ?int $createdBy,
        string $remark
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO redeem_batches (batch_no, name, channel, category_id, total_count, created_by, remark, created_at, updated_at) '
            . 'VALUES (:batch_no, :name, :channel, :category_id, :total_count, :created_by, :remark, NOW(), NOW())'
        );
        $stmt->bindValue(':batch_no', $batchNo, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':category_id', $categoryId, $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':total_count', max(0, $totalCount), PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':remark', $remark, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
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

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM redeem_batches rb ' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT rb.id, rb.batch_no, rb.name, rb.channel, rb.category_id, rb.total_count, rb.created_by, rb.remark, rb.created_at, rb.updated_at, '
            . 'rc.name AS category_name '
            . 'FROM redeem_batches rb '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rb.category_id '
            . $whereSql
            . ' ORDER BY rb.id DESC LIMIT :limit OFFSET :offset';

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

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT rb.id, rb.batch_no, rb.name, rb.channel, rb.category_id, rb.total_count, rb.created_by, rb.remark, rb.created_at, rb.updated_at, '
            . 'rc.name AS category_name '
            . 'FROM redeem_batches rb '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rb.category_id '
            . 'WHERE rb.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,int>
     */
    public function statsByBatch(int $batchId): array
    {
        $keysStmt = $this->db->prepare(
            'SELECT '
            . 'COUNT(*) AS total_keys, '
            . "SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_keys, "
            . "SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked_keys, "
            . "SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) AS deleted_keys, "
            . 'SUM(used_count) AS used_count_sum, '
            . "SUM(CASE WHEN used_count >= max_uses THEN 1 ELSE 0 END) AS exhausted_keys, "
            . "SUM(CASE WHEN status = 'available' AND used_count < max_uses AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS publishable_keys "
            . 'FROM redeem_keys '
            . 'WHERE batch_id = :batch_id'
        );
        $keysStmt->execute([':batch_id' => $batchId]);
        $keyStats = $keysStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $logsStmt = $this->db->prepare(
            'SELECT '
            . 'COUNT(*) AS total_logs, '
            . "SUM(CASE WHEN rl.status = 'executing' THEN 1 ELSE 0 END) AS executing_logs, "
            . "SUM(CASE WHEN rl.status = 'success' THEN 1 ELSE 0 END) AS success_logs, "
            . "SUM(CASE WHEN rl.status = 'failed' THEN 1 ELSE 0 END) AS failed_logs, "
            . "SUM(CASE WHEN rl.status = 'failed' AND rl.admin_status = 'pending' THEN 1 ELSE 0 END) AS pending_failed_logs "
            . 'FROM redeem_logs rl '
            . 'INNER JOIN redeem_keys rk ON rk.id = rl.key_id '
            . 'WHERE rk.batch_id = :batch_id'
        );
        $logsStmt->execute([':batch_id' => $batchId]);
        $logStats = $logsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_keys' => (int)($keyStats['total_keys'] ?? 0),
            'available_keys' => (int)($keyStats['available_keys'] ?? 0),
            'revoked_keys' => (int)($keyStats['revoked_keys'] ?? 0),
            'deleted_keys' => (int)($keyStats['deleted_keys'] ?? 0),
            'used_count_sum' => (int)($keyStats['used_count_sum'] ?? 0),
            'exhausted_keys' => (int)($keyStats['exhausted_keys'] ?? 0),
            'publishable_keys' => (int)($keyStats['publishable_keys'] ?? 0),
            'total_logs' => (int)($logStats['total_logs'] ?? 0),
            'executing_logs' => (int)($logStats['executing_logs'] ?? 0),
            'success_logs' => (int)($logStats['success_logs'] ?? 0),
            'failed_logs' => (int)($logStats['failed_logs'] ?? 0),
            'pending_failed_logs' => (int)($logStats['pending_failed_logs'] ?? 0),
        ];
    }

    public function countTotal(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM redeem_batches')->fetchColumn();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildFilterWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'rb.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $channel = trim((string)($filters['channel'] ?? ''));
        if ($channel !== '') {
            $where[] = 'rb.channel = :channel';
            $params[':channel'] = $channel;
        }

        $createdFrom = trim((string)($filters['created_from'] ?? ''));
        if ($createdFrom !== '') {
            $where[] = 'rb.created_at >= :created_from';
            $params[':created_from'] = $createdFrom;
        }

        $createdTo = trim((string)($filters['created_to'] ?? ''));
        if ($createdTo !== '') {
            $where[] = 'rb.created_at <= :created_to';
            $params[':created_to'] = $createdTo;
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(rb.batch_no LIKE :kw OR rb.name LIKE :kw OR rb.channel LIKE :kw OR rb.remark LIKE :kw)';
            $params[':kw'] = '%' . $q . '%';
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        return [$whereSql, $params];
    }
}
