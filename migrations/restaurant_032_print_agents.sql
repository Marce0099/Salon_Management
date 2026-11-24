-- Print agents (printing plan step 4): a small program on a PC with a USB printer polls the server and prints locally.
-- Authenticated by a per-agent token (only its sha256 is stored). A printer of conn_type 'agent' belongs to one agent;
-- its `address` is the Windows printer name ON THE AGENT'S PC.
CREATE TABLE IF NOT EXISTS print_agents (
  id_agent INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_seen_at DATETIME NULL,
  last_ip VARCHAR(45) NULL,
  version VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_agent),
  UNIQUE KEY uq_agent_name (name),
  UNIQUE KEY uq_agent_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE printers MODIFY conn_type ENUM('network','windows','agent') NOT NULL;
ALTER TABLE printers ADD COLUMN IF NOT EXISTS id_agent INT NULL;
ALTER TABLE printers ADD CONSTRAINT fk_printer_agent FOREIGN KEY (id_agent) REFERENCES print_agents (id_agent) ON DELETE SET NULL;
