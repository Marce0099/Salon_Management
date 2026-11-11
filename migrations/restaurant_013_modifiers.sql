-- Block D: order modifiers ("Sin queso", "Término medio"). Global catalog attached per dish.
-- Effects: kitchen text on the comanda + optional removal of ingredient amounts from that portion's stock deduction. No price effect.
CREATE TABLE IF NOT EXISTS modifiers (
  id_modifier INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  kitchen_text VARCHAR(60) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_modifier),
  UNIQUE KEY uq_modifier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- quantity NULL = remove the dish's whole recipe amount of that ingredient; a value = remove that amount (clamped to the recipe amount)
CREATE TABLE IF NOT EXISTS modifier_ingredients (
  id_modifier INT UNSIGNED NOT NULL,
  id_ingredient INT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NULL DEFAULT NULL,
  PRIMARY KEY (id_modifier, id_ingredient),
  KEY idx_mi_ingredient (id_ingredient),
  CONSTRAINT fk_mi_modifier FOREIGN KEY (id_modifier) REFERENCES modifiers (id_modifier) ON DELETE CASCADE,
  CONSTRAINT fk_mi_ingredient FOREIGN KEY (id_ingredient) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS product_modifiers (
  id_product INT UNSIGNED NOT NULL,
  id_modifier INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_product, id_modifier),
  KEY idx_pm_modifier (id_modifier),
  CONSTRAINT fk_pm_product FOREIGN KEY (id_product) REFERENCES products (id_product) ON DELETE CASCADE,
  CONSTRAINT fk_pm_modifier FOREIGN KEY (id_modifier) REFERENCES modifiers (id_modifier) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Snapshot per order line: one row per (modifier, ingredient actually removed) or a single text-only row (id_ingredient NULL).
-- qty_removed is PER PORTION, already clamped to the recipe amount at send time; voids restock from it.
CREATE TABLE IF NOT EXISTS order_item_modifiers (
  id_oim INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_order_item INT UNSIGNED NOT NULL,
  id_modifier INT UNSIGNED NULL DEFAULT NULL,
  name VARCHAR(60) NOT NULL,
  kitchen_text VARCHAR(60) NOT NULL,
  id_ingredient INT UNSIGNED NULL DEFAULT NULL,
  qty_removed DECIMAL(12,3) NULL DEFAULT NULL,
  PRIMARY KEY (id_oim),
  KEY idx_oim_item (id_order_item),
  CONSTRAINT fk_oim_item FOREIGN KEY (id_order_item) REFERENCES order_items (id_order_item) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
