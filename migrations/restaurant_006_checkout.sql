-- Restaurant conversion, Stage 7: checkout from a bill.
-- sales.tip / sales.iva are in the same currency as product prices (dollars) and are added on top of the lines;
-- sales.id_bill links the sale to the bill it paid. bills.stock_deducted marks that an equal-split parent's items
-- already left stock (done once, when its first part is paid).
ALTER TABLE sales ADD COLUMN tip DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN iva DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN id_bill INT UNSIGNED NULL DEFAULT NULL, ADD KEY idx_sales_bill (id_bill);
ALTER TABLE bills ADD COLUMN stock_deducted TINYINT(1) NOT NULL DEFAULT 0;

-- placeholder service product used as the single sale line of an equal-split share ("Consumo de mesa")
INSERT INTO products (barcode, name, price_cost, price_sell, stock, is_service, active, prints_kitchen, description)
SELECT 'SPLIT-CONSUMO', 'Consumo de mesa (cuenta dividida)', '0', '0', 0, 1, 1, 0, 'Producto interno: representa una parte de una cuenta dividida en partes iguales'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE barcode = 'SPLIT-CONSUMO');
