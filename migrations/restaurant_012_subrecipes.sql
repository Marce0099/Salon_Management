-- Block C: sub-recipes. A "prepared" ingredient (sauce, dough, marinade) has batch_yield (base units made by one batch)
-- and a list of raw components consumed per batch. Selling a dish still deducts the prepared ingredient's own stock;
-- "Preparar lote" converts raw components into it (movements prepare_out / prepare_in).
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingredients' AND COLUMN_NAME = 'batch_yield');
SET @sql := IF(@has = 0, 'ALTER TABLE ingredients ADD COLUMN batch_yield DECIMAL(12,3) NULL DEFAULT NULL AFTER pack_size', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS ingredient_components (
  id_ingredient INT UNSIGNED NOT NULL,
  id_component INT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL COMMENT 'base units of the component consumed per batch',
  PRIMARY KEY (id_ingredient, id_component),
  KEY idx_component (id_component),
  CONSTRAINT fk_comp_ingredient FOREIGN KEY (id_ingredient) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE,
  CONSTRAINT fk_comp_component FOREIGN KEY (id_component) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE ingredient_movements MODIFY type ENUM('receive','order','void','waste','adjustment','prepare_in','prepare_out') NOT NULL;
