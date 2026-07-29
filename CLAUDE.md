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
| `admins` | `id_admin` | `username`, `password` (varchar 40 — SHA1), `role` ENUM('super','administrador','trabajador') NOT NULL DEFAULT 'trabajador' (three-tier hierarchy: `Admin_Controller::is_admin()` returns true for both `super` and `administrador`, gating the general admin-only pages/actions; `Admin_Controller::is_super()` is stricter, true only for `super`, and additionally gates `/admins` account management, `/loyalty_points`, and `service_categories` create/edit — `administrador` can view the service-category list but not mutate it), `active` TINYINT(1) NOT NULL DEFAULT 1 (soft-delete; 0 = hidden from `/admins/list` and rejected at login), `name` | System login accounts |
| `employees` | `id_employee` | `name`, `phone`, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Sales staff assigned to sales |
| `clients` | `id_client` | `id_card_client` VARCHAR(50) DEFAULT NULL UNIQUE (national ID — nullable; multiple NULLs allowed by MySQL UNIQUE), `name_client`, `surname_client`, `birth_client`, `is_vip_client` TINYINT, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Customer profiles |
| `products` | `id_product` | `barcode` (UNIQUE), `name`, `description` TEXT, `price_cost` VARCHAR, `price_sell` VARCHAR, `stock`, `stock_alert_threshold` INT NULL DEFAULT NULL (low-stock alert threshold; NULL = no alert configured; only meaningful when `is_service=0` — forced to NULL server-side for services; a product is "low stock" when `active=1 AND is_service=0 AND stock_alert_threshold IS NOT NULL AND stock <= stock_alert_threshold`, surfaced via the bell icon/badge/modal next to `menu_toggle` in `header.php`, backed by `Products::lowStockCount()`/`lowStockList()`), `is_service` TINYINT (1 = no stock deducted on sale), `id_service_category`->`service_category.id_service_category` (nullable; optional category, only meaningful when `is_service=1`; `ON DELETE SET NULL`), `commisssion_rate` DECIMAL, `id_provider`->`providers.id_provider`, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists) | Product catalog + live stock levels |
| `providers` | `id_provider` | `provider` VARCHAR(150) DEFAULT NULL (company name — optional), `representative` VARCHAR(150) NOT NULL DEFAULT '' (contact person — required on new records), `phone_provider` VARCHAR(20) DEFAULT NULL, `email_provider` VARCHAR(100) DEFAULT NULL, `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from lists and invoice form) | Supplier/brand names |
| `service_category` | `id_service_category` | `name_category` VARCHAR(150) UNIQUE NOT NULL, `discount_rate` DECIMAL(5,2) NULL DEFAULT NULL (percentage 0–100; auto-fills the invoice line discount when a service in this category is added to a sale in `form_sales.php`, still editable per line at sale time; NULL = no default discount) | Optional categories for service-type products; managed via `/service_categories` (admin-only create/edit), no soft-delete |

### Appointments

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `appointments` | `id_appointment` | `id_client`->`clients.id_client` (`ON DELETE CASCADE`), `id_admin`->`admins.id_admin` (`ON DELETE CASCADE`; who booked it, audit only), `updated_by`->`admins.id_admin` (`ON DELETE SET NULL`; nullable; who last edited it, NULL until first edit), `id_employee`->`employees.id_employee` (`ON DELETE SET NULL`; nullable; which employee performs the appointment — live-only column, not yet in the checked-in SQL dump, same as `products.active`), `start_datetime`/`end_datetime` DATETIME NOT NULL, `notes` TEXT NULL, `updated_at` DATETIME NULL (timestamp of the last edit; NULL until first edit), `edit_reason` TEXT NULL (mandatory free-text reason captured via a modal on every `Appointments::update()`, NULL on the original booking), `active` TINYINT(1) NOT NULL DEFAULT 1 (soft-delete; 0 = cancelled — hidden from the schedule grid, the month-view count badges, and the sales-continuation flow (`Sales::buildAppointmentPrefill()`); a cancelled appointment's `appointment_payments` deposit row is retained and forfeited, never deleted, per the no-refund policy — live-only column, not yet in the checked-in SQL dump, same as `id_employee` above), `cancelled_at` DATETIME NULL (stamped alongside `active=0` in `Appointments_mdl::delete()`; used by `Cash_mdl::getCashMovements()` to attribute a cancelled appointment's forfeited deposit to the cash-drawer day it was cancelled on — surfaced as its own "Depósitos de reservas canceladas" section in `/cash/state` and the historical `cash_details` view, folded into that day's `sales_total_transfer` — live-only column, not yet in the checked-in SQL dump, same as `active` above) | Client service reservation, independent of the sales/billing flow; viewable via `/appointments` (month calendar, day cells show only a reservation-count badge) and booked/viewed in detail via `/appointments/schedule/:date` (a per-employee hourly grid, 24 one-hour columns starting 01:00 through 00:00 the next day); booking now requires an open till and a mandatory 10% transfer deposit (see `appointment_payments`); cancellable only by admins (`Appointments::delete()` gates on `is_admin()`, soft-deletes via `active=0` rather than removing the row), and neither `update()` nor `delete()` are permitted once the appointment has a linked `sales` row (`Appointments_mdl::isInvoiced()`) |
| `appointment_services` | `id_appointment_service` | `id_appointment`->`appointments.id_appointment` (`ON DELETE CASCADE`), `id_product`->`products.id_product` (`ON DELETE CASCADE`; product where `is_service=1`) | Junction table — one or more services requested per appointment |
| `appointment_payments` | `id_appointment_payment` | `id_appointment`->`appointments.id_appointment` (`ON DELETE CASCADE`), `account` TINYINT (references `bank_account.id_account`, no FK enforced, same convention as `sale_payments.account`), `cordoba` DECIMAL(10,2), `dolar` DECIMAL(10,2), `date_time` DATETIME DEFAULT CURRENT_TIMESTAMP | The mandatory transfer deposit collected at booking time — always exactly 10% of the services total at that moment (server-computed in `Appointments::resolveDeposit()`, never client-trusted), converted into the chosen `bank_account`'s currency the same way `Sales::register()` converts transfer payments; one row per appointment in practice, frozen permanently — never recalculated or topped up when the appointment's services/date are later edited. Consumed by `Sales::register()` when an appointment is continued into a sale (see `sales.id_appointment`): copied verbatim into a `sale_payments` row and credited against the invoice total, with any excess forfeited (no refund policy) |

### Sales flow

| Table | PK | Key columns | Links |
|---|---|---|---|
| `sales` | `id_sale` | `id_employee`->`employees.id_employee`, `client`->`clients.id_client`, `id_appointment`->`appointments.id_appointment` (nullable, `ON DELETE SET NULL`, `UNIQUE` — set only when the sale is a continuation of a deposited appointment via `Sales::register($id_appointment)`; the `UNIQUE` constraint plus an app-level pre-check in `Sales::register()` ensure an appointment can only ever be continued into one sale, at which point `Appointments_mdl::isInvoiced()` starts reporting it as locked), `date_time`, `dollar_ex_buy` DECIMAL, `dollar_ex_sell` DECIMAL (exchange rate snapshot at time of sale) | Sale header |
| `sale_products` | `id_sale_product` | `id_sale`->`sales.id_sale`, `id_product`->`products.id_product`, `quantity`, `price` VARCHAR (sell price at time of sale), `discount` INT (percentage), `commission_rate` DECIMAL, `my_product` TINYINT | Sale line items; stock decremented on save unless `is_service=1` |
| `sale_payments` | `id_sale_payment` | `id_sale`->`sales.id_sale`, `payment_method` VARCHAR(8) (cash/pos/transfer/cupon), `account` TINYINT (references `bank_account.id_account`, no FK enforced), `cupon_code` VARCHAR(50) NULL (coupon code; only set for `cupon` rows — enforced unique across all sales at the application level in `Sales::register()`, not by a DB constraint), `cupon_discount_rate` DECIMAL(5,2) NULL (bank/processor discount % on the coupon's tendered amount; only set for `cupon` rows — `cordoba * (1 - cupon_discount_rate/100)` is the real revenue recognized for that coupon, as opposed to the full tendered `cordoba` which is what covers the invoice), `cordoba` DECIMAL, `dolar` DECIMAL, `tax_rate` DECIMAL (POS commission %, typically 6.75), `change_cordoba_given` DECIMAL(7,2) NULL (córdoba physically handed back to the customer as change; only set on the sale's `cash` row, and only when that row overpays the invoice), `change_dollar_given` DECIMAL(7,2) NULL (dollars physically handed back as change; same row/condition as above — cashier may split change across both currencies regardless of what the customer tendered in), `change_rounding_cordoba` DECIMAL(7,2) NULL (signed córdoba-equivalent leftover between change owed and change actually given; app rejects a whole-unit leftover, only fractional/cents rounding is allowed) | One or more payment rows per sale (split payments supported); for `transfer` rows, `cordoba`/`dolar` are stored in the resolved account's currency (converted via `bank_account.buy_rate`/`sell_rate` if the employee typed the other currency), not necessarily the currency originally typed; for `cupon` rows, each coupon's tendered amount must fit within what's left of the invoice after prior coupons (evaluated coupon-by-coupon in submission order, independent of other payment methods); only `cash` may cover an overpayment (`Sales::register()` rejects POS/transfer/cupon overpaying the invoice); when the sale continues a deposited appointment (`sales.id_appointment` set), one extra `transfer` row is appended holding the appointment's frozen `appointment_payments` deposit verbatim — this row is excluded from all payment-sufficiency/overpay validation (it's already-collected money, not a new payment decision), and the invoice total used for those checks on the newly-entered rows is first reduced by the deposit's córdoba-equivalent (floored at 0) |

### Purchase flow

| Table | PK | Key columns | Links |
|---|---|---|---|
| `invoices` | `id_invoice` | `invoice_no`, `id_provider`->`providers.id_provider`, `date_time` | Purchase invoice header |
| `invoice_products` | `id_invoice_product` | `id_invoice`->`invoices.id_invoice`, `id_product`->`products.id_product`, `quantity`, `price_cost` VARCHAR, `price_sell` VARCHAR | Invoice line items |
| `orders` | `id_order` | `id_provider`->`providers.id_provider`, `date_time`, `file` VARCHAR (attached PDF filename) | Purchase order to a supplier |
| `orders_products` | `id_order_product` | `id_order`->`orders.id_order`, `id_product`->`products.id_product`, `quantity` | Order line items |
| `returns` | `id_return` | `id_provider`->`providers.id_provider`, `id_invoice`->`invoices.id_invoice` (nullable, ON DELETE SET NULL), `date_time` | Return-to-supplier header |
| `returns_products` | `id_return_product` | `id_return`->`returns.id_return`, `id_product`->`products.id_product`, `quantity`, `price_cost` VARCHAR, `price_sell` VARCHAR | Return line items |

### Internal product usage

| Table | PK | Key columns | Links |
|---|---|---|---|
| `internal_uses` | `id_internal_use` | `id_admin`->`admins.id_admin` (`ON DELETE CASCADE`; who registered the withdrawal), `date_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `reason` TEXT NOT NULL (mandatory business justification, e.g. store display, damaged write-off, employee use) | Header for a stock withdrawal used internally by the business rather than sold; reachable via `/products/internalUse` (register) and `/products/internal_use_list` (history), open to any logged-in admin/employee (no `is_admin()` gate) |
| `internal_use_products` | `id_internal_use_product` | `id_internal_use`->`internal_uses.id_internal_use` (`ON DELETE CASCADE`), `id_product`->`products.id_product` (`ON DELETE CASCADE`; must be `is_service=0`, services are rejected), `quantity` INT NOT NULL, `price_cost` VARCHAR(20) NOT NULL (cost snapshot at withdrawal time, mirrors `returns_products`/`invoice_products`) | Line items; stock decremented per product on save (`Internal_use_mdl::saveProducts()`), validated against current stock inside the same DB transaction as the header insert |

### Cash & financial

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `cash` | `id_cash` | `open_date_time`, `close_date_time`, `open_quantity` VARCHAR (opening cordoba amount), `open_quantity_dollar` VARCHAR (opening dollar amount), `close_quantity_cordobas/dollar/pos/transfer/cupon` VARCHAR (`close_quantity_cupon` is the net-of-discount coupon revenue snapshot, mirroring `Cash_mdl::getCashMovements()`'s `sales_total_cupon`; `close_quantity_transfer` is real sale transfers only), `close_quantity_cancelled_deposits` VARCHAR(20) NULL (closing-time snapshot of that day's total forfeited appointment-deposit revenue — see `appointments.cancelled_at` — kept as its own column, separate from `close_quantity_transfer`, so the split survives even if the source `appointments`/`appointment_payments` rows are ever archived), `close_user_quantity_cordobas/dollar` VARCHAR, `open_id_admin`->`admins.id_admin`, `close_id_admin`->`admins.id_admin` | Daily cash drawer session; one open row at a time gates sale registration |
| `outcomes` | `id_outcome` | `id_admin`->`admins.id_admin`, `quantity` VARCHAR, `description` TEXT, `date_time` | Cash outflows/expenses subtracted from cash state |
| `bank_account` | `id_account` | `token_account` (UNIQUE — display identifier), `name_account`, `type` ENUM('cordobas','dolares') (currency the account is held in), `buy_rate`/`sell_rate` DECIMAL(10,2) NULL (per-account exchange rates; used to convert a transfer payment into the account's currency before it's stored in `sale_payments` — see `Sales::register()`), `active` TINYINT(1) DEFAULT 1 (soft-delete; 0 = hidden from transfer dropdowns and rejected if submitted directly at sale time) | Bank accounts used as targets for transfer payments |
| `dollar_exchange` | — | `buy_price` DECIMAL DEFAULT 29.50, `sell_price` DECIMAL DEFAULT 30.00 | Single-row table; buy rate values every córdoba conversion in the sale/print/cash-drawer flow (what a client is charged, receipts, drawer reconciliation); sell rate is unused in that flow, only shown in a live commission-value display |
| `goals` | `id_goal` | `month_goal` TINYINT (1–12, CHECK constraint), `year_goal` YEAR, `amount` DECIMAL; UNIQUE(`month_goal`, `year_goal`) | Monthly sales targets; visible only to admin (id_admin=1) |

### Loyalty program

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `loyalty_settings` | — | `enabled` TINYINT(1) DEFAULT 0, `points_ceiling` INT DEFAULT 100, `tier1_discount_rate`..`tier4_discount_rate` DECIMAL(5,2) (% discount on services granted at each of the 4 tiers; tier point-thresholds are never stored — always derived at runtime as `points_ceiling * n/4` for n=1..4) | Single-row config table (no PK, same shape as `dollar_exchange`); admin-editable via `/loyalty_points`, gated by `Admin_Controller::is_admin()` |
| `loyalty_point_movements` | `id_loyalty_movement` | `id_client`->`clients.id_client` (`ON DELETE CASCADE`), `id_sale`->`sales.id_sale` (nullable, `ON DELETE SET NULL`), `movement_type` ENUM('accrual','redemption'), `points` DECIMAL(10,2) (signed — positive for accrual, negative for redemption; a client's current balance is the most recent `balance_after` row, not a live SUM), `tier_applied`/`discount_rate_applied` (redemption rows only; rate is a snapshot at redemption time so later admin edits to `loyalty_settings` never retroactively change history), `services_total_cordobas` (accrual rows only; the post-loyalty-discount services subtotal the 2% accrual was computed from — `Loyalty_points_mdl::ACCRUAL_RATE`), `balance_after` DECIMAL(10,2) (running balance snapshot after this movement), `id_admin`->`admins.id_admin` (nullable, audit only — who processed the sale) | Traceable per-client points ledger, viewable via `/loyalty_points/registry/:id_client` (reached from the clients list' "Registro de puntos" action); written atomically inside `Sales::register()`'s existing transaction, right after `saveSale()`. A redemption burns the point threshold (`ceiling * tier/4`) for the highest tier the client currently qualifies for (not their full balance) and discounts that sale's services by the tier's rate; the discount is a pure write-off (no `sale_payments` row), applied to `$remaining_cordobas` and to the coupon cap (`$coupon_remaining`) before coupon validation runs, so a coupon can't be validated against services value already given away as a loyalty reward |

### App settings

| Table | PK | Key columns | Purpose |
|---|---|---|---|
| `settings` | `id_setting` | `setting_key` VARCHAR(100) UNIQUE NOT NULL, `setting_value` VARCHAR(255), `description` VARCHAR(255) | Generic key/value store for app-wide config parameters, intended to grow over time (new settings are new rows, no schema change — `Settings_mdl::save()` only UPDATEs an existing row, so a brand-new key must be seeded with an `INSERT` before it can be edited via `/settings`); admin-editable via `/settings`, gated by `Admin_Controller::is_admin()`. Currently holds `vip_discount_rate` (default `15`) and `birthday_discount_rate` (default `50`) — % discounts auto-filled on service line items in `/sales/register` when the selected client has `clients.is_vip_client=1` or when today's date matches the month/day of `clients.birth_client`, respectively; on each service row these compete with that service's `service_category.discount_rate` default and the highest of the three auto-fills `sale_products.discount` (still editable per line) — and `company_name` (default `Conceptos`, mandatory/non-empty) — the company name shown in the browser tab title (`header.php`, `form_login.php`) and the opening-cash-drawer email report heading (`open_cash_report.php`); loaded once per request in `Admin_Controller::__construct()` into `$this->header_data['company_name']` for admin pages, and fetched directly by `Login` (extends `MX_Controller`, no `Admin_Controller`) for the pre-auth login page. Also holds 4 dynamic-logo keys — `logo_header`, `logo_login`, `logo_receipt`, `logo_purchase_order` (all default `NULL`) — each storing the generated filename (32-char hex + original extension, e.g. `a1b2...c3.png`) of an admin-uploaded replacement for one of the 4 previously-hardcoded `assets/img/` logos; physical files live in `uploads/logos/` (gitignored). `Settings::uploadLogo()` validates (extension whitelist png/jpg/jpeg, `getimagesize()` real-image check, 5MB max), re-encodes via GD (`Settings::compressLogoImage()` — downscales past 1000px and re-saves at `imagepng` level 9 / `imagejpeg` quality 85, both visually lossless) into a freshly hashed filename, updates the `setting_value` only after the new file is confirmed on disk, and only then deletes the previous custom file (rollback-safe: a DB-save failure after upload deletes the just-written file and leaves the old value/file untouched). `resolveLogoUrl()`/`resolveLogoPath()` (`misc_helper.php`) resolve each key to either the custom `uploads/logos/` file (if set and still present on disk) or the original bundled `assets/img/` file, which is never deleted and acts as the permanent fallback — consumed via `resolveLogoUrl()` in `header.php`/`form_login.php` (browser `<img>`) and `resolveLogoPath()` in `Sales.php`'s thermal-receipt printing and `Orders.php`'s purchase-order PDF generation (server-side file paths) |

### Framework

`ci_sessions` — standard CodeIgniter database session storage; not application data.