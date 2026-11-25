-- A reprint replaces the copies of a ticket that are still waiting or failed, so an offline station never prints it twice.
ALTER TABLE print_jobs MODIFY status ENUM('pending','printing','printed','failed','cancelled') NOT NULL DEFAULT 'pending';
