<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class AuditModel extends Model
{
    public function logAction(?int $userId, string $action, string $ipAddress, array $details = []): void
    {
        $detailsJson = null;
        if (!empty($details)) {
            $encoded = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $detailsJson = ($encoded === false) ? null : $encoded;
        }

        $stmt = $this->db->prepare('
            INSERT INTO audit_logs (user_id, action, ip_address, details, created_at)
            VALUES (:user_id, :action, :ip_address, :details, NOW())
        ');
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':details', $detailsJson, $detailsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    }
}

