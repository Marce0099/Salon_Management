-- Returns remember which invoice line they came from and the presentation (NULL = pre-019 return / loose line)
ALTER TABLE returns_products
  ADD COLUMN id_invoice_product INT NULL AFTER id_product,
  ADD COLUMN pack_name VARCHAR(60) NULL AFTER id_invoice_product,
  ADD COLUMN pack_qty INT NULL AFTER pack_name,
  ADD COLUMN pack_units INT NULL AFTER pack_qty,
  ADD KEY idx_rp_invoice_product (id_invoice_product);
