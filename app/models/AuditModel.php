<?php
declare(strict_types=1);

namespace Model;

use Core\ApiResponse;
use Core\Model;
use Core\SensitiveDataSanitizer;
use PDO;

class AuditModel extends Model
{
    public function logAction(?int $userId, string $action, string $ipAddress, array $details = []): void
    {
        $sanitizedDetails = SensitiveDataSanitizer::sanitize($details);
        if (!is_array($sanitizedDetails)) {
            $sanitizedDetails = [];
        }

        $requestId = ApiResponse::ensureRequestIdHeader();
        $storageMode = $this->resolveStorageMode();
        $record = [
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ipAddress,
            'details' => $sanitizedDetails,
            'request_id' => $requestId,
        ];

        if ($storageMode === 'file') {
            $this->writeFileLog($record);
            return;
        }

        $mysqlWritten = $this->writeMysqlLog($record);
        if ($storageMode === 'both') {
            $this->writeFileLog($record);
            return;
        }

        if (!$mysqlWritten) {
            return;
        }
    }

    /**
     * @param array<string,mixed> $record
     */
    private function writeMysqlLog(array $record): bool
    {
        $detailsJson = null;
        if (!empty($record['details']) && is_array($record['details'])) {
            $encoded = json_encode($record['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $detailsJson = ($encoded === false) ? null : $encoded;
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO audit_logs (user_id, action, ip_address, details, request_id, created_at)
                VALUES (:user_id, :action, :ip_address, :details, :request_id, NOW())
            ');
            $stmt->bindValue(':user_id', $record['user_id'], $record['user_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':action', (string)$record['action'], PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', (string)$record['ip_address'], PDO::PARAM_STR);
            $stmt->bindValue(':details', $detailsJson, $detailsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':request_id', (string)($record['request_id'] ?? ''), PDO::PARAM_STR);
            $stmt->execute();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $record
     */
    private function writeFileLog(array $record): void
    {
        try {
            $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'audit';
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $filePath = $directory . DIRECTORY_SEPARATOR . 'runtime-audit-' . date('Y-m') . '.jsonl';
            $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                return;
            }

            @file_put_contents($filePath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    private function resolveStorageMode(): string
    {
        static $cachedMode = null;
        if (is_string($cachedMode)) {
            return $cachedMode;
        }

        $mode = strtolower(trim((string)defined('DEFAULT_AUDIT_LOG_STORAGE') ? DEFAULT_AUDIT_LOG_STORAGE : 'mysql'));

        try {
            $stmt = $this->db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute([':key' => 'audit_log_storage']);
            $value = $stmt->fetchColumn();
            if (is_string($value) && $value !== '') {
                $mode = strtolower(trim($value));
            }
        } catch (\Throwable $e) {
        }

        if (!in_array($mode, ['mysql', 'file', 'both'], true)) {
            $mode = 'mysql';
        }

        $cachedMode = $mode;
        return $cachedMode;
    }
}

