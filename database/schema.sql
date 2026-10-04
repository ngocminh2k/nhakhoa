-- MariaDB / MySQL Schema for Nha Khoa Kim Dung
-- Database: utf8mb4 / utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Admin',
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Services
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 30,
  `price` DECIMAL(12, 2) NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_services_active_sort` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Business Hours (0 = Sunday, 1 = Monday ... 6 = Saturday)
CREATE TABLE IF NOT EXISTS `business_hours` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `weekday` TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uk_weekday` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Schedule Blocks (Holidays, Meetings, Doctor leave)
CREATE TABLE IF NOT EXISTS `schedule_blocks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_blocks_range` (`start_datetime`, `end_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_code` VARCHAR(32) NOT NULL UNIQUE,
  `customer_name` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `notes` TEXT NULL,
  `service_id` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `status` ENUM('new', 'confirmed', 'contacted', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'new',
  `source_page` VARCHAR(255) NULL,
  `referrer` VARCHAR(500) NULL,
  `utm_source` VARCHAR(100) NULL,
  `utm_medium` VARCHAR(100) NULL,
  `utm_campaign` VARCHAR(100) NULL,
  `utm_content` VARCHAR(100) NULL,
  `utm_term` VARCHAR(100) NULL,
  `session_id` VARCHAR(64) NULL,
  `sheet_sync_status` ENUM('pending', 'synced', 'failed') NOT NULL DEFAULT 'pending',
  `sheet_sync_error` TEXT NULL,
  `sheet_synced_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cancelled_at` DATETIME NULL,
  INDEX `idx_booking_slot` (`booking_date`, `start_time`, `end_time`, `status`),
  INDEX `idx_booking_phone` (`phone`),
  INDEX `idx_booking_sync` (`sheet_sync_status`),
  INDEX `idx_booking_created` (`created_at`),
  CONSTRAINT `fk_bookings_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Posts (external automation / dynamic blog)
CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `external_id` VARCHAR(191) NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `excerpt` TEXT NULL,
  `content` LONGTEXT NOT NULL,
  `featured_image` VARCHAR(500) NULL,
  `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_posts_status_published` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sessions (First-party analytics)
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL UNIQUE,
  `first_page` VARCHAR(255) NULL,
  `referrer` VARCHAR(500) NULL,
  `utm_source` VARCHAR(100) NULL,
  `utm_medium` VARCHAR(100) NULL,
  `utm_campaign` VARCHAR(100) NULL,
  `utm_content` VARCHAR(100) NULL,
  `utm_term` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sessions_created` (`created_at`),
  INDEX `idx_sessions_source` (`utm_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Page Views
CREATE TABLE IF NOT EXISTS `page_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pv_session` (`session_id`),
  INDEX `idx_pv_created` (`created_at`),
  INDEX `idx_pv_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Daily Analytics (Pre-aggregated)
CREATE TABLE IF NOT EXISTS `daily_analytics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `path` VARCHAR(255) NOT NULL DEFAULT '',
  `source` VARCHAR(100) NOT NULL DEFAULT 'direct',
  `campaign` VARCHAR(100) NOT NULL DEFAULT '',
  `sessions` INT NOT NULL DEFAULT 0,
  `page_views` INT NOT NULL DEFAULT 0,
  `bookings` INT NOT NULL DEFAULT 0,
  UNIQUE KEY `uk_daily_slice` (`date`, `path`, `source`, `campaign`),
  INDEX `idx_daily_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. API Keys (For external automation posting)
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `key_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of API key',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
