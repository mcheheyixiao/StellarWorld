<?php
declare(strict_types=1);

namespace Model;

use Core\MinecraftUuid;
use Core\Model;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;

class Checkin extends Model
{
    private const USERNAME_PATTERN = '/^[a-zA-Z0-9_]{1,16}$/';
    private const DELIVERY_STATUSES = ['pending', 'delivering', 'delivered', 'failed', 'cancelled'];
    private const DELIVERY_LOCK_TIMEOUT_MINUTES = 10;
    private const MAX_PLUGIN_LIMIT = 50;

    private static bool $schemaReady = false;
    private static bool $legacySynced = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    public function getStatusForUser(int $userId): array
    {
        $user = $this->getUserIdentity($userId);
        if ($user === null) {
            return $this->emptyStatus();
        }

        $today = $this->now()->format('Y-m-d');
        $monthKey = substr($today, 0, 7);
        $bound = $this->isBoundIdentity($user['mc_username'], $user['mc_uuid']);
        $todayRecord = $this->findRecordByUserAndDate($userId, $today);
        $latestRecord = $this->findLatestRecord($userId);

        $monthDays = $todayRecord !== null
            ? (int)$todayRecord['month_days']
            : $this->countMonthDays($userId, $monthKey);
        $totalDays = $todayRecord !== null
            ? (int)$todayRecord['total_days']
            : $this->countTotalDays($userId);

        $streakDays = 0;
        if ($todayRecord !== null) {
            $streakDays = (int)$todayRecord['streak_days'];
        } elseif ($latestRecord !== null && ($latestRecord['checkin_date'] ?? '') === $this->dateOffset($today, -1)) {
            $streakDays = (int)$latestRecord['streak_days'];
        }

        $reward = null;
        if ($bound) {
            if ($todayRecord !== null) {
                $reward = $this->decodeJsonObject($todayRecord['reward_snapshot_json'] ?? null);
            } else {
                $reward = $this->buildRewardSnapshot(
                    $user['mc_username'],
                    $user['mc_uuid'],
                    $monthDays + 1,
                    $this->now()
                );
            }
        }

        return [
            'logged_in' => true,
            'bound_mc' => $bound,
            'checked_in_today' => $todayRecord !== null,
            'streak_days' => $streakDays,
            'month_days' => $monthDays,
            'total_days' => $totalDays,
            'today_reward' => $reward,
            'latest_delivery_status' => $todayRecord !== null
                ? (string)($todayRecord['delivery_status'] ?? 'pending')
                : $this->findLatestDeliveryStatus($userId),
            'today_date' => $today,
            'month_key' => $monthKey,
            'mc_username' => $bound ? $user['mc_username'] : null,
            'mc_uuid' => $bound ? $user['mc_uuid'] : null,
        ];
    }

    public function getHistoryForUser(int $userId, int $limit = 30): array
    {
        $limit = max(1, min(30, $limit));
        $stmt = $this->db->prepare('
            SELECT
                id,
                checkin_date,
                streak_days,
                month_days,
                total_days,
                reward_snapshot_json,
                delivery_status,
                created_at
            FROM checkin_records
            WHERE user_id = :user_id
            ORDER BY checkin_date DESC, id DESC
            LIMIT ' . $limit
        );
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'checkin_date' => (string)$row['checkin_date'],
                'streak_days' => (int)$row['streak_days'],
                'month_days' => (int)$row['month_days'],
                'total_days' => (int)$row['total_days'],
                'delivery_status' => (string)$row['delivery_status'],
                'created_at' => (string)$row['created_at'],
                'reward' => $this->decodeJsonObject($row['reward_snapshot_json'] ?? null),
            ];
        }, $rows);
    }

    public function getRewardRulesForCurrentMonth(): array
    {
        $rows = $this->fetchRewardRules();
        $daily = [];
        $monthly = [];

        foreach ($rows as $row) {
            if ((string)$row['scope'] === 'daily') {
                $daily[] = $row;
                continue;
            }
            $monthly[] = $row;
        }

        return [
            'month_key' => $this->now()->format('Y-m'),
            'daily' => $daily,
            'monthly' => $monthly,
        ];
    }

    public function claimForUser(int $userId, string $ip, string $userAgent): array
    {
        $user = $this->getUserIdentity($userId, true);
        if ($user === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'User not found'];
        }

        if (!$this->isBoundIdentity($user['mc_username'], $user['mc_uuid'])) {
            return ['ok' => false, 'status' => 400, 'message' => 'Minecraft account is not bound'];
        }

        $now = $this->now();
        $checkinDate = $now->format('Y-m-d');
        $monthKey = $now->format('Y-m');
        $ipHash = hash('sha256', trim($ip));
        $userAgentHash = hash('sha256', trim($userAgent));

        try {
            $this->db->beginTransaction();

            $existing = $this->findRecordByUserAndDate($userId, $checkinDate, true);
            if ($existing !== null) {
                $this->db->rollBack();
                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'Already checked in today',
                    'record' => $this->formatRecordSummary($existing),
                ];
            }

            $yesterdayDate = $this->dateOffset($checkinDate, -1);
            $yesterday = $this->findRecordByUserAndDate($userId, $yesterdayDate, true);
            $streakDays = $yesterday !== null ? ((int)$yesterday['streak_days'] + 1) : 1;
            $monthDays = $this->countMonthDays($userId, $monthKey, true) + 1;
            $totalDays = $this->countTotalDays($userId, true) + 1;

            $snapshot = $this->buildRewardSnapshot(
                $user['mc_username'],
                $user['mc_uuid'],
                $monthDays,
                $now
            );
            $snapshotJson = $this->encodeJson($snapshot);
            $nowString = $now->format('Y-m-d H:i:s');

            $stmt = $this->db->prepare('
                INSERT INTO checkin_records (
                    user_id,
                    mc_uuid,
                    username,
                    checkin_date,
                    checkin_time,
                    month_key,
                    streak_days,
                    month_days,
                    total_days,
                    reward_snapshot_json,
                    delivery_status,
                    ip_hash,
                    user_agent_hash,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :mc_uuid,
                    :username,
                    :checkin_date,
                    :checkin_time,
                    :month_key,
                    :streak_days,
                    :month_days,
                    :total_days,
                    :reward_snapshot_json,
                    :delivery_status,
                    :ip_hash,
                    :user_agent_hash,
                    :created_at,
                    :updated_at
                )
            ');
            $stmt->execute([
                ':user_id' => $userId,
                ':mc_uuid' => $user['mc_uuid'],
                ':username' => $user['mc_username'],
                ':checkin_date' => $checkinDate,
                ':checkin_time' => $nowString,
                ':month_key' => $monthKey,
                ':streak_days' => $streakDays,
                ':month_days' => $monthDays,
                ':total_days' => $totalDays,
                ':reward_snapshot_json' => $snapshotJson,
                ':delivery_status' => 'pending',
                ':ip_hash' => $ipHash,
                ':user_agent_hash' => $userAgentHash,
                ':created_at' => $nowString,
                ':updated_at' => $nowString,
            ]);

            $recordId = (int)$this->db->lastInsertId();

            $legacyStmt = $this->db->prepare('
                INSERT INTO user_checkins (user_id, checkin_date, created_at)
                VALUES (:user_id, :checkin_date, :created_at)
                ON DUPLICATE KEY UPDATE created_at = created_at
            ');
            $legacyStmt->execute([
                ':user_id' => $userId,
                ':checkin_date' => $checkinDate,
                ':created_at' => $nowString,
            ]);

            $deliveryStmt = $this->db->prepare('
                INSERT INTO checkin_reward_deliveries (
                    record_id,
                    user_id,
                    mc_uuid,
                    username,
                    reward_payload_json,
                    delivery_mode,
                    status,
                    attempts,
                    last_error,
                    locked_at,
                    delivered_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :record_id,
                    :user_id,
                    :mc_uuid,
                    :username,
                    :reward_payload_json,
                    :delivery_mode,
                    :status,
                    0,
                    NULL,
                    NULL,
                    NULL,
                    :created_at,
                    :updated_at
                )
            ');
            $deliveryStmt->execute([
                ':record_id' => $recordId,
                ':user_id' => $userId,
                ':mc_uuid' => $user['mc_uuid'],
                ':username' => $user['mc_username'],
                ':reward_payload_json' => $snapshotJson,
                ':delivery_mode' => 'plugin_poll',
                ':status' => 'pending',
                ':created_at' => $nowString,
                ':updated_at' => $nowString,
            ]);

            $deliveryId = (int)$this->db->lastInsertId();

            $this->db->commit();

            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Check-in successful',
                'record' => [
                    'id' => $recordId,
                    'checkin_date' => $checkinDate,
                    'streak_days' => $streakDays,
                    'month_days' => $monthDays,
                    'total_days' => $totalDays,
                    'delivery_status' => 'pending',
                    'reward' => $snapshot,
                ],
                'delivery' => [
                    'id' => $deliveryId,
                    'status' => 'pending',
                    'mode' => 'plugin_poll',
                ],
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($this->isDuplicateEntry($e)) {
                return ['ok' => false, 'status' => 409, 'message' => 'Already checked in today'];
            }

            throw $e;
        }
    }

    public function pullPendingDeliveries(int $limit): array
    {
        $limit = max(1, min(self::MAX_PLUGIN_LIMIT, $limit));
        $this->recoverExpiredDeliveries();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                SELECT
                    id,
                    record_id,
                    mc_uuid,
                    username,
                    reward_payload_json,
                    status,
                    attempts,
                    created_at
                FROM checkin_reward_deliveries
                WHERE status IN (\'pending\', \'failed\')
                  AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL ' . self::DELIVERY_LOCK_TIMEOUT_MINUTES . ' MINUTE))
                ORDER BY
                    CASE WHEN status = \'pending\' THEN 0 ELSE 1 END ASC,
                    created_at ASC,
                    id ASC
                LIMIT ' . $limit . '
                FOR UPDATE
            ');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($rows === []) {
                $this->db->commit();
                return [];
            }

            $deliveryIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
            $recordIds = array_map(static fn(array $row): int => (int)$row['record_id'], $rows);
            $nowString = $this->now()->format('Y-m-d H:i:s');

            $inDeliveries = implode(',', array_fill(0, count($deliveryIds), '?'));
            $update = $this->db->prepare('
                UPDATE checkin_reward_deliveries
                SET status = \'delivering\',
                    locked_at = ?,
                    updated_at = ?
                WHERE id IN (' . $inDeliveries . ')
            ');
            $update->execute(array_merge([$nowString, $nowString], $deliveryIds));

            $inRecords = implode(',', array_fill(0, count($recordIds), '?'));
            $updateRecords = $this->db->prepare('
                UPDATE checkin_records
                SET delivery_status = \'delivering\',
                    updated_at = ?
                WHERE id IN (' . $inRecords . ')
            ');
            $updateRecords->execute(array_merge([$nowString], $recordIds));

            $this->db->commit();

            return array_map(function (array $row): array {
                return [
                    'delivery_id' => (int)$row['id'],
                    'record_id' => (int)$row['record_id'],
                    'status' => 'delivering',
                    'attempts' => (int)$row['attempts'],
                    'player' => [
                        'username' => (string)$row['username'],
                        'uuid' => (string)$row['mc_uuid'],
                    ],
                    'reward' => $this->decodeJsonObject($row['reward_payload_json'] ?? null),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function ackDelivery(int $deliveryId, bool $success, string $message): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('
                SELECT
                    d.*,
                    r.reward_snapshot_json
                FROM checkin_reward_deliveries d
                INNER JOIN checkin_records r ON r.id = d.record_id
                WHERE d.id = :id
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([':id' => $deliveryId]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$delivery) {
                $this->db->rollBack();
                return ['ok' => false, 'status' => 404, 'message' => 'Delivery not found'];
            }

            $currentStatus = (string)($delivery['status'] ?? '');
            if ($currentStatus === 'delivered') {
                $this->db->commit();
                return [
                    'ok' => true,
                    'status' => 200,
                    'message' => 'Delivery already acknowledged',
                    'delivery' => $this->formatDeliverySummary($delivery),
                ];
            }

            $nowString = $this->now()->format('Y-m-d H:i:s');

            if ($success) {
                $reward = $this->decodeJsonObject($delivery['reward_snapshot_json'] ?? null);
                $coins = max(0, (int)($reward['coins'] ?? 0));

                $update = $this->db->prepare('
                    UPDATE checkin_reward_deliveries
                    SET status = \'delivered\',
                        locked_at = NULL,
                        last_error = NULL,
                        delivered_at = COALESCE(delivered_at, :delivered_at),
                        updated_at = :updated_at
                    WHERE id = :id
                ');
                $update->execute([
                    ':delivered_at' => $nowString,
                    ':updated_at' => $nowString,
                    ':id' => $deliveryId,
                ]);

                $updateRecord = $this->db->prepare('
                    UPDATE checkin_records
                    SET delivery_status = \'delivered\',
                        updated_at = :updated_at
                    WHERE id = :id
                ');
                $updateRecord->execute([
                    ':updated_at' => $nowString,
                    ':id' => (int)$delivery['record_id'],
                ]);

                if ($coins > 0) {
                    $updateCoins = $this->db->prepare('
                        UPDATE users
                        SET coins = coins + :coins,
                            updated_at = NOW()
                        WHERE id = :id
                    ');
                    $updateCoins->execute([
                        ':coins' => $coins,
                        ':id' => (int)$delivery['user_id'],
                    ]);
                }

                $this->db->commit();

                return [
                    'ok' => true,
                    'status' => 200,
                    'message' => 'Delivery acknowledged',
                    'delivery' => [
                        'id' => $deliveryId,
                        'status' => 'delivered',
                        'delivered_at' => $nowString,
                    ],
                ];
            }

            $safeMessage = mb_substr(trim($message), 0, 255);
            if ($safeMessage === '') {
                $safeMessage = 'Plugin reported delivery failure';
            }

            $update = $this->db->prepare('
                UPDATE checkin_reward_deliveries
                SET status = \'failed\',
                    attempts = attempts + 1,
                    last_error = :last_error,
                    locked_at = NULL,
                    updated_at = :updated_at
                WHERE id = :id
            ');
            $update->execute([
                ':last_error' => $safeMessage,
                ':updated_at' => $nowString,
                ':id' => $deliveryId,
            ]);

            $updateRecord = $this->db->prepare('
                UPDATE checkin_records
                SET delivery_status = \'failed\',
                    updated_at = :updated_at
                WHERE id = :id
            ');
            $updateRecord->execute([
                ':updated_at' => $nowString,
                ':id' => (int)$delivery['record_id'],
            ]);

            $this->db->commit();

            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Delivery marked as failed',
                'delivery' => [
                    'id' => $deliveryId,
                    'status' => 'failed',
                    'last_error' => $safeMessage,
                ],
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getAdminLogs(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare('
            SELECT
                username,
                checkin_date,
                streak_days,
                month_days,
                total_days,
                delivery_status,
                created_at
            FROM checkin_records
            ORDER BY checkin_date DESC, id DESC
            LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAdminStats(): array
    {
        $today = $this->now()->format('Y-m-d');
        $yesterday = $this->dateOffset($today, -1);
        $monthKey = substr($today, 0, 7);

        return [
            'today_count' => $this->countByDate($today),
            'yesterday_count' => $this->countByDate($yesterday),
            'month_total' => $this->countByMonth($monthKey),
            'pending_deliveries' => $this->countDeliveriesByStatus('pending'),
            'failed_deliveries' => $this->countDeliveriesByStatus('failed'),
            'delivered_deliveries' => $this->countDeliveriesByStatus('delivered'),
            'top_streaks' => $this->fetchTopStreaks(),
        ];
    }

    public function getAdminRewardRules(): array
    {
        return $this->getRewardRulesForCurrentMonth();
    }

    public function saveRewardRule(array $input): array
    {
        $scope = trim((string)($input['scope'] ?? ''));
        if (!in_array($scope, ['daily', 'monthly'], true)) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid rule scope'];
        }

        $day = (int)($input['day'] ?? 0);
        if ($scope === 'daily') {
            $day = 1;
        }
        if ($day < 1 || $day > 31) {
            return ['ok' => false, 'status' => 400, 'message' => 'Invalid reward day'];
        }

        $coins = (int)($input['coins'] ?? 0);
        $points = (int)($input['points'] ?? 0);
        $enabled = !empty($input['enabled']) ? 1 : 0;

        $items = $this->normalizeItemRules($input['items_json'] ?? '[]');
        if ($items === null) {
            return ['ok' => false, 'status' => 400, 'message' => 'items_json must be a JSON array'];
        }

        $commands = $this->normalizeCommandRules($input['commands_json'] ?? '[]');
        if ($commands === null) {
            return ['ok' => false, 'status' => 400, 'message' => 'commands_json must be a JSON array with only {player} or {uuid} placeholders'];
        }

        $id = (int)($input['id'] ?? 0);
        $nowString = $this->now()->format('Y-m-d H:i:s');

        if ($id > 0) {
            $stmt = $this->db->prepare('
                UPDATE checkin_reward_rules
                SET day = :day,
                    scope = :scope,
                    coins = :coins,
                    points = :points,
                    items_json = :items_json,
                    commands_json = :commands_json,
                    enabled = :enabled,
                    updated_at = :updated_at
                WHERE id = :id
            ');
            $stmt->execute([
                ':day' => $day,
                ':scope' => $scope,
                ':coins' => $coins,
                ':points' => $points,
                ':items_json' => $this->encodeJson($items),
                ':commands_json' => $this->encodeJson($commands),
                ':enabled' => $enabled,
                ':updated_at' => $nowString,
                ':id' => $id,
            ]);
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO checkin_reward_rules (
                    day,
                    scope,
                    coins,
                    points,
                    items_json,
                    commands_json,
                    enabled,
                    created_at,
                    updated_at
                ) VALUES (
                    :day,
                    :scope,
                    :coins,
                    :points,
                    :items_json,
                    :commands_json,
                    :enabled,
                    :created_at,
                    :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    coins = VALUES(coins),
                    points = VALUES(points),
                    items_json = VALUES(items_json),
                    commands_json = VALUES(commands_json),
                    enabled = VALUES(enabled),
                    updated_at = VALUES(updated_at)
            ');
            $stmt->execute([
                ':day' => $day,
                ':scope' => $scope,
                ':coins' => $coins,
                ':points' => $points,
                ':items_json' => $this->encodeJson($items),
                ':commands_json' => $this->encodeJson($commands),
                ':enabled' => $enabled,
                ':created_at' => $nowString,
                ':updated_at' => $nowString,
            ]);
            $id = (int)$this->db->lastInsertId();
            if ($id <= 0) {
                $existing = $this->findRewardRuleByScopeAndDay($scope, $day);
                $id = $existing !== null ? (int)$existing['id'] : 0;
            }
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Reward rule saved',
            'rule_id' => $id,
        ];
    }

    private function ensureSchema(): void
    {
        if (!self::$schemaReady) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS checkin_records (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    mc_uuid VARCHAR(64) NOT NULL,
                    username VARCHAR(64) NOT NULL,
                    checkin_date DATE NOT NULL,
                    checkin_time DATETIME NOT NULL,
                    month_key CHAR(7) NOT NULL,
                    streak_days INT UNSIGNED NOT NULL DEFAULT 1,
                    month_days INT UNSIGNED NOT NULL DEFAULT 1,
                    total_days INT UNSIGNED NOT NULL DEFAULT 1,
                    reward_snapshot_json JSON NOT NULL,
                    delivery_status ENUM('pending', 'delivering', 'delivered', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                    ip_hash CHAR(64) NOT NULL DEFAULT '',
                    user_agent_hash CHAR(64) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    CONSTRAINT fk_checkin_records_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY uq_checkin_records_user_date (user_id, checkin_date),
                    KEY idx_checkin_records_month_user (month_key, user_id),
                    KEY idx_checkin_records_delivery_status (delivery_status),
                    KEY idx_checkin_records_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS checkin_reward_rules (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    day TINYINT UNSIGNED NOT NULL,
                    scope ENUM('daily', 'monthly') NOT NULL,
                    coins INT NOT NULL DEFAULT 0,
                    points INT NOT NULL DEFAULT 0,
                    items_json JSON NULL,
                    commands_json JSON NULL,
                    enabled TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_checkin_reward_scope_day (scope, day),
                    KEY idx_checkin_reward_enabled (enabled)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS checkin_reward_deliveries (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    record_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    mc_uuid VARCHAR(64) NOT NULL,
                    username VARCHAR(64) NOT NULL,
                    reward_payload_json JSON NOT NULL,
                    delivery_mode ENUM('plugin_poll') NOT NULL DEFAULT 'plugin_poll',
                    status ENUM('pending', 'delivering', 'delivered', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    last_error VARCHAR(255) NULL DEFAULT NULL,
                    locked_at DATETIME NULL DEFAULT NULL,
                    delivered_at DATETIME NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    CONSTRAINT fk_checkin_reward_deliveries_record FOREIGN KEY (record_id) REFERENCES checkin_records(id) ON DELETE CASCADE,
                    CONSTRAINT fk_checkin_reward_deliveries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    KEY idx_checkin_reward_deliveries_status_locked (status, locked_at),
                    KEY idx_checkin_reward_deliveries_user (user_id),
                    KEY idx_checkin_reward_deliveries_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $seed = $this->db->prepare('
                INSERT INTO checkin_reward_rules (day, scope, coins, points, items_json, commands_json, enabled, created_at, updated_at)
                SELECT 1, \'daily\', 120, 0, :items_json, :commands_json, 1, NOW(), NOW()
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM checkin_reward_rules
                    LIMIT 1
                )
            ');
            $seed->execute([
                ':items_json' => '[{"id":"minecraft:iron_ingot","amount":10}]',
                ':commands_json' => '[]',
            ]);

            self::$schemaReady = true;
        }

        if (!self::$legacySynced) {
            $this->syncLegacyCheckins();
            self::$legacySynced = true;
        }
    }

    private function syncLegacyCheckins(): void
    {
        try {
            $stmt = $this->db->query('SHOW TABLES LIKE \'user_checkins\'');
            if ($stmt->fetchColumn() === false) {
                return;
            }

            $legacyStmt = $this->db->query('
                SELECT
                    uc.user_id,
                    uc.checkin_date,
                    uc.created_at,
                    COALESCE(NULLIF(u.mc_username, \'\'), u.username) AS record_username,
                    COALESCE(NULLIF(u.mc_uuid, \'\'), \'\') AS record_uuid
                FROM user_checkins uc
                INNER JOIN users u ON u.id = uc.user_id
                LEFT JOIN checkin_records cr
                    ON cr.user_id = uc.user_id
                   AND cr.checkin_date = uc.checkin_date
                WHERE cr.id IS NULL
                ORDER BY uc.user_id ASC, uc.checkin_date ASC, uc.id ASC
            ');
            $legacyRows = $legacyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($legacyRows === []) {
                return;
            }

            $this->db->beginTransaction();

            foreach ($legacyRows as $row) {
                $userId = (int)$row['user_id'];
                $checkinDate = (string)$row['checkin_date'];
                $createdAt = trim((string)($row['created_at'] ?? ''));
                $username = trim((string)($row['record_username'] ?? ''));
                $uuid = trim((string)($row['record_uuid'] ?? ''));

                if ($userId <= 0 || $checkinDate === '' || !$this->isValidPlayerUsername($username)) {
                    continue;
                }

                $uuid = $this->normalizePlayerUuid($uuid);
                if ($uuid === '') {
                    $uuid = MinecraftUuid::getOfflineUuid($username);
                }

                $monthKey = substr($checkinDate, 0, 7);
                $previous = $this->findRecordByUserAndDate($userId, $this->dateOffset($checkinDate, -1), true);
                $streakDays = $previous !== null ? ((int)$previous['streak_days'] + 1) : 1;
                $monthDays = $this->countMonthDays($userId, $monthKey, true) + 1;
                $totalDays = $this->countTotalDays($userId, true) + 1;
                $checkinTime = $createdAt !== '' ? $createdAt : ($checkinDate . ' 00:00:00');
                $snapshot = [
                    'coins' => 0,
                    'points' => 0,
                    'items' => [],
                    'commands' => [],
                    'scope' => ['legacy_import'],
                    'generated_at' => $checkinTime,
                    'legacy' => true,
                ];

                $insert = $this->db->prepare('
                    INSERT INTO checkin_records (
                        user_id,
                        mc_uuid,
                        username,
                        checkin_date,
                        checkin_time,
                        month_key,
                        streak_days,
                        month_days,
                        total_days,
                        reward_snapshot_json,
                        delivery_status,
                        ip_hash,
                        user_agent_hash,
                        created_at,
                        updated_at
                    ) VALUES (
                        :user_id,
                        :mc_uuid,
                        :username,
                        :checkin_date,
                        :checkin_time,
                        :month_key,
                        :streak_days,
                        :month_days,
                        :total_days,
                        :reward_snapshot_json,
                        \'delivered\',
                        \'\',
                        \'\',
                        :created_at,
                        :updated_at
                    )
                ');
                $insert->execute([
                    ':user_id' => $userId,
                    ':mc_uuid' => $uuid,
                    ':username' => $username,
                    ':checkin_date' => $checkinDate,
                    ':checkin_time' => $checkinTime,
                    ':month_key' => $monthKey,
                    ':streak_days' => $streakDays,
                    ':month_days' => $monthDays,
                    ':total_days' => $totalDays,
                    ':reward_snapshot_json' => $this->encodeJson($snapshot),
                    ':created_at' => $checkinTime,
                    ':updated_at' => $checkinTime,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function getUserIdentity(int $userId, bool $forUpdate = false): ?array
    {
        $sql = '
            SELECT
                id,
                username,
                mc_username,
                mc_uuid
            FROM users
            WHERE id = :id
            LIMIT 1
        ';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $mcUsername = trim((string)($row['mc_username'] ?? ''));
        $mcUuid = $this->normalizePlayerUuid((string)($row['mc_uuid'] ?? ''));

        return [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'mc_username' => $mcUsername,
            'mc_uuid' => $mcUuid,
        ];
    }

    private function isBoundIdentity(string $username, string $uuid): bool
    {
        return $this->isValidPlayerUsername($username) && $this->normalizePlayerUuid($uuid) !== '';
    }

    private function findRecordByUserAndDate(int $userId, string $checkinDate, bool $forUpdate = false): ?array
    {
        $sql = '
            SELECT *
            FROM checkin_records
            WHERE user_id = :user_id
              AND checkin_date = :checkin_date
            LIMIT 1
        ';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':checkin_date' => $checkinDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findLatestRecord(int $userId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM checkin_records
            WHERE user_id = :user_id
            ORDER BY checkin_date DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function countMonthDays(int $userId, string $monthKey, bool $inTransaction = false): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM checkin_records
            WHERE user_id = :user_id
              AND month_key = :month_key
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':month_key' => $monthKey,
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function countTotalDays(int $userId, bool $inTransaction = false): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM checkin_records
            WHERE user_id = :user_id
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    private function findLatestDeliveryStatus(int $userId): ?string
    {
        $stmt = $this->db->prepare('
            SELECT status
            FROM checkin_reward_deliveries
            WHERE user_id = :user_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $status = $stmt->fetchColumn();
        if (!is_string($status) || !in_array($status, self::DELIVERY_STATUSES, true)) {
            return null;
        }

        return $status;
    }

    private function fetchRewardRules(): array
    {
        $stmt = $this->db->query('
            SELECT
                id,
                day,
                scope,
                coins,
                points,
                items_json,
                commands_json,
                enabled,
                created_at,
                updated_at
            FROM checkin_reward_rules
            ORDER BY
                CASE WHEN scope = \'daily\' THEN 0 ELSE 1 END ASC,
                day ASC,
                id ASC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'day' => (int)$row['day'],
                'scope' => (string)$row['scope'],
                'coins' => (int)$row['coins'],
                'points' => (int)$row['points'],
                'items' => $this->decodeJsonArray($row['items_json'] ?? null),
                'commands' => $this->decodeJsonArray($row['commands_json'] ?? null),
                'enabled' => (int)$row['enabled'] === 1,
                'created_at' => (string)$row['created_at'],
                'updated_at' => (string)$row['updated_at'],
            ];
        }, $rows);
    }

    private function buildRewardSnapshot(
        string $mcUsername,
        string $mcUuid,
        int $monthDays,
        DateTimeImmutable $now
    ): array {
        $coins = 0;
        $points = 0;
        $items = [];
        $commands = [];
        $scope = [];

        foreach ($this->fetchRewardRules() as $rule) {
            if (!$rule['enabled']) {
                continue;
            }

            if ($rule['scope'] === 'daily') {
                $coins += (int)$rule['coins'];
                $points += (int)$rule['points'];
                $items = array_merge($items, $rule['items']);
                $commands = array_merge($commands, $this->renderCommands($rule['commands'], $mcUsername, $mcUuid));
                $scope[] = 'daily';
                continue;
            }

            if ($rule['scope'] === 'monthly' && (int)$rule['day'] === $monthDays) {
                $coins += (int)$rule['coins'];
                $points += (int)$rule['points'];
                $items = array_merge($items, $rule['items']);
                $commands = array_merge($commands, $this->renderCommands($rule['commands'], $mcUsername, $mcUuid));
                $scope[] = 'monthly';
            }
        }

        return [
            'coins' => $coins,
            'points' => $points,
            'items' => $items,
            'commands' => $commands,
            'scope' => array_values(array_unique($scope)),
            'generated_at' => $now->format(DateTimeInterface::ATOM),
        ];
    }

    private function renderCommands(array $commands, string $mcUsername, string $mcUuid): array
    {
        $safeCommands = [];
        foreach ($commands as $command) {
            if (!is_string($command)) {
                continue;
            }
            if (!$this->isSafeCommandTemplate($command)) {
                continue;
            }

            $safeCommands[] = strtr($command, [
                '{player}' => $mcUsername,
                '{uuid}' => $mcUuid,
            ]);
        }

        return $safeCommands;
    }

    private function recoverExpiredDeliveries(): void
    {
        $nowString = $this->now()->format('Y-m-d H:i:s');
        $update = $this->db->prepare('
            UPDATE checkin_reward_deliveries
            SET status = \'failed\',
                attempts = attempts + 1,
                last_error = CASE
                    WHEN last_error IS NULL OR last_error = \'\' THEN \'Delivery lock expired\'
                    ELSE last_error
                END,
                locked_at = NULL,
                updated_at = :updated_at
            WHERE status = \'delivering\'
              AND locked_at IS NOT NULL
              AND locked_at < DATE_SUB(NOW(), INTERVAL ' . self::DELIVERY_LOCK_TIMEOUT_MINUTES . ' MINUTE)
        ');
        $update->execute([':updated_at' => $nowString]);

        $recordUpdate = $this->db->prepare('
            UPDATE checkin_records cr
            INNER JOIN checkin_reward_deliveries d ON d.record_id = cr.id
            SET cr.delivery_status = \'failed\',
                cr.updated_at = :record_updated_at
            WHERE d.status = \'failed\'
              AND d.updated_at = :delivery_updated_at
        ');
        $recordUpdate->execute([
            ':record_updated_at' => $nowString,
            ':delivery_updated_at' => $nowString,
        ]);
    }

    private function countByDate(string $checkinDate): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM checkin_records
            WHERE checkin_date = :checkin_date
        ');
        $stmt->execute([':checkin_date' => $checkinDate]);
        return (int)$stmt->fetchColumn();
    }

    private function countByMonth(string $monthKey): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM checkin_records
            WHERE month_key = :month_key
        ');
        $stmt->execute([':month_key' => $monthKey]);
        return (int)$stmt->fetchColumn();
    }

    private function countDeliveriesByStatus(string $status): int
    {
        if (!in_array($status, self::DELIVERY_STATUSES, true)) {
            return 0;
        }

        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM checkin_reward_deliveries
            WHERE status = :status
        ');
        $stmt->execute([':status' => $status]);
        return (int)$stmt->fetchColumn();
    }

    private function fetchTopStreaks(): array
    {
        $stmt = $this->db->query('
            SELECT
                cr.username,
                cr.streak_days,
                cr.checkin_date
            FROM checkin_records cr
            INNER JOIN (
                SELECT user_id, MAX(checkin_date) AS latest_date
                FROM checkin_records
                GROUP BY user_id
            ) latest
                ON latest.user_id = cr.user_id
               AND latest.latest_date = cr.checkin_date
            ORDER BY cr.streak_days DESC, cr.checkin_date DESC, cr.id DESC
            LIMIT 10
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findRewardRuleByScopeAndDay(string $scope, int $day): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id
            FROM checkin_reward_rules
            WHERE scope = :scope
              AND day = :day
            LIMIT 1
        ');
        $stmt->execute([
            ':scope' => $scope,
            ':day' => $day,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function normalizeItemRules($raw): ?array
    {
        $rows = $this->decodeLooseJsonArray($raw);
        if ($rows === null) {
            return null;
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $name = trim($row);
                if ($name === '') {
                    continue;
                }
                $normalized[] = ['id' => $name, 'amount' => 1];
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $id = trim((string)($row['id'] ?? $row['name'] ?? ''));
            if ($id === '') {
                continue;
            }

            $amount = max(1, (int)($row['amount'] ?? 1));
            $normalized[] = ['id' => $id, 'amount' => $amount];
        }

        return $normalized;
    }

    private function normalizeCommandRules($raw): ?array
    {
        $rows = $this->decodeLooseJsonArray($raw);
        if ($rows === null) {
            return null;
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                continue;
            }
            $command = trim($row);
            if ($command === '') {
                continue;
            }
            if (!$this->isSafeCommandTemplate($command)) {
                return null;
            }
            $normalized[] = $command;
        }

        return $normalized;
    }

    private function isSafeCommandTemplate(string $command): bool
    {
        preg_match_all('/\{[^}]+\}/', $command, $matches);
        foreach ($matches[0] as $placeholder) {
            if (!in_array($placeholder, ['{player}', '{uuid}'], true)) {
                return false;
            }
        }

        return true;
    }

    private function decodeLooseJsonArray($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $rawString = trim((string)$raw);
        if ($rawString === '') {
            return [];
        }

        $decoded = json_decode($rawString, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function decodeJsonArray($raw): array
    {
        $decoded = $this->decodeLooseJsonArray($raw);
        return $decoded ?? [];
    }

    private function decodeJsonObject($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '[]';
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    private function dateOffset(string $date, int $days): string
    {
        $base = new DateTimeImmutable($date . ' 00:00:00');
        $modifier = ($days >= 0 ? '+' : '') . $days . ' day';
        return $base->modify($modifier)->format('Y-m-d');
    }

    private function normalizePlayerUuid(string $uuid): string
    {
        return MinecraftUuid::normalizeToDashed($uuid);
    }

    private function isValidPlayerUsername(string $username): bool
    {
        return preg_match(self::USERNAME_PATTERN, $username) === 1;
    }

    private function isDuplicateEntry(PDOException $e): bool
    {
        return $e->getCode() === '23000';
    }

    private function formatRecordSummary(array $record): array
    {
        return [
            'id' => isset($record['id']) ? (int)$record['id'] : 0,
            'checkin_date' => (string)($record['checkin_date'] ?? ''),
            'streak_days' => (int)($record['streak_days'] ?? 0),
            'month_days' => (int)($record['month_days'] ?? 0),
            'total_days' => (int)($record['total_days'] ?? 0),
            'delivery_status' => (string)($record['delivery_status'] ?? ''),
            'reward' => $this->decodeJsonObject($record['reward_snapshot_json'] ?? null),
        ];
    }

    private function formatDeliverySummary(array $delivery): array
    {
        return [
            'id' => isset($delivery['id']) ? (int)$delivery['id'] : 0,
            'status' => (string)($delivery['status'] ?? ''),
            'attempts' => (int)($delivery['attempts'] ?? 0),
            'delivered_at' => (string)($delivery['delivered_at'] ?? ''),
        ];
    }

    private function emptyStatus(): array
    {
        return [
            'logged_in' => false,
            'bound_mc' => false,
            'checked_in_today' => false,
            'streak_days' => 0,
            'month_days' => 0,
            'total_days' => 0,
            'today_reward' => null,
            'latest_delivery_status' => null,
            'today_date' => $this->now()->format('Y-m-d'),
            'month_key' => $this->now()->format('Y-m'),
            'mc_username' => null,
            'mc_uuid' => null,
        ];
    }
}
