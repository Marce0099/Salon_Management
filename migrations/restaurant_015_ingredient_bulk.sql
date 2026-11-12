-- Ingredient presentations (several per ingredient) + purchase records (centralized "Compra" page).
-- ingredient_packs replaces the single ingredients.pack_name/pack_size (kept, no longer read; backfilled once below).
-- ingredient_purchases / _lines: one saved receipt (store, date, total) and what it loaded. Stock/cost still move through ingredient_movements ('receive').
CREATE TABLE IF NOT EXISTS ingredient_packs (
  id_pack INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ingredient INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  size DECIMAL(12,3) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_pack),
  UNIQUE KEY uq_ingredient_pack (id_ingredient, name),
  CONSTRAINT fk_ipack_ingredient FOREIGN KEY (id_ingredient) REFERENCES ingredients (id_ingredient) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT IGNORE INTO ingredient_packs (id_ingredient, name, size)
SELECT id_ingredient, pack_name, pack_size FROM ingredients WHERE pack_name IS NOT NULL AND pack_name <> '' AND pack_size IS NOT NULL AND pack_size > 0;

CREATE TABLE IF NOT EXISTS ingredient_purchases (
  id_purchase INT UNSIGNED NOT NULL AUTO_INCREMENT,
  store VARCHAR(100) NULL DEFAULT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  purchased_at DATE NOT NULL,
  total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  id_admin INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_purchase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_purchase_lines (
  id_line INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_purchase INT UNSIGNED NOT NULL,
  id_ingredient INT UNSIGNED NOT NULL,
  pack_name VARCHAR(60) NULL DEFAULT NULL,
  pack_qty DECIMAL(12,3) NOT NULL,
  base_qty DECIMAL(12,3) NOT NULL,
  line_cost DECIMAL(12,2) NULL DEFAULT NULL,
  PRIMARY KEY (id_line),
  KEY idx_ipl_purchase (id_purchase),
  CONSTRAINT fk_ipl_purchase FOREIGN KEY (id_purchase) REFERENCES ingredient_purchases (id_purchase) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
