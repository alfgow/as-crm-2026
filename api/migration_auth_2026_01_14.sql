-- Migración para Sistema de Autenticación y Revocación (2026-01-14)

-- 1. Tabla para gestionar Refresh Tokens (Rotación y Revocación)
CREATE TABLE IF NOT EXISTS `usuarios_refresh_tokens` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `jti` varchar(36) NOT NULL,
    `token_hash` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `revoked_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_jti` (`user_id`, `jti`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla para Lista Negra de Access Tokens (Logout inmediato)
CREATE TABLE IF NOT EXISTS `usuarios_access_token_blacklist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jti` VARCHAR(36) NOT NULL,
    `user_id` INT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_jti` (`jti`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla Outbox para Eventos de Negocio (Durable)
CREATE TABLE IF NOT EXISTS `event_outbox` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `correlation_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `aggregate_type` VARCHAR(40) NOT NULL,
  `aggregate_id` VARCHAR(64) NOT NULL,

  `payload_json` JSON NOT NULL,

  `status` ENUM('pending','processing','delivered','failed','dead') NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_error` TEXT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `ux_correlation` (`correlation_id`),
  KEY `idx_status_next` (`status`, `next_attempt_at`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_aggregate` (`aggregate_type`, `aggregate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Bitácora de Automatizaciones (n8n logs)
CREATE TABLE IF NOT EXISTS `automation_runs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `correlation_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,

  `n8n_workflow` VARCHAR(120) NULL,
  `n8n_execution_id` VARCHAR(80) NULL,

  `status` ENUM('received','running','succeeded','failed') NOT NULL DEFAULT 'received',
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,

  `result_json` JSON NULL,
  `error_message` TEXT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `ux_run_corr` (`correlation_id`),
  KEY `idx_run_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
