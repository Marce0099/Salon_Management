-- Restaurant conversion, Stage 5: ordering + kitchen tickets.
-- orders_kitchen = one "Enviar a cocina" batch for a tab; order_items = its lines (void keeps history).
-- id_bill is added in Stage 6 (floating/split bills).
ALTER TABLE products ADD COLUMN prints_kitchen TINYINT(1) NOT NULL DEFAULT 1;
UPDATE products SET prints_kitchen = 0 WHERE is_service = 0;

CREATE TABLE IF NOT EXISTS orders_kitchen (
  id_order INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_tab INT UNSIGNED NOT NULL,
  id_admin INT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  print_status ENUM('pending','printed','failed','skipped') NOT NULL DEFAULT 'pending',
  printed_at DATETIME NULL DEFAULT NULL,
  print_error VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (id_order),
  KEY idx_ok_tab (id_tab),
  CONSTRAINT fk_ok_tab FOREIGN KEY (id_tab) REFERENCES tabs (id_tab) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id_order_item INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_order INT UNSIGNED NOT NULL,
  id_tab INT UNSIGNED NOT NULL,
  id_product INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  notes VARCHAR(255) NULL DEFAULT NULL,
  course TINYINT UNSIGNED NOT NULL DEFAULT 1,
  prints_kitchen TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','void') NOT NULL DEFAULT 'active',
  voided_at DATETIME NULL DEFAULT NULL,
  void_reason VARCHAR(255) NULL DEFAULT NULL,
  voided_by INT NULL DEFAULT NULL,
  PRIMARY KEY (id_order_item),
  KEY idx_oi_order (id_order),
  KEY idx_oi_tab_status (id_tab, status),
  CONSTRAINT fk_oi_order FOREIGN KEY (id_order) REFERENCES orders_kitchen (id_order) ON DELETE CASCADE,
  CONSTRAINT fk_oi_tab FOREIGN KEY (id_tab) REFERENCES tabs (id_tab) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
