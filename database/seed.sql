-- Limodelia CRM seed data
-- Run after database.sql

USE `limodelia_crm`;

START TRANSACTION;

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `phone`, `status`)
VALUES
  (1, 'Default Administrator', 'admin@limodelia.local', '$2y$10$replace_with_real_password_hash_before_production', NULL, 'active')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `status` = VALUES(`status`);

INSERT INTO `roles` (`name`, `slug`, `description`, `status`)
VALUES
  ('Owner', 'owner', 'Full access to all CRM modules, settings, reports, and financial data.', 'active'),
  ('Administrator', 'administrator', 'Operational administration of students, trips, payments, and settings allowed by policy.', 'active'),
  ('Finance', 'finance', 'Access to payments, expenses, salaries, and financial reports.', 'active'),
  ('Teacher', 'teacher', 'Access to assigned students, groups, lessons, trips, and own salary summaries.', 'active'),
  ('Marketing', 'marketing', 'Access to campaigns, leads, source tracking, and marketing reports.', 'active'),
  ('Viewer', 'viewer', 'Read-only access to permitted dashboard and report areas.', 'active')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`);

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `assigned_by`)
SELECT 1, `id`, 1
FROM `roles`
WHERE `slug` = 'owner';

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `value_type`, `is_private`, `updated_by`)
VALUES
  ('app.name', 'Limodelia CRM', 'general', 'string', 0, 1),
  ('app.locale', 'pl_PL', 'general', 'string', 0, 1),
  ('app.timezone', 'Europe/Warsaw', 'general', 'string', 0, 1),
  ('app.currency', 'PLN', 'finance', 'string', 0, 1),
  ('school.name', 'Limodelia', 'school', 'string', 0, 1),
  ('school.nip', '', 'school', 'string', 0, 1),
  ('school.regon', '', 'school', 'string', 0, 1),
  ('school.phone', '', 'school', 'string', 0, 1),
  ('school.email', '', 'school', 'string', 0, 1),
  ('school.instagram', '', 'school', 'string', 0, 1),
  ('school.logo_url', '', 'school', 'string', 0, 1),
  ('school.favicon_url', '', 'school', 'string', 0, 1),
  ('ui.default_theme', 'light', 'ui', 'string', 0, 1),
  ('ui.light.background', '#F7F5F2', 'ui', 'string', 0, 1),
  ('ui.light.surface', '#FFFFFF', 'ui', 'string', 0, 1),
  ('ui.light.primary', '#C89B3C', 'ui', 'string', 0, 1),
  ('ui.light.accent', '#E25D79', 'ui', 'string', 0, 1),
  ('ui.dark.background', '#171312', 'ui', 'string', 0, 1),
  ('ui.dark.surface', '#231A18', 'ui', 'string', 0, 1),
  ('ui.dark.primary', '#7A4A32', 'ui', 'string', 0, 1),
  ('ui.dark.accent', '#CF78AF', 'ui', 'string', 0, 1),
  ('payments.default_due_day', '10', 'payments', 'number', 0, 1),
  ('payments.overdue_grace_days', '0', 'payments', 'number', 0, 1),
  ('uploads.max_file_size_mb', '10', 'uploads', 'number', 0, 1),
  ('cloudinary.enabled', 'false', 'cloudinary', 'boolean', 0, 1),
  ('cloudinary.cloud_name', '', 'cloudinary', 'string', 1, 1),
  ('cloudinary.api_key', '', 'cloudinary', 'string', 1, 1),
  ('cloudinary.api_secret', '', 'cloudinary', 'string', 1, 1),
  ('cloudinary.root_folder', 'limodelia', 'cloudinary', 'string', 0, 1),
  ('backup.enabled', 'false', 'backup', 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
  `setting_value` = VALUES(`setting_value`),
  `setting_group` = VALUES(`setting_group`),
  `value_type` = VALUES(`value_type`),
  `is_private` = VALUES(`is_private`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `student_groups` (`name`, `slug`, `description`, `status`, `max_students`, `color`, `is_visible`, `sort_order`)
VALUES
  ('Dorosłe', 'dorosle', 'Grupa dla dorosłych uczniów.', 'active', NULL, '#C89B3C', 1, 10),
  ('Dziecięca', 'dziecieca', 'Grupa dla dzieci.', 'active', NULL, '#D86F8A', 1, 20)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`),
  `max_students` = VALUES(`max_students`),
  `color` = VALUES(`color`),
  `is_visible` = VALUES(`is_visible`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `subscriptions` (`name`, `slug`, `lessons_count`, `price`, `currency`, `billing_period`, `status`, `description`, `sort_order`)
VALUES
  ('4 zajęcia', '4-zajecia', 4, 130.00, 'PLN', 'monthly', 'active', 'Miesięczny abonament obejmujący 4 zajęcia.', 10),
  ('8 zajęć', '8-zajec', 8, 180.00, 'PLN', 'monthly', 'active', 'Miesięczny abonament obejmujący 8 zajęć.', 20),
  ('Indywidualne zajęcia', 'indywidualne-zajecia', 1, 120.00, 'PLN', 'once', 'active', 'Jednorazowa płatność za indywidualne zajęcia.', 30),
  ('Indywidualne 4 zajęcia', 'indywidualne-4-zajecia', 4, 390.00, 'PLN', 'custom', 'active', 'Pakiet 4 zajęć indywidualnych ważny 30 dni.', 40)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `lessons_count` = VALUES(`lessons_count`),
  `price` = VALUES(`price`),
  `currency` = VALUES(`currency`),
  `billing_period` = VALUES(`billing_period`),
  `status` = VALUES(`status`),
  `description` = VALUES(`description`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `payment_methods` (`name`, `slug`, `status`, `sort_order`)
VALUES
  ('Gotówka', 'gotowka', 'active', 10),
  ('Karta', 'karta', 'active', 20),
  ('Przelew', 'przelew', 'active', 30),
  ('BLIK', 'blik', 'active', 40)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `status` = VALUES(`status`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `expense_categories` (`name`, `slug`, `status`, `sort_order`)
VALUES
  ('Wynajem', 'wynajem', 'active', 10),
  ('Wynajem indywidualny', 'wynajem-indywidualny', 'active', 15),
  ('Marketing', 'marketing', 'active', 20),
  ('Księgowość', 'ksiegowosc', 'active', 30),
  ('Wyjazdy', 'wyjazdy', 'active', 40),
  ('Sprzęt', 'sprzet', 'active', 50),
  ('Podatki', 'podatki', 'active', 60),
  ('Inne', 'inne', 'active', 70)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `status` = VALUES(`status`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `salary_rules` (`name`, `user_id`, `role_scope`, `rule_type`, `amount`, `percentage`, `currency`, `effective_from`, `effective_to`, `status`)
VALUES
  ('Accountant fixed salary', NULL, 'accountant', 'fixed', 1000.00, NULL, 'PLN', CURRENT_DATE, NULL, 'active'),
  ('Teacher 100% net profit', NULL, 'teacher', 'remaining_profit', NULL, NULL, 'PLN', CURRENT_DATE, NULL, 'active'),
  ('Marketing specialist per new student', NULL, 'marketing', 'per_student', 45.00, NULL, 'PLN', CURRENT_DATE, NULL, 'active')
ON DUPLICATE KEY UPDATE
  `role_scope` = VALUES(`role_scope`),
  `rule_type` = VALUES(`rule_type`),
  `amount` = VALUES(`amount`),
  `percentage` = VALUES(`percentage`),
    `currency` = VALUES(`currency`),
    `status` = VALUES(`status`);

UPDATE `salary_rules`
SET `name` = 'Teacher remaining profit'
WHERE `name` = 'Teacher 100% net profit';

INSERT INTO `system_logs` (`user_id`, `level`, `event`, `entity_type`, `entity_id`, `message`, `context_json`)
VALUES
  (1, 'info', 'database.seeded', 'database', NULL, 'Initial seed data inserted.', JSON_OBJECT('version', '0.1.0'));

COMMIT;
