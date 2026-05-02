<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class Feedback extends Model
{
    private const ALLOWED_STATUSES = [
        'pending',
        'reviewing',
        'need_more_info',
        'resolved',
        'rejected',
    ];

    private const ALLOWED_CATEGORIES = [
        'report',
        'bug',
        'account',
        'suggestion',
        'other',
    ];

    private const MAX_ATTACHMENT_COUNT = 3;
    private const MAX_ATTACHMENT_BYTES = 5242880; // 5MB
    private ?bool $supplementColumnsReady = null;

    /**
     * @return string[]
     */
    public static function allowedStatuses(): array
    {
        return self::ALLOWED_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function allowedCategories(): array
    {
        return self::ALLOWED_CATEGORIES;
    }

    public function createFeedback(array $data, array $files = []): int
    {
        $category = strtolower(trim((string)($data['category'] ?? 'other')));
        if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $category = 'other';
        }

        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '' || $content === '') {
            throw new \RuntimeException('反馈标题和内容不能为空。');
        }

        $attachments = $this->validateAndMoveAttachments($files);

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('
                INSERT INTO player_feedback (
                    user_id,
                    username,
                    mc_username,
                    category,
                    target_player,
                    title,
                    content,
                    world,
                    coordinates,
                    occurred_at,
                    evidence_url,
                    status,
                    created_ip,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :username,
                    :mc_username,
                    :category,
                    :target_player,
                    :title,
                    :content,
                    :world,
                    :coordinates,
                    :occurred_at,
                    :evidence_url,
                    :status,
                    :created_ip,
                    NOW(),
                    NOW()
                )
            ');
            $stmt->execute([
                ':user_id' => (int)($data['user_id'] ?? 0),
                ':username' => $this->limitText((string)($data['username'] ?? ''), 64),
                ':mc_username' => $this->toNullable($this->limitText((string)($data['mc_username'] ?? ''), 64)),
                ':category' => $category,
                ':target_player' => $this->toNullable($this->limitText((string)($data['target_player'] ?? ''), 64)),
                ':title' => $this->limitText($title, 120),
                ':content' => $this->limitText($content, 5000),
                ':world' => $this->toNullable($this->limitText((string)($data['world'] ?? ''), 64)),
                ':coordinates' => $this->toNullable($this->limitText((string)($data['coordinates'] ?? ''), 64)),
                ':occurred_at' => $this->toNullable((string)($data['occurred_at'] ?? '')),
                ':evidence_url' => $this->toNullable($this->limitText((string)($data['evidence_url'] ?? ''), 500)),
                ':status' => 'pending',
                ':created_ip' => $this->toNullable($this->limitText((string)($data['created_ip'] ?? ''), 64)),
            ]);

            $feedbackId = (int)$this->db->lastInsertId();

            $this->saveAttachments($feedbackId, $attachments);

            $this->db->commit();
            return $feedbackId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getUserFeedbackList(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare('
            SELECT
                ' . $this->feedbackSelectColumnsSql() . '
            FROM player_feedback
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn(array $row): array => $this->normalizeFeedbackRow($row), $rows);
    }

    public function getUserFeedbackById(int $feedbackId, int $userId): ?array
    {
        if ($feedbackId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('
            SELECT
                ' . $this->feedbackSelectColumnsSql() . '
            FROM player_feedback
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ');
        $stmt->bindValue(':id', $feedbackId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeFeedbackRow($row);
    }

    public function appendUserSupplement(int $feedbackId, int $userId, string $content, array $files = []): bool
    {
        if ($feedbackId <= 0 || $userId <= 0) {
            return false;
        }

        if (!$this->feedbackSupplementColumnsAvailable()) {
            throw new \RuntimeException('反馈系统需要数据库升级，请执行数据库迁移后再提交补充材料。');
        }

        $supplementContent = trim($content);
        if ($supplementContent === '') {
            throw new \RuntimeException('补充说明不能为空。');
        }
        if (mb_strlen($supplementContent, 'UTF-8') > 5000) {
            $supplementContent = mb_substr($supplementContent, 0, 5000, 'UTF-8');
        }

        $feedback = $this->getUserFeedbackById($feedbackId, $userId);
        if (!$feedback) {
            return false;
        }
        $status = strtolower(trim((string)($feedback['status'] ?? '')));
        if ($status !== 'need_more_info') {
            return false;
        }

        $attachments = $this->validateAndMoveAttachments($files);
        $timestamp = date('Y-m-d H:i:s');
        $supplementBlock = '[' . $timestamp . ' 用户补充]' . "\n" . $supplementContent;
        $existingSupplement = trim((string)($feedback['user_supplement'] ?? ''));
        $nextSupplement = $existingSupplement === ''
            ? $supplementBlock
            : ($existingSupplement . "\n\n---\n" . $supplementBlock);

        try {
            $this->db->beginTransaction();

            $updateStmt = $this->db->prepare('
                UPDATE player_feedback
                SET
                    user_supplement = :user_supplement,
                    supplemented_at = NOW(),
                    status = :next_status,
                    updated_at = NOW()
                WHERE id = :id AND user_id = :user_id AND status = :current_status
                LIMIT 1
            ');
            $updateStmt->bindValue(':user_supplement', $nextSupplement, PDO::PARAM_STR);
            $updateStmt->bindValue(':next_status', 'reviewing', PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $feedbackId, PDO::PARAM_INT);
            $updateStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $updateStmt->bindValue(':current_status', 'need_more_info', PDO::PARAM_STR);
            $updateStmt->execute();

            if ($updateStmt->rowCount() <= 0) {
                $this->db->rollBack();
                return false;
            }

            $this->saveAttachments($feedbackId, $attachments);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getAdminFeedbackList(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $whereSql = $this->buildAdminWhereClause($filters, $params);
        $sql = '
            SELECT
                ' . $this->feedbackSelectColumnsSql() . '
            FROM player_feedback
            ' . $whereSql . '
            ORDER BY created_at DESC, id DESC
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = array_map(fn(array $row): array => $this->normalizeFeedbackRow($row), $rows);
        $this->appendAttachments($rows);

        return $rows;
    }

    public function countAdminFeedbackList(array $filters): int
    {
        $params = [];
        $whereSql = $this->buildAdminWhereClause($filters, $params);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM player_feedback ' . $whereSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getFeedbackDetail(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                ' . $this->feedbackSelectColumnsSql() . '
            FROM player_feedback
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->normalizeFeedbackRow($row);
    }

    public function getFeedbackAttachments(int $feedbackId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                feedback_id,
                file_path,
                original_name,
                mime_type,
                file_size,
                created_at
            FROM player_feedback_attachments
            WHERE feedback_id = :feedback_id
            ORDER BY id ASC
        ');
        $stmt->bindValue(':feedback_id', $feedbackId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'feedback_id' => (int)($row['feedback_id'] ?? 0),
                'file_path' => (string)($row['file_path'] ?? ''),
                'original_name' => (string)($row['original_name'] ?? ''),
                'mime_type' => (string)($row['mime_type'] ?? ''),
                'file_size' => (int)($row['file_size'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    public function updateFeedbackStatus(int $id, string $status, string $adminReply, int $adminId): bool
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return false;
        }

        $reply = trim($adminReply);
        if (mb_strlen($reply, 'UTF-8') > 5000) {
            $reply = mb_substr($reply, 0, 5000, 'UTF-8');
        }

        $stmt = $this->db->prepare('
            UPDATE player_feedback
            SET
                status = :status,
                admin_reply = :admin_reply,
                handled_by = :handled_by,
                handled_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        if ($reply === '') {
            $stmt->bindValue(':admin_reply', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':admin_reply', $reply, PDO::PARAM_STR);
        }
        $stmt->bindValue(':handled_by', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    private function saveAttachments(int $feedbackId, array $attachments): void
    {
        if ($feedbackId <= 0 || $attachments === []) {
            return;
        }

        $attachStmt = $this->db->prepare('
            INSERT INTO player_feedback_attachments (
                feedback_id,
                file_path,
                original_name,
                mime_type,
                file_size,
                created_at
            ) VALUES (
                :feedback_id,
                :file_path,
                :original_name,
                :mime_type,
                :file_size,
                NOW()
            )
        ');

        foreach ($attachments as $attachment) {
            $attachStmt->execute([
                ':feedback_id' => $feedbackId,
                ':file_path' => (string)($attachment['file_path'] ?? ''),
                ':original_name' => $this->toNullable($this->limitText((string)($attachment['original_name'] ?? ''), 255)),
                ':mime_type' => $this->limitText((string)($attachment['mime_type'] ?? ''), 100),
                ':file_size' => (int)($attachment['file_size'] ?? 0),
            ]);
        }
    }

    private function feedbackSelectColumnsSql(): string
    {
        $supplementColumns = $this->feedbackSupplementColumnsAvailable()
            ? 'user_supplement, supplemented_at'
            : 'NULL AS user_supplement, NULL AS supplemented_at';

        return '
                id,
                user_id,
                username,
                mc_username,
                category,
                target_player,
                title,
                content,
                world,
                coordinates,
                occurred_at,
                evidence_url,
                status,
                admin_reply,
                ' . $supplementColumns . ',
                handled_by,
                handled_at,
                created_ip,
                created_at,
                updated_at
        ';
    }

    private function feedbackSupplementColumnsAvailable(): bool
    {
        if ($this->supplementColumnsReady !== null) {
            return $this->supplementColumnsReady;
        }

        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) AS c
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'player_feedback'
                  AND COLUMN_NAME IN ('user_supplement', 'supplemented_at')
            ");
            $count = (int)$stmt->fetchColumn();
            $this->supplementColumnsReady = ($count >= 2);
        } catch (\Throwable $e) {
            $this->supplementColumnsReady = false;
        }

        return $this->supplementColumnsReady;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function appendAttachments(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $feedbackIds = [];
        foreach ($rows as $row) {
            $feedbackIds[] = (int)($row['id'] ?? 0);
        }
        $feedbackIds = array_values(array_unique(array_filter($feedbackIds, static fn(int $id): bool => $id > 0)));

        if ($feedbackIds === []) {
            foreach ($rows as &$row) {
                $row['attachments'] = [];
            }
            unset($row);
            return;
        }

        $placeholders = [];
        foreach ($feedbackIds as $index => $feedbackId) {
            $placeholders[':f' . $index] = $feedbackId;
        }

        $sql = '
            SELECT
                id,
                feedback_id,
                file_path,
                original_name,
                mime_type,
                file_size,
                created_at
            FROM player_feedback_attachments
            WHERE feedback_id IN (' . implode(', ', array_keys($placeholders)) . ')
            ORDER BY id ASC
        ';
        $stmt = $this->db->prepare($sql);
        foreach ($placeholders as $key => $feedbackId) {
            $stmt->bindValue($key, $feedbackId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $attachmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($attachmentRows as $attachmentRow) {
            $feedbackId = (int)($attachmentRow['feedback_id'] ?? 0);
            if ($feedbackId <= 0) {
                continue;
            }
            if (!isset($grouped[$feedbackId])) {
                $grouped[$feedbackId] = [];
            }
            $grouped[$feedbackId][] = [
                'id' => (int)($attachmentRow['id'] ?? 0),
                'feedback_id' => $feedbackId,
                'file_path' => (string)($attachmentRow['file_path'] ?? ''),
                'original_name' => (string)($attachmentRow['original_name'] ?? ''),
                'mime_type' => (string)($attachmentRow['mime_type'] ?? ''),
                'file_size' => (int)($attachmentRow['file_size'] ?? 0),
                'created_at' => (string)($attachmentRow['created_at'] ?? ''),
            ];
        }

        foreach ($rows as &$row) {
            $feedbackId = (int)($row['id'] ?? 0);
            $row['attachments'] = $grouped[$feedbackId] ?? [];
        }
        unset($row);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, string> $params
     */
    private function buildAdminWhereClause(array $filters, array &$params): string
    {
        $conditions = [];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            if (mb_strlen($q, 'UTF-8') > 120) {
                $q = mb_substr($q, 0, 120, 'UTF-8');
            }
            $params[':q'] = '%' . $q . '%';
            $conditions[] = '(username LIKE :q OR mc_username LIKE :q OR target_player LIKE :q OR title LIKE :q OR content LIKE :q)';
        }

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
            $params[':status'] = $status;
            $conditions[] = 'status = :status';
        }

        $category = strtolower(trim((string)($filters['category'] ?? '')));
        if ($category !== '' && in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $params[':category'] = $category;
            $conditions[] = 'category = :category';
        }

        if ($conditions === []) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * @param array<string, mixed> $files
     * @return array<int, array<string, mixed>>
     */
    private function validateAndMoveAttachments(array $files): array
    {
        $normalizedFiles = $this->normalizeUploadFiles($files);
        if ($normalizedFiles === []) {
            return [];
        }

        if (count($normalizedFiles) > self::MAX_ATTACHMENT_COUNT) {
            throw new \RuntimeException('最多只能上传 3 张图片。');
        }

        if (!class_exists(\finfo::class)) {
            throw new \RuntimeException('服务器缺少文件类型检测扩展。');
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimeExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $year = date('Y');
        $month = date('m');
        $relativeDir = '/uploads/feedback/' . $year . '/' . $month;
        $absoluteDir = rtrim((string)PUBLIC_PATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'feedback' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('上传目录创建失败，请稍后重试。');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $attachments = [];

        foreach ($normalizedFiles as $index => $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片上传失败：' . $this->uploadErrorMessage($error));
            }

            $size = max(0, (int)($file['size'] ?? 0));
            if ($size > self::MAX_ATTACHMENT_BYTES) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片超过 5MB 限制。');
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            $originalName = trim((string)($file['name'] ?? ''));
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片无效，请重新上传。');
            }

            $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片格式不支持，仅允许 jpg/jpeg/png/webp。');
            }

            $mime = strtolower((string)$finfo->file($tmpName));
            if (!isset($allowedMimeExt[$mime])) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片类型不安全，请更换图片。');
            }

            $imageMeta = @getimagesize($tmpName);
            if (!is_array($imageMeta)) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张文件不是有效图片。');
            }

            $finalExt = $allowedMimeExt[$mime];
            $safeFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $finalExt;
            $absoluteFile = $absoluteDir . DIRECTORY_SEPARATOR . $safeFilename;
            $relativeFile = $relativeDir . '/' . $safeFilename;

            if (!move_uploaded_file($tmpName, $absoluteFile)) {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 张图片保存失败，请稍后重试。');
            }

            $attachments[] = [
                'file_path' => $relativeFile,
                'original_name' => $this->limitText($originalName, 255),
                'mime_type' => $mime,
                'file_size' => $size,
            ];
        }

        return $attachments;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUploadFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        $normalized = [];
        if (is_array($files['name'])) {
            $total = count($files['name']);
            for ($i = 0; $i < $total; $i++) {
                $normalized[] = [
                    'name' => (string)($files['name'][$i] ?? ''),
                    'type' => (string)($files['type'][$i] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                    'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($files['size'][$i] ?? 0),
                ];
            }
        } else {
            $normalized[] = [
                'name' => (string)($files['name'] ?? ''),
                'type' => (string)($files['type'] ?? ''),
                'tmp_name' => (string)($files['tmp_name'] ?? ''),
                'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'] ?? 0),
            ];
        }

        return array_values(array_filter($normalized, static function (array $file): bool {
            return (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件大小超限',
            UPLOAD_ERR_PARTIAL => '文件上传不完整',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '服务器写入失败',
            UPLOAD_ERR_EXTENSION => '文件被系统拦截',
            default => '未知错误',
        };
    }

    private function limitText(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($maxLength <= 0) {
            return '';
        }
        if (mb_strlen($value, 'UTF-8') <= $maxLength) {
            return $value;
        }
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function toNullable(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeFeedbackRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'username' => (string)($row['username'] ?? ''),
            'mc_username' => (string)($row['mc_username'] ?? ''),
            'category' => (string)($row['category'] ?? 'other'),
            'target_player' => (string)($row['target_player'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
            'world' => (string)($row['world'] ?? ''),
            'coordinates' => (string)($row['coordinates'] ?? ''),
            'occurred_at' => (string)($row['occurred_at'] ?? ''),
            'evidence_url' => (string)($row['evidence_url'] ?? ''),
            'status' => (string)($row['status'] ?? 'pending'),
            'admin_reply' => (string)($row['admin_reply'] ?? ''),
            'user_supplement' => (string)($row['user_supplement'] ?? ''),
            'supplemented_at' => (string)($row['supplemented_at'] ?? ''),
            'handled_by' => isset($row['handled_by']) && $row['handled_by'] !== null ? (int)$row['handled_by'] : null,
            'handled_at' => (string)($row['handled_at'] ?? ''),
            'created_ip' => (string)($row['created_ip'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
