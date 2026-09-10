-- Fixes: MariaDB/InnoDB error 1467 "Failed to read auto-increment value from
-- storage engine" on the first INSERT INTO `cash` after a cold MySQL start.
--
-- Root cause: `cash.open_date` was a VIRTUAL generated column. InnoDB has a
-- known bug where, right after startup, it can fail to initialize the
-- persistent AUTO_INCREMENT counter for a table that has an indexed VIRTUAL
-- generated column -- the counter calculation needs to evaluate the virtual
-- expression before the table is fully open. A second server restart "fixes"
-- it because by then the counter has already been initialized once.
--
-- Fix: convert the column from VIRTUAL to STORED. Stored columns are
-- materialized on write like a normal column, so they don't hit this
-- initialization-order bug. Behavior is unchanged -- MariaDB does not
-- support ALTER ... MODIFY to flip VIRTUAL/STORED directly, so the column
-- and its unique index are dropped and recreated.
--
-- Run this once against the affected database (e.g. via phpMyAdmin's SQL
-- tab or `mysql -u root coco_cosmetics2 < migration_fix_cash_open_date_stored.sql`).
-- Safe to run at any time -- `cash` gets at most one row per day, so the
-- table rebuild is instant, and existing `open_date` values are
-- recomputed identically from `open_date_time`.

ALTER TABLE `cash` DROP INDEX `uniq_open_date`;
ALTER TABLE `cash` DROP COLUMN `open_date`;
ALTER TABLE `cash` ADD COLUMN `open_date` date GENERATED ALWAYS AS (cast(`open_date_time` as date)) STORED;
ALTER TABLE `cash` ADD UNIQUE KEY `uniq_open_date` (`open_date`);
