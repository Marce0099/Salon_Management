-- 023: cordoba-first prices. C$ columns are the source of truth for menu prices and
-- purchase costs; the legacy dollar columns stay (derived = C$ / dollar_exchange.sell_price)
-- so every untouched reader keeps working. Re-runnable.
SET NAMES utf8mb4;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS price_cost_cor DECIMAL(14,4) NULL AFTER price_sell,
  ADD COLUMN IF NOT EXISTS price_sell_cor DECIMAL(12,2) NULL AFTER price_cost_cor;

ALTER TABLE product_packs
  ADD COLUMN IF NOT EXISTS price_cost_cor DECIMAL(14,2) NULL;

ALTER TABLE invoice_products
  ADD COLUMN IF NOT EXISTS price_cost_cor DECIMAL(14,4) NULL,
  ADD COLUMN IF NOT EXISTS price_sell_cor DECIMAL(12,2) NULL;

-- rate snapshot per purchase invoice
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS dollar_ex_sell DECIMAL(10,4) NULL;

SET @rate := (SELECT sell_price FROM dollar_exchange LIMIT 1);

UPDATE invoices SET dollar_ex_sell = @rate WHERE dollar_ex_sell IS NULL;

UPDATE products
   SET price_cost_cor = ROUND(CAST(price_cost AS DECIMAL(14,4)) * @rate, 4)
 WHERE price_cost_cor IS NULL AND price_cost REGEXP '^[0-9]+(\.[0-9]+)?$';
UPDATE products
   SET price_sell_cor = ROUND(CAST(price_sell AS DECIMAL(14,4)) * @rate, 2)
 WHERE price_sell_cor IS NULL AND price_sell REGEXP '^[0-9]+(\.[0-9]+)?$';

UPDATE product_packs SET price_cost_cor = ROUND(price_cost * @rate, 2)
 WHERE price_cost_cor IS NULL AND price_cost IS NOT NULL;

UPDATE invoice_products ip JOIN invoices i ON i.id_invoice = ip.id_invoice
   SET ip.price_cost_cor = ROUND(CAST(ip.price_cost AS DECIMAL(14,4)) * i.dollar_ex_sell, 4)
 WHERE ip.price_cost_cor IS NULL AND ip.price_cost REGEXP '^[0-9]+(\.[0-9]+)?$';
UPDATE invoice_products ip JOIN invoices i ON i.id_invoice = ip.id_invoice
   SET ip.price_sell_cor = ROUND(CAST(ip.price_sell AS DECIMAL(14,4)) * i.dollar_ex_sell, 2)
 WHERE ip.price_sell_cor IS NULL AND ip.price_sell REGEXP '^[0-9]+(\.[0-9]+)?$';

-- tip is typed in cordobas now: keep enough dollar precision so C$ x rate comes back exact
ALTER TABLE sales MODIFY tip DECIMAL(14,6) NOT NULL DEFAULT 0.000000;

-- dollar snapshots of C$ prices need more than cents or C$ x rate drifts (C$120 -> $3.23 -> C$119.83)
ALTER TABLE order_items MODIFY unit_price DECIMAL(14,6) NOT NULL;
ALTER TABLE bills MODIFY amount_override DECIMAL(14,6) NULL;
