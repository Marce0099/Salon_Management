-- Block B: ingredients, recipes and the stock movement log.
-- Ingredient stock is kept in ONE base unit (g, ml or u). Optional pack conversion: 1 pack = pack_size base units.
CREATE TABLE IF NOT EXISTS ingredients (
  id_ingredient INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  unit ENUM('g','ml','u') NOT NULL DEFAULT 'g',
  stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  alert_threshold DECIMAL(12,3) NOT NULL DEFAULT 0,
  cost_unit DECIMAL(12,6) NULL DEFAULT NULL,
  pack_name VARCHAR(60) NULL DEFAULT NULL,
  pack_size DECIMAL(12,3) NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_ingredient),
  UNIQUE KEY uq_ingredient_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_items (
  id_recipe_item INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_product INT UNSIGNED NOT NULL,
  id_ingredient INT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (id_recipe_item),
  UNIQUE KEY uq_recipe (id_product, id_ingredient),
  KEY idx_recipe_ingredient (id_ingredient),
  CONSTRAINT fk_recipe_product FOREIGN KEY (id_product) REFERENCES products (id_product) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_ingredient FOREIGN KEY (id_ingredient) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_movements (
  id_movement INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ingredient INT UNSIGNED NOT NULL,
  type ENUM('receive','order','void','waste','adjustment') NOT NULL,
  quantity DECIMAL(12,3) NOT NULL COMMENT 'signed, base units',
  reason VARCHAR(255) NULL DEFAULT NULL,
  id_admin INT UNSIGNED NULL DEFAULT NULL,
  id_order_item INT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_movement),
  KEY idx_mov_ingredient (id_ingredient, created_at),
  CONSTRAINT fk_mov_ingredient FOREIGN KEY (id_ingredient) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
