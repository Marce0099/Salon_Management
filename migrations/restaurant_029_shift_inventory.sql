-- Shift inventory report (informational only: never touches products.stock).
-- Storages are registered by super; a shift report snapshots them and each admin counts their own.
CREATE TABLE IF NOT EXISTS inv_storages (
  id_storage INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  description VARCHAR(150) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_storage),
  UNIQUE KEY uq_inv_storage_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS inv_shifts (
  id_shift INT NOT NULL AUTO_INCREMENT,
  label VARCHAR(80) NOT NULL,
  status ENUM('open','done','void') NOT NULL DEFAULT 'open',
  opened_by INT NULL,
  opened_by_name VARCHAR(100) NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_by INT NULL,
  closed_by_name VARCHAR(100) NULL,
  closed_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  PRIMARY KEY (id_shift),
  KEY idx_inv_shift_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS inv_shift_storages (
  id_shift_storage INT NOT NULL AUTO_INCREMENT,
  id_shift INT NOT NULL,
  id_storage INT NOT NULL,
  storage_name VARCHAR(60) NOT NULL,
  status ENUM('pending','counted') NOT NULL DEFAULT 'pending',
  is_empty TINYINT(1) NOT NULL DEFAULT 0,
  counted_by INT NULL,
  counted_by_name VARCHAR(100) NULL,
  counted_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  PRIMARY KEY (id_shift_storage),
  UNIQUE KEY uq_inv_shift_storage (id_shift, id_storage),
  CONSTRAINT fk_inv_ss_shift FOREIGN KEY (id_shift) REFERENCES inv_shifts (id_shift) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS inv_lines (
  id_line INT NOT NULL AUTO_INCREMENT,
  id_shift_storage INT NOT NULL,
  id_product INT(3) UNSIGNED ZEROFILL NOT NULL,
  product_name VARCHAR(250) NOT NULL,
  barcode VARCHAR(20) NULL,
  id_pack INT NULL,
  pack_name VARCHAR(60) NULL,
  pack_units INT NULL,
  qty INT NOT NULL,
  base_units INT NOT NULL,
  note VARCHAR(150) NULL,
  PRIMARY KEY (id_line),
  KEY idx_inv_line_ss (id_shift_storage),
  KEY idx_inv_line_product (id_product),
  CONSTRAINT fk_inv_line_ss FOREIGN KEY (id_shift_storage) REFERENCES inv_shift_storages (id_shift_storage) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
