-- Ingredient purchases are now typed in cordobas. Dollars stay the stored unit (like the rest of the app):
-- line_cost/total_cost (USD, widened to 4 decimals so small cordoba amounts do not lose precision) = cordobas / dollar_ex_sell snapshot.
ALTER TABLE ingredient_purchases
  ADD COLUMN dollar_ex_sell DECIMAL(10,4) NULL DEFAULT NULL AFTER total_cost,
  ADD COLUMN total_cost_cor DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER dollar_ex_sell,
  MODIFY total_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000;
ALTER TABLE ingredient_purchase_lines
  ADD COLUMN line_cost_cor DECIMAL(14,2) NULL DEFAULT NULL AFTER line_cost,
  MODIFY line_cost DECIMAL(14,4) NULL DEFAULT NULL;
-- Backfill old (dollar) purchases with today's rate.
UPDATE ingredient_purchases SET dollar_ex_sell = (SELECT sell_price FROM dollar_exchange LIMIT 1) WHERE dollar_ex_sell IS NULL;
UPDATE ingredient_purchases SET total_cost_cor = ROUND(total_cost * dollar_ex_sell, 2);
UPDATE ingredient_purchase_lines l JOIN ingredient_purchases p ON p.id_purchase = l.id_purchase SET l.line_cost_cor = ROUND(l.line_cost * p.dollar_ex_sell, 2) WHERE l.line_cost IS NOT NULL;
