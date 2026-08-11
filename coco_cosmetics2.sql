-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 31-07-2026 a las 06:48:17
-- Versión del servidor: 10.4.22-MariaDB
-- Versión de PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `coco_cosmetics2`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `role` enum('super','administrador','trabajador') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'trabajador',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id_admin`, `username`, `password`, `role`, `active`, `name`) VALUES
(1, 'admin', '$2y$10$9EMmpuBBN0XufoDuh05huO.a/pIiC.DQZzAReLcC3qA.voKSGtac6', 'super', 1, 'Admin User');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `appointments`
--

CREATE TABLE `appointments` (
  `id_appointment` int(11) NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `id_employee` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `edit_reason` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `appointment_payments`
--

CREATE TABLE `appointment_payments` (
  `id_appointment_payment` int(11) NOT NULL,
  `id_appointment` int(11) NOT NULL,
  `account` tinyint(4) NOT NULL,
  `cordoba` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dolar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `appointment_services`
--

CREATE TABLE `appointment_services` (
  `id_appointment_service` int(11) NOT NULL,
  `id_appointment` int(11) NOT NULL,
  `id_product` int(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bank_account`
--

CREATE TABLE `bank_account` (
  `id_account` int(11) NOT NULL,
  `token_account` varchar(20) NOT NULL,
  `name_account` varchar(60) NOT NULL,
  `type` enum('cordobas','dolares') NOT NULL DEFAULT 'cordobas',
  `buy_rate` decimal(10,2) DEFAULT NULL,
  `sell_rate` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `bank_account`
--

INSERT INTO `bank_account` (`id_account`, `token_account`, `name_account`, `type`, `buy_rate`, `sell_rate`, `active`) VALUES
(1, '00000000000001', 'Banpro cordoba', 'cordobas', '36.40', '37.00', 1),
(3, '00000000000002', 'Banpro dolar', 'dolares', '36.40', '37.00', 1),
(4, '000000001', 'BAC cordoba', 'cordobas', '36.30', '37.40', 1),
(5, '000000002', 'BAC dolar', 'dolares', '36.30', '37.40', 1),
(6, '0000000000001', 'tbanco', 'cordobas', '36.40', '37.00', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cash`
--

CREATE TABLE `cash` (
  `id_cash` int(11) NOT NULL,
  `open_date_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `close_date_time` timestamp NULL DEFAULT NULL,
  `open_quantity` varchar(30) DEFAULT NULL,
  `open_quantity_dollar` varchar(30) DEFAULT NULL,
  `close_quantity_pos` varchar(20) DEFAULT NULL,
  `close_quantity_transfer` varchar(20) DEFAULT NULL,
  `close_quantity_cupon` varchar(20) DEFAULT NULL,
  `close_quantity_cordobas` varchar(30) DEFAULT NULL,
  `close_quantity_dollar` varchar(20) DEFAULT NULL,
  `close_user_quantity_cordobas` varchar(20) DEFAULT NULL,
  `close_user_quantity_dollar` varchar(20) DEFAULT NULL,
  `open_id_admin` int(11) DEFAULT NULL,
  `close_id_admin` int(11) DEFAULT NULL,
  `close_quantity_cancelled_deposits` varchar(20) DEFAULT NULL,
  `open_date` date GENERATED ALWAYS AS (cast(`open_date_time` as date)) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ci_sessions`
--

CREATE TABLE `ci_sessions` (
  `id` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `data` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clients`
--

CREATE TABLE `clients` (
  `id_client` int(11) NOT NULL,
  `id_card_client` varchar(50) DEFAULT NULL,
  `name_client` varchar(50) NOT NULL,
  `surname_client` varchar(50) NOT NULL,
  `phone_client` varchar(20) DEFAULT NULL,
  `email_client` varchar(100) DEFAULT NULL,
  `birth_client` date NOT NULL,
  `registered_client` date NOT NULL DEFAULT current_timestamp(),
  `is_vip_client` tinyint(4) DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dollar_exchange`
--

CREATE TABLE `dollar_exchange` (
  `buy_price` decimal(10,2) DEFAULT 29.50,
  `sell_price` decimal(10,2) DEFAULT 30.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `dollar_exchange`
--

INSERT INTO `dollar_exchange` (`buy_price`, `sell_price`) VALUES
('36.40', '37.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employees`
--

CREATE TABLE `employees` (
  `id_employee` int(3) UNSIGNED ZEROFILL NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `goals`
--

CREATE TABLE `goals` (
  `id_goal` int(11) NOT NULL,
  `month_goal` tinyint(4) NOT NULL CHECK (`month_goal` between 1 and 12),
  `year_goal` year(4) NOT NULL,
  `amount` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `internal_uses`
--

CREATE TABLE `internal_uses` (
  `id_internal_use` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `date_time` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `internal_use_products`
--

CREATE TABLE `internal_use_products` (
  `id_internal_use_product` int(11) NOT NULL,
  `id_internal_use` int(11) NOT NULL,
  `id_product` int(3) UNSIGNED ZEROFILL NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_cost` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoices`
--

CREATE TABLE `invoices` (
  `id_invoice` int(11) NOT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `id_provider` int(11) DEFAULT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoice_products`
--

CREATE TABLE `invoice_products` (
  `id_invoice_product` int(11) NOT NULL,
  `id_invoice` int(11) DEFAULT NULL,
  `id_product` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price_cost` varchar(15) DEFAULT NULL,
  `price_sell` varchar(15) DEFAULT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `loyalty_point_movements`
--

CREATE TABLE `loyalty_point_movements` (
  `id_loyalty_movement` int(11) NOT NULL,
  `id_client` int(11) NOT NULL,
  `id_sale` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `movement_type` enum('accrual','redemption') NOT NULL,
  `points` decimal(10,2) NOT NULL,
  `tier_applied` tinyint(1) DEFAULT NULL,
  `discount_rate_applied` decimal(5,2) DEFAULT NULL,
  `services_total_cordobas` decimal(10,2) DEFAULT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `date_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `loyalty_settings`
--

CREATE TABLE `loyalty_settings` (
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `points_ceiling` int(11) NOT NULL DEFAULT 100,
  `tier1_discount_rate` decimal(5,2) NOT NULL DEFAULT 5.00,
  `tier2_discount_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `tier3_discount_rate` decimal(5,2) NOT NULL DEFAULT 15.00,
  `tier4_discount_rate` decimal(5,2) NOT NULL DEFAULT 20.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `loyalty_settings`
--

INSERT INTO `loyalty_settings` (`enabled`, `points_ceiling`, `tier1_discount_rate`, `tier2_discount_rate`, `tier3_discount_rate`, `tier4_discount_rate`) VALUES
(0, 200, '5.00', '10.00', '15.00', '20.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id_order` int(3) UNSIGNED ZEROFILL NOT NULL,
  `id_provider` int(11) DEFAULT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `file` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders_products`
--

CREATE TABLE `orders_products` (
  `id_order_product` int(11) NOT NULL,
  `id_order` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `id_product` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `outcomes`
--

CREATE TABLE `outcomes` (
  `id_outcome` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `quantity` varchar(30) NOT NULL,
  `description` text NOT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `id_product` int(3) UNSIGNED ZEROFILL NOT NULL,
  `barcode` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `price_cost` varchar(20) COLLATE utf8_unicode_ci DEFAULT '0.00',
  `price_sell` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `stock_alert_threshold` int(11) DEFAULT NULL,
  `is_service` tinyint(4) DEFAULT 0,
  `id_service_category` int(11) DEFAULT NULL,
  `commisssion_rate` decimal(5,2) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_provider` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `providers`
--

CREATE TABLE `providers` (
  `id_provider` int(11) NOT NULL,
  `provider` varchar(150) DEFAULT NULL,
  `representative` varchar(150) NOT NULL DEFAULT '',
  `phone_provider` varchar(20) DEFAULT NULL,
  `email_provider` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `returns`
--

CREATE TABLE `returns` (
  `id_return` int(11) NOT NULL,
  `id_provider` int(11) DEFAULT NULL,
  `id_invoice` int(11) DEFAULT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `returns_products`
--

CREATE TABLE `returns_products` (
  `id_return_product` int(11) NOT NULL,
  `id_return` int(11) DEFAULT NULL,
  `id_product` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price_cost` varchar(20) DEFAULT NULL,
  `price_sell` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sales`
--

CREATE TABLE `sales` (
  `id_sale` int(3) UNSIGNED ZEROFILL NOT NULL,
  `id_employee` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `date_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `client` int(11) DEFAULT NULL,
  `id_appointment` int(11) DEFAULT NULL,
  `dollar_ex_buy` decimal(10,2) DEFAULT NULL,
  `dollar_ex_sell` decimal(10,2) DEFAULT NULL,
  `file` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id_sale_payment` int(11) NOT NULL,
  `id_sale` int(3) UNSIGNED ZEROFILL NOT NULL,
  `payment_method` varchar(8) NOT NULL,
  `account` tinyint(4) DEFAULT NULL,
  `cupon_code` varchar(50) DEFAULT NULL,
  `cupon_discount_rate` decimal(5,2) DEFAULT NULL,
  `cordoba` decimal(7,2) DEFAULT NULL,
  `dolar` decimal(7,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `change_cordoba_given` decimal(7,2) DEFAULT NULL,
  `change_dollar_given` decimal(7,2) DEFAULT NULL,
  `change_rounding_cordoba` decimal(7,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sale_products`
--

CREATE TABLE `sale_products` (
  `id_sale_product` int(11) NOT NULL,
  `id_sale` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `id_product` int(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `discount` int(11) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `my_product` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `service_category`
--

CREATE TABLE `service_category` (
  `id_service_category` int(11) NOT NULL,
  `name_category` varchar(150) NOT NULL,
  `discount_rate` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `id_setting` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`id_setting`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'vip_discount_rate', '15', 'Descuento (%) aplicado automaticamente a los servicios cuando el cliente es VIP'),
(2, 'birthday_discount_rate', '50', 'Descuento (%) aplicado automaticamente a los servicios cuando es el cumpleanos del cliente'),
(3, 'company_name', 'Conceptos', 'Nombre de la empresa mostrado en el tÝtulo y reportes'),
(4, 'logo_header', NULL, 'Nombre de archivo del logo del panel administrativo (vacio = usar imagen por defecto)'),
(5, 'logo_login', NULL, 'Nombre de archivo del logo de la pantalla de inicio de sesion'),
(6, 'logo_receipt', NULL, 'Nombre de archivo del logo impreso en los recibos termicos'),
(7, 'logo_purchase_order', NULL, 'Nombre de archivo del logo en los PDF de ordenes de compra'),
(8, 'receipt_ruc', '2811201760006N', 'RUC impreso en el encabezado del recibo'),
(9, 'receipt_address', 'Costado sur funeraria monte\nlos olivos 2 1/2c al este,\nManagua-Nicaragua.', 'Direccion impresa en el encabezado del recibo (una linea por salto de linea)'),
(10, 'receipt_phone', '8885-1430', 'Telefono impreso en el encabezado del recibo'),
(11, 'receipt_tagline', 'Siente la Diferencia', 'Eslogan impreso en el encabezado del recibo (sin comillas, se agregan al imprimir)'),
(12, 'receipt_footer_notice', 'No se aceptan devoluciones', 'Aviso impreso en el pie del recibo'),
(13, 'receipt_footer_thanks', 'Gracias por preferirnos!!\nLe esperamos pronto.', 'Mensaje de agradecimiento impreso en el pie del recibo (una linea por salto de linea)'),
(14, 'birthday_discount_ceiling_min', '0', 'Monto minimo (C$) del total de servicios sin descuento para que aplique el descuento de cumpleanos'),
(15, 'birthday_discount_ceiling_max', '999999999', 'Monto maximo (C$) del total de servicios sin descuento que puede recibir el descuento de cumpleanos');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id_admin`);

--
-- Indices de la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id_appointment`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_admin` (`id_admin`),
  ADD KEY `start_datetime` (`start_datetime`),
  ADD KEY `idx_appointments_employee_start` (`id_employee`,`start_datetime`),
  ADD KEY `fk_appointments_updated_by` (`updated_by`);

--
-- Indices de la tabla `appointment_payments`
--
ALTER TABLE `appointment_payments`
  ADD PRIMARY KEY (`id_appointment_payment`),
  ADD KEY `id_appointment` (`id_appointment`);

--
-- Indices de la tabla `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`id_appointment_service`),
  ADD KEY `id_appointment` (`id_appointment`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `bank_account`
--
ALTER TABLE `bank_account`
  ADD PRIMARY KEY (`id_account`),
  ADD UNIQUE KEY `token_account` (`token_account`);

--
-- Indices de la tabla `cash`
--
ALTER TABLE `cash`
  ADD PRIMARY KEY (`id_cash`),
  ADD UNIQUE KEY `uniq_open_date` (`open_date`),
  ADD KEY `open_id_admin` (`open_id_admin`),
  ADD KEY `close_id_admin` (`close_id_admin`);

--
-- Indices de la tabla `ci_sessions`
--
ALTER TABLE `ci_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ci_sessions_timestamp` (`timestamp`);

--
-- Indices de la tabla `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id_client`),
  ADD UNIQUE KEY `id_card_client` (`id_card_client`);

--
-- Indices de la tabla `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id_employee`);

--
-- Indices de la tabla `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id_goal`),
  ADD UNIQUE KEY `month_goal` (`month_goal`,`year_goal`);

--
-- Indices de la tabla `internal_uses`
--
ALTER TABLE `internal_uses`
  ADD PRIMARY KEY (`id_internal_use`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `internal_use_products`
--
ALTER TABLE `internal_use_products`
  ADD PRIMARY KEY (`id_internal_use_product`),
  ADD KEY `id_internal_use` (`id_internal_use`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id_invoice`),
  ADD UNIQUE KEY `uniq_invoice_no_provider` (`invoice_no`,`id_provider`),
  ADD KEY `id_provider` (`id_provider`);

--
-- Indices de la tabla `invoice_products`
--
ALTER TABLE `invoice_products`
  ADD PRIMARY KEY (`id_invoice_product`),
  ADD KEY `id_invoice` (`id_invoice`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `loyalty_point_movements`
--
ALTER TABLE `loyalty_point_movements`
  ADD PRIMARY KEY (`id_loyalty_movement`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_sale` (`id_sale`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id_order`),
  ADD KEY `id_provider` (`id_provider`);

--
-- Indices de la tabla `orders_products`
--
ALTER TABLE `orders_products`
  ADD PRIMARY KEY (`id_order_product`),
  ADD KEY `id_order` (`id_order`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `outcomes`
--
ALTER TABLE `outcomes`
  ADD PRIMARY KEY (`id_outcome`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id_product`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_products_provider` (`id_provider`),
  ADD KEY `fk_products_service_category` (`id_service_category`);

--
-- Indices de la tabla `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id_provider`);

--
-- Indices de la tabla `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id_return`),
  ADD KEY `id_provider` (`id_provider`),
  ADD KEY `fk_returns_invoice` (`id_invoice`);

--
-- Indices de la tabla `returns_products`
--
ALTER TABLE `returns_products`
  ADD PRIMARY KEY (`id_return_product`),
  ADD KEY `id_return` (`id_return`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id_sale`),
  ADD UNIQUE KEY `uq_sales_id_appointment` (`id_appointment`),
  ADD KEY `id_employee` (`id_employee`),
  ADD KEY `idx_sales_date` (`date_time`),
  ADD KEY `fk_sales_clients` (`client`);

--
-- Indices de la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id_sale_payment`),
  ADD UNIQUE KEY `uniq_cupon_code` (`cupon_code`),
  ADD KEY `id_sale` (`id_sale`);

--
-- Indices de la tabla `sale_products`
--
ALTER TABLE `sale_products`
  ADD PRIMARY KEY (`id_sale_product`),
  ADD KEY `id_sale` (`id_sale`),
  ADD KEY `id_product` (`id_product`);

--
-- Indices de la tabla `service_category`
--
ALTER TABLE `service_category`
  ADD PRIMARY KEY (`id_service_category`),
  ADD UNIQUE KEY `name_category` (`name_category`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id_setting`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id_appointment` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `appointment_payments`
--
ALTER TABLE `appointment_payments`
  MODIFY `id_appointment_payment` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `appointment_services`
--
ALTER TABLE `appointment_services`
  MODIFY `id_appointment_service` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bank_account`
--
ALTER TABLE `bank_account`
  MODIFY `id_account` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `cash`
--
ALTER TABLE `cash`
  MODIFY `id_cash` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clients`
--
ALTER TABLE `clients`
  MODIFY `id_client` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `employees`
--
ALTER TABLE `employees`
  MODIFY `id_employee` int(3) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `goals`
--
ALTER TABLE `goals`
  MODIFY `id_goal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `internal_uses`
--
ALTER TABLE `internal_uses`
  MODIFY `id_internal_use` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `internal_use_products`
--
ALTER TABLE `internal_use_products`
  MODIFY `id_internal_use_product` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id_invoice` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `invoice_products`
--
ALTER TABLE `invoice_products`
  MODIFY `id_invoice_product` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `loyalty_point_movements`
--
ALTER TABLE `loyalty_point_movements`
  MODIFY `id_loyalty_movement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id_order` int(3) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orders_products`
--
ALTER TABLE `orders_products`
  MODIFY `id_order_product` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `outcomes`
--
ALTER TABLE `outcomes`
  MODIFY `id_outcome` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `id_product` int(3) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `providers`
--
ALTER TABLE `providers`
  MODIFY `id_provider` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `returns`
--
ALTER TABLE `returns`
  MODIFY `id_return` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `returns_products`
--
ALTER TABLE `returns_products`
  MODIFY `id_return_product` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sales`
--
ALTER TABLE `sales`
  MODIFY `id_sale` int(3) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id_sale_payment` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sale_products`
--
ALTER TABLE `sale_products`
  MODIFY `id_sale_product` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `service_category`
--
ALTER TABLE `service_category`
  MODIFY `id_service_category` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `settings`
--
ALTER TABLE `settings`
  MODIFY `id_setting` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointments_employee` FOREIGN KEY (`id_employee`) REFERENCES `employees` (`id_employee`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointments_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL;

--
-- Filtros para la tabla `appointment_payments`
--
ALTER TABLE `appointment_payments`
  ADD CONSTRAINT `fk_appointment_payments_appointment` FOREIGN KEY (`id_appointment`) REFERENCES `appointments` (`id_appointment`) ON DELETE CASCADE;

--
-- Filtros para la tabla `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD CONSTRAINT `appointment_services_ibfk_1` FOREIGN KEY (`id_appointment`) REFERENCES `appointments` (`id_appointment`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_services_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cash`
--
ALTER TABLE `cash`
  ADD CONSTRAINT `cash_ibfk_1` FOREIGN KEY (`open_id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_ibfk_2` FOREIGN KEY (`close_id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL;

--
-- Filtros para la tabla `internal_uses`
--
ALTER TABLE `internal_uses`
  ADD CONSTRAINT `internal_uses_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE CASCADE;

--
-- Filtros para la tabla `internal_use_products`
--
ALTER TABLE `internal_use_products`
  ADD CONSTRAINT `internal_use_products_ibfk_1` FOREIGN KEY (`id_internal_use`) REFERENCES `internal_uses` (`id_internal_use`) ON DELETE CASCADE,
  ADD CONSTRAINT `internal_use_products_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;

--
-- Filtros para la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`id_provider`) REFERENCES `providers` (`id_provider`) ON DELETE SET NULL;

--
-- Filtros para la tabla `invoice_products`
--
ALTER TABLE `invoice_products`
  ADD CONSTRAINT `invoice_products_ibfk_1` FOREIGN KEY (`id_invoice`) REFERENCES `invoices` (`id_invoice`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_products_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;

--
-- Filtros para la tabla `loyalty_point_movements`
--
ALTER TABLE `loyalty_point_movements`
  ADD CONSTRAINT `loyalty_point_movements_ibfk_admin` FOREIGN KEY (`id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL,
  ADD CONSTRAINT `loyalty_point_movements_ibfk_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `loyalty_point_movements_ibfk_sale` FOREIGN KEY (`id_sale`) REFERENCES `sales` (`id_sale`) ON DELETE SET NULL;

--
-- Filtros para la tabla `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`id_provider`) REFERENCES `providers` (`id_provider`) ON DELETE CASCADE;

--
-- Filtros para la tabla `orders_products`
--
ALTER TABLE `orders_products`
  ADD CONSTRAINT `orders_products_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_products_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;

--
-- Filtros para la tabla `outcomes`
--
ALTER TABLE `outcomes`
  ADD CONSTRAINT `outcomes_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL;

--
-- Filtros para la tabla `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_provider` FOREIGN KEY (`id_provider`) REFERENCES `providers` (`id_provider`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_service_category` FOREIGN KEY (`id_service_category`) REFERENCES `service_category` (`id_service_category`) ON DELETE SET NULL;

--
-- Filtros para la tabla `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `fk_returns_invoice` FOREIGN KEY (`id_invoice`) REFERENCES `invoices` (`id_invoice`) ON DELETE SET NULL,
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`id_provider`) REFERENCES `providers` (`id_provider`) ON DELETE SET NULL;

--
-- Filtros para la tabla `returns_products`
--
ALTER TABLE `returns_products`
  ADD CONSTRAINT `returns_products_ibfk_1` FOREIGN KEY (`id_return`) REFERENCES `returns` (`id_return`) ON DELETE CASCADE,
  ADD CONSTRAINT `returns_products_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_appointment` FOREIGN KEY (`id_appointment`) REFERENCES `appointments` (`id_appointment`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_clients` FOREIGN KEY (`client`) REFERENCES `clients` (`id_client`),
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`id_employee`) REFERENCES `employees` (`id_employee`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `sale_payments_ibfk_1` FOREIGN KEY (`id_sale`) REFERENCES `sales` (`id_sale`);

--
-- Filtros para la tabla `sale_products`
--
ALTER TABLE `sale_products`
  ADD CONSTRAINT `sale_products_ibfk_1` FOREIGN KEY (`id_sale`) REFERENCES `sales` (`id_sale`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_products_ibfk_2` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
