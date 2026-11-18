-- Stage: remember the purchase presentation of each received line (NULL = bought as loose units / legacy rows)
ALTER TABLE invoice_products
  ADD COLUMN id_pack INT NULL AFTER id_product,
  ADD COLUMN pack_name VARCHAR(60) NULL AFTER id_pack,
  ADD COLUMN pack_qty INT NULL AFTER pack_name,
  ADD COLUMN pack_units INT NULL AFTER pack_qty;
