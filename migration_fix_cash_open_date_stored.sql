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
-- initialization-order bug.
--
-- Why this is safe on a database that already has data:
--   `open_date` is a GENERATED column -- it holds no independent data of its
--   own, it is always recomputed from `open_date_time` (cast to DATE). This
--   script never touches `open_date_time` or any other column/row/table --
--   it only drops and recreates the *derived* `open_date` column, so the
--   values come back byte-for-byte identical for every existing row. Nothing
--   here can lose or corrupt real data.
--
-- This script is defensive about the "no idea what's actually on that
-- server" concern:
--   1. It inspects information_schema first and only acts if `open_date` is
--      currently VIRTUAL. If it's already STORED (already fixed) or doesn't
--      exist at all, it does nothing and just reports that.
--   2. It snapshots every row's (id_cash, open_date_time, open_date) into a
--      temporary table *before* touching anything.
--   3. After the ALTERs, it re-derives open_date for every row and compares
--      row count + every value against the snapshot. It only reports "OK"
--      if 100% of rows match; otherwise it prints a warning instead of
--      silently declaring success.
--   4. It is idempotent -- safe to run more than once.
--
-- Run once against the affected database, e.g.:
--   mysql -u root coco_cosmetics2 < migration_fix_cash_open_date_stored.sql
-- or paste it into phpMyAdmin's SQL tab. Read the final result row it prints
-- to confirm it says OK before considering the fix applied.

DELIMITER $$

DROP PROCEDURE IF EXISTS _fix_cash_open_date_stored$$
CREATE PROCEDURE _fix_cash_open_date_stored()
BEGIN
    DECLARE current_extra VARCHAR(255) DEFAULT NULL;
    DECLARE idx_exists INT DEFAULT 0;
    DECLARE row_count_before INT DEFAULT 0;
    DECLARE row_count_after INT DEFAULT 0;
    DECLARE mismatch_count INT DEFAULT 0;

    SELECT EXTRA INTO current_extra
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash' AND COLUMN_NAME = 'open_date';

    IF current_extra IS NULL THEN
        SELECT 'SKIPPED: cash.open_date column not found -- table structure differs from expected, investigate manually before doing anything' AS result;

    ELSEIF current_extra LIKE '%STORED GENERATED%' THEN
        SELECT 'OK: cash.open_date is already STORED -- no migration needed, nothing changed' AS result;

    ELSEIF current_extra LIKE '%VIRTUAL GENERATED%' THEN

        DROP TEMPORARY TABLE IF EXISTS _cash_open_date_snapshot;
        CREATE TEMPORARY TABLE _cash_open_date_snapshot AS
            SELECT id_cash, open_date_time, open_date FROM cash;

        SELECT COUNT(*) INTO row_count_before FROM _cash_open_date_snapshot;

        SELECT COUNT(*) INTO idx_exists
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash' AND INDEX_NAME = 'uniq_open_date';

        IF idx_exists > 0 THEN
            ALTER TABLE cash DROP INDEX uniq_open_date;
        END IF;

        ALTER TABLE cash DROP COLUMN open_date;
        ALTER TABLE cash ADD COLUMN open_date date GENERATED ALWAYS AS (cast(open_date_time as date)) STORED;
        ALTER TABLE cash ADD UNIQUE KEY uniq_open_date (open_date);

        SELECT COUNT(*) INTO row_count_after FROM cash;

        SELECT COUNT(*) INTO mismatch_count
        FROM cash c
        JOIN _cash_open_date_snapshot s ON s.id_cash = c.id_cash
        WHERE c.open_date <> s.open_date OR c.open_date_time <> s.open_date_time;

        IF row_count_before <> row_count_after THEN
            SELECT CONCAT('WARNING: row count changed during migration (before=', row_count_before, ', after=', row_count_after, ') -- do NOT trust this database, restore from backup and investigate') AS result;
        ELSEIF mismatch_count > 0 THEN
            SELECT CONCAT('WARNING: ', mismatch_count, ' row(s) have a different open_date/open_date_time than before the migration -- do NOT trust this database, restore from backup and investigate') AS result;
        ELSE
            SELECT CONCAT('OK: migrated cash.open_date to STORED. ', row_count_after, ' row(s) verified identical (open_date_time and every derived open_date unchanged).') AS result;
        END IF;

        DROP TEMPORARY TABLE IF EXISTS _cash_open_date_snapshot;

    ELSE
        SELECT CONCAT('SKIPPED: cash.open_date has unexpected definition (', current_extra, ') -- not a plain VIRTUAL/STORED generated column, investigate manually before doing anything') AS result;
    END IF;
END$$

DELIMITER ;

CALL _fix_cash_open_date_stored();
DROP PROCEDURE _fix_cash_open_date_stored;
