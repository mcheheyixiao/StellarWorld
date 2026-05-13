<?php
declare(strict_types=1);

namespace Model;

use Core\MinecraftUuid;
use Core\Model;
use PDO;

class SigninRewardConfig extends Model
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SCHEDULED = 'scheduled';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_ARCHIVED = 'archived';

    private const RULE_DAILY = 'daily';
    private const RULE_STREAK = 'streak';
    private const RULE_TOTAL = 'total';
    private const RULE_MONTHLY = 'monthly';

    private const TEST_SEND_SETTING_KEY = 'signin_reward_test_send_enabled';
    private const REPEAT_TEST_SETTING_KEY = 'signin_reward_admin_repeat_test_enabled';

    private static bool $schemaReady = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    public function getAdminEditorState(int $adminUserId = 0): array
    {
        $today = date('Y-m-d');
        $this->rolloverConfigsForDate($today);

        $draft = $this->ensureDraftConfig($adminUserId);
        $active = $this->findActiveConfig();
        $scheduled = $this->findNextScheduledConfig($today);
        $draftRules = $draft !== null ? $this->fetchRulesForConfig((int)$draft['id']) : [];

        return [
            'active' => $active,
            'scheduled' => $scheduled,
            'draft' => $draft,
            'rules' => $this->groupRulesByType($draftRules),
            'flat_rules' => $draftRules,
            'settings' => $this->readFeatureSettings(),
        ];
    }

    public function saveDraftFromAdmin(array $payload, int $adminUserId): array
    {
        $draft = $this->ensureDraftConfig($adminUserId);
        if ($draft === null) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => '初始化草稿配置失败',
            ];
        }

        $rawRules = $payload['rules'] ?? null;
        if (!is_array($rawRules)) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => '必须提供规则数据',
            ];
        }

        $normalizedRules = [];
        $sortOrder = 0;
        foreach ($rawRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $normalized = $this->normalizeRuleInput($rule, $sortOrder);
            if ($normalized === null) {
                continue;
            }
            $normalizedRules[] = $normalized;
            $sortOrder++;
        }

        $name = trim((string)($payload['name'] ?? ($draft['name'] ?? '签到奖励草稿')));
        if ($name === '') {
            $name = '签到奖励草稿';
        }
        $name = $this->cutString($name, 120);

        $testSendEnabled = $this->normalizeBoolInt(
            $payload['test_send_enabled'] ?? ($payload['signin_reward_test_send_enabled'] ?? null),
            null
        );
        $repeatTestEnabled = $this->normalizeBoolInt($payload['admin_repeat_test_enabled'] ?? null, null);

        $ownsTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->db->prepare('
                UPDATE signin_reward_configs
                SET name = :name,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ');
            $stmt->execute([
                ':name' => $name,
                ':id' => (int)$draft['id'],
            ]);

            $deleteStmt = $this->db->prepare('DELETE FROM signin_reward_rules WHERE config_id = :config_id');
            $deleteStmt->execute([':config_id' => (int)$draft['id']]);

            foreach ($normalizedRules as $rule) {
                $this->insertRule((int)$draft['id'], $rule);
            }

            if ($testSendEnabled !== null) {
                $this->upsertSiteSetting(self::TEST_SEND_SETTING_KEY, (string)$testSendEnabled);
            }

            if ($repeatTestEnabled !== null) {
                $this->upsertSiteSetting(self::REPEAT_TEST_SETTING_KEY, (string)$repeatTestEnabled);
            }

            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'status' => 500,
                'message' => '保存草稿配置失败',
            ];
        }

        $freshDraft = $this->findLatestConfigByStatus(self::STATUS_DRAFT);
        $freshRules = $freshDraft !== null ? $this->fetchRulesForConfig((int)$freshDraft['id']) : [];

        return [
            'ok' => true,
            'status' => 200,
            'message' => '草稿配置已保存',
            'draft' => $freshDraft,
            'rules' => $this->groupRulesByType($freshRules),
            'flat_rules' => $freshRules,
        ];
    }

    public function publishDraft(?string $effectiveDate, int $adminUserId): array
    {
        $draft = $this->ensureDraftConfig($adminUserId);
        if ($draft === null) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => '草稿配置不可用',
            ];
        }

        $targetDate = $this->normalizeDate($effectiveDate ?? '');
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('+1 day'));
        }
        $minDate = date('Y-m-d', strtotime('+1 day'));
        if (strcmp($targetDate, $minDate) < 0) {
            return [
                'ok' => false,
                'status' => 422,
                'code' => 'publish_date_too_early',
                'message' => '正式配置最早只能从明天 00:00 生效；当天验证请使用测试发送。',
            ];
        }

        $draftRules = $this->fetchRulesForConfig((int)$draft['id']);
        if ($draftRules === []) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => '草稿没有可发布规则',
            ];
        }

        $scheduledName = trim((string)($draft['name'] ?? '签到奖励配置'));
        if ($scheduledName === '') {
            $scheduledName = '签到奖励配置';
        }
        $scheduledName = $this->cutString($scheduledName . ' [' . $targetDate . ']', 120);

        $ownsTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $ownsTx = true;
        }
        try {
            $this->db->exec("UPDATE signin_reward_configs SET status = 'archived', is_default = 0, updated_at = NOW() WHERE status = 'scheduled'");

            $stmt = $this->db->prepare('
                INSERT INTO signin_reward_configs (
                    name,
                    status,
                    effective_date,
                    is_default,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :name,
                    :status,
                    :effective_date,
                    0,
                    :created_by,
                    NOW(),
                    NOW()
                )
            ');
            $stmt->execute([
                ':name' => $scheduledName,
                ':status' => self::STATUS_SCHEDULED,
                ':effective_date' => $targetDate,
                ':created_by' => $adminUserId > 0 ? $adminUserId : null,
            ]);

            $scheduledId = (int)$this->db->lastInsertId();
            foreach ($draftRules as $rule) {
                $this->insertRule($scheduledId, [
                    'rule_type' => (string)($rule['rule_type'] ?? self::RULE_DAILY),
                    'trigger_day' => (int)($rule['trigger_day'] ?? 1),
                    'mail_title' => (string)($rule['mail_title'] ?? ''),
                    'mail_icon' => (string)($rule['mail_icon'] ?? 'BOOK'),
                    'mail_content' => is_array($rule['mail_content'] ?? null) ? $rule['mail_content'] : [],
                    'items' => is_array($rule['items'] ?? null) ? $rule['items'] : [],
                    'commands' => is_array($rule['commands'] ?? null) ? $rule['commands'] : [],
                    'enabled' => !empty($rule['enabled']),
                    'sort_order' => (int)($rule['sort_order'] ?? 0),
                ]);
            }

            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'status' => 500,
                'message' => '发布草稿配置失败',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '草稿已发布，并已安排生效',
            'effective_date' => $targetDate,
        ];
    }

    public function deleteDraftRule(int $ruleId): array
    {
        if ($ruleId <= 0) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => '规则 ID 无效',
            ];
        }

        $draft = $this->findLatestConfigByStatus(self::STATUS_DRAFT);
        if ($draft === null) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => '未找到草稿配置',
            ];
        }

        $stmt = $this->db->prepare('DELETE FROM signin_reward_rules WHERE id = :id AND config_id = :config_id LIMIT 1');
        $stmt->execute([
            ':id' => $ruleId,
            ':config_id' => (int)$draft['id'],
        ]);

        if ($stmt->rowCount() !== 1) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => '草稿中未找到该规则',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '规则已删除',
        ];
    }

    public function createTestRewardOutbox(int $adminUserId, array $input): array
    {
        $settings = $this->readFeatureSettings();
        if ((int)($settings['test_send_enabled'] ?? 1) !== 1) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => '站点设置已关闭测试发送',
            ];
        }

        $target = $this->resolveTestTarget($adminUserId, $input);
        if (($target['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => (int)($target['status'] ?? 400),
                'message' => (string)($target['message'] ?? '测试目标无效'),
            ];
        }

        $signDate = $this->normalizeDate((string)($input['sign_date'] ?? ''));
        if ($signDate === null) {
            $signDate = date('Y-m-d');
        }

        $continuous = max(1, (int)($input['continuous'] ?? 1));
        $total = max(1, (int)($input['total'] ?? $continuous));
        $monthDays = max(1, (int)($input['month_days'] ?? 1));

        $draft = $this->ensureDraftConfig($adminUserId);
        if ($draft === null) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => '草稿配置不可用',
            ];
        }

        $rules = $this->fetchRulesForConfig((int)$draft['id']);
        $payload = $this->composeRewardPayload(
            $rules,
            $signDate,
            $continuous,
            $total,
            $monthDays,
            [
                'player' => (string)$target['player_name'],
                'uuid' => (string)$target['player_uuid'],
                'server_id' => (string)($input['server_id'] ?? $this->defaultServerId()),
                'source' => 'signin_test',
                'config_id' => (int)$draft['id'],
                'config_name' => (string)($draft['name'] ?? ''),
                'config_status' => (string)($draft['status'] ?? self::STATUS_DRAFT),
            ]
        );

        if (($payload['items'] ?? []) === [] && ($payload['commands'] ?? []) === []) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => '草稿规则解析后为空奖励',
            ];
        }

        $requestId = 'signin_test_' . date('YmdHis') . '_' . bin2hex(random_bytes(5));
        $gateway = new SigninGateway();

        try {
            $gateway->createSigninTestRewardOutbox([
                'request_id' => $requestId,
                'website_user_id' => max(0, (int)($target['website_user_id'] ?? $adminUserId)),
                'player_uuid' => (string)$target['player_uuid'],
                'player_name' => (string)$target['player_name'],
                'server_id' => $this->cutString((string)($input['server_id'] ?? $this->defaultServerId()), 64),
                'reward_type' => 'sweetmail',
                'reward_payload_json' => $this->encodeJson($payload),
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => '写入测试奖励队列失败',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '测试奖励已入队',
            'request_id' => $requestId,
            'payload' => $payload,
            'target' => [
                'player_name' => (string)$target['player_name'],
                'player_uuid' => (string)$target['player_uuid'],
            ],
        ];
    }

    public function buildPayloadForSignDate(string $signDate, int $continuous, int $total, int $monthDays, array $context = []): ?array
    {
        $normalizedDate = $this->normalizeDate($signDate);
        if ($normalizedDate === null) {
            $normalizedDate = date('Y-m-d');
        }

        $this->rolloverConfigsForDate($normalizedDate);
        $active = $this->findActiveConfig();
        if ($active === null) {
            return null;
        }

        $activeEffective = $this->normalizeDate((string)($active['effective_date'] ?? ''));
        if ($activeEffective !== null && strcmp($activeEffective, $normalizedDate) > 0) {
            return null;
        }

        $rules = $this->fetchRulesForConfig((int)$active['id']);
        if ($rules === []) {
            return null;
        }

        return $this->composeRewardPayload(
            $rules,
            $normalizedDate,
            max(1, $continuous),
            max(1, $total),
            max(1, $monthDays),
            [
                'player' => (string)($context['player'] ?? ''),
                'uuid' => (string)($context['uuid'] ?? ''),
                'server_id' => (string)($context['server_id'] ?? $this->defaultServerId()),
                'source' => (string)($context['source'] ?? 'web_queue'),
                'config_id' => (int)$active['id'],
                'config_name' => (string)($active['name'] ?? ''),
                'config_status' => (string)($active['status'] ?? self::STATUS_ACTIVE),
            ]
        );
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->db->exec('
            CREATE TABLE IF NOT EXISTS signin_reward_configs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "draft",
                effective_date DATE NULL DEFAULT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_by INT UNSIGNED NULL DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_signin_reward_configs_status_effective (status, effective_date),
                KEY idx_signin_reward_configs_default (is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->db->exec('
            CREATE TABLE IF NOT EXISTS signin_reward_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                config_id BIGINT UNSIGNED NOT NULL,
                rule_type VARCHAR(32) NOT NULL,
                trigger_day INT UNSIGNED NOT NULL DEFAULT 1,
                mail_title VARCHAR(120) NOT NULL DEFAULT "",
                mail_icon VARCHAR(64) NOT NULL DEFAULT "BOOK",
                mail_content_json LONGTEXT NULL,
                items_json LONGTEXT NULL,
                commands_json LONGTEXT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_signin_reward_rules_config_type_day (config_id, rule_type, trigger_day),
                KEY idx_signin_reward_rules_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->ensureConfigTableColumnsAndIndexes();
        $this->ensureRuleTableColumnsAndIndexes();
        $this->ensureFeatureSettings();
        $this->seedDefaultDraftIfNeeded();

        self::$schemaReady = true;
    }

    private function ensureConfigTableColumnsAndIndexes(): void
    {
        $columns = [
            'name' => 'ALTER TABLE signin_reward_configs ADD COLUMN name VARCHAR(120) NOT NULL AFTER id',
            'status' => 'ALTER TABLE signin_reward_configs ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT "draft" AFTER name',
            'effective_date' => 'ALTER TABLE signin_reward_configs ADD COLUMN effective_date DATE NULL DEFAULT NULL AFTER status',
            'is_default' => 'ALTER TABLE signin_reward_configs ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER effective_date',
            'created_by' => 'ALTER TABLE signin_reward_configs ADD COLUMN created_by INT UNSIGNED NULL DEFAULT NULL AFTER is_default',
            'created_at' => 'ALTER TABLE signin_reward_configs ADD COLUMN created_at DATETIME NOT NULL AFTER created_by',
            'updated_at' => 'ALTER TABLE signin_reward_configs ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at',
        ];

        foreach ($columns as $column => $ddl) {
            if (!$this->tableHasColumn('signin_reward_configs', $column)) {
                $this->db->exec($ddl);
            }
        }

        if (!$this->tableHasIndex('signin_reward_configs', 'idx_signin_reward_configs_status_effective')) {
            $this->db->exec('ALTER TABLE signin_reward_configs ADD KEY idx_signin_reward_configs_status_effective (status, effective_date)');
        }
        if (!$this->tableHasIndex('signin_reward_configs', 'idx_signin_reward_configs_default')) {
            $this->db->exec('ALTER TABLE signin_reward_configs ADD KEY idx_signin_reward_configs_default (is_default)');
        }
    }

    private function ensureRuleTableColumnsAndIndexes(): void
    {
        $columns = [
            'config_id' => 'ALTER TABLE signin_reward_rules ADD COLUMN config_id BIGINT UNSIGNED NOT NULL AFTER id',
            'rule_type' => 'ALTER TABLE signin_reward_rules ADD COLUMN rule_type VARCHAR(32) NOT NULL AFTER config_id',
            'trigger_day' => 'ALTER TABLE signin_reward_rules ADD COLUMN trigger_day INT UNSIGNED NOT NULL DEFAULT 1 AFTER rule_type',
            'mail_title' => 'ALTER TABLE signin_reward_rules ADD COLUMN mail_title VARCHAR(120) NOT NULL DEFAULT "" AFTER trigger_day',
            'mail_icon' => 'ALTER TABLE signin_reward_rules ADD COLUMN mail_icon VARCHAR(64) NOT NULL DEFAULT "BOOK" AFTER mail_title',
            'mail_content_json' => 'ALTER TABLE signin_reward_rules ADD COLUMN mail_content_json LONGTEXT NULL AFTER mail_icon',
            'items_json' => 'ALTER TABLE signin_reward_rules ADD COLUMN items_json LONGTEXT NULL AFTER mail_content_json',
            'commands_json' => 'ALTER TABLE signin_reward_rules ADD COLUMN commands_json LONGTEXT NULL AFTER items_json',
            'enabled' => 'ALTER TABLE signin_reward_rules ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER commands_json',
            'sort_order' => 'ALTER TABLE signin_reward_rules ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER enabled',
            'created_at' => 'ALTER TABLE signin_reward_rules ADD COLUMN created_at DATETIME NOT NULL AFTER sort_order',
            'updated_at' => 'ALTER TABLE signin_reward_rules ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at',
        ];

        foreach ($columns as $column => $ddl) {
            if (!$this->tableHasColumn('signin_reward_rules', $column)) {
                $this->db->exec($ddl);
            }
        }

        if (!$this->tableHasIndex('signin_reward_rules', 'idx_signin_reward_rules_config_type_day')) {
            $this->db->exec('ALTER TABLE signin_reward_rules ADD KEY idx_signin_reward_rules_config_type_day (config_id, rule_type, trigger_day)');
        }
        if (!$this->tableHasIndex('signin_reward_rules', 'idx_signin_reward_rules_enabled')) {
            $this->db->exec('ALTER TABLE signin_reward_rules ADD KEY idx_signin_reward_rules_enabled (enabled)');
        }
    }

    private function ensureFeatureSettings(): void
    {
        if (!$this->tableExists('site_settings')) {
            return;
        }

        $this->upsertSiteSetting(self::TEST_SEND_SETTING_KEY, '1', '管理员测试发送开关。1=开启，0=关闭。');
        $this->upsertSiteSetting(self::REPEAT_TEST_SETTING_KEY, '0', '允许管理员重复模拟正式签到。1=开启，0=关闭。');
    }

    private function seedDefaultDraftIfNeeded(): void
    {
        $countStmt = $this->db->query('SELECT COUNT(*) FROM signin_reward_configs');
        $count = $countStmt !== false ? (int)$countStmt->fetchColumn() : 0;
        if ($count > 0) {
            return;
        }

        $base = $this->defaultRewardTemplate();

        $ownsTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->db->prepare('
                INSERT INTO signin_reward_configs (
                    name,
                    status,
                    effective_date,
                    is_default,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :name,
                    :status,
                    NULL,
                    0,
                    NULL,
                    NOW(),
                    NOW()
                )
            ');
            $stmt->execute([
                ':name' => '签到奖励草稿',
                ':status' => self::STATUS_DRAFT,
            ]);

            $configId = (int)$this->db->lastInsertId();
            $this->insertRule($configId, [
                'rule_type' => self::RULE_DAILY,
                'trigger_day' => 1,
                'mail_title' => (string)$base['mail']['title'],
                'mail_icon' => (string)$base['mail']['icon'],
                'mail_content' => (array)$base['mail']['content'],
                'items' => (array)$base['items'],
                'commands' => (array)$base['commands'],
                'enabled' => true,
                'sort_order' => 0,
            ]);

            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function ensureDraftConfig(int $adminUserId): ?array
    {
        $draft = $this->findLatestConfigByStatus(self::STATUS_DRAFT);
        if ($draft !== null) {
            return $draft;
        }

        $stmt = $this->db->prepare('
            INSERT INTO signin_reward_configs (
                name,
                status,
                effective_date,
                is_default,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :status,
                NULL,
                0,
                :created_by,
                NOW(),
                NOW()
            )
        ');
        $stmt->execute([
            ':name' => '签到奖励草稿',
            ':status' => self::STATUS_DRAFT,
            ':created_by' => $adminUserId > 0 ? $adminUserId : null,
        ]);

        $draftId = (int)$this->db->lastInsertId();
        if ($draftId <= 0) {
            return null;
        }

        $base = $this->defaultRewardTemplate();
        $this->insertRule($draftId, [
            'rule_type' => self::RULE_DAILY,
            'trigger_day' => 1,
            'mail_title' => (string)$base['mail']['title'],
            'mail_icon' => (string)$base['mail']['icon'],
            'mail_content' => (array)$base['mail']['content'],
            'items' => (array)$base['items'],
            'commands' => (array)$base['commands'],
            'enabled' => true,
            'sort_order' => 0,
        ]);

        return $this->findConfigById($draftId);
    }

    private function findConfigById(int $configId): ?array
    {
        if ($configId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM signin_reward_configs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $configId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeConfigRow($row);
    }

    private function findLatestConfigByStatus(string $status): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM signin_reward_configs
            WHERE status = :status
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([':status' => $status]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeConfigRow($row);
    }

    private function findActiveConfig(): ?array
    {
        $stmt = $this->db->query('
            SELECT *
            FROM signin_reward_configs
            WHERE status = "active"
            ORDER BY effective_date DESC, id DESC
            LIMIT 1
        ');
        if ($stmt === false) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeConfigRow($row);
    }

    private function findNextScheduledConfig(string $today): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM signin_reward_configs
            WHERE status = :status
              AND effective_date IS NOT NULL
              AND effective_date >= :today
            ORDER BY effective_date ASC, id ASC
            LIMIT 1
        ');
        $stmt->execute([
            ':status' => self::STATUS_SCHEDULED,
            ':today' => $today,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeConfigRow($row);
    }

    private function rolloverConfigsForDate(string $date): void
    {
        $normalizedDate = $this->normalizeDate($date);
        if ($normalizedDate === null) {
            $normalizedDate = date('Y-m-d');
        }

        $stmt = $this->db->prepare('
            SELECT *
            FROM signin_reward_configs
            WHERE status = :status
              AND effective_date IS NOT NULL
              AND effective_date <= :date
            ORDER BY effective_date DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':status' => self::STATUS_SCHEDULED,
            ':date' => $normalizedDate,
        ]);
        $due = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$due) {
            return;
        }

        $dueId = (int)($due['id'] ?? 0);
        if ($dueId <= 0) {
            return;
        }

        $ownsTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $ownsTx = true;
        }
        try {
            $archiveActive = $this->db->prepare('
                UPDATE signin_reward_configs
                SET status = :archived,
                    is_default = 0,
                    updated_at = NOW()
                WHERE status = :active
                  AND id <> :id
            ');
            $archiveActive->execute([
                ':archived' => self::STATUS_ARCHIVED,
                ':active' => self::STATUS_ACTIVE,
                ':id' => $dueId,
            ]);

            $archiveOldScheduled = $this->db->prepare('
                UPDATE signin_reward_configs
                SET status = :archived,
                    is_default = 0,
                    updated_at = NOW()
                WHERE status = :scheduled
                  AND effective_date IS NOT NULL
                  AND effective_date <= :date
                  AND id <> :id
            ');
            $archiveOldScheduled->execute([
                ':archived' => self::STATUS_ARCHIVED,
                ':scheduled' => self::STATUS_SCHEDULED,
                ':date' => $normalizedDate,
                ':id' => $dueId,
            ]);

            $activate = $this->db->prepare('
                UPDATE signin_reward_configs
                SET status = :active,
                    is_default = 1,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ');
            $activate->execute([
                ':active' => self::STATUS_ACTIVE,
                ':id' => $dueId,
            ]);

            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function fetchRulesForConfig(int $configId): array
    {
        if ($configId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('
            SELECT *
            FROM signin_reward_rules
            WHERE config_id = :config_id
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([':config_id' => $configId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rules = [];
        foreach ($rows as $row) {
            $rules[] = $this->normalizeRuleRow($row);
        }

        return $rules;
    }

    private function insertRule(int $configId, array $rule): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO signin_reward_rules (
                config_id,
                rule_type,
                trigger_day,
                mail_title,
                mail_icon,
                mail_content_json,
                items_json,
                commands_json,
                enabled,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                :config_id,
                :rule_type,
                :trigger_day,
                :mail_title,
                :mail_icon,
                :mail_content_json,
                :items_json,
                :commands_json,
                :enabled,
                :sort_order,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            ':config_id' => $configId,
            ':rule_type' => (string)($rule['rule_type'] ?? self::RULE_DAILY),
            ':trigger_day' => max(1, (int)($rule['trigger_day'] ?? 1)),
            ':mail_title' => $this->cutString((string)($rule['mail_title'] ?? ''), 120),
            ':mail_icon' => $this->cutString((string)($rule['mail_icon'] ?? 'BOOK'), 64),
            ':mail_content_json' => $this->encodeJson((array)($rule['mail_content'] ?? [])),
            ':items_json' => $this->encodeJson((array)($rule['items'] ?? [])),
            ':commands_json' => $this->encodeJson((array)($rule['commands'] ?? [])),
            ':enabled' => !empty($rule['enabled']) ? 1 : 0,
            ':sort_order' => (int)($rule['sort_order'] ?? 0),
        ]);
    }

    private function normalizeRuleInput(array $rule, int $sortOrder): ?array
    {
        $type = strtolower(trim((string)($rule['rule_type'] ?? $rule['type'] ?? '')));
        if (!in_array($type, [self::RULE_DAILY, self::RULE_STREAK, self::RULE_TOTAL, self::RULE_MONTHLY], true)) {
            return null;
        }

        $triggerDay = (int)($rule['trigger_day'] ?? $rule['day'] ?? 1);
        if ($type === self::RULE_DAILY) {
            $triggerDay = 1;
        }
        if ($triggerDay < 1) {
            $triggerDay = 1;
        }

        $mailTitle = $this->cutString(trim((string)($rule['mail_title'] ?? '')), 120);
        $mailIcon = $this->cutString(trim((string)($rule['mail_icon'] ?? 'BOOK')), 64);
        if ($mailIcon === '') {
            $mailIcon = 'BOOK';
        }

        $mailContentRaw = $rule['mail_content'] ?? [];
        if (is_string($mailContentRaw)) {
            $mailContentRaw = preg_split('/\r\n|\r|\n/', $mailContentRaw) ?: [];
        }
        $mailContent = $this->normalizeMailContent(is_array($mailContentRaw) ? $mailContentRaw : []);

        $itemsRaw = $rule['items'] ?? [];
        if (is_string($itemsRaw) && trim($itemsRaw) !== '') {
            $decoded = json_decode($itemsRaw, true);
            if (is_array($decoded)) {
                $itemsRaw = $decoded;
            }
        }
        $items = $this->normalizeItems(is_array($itemsRaw) ? $itemsRaw : []);

        $commandsRaw = $rule['commands'] ?? [];
        if (is_string($commandsRaw) && trim($commandsRaw) !== '') {
            $decoded = json_decode($commandsRaw, true);
            if (is_array($decoded)) {
                $commandsRaw = $decoded;
            } else {
                $commandsRaw = preg_split('/\r\n|\r|\n/', $commandsRaw) ?: [];
            }
        }
        $commands = $this->normalizeCommandsForStorage(is_array($commandsRaw) ? $commandsRaw : []);

        $enabled = $this->normalizeBoolInt($rule['enabled'] ?? null, 1) === 1;

        return [
            'rule_type' => $type,
            'trigger_day' => $triggerDay,
            'mail_title' => $mailTitle,
            'mail_icon' => $mailIcon,
            'mail_content' => $mailContent,
            'items' => $items,
            'commands' => $commands,
            'enabled' => $enabled,
            'sort_order' => $sortOrder,
        ];
    }

    private function normalizeRuleRow(array $row): array
    {
        $mailContent = $this->decodeJsonList($row['mail_content_json'] ?? []);
        $items = $this->normalizeItems($this->decodeJsonList($row['items_json'] ?? []));
        $commands = $this->normalizeCommandsForStorage($this->decodeJsonList($row['commands_json'] ?? []));

        return [
            'id' => (int)($row['id'] ?? 0),
            'config_id' => (int)($row['config_id'] ?? 0),
            'rule_type' => strtolower(trim((string)($row['rule_type'] ?? self::RULE_DAILY))),
            'trigger_day' => max(1, (int)($row['trigger_day'] ?? 1)),
            'mail_title' => trim((string)($row['mail_title'] ?? '')),
            'mail_icon' => trim((string)($row['mail_icon'] ?? 'BOOK')) ?: 'BOOK',
            'mail_content' => $this->normalizeMailContent($mailContent),
            'items' => $items,
            'commands' => $commands,
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'created_at' => trim((string)($row['created_at'] ?? '')),
            'updated_at' => trim((string)($row['updated_at'] ?? '')),
        ];
    }

    private function normalizeConfigRow(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => trim((string)($row['name'] ?? '')),
            'status' => trim((string)($row['status'] ?? self::STATUS_DRAFT)),
            'effective_date' => $this->normalizeDate((string)($row['effective_date'] ?? '')),
            'is_default' => (int)($row['is_default'] ?? 0) === 1,
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => trim((string)($row['created_at'] ?? '')),
            'updated_at' => trim((string)($row['updated_at'] ?? '')),
        ];
    }

    private function composeRewardPayload(
        array $rules,
        string $signDate,
        int $continuous,
        int $total,
        int $monthDays,
        array $context
    ): array {
        $matched = $this->matchRules($rules, $continuous, $total, $monthDays);
        $default = $this->defaultRewardTemplate();
        $primary = $matched !== [] ? $matched[0] : null;

        $placeholderValues = [
            'player' => (string)($context['player'] ?? ''),
            'uuid' => (string)($context['uuid'] ?? ''),
            'date' => $signDate,
            'continuous' => (string)$continuous,
            'total' => (string)$total,
            'month_days' => (string)$monthDays,
            'server_id' => (string)($context['server_id'] ?? $this->defaultServerId()),
        ];

        $mailTitle = trim((string)($primary['mail_title'] ?? ($default['mail']['title'] ?? '每日签到奖励')));
        if ($mailTitle === '') {
            $mailTitle = (string)($default['mail']['title'] ?? '每日签到奖励');
        }
        $mailIcon = trim((string)($primary['mail_icon'] ?? ($default['mail']['icon'] ?? 'BOOK')));
        if ($mailIcon === '') {
            $mailIcon = (string)($default['mail']['icon'] ?? 'BOOK');
        }

        $mailContent = [];
        if ($primary !== null) {
            $mailContent = $this->normalizeMailContent((array)($primary['mail_content'] ?? []));
        }
        if ($mailContent === []) {
            $mailContent = $this->normalizeMailContent((array)($default['mail']['content'] ?? []));
        }

        for ($i = 1; $i < count($matched); $i++) {
            $append = $this->normalizeMailContent((array)($matched[$i]['mail_content'] ?? []));
            foreach ($append as $line) {
                $mailContent[] = $line;
            }
        }

        if ($mailContent === []) {
            $mailContent = [
                '你完成了 {date} 的每日签到。',
                '连续签到：{continuous} 天',
                '累计签到：{total} 次',
            ];
        }

        $mailContentRendered = [];
        foreach ($mailContent as $line) {
            $rendered = $this->renderTemplateLine($line, $placeholderValues);
            if ($rendered !== '') {
                $mailContentRendered[] = $rendered;
            }
        }
        if ($mailContentRendered === []) {
            $mailContentRendered = [
                $this->renderTemplateLine('你完成了 {date} 的每日签到。', $placeholderValues),
            ];
        }

        $itemGroups = [];
        $commandGroups = [];
        foreach ($matched as $rule) {
            $itemGroups[] = is_array($rule['items'] ?? null) ? $rule['items'] : [];
            $commandGroups[] = is_array($rule['commands'] ?? null) ? $rule['commands'] : [];
        }

        $mergedItems = $this->mergeItems($itemGroups);
        $mergedCommands = $this->mergeCommands($commandGroups, $placeholderValues);

        $metaMatchedRules = [];
        foreach ($matched as $rule) {
            $metaMatchedRules[] = [
                'rule_id' => (int)($rule['id'] ?? 0),
                'rule_type' => (string)($rule['rule_type'] ?? ''),
                'trigger_day' => (int)($rule['trigger_day'] ?? 0),
            ];
        }

        $payload = [
            'mail' => [
                'title' => $this->renderTemplateLine($mailTitle, $placeholderValues),
                'icon' => $mailIcon,
                'content' => $mailContentRendered,
            ],
            'items' => $mergedItems,
            'commands' => $mergedCommands,
            'meta' => [
                'signDate' => $signDate,
                'continuous' => max(1, $continuous),
                'total' => max(1, $total),
                'month_days' => max(1, $monthDays),
                'source' => trim((string)($context['source'] ?? 'web_queue')) ?: 'web_queue',
                'matchedRules' => $metaMatchedRules,
                'configId' => (int)($context['config_id'] ?? 0),
                'configName' => (string)($context['config_name'] ?? ''),
                'configStatus' => (string)($context['config_status'] ?? ''),
            ],
        ];

        if ($payload['items'] === [] && $payload['commands'] === []) {
            $payload['meta']['rewardEmpty'] = true;
        }

        return $payload;
    }

    private function matchRules(array $rules, int $continuous, int $total, int $monthDays): array
    {
        $daily = [];
        $streak = [];
        $totalRules = [];
        $monthly = [];

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['enabled'])) {
                continue;
            }

            $type = strtolower(trim((string)($rule['rule_type'] ?? '')));
            $triggerDay = max(1, (int)($rule['trigger_day'] ?? 1));
            switch ($type) {
                case self::RULE_DAILY:
                    $daily[] = $rule;
                    break;
                case self::RULE_STREAK:
                    if ($triggerDay === $continuous) {
                        $streak[] = $rule;
                    }
                    break;
                case self::RULE_TOTAL:
                    if ($triggerDay === $total) {
                        $totalRules[] = $rule;
                    }
                    break;
                case self::RULE_MONTHLY:
                    if ($triggerDay === $monthDays) {
                        $monthly[] = $rule;
                    }
                    break;
                default:
                    break;
            }
        }

        $sort = static function (array $left, array $right): int {
            $leftOrder = (int)($left['sort_order'] ?? 0);
            $rightOrder = (int)($right['sort_order'] ?? 0);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
        };

        usort($daily, $sort);
        usort($streak, $sort);
        usort($totalRules, $sort);
        usort($monthly, $sort);

        return array_merge($daily, $streak, $totalRules, $monthly);
    }

    private function mergeItems(array $itemGroups): array
    {
        $merged = [];
        $positions = [];

        foreach ($itemGroups as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = trim((string)($item['type'] ?? ''));
                if ($type === '') {
                    continue;
                }
                $amount = max(1, (int)($item['amount'] ?? 1));

                if (!array_key_exists($type, $positions)) {
                    $positions[$type] = count($merged);
                    $merged[] = [
                        'type' => $type,
                        'amount' => $amount,
                    ];
                    continue;
                }

                $index = (int)$positions[$type];
                $merged[$index]['amount'] = max(1, (int)($merged[$index]['amount'] ?? 0) + $amount);
            }
        }

        return $merged;
    }

    private function mergeCommands(array $commandGroups, array $placeholders): array
    {
        $merged = [];
        $seen = [];

        foreach ($commandGroups as $commands) {
            if (!is_array($commands)) {
                continue;
            }
            foreach ($commands as $command) {
                if (!is_scalar($command)) {
                    continue;
                }
                $text = trim((string)$command);
                if ($text === '' || preg_match('/[\r\n]/', $text) === 1) {
                    continue;
                }

                $text = ltrim($text, '/');
                $text = trim($text);
                if ($text === '') {
                    continue;
                }

                $rendered = $this->renderTemplateLine($text, $placeholders);
                if ($rendered === '' || preg_match('/[\r\n]/', $rendered) === 1) {
                    continue;
                }

                if (isset($seen[$rendered])) {
                    continue;
                }
                $seen[$rendered] = true;
                $merged[] = $rendered;
            }
        }

        return $merged;
    }

    private function renderTemplateLine(string $text, array $placeholders): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $map = [
            '{player}' => (string)($placeholders['player'] ?? ''),
            '{uuid}' => (string)($placeholders['uuid'] ?? ''),
            '{date}' => (string)($placeholders['date'] ?? ''),
            '{continuous}' => (string)($placeholders['continuous'] ?? ''),
            '{total}' => (string)($placeholders['total'] ?? ''),
            '{month_days}' => (string)($placeholders['month_days'] ?? ''),
            '{server_id}' => (string)($placeholders['server_id'] ?? ''),
        ];

        return strtr($text, $map);
    }

    private function defaultRewardTemplate(): array
    {
        $mailTitle = defined('SIGNIN_REWARD_MAIL_TITLE')
            ? trim((string)SIGNIN_REWARD_MAIL_TITLE)
            : '每日签到奖励';
        if ($mailTitle === '') {
            $mailTitle = '每日签到奖励';
        }

        $mailIcon = defined('SIGNIN_REWARD_MAIL_ICON')
            ? trim((string)SIGNIN_REWARD_MAIL_ICON)
            : 'BOOK';
        if ($mailIcon === '') {
            $mailIcon = 'BOOK';
        }

        $mailContent = [
            '你完成了 {date} 的每日签到。',
            '连续签到：{continuous} 天',
            '累计签到：{total} 次',
            '奖励已通过系统邮件投递，请上线后在邮箱中领取。',
        ];
        if (defined('SIGNIN_REWARD_MAIL_CONTENT') && is_array(SIGNIN_REWARD_MAIL_CONTENT)) {
            $normalizedContent = $this->normalizeMailContent(SIGNIN_REWARD_MAIL_CONTENT);
            if ($normalizedContent !== []) {
                $mailContent = $normalizedContent;
            }
        }

        $items = [['type' => 'minecraft:diamond', 'amount' => 1]];
        if (defined('SIGNIN_REWARD_ITEMS') && is_array(SIGNIN_REWARD_ITEMS)) {
            $normalizedItems = $this->normalizeItems(SIGNIN_REWARD_ITEMS);
            if ($normalizedItems !== []) {
                $items = $normalizedItems;
            }
        }

        $commands = [];
        if (defined('SIGNIN_REWARD_COMMANDS') && is_array(SIGNIN_REWARD_COMMANDS)) {
            $commands = $this->normalizeCommandsForStorage(SIGNIN_REWARD_COMMANDS);
        }

        return [
            'mail' => [
                'title' => $mailTitle,
                'icon' => $mailIcon,
                'content' => $mailContent,
            ],
            'items' => $items,
            'commands' => $commands,
        ];
    }

    private function normalizeMailContent(array $lines): array
    {
        $safe = [];
        foreach ($lines as $line) {
            if (!is_scalar($line)) {
                continue;
            }
            $text = trim((string)$line);
            if ($text !== '') {
                $safe[] = $text;
            }
        }

        return $safe;
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = trim((string)($item['type'] ?? $item['name'] ?? ''));
            if ($type === '') {
                continue;
            }
            $normalized[] = [
                'type' => $type,
                'amount' => max(1, (int)($item['amount'] ?? 1)),
            ];
        }

        return $normalized;
    }

    private function normalizeCommandsForStorage(array $commands): array
    {
        $normalized = [];
        $seen = [];

        foreach ($commands as $command) {
            if (!is_scalar($command)) {
                continue;
            }
            $text = trim((string)$command);
            if ($text === '' || preg_match('/[\r\n]/', $text) === 1) {
                continue;
            }
            $text = ltrim($text, '/');
            $text = trim($text);
            if ($text === '') {
                continue;
            }

            if (isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $normalized[] = $text;
        }

        return $normalized;
    }

    private function readFeatureSettings(): array
    {
        $defaults = [
            'test_send_enabled' => 1,
            'admin_repeat_test_enabled' => 0,
        ];

        if (!$this->tableExists('site_settings')) {
            return $defaults;
        }

        $stmt = $this->db->prepare('
            SELECT setting_key, setting_value
            FROM site_settings
            WHERE setting_key = :test_send
               OR setting_key = :repeat_test
        ');
        $stmt->execute([
            ':test_send' => self::TEST_SEND_SETTING_KEY,
            ':repeat_test' => self::REPEAT_TEST_SETTING_KEY,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            $value = trim((string)($row['setting_value'] ?? ''));
            if ($key === self::TEST_SEND_SETTING_KEY) {
                $defaults['test_send_enabled'] = $value === '0' ? 0 : 1;
            }
            if ($key === self::REPEAT_TEST_SETTING_KEY) {
                $defaults['admin_repeat_test_enabled'] = $value === '1' ? 1 : 0;
            }
        }

        return $defaults;
    }

    private function resolveTestTarget(int $adminUserId, array $input): array
    {
        $targetName = trim((string)($input['target_player_name'] ?? ''));
        $targetUuid = MinecraftUuid::normalizeToDashed((string)($input['target_player_uuid'] ?? ''));

        $defaultUser = $this->fetchUserById($adminUserId);
        $defaultName = trim((string)($defaultUser['mc_username'] ?? ''));
        $defaultUuid = MinecraftUuid::normalizeToDashed((string)($defaultUser['mc_uuid'] ?? ''));

        if ($targetName === '' && $targetUuid === '') {
            $targetName = $defaultName;
            $targetUuid = $defaultUuid;
        }

        $resolvedUser = null;
        if ($targetUuid !== '') {
            $resolvedUser = $this->fetchUserByUuid($targetUuid);
        }
        if ($resolvedUser === null && $targetName !== '') {
            $resolvedUser = $this->fetchUserByName($targetName);
        }

        if ($targetUuid === '' && $resolvedUser !== null) {
            $targetUuid = MinecraftUuid::normalizeToDashed((string)($resolvedUser['mc_uuid'] ?? ''));
        }
        if ($targetName === '' && $resolvedUser !== null) {
            $targetName = trim((string)($resolvedUser['mc_username'] ?? ''));
        }

        if ($targetName === '' || $targetUuid === '') {
            return [
                'ok' => false,
                'status' => 400,
                'message' => '测试发送需要有效的 Minecraft 玩家名和 UUID',
            ];
        }

        if (preg_match('/^[a-zA-Z0-9_]{1,16}$/', $targetName) !== 1) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Minecraft 玩家名格式无效',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'player_name' => $targetName,
            'player_uuid' => $targetUuid,
            'website_user_id' => $resolvedUser !== null ? (int)($resolvedUser['id'] ?? 0) : max(0, $adminUserId),
        ];
    }

    private function fetchUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, mc_username, mc_uuid FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchUserByUuid(string $uuid): ?array
    {
        if ($uuid === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, mc_username, mc_uuid FROM users WHERE mc_uuid = :uuid LIMIT 1');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchUserByName(string $name): ?array
    {
        if ($name === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, mc_username, mc_uuid FROM users WHERE mc_username = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function upsertSiteSetting(string $settingKey, string $settingValue, string $description = ''): void
    {
        if (!$this->tableExists('site_settings')) {
            return;
        }

        $stmt = $this->db->prepare('SELECT id FROM site_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute([':setting_key' => $settingKey]);
        $exists = $stmt->fetchColumn();
        if ($exists !== false) {
            $update = $this->db->prepare('UPDATE site_settings SET setting_value = :setting_value WHERE setting_key = :setting_key LIMIT 1');
            $update->execute([
                ':setting_value' => $settingValue,
                ':setting_key' => $settingKey,
            ]);
            return;
        }

        $insert = $this->db->prepare('
            INSERT INTO site_settings (setting_key, setting_value, description)
            VALUES (:setting_key, :setting_value, :description)
        ');
        $insert->execute([
            ':setting_key' => $settingKey,
            ':setting_value' => $settingValue,
            ':description' => $this->cutString($description, 255),
        ]);
    }

    private function groupRulesByType(array $rules): array
    {
        $grouped = [
            self::RULE_DAILY => [],
            self::RULE_STREAK => [],
            self::RULE_TOTAL => [],
            self::RULE_MONTHLY => [],
        ];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $type = strtolower(trim((string)($rule['rule_type'] ?? '')));
            if (!isset($grouped[$type])) {
                continue;
            }
            $grouped[$type][] = $rule;
        }

        return $grouped;
    }

    private function decodeJsonList($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        $text = trim((string)$payload);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJson($payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '[]';
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $timestamp = strtotime($value . ' 00:00:00');
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeBoolInt($value, ?int $default): ?int
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $text = strtolower(trim((string)$value));
        if ($text === '') {
            return $default;
        }

        if (in_array($text, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
            return 1;
        }

        if (in_array($text, ['0', 'false', 'no', 'off', 'disabled'], true)) {
            return 0;
        }

        return $default;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ');
        $stmt->execute([':table_name' => $tableName]);
        return $stmt->fetchColumn() !== false;
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ');
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function tableHasIndex(string $tableName, string $indexName): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
            LIMIT 1
        ');
        $stmt->execute([
            ':table_name' => $tableName,
            ':index_name' => $indexName,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function cutString(string $value, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }

    private function defaultServerId(): string
    {
        return defined('SIGNIN_SERVER_ID') ? trim((string)SIGNIN_SERVER_ID) : 'survival-1';
    }
}
