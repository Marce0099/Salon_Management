-- Restaurant conversion, Stage 1: remove salon-only features. Apply to DB `restaurant`.
-- Existing sales/products/settings (company name, logos, receipt keys) are preserved.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS appointment_payments;
DROP TABLE IF EXISTS appointment_services;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS loyalty_point_movements;
DROP TABLE IF EXISTS loyalty_settings;

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE sales DROP FOREIGN KEY fk_sales_appointment;
ALTER TABLE sales DROP INDEX uq_sales_id_appointment;
ALTER TABLE sales DROP COLUMN id_appointment;

ALTER TABLE sale_products DROP COLUMN my_product;
ALTER TABLE service_category DROP COLUMN discount_rate;

DELETE FROM settings WHERE setting_key IN
  ('vip_discount_rate','birthday_discount_rate','birthday_discount_ceiling_min','birthday_discount_ceiling_max');

-- Default walk-in client used when a bill is charged without picking a client
INSERT INTO clients (id_card_client, name_client, surname_client, birth_client, active)
SELECT NULL, 'Consumidor', 'Final', '2000-01-01', 1
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE name_client='Consumidor' AND surname_client='Final');

INSERT INTO settings (setting_key, setting_value, description)
SELECT 'default_client_id', (SELECT id_client FROM clients WHERE name_client='Consumidor' AND surname_client='Final' LIMIT 1),
       'Cliente por defecto (Consumidor Final) cuando la venta no tiene cliente'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='default_client_id');

INSERT INTO settings (setting_key, setting_value, description)
SELECT k, v, d FROM (
  SELECT 'tip_enabled' k, '1' v, 'Propina sugerida activa (1/0)' d
  UNION ALL SELECT 'tip_rate', '10', 'Porcentaje de propina sugerida'
  UNION ALL SELECT 'tax_rate_iva', '0', 'Porcentaje de IVA aplicado a la cuenta (0 = sin IVA)'
  UNION ALL SELECT 'kitchen_printer_name', 'COCINA', 'Nombre de la impresora Windows para comandas de cocina'
) t WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = t.k);
