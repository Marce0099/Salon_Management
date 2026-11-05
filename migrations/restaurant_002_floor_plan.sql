-- Restaurant conversion, Stage 3: floor plan (areas + free-form tables) and tabs (open table visits).
-- Coordinates are PERCENTAGES of the canvas (10:7 aspect) so the layout scales to phone/tablet/desktop.

CREATE TABLE IF NOT EXISTS table_areas (
  id_area INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dining_tables (
  id_table INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_area INT UNSIGNED NOT NULL,
  label VARCHAR(30) NOT NULL,
  seats TINYINT UNSIGNED NOT NULL DEFAULT 4,
  x DECIMAL(6,3) NOT NULL DEFAULT 5,
  y DECIMAL(6,3) NOT NULL DEFAULT 5,
  w DECIMAL(6,3) NOT NULL DEFAULT 12,
  h DECIMAL(6,3) NOT NULL DEFAULT 12,
  shape ENUM('square','round') NOT NULL DEFAULT 'square',
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_table),
  KEY idx_dining_tables_area (id_area),
  CONSTRAINT fk_dining_tables_area FOREIGN KEY (id_area) REFERENCES table_areas (id_area) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tabs (
  id_tab INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_table INT UNSIGNED NOT NULL,
  id_employee INT(3) UNSIGNED ZEROFILL NULL DEFAULT NULL,
  guests TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('open','paid','void') NOT NULL DEFAULT 'open',
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL DEFAULT NULL,
  id_admin INT NULL DEFAULT NULL,
  PRIMARY KEY (id_tab),
  KEY idx_tabs_table_status (id_table, status),
  CONSTRAINT fk_tabs_table FOREIGN KEY (id_table) REFERENCES dining_tables (id_table) ON DELETE CASCADE,
  CONSTRAINT fk_tabs_employee FOREIGN KEY (id_employee) REFERENCES employees (id_employee) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO table_areas (name, sort_order) SELECT 'Salón', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM table_areas);
