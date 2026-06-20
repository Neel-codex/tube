-- =====================================================================
-- TradeVision Pro - Complete MySQL Schema
-- Engine: InnoDB | Charset: utf8mb4 | MySQL 8.0+
-- Import this file via phpMyAdmin or `mysql -u user -p db < this.sql`
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- PLANS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `plans` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(20)  NOT NULL,              -- free | pro | elite
  `name`            VARCHAR(60)  NOT NULL,
  `price_usdt`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `duration_days`   INT UNSIGNED NOT NULL DEFAULT 30,
  `max_watchlists`  INT NOT NULL DEFAULT 1,
  `max_alerts`      INT NOT NULL DEFAULT 5,
  `signals_per_day` INT NOT NULL DEFAULT 5,            -- -1 = unlimited
  `scanner_access`  ENUM('limited','full') NOT NULL DEFAULT 'limited',
  `features`        JSON NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`      INT NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- USERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`             CHAR(36) NOT NULL,
  `full_name`        VARCHAR(120) NOT NULL,
  `email`            VARCHAR(180) NOT NULL,
  `password_hash`    VARCHAR(255) NOT NULL,
  `plan_id`          INT UNSIGNED NOT NULL DEFAULT 1,
  `role`             ENUM('user','admin') NOT NULL DEFAULT 'user',
  `status`           ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  `avatar`           VARCHAR(255) NULL,
  `telegram_chat_id` VARCHAR(40) NULL,
  `email_verified`   TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at`    TIMESTAMP NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_uuid` (`uuid`),
  KEY `idx_users_plan` (`plan_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- ADMINS (separate elevated table for admin-only auth scope)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NULL,
  `username`      VARCHAR(80) NOT NULL,
  `email`         VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `permissions`   JSON NULL,
  `last_login_at` TIMESTAMP NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`),
  UNIQUE KEY `uq_admin_email` (`email`),
  KEY `idx_admin_user` (`user_id`),
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SUBSCRIPTIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `plan_id`    INT UNSIGNED NOT NULL,
  `status`     ENUM('active','expired','cancelled','pending') NOT NULL DEFAULT 'pending',
  `started_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `source`     VARCHAR(40) NOT NULL DEFAULT 'manual_usdt',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_user` (`user_id`),
  KEY `idx_sub_plan` (`plan_id`),
  KEY `idx_sub_status` (`status`),
  KEY `idx_sub_expires` (`expires_at`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- WALLET SETTINGS (USDT BEP20 receiving wallets)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `wallet_settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `network`     VARCHAR(40) NOT NULL DEFAULT 'BEP20',
  `currency`    VARCHAR(20) NOT NULL DEFAULT 'USDT',
  `address`     VARCHAR(120) NOT NULL,
  `label`       VARCHAR(80) NULL,
  `qr_image`    VARCHAR(255) NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- PAYMENT REQUESTS (manual USDT review queue)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `payment_requests` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `plan_id`       INT UNSIGNED NOT NULL,
  `wallet_id`     INT UNSIGNED NULL,
  `amount_usdt`   DECIMAL(12,2) NOT NULL,
  `txid`          VARCHAR(120) NOT NULL,
  `screenshot`    VARCHAR(255) NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note`    VARCHAR(255) NULL,
  `reviewed_by`   INT UNSIGNED NULL,
  `reviewed_at`   TIMESTAMP NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_txid` (`txid`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_status` (`status`),
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallet_settings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SCANNER RESULTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `scanner_results` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol`        VARCHAR(30) NOT NULL,
  `timeframe`     VARCHAR(10) NOT NULL,          -- 1m,5m,15m,1h
  `price`         DECIMAL(24,8) NOT NULL,
  `change_pct`    DECIMAL(10,4) NOT NULL DEFAULT 0,
  `volume`        DECIMAL(24,4) NOT NULL DEFAULT 0,
  `setup_type`    VARCHAR(40) NOT NULL,          -- breakout, reversal, pullback...
  `rating`        ENUM('strong_buy','buy','neutral','sell','strong_sell') NOT NULL DEFAULT 'neutral',
  `trend_score`   DECIMAL(6,2) NOT NULL DEFAULT 0,
  `rsi`           DECIMAL(6,2) NULL,
  `atr`           DECIMAL(24,8) NULL,
  `signals`       JSON NULL,                     -- list of detections
  `scanned_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scan_symbol_tf` (`symbol`,`timeframe`),
  KEY `idx_scan_rating` (`rating`),
  KEY `idx_scan_setup` (`setup_type`),
  KEY `idx_scan_tf` (`timeframe`),
  KEY `idx_scan_time` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SIGNALS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `signals` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol`         VARCHAR(30) NOT NULL,
  `timeframe`      VARCHAR(10) NOT NULL,
  `style`          ENUM('scalping','intraday','swing') NOT NULL DEFAULT 'intraday',
  `direction`      ENUM('long','short') NOT NULL,
  `entry`          DECIMAL(24,8) NOT NULL,
  `stop_loss`      DECIMAL(24,8) NOT NULL,
  `tp1`            DECIMAL(24,8) NOT NULL,
  `tp2`            DECIMAL(24,8) NOT NULL,
  `tp3`            DECIMAL(24,8) NOT NULL,
  `risk_reward`    DECIMAL(8,2) NOT NULL DEFAULT 0,
  `confidence`     DECIMAL(6,2) NOT NULL DEFAULT 0,    -- 0-100
  `confluences`    JSON NULL,                          -- breakdown of weights
  `status`         ENUM('active','tp1_hit','tp2_hit','tp3_hit','sl_hit','expired','closed') NOT NULL DEFAULT 'active',
  `is_premium`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sig_symbol` (`symbol`),
  KEY `idx_sig_status` (`status`),
  KEY `idx_sig_style` (`style`),
  KEY `idx_sig_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SUPPLY / DEMAND & SMC ZONES (historical store)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `zones` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol`       VARCHAR(30) NOT NULL,
  `timeframe`    VARCHAR(10) NOT NULL,
  `zone_type`    VARCHAR(40) NOT NULL,   -- supply, demand, order_block, fvg, breaker...
  `price_high`   DECIMAL(24,8) NOT NULL,
  `price_low`    DECIMAL(24,8) NOT NULL,
  `strength`     DECIMAL(6,2) NOT NULL DEFAULT 0,
  `status`       ENUM('fresh','tested','mitigated') NOT NULL DEFAULT 'fresh',
  `meta`         JSON NULL,
  `detected_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_zone_symbol_tf` (`symbol`,`timeframe`),
  KEY `idx_zone_type` (`zone_type`),
  KEY `idx_zone_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- WATCHLISTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `watchlists` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `market`     ENUM('crypto','forex','stocks','commodities') NOT NULL DEFAULT 'crypto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wl_user` (`user_id`),
  CONSTRAINT `fk_wl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `watchlist_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `watchlist_id`  BIGINT UNSIGNED NOT NULL,
  `symbol`        VARCHAR(30) NOT NULL,
  `added_price`   DECIMAL(24,8) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wl_symbol` (`watchlist_id`,`symbol`),
  KEY `idx_wli_wl` (`watchlist_id`),
  CONSTRAINT `fk_wli_wl` FOREIGN KEY (`watchlist_id`) REFERENCES `watchlists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- ALERTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `symbol`     VARCHAR(30) NULL,
  `alert_type` ENUM('new_signal','breakout','trend_change','zone_touch','volume_spike','price_above','price_below') NOT NULL,
  `condition_value` DECIMAL(24,8) NULL,
  `channels`   JSON NULL,                 -- ["browser","email","telegram"]
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `last_triggered_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_user` (`user_id`),
  KEY `idx_alert_type` (`alert_type`),
  CONSTRAINT `fk_alert_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- TRADE JOURNAL
-- =====================================================================
CREATE TABLE IF NOT EXISTS `trade_journal` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `symbol`       VARCHAR(30) NOT NULL,
  `direction`    ENUM('long','short') NOT NULL,
  `entry_price`  DECIMAL(24,8) NOT NULL,
  `exit_price`   DECIMAL(24,8) NULL,
  `stop_loss`    DECIMAL(24,8) NULL,
  `take_profit`  DECIMAL(24,8) NULL,
  `position_size` DECIMAL(24,8) NOT NULL DEFAULT 0,
  `pnl`          DECIMAL(24,8) NULL,
  `pnl_pct`      DECIMAL(10,4) NULL,
  `rr`           DECIMAL(8,2) NULL,
  `outcome`      ENUM('open','win','loss','breakeven') NOT NULL DEFAULT 'open',
  `screenshot`   VARCHAR(255) NULL,
  `notes`        TEXT NULL,
  `opened_at`    TIMESTAMP NULL,
  `closed_at`    TIMESTAMP NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journal_user` (`user_id`),
  KEY `idx_journal_outcome` (`outcome`),
  CONSTRAINT `fk_journal_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- PORTFOLIO
-- =====================================================================
CREATE TABLE IF NOT EXISTS `portfolio` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `symbol`        VARCHAR(30) NOT NULL,
  `direction`     ENUM('long','short') NOT NULL DEFAULT 'long',
  `quantity`      DECIMAL(24,8) NOT NULL DEFAULT 0,
  `avg_entry`     DECIMAL(24,8) NOT NULL DEFAULT 0,
  `current_price` DECIMAL(24,8) NULL,
  `status`        ENUM('open','closed') NOT NULL DEFAULT 'open',
  `realized_pnl`  DECIMAL(24,8) NOT NULL DEFAULT 0,
  `opened_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pf_user` (`user_id`),
  KEY `idx_pf_status` (`status`),
  CONSTRAINT `fk_pf_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- UPLOADED FILES
-- =====================================================================
CREATE TABLE IF NOT EXISTS `uploaded_files` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL,
  `category`   ENUM('chart','payment','profile','other') NOT NULL DEFAULT 'other',
  `path`       VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(100) NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_file_user` (`user_id`),
  KEY `idx_file_cat` (`category`),
  CONSTRAINT `fk_file_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- NOTIFICATIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `title`      VARCHAR(160) NOT NULL,
  `body`       TEXT NULL,
  `type`       VARCHAR(40) NOT NULL DEFAULT 'system',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SETTINGS (key-value site settings + scanner/indicator config)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `skey`       VARCHAR(80) NOT NULL,
  `svalue`     TEXT NULL,
  `group_name` VARCHAR(40) NOT NULL DEFAULT 'general',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- ANNOUNCEMENTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(160) NOT NULL,
  `body`       TEXT NULL,
  `level`      ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- ACTIVITY LOGS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL,
  `action`     VARCHAR(80) NOT NULL,
  `entity`     VARCHAR(80) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `meta`       JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_action` (`action`),
  KEY `idx_log_time` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- RATE LIMITING (token bucket persistence for API)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rl_key`      VARCHAR(120) NOT NULL,
  `hits`        INT NOT NULL DEFAULT 0,
  `window_start` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rl_key` (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MARKET DATA CACHE (file/db hybrid cache for API responses)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `market_cache` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cache_key`  VARCHAR(190) NOT NULL,
  `payload`    MEDIUMTEXT NULL,
  `expires_at` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cache_key` (`cache_key`),
  KEY `idx_cache_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================
INSERT INTO `plans` (`code`,`name`,`price_usdt`,`duration_days`,`max_watchlists`,`max_alerts`,`signals_per_day`,`scanner_access`,`features`,`sort_order`) VALUES
('free','Free',0,3650,1,3,5,'limited', JSON_ARRAY('Limited Scanner','5 Signals / day','Basic Dashboard'),1),
('pro','Pro',29.00,30,10,100,-1,'full', JSON_ARRAY('Full Scanner','Unlimited Signals','Watchlists','Alerts'),2),
('elite','Elite',79.00,30,50,1000,-1,'full', JSON_ARRAY('Everything in Pro','Advanced Analytics','Priority Alerts','SMC Dashboard'),3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default admin: email admin@tradevision.pro / password: Admin@12345  (CHANGE AFTER LOGIN)
INSERT INTO `users` (`uuid`,`full_name`,`email`,`password_hash`,`plan_id`,`role`,`status`,`email_verified`) VALUES
(UUID(),'Platform Admin','admin@tradevision.pro','$2y$12$ytfhid87vFftD4BJ/24a6uwSMtfdlGDRPg7e8hIfnJY7X4Tesg35G',3,'admin','active',1)
ON DUPLICATE KEY UPDATE `email`=VALUES(`email`);

INSERT INTO `admins` (`user_id`,`username`,`email`,`password_hash`,`permissions`)
SELECT u.id,'admin','admin@tradevision.pro','$2y$12$ytfhid87vFftD4BJ/24a6uwSMtfdlGDRPg7e8hIfnJY7X4Tesg35G', JSON_ARRAY('all')
FROM `users` u WHERE u.email='admin@tradevision.pro'
ON DUPLICATE KEY UPDATE `email`=VALUES(`email`);

INSERT INTO `wallet_settings` (`network`,`currency`,`address`,`label`,`is_active`) VALUES
('BEP20','USDT','0x0000000000000000000000000000000000000000','Primary USDT (BEP20) Wallet',1)
ON DUPLICATE KEY UPDATE `address`=VALUES(`address`);

INSERT INTO `settings` (`skey`,`svalue`,`group_name`) VALUES
('site_name','TradeVision Pro','general'),
('site_tagline','Real-Time Market Scanner & Professional Trading Signals','general'),
('support_email','support@tradevision.pro','general'),
('scanner_symbols_limit','120','scanner'),
('scanner_min_volume_usdt','5000000','scanner'),
('signal_min_confidence','60','signals'),
('weight_trend','25','signals'),
('weight_volume','20','signals'),
('weight_rsi','15','signals'),
('weight_structure','20','signals'),
('weight_supply_demand','20','signals'),
('telegram_bot_token','','integrations'),
('smtp_host','','integrations'),
('smtp_user','','integrations'),
('smtp_pass','','integrations')
ON DUPLICATE KEY UPDATE `svalue`=VALUES(`svalue`);

INSERT INTO `announcements` (`title`,`body`,`level`,`is_active`) VALUES
('Welcome to TradeVision Pro','All signals are generated from live market data and technical analysis only. No AI involved.','info',1);
