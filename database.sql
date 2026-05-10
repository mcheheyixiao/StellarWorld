-- MySQL schema for Minecraft web system
--
-- 缓存 / 会话 / 限流优先使用 Redis（phpredis / predis），不可用时回退到文件存储。
-- 文件缓存默认写入 storage/cache 下的 JSON 文件；执行 DDL 前请确认目录可写。

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    mc_username VARCHAR(64) NULL DEFAULT NULL COMMENT '当前绑定的 Minecraft 用户名',
    mc_uuid VARCHAR(64) NULL DEFAULT NULL COMMENT '当前绑定的 Minecraft UUID',
    ip VARCHAR(40) DEFAULT NULL,
    lastlogin BIGINT DEFAULT NULL,
    regip VARCHAR(40) DEFAULT NULL,
    regdate BIGINT DEFAULT NULL,
    isLogged SMALLINT DEFAULT 0,
    hasSession SMALLINT DEFAULT 0,
    totp VARCHAR(32) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('player','admin') NOT NULL DEFAULT 'player',
    status ENUM('active','frozen','banned') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    mua_sub VARCHAR(255) UNIQUE DEFAULT NULL COMMENT 'MUA Union 用户唯一标识',
    last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT '最近一次绑定 Minecraft 账号的时间',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_users_email_verified (email_verified),
    UNIQUE KEY uq_users_mc_uuid (mc_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AuthMe Reloaded compatibility (existing database upgrade)
-- ALTER TABLE users
--   ADD COLUMN ip VARCHAR(40) DEFAULT NULL,
--   ADD COLUMN lastlogin BIGINT DEFAULT NULL,
--   ADD COLUMN regip VARCHAR(40) DEFAULT NULL,
--   ADD COLUMN regdate BIGINT DEFAULT NULL,
--   ADD COLUMN isLogged SMALLINT DEFAULT 0,
--   ADD COLUMN hasSession SMALLINT DEFAULT 0,
--   ADD COLUMN totp VARCHAR(32) DEFAULT NULL;
--   ADD COLUMN last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT '最近一次绑定 Minecraft 账号的时间';

-- Safe upgrade for existing databases: add users.last_mc_bind_at if missing
SET @has_last_mc_bind_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'last_mc_bind_at'
);
SET @add_last_mc_bind_sql := IF(
    @has_last_mc_bind_at = 0,
    'ALTER TABLE users ADD COLUMN last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT ''最近一次绑定 Minecraft 账号的时间'' AFTER email_verified',
    'SELECT ''users.last_mc_bind_at already exists'' AS message'
);
PREPARE stmt_add_last_mc_bind FROM @add_last_mc_bind_sql;
EXECUTE stmt_add_last_mc_bind;
DEALLOCATE PREPARE stmt_add_last_mc_bind;

-- Safe upgrade: ensure users.mc_uuid has a unique index for one-to-one Minecraft bindings.
-- MySQL UNIQUE allows multiple NULL values, but duplicate empty strings ('') will still fail.
-- If this migration fails on an old database, manually inspect duplicate UUID values including empty strings:
-- SELECT mc_uuid, COUNT(*) AS c
-- FROM users
-- WHERE mc_uuid IS NOT NULL
-- GROUP BY mc_uuid
-- HAVING c > 1;
--
-- If many legacy rows have mc_uuid = '', manually review them and convert empty strings to NULL before adding the unique index:
-- UPDATE users SET mc_uuid = NULL WHERE mc_uuid = '';
-- Do NOT run cleanup SQL blindly on production; back up the database first.
SET @has_unique_users_mc_uuid := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'mc_uuid'
      AND NON_UNIQUE = 0
);
SET @add_uq_users_mc_uuid_sql := IF(
    @has_unique_users_mc_uuid = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_mc_uuid (mc_uuid)',
    'SELECT ''users.mc_uuid already has a unique index'' AS message'
);
PREPARE stmt_add_uq_users_mc_uuid FROM @add_uq_users_mc_uuid_sql;
EXECUTE stmt_add_uq_users_mc_uuid;
DEALLOCATE PREPARE stmt_add_uq_users_mc_uuid;

-- Safe upgrade for existing databases: add users.mua_sub if missing
SET @has_mua_sub := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'mua_sub'
);
SET @add_mua_sub_sql := IF(
    @has_mua_sub = 0,
    'ALTER TABLE users ADD COLUMN mua_sub VARCHAR(255) UNIQUE DEFAULT NULL COMMENT ''MUA Union 用户唯一标识'' AFTER email_verified',
    'SELECT ''users.mua_sub already exists'' AS message'
);
PREPARE stmt_add_mua_sub FROM @add_mua_sub_sql;
EXECUTE stmt_add_mua_sub;
DEALLOCATE PREPARE stmt_add_mua_sub;

-- Safe upgrade for existing databases: widen users.mua_sub to VARCHAR(255)
SET @mua_sub_char_len := (
    SELECT CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'mua_sub'
    LIMIT 1
);
SET @alter_mua_sub_len_sql := IF(
    @mua_sub_char_len IS NOT NULL AND @mua_sub_char_len < 255,
    'ALTER TABLE users MODIFY COLUMN mua_sub VARCHAR(255) DEFAULT NULL COMMENT ''MUA Union 用户唯一标识''',
    'SELECT ''users.mua_sub length already compatible'' AS message'
);
PREPARE stmt_alter_mua_sub_len FROM @alter_mua_sub_len_sql;
EXECUTE stmt_alter_mua_sub_len;
DEALLOCATE PREPARE stmt_alter_mua_sub_len;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_login_attempts_ip_time (ip_address, created_at),
    INDEX idx_login_attempts_user_time (username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_reg_limits_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    token VARCHAR(64) NULL UNIQUE,
    purpose VARCHAR(32) NOT NULL DEFAULT 'register',
    email VARCHAR(255) NULL DEFAULT NULL,
    code_hash VARCHAR(255) NULL DEFAULT NULL,
    expires_at DATETIME NULL DEFAULT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ip_hash CHAR(64) NOT NULL DEFAULT '',
    user_agent_hash CHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    KEY idx_email_verifications_email_purpose_created (email, purpose, created_at),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe upgrade for existing databases: allow pending email-code rows without a user yet
SET @email_verifications_user_id_nullable := (
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'user_id'
    LIMIT 1
);
SET @alter_email_verifications_user_id_sql := IF(
    @email_verifications_user_id_nullable = 'NO',
    'ALTER TABLE email_verifications MODIFY COLUMN user_id INT UNSIGNED NULL',
    'SELECT ''email_verifications.user_id already nullable'' AS message'
);
PREPARE stmt_alter_email_verifications_user_id FROM @alter_email_verifications_user_id_sql;
EXECUTE stmt_alter_email_verifications_user_id;
DEALLOCATE PREPARE stmt_alter_email_verifications_user_id;

-- Safe upgrade for existing databases: allow NULL token for numeric email-code rows
SET @email_verifications_token_nullable := (
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'token'
    LIMIT 1
);
SET @alter_email_verifications_token_sql := IF(
    @email_verifications_token_nullable = 'NO',
    'ALTER TABLE email_verifications MODIFY COLUMN token VARCHAR(64) NULL',
    'SELECT ''email_verifications.token already nullable'' AS message'
);
PREPARE stmt_alter_email_verifications_token FROM @alter_email_verifications_token_sql;
EXECUTE stmt_alter_email_verifications_token;
DEALLOCATE PREPARE stmt_alter_email_verifications_token;

SET @has_email_verifications_purpose := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'purpose'
);
SET @add_email_verifications_purpose_sql := IF(
    @has_email_verifications_purpose = 0,
    'ALTER TABLE email_verifications ADD COLUMN purpose VARCHAR(32) NOT NULL DEFAULT ''register'' AFTER token',
    'SELECT ''email_verifications.purpose already exists'' AS message'
);
PREPARE stmt_add_email_verifications_purpose FROM @add_email_verifications_purpose_sql;
EXECUTE stmt_add_email_verifications_purpose;
DEALLOCATE PREPARE stmt_add_email_verifications_purpose;

SET @has_email_verifications_email := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'email'
);
SET @add_email_verifications_email_sql := IF(
    @has_email_verifications_email = 0,
    'ALTER TABLE email_verifications ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL AFTER purpose',
    'SELECT ''email_verifications.email already exists'' AS message'
);
PREPARE stmt_add_email_verifications_email FROM @add_email_verifications_email_sql;
EXECUTE stmt_add_email_verifications_email;
DEALLOCATE PREPARE stmt_add_email_verifications_email;

SET @has_email_verifications_code_hash := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'code_hash'
);
SET @add_email_verifications_code_hash_sql := IF(
    @has_email_verifications_code_hash = 0,
    'ALTER TABLE email_verifications ADD COLUMN code_hash VARCHAR(255) NULL DEFAULT NULL AFTER email',
    'SELECT ''email_verifications.code_hash already exists'' AS message'
);
PREPARE stmt_add_email_verifications_code_hash FROM @add_email_verifications_code_hash_sql;
EXECUTE stmt_add_email_verifications_code_hash;
DEALLOCATE PREPARE stmt_add_email_verifications_code_hash;

SET @has_email_verifications_expires_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'expires_at'
);
SET @add_email_verifications_expires_at_sql := IF(
    @has_email_verifications_expires_at = 0,
    'ALTER TABLE email_verifications ADD COLUMN expires_at DATETIME NULL DEFAULT NULL AFTER code_hash',
    'SELECT ''email_verifications.expires_at already exists'' AS message'
);
PREPARE stmt_add_email_verifications_expires_at FROM @add_email_verifications_expires_at_sql;
EXECUTE stmt_add_email_verifications_expires_at;
DEALLOCATE PREPARE stmt_add_email_verifications_expires_at;

SET @has_email_verifications_attempts := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'attempts'
);
SET @add_email_verifications_attempts_sql := IF(
    @has_email_verifications_attempts = 0,
    'ALTER TABLE email_verifications ADD COLUMN attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER expires_at',
    'SELECT ''email_verifications.attempts already exists'' AS message'
);
PREPARE stmt_add_email_verifications_attempts FROM @add_email_verifications_attempts_sql;
EXECUTE stmt_add_email_verifications_attempts;
DEALLOCATE PREPARE stmt_add_email_verifications_attempts;

SET @has_email_verifications_ip_hash := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'ip_hash'
);
SET @add_email_verifications_ip_hash_sql := IF(
    @has_email_verifications_ip_hash = 0,
    'ALTER TABLE email_verifications ADD COLUMN ip_hash CHAR(64) NOT NULL DEFAULT '''' AFTER attempts',
    'SELECT ''email_verifications.ip_hash already exists'' AS message'
);
PREPARE stmt_add_email_verifications_ip_hash FROM @add_email_verifications_ip_hash_sql;
EXECUTE stmt_add_email_verifications_ip_hash;
DEALLOCATE PREPARE stmt_add_email_verifications_ip_hash;

SET @has_email_verifications_user_agent_hash := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND COLUMN_NAME = 'user_agent_hash'
);
SET @add_email_verifications_user_agent_hash_sql := IF(
    @has_email_verifications_user_agent_hash = 0,
    'ALTER TABLE email_verifications ADD COLUMN user_agent_hash CHAR(64) NOT NULL DEFAULT '''' AFTER ip_hash',
    'SELECT ''email_verifications.user_agent_hash already exists'' AS message'
);
PREPARE stmt_add_email_verifications_user_agent_hash FROM @add_email_verifications_user_agent_hash_sql;
EXECUTE stmt_add_email_verifications_user_agent_hash;
DEALLOCATE PREPARE stmt_add_email_verifications_user_agent_hash;

SET @has_idx_email_verifications_email_purpose_created := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_verifications'
      AND INDEX_NAME = 'idx_email_verifications_email_purpose_created'
);
SET @add_idx_email_verifications_email_purpose_created_sql := IF(
    @has_idx_email_verifications_email_purpose_created = 0,
    'ALTER TABLE email_verifications ADD INDEX idx_email_verifications_email_purpose_created (email, purpose, created_at)',
    'SELECT ''idx_email_verifications_email_purpose_created already exists'' AS message'
);
PREPARE stmt_add_idx_email_verifications_email_purpose_created FROM @add_idx_email_verifications_email_purpose_created_sql;
EXECUTE stmt_add_idx_email_verifications_email_purpose_created;
DEALLOCATE PREPARE stmt_add_idx_email_verifications_email_purpose_created;

-- 密码重置令牌记录
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    INDEX idx_password_resets_email (email),
    INDEX idx_password_resets_token_time (token, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_announcements_published (is_published, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin seed (disabled by default for production safety)
-- 请在生产环境执行前替换默认管理员账号、邮箱与密码哈希，或拆分到单独的 seed.sql。
-- Admin seed is intentionally disabled for production safety.
-- First deployment should create an admin account manually (CLI or secure bootstrap flow).
-- INSERT INTO users (username, email, password_hash, role, status, email_verified, created_at, updated_at)
-- VALUES ('change_me_admin', 'change_me_admin@example.com', '$2y$10$replace_with_real_hash', 'admin', 'active', 1, NOW(), NOW());

CREATE TABLE IF NOT EXISTS milestones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    milestone_date VARCHAR(50) NOT NULL COMMENT '里程碑日期（如 2024 年 1 月）',
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_milestones_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT '关联用户 ID；NULL 表示系统或匿名操作',
    `action` VARCHAR(50) NOT NULL COMMENT '操作类型，如 LOGIN、REGISTER、API_AUTH',
    `ip_address` VARCHAR(45) NOT NULL,
    `details` JSON NULL COMMENT '附加详情 JSON；写入前应完成脱敏',
    `request_id` VARCHAR(32) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_audit_logs_request_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'audit_logs'
      AND COLUMN_NAME = 'request_id'
);
SET @add_audit_logs_request_id_sql := IF(
    @has_audit_logs_request_id = 0,
    'ALTER TABLE audit_logs ADD COLUMN request_id VARCHAR(32) NULL DEFAULT NULL AFTER details',
    'SELECT ''audit_logs.request_id already exists'' AS message'
);
PREPARE stmt_add_audit_logs_request_id FROM @add_audit_logs_request_id_sql;
EXECUTE stmt_add_audit_logs_request_id;
DEALLOCATE PREPARE stmt_add_audit_logs_request_id;

CREATE TABLE IF NOT EXISTS player_stats (
    mc_uuid VARCHAR(64) NOT NULL,
    username VARCHAR(64) NOT NULL,
    play_time_ticks BIGINT NOT NULL DEFAULT 0,
    fly_distance_cm BIGINT NOT NULL DEFAULT 0,
    deaths INT NOT NULL DEFAULT 0,
    blocks_mined BIGINT NOT NULL DEFAULT 0,
    blocks_placed BIGINT NOT NULL DEFAULT 0,
    fish_caught INT NOT NULL DEFAULT 0,
    player_kills INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (mc_uuid),
    CONSTRAINT fk_player_stats_user FOREIGN KEY (mc_uuid)
        REFERENCES users(mc_uuid)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 为 users 表补充 coins 字段，兼容旧数据库升级
SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'coins'
        ),
        'SELECT 1',
        'ALTER TABLE users ADD COLUMN coins INT NOT NULL DEFAULT 0 COMMENT ''网页硬币/积分'''
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- 每日签到记录
CREATE TABLE IF NOT EXISTS user_checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_checkins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_checkins_user_date (user_id, checkin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 为签到与审计日志补充复合索引，兼容旧数据库升级
SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'user_checkins'
              AND INDEX_NAME = 'idx_user_checkins_user_created_at'
        ),
        'SELECT 1',
        'ALTER TABLE user_checkins ADD INDEX idx_user_checkins_user_created_at (user_id, created_at)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'audit_logs'
              AND INDEX_NAME = 'idx_audit_logs_user_created_at'
        ),
        'SELECT 1',
        'ALTER TABLE audit_logs ADD INDEX idx_audit_logs_user_created_at (user_id, created_at)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
INSERT INTO checkin_reward_rules (day, scope, coins, points, items_json, commands_json, enabled, created_at, updated_at)
SELECT
    1,
    'daily',
    120,
    0,
    '[{"id":"minecraft:iron_ingot","amount":10}]',
    '[]',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM checkin_reward_rules
    LIMIT 1
);

-- Remember Me tokens for persistent multi-device login
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    selector VARCHAR(24) NOT NULL,
    validator_hash VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_auth_tokens_selector (selector),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avatar_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL,
    size_px SMALLINT UNSIGNED NOT NULL DEFAULT 32,
    source_url VARCHAR(512) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    storage_rel_path VARCHAR(255) NOT NULL,
    mime VARCHAR(64) NOT NULL DEFAULT 'image/png',
    http_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    fetched_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_avatar_cache_username_size (username, size_px),
    INDEX idx_avatar_cache_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_blacklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_cidr VARCHAR(45) NOT NULL COMMENT '支持 IPv4/IPv6 CIDR，例如 192.168.1.0/24',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_ip_blacklist_ip_cidr (ip_cidr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 站点设置表，用于后台可配置的运行时选项
CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uq_site_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 关于页成员列表数据，兼容旧 public/assets/memberlist/whitelist.json 配置
CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL COMMENT 'Minecraft 玩家用户名',
    role VARCHAR(128) NOT NULL DEFAULT '服务器成员' COMMENT '职位名称，用于前台展示',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 注册 IP 白名单；命中后可配合 site_settings 放宽频率限制
CREATE TABLE IF NOT EXISTS ip_whitelist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_cidr VARCHAR(45) NOT NULL COMMENT '支持 IPv4/IPv6 CIDR',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_ip_whitelist_ip_cidr (ip_cidr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value, description) VALUES
    ('register_ip_limit', '2', '同一 IP 24 小时内允许注册的账号数量；命中后拒绝继续注册'),
    ('whitelist_ignores_rate_limit', '0', '是否允许白名单 IP 跳过 checkRateLimit 限制：1 启用，0 禁用')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO site_settings (setting_key, setting_value, description) VALUES
    ('email_domain_whitelist_enabled', '1', '是否启用邮箱域名白名单：1 启用，0 禁用'),
    ('email_domain_whitelist', 'qq.com,foxmail.com,163.com,126.com,gmail.com,outlook.com,hotmail.com,icloud.com,yahoo.com', '允许注册的邮箱域名列表，使用逗号分隔'),
    ('email_code_expire_seconds', '600', '邮箱验证码有效期，单位为秒'),
    ('email_code_send_cooldown_seconds', '60', '同一邮箱发送验证码的冷却时间，单位为秒'),
    ('audit_log_storage', 'mysql', '审计日志存储模式：mysql / file / both')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Realtime status history snapshots (WS primary source, DB fallback)
CREATE TABLE IF NOT EXISTS server_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(32) NOT NULL DEFAULT 'ws-api',
    payload_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_server_status_history_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback ticket system
CREATE TABLE IF NOT EXISTS player_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(64) NOT NULL,
    mc_username VARCHAR(64) NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'other',
    target_player VARCHAR(64) NULL,
    title VARCHAR(120) NOT NULL,
    content TEXT NOT NULL,
    world VARCHAR(64) NULL,
    coordinates VARCHAR(64) NULL,
    occurred_at DATETIME NULL,
    evidence_url VARCHAR(500) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    admin_reply TEXT NULL,
    user_supplement TEXT NULL,
    supplemented_at DATETIME NULL,
    handled_by INT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_feedback_user_id (user_id),
    INDEX idx_feedback_status (status),
    INDEX idx_feedback_category (category),
    INDEX idx_feedback_created_at (created_at),
    INDEX idx_feedback_search (username, mc_username, target_player),
    CONSTRAINT fk_player_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_feedback_handled_user FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_feedback_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feedback_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_attachments_feedback_id (feedback_id),
    CONSTRAINT fk_feedback_attachments_feedback FOREIGN KEY (feedback_id) REFERENCES player_feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback compatibility patch (idempotent)
SET @has_feedback_user_supplement := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'player_feedback'
      AND COLUMN_NAME = 'user_supplement'
);
SET @sql_add_feedback_user_supplement := IF(
    @has_feedback_user_supplement = 0,
    'ALTER TABLE player_feedback ADD COLUMN user_supplement TEXT NULL AFTER admin_reply',
    'SELECT 1'
);
PREPARE stmt_add_feedback_user_supplement FROM @sql_add_feedback_user_supplement;
EXECUTE stmt_add_feedback_user_supplement;
DEALLOCATE PREPARE stmt_add_feedback_user_supplement;

SET @has_feedback_supplemented_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'player_feedback'
      AND COLUMN_NAME = 'supplemented_at'
);
SET @sql_add_feedback_supplemented_at := IF(
    @has_feedback_supplemented_at = 0,
    'ALTER TABLE player_feedback ADD COLUMN supplemented_at DATETIME NULL AFTER user_supplement',
    'SELECT 1'
);
PREPARE stmt_add_feedback_supplemented_at FROM @sql_add_feedback_supplemented_at;
EXECUTE stmt_add_feedback_supplemented_at;
DEALLOCATE PREPARE stmt_add_feedback_supplemented_at;

UPDATE player_feedback
SET status = 'resolved'
WHERE status = 'closed';

-- Redeem V1: categories, keys, and exchange logs
-- V1 note:
-- plain_code is intentionally stored for admin CSV export convenience.
-- In production, you can switch to one-time export at generation time and avoid long-term plain storage.
CREATE TABLE IF NOT EXISTS redeem_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    description TEXT NULL,
    default_command_template TEXT NOT NULL,
    status ENUM('enabled', 'disabled') NOT NULL DEFAULT 'enabled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_redeem_categories_name (name),
    KEY idx_redeem_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redeem_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    code_hash CHAR(64) NOT NULL,
    plain_code VARCHAR(128) NOT NULL,
    command_template TEXT NOT NULL,
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('available', 'revoked', 'deleted') NOT NULL DEFAULT 'available',
    expires_at DATETIME NULL,
    remark VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_redeem_keys_code_hash (code_hash),
    KEY idx_redeem_keys_status (status),
    KEY idx_redeem_keys_category (category_id),
    KEY idx_redeem_keys_expires (expires_at),
    KEY idx_redeem_keys_created_at (created_at),
    CONSTRAINT fk_redeem_keys_category FOREIGN KEY (category_id) REFERENCES redeem_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_redeem_keys_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redeem_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_id BIGINT UNSIGNED NULL,
    redeem_code_hash CHAR(64) NOT NULL,
    server_id VARCHAR(64) NOT NULL,
    player_uuid VARCHAR(64) NULL,
    player_name VARCHAR(64) NULL,
    world_name VARCHAR(64) NULL,
    status ENUM('executing', 'success', 'failed') NOT NULL DEFAULT 'executing',
    failure_reason VARCHAR(255) NULL,
    command_snapshot JSON NULL,
    executed_commands JSON NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    KEY idx_redeem_logs_key (key_id),
    KEY idx_redeem_logs_status (status),
    KEY idx_redeem_logs_created_at (created_at),
    KEY idx_redeem_logs_player (player_name),
    KEY idx_redeem_logs_server (server_id),
    CONSTRAINT fk_redeem_logs_key FOREIGN KEY (key_id) REFERENCES redeem_keys(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Redeem V2: operations enhancement (batch/channel/admin logs/manual failure handling)
CREATE TABLE IF NOT EXISTS redeem_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_no VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL DEFAULT '',
    channel VARCHAR(128) NOT NULL DEFAULT '',
    category_id INT UNSIGNED NULL,
    total_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_redeem_batches_batch_no (batch_no),
    KEY idx_redeem_batches_channel (channel),
    KEY idx_redeem_batches_category_id (category_id),
    KEY idx_redeem_batches_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql_add_redeem_keys_batch_id = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'batch_id'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN batch_id BIGINT UNSIGNED NULL AFTER category_id',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_batch_id FROM @sql_add_redeem_keys_batch_id;
EXECUTE stmt_add_redeem_keys_batch_id;
DEALLOCATE PREPARE stmt_add_redeem_keys_batch_id;

SET @sql_add_redeem_keys_channel = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'channel'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN channel VARCHAR(128) NOT NULL DEFAULT '''' AFTER batch_id',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_channel FROM @sql_add_redeem_keys_channel;
EXECUTE stmt_add_redeem_keys_channel;
DEALLOCATE PREPARE stmt_add_redeem_keys_channel;

SET @sql_add_idx_redeem_keys_batch_id = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND INDEX_NAME = 'idx_redeem_keys_batch_id'
    ) = 0,
    'ALTER TABLE redeem_keys ADD KEY idx_redeem_keys_batch_id (batch_id)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_keys_batch_id FROM @sql_add_idx_redeem_keys_batch_id;
EXECUTE stmt_add_idx_redeem_keys_batch_id;
DEALLOCATE PREPARE stmt_add_idx_redeem_keys_batch_id;

SET @sql_add_idx_redeem_keys_channel = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND INDEX_NAME = 'idx_redeem_keys_channel'
    ) = 0,
    'ALTER TABLE redeem_keys ADD KEY idx_redeem_keys_channel (channel)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_keys_channel FROM @sql_add_idx_redeem_keys_channel;
EXECUTE stmt_add_idx_redeem_keys_channel;
DEALLOCATE PREPARE stmt_add_idx_redeem_keys_channel;

SET @sql_add_redeem_logs_admin_status = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'admin_status'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN admin_status ENUM(''pending'',''handled'',''ignored'') NOT NULL DEFAULT ''pending'' AFTER status',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_admin_status FROM @sql_add_redeem_logs_admin_status;
EXECUTE stmt_add_redeem_logs_admin_status;
DEALLOCATE PREPARE stmt_add_redeem_logs_admin_status;

SET @sql_add_redeem_logs_admin_note = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'admin_note'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN admin_note VARCHAR(500) NOT NULL DEFAULT '''' AFTER admin_status',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_admin_note FROM @sql_add_redeem_logs_admin_note;
EXECUTE stmt_add_redeem_logs_admin_note;
DEALLOCATE PREPARE stmt_add_redeem_logs_admin_note;

SET @sql_add_redeem_logs_handled_by = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'handled_by'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN handled_by INT UNSIGNED NULL AFTER admin_note',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_handled_by FROM @sql_add_redeem_logs_handled_by;
EXECUTE stmt_add_redeem_logs_handled_by;
DEALLOCATE PREPARE stmt_add_redeem_logs_handled_by;

SET @sql_add_redeem_logs_handled_at = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'handled_at'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN handled_at DATETIME NULL AFTER handled_by',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_handled_at FROM @sql_add_redeem_logs_handled_at;
EXECUTE stmt_add_redeem_logs_handled_at;
DEALLOCATE PREPARE stmt_add_redeem_logs_handled_at;

SET @sql_add_idx_redeem_logs_admin_status = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND INDEX_NAME = 'idx_redeem_logs_admin_status'
    ) = 0,
    'ALTER TABLE redeem_logs ADD KEY idx_redeem_logs_admin_status (admin_status)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_logs_admin_status FROM @sql_add_idx_redeem_logs_admin_status;
EXECUTE stmt_add_idx_redeem_logs_admin_status;
DEALLOCATE PREPARE stmt_add_idx_redeem_logs_admin_status;

CREATE TABLE IF NOT EXISTS redeem_admin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    detail_json JSON NULL,
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_redeem_admin_logs_admin_id (admin_id),
    KEY idx_redeem_admin_logs_action (action),
    KEY idx_redeem_admin_logs_target (target_type, target_id),
    KEY idx_redeem_admin_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Redeem V3: precise delivery / restricted redeem / multi-server rules
SET @sql_add_redeem_keys_allowed_server_ids = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'allowed_server_ids'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN allowed_server_ids TEXT NULL COMMENT ''JSON array or comma separated server ids; empty means all servers allowed'' AFTER channel',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_allowed_server_ids FROM @sql_add_redeem_keys_allowed_server_ids;
EXECUTE stmt_add_redeem_keys_allowed_server_ids;
DEALLOCATE PREPARE stmt_add_redeem_keys_allowed_server_ids;

SET @sql_add_redeem_keys_bound_player_uuid = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'bound_player_uuid'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN bound_player_uuid VARCHAR(64) NULL DEFAULT NULL COMMENT ''Only this Minecraft UUID can redeem'' AFTER allowed_server_ids',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_bound_player_uuid FROM @sql_add_redeem_keys_bound_player_uuid;
EXECUTE stmt_add_redeem_keys_bound_player_uuid;
DEALLOCATE PREPARE stmt_add_redeem_keys_bound_player_uuid;

SET @sql_add_redeem_keys_bound_player_name = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'bound_player_name'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN bound_player_name VARCHAR(64) NULL DEFAULT NULL COMMENT ''Display/helper only; UUID is authoritative'' AFTER bound_player_uuid',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_bound_player_name FROM @sql_add_redeem_keys_bound_player_name;
EXECUTE stmt_add_redeem_keys_bound_player_name;
DEALLOCATE PREPARE stmt_add_redeem_keys_bound_player_name;

SET @sql_add_redeem_keys_require_bound_account = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'require_bound_account'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN require_bound_account TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Require website user account bound to mc_uuid'' AFTER bound_player_name',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_require_bound_account FROM @sql_add_redeem_keys_require_bound_account;
EXECUTE stmt_add_redeem_keys_require_bound_account;
DEALLOCATE PREPARE stmt_add_redeem_keys_require_bound_account;

SET @sql_add_redeem_keys_require_email_verified = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'require_email_verified'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN require_email_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Require bound website user email verified'' AFTER require_bound_account',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_require_email_verified FROM @sql_add_redeem_keys_require_email_verified;
EXECUTE stmt_add_redeem_keys_require_email_verified;
DEALLOCATE PREPARE stmt_add_redeem_keys_require_email_verified;

SET @sql_add_redeem_keys_require_account_active = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'require_account_active'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN require_account_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Require bound website user status=active'' AFTER require_email_verified',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_require_account_active FROM @sql_add_redeem_keys_require_account_active;
EXECUTE stmt_add_redeem_keys_require_account_active;
DEALLOCATE PREPARE stmt_add_redeem_keys_require_account_active;

SET @sql_add_redeem_keys_per_player_limit = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'per_player_limit'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN per_player_limit INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 means unlimited by player uuid'' AFTER require_account_active',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_per_player_limit FROM @sql_add_redeem_keys_per_player_limit;
EXECUTE stmt_add_redeem_keys_per_player_limit;
DEALLOCATE PREPARE stmt_add_redeem_keys_per_player_limit;

SET @sql_add_redeem_keys_per_account_limit = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'per_account_limit'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN per_account_limit INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 means unlimited by website user id'' AFTER per_player_limit',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_per_account_limit FROM @sql_add_redeem_keys_per_account_limit;
EXECUTE stmt_add_redeem_keys_per_account_limit;
DEALLOCATE PREPARE stmt_add_redeem_keys_per_account_limit;

SET @sql_add_redeem_keys_rule_note = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND COLUMN_NAME = 'rule_note'
    ) = 0,
    'ALTER TABLE redeem_keys ADD COLUMN rule_note VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''Admin note for V3 restriction rules'' AFTER per_account_limit',
    'SELECT 1'
);
PREPARE stmt_add_redeem_keys_rule_note FROM @sql_add_redeem_keys_rule_note;
EXECUTE stmt_add_redeem_keys_rule_note;
DEALLOCATE PREPARE stmt_add_redeem_keys_rule_note;

SET @sql_add_idx_redeem_keys_bound_player_uuid = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND INDEX_NAME = 'idx_redeem_keys_bound_player_uuid'
    ) = 0,
    'ALTER TABLE redeem_keys ADD KEY idx_redeem_keys_bound_player_uuid (bound_player_uuid)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_keys_bound_player_uuid FROM @sql_add_idx_redeem_keys_bound_player_uuid;
EXECUTE stmt_add_idx_redeem_keys_bound_player_uuid;
DEALLOCATE PREPARE stmt_add_idx_redeem_keys_bound_player_uuid;

SET @sql_add_idx_redeem_keys_require_bound_account = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_keys'
          AND INDEX_NAME = 'idx_redeem_keys_require_bound_account'
    ) = 0,
    'ALTER TABLE redeem_keys ADD KEY idx_redeem_keys_require_bound_account (require_bound_account)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_keys_require_bound_account FROM @sql_add_idx_redeem_keys_require_bound_account;
EXECUTE stmt_add_idx_redeem_keys_require_bound_account;
DEALLOCATE PREPARE stmt_add_idx_redeem_keys_require_bound_account;

SET @sql_add_redeem_logs_rule_result = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'rule_result'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN rule_result ENUM(''passed'',''rejected'',''skipped'') NOT NULL DEFAULT ''skipped'' AFTER admin_status',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_rule_result FROM @sql_add_redeem_logs_rule_result;
EXECUTE stmt_add_redeem_logs_rule_result;
DEALLOCATE PREPARE stmt_add_redeem_logs_rule_result;

SET @sql_add_redeem_logs_rule_reason = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'rule_reason'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN rule_reason VARCHAR(128) NOT NULL DEFAULT '''' AFTER rule_result',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_rule_reason FROM @sql_add_redeem_logs_rule_reason;
EXECUTE stmt_add_redeem_logs_rule_reason;
DEALLOCATE PREPARE stmt_add_redeem_logs_rule_reason;

SET @sql_add_redeem_logs_rule_snapshot_json = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'rule_snapshot_json'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN rule_snapshot_json JSON NULL AFTER rule_reason',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_rule_snapshot_json FROM @sql_add_redeem_logs_rule_snapshot_json;
EXECUTE stmt_add_redeem_logs_rule_snapshot_json;
DEALLOCATE PREPARE stmt_add_redeem_logs_rule_snapshot_json;

SET @sql_add_redeem_logs_website_user_id = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND COLUMN_NAME = 'website_user_id'
    ) = 0,
    'ALTER TABLE redeem_logs ADD COLUMN website_user_id INT UNSIGNED NULL AFTER rule_snapshot_json',
    'SELECT 1'
);
PREPARE stmt_add_redeem_logs_website_user_id FROM @sql_add_redeem_logs_website_user_id;
EXECUTE stmt_add_redeem_logs_website_user_id;
DEALLOCATE PREPARE stmt_add_redeem_logs_website_user_id;

SET @sql_add_idx_redeem_logs_rule_result = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND INDEX_NAME = 'idx_redeem_logs_rule_result'
    ) = 0,
    'ALTER TABLE redeem_logs ADD KEY idx_redeem_logs_rule_result (rule_result)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_logs_rule_result FROM @sql_add_idx_redeem_logs_rule_result;
EXECUTE stmt_add_idx_redeem_logs_rule_result;
DEALLOCATE PREPARE stmt_add_idx_redeem_logs_rule_result;

SET @sql_add_idx_redeem_logs_rule_reason = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND INDEX_NAME = 'idx_redeem_logs_rule_reason'
    ) = 0,
    'ALTER TABLE redeem_logs ADD KEY idx_redeem_logs_rule_reason (rule_reason)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_logs_rule_reason FROM @sql_add_idx_redeem_logs_rule_reason;
EXECUTE stmt_add_idx_redeem_logs_rule_reason;
DEALLOCATE PREPARE stmt_add_idx_redeem_logs_rule_reason;

SET @sql_add_idx_redeem_logs_website_user_id = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redeem_logs'
          AND INDEX_NAME = 'idx_redeem_logs_website_user_id'
    ) = 0,
    'ALTER TABLE redeem_logs ADD KEY idx_redeem_logs_website_user_id (website_user_id)',
    'SELECT 1'
);
PREPARE stmt_add_idx_redeem_logs_website_user_id FROM @sql_add_idx_redeem_logs_website_user_id;
EXECUTE stmt_add_idx_redeem_logs_website_user_id;
DEALLOCATE PREPARE stmt_add_idx_redeem_logs_website_user_id;

-- -----------------------------------------------------------------------------
-- LiteSignIn bridge tables (website cache + audit). Legacy check-in tables are
-- intentionally kept for backward data compatibility and marked as deprecated.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stellar_signin_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` VARCHAR(80) NOT NULL,
  `website_user_id` INT UNSIGNED NOT NULL,
  `player_uuid` VARCHAR(64) NOT NULL,
  `player_name` VARCHAR(64) NOT NULL,
  `server_id` VARCHAR(64) NOT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'web',
  `status` VARCHAR(32) NOT NULL DEFAULT 'requested',
  `message` VARCHAR(255) NOT NULL DEFAULT '',
  `result_payload_json` LONGTEXT NULL,
  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stellar_signin_requests_request_id` (`request_id`),
  KEY `idx_stellar_signin_requests_user_created` (`website_user_id`, `created_at`),
  KEY `idx_stellar_signin_requests_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stellar_signin_daily_cache` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` VARCHAR(80) NULL,
  `player_uuid` VARCHAR(64) NOT NULL,
  `player_name` VARCHAR(64) NOT NULL,
  `website_user_id` INT UNSIGNED NOT NULL,
  `server_id` VARCHAR(64) NOT NULL,
  `sign_date` DATE NOT NULL,
  `signed_today` TINYINT(1) NOT NULL DEFAULT 0,
  `continuous` INT UNSIGNED NOT NULL DEFAULT 0,
  `total` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_signin_at` DATETIME NULL,
  `last_source` VARCHAR(32) NOT NULL DEFAULT 'litesignin_cache',
  `raw_payload_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stellar_signin_daily_cache_identity` (`player_uuid`, `server_id`, `sign_date`),
  KEY `idx_stellar_signin_daily_cache_user` (`website_user_id`),
  KEY `idx_stellar_signin_daily_cache_request_id` (`request_id`),
  KEY `idx_stellar_signin_daily_cache_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stellar_reward_outbox` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` VARCHAR(80) NOT NULL,
  `website_user_id` INT UNSIGNED NOT NULL,
  `player_uuid` VARCHAR(64) NOT NULL,
  `player_name` VARCHAR(64) NOT NULL,
  `server_id` VARCHAR(64) NOT NULL DEFAULT 'stellar-main',
  `sign_date` DATE NULL DEFAULT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'signin',
  `reward_type` VARCHAR(32) NOT NULL DEFAULT 'sweetmail',
  `reward_payload_json` LONGTEXT NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `processing_at` DATETIME NULL DEFAULT NULL,
  `delivered_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stellar_reward_outbox_request` (`request_id`),
  UNIQUE KEY `uq_stellar_reward_outbox_daily` (`player_uuid`, `server_id`, `source`, `sign_date`),
  KEY `idx_stellar_reward_outbox_status_created` (`status`, `created_at`),
  KEY `idx_stellar_reward_outbox_player_status` (`player_uuid`, `status`),
  KEY `idx_stellar_reward_outbox_user_created` (`website_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
