-- mapi_users
CREATE TABLE IF NOT EXISTS `mapi_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `qq` VARCHAR(20) DEFAULT '',
    `email` VARCHAR(100) DEFAULT '',
    `is_admin` TINYINT DEFAULT 0,
    `auto_theme` TINYINT DEFAULT 1,
    `theme_mode` VARCHAR(10) DEFAULT 'light',
    `lyrics_default` TINYINT DEFAULT 1,
    `autoplay_default` TINYINT DEFAULT 0,
    `background` VARCHAR(500) DEFAULT '',
    `background_url` VARCHAR(500) DEFAULT '',
    `expire_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_config
CREATE TABLE IF NOT EXISTS `mapi_config` (
    `config_key` VARCHAR(50) NOT NULL,
    `config_value` TEXT,
    PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_keys
CREATE TABLE IF NOT EXISTS `mapi_keys` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `api_key` VARCHAR(64) NOT NULL,
    `status` TINYINT DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_orders
CREATE TABLE IF NOT EXISTS `mapi_orders` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(100) DEFAULT '',
    `type` VARCHAR(20) DEFAULT '',
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `duration_days` INT DEFAULT 0,
    `trade_no` VARCHAR(64) DEFAULT '',
    `status` TINYINT DEFAULT 0,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_passkeys
CREATE TABLE IF NOT EXISTS `mapi_passkeys` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `credential_id` VARCHAR(500) NOT NULL,
    `public_key_pem` TEXT,
    `counter` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_credential_id` (`credential_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_products
CREATE TABLE IF NOT EXISTS `mapi_products` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` VARCHAR(20) DEFAULT 'subscription',
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `duration_days` INT DEFAULT 0,
    `description` TEXT,
    `status` TINYINT DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_mail_templates
CREATE TABLE IF NOT EXISTS `mapi_mail_templates` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL DEFAULT '',
    `subject` VARCHAR(200) NOT NULL DEFAULT '顺雅音乐 - 验证码邮件',
    `body` TEXT,
    `is_html` TINYINT NOT NULL DEFAULT 0,
    `is_default` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_tokens
CREATE TABLE IF NOT EXISTS `mapi_tokens` (
    `token` VARCHAR(80) NOT NULL,
    `api_key` VARCHAR(255) NOT NULL,
    `expires_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`token`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_logs
CREATE TABLE IF NOT EXISTS `mapi_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ip` VARCHAR(45) DEFAULT '',
    `referer` VARCHAR(500) DEFAULT '',
    `endpoint` VARCHAR(200) DEFAULT '',
    `user_agent` VARCHAR(500) DEFAULT '',
    `api_key` VARCHAR(64) DEFAULT '',
    `traffic_bytes` BIGINT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_api_key` (`api_key`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_geetest
CREATE TABLE IF NOT EXISTS `mapi_geetest` (
    `captcha_id` VARCHAR(64) NOT NULL DEFAULT '',
    `key` VARCHAR(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mapi_super_settings
CREATE TABLE IF NOT EXISTS `mapi_super_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(50) NOT NULL,
    `setting_value` TEXT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mapi_playlists` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `key_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT '',
    `type` VARCHAR(20) NOT NULL DEFAULT 'custom',
    `remote_id` VARCHAR(100) DEFAULT '',
    `server` VARCHAR(20) DEFAULT 'netease',
    `cover_url` VARCHAR(500) DEFAULT '',
    `cover_mode` VARCHAR(20) DEFAULT 'auto',
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mapi_songs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `key_id` INT NOT NULL DEFAULT 0,
    `playlist_id` INT NOT NULL,
    `song_id` VARCHAR(64) NOT NULL DEFAULT '',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `artist` VARCHAR(255) DEFAULT '',
    `server` VARCHAR(20) DEFAULT 'netease',
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_playlist_id` (`playlist_id`),
    KEY `idx_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mapi_stats` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `call_count` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;