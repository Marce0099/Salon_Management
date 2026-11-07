-- Block A, A1: purchase presentations (packs) per product.
-- A pack = N base units (e.g. "Six-pack" = 6). Receiving "3 six-packs" adds 18 units to products.stock.
-- Pack barcodes are UNIQUE among packs; the app also keeps them disjoint from products.barcode.
CREATE TABLE IF NOT EXISTS product_packs (
  id_pack INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_product INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  units INT UNSIGNED NOT NULL,
  barcode VARCHAR(20) NULL DEFAULT NULL,
  price_cost DECIMAL(10,2) NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_pack),
  UNIQUE KEY uq_pack_barcode (barcode),
  KEY idx_pack_product (id_product),
  CONSTRAINT fk_pack_product FOREIGN KEY (id_product) REFERENCES products (id_product) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
