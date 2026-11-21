-- 024: C$ mirrors of the cost history (so past rows do not move when the exchange rate changes). Re-runnable.
SET NAMES utf8mb4;

ALTER TABLE product_cost_history
  ADD COLUMN IF NOT EXISTS old_cost_cor DECIMAL(14,4) NULL,
  ADD COLUMN IF NOT EXISTS new_cost_cor DECIMAL(14,4) NULL,
  ADD COLUMN IF NOT EXISTS old_sell_cor DECIMAL(12,2) NULL,
  ADD COLUMN IF NOT EXISTS new_sell_cor DECIMAL(12,2) NULL;

SET @rate := (SELECT sell_price FROM dollar_exchange LIMIT 1);

-- rate of the purchase invoice when there is one, today's rate otherwise
UPDATE product_cost_history H LEFT JOIN invoices I ON I.id_invoice = H.id_invoice
   SET H.old_cost_cor = ROUND(H.old_cost * IFNULL(I.dollar_ex_sell, @rate), 4),
       H.new_cost_cor = ROUND(H.new_cost * IFNULL(I.dollar_ex_sell, @rate), 4),
       H.old_sell_cor = ROUND(H.old_sell * IFNULL(I.dollar_ex_sell, @rate), 2),
       H.new_sell_cor = ROUND(H.new_sell * IFNULL(I.dollar_ex_sell, @rate), 2)
 WHERE H.new_cost_cor IS NULL;

-- goals.amount is C$ from now on (no data conversion: existing values were already C$-scale)

-- snap values that are within rounding noise of a whole C$ cent (dollar columns carry 4 decimals)
UPDATE product_cost_history
   SET old_cost_cor = IF(ABS(old_cost_cor - ROUND(old_cost_cor, 2)) < 0.002, ROUND(old_cost_cor, 2), old_cost_cor),
       new_cost_cor = IF(ABS(new_cost_cor - ROUND(new_cost_cor, 2)) < 0.002, ROUND(new_cost_cor, 2), new_cost_cor);
