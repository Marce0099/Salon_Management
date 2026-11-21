-- Migration 026: córdoba-only money storage.
-- Dollars are no longer stored: they are derived at read time (live rate, or the sale's own dollar_ex_sell snapshot).
-- Money has 2 decimals everywhere; per-unit costs (products/packs/ingredients cost per base unit) keep 4 decimals.
-- Apply once, by hand, to DB `restaurant` (take a mysqldump first: this drops columns).

SET @rate := (SELECT sell_price FROM dollar_exchange LIMIT 1);

-- 1. Dollar snapshots -> C$ (sales use each sale's own rate; open tabs / bills / ingredient costs use the live rate)
UPDATE sale_products sp JOIN sales s ON s.id_sale = sp.id_sale SET sp.price = ROUND(CAST(sp.price AS DECIMAL(20,8)) * s.dollar_ex_sell, 2);
ALTER TABLE sale_products MODIFY price DECIMAL(12,2) NOT NULL;

UPDATE sales SET tip = ROUND(tip * dollar_ex_sell, 2), iva = ROUND(iva * dollar_ex_sell, 2);
ALTER TABLE sales MODIFY tip DECIMAL(12,2) NOT NULL DEFAULT 0.00, MODIFY iva DECIMAL(12,2) NOT NULL DEFAULT 0.00;

UPDATE order_items SET unit_price = ROUND(unit_price * @rate, 2);
ALTER TABLE order_items MODIFY unit_price DECIMAL(12,2) NOT NULL;

UPDATE bills SET amount_override = ROUND(amount_override * @rate, 2) WHERE amount_override IS NOT NULL;
ALTER TABLE bills MODIFY amount_override DECIMAL(12,2) NULL;

UPDATE ingredients SET cost_unit = ROUND(cost_unit * @rate, 4) WHERE cost_unit IS NOT NULL;
ALTER TABLE ingredients MODIFY cost_unit DECIMAL(12,4) NULL;

-- 2. Drop the legacy dollar columns (their *_cor counterparts are the only copy from now on)
ALTER TABLE products DROP COLUMN price_cost, DROP COLUMN price_sell;
ALTER TABLE product_packs DROP COLUMN price_cost;
ALTER TABLE invoice_products DROP COLUMN price_cost, DROP COLUMN price_sell;
ALTER TABLE returns_products DROP COLUMN price_cost, DROP COLUMN price_sell;
ALTER TABLE internal_use_products DROP COLUMN price_cost;
ALTER TABLE product_cost_history DROP COLUMN old_cost, DROP COLUMN new_cost, DROP COLUMN old_sell, DROP COLUMN new_sell;
ALTER TABLE ingredient_purchases DROP COLUMN total_cost, DROP COLUMN dollar_ex_sell;
ALTER TABLE ingredient_purchase_lines DROP COLUMN line_cost;
ALTER TABLE invoices DROP COLUMN dollar_ex_sell;

-- 3. product_cost_history.new_cost_cor is the audited cost: make it required like the old new_cost
UPDATE product_cost_history SET new_cost_cor = 0 WHERE new_cost_cor IS NULL;
ALTER TABLE product_cost_history MODIFY new_cost_cor DECIMAL(14,4) NOT NULL;
