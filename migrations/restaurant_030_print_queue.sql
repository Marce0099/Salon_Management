-- Print queue (printing plan step 1-2, .claude/docs/printing_plan.md).
-- `printers`: where a job can go (network = raw TCP, windows = Windows printer name / smb path on the server PC).
-- `print_jobs`: every thermal print (comanda, receipt, test) is rendered ONCE to ESC/POS bytes, stored, then delivered;
-- failed jobs can be retried or reprinted from the stored bytes.
CREATE TABLE IF NOT EXISTS printers (
  id_printer INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  role ENUM('receipt','kitchen') NOT NULL,
  conn_type ENUM('network','windows') NOT NULL,
  address VARCHAR(150) NOT NULL,
  port INT NOT NULL DEFAULT 9100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_printer),
  UNIQUE KEY uq_printer_name (name),
  KEY idx_printer_role (role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS print_jobs (
  id_job INT NOT NULL AUTO_INCREMENT,
  id_printer INT NULL,
  printer_name VARCHAR(60) NULL,
  kind ENUM('comanda','receipt','test') NOT NULL,
  ref_type VARCHAR(20) NULL,
  ref_id INT NULL,
  title VARCHAR(120) NOT NULL,
  payload MEDIUMBLOB NULL,
  status ENUM('pending','printing','printed','failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  id_admin INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at DATETIME NULL,
  printed_at DATETIME NULL,
  PRIMARY KEY (id_job),
  KEY idx_job_status (status, id_job),
  KEY idx_job_ref (ref_type, ref_id),
  CONSTRAINT fk_job_printer FOREIGN KEY (id_printer) REFERENCES printers (id_printer) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed from the legacy configuration so existing installs keep printing where they did before.
INSERT INTO printers (name, role, conn_type, address, port)
SELECT 'Caja', 'receipt', 'windows', 'CAJA', 9100 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM printers WHERE name = 'Caja');

INSERT INTO printers (name, role, conn_type, address, port)
SELECT 'Cocina', 'kitchen', 'windows', TRIM(setting_value), 9100 FROM settings
WHERE setting_key = 'kitchen_printer_name' AND TRIM(setting_value) <> ''
  AND NOT EXISTS (SELECT 1 FROM printers WHERE name = 'Cocina');

-- 1 = also print the "COPIA DE FACTURA" ticket after the receipt (what the app always did).
INSERT INTO settings (setting_key, setting_value, description)
SELECT 'receipt_copy', '1', 'Imprimir copia del recibo (0/1)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'receipt_copy');
