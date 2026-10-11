-- Adds UNIQUE(username) to `admins`.
--
-- Why: Admins::save() only pre-checks usernameExists() in application code, so
-- two concurrent saves of the same name could both pass the check and create
-- two accounts with the same login. The index makes the database refuse the
-- second one (Admins_mdl::save() turns that into the normal "Ya existe un
-- usuario con ese nombre" message).
--
-- Before running: the statement fails if the table already holds duplicate
-- usernames (the collation is case-insensitive, so "Ana" and "ana" count).
-- Find them with:
--
--   SELECT username, COUNT(*) c, GROUP_CONCAT(id_admin) ids
--   FROM admins WHERE username IS NOT NULL
--   GROUP BY username HAVING c > 1;
--
-- and rename or delete the extra rows first. NULL usernames are allowed any
-- number of times (standard MySQL UNIQUE semantics). No data is changed.

ALTER TABLE `admins` ADD UNIQUE KEY `uq_admins_username` (`username`);
