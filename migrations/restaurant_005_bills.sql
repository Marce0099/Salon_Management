-- Restaurant conversion, Stage 6: floating bills (cuentas) per tab. A tab has 1..N bills; every order line belongs to a bill.
-- status: open (editable) | split (divided equally into child bills, locked) | paid | void.
-- Child bills of an equal split have parent_bill + amount_override (their share); the parent keeps the items.
CREATE TABLE IF NOT EXISTS bills (
  id_bill INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_tab INT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL,
  status ENUM('open','split','paid','void') NOT NULL DEFAULT 'open',
  parent_bill INT UNSIGNED NULL DEFAULT NULL,
  amount_override DECIMAL(10,2) NULL DEFAULT NULL,
  id_sale INT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bill),
  KEY idx_bills_tab (id_tab, status),
  KEY idx_bills_parent (parent_bill),
  CONSTRAINT fk_bills_tab FOREIGN KEY (id_tab) REFERENCES tabs (id_tab) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE order_items ADD COLUMN id_bill INT UNSIGNED NULL DEFAULT NULL AFTER id_tab,
  ADD KEY idx_oi_bill (id_bill),
  ADD CONSTRAINT fk_oi_bill FOREIGN KEY (id_bill) REFERENCES bills (id_bill) ON DELETE SET NULL;

-- backfill: every open tab gets a first bill and its items are attached to it
INSERT INTO bills (id_tab, label) SELECT t.id_tab, 'Cuenta 1' FROM tabs t WHERE t.status = 'open' AND NOT EXISTS (SELECT 1 FROM bills b WHERE b.id_tab = t.id_tab);
UPDATE order_items oi JOIN bills b ON b.id_tab = oi.id_tab AND b.parent_bill IS NULL SET oi.id_bill = b.id_bill WHERE oi.id_bill IS NULL;
