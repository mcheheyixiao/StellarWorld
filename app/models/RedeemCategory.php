<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class RedeemCategory extends Model
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, name, description, default_command_template, status, created_at, updated_at FROM redeem_categories ORDER BY id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, description, default_command_template, status, created_at, updated_at FROM redeem_categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $name, string $description, string $defaultCommandTemplate, string $status): int
    {
        $stmt = $this->db->prepare('INSERT INTO redeem_categories (name, description, default_command_template, status, created_at, updated_at) VALUES (:name, :description, :default_command_template, :status, NOW(), NOW())');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':default_command_template' => $defaultCommandTemplate,
            ':status' => $status,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $description, string $defaultCommandTemplate, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE redeem_categories SET name = :name, description = :description, default_command_template = :default_command_template, status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':description' => $description,
            ':default_command_template' => $defaultCommandTemplate,
            ':status' => $status,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countKeysByCategory(int $categoryId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM redeem_keys WHERE category_id = :category_id');
        $stmt->execute([':category_id' => $categoryId]);
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM redeem_categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
