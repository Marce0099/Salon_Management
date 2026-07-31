-- Coco Cosmetics — clean database and preset AUTO_INCREMENT ids
--
-- Empties all transactional/business data while preserving system
-- configuration the app cannot run without (bank_account, dollar_exchange,
-- loyalty_settings, settings) and exactly one admin login (username='admin').
--
-- AUTO_INCREMENT is reset to 1 for tables left fully empty, and to
-- MAX(pk)+1 for tables that still hold rows.
--
-- SAFETY: take a full mysqldump backup of the database before running this.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE appointment_payments;
TRUNCATE TABLE appointment_services;
TRUNCATE TABLE appointments;
TRUNCATE TABLE cash;
TRUNCATE TABLE ci_sessions;
TRUNCATE TABLE clients;
TRUNCATE TABLE employees;
TRUNCATE TABLE goals;
TRUNCATE TABLE internal_use_products;
TRUNCATE TABLE internal_uses;
TRUNCATE TABLE invoice_products;
TRUNCATE TABLE invoices;
TRUNCATE TABLE loyalty_point_movements;
TRUNCATE TABLE orders_products;
TRUNCATE TABLE orders;
TRUNCATE TABLE outcomes;
TRUNCATE TABLE products;
TRUNCATE TABLE providers;
TRUNCATE TABLE returns_products;
TRUNCATE TABLE returns;
TRUNCATE TABLE sale_payments;
TRUNCATE TABLE sale_products;
TRUNCATE TABLE sales;
TRUNCATE TABLE service_category;

DELETE FROM admins WHERE username <> 'admin';

SET FOREIGN_KEY_CHECKS = 1;

-- Preset AUTO_INCREMENT to the next logical id for tables that still have rows
SET @next_admin_id = (SELECT MAX(id_admin) + 1 FROM admins);
SET @sql = CONCAT('ALTER TABLE admins AUTO_INCREMENT = ', @next_admin_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_bank_id = (SELECT IFNULL(MAX(id_account) + 1, 1) FROM bank_account);
SET @sql = CONCAT('ALTER TABLE bank_account AUTO_INCREMENT = ', @next_bank_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_setting_id = (SELECT IFNULL(MAX(id_setting) + 1, 1) FROM settings);
SET @sql = CONCAT('ALTER TABLE settings AUTO_INCREMENT = ', @next_setting_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
