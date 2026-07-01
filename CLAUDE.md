# Coco Cosmetics — Billing System

Internal point-of-sale and inventory management system for a cosmetics retailer in Nicaragua. Handles sales, purchases, cash drawer, employees, clients, and statistics.

## Tech Stack

- **Backend:** PHP 5/7 · CodeIgniter 2 (CI2) + HMVC via `MX` module extension
- **Database:** MySQL (served by XAMPP)
- **Frontend:** Bootstrap 3 · Gentelella admin theme · jQuery · Chart.js · PNotify
- **Printing:** ESC/POS thermal receipt printer via `mike42/escpos-php` (`print/autoload.php`)
- **PDF:** html2pdf (`includes/html2pdf/`)

## Key Directories

| Path | Purpose |
|---|---|
| `application/controllers/` | One controller per domain (Sales, Products, Cash, Clients, …) |
| `application/models/` | Thin DB-access layer; named `<Domain>_mdl.php` |
| `application/views/` | PHP templates; forms prefixed `form_`, lists match domain name |
| `application/core/` | `MY_Controller.php` — base classes `MY_Controller` and `Admin_Controller` |
| `application/libraries/` | `Easysearch.php` (filterable paginated queries), `Imagetool.php`, `MY_PHPMailer.php` |
| `application/helpers/` | `data_helper.php` (session flash, array utils), `misc_helper.php` (form token, ASCII) |
| `application/config/` | CI2 config files; `autoload.php` registers global libs/helpers |
| `assets/` | CSS (`admin.css`), JS (`bootbox`, `numeraljs`), Gentelella vendor bundle |
| `uploads/` | Product images |
| `orders_pdf/` | Generated order PDFs |

## Running the App

No build step. Start Apache + MySQL via XAMPP Control Panel, then open `http://localhost/` in a browser.

- Default route: `login` (`application/config/routes.php:52`)
- Database credentials: `application/config/database.php`

## Testing

No automated test suite. Verify changes manually in the browser.

## Additional Documentation

Check these files when working in the relevant area:

- `.claude/docs/architectural_patterns.md` — Controller/Model conventions, Easysearch pattern, AJAX response shape, session flash, form CSRF token, thermal printing flow

## Adding New Features or Fixing bugs

**IMPORTANT**: When you work on a new feature or bug, create a git branch first. Then work on changes in that branch for the remainder of the session.

**IMPORTANT**: If you alter the database schema (ADD/DROP/MODIFY columns, create/drop tables, rename fields), you MUST update the Database Schema section in this file to reflect those changes before finishing the task.

## Database Schema (`coco_cosmetics2`)

> Monetary/price values are inconsistently typed: product prices and invoice costs use `VARCHAR`; payment amounts and exchange rates use `DECIMAL`. All PKs are auto-increment integers. FK constraints are enforced at the DB level using `ON DELETE CASCADE` or `ON DELETE SET NULL`. `products` is queried with `WHERE active=1` in the app but the `active` column is absent from the SQL dump — it exists in the live DB. `products.commisssion_rate` has a typo (three s's) that is preserved in the column name.

### Core entities

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `admins` | `id_admin` | `username`, `password` (varchar 40 — MD5/SHA1), `name` | System login accounts |
| `employees` | `id_employee` | `name`, `phone`, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Sales staff assigned to sales |
| `clients` | `id_client` | `id_card_client` VARCHAR(50) DEFAULT NULL UNIQUE (national ID — nullable; multiple NULLs allowed by MySQL UNIQUE), `name_client`, `surname_client`, `birth_client`, `is_vip_client` TINYINT, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Customer profiles |
| `products` | `id_product` | `barcode` (UNIQUE), `name`, `description` TEXT, `price_cost` VARCHAR, `price_sell` VARCHAR, `stock`, `is_service` TINYINT (1 = no stock deducted on sale), `commisssion_rate` DECIMAL, `id_provider`->`providers.id_provider`, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Product catalog + live stock levels |
| `providers` | `id_provider` | `provider` VARCHAR(150) DEFAULT NULL (company name — optional), `representative` VARCHAR(150) NOT NULL DEFAULT '' (contact person — required on new records), `phone_provider` VARCHAR(20) DEFAULT NULL, `email_provider` VARCHAR(100) DEFAULT NULL, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists and invoice form) | Supplier/brand names |

### Sales flow

| Table | PK | Key columns | Links |
|---|---|---|---|
| `sales` | `id_sale` | `id_employee`->`employees.id_employee`, `client`->`clients.id_client`, `date_time`, `dollar_ex_buy` DECIMAL, `dollar_ex_sell` DECIMAL (exchange rate snapshot at time of sale) | Sale header |
| `sale_products` | `id_sale_product` | `id_sale`->`sales.id_sale`, `id_product`->`products.id_product`, `quantity`, `price` VARCHAR (sell price at time of sale), `discount` INT (percentage), `commission_rate` DECIMAL, `my_product` TINYINT | Sale line items; stock decremented on save unless `is_service=1` |
| `sale_payments` | `id_sale_payment` | `id_sale`->`sales.id_sale`, `payment_method` VARCHAR(8) (cash/pos/transfer), `account` TINYINT (references `bank_account.id_account`, no FK enforced), `cordoba` DECIMAL, `dolar` DECIMAL, `tax_rate` DECIMAL (POS commission %, typically 6.75) | One or two payment rows per sale (split payments supported) |

### Purchase flow

| Table | PK | Key columns | Links |
|---|---|---|---|
| `invoices` | `id_invoice` | `invoice_no`, `id_provider`->`providers.id_provider`, `date_time` | Purchase invoice header |
| `invoice_products` | `id_invoice_product` | `id_invoice`->`invoices.id_invoice`, `id_product`->`products.id_product`, `quantity`, `price_cost` VARCHAR, `price_sell` VARCHAR | Invoice line items |
| `orders` | `id_order` | `id_provider`->`providers.id_provider`, `date_time`, `file` VARCHAR (attached PDF filename) | Purchase order to a supplier |
| `orders_products` | `id_order_product` | `id_order`->`orders.id_order`, `id_product`->`products.id_product`, `quantity` | Order line items |
| `returns` | `id_return` | `id_provider`->`providers.id_provider`, `id_invoice`->`invoices.id_invoice` (nullable, ON DELETE SET NULL), `date_time` | Return-to-supplier header |
| `returns_products` | `id_return_product` | `id_return`->`returns.id_return`, `id_product`->`products.id_product`, `quantity`, `price_cost` VARCHAR, `price_sell` VARCHAR | Return line items |

### Cash & financial

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `cash` | `id_cash` | `open_date_time`, `close_date_time`, `open_quantity` VARCHAR (opening cash amount), `close_quantity_cordobas/dollar/pos/transfer` VARCHAR, `close_user_quantity_cordobas/dollar` VARCHAR, `open_id_admin`->`admins.id_admin`, `close_id_admin`->`admins.id_admin` | Daily cash drawer session; one open row at a time gates sale registration |
| `outcomes` | `id_outcome` | `id_admin`->`admins.id_admin`, `quantity` VARCHAR, `description` TEXT, `date_time` | Cash outflows/expenses subtracted from cash state |
| `bank_account` | `id_account` | `token_account` (UNIQUE — display identifier), `name_account`, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from transfer dropdowns) | Bank accounts used as targets for transfer payments |
| `dollar_exchange` | — | `buy_price` DECIMAL DEFAULT 29.50, `sell_price` DECIMAL DEFAULT 30.00 | Single-row table; buy rate used for cordoba conversions, sell rate printed on receipts |
| `goals` | `id_goal` | `month_goal` TINYINT (1–12, CHECK constraint), `year_goal` YEAR, `amount` DECIMAL; UNIQUE(`month_goal`, `year_goal`) | Monthly sales targets; visible only to admin (id_admin=1) |

### Framework

`ci_sessions` — standard CodeIgniter database session storage; not application data.