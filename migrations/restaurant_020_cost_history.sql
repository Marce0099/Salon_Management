-- Stage: audit of product cost changes. One row per purchase line (source 'purchase', written by
-- Invoices_mdl::receive) and per manual/import cost change. Costs are per base unit, in dollars.
CREATE TABLE IF NOT EXISTS product_cost_history (
  id_cost_history INT NOT NULL AUTO_INCREMENT,
  id_product INT(3) UNSIGNED ZEROFILL NOT NULL,
  source ENUM('purchase','manual','import') NOT NULL DEFAULT 'purchase',
  id_invoice INT NULL,
  id_invoice_product INT NULL,
  id_provider INT NULL,
  old_cost DECIMAL(12,4) NULL,
  new_cost DECIMAL(12,4) NOT NULL,
  quantity INT NULL,
  old_sell DECIMAL(12,4) NULL,
  new_sell DECIMAL(12,4) NULL,
  id_admin INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cost_history),
  KEY idx_pch_product_date (id_product, created_at),
  KEY idx_pch_invoice (id_invoice),
  CONSTRAINT fk_pch_product FOREIGN KEY (id_product) REFERENCES products (id_product) ON DELETE CASCADE,
  CONSTRAINT fk_pch_invoice FOREIGN KEY (id_invoice) REFERENCES invoices (id_invoice) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Backfill from past invoices; old_cost = cost of the previous purchase line of the same product.
INSERT INTO product_cost_history (id_product, source, id_invoice, id_invoice_product, id_provider, old_cost, new_cost, quantity, new_sell, created_at)
SELECT IP.id_product, 'purchase', IP.id_invoice, IP.id_invoice_product, I.id_provider,
  (SELECT CAST(P2.price_cost AS DECIMAL(12,4)) FROM invoice_products P2
     WHERE P2.id_product = IP.id_product AND P2.price_cost <> '' AND P2.price_cost REGEXP '^[0-9]+(\\.[0-9]+)?$'
       AND P2.id_invoice_product < IP.id_invoice_product
     ORDER BY P2.id_invoice_product DESC LIMIT 1),
  CAST(IP.price_cost AS DECIMAL(12,4)), IP.quantity,
  CASE WHEN IP.price_sell REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(IP.price_sell AS DECIMAL(12,4)) ELSE NULL END,
  IP.date_time
FROM invoice_products IP
INNER JOIN invoices I ON I.id_invoice = IP.id_invoice
WHERE IP.price_cost <> '' AND IP.price_cost REGEXP '^[0-9]+(\\.[0-9]+)?$'
ORDER BY IP.id_invoice_product;
