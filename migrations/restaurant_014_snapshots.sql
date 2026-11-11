-- Exact restock + receipt text.
-- order_item_ingredients: what ONE unit of an order line really took from each ingredient when it was sent (recipe minus modifier removals).
-- A void/replace gives back exactly that, whatever the recipe says later. order_items.stock_snapshot = 1 marks lines that have the snapshot
-- (older lines keep the previous behavior: current recipe minus the modifier removals).
-- sale_products.mods_text: summary of the modifiers of the bill lines of that product ("Sin queso x2, Término medio x1") for receipts.
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'stock_snapshot');
SET @sql := IF(@has = 0, 'ALTER TABLE order_items ADD COLUMN stock_snapshot TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_products' AND COLUMN_NAME = 'mods_text');
SET @sql := IF(@has = 0, 'ALTER TABLE sale_products ADD COLUMN mods_text VARCHAR(255) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS order_item_ingredients (
  id_order_item INT UNSIGNED NOT NULL,
  id_ingredient INT UNSIGNED NOT NULL,
  qty_per_unit DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (id_order_item, id_ingredient),
  CONSTRAINT fk_oii_item FOREIGN KEY (id_order_item) REFERENCES order_items (id_order_item) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
