-- Print routing (printing plan step 3): a menu category can point to a kitchen printer (kitchen, bar...).
-- NULL = the default kitchen printer (first active printer of role 'kitchen'). A comanda is split into one ticket per printer.
ALTER TABLE service_category ADD COLUMN IF NOT EXISTS id_printer INT NULL;
ALTER TABLE service_category ADD CONSTRAINT fk_category_printer FOREIGN KEY (id_printer) REFERENCES printers (id_printer) ON DELETE SET NULL;
