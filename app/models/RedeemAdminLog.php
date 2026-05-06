<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class RedeemAdminLog extends Model
{
    /**
     * @param array<string,mixed>|null $detail
     */
    public function create(
        ?int $adminId,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $detail,
        string $ipAddress
    ): int {
        $detailJson = null;
        if (is_array($detail) && $detail !== []) {
            $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded)) {
                $detailJson = $encoded;
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO redeem_admin_logs (admin_id, action, target_type, target_id, detail_json, ip_address, created_at) '
            . 'VALUES (:admin_id, :action, :target_type, :target_id, :detail_json, :ip_address, NOW())'
        );
        $stmt->bindValue(':admin_id', $adminId, $adminId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $targetId, $targetId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':detail_json', $detailJson, $detailJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
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

        $where = [];
        $params = [];

        $action = trim((string)($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'ral.action = :action';
            $params[':action'] = $action;
        }

        $targetType = trim((string)($filters['target_type'] ?? ''));
        if ($targetType !== '') {
            $where[] = 'ral.target_type = :target_type';
            $params[':target_type'] = $targetType;
        }

        $createdFrom = trim((string)($filters['created_from'] ?? ''));
        if ($createdFrom !== '') {
            $where[] = 'ral.created_at >= :created_from';
            $params[':created_from'] = $createdFrom;
        }

        $createdTo = trim((string)($filters['created_to'] ?? ''));
        if ($createdTo !== '') {
            $where[] = 'ral.created_at <= :created_to';
            $params[':created_to'] = $createdTo;
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(CAST(ral.admin_id AS CHAR) LIKE :kw OR CAST(ral.target_id AS CHAR) LIKE :kw OR ral.action LIKE :kw OR ral.target_type LIKE :kw OR ral.ip_address LIKE :kw OR CAST(ral.detail_json AS CHAR) LIKE :kw)';
            $params[':kw'] = '%' . $q . '%';
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM redeem_admin_logs ral ' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT ral.id, ral.admin_id, ral.action, ral.target_type, ral.target_id, ral.detail_json, ral.ip_address, ral.created_at '
            . 'FROM redeem_admin_logs ral '
            . $whereSql
            . ' ORDER BY ral.id DESC LIMIT :limit OFFSET :offset';

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
}
