-- Limodelia CRM database schema
-- MySQL 8+, InnoDB, utf8mb4

CREATE DATABASE IF NOT EXISTS `limodelia_crm`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `limodelia_crm`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `salary_payouts`;
DROP TABLE IF EXISTS `salary_rules`;
DROP TABLE IF EXISTS `trip_photos`;
DROP TABLE IF EXISTS `trip_participants`;
DROP TABLE IF EXISTS `trips`;
DROP TABLE IF EXISTS `marketing_campaigns`;
DROP TABLE IF EXISTS `expense_categories`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `payment_methods`;
DROP TABLE IF EXISTS `student_subscriptions`;
DROP TABLE IF EXISTS `attendance_records`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `subscriptions`;
DROP TABLE IF EXISTS `student_groups`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;


CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `chk_users_email_not_blank` CHECK (`email` <> ''),
  CONSTRAINT `chk_users_name_not_blank` CHECK (`name` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`),
  KEY `idx_roles_status` (`status`),
  CONSTRAINT `chk_roles_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_roles_slug_not_blank` CHECK (`slug` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  KEY `idx_user_roles_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(80) NOT NULL DEFAULT 'general',
  `value_type` ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`),
  KEY `idx_settings_is_private` (`is_private`),
  KEY `idx_settings_updated_by` (`updated_by`),
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_settings_key_not_blank` CHECK (`setting_key` <> ''),
  CONSTRAINT `chk_settings_group_not_blank` CHECK (`setting_group` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `max_students` INT UNSIGNED NULL,
  `color` VARCHAR(20) NULL,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_groups_slug` (`slug`),
  KEY `idx_student_groups_status` (`status`),
  KEY `idx_student_groups_is_visible` (`is_visible`),
  KEY `idx_student_groups_sort_order` (`sort_order`),
  CONSTRAINT `chk_student_groups_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_student_groups_slug_not_blank` CHECK (`slug` <> ''),
  CONSTRAINT `chk_student_groups_max_students` CHECK (`max_students` IS NULL OR `max_students` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `lessons_count` SMALLINT UNSIGNED NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `billing_period` ENUM('monthly', 'once', 'custom') NOT NULL DEFAULT 'monthly',
  `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriptions_slug` (`slug`),
  KEY `idx_subscriptions_status` (`status`),
  KEY `idx_subscriptions_billing_period` (`billing_period`),
  KEY `idx_subscriptions_sort_order` (`sort_order`),
  CONSTRAINT `chk_subscriptions_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_subscriptions_lessons_count` CHECK (`lessons_count` > 0),
  CONSTRAINT `chk_subscriptions_price` CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `students` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_group_id` BIGINT UNSIGNED NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `birth_date` DATE NULL,
  `phone` VARCHAR(40) NULL,
  `telegram_username` VARCHAR(100) NULL,
  `photo_path` VARCHAR(255) NULL,
  `guardian_name` VARCHAR(150) NULL,
  `guardian_phone` VARCHAR(40) NULL,
  `guardian_telegram_username` VARCHAR(100) NULL,
  `enrollment_date` DATE NOT NULL,
  `status` ENUM('lead', 'active', 'paused', 'inactive', 'archived') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_students_group` (`student_group_id`),
  KEY `idx_students_name` (`last_name`, `first_name`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_enrollment_date` (`enrollment_date`),
  KEY `idx_students_telegram_username` (`telegram_username`),
  KEY `idx_students_guardian_phone` (`guardian_phone`),
  KEY `idx_students_guardian_telegram_username` (`guardian_telegram_username`),
  KEY `idx_students_created_by` (`created_by`),
  CONSTRAINT `fk_students_group` FOREIGN KEY (`student_group_id`) REFERENCES `student_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_students_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_students_first_name_not_blank` CHECK (`first_name` <> ''),
  CONSTRAINT `chk_students_last_name_not_blank` CHECK (`last_name` <> ''),
  CONSTRAINT `chk_students_birth_date` CHECK (`birth_date` IS NULL OR `birth_date` >= '1900-01-01'),
  CONSTRAINT `chk_students_enrollment_date` CHECK (`enrollment_date` >= '2000-01-01')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `subscription_id` BIGINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `status` ENUM('active', 'ended', 'cancelled') NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_subscriptions_student` (`student_id`),
  KEY `idx_student_subscriptions_subscription` (`subscription_id`),
  KEY `idx_student_subscriptions_status` (`status`),
  KEY `idx_student_subscriptions_start_date` (`start_date`),
  KEY `idx_student_subscriptions_created_by` (`created_by`),
  CONSTRAINT `fk_student_subscriptions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_subscriptions_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_subscriptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_student_subscriptions_dates` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `payment_id` BIGINT UNSIGNED NULL,
  `lesson_date` DATE NOT NULL,
  `status` ENUM('present', 'absent_notice', 'absent_no_notice', 'school_cancelled') NOT NULL DEFAULT 'present',
  `counts_as_used_lesson` TINYINT(1) NOT NULL DEFAULT 1,
  `notice_number` SMALLINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_student_date` (`student_id`, `lesson_date`),
  KEY `idx_attendance_payment` (`payment_id`),
  KEY `idx_attendance_lesson_date` (`lesson_date`),
  KEY `idx_attendance_status` (`status`),
  KEY `idx_attendance_counts_used` (`counts_as_used_lesson`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_attendance_notice_number` CHECK (`notice_number` IS NULL OR `notice_number` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_methods_slug` (`slug`),
  KEY `idx_payment_methods_status` (`status`),
  KEY `idx_payment_methods_sort_order` (`sort_order`),
  CONSTRAINT `chk_payment_methods_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_payment_methods_slug_not_blank` CHECK (`slug` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` VARCHAR(40) NOT NULL,
  `receipt_number` VARCHAR(80) NULL,
  `receipt_path` VARCHAR(255) NULL,
  `is_manual` TINYINT(1) NOT NULL DEFAULT 1,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `subscription_id` BIGINT UNSIGNED NULL,
  `payment_method_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `payment_date` DATE NOT NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `category` ENUM('subscription', 'registration', 'trip', 'workshop', 'portfolio', 'other') NOT NULL DEFAULT 'subscription',
  `status` ENUM('pending', 'completed', 'partial', 'refunded', 'cancelled') NOT NULL DEFAULT 'completed',
  `reference_number` VARCHAR(120) NULL,
  `comment` TEXT NULL,
  `remaining_lessons_override` SMALLINT UNSIGNED NULL,
  `remaining_lessons_override_used_baseline` SMALLINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_payment_number` (`payment_number`),
  KEY `idx_payments_payment_number` (`payment_number`),
  KEY `idx_payments_student` (`student_id`),
  KEY `idx_payments_subscription` (`subscription_id`),
  KEY `idx_payments_method` (`payment_method_id`),
  KEY `idx_payments_date` (`payment_date`),
  KEY `idx_payments_status` (`status`),
  KEY `idx_payments_category` (`category`),
  KEY `idx_payments_created_by` (`created_by`),
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_payments_number_not_blank` CHECK (`payment_number` <> ''),
  CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_payments_remaining_override` CHECK (`remaining_lessons_override` IS NULL OR `remaining_lessons_override` >= 0),
  CONSTRAINT `chk_payments_remaining_baseline` CHECK (`remaining_lessons_override_used_baseline` IS NULL OR `remaining_lessons_override_used_baseline` >= 0),
  CONSTRAINT `chk_payments_period` CHECK (`period_start` IS NULL OR `period_end` IS NULL OR `period_end` >= `period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expense_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_categories_slug` (`slug`),
  KEY `idx_expense_categories_status` (`status`),
  KEY `idx_expense_categories_sort_order` (`sort_order`),
  CONSTRAINT `chk_expense_categories_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_expense_categories_slug_not_blank` CHECK (`slug` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketing_campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `source` VARCHAR(120) NULL,
  `instagram_post_url` TEXT NULL,
  `campaign_url` TEXT NULL,
  `status` ENUM('planned', 'active', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
  `budget` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `leads_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `new_students_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `description` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_campaigns_status` (`status`),
  KEY `idx_marketing_campaigns_source` (`source`),
  KEY `idx_marketing_campaigns_dates` (`start_date`, `end_date`),
  KEY `idx_marketing_campaigns_created_by` (`created_by`),
  CONSTRAINT `fk_marketing_campaigns_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_marketing_campaigns_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_marketing_campaigns_budget` CHECK (`budget` >= 0),
  CONSTRAINT `chk_marketing_campaigns_leads` CHECK (`leads_count` >= 0),
  CONSTRAINT `chk_marketing_campaigns_new_students` CHECK (`new_students_count` >= 0),
  CONSTRAINT `chk_marketing_campaigns_dates` CHECK (`start_date` IS NULL OR `end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trips` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `location` VARCHAR(180) NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `capacity` INT UNSIGNED NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `internal_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `status` ENUM('planned', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
  `description` TEXT NULL,
  `cover_photo_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trips_dates` (`start_date`, `end_date`),
  KEY `idx_trips_status` (`status`),
  KEY `idx_trips_created_by` (`created_by`),
  KEY `idx_trips_cover_photo` (`cover_photo_id`),
  CONSTRAINT `fk_trips_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_trips_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_trips_dates` CHECK (`end_date` >= `start_date`),
  CONSTRAINT `chk_trips_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_trips_internal_cost` CHECK (`internal_cost` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trip_participants` (
  `trip_id` BIGINT UNSIGNED NOT NULL,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('planned', 'confirmed', 'cancelled') NOT NULL DEFAULT 'planned',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`trip_id`, `student_id`),
  KEY `idx_trip_participants_student` (`student_id`),
  KEY `idx_trip_participants_status` (`status`),
  CONSTRAINT `fk_trip_participants_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trip_participants_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salary_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `role_scope` ENUM('accountant', 'teacher', 'administrator', 'finance', 'marketing', 'custom') NOT NULL DEFAULT 'custom',
  `rule_type` ENUM('fixed', 'per_lesson', 'per_student', 'percentage_net_profit', 'remaining_profit', 'bonus', 'deduction') NOT NULL,
  `amount` DECIMAL(10,2) NULL,
  `percentage` DECIMAL(5,2) NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary_rules_name` (`name`),
  KEY `idx_salary_rules_user` (`user_id`),
  KEY `idx_salary_rules_role_scope` (`role_scope`),
  KEY `idx_salary_rules_type` (`rule_type`),
  KEY `idx_salary_rules_status` (`status`),
  KEY `idx_salary_rules_effective` (`effective_from`, `effective_to`),
  CONSTRAINT `fk_salary_rules_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_salary_rules_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_salary_rules_amount` CHECK (`amount` IS NULL OR `amount` >= 0),
  CONSTRAINT `chk_salary_rules_percentage` CHECK (`percentage` IS NULL OR (`percentage` >= 0 AND `percentage` <= 100)),
  CONSTRAINT `chk_salary_rules_dates` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`),
  CONSTRAINT `chk_salary_rules_value` CHECK (
    (`rule_type` IN ('fixed', 'per_lesson', 'per_student', 'bonus', 'deduction') AND `amount` IS NOT NULL)
    OR (`rule_type` = 'percentage_net_profit' AND `percentage` IS NOT NULL)
    OR (`rule_type` = 'remaining_profit' AND `amount` IS NULL AND `percentage` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salary_payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `salary_rule_id` BIGINT UNSIGNED NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `base_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_profit_base` DECIMAL(10,2) NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `status` ENUM('draft', 'calculated', 'approved', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
  `calculated_at` DATETIME NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_salary_payouts_user` (`user_id`),
  KEY `idx_salary_payouts_rule` (`salary_rule_id`),
  KEY `idx_salary_payouts_period` (`period_start`, `period_end`),
  KEY `idx_salary_payouts_status` (`status`),
  KEY `idx_salary_payouts_approved_by` (`approved_by`),
  CONSTRAINT `fk_salary_payouts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_salary_payouts_rule` FOREIGN KEY (`salary_rule_id`) REFERENCES `salary_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_salary_payouts_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_salary_payouts_period` CHECK (`period_end` >= `period_start`),
  CONSTRAINT `chk_salary_payouts_amounts` CHECK (`base_amount` >= 0 AND `bonus_amount` >= 0 AND `deduction_amount` >= 0),
  CONSTRAINT `chk_salary_payouts_total` CHECK (`total_amount` >= 0),
  CONSTRAINT `chk_salary_payouts_approval` CHECK (`status` NOT IN ('approved', 'paid') OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL)),
  CONSTRAINT `chk_salary_payouts_paid` CHECK (`status` <> 'paid' OR `paid_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_category_id` BIGINT UNSIGNED NOT NULL,
  `payment_method_id` BIGINT UNSIGNED NULL,
  `trip_id` BIGINT UNSIGNED NULL,
  `marketing_campaign_id` BIGINT UNSIGNED NULL,
  `salary_payout_id` BIGINT UNSIGNED NULL,
  `is_salary` TINYINT(1) NOT NULL DEFAULT 0,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PLN',
  `expense_date` DATE NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
  `receipt_url` VARCHAR(500) NULL,
  `receipt_path` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_category` (`expense_category_id`),
  KEY `idx_expenses_method` (`payment_method_id`),
  KEY `idx_expenses_trip` (`trip_id`),
  KEY `idx_expenses_campaign` (`marketing_campaign_id`),
  KEY `idx_expenses_salary_payout` (`salary_payout_id`),
  KEY `idx_expenses_is_salary` (`is_salary`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_status` (`status`),
  KEY `idx_expenses_created_by` (`created_by`),
  KEY `idx_expenses_approved_by` (`approved_by`),
  CONSTRAINT `fk_expenses_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_campaign` FOREIGN KEY (`marketing_campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_salary_payout` FOREIGN KEY (`salary_payout_id`) REFERENCES `salary_payouts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_expenses_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_expenses_salary_link` CHECK (`is_salary` = 0 OR `salary_payout_id` IS NOT NULL),
  CONSTRAINT `chk_expenses_approval` CHECK ((`status` <> 'approved' AND `status` <> 'paid') OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trip_photos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trip_id` BIGINT UNSIGNED NOT NULL,
  `cloudinary_public_id` VARCHAR(255) NOT NULL,
  `folder` VARCHAR(255) NULL,
  `version` VARCHAR(80) NULL,
  `secure_url` TEXT NOT NULL,
  `thumbnail_url` TEXT NULL,
  `original_url` TEXT NULL,
  `file_name` VARCHAR(255) NULL,
  `file_size` BIGINT UNSIGNED NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `format` VARCHAR(20) NULL,
  `description` TEXT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_cover` TINYINT(1) NOT NULL DEFAULT 0,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trip_photos_public_id` (`cloudinary_public_id`),
  KEY `idx_trip_photos_trip` (`trip_id`),
  KEY `idx_trip_photos_uploaded_by` (`uploaded_by`),
  KEY `idx_trip_photos_cover` (`is_cover`),
  KEY `idx_trip_photos_sort_order` (`sort_order`),
  KEY `idx_trip_photos_public_id` (`cloudinary_public_id`),
  CONSTRAINT `fk_trip_photos_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trip_photos_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_trip_photos_public_id_not_blank` CHECK (`cloudinary_public_id` <> ''),
  CONSTRAINT `chk_trip_photos_secure_url_not_blank` CHECK (`secure_url` <> ''),
  CONSTRAINT `chk_trip_photos_file_size` CHECK (`file_size` IS NULL OR `file_size` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `trips`
  ADD CONSTRAINT `fk_trips_cover_photo`
  FOREIGN KEY (`cover_photo_id`) REFERENCES `trip_photos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE `reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `report_type` ENUM('revenue', 'expenses', 'profit_loss', 'students', 'marketing', 'trips', 'salaries', 'custom') NOT NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `file_url` VARCHAR(500) NULL,
  `filters_json` JSON NULL,
  `status` ENUM('generated', 'exported', 'archived') NOT NULL DEFAULT 'generated',
  `generated_by` BIGINT UNSIGNED NULL,
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_type` (`report_type`),
  KEY `idx_reports_period` (`period_start`, `period_end`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_generated_by` (`generated_by`),
  CONSTRAINT `fk_reports_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_reports_name_not_blank` CHECK (`name` <> ''),
  CONSTRAINT `chk_reports_period` CHECK (`period_start` IS NULL OR `period_end` IS NULL OR `period_end` >= `period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `level` ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
  `event` VARCHAR(120) NOT NULL,
  `entity_type` VARCHAR(80) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `message` TEXT NOT NULL,
  `context_json` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_logs_user` (`user_id`),
  KEY `idx_system_logs_level` (`level`),
  KEY `idx_system_logs_event` (`event`),
  KEY `idx_system_logs_entity` (`entity_type`, `entity_id`),
  KEY `idx_system_logs_created_at` (`created_at`),
  CONSTRAINT `fk_system_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_system_logs_event_not_blank` CHECK (`event` <> ''),
  CONSTRAINT `chk_system_logs_message_not_blank` CHECK (`message` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(120) NOT NULL,
  `entity_type` VARCHAR(120) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `description` TEXT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user` (`user_id`),
  KEY `idx_activity_logs_action` (`action`),
  KEY `idx_activity_logs_entity_type` (`entity_type`),
  KEY `idx_activity_logs_entity_id` (`entity_id`),
  KEY `idx_activity_logs_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_activity_logs_action_not_blank` CHECK (`action` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
  `status` ENUM('unread', 'read', 'archived') NOT NULL DEFAULT 'unread',
  `related_entity_type` VARCHAR(80) NULL,
  `related_entity_id` BIGINT UNSIGNED NULL,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_status` (`status`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_related` (`related_entity_type`, `related_entity_id`),
  KEY `idx_notifications_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_notifications_title_not_blank` CHECK (`title` <> ''),
  CONSTRAINT `chk_notifications_message_not_blank` CHECK (`message` <> ''),
  CONSTRAINT `chk_notifications_read_at` CHECK (`status` <> 'read' OR `read_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` CHAR(128) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `payload` MEDIUMTEXT NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_sessions_payload_not_blank` CHECK (`payload` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign keys are re-enabled only after every table exists (attendance_records
-- references payments, which is created later in this file).
SET FOREIGN_KEY_CHECKS = 1;
