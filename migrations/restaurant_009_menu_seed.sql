-- Restaurant menu seed: retire salon-era demo products/services (soft delete keeps sale history)
-- and load a Nicaraguan menu. Prices are in DOLLARS (products.price_sell convention); screens show C$ = $ x sell rate.
UPDATE products SET active = 0 WHERE barcode IS NULL OR barcode <> 'SPLIT-CONSUMO';
UPDATE products SET barcode = NULL WHERE active = 0;
UPDATE products SET id_service_category = NULL WHERE active = 0;
DELETE FROM service_category WHERE name_category = 'tcategoria';
INSERT INTO service_category (name_category) VALUES ('Entradas'),('Platos fuertes'),('Bebidas'),('Cervezas y licores'),('Postres');

-- is_service=1: made to order (no stock); is_service=0: stocked item
INSERT INTO products (barcode,name,price_cost,price_sell,stock,stock_alert_threshold,is_service,id_service_category,id_provider,active,prints_kitchen)
SELECT v.barcode,v.name,v.cost,v.sell,v.stock,v.thr,v.svc,c.id_service_category,NULL,1,v.kit
FROM (
 SELECT 'ENT-001' barcode,'Queso frito con tajadas' name,'1.20' cost,'3.50' sell,0 stock,NULL thr,1 svc,'Entradas' cat,1 kit UNION ALL
 SELECT 'ENT-002','Tostones con queso','1.00','3.00',0,NULL,1,'Entradas',1 UNION ALL
 SELECT 'ENT-003','Guacamole con chips','1.40','4.00',0,NULL,1,'Entradas',1 UNION ALL
 SELECT 'ENT-004','Sopa de queso','1.60','4.50',0,NULL,1,'Entradas',1 UNION ALL
 SELECT 'PLA-001','Gallo pinto con carne asada','2.60','7.00',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-002','Churrasco con chimichurri','4.50','11.00',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-003','Pollo a la plancha','2.80','8.00',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-004','Pescado frito entero','3.60','9.50',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-005','Vigorón','1.20','3.50',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-006','Nacatamal','1.10','3.00',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-007','Hamburguesa de la casa','2.40','6.50',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'PLA-008','Arroz a la valenciana','3.20','8.50',0,NULL,1,'Platos fuertes',1 UNION ALL
 SELECT 'BEB-001','Fresco de cacao','0.40','1.50',0,NULL,1,'Bebidas',0 UNION ALL
 SELECT 'BEB-002','Fresco de tamarindo','0.30','1.20',0,NULL,1,'Bebidas',0 UNION ALL
 SELECT 'BEB-003','Jugo natural','0.60','2.00',0,NULL,1,'Bebidas',0 UNION ALL
 SELECT 'BEB-004','Café americano','0.30','1.50',0,NULL,1,'Bebidas',0 UNION ALL
 SELECT 'BEB-005','Gaseosa','0.45','1.20',48,12,0,'Bebidas',0 UNION ALL
 SELECT 'BEB-006','Agua embotellada','0.30','1.00',48,12,0,'Bebidas',0 UNION ALL
 SELECT 'CER-001','Cerveza Toña','0.85','1.80',72,24,0,'Cervezas y licores',0 UNION ALL
 SELECT 'CER-002','Cerveza Victoria','0.85','1.80',72,24,0,'Cervezas y licores',0 UNION ALL
 SELECT 'CER-003','Cerveza Premium','1.00','2.20',48,12,0,'Cervezas y licores',0 UNION ALL
 SELECT 'CER-004','Ron Flor de Caña 4 años (trago)','0.90','2.50',40,10,0,'Cervezas y licores',0 UNION ALL
 SELECT 'POS-001','Tres leches','0.90','3.00',0,NULL,1,'Postres',1 UNION ALL
 SELECT 'POS-002','Flan de coco','0.70','2.50',0,NULL,1,'Postres',1 UNION ALL
 SELECT 'POS-003','Helado (2 bolas)','0.60','2.00',0,NULL,1,'Postres',1
) v JOIN service_category c ON c.name_category = v.cat;
