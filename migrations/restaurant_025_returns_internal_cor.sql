-- 025: C$ snapshot of the cost on supplier returns and internal use (so old records do not move with the rate). Re-runnable.
SET NAMES utf8mb4;

ALTER TABLE returns_products
  ADD COLUMN IF NOT EXISTS price_cost_cor DECIMAL(14,4) NULL,
  ADD COLUMN IF NOT EXISTS price_sell_cor DECIMAL(12,2) NULL;

ALTER TABLE internal_use_products
  ADD COLUMN IF NOT EXISTS price_cost_cor DECIMAL(14,4) NULL;

SET @rate := (SELECT sell_price FROM dollar_exchange LIMIT 1);

-- returns: take the invoice line's C$ when linked, else dollars x the invoice rate (today's rate as last resort)
UPDATE returns_products RP
  LEFT JOIN invoice_products IP ON IP.id_invoice_product = RP.id_invoice_product
  LEFT JOIN invoices I ON I.id_invoice = IP.id_invoice
   SET RP.price_cost_cor = IFNULL(IP.price_cost_cor, ROUND(CAST(RP.price_cost AS DECIMAL(14,4)) * IFNULL(I.dollar_ex_sell, @rate), 4)),
       RP.price_sell_cor = IFNULL(IP.price_sell_cor, ROUND(CAST(RP.price_sell AS DECIMAL(14,4)) * IFNULL(I.dollar_ex_sell, @rate), 2))
 WHERE RP.price_cost_cor IS NULL AND RP.price_cost REGEXP '^[0-9]+(\.[0-9]+)?$';

UPDATE internal_use_products
   SET price_cost_cor = ROUND(CAST(price_cost AS DECIMAL(14,4)) * @rate, 4)
 WHERE price_cost_cor IS NULL AND price_cost REGEXP '^[0-9]+(\.[0-9]+)?$';
