-- Ingredient purchases point to the providers table instead of a free-text store name.
ALTER TABLE ingredient_purchases
  ADD COLUMN id_provider INT(11) NULL DEFAULT NULL AFTER store,
  ADD KEY idx_ip_provider (id_provider),
  ADD CONSTRAINT fk_ip_provider FOREIGN KEY (id_provider) REFERENCES providers (id_provider) ON DELETE SET NULL;

-- Keep history: create a provider for every distinct store name that has no provider yet, then link.
INSERT INTO providers (provider)
SELECT DISTINCT TRIM(p.store) FROM ingredient_purchases p
WHERE p.store IS NOT NULL AND TRIM(p.store) <> ''
  AND NOT EXISTS (SELECT 1 FROM providers v WHERE v.provider = TRIM(p.store) COLLATE utf8_general_ci);

UPDATE ingredient_purchases p JOIN providers v ON v.provider = TRIM(p.store) COLLATE utf8_general_ci SET p.id_provider = v.id_provider
WHERE p.store IS NOT NULL AND TRIM(p.store) <> '';

ALTER TABLE ingredient_purchases DROP COLUMN store;
