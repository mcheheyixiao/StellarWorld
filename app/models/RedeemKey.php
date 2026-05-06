<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class RedeemKey extends Model
{
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

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM redeem_keys rk ' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT rk.id, rk.category_id, rk.batch_id, rk.channel, rk.plain_code, rk.max_uses, rk.used_count, rk.status, rk.expires_at, rk.remark, rk.created_by, rk.created_at, rk.updated_at, '
            . 'rc.name AS category_name, rb.batch_no, rb.name AS batch_name '
            . 'FROM redeem_keys rk '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rk.category_id '
            . 'LEFT JOIN redeem_batches rb ON rb.id = rk.batch_id '
            . $whereSql
            . ' ORDER BY rk.id DESC LIMIT :limit OFFSET :offset';

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
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function listForExport(array $filters, int $limit = 5000): array
    {
        $limit = max(1, min(20000, $limit));
        [$whereSql, $params] = $this->buildFilterWhere($filters);

        $sql = 'SELECT rk.id, rk.category_id, rk.batch_id, rk.channel, rk.plain_code, rk.max_uses, rk.used_count, rk.status, rk.expires_at, rk.remark, rk.created_by, rk.created_at, rk.updated_at, '
            . 'rc.name AS category_name, rb.batch_no, rb.name AS batch_name '
            . 'FROM redeem_keys rk '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rk.category_id '
            . 'LEFT JOIN redeem_batches rb ON rb.id = rk.batch_id '
            . $whereSql
            . ' ORDER BY rk.id DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByCodeHashForUpdate(string $codeHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT rk.*, rc.status AS category_status, rc.name AS category_name '
            . 'FROM redeem_keys rk '
            . 'LEFT JOIN redeem_categories rc ON rc.id = rk.category_id '
            . 'WHERE rk.code_hash = :code_hash LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':code_hash' => $codeHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function incrementUsage(int $keyId): void
    {
        $stmt = $this->db->prepare('UPDATE redeem_keys SET used_count = used_count + 1, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $keyId]);
    }

    public function revokeById(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE redeem_keys SET status = 'revoked', updated_at = NOW() WHERE id = :id AND status <> 'deleted'");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int,int> $ids
     */
    public function revokeBatch(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
        if ($ids === []) {
            return 0;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE redeem_keys SET status = 'revoked', updated_at = NOW() WHERE status <> 'deleted' AND id IN ($in)");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /**
     * @param array<int,int> $ids
     */
    public function deleteBatch(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
        if ($ids === []) {
            return 0;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE redeem_keys SET status = 'deleted', updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public function countTotal(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM redeem_keys')->fetchColumn();
    }

    public function countRevoked(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM redeem_keys WHERE status = 'revoked'")->fetchColumn();
    }

    public function sumUsedCount(): int
    {
        return (int)$this->db->query('SELECT COALESCE(SUM(used_count), 0) FROM redeem_keys')->fetchColumn();
    }

    public function countPublishable(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM redeem_keys rk "
            . "LEFT JOIN redeem_categories rc ON rc.id = rk.category_id "
            . "WHERE rk.status = 'available' "
            . "AND rk.used_count < rk.max_uses "
            . "AND (rk.expires_at IS NULL OR rk.expires_at > NOW()) "
            . "AND (rk.category_id IS NULL OR rc.status = 'enabled')"
        );
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
        if ($status !== '' && in_array($status, ['available', 'revoked', 'deleted'], true)) {
            $where[] = 'rk.status = :status';
            $params[':status'] = $status;
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'rk.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $batchId = (int)($filters['batch_id'] ?? 0);
        if ($batchId > 0) {
            $where[] = 'rk.batch_id = :batch_id';
            $params[':batch_id'] = $batchId;
        }

        $channel = trim((string)($filters['channel'] ?? ''));
        if ($channel !== '') {
            $where[] = 'rk.channel = :channel';
            $params[':channel'] = $channel;
        }

        $createdFrom = trim((string)($filters['created_from'] ?? ''));
        if ($createdFrom !== '') {
            $where[] = 'rk.created_at >= :created_from';
            $params[':created_from'] = $createdFrom;
        }

        $createdTo = trim((string)($filters['created_to'] ?? ''));
        if ($createdTo !== '') {
            $where[] = 'rk.created_at <= :created_to';
            $params[':created_to'] = $createdTo;
        }

        $expiresFrom = trim((string)($filters['expires_from'] ?? ''));
        if ($expiresFrom !== '') {
            $where[] = 'rk.expires_at IS NOT NULL AND rk.expires_at >= :expires_from';
            $params[':expires_from'] = $expiresFrom;
        }

        $expiresTo = trim((string)($filters['expires_to'] ?? ''));
        if ($expiresTo !== '') {
            $where[] = 'rk.expires_at IS NOT NULL AND rk.expires_at <= :expires_to';
            $params[':expires_to'] = $expiresTo;
        }

        $keyword = trim((string)($filters['q'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(rk.plain_code LIKE :kw OR rk.remark LIKE :kw OR rb.batch_no LIKE :kw OR rb.name LIKE :kw)';
            $params[':kw'] = '%' . $keyword . '%';
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        return [$whereSql, $params];
    }
}
