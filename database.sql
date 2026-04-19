-- MySQL schema for Minecraft web system
--
-- 说明：登录 / 找回密码 / 注册等 Rate Limit 与排行榜页面缓存默认使用 Redis（phpredis）；
-- 连接失败时由应用层自动降级到 storage/cache 下 JSON 文件，无需额外 DDL。

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    mc_username VARCHAR(64) NULL DEFAULT NULL COMMENT '绑定的游戏角色名',
    mc_uuid VARCHAR(64) NULL DEFAULT NULL COMMENT '绑定的游戏UUID',
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
    mua_sub VARCHAR(255) UNIQUE DEFAULT NULL COMMENT 'MUA Union 唯一标识',
    last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT '最近一次修改游戏角色绑定时间',
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
--   ADD COLUMN last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT '最近一次修改游戏角色绑定时间';

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
    'ALTER TABLE users ADD COLUMN last_mc_bind_at DATETIME NULL DEFAULT NULL COMMENT ''最近一次修改游戏角色绑定时间'' AFTER email_verified',
    'SELECT ''users.last_mc_bind_at already exists'' AS message'
);
PREPARE stmt_add_last_mc_bind FROM @add_last_mc_bind_sql;
EXECUTE stmt_add_last_mc_bind;
DEALLOCATE PREPARE stmt_add_last_mc_bind;

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
    'ALTER TABLE users ADD COLUMN mua_sub VARCHAR(255) UNIQUE DEFAULT NULL COMMENT ''MUA Union 唯一标识'' AFTER email_verified',
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
    'ALTER TABLE users MODIFY COLUMN mua_sub VARCHAR(255) DEFAULT NULL COMMENT ''MUA Union 唯一标识''',
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
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 新增：密码重置记录表
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

-- Seed super admin user (DEV/TEST only)
-- 正式上线建议：删除/注释本段，改为后台创建或单独执行 seed.sql，避免误用占位密码。
INSERT INTO users (username, email, password_hash, role, status, email_verified, created_at, updated_at)
VALUES (
    'StellarVan',
    'admin@example.com',
    '$2y$10$replace_this_with_real_bcrypt_hashXXXXXXXXXXXXXXX',
    'admin',
    'active',
    1,
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE username = username;

CREATE TABLE IF NOT EXISTS milestones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    milestone_date VARCHAR(50) NOT NULL COMMENT '如：2024年1月',
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_milestones_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT '可以是NULL，比如未登录访客操作或API调用',
    `action` VARCHAR(50) NOT NULL COMMENT '如 LOGIN, REGISTER, API_AUTH',
    `ip_address` VARCHAR(45) NOT NULL,
    `details` JSON NULL COMMENT '详细变更内容或错误信息',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 新增：网页签到积分字段
ALTER TABLE users ADD COLUMN coins INT NOT NULL DEFAULT 0 COMMENT '网页硬币/积分';

-- 新增：用户每日签到记录
CREATE TABLE IF NOT EXISTS user_checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_checkins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_checkins_user_date (user_id, checkin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 新增：为玩家行为时间轴查询补充索引
ALTER TABLE user_checkins ADD INDEX idx_user_checkins_user_created_at (user_id, created_at);
ALTER TABLE audit_logs ADD INDEX idx_audit_logs_user_created_at (user_id, created_at);

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
    ip_cidr VARCHAR(45) NOT NULL COMMENT '精确 IPv4/IPv6 或 CIDR，如 192.168.1.0/24',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_ip_blacklist_ip_cidr (ip_cidr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 站点键值配置（动态注册上限、白名单限流策略等）
CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uq_site_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 「关于我们」团队成员（替代 whitelist.json）
CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL COMMENT 'Minecraft 用户名，用于头像与展示',
    role VARCHAR(128) NOT NULL DEFAULT '服务器成员' COMMENT '职位/角色描述',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 认证接口 IP 白名单（优先级高于黑名单；可配合 site_settings 放宽限流）
CREATE TABLE IF NOT EXISTS ip_whitelist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_cidr VARCHAR(45) NOT NULL COMMENT '精确 IPv4/IPv6 或 CIDR',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_ip_whitelist_ip_cidr (ip_cidr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value, description) VALUES
    ('register_ip_limit', '2', '同一 IP 24 小时内允许注册的最大账号数（白名单 IP 不受此限制）'),
    ('whitelist_ignores_rate_limit', '0', '设为 1 时：白名单 IP 同时无视 checkRateLimit、登录失败锁定、会话冷却等限流')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
