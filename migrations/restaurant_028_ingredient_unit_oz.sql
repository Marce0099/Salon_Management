-- Replace the extra units of migration 027 with just oz.
ALTER TABLE ingredients MODIFY unit ENUM('g','ml','u','oz') NOT NULL DEFAULT 'g';
