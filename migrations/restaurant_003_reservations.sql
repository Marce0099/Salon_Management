-- Restaurant conversion, Stage 4: table reservations. A table is "reserved" while an active reservation's
-- hold window (start - 30 min) .. end is current and the table has no open tab.
CREATE TABLE IF NOT EXISTS table_reservations (
  id_reservation INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_table INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL DEFAULT NULL,
  party_size TINYINT UNSIGNED NOT NULL DEFAULT 2,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  notes VARCHAR(255) NULL DEFAULT NULL,
  status ENUM('active','seated','cancelled','no_show') NOT NULL DEFAULT 'active',
  id_tab INT UNSIGNED NULL DEFAULT NULL,
  id_admin INT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_reservation),
  KEY idx_res_table_time (id_table, status, start_at, end_at),
  KEY idx_res_start (start_at),
  CONSTRAINT fk_res_table FOREIGN KEY (id_table) REFERENCES dining_tables (id_table) ON DELETE CASCADE,
  CONSTRAINT fk_res_tab FOREIGN KEY (id_tab) REFERENCES tabs (id_tab) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value, description)
SELECT 'reservation_hold_minutes', '30', 'Minutes before a reservation start during which its table shows as reserved'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='reservation_hold_minutes');
INSERT INTO settings (setting_key, setting_value, description)
SELECT 'reservation_default_minutes', '90', 'Default reservation duration in minutes'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='reservation_default_minutes');
