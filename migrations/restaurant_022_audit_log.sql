-- Central audit trail (append-only). Written by PHP (Audit library) today and by the Rust backend / Tauri terminal later:
-- the contract is documented in .claude/docs/audit_events.md. occurred_at is stored in UTC.
CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  occurred_at DATETIME(3) NOT NULL,
  request_id VARCHAR(40) NOT NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'php',
  id_admin INT NULL,
  admin_name VARCHAR(100) NULL,
  role VARCHAR(20) NULL,
  ip VARCHAR(45) NULL,
  terminal_id VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  request VARCHAR(120) NULL,
  action VARCHAR(60) NOT NULL,
  result ENUM('ok','denied','error') NOT NULL DEFAULT 'ok',
  entity_type VARCHAR(40) NULL,
  entity_id VARCHAR(40) NULL,
  id_tab INT NULL,
  id_bill INT NULL,
  id_sale INT NULL,
  summary VARCHAR(255) NULL,
  before_json MEDIUMTEXT NULL,
  after_json MEDIUMTEXT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_time (occurred_at),
  KEY idx_audit_admin (id_admin, occurred_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_action (action, occurred_at),
  KEY idx_audit_tab (id_tab),
  KEY idx_audit_bill (id_bill),
  KEY idx_audit_sale (id_sale),
  KEY idx_audit_req (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Cashier who collected the sale (sales.id_employee is the waiter).
ALTER TABLE sales ADD COLUMN IF NOT EXISTS id_admin INT NULL DEFAULT NULL;
