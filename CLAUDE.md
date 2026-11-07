# Restaurant POS — Floor, Orders, Bills & Billing System

Internal point-of-sale for a restaurant in Nicaragua (originally a salon/cosmetics billing app; converted in 8 stages, see "Restaurant flow"). Waiters use it on phones/tablets: free-form floor plan with table states, reservations, ordering with a kitchen ticket, floating/split bills, and checkout through the original multi-currency payment engine. Also: purchases, stock, cash drawer, waiters (`employees`), clients, reports.

## Tech Stack

- **Backend:** PHP 7/8 · CodeIgniter **3.0** (`system/`) + HMVC via the `MX` module extension. (Older notes say "CI2": the query builder `group_start()` etc. is CI3.)
- **Database:** MariaDB/MySQL (XAMPP), DB name `restaurant` (credentials in `application/config/database.php`, `.env` `DB_NAME=restaurant`). Charset of the app connection is 3-byte `utf8`; the mysql CLI needs `--default-character-set=utf8mb4` for accents.
- **Frontend:** Bootstrap 3 · Gentelella admin theme · jQuery · Chart.js · PNotify · bootbox. Mobile layer: `assets/css/mobile.css` (touch sizes, bottom nav, slide-in drawer) and `assets/css/floor.css` (floor canvas).
- **Printing:** ESC/POS thermal printers through `mike42/escpos-php` (`print/autoload.php`): receipts → Windows printer `CAJA`; kitchen tickets → printer named in setting `kitchen_printer_name` (default `COCINA`). Neither printer exists on every machine: failures are logged/recorded and never fail a sale or order.
- **PDF:** html2pdf (`includes/html2pdf/`) → `uploads/receipts/` (receipt copy per sale).

## Key Directories

| Path | Purpose |
|---|---|
| `application/controllers/` | One controller per domain (Floor, Reservations, Kitchen, Bills, Sales, Reports, Products, Cash, …) |
| `application/models/` | Thin DB layer `<Domain>_mdl.php` (`Floor_mdl`, `Kitchen_mdl`, `Bills_mdl`, `Reservations_mdl`, `Reports_mdl`, …) |
| `application/views/` | PHP templates; forms prefixed `form_` |
| `application/core/MY_Controller.php` | `Admin_Controller` base (session login, `renderPanel`, `is_admin()`, `is_super()`) |
| `application/libraries/` | `Easysearch.php`, `Imagetool.php`, `MY_PHPMailer.php` |
| `migrations/` | `restaurant_00N_*.sql`, applied by hand to DB `restaurant` (pre-stage dumps are git-excluded) |
| `.claude/docs/architectural_patterns.md` | Controller/Model conventions, Easysearch, AJAX shape, session flash, form token, thermal printing |

## Running the App

No build step. Start Apache + MySQL from XAMPP, open `http://localhost/`. Default route `login`. No automated tests: verify in the browser (resize_window does not change the viewport in Chrome automation; use a 390px iframe to emulate a phone). PHP lint: `C:\xampp\php\php.exe -l file`.

## Working agreements

- **IMPORTANT:** for any new feature or bug, create a git branch first and stay on it. Finish by merging into `backup` (never push without the owner's OK).
- **IMPORTANT:** when the schema changes, update the "Database Schema" section below in the same task.
- UI text is Spanish; backend strings/log messages stay English (JSON `message` shown to users is Spanish).
- Files mostly use CRLF line endings; edit with tools that preserve them.

## Roles

`admins.role`: `super` > `administrador` > `trabajador` (waiter). `is_admin()` = super or administrador (admin-only: settings, reports, product/menu management, meseros, clients, providers); `is_super()` = only `super` (admin accounts). Waiters see a reduced menu and are blocked server-side from clients/providers/menu-categories/reports/admin pages.

## Restaurant flow (stages 1-8)

1. **Foundation** — salon features removed (appointments, loyalty, VIP/birthday/category discounts); `service_category` = menu categories; default client "Consumidor Final" (`default_client_id`).
2. **Mobile shell** — bottom nav, drawer, touch CSS, role-filtered menu.
3. **Floor plan** — areas + free-form tables (`/floor`, admin editor `/floor/editor`), tab = open table visit; table state derived (occupied = open tab, reserved = active reservation in hold window, else free).
4. **Reservations** — `/reservations`; seat = open the tab with the party size.
5. **Ordering + kitchen** — `/kitchen/tab/{id_tab}`; "Enviar a cocina" creates `orders_kitchen` + `order_items`, prints the comanda; any role can void a sent line (whole or part of its units, with reason) or swap it for another product in one step (`Kitchen::replace_item`, atomic; waiters cannot touch already-collected bills, nobody can void inside a `split` bill); **stock leaves here** (atomic `UPDATE … WHERE stock >= qty` inside the order transaction; non-service products only); voiding a line restocks it (unless its bill is already paid).
6. **Floating bills** — `/bills/tab/{id_tab}`; move items (partial quantity splits the line), merge, equal split (2..20) into child bills.
7. **Checkout** — `/sales/register?bill=ID` = the original sale form in "bill mode" (lines locked, tip editable, IVA fixed); server rebuilds everything from the bill; stock is NOT deducted again (already taken when sent to the kitchen); only free-form sales outside bill mode still deduct at save.
8. **Reports & polish** — `/reports`, dashboard tiles, Spanish copy ("Mesero", "Platillo"), optional client birth date.

Money convention: product prices, tip and IVA are stored in **dollars** (the unit of `products.price_sell`); each sale snapshots `dollar_ex_sell` and everything charged in córdobas is `$ × dollar_ex_sell`. Tip = `tip_rate`% of the subtotal (if `tip_enabled`), IVA = `tax_rate_iva`% — both ADDED on top of the subtotal.

## Database Schema (`restaurant`)

> PKs are auto-increment ints. Many tables use soft delete via `active`. `products.commisssion_rate` keeps its historical typo. Product prices/costs are VARCHAR; payment amounts and rates are DECIMAL. Several tables still carry salon-era columns that are no longer read (`clients.is_vip_client`, `cash.close_quantity_cancelled_deposits`, `sale_products.commission_rate`/`id_employee`, `products.commisssion_rate`).

### Floor, tabs, orders, bills (stages 3-7)

| Table | Key columns / rules |
|---|---|
| `table_areas` | `name`, `sort_order`, `active` (soft delete); seeded "Salón" |
| `dining_tables` | `id_area`->areas CASCADE, `label` (unique per area, app-checked), `seats`, `x`,`y`,`w`,`h` DECIMAL(6,3) = **% of a 10:7 canvas**, `shape` square\|round, `active` |
| `tabs` | one table visit: `id_table`, `id_employee` (waiter, SET NULL), `guests`, `status` open\|paid\|void, `opened_at`, `closed_at`, `id_admin`. A table is occupied while it has an open tab (opening is serialized with `SELECT … FOR UPDATE`) |
| `table_reservations` | `id_table`, `name`, `phone`, `party_size`, `start_at`/`end_at`, `notes`, `status` active\|seated\|cancelled\|no_show, `id_tab` (set when seated). Reserved while `start_at - reservation_hold_minutes <= NOW() < end_at`; overlaps per table rejected |
| `orders_kitchen` | one "Enviar a cocina" batch: `id_tab`, `created_at`, `print_status` pending\|printed\|failed\|skipped, `printed_at`, `print_error` |
| `order_items` | `id_order`, `id_tab`, `id_bill`->bills SET NULL, `id_product` (no FK), `product_name`/`unit_price` snapshots, `quantity`, `notes`, `course` 1-5, `prints_kitchen` snapshot, `status` active\|void, void fields. Partial moves between bills clone the line |
| `bills` | floating bill of a tab: `label`, `status` open\|split\|paid\|void, `parent_bill`+`amount_override` (equal-split child = its share), `id_sale`, `stock_deducted` (legacy, unused since stock moves at send time). Every tab starts with "Cuenta 1"; releasing a tab voids its bills; a `split` bill is locked until undone (refused once a share is paid) |

Availability while ordering = `products.stock` (`Kitchen_mdl::available`), since the decrement happens when the order is sent. `Floor::close_tab` refuses a table with active items. Equal-split money: parts apportion subtotal/tip/IVA with floor-to-cents, last part takes the remainder (`Bills_mdl::childTotals`).

### Sales & payments

| Table | Key columns / rules |
|---|---|
| `sales` | `id_employee` (waiter), `client` (default Consumidor Final when none), `date_time`, `dollar_ex_buy`/`dollar_ex_sell` snapshot, `file` (receipt PDF name in `uploads/receipts/`), `tip`, `iva` (dollars, on top of lines), `id_bill` (bill paid; no FK) |
| `sale_products` | `id_sale`, `id_product`, `quantity`, `price` (VARCHAR, unit sell price at sale), `discount` % (admins only; bill checkout forces 0), stock decremented on save unless `is_service=1`. An equal-split share is one line of the placeholder product `barcode='SPLIT-CONSUMO'` (service, hidden from the kitchen menu) |
| `sale_payments` | cash\|pos\|transfer\|cupon rows per sale (split payments). `account` -> `bank_account` (transfer amounts stored in the account's currency), coupons (`cupon_code` UNIQUE, `cupon_discount_rate`), `tax_rate` (POS fee), change given (`change_*`). Only cash may overpay; one cash row and one coupon row max |
| `sale_comments` | append-only chat notes per sale (`Sale_comments` JSON API, utf8mb4) |

`Sales::register` (bill mode): rebuilds lines from `Bills_mdl::checkoutContext`, locks the bill `FOR UPDATE` in the transaction, rejects paid/split/empty/changed bills, validates tip 0..subtotal, then `Bills_mdl::settle()` marks the bill paid (+`id_sale`), marks the parent paid when all shares are paid, voids empty leftover bills and closes the tab when nothing remains. Receipts (thermal/PDF/`/sales/details`) print Mesa, Subtotal, IVA, Propina. The form's single-use token `tk` rotates on success.

### Core entities

| Table | Notes |
|---|---|
| `admins` | `username` UNIQUE, `password` bcrypt (legacy SHA1 rehashed on login), `role` super\|administrador\|trabajador, `active` |
| `employees` | waiters (`name`, `phone`, `active`) — UI calls them "Meseros" |
| `clients` | optional customers: `id_card_client` UNIQUE nullable, names, `birth_client` **nullable**, `active`; `default_client_id` setting points at "Consumidor Final" |
| `products` | `barcode` UNIQUE, `name`, `price_cost`/`price_sell` VARCHAR, `stock`, `stock_alert_threshold` (low-stock bell), `is_service` (1 = "platillo": made to order, no stock), `id_service_category`, `prints_kitchen` (appears on the comanda), `id_provider`, `active` |
| `product_packs` | purchase presentations of a product: `id_product`->products CASCADE, `name` (e.g. "Six-pack"), `units` (>=2), optional `barcode` (UNIQUE among packs; app keeps it disjoint from `products.barcode` via `Products_mdl::barcodeTaken`), optional `price_cost` (pack cost). Receiving a pack in `Invoices::register` stores `qty*units` base units with cost per unit; stock stays one integer per product. Not for services |
| `service_category` | menu categories (`name_category` UNIQUE), admin-editable via `/service_categories` |
| `providers`, `invoices`, `invoice_products`, `returns`, `returns_products` | purchasing (`UNIQUE(invoice_no,id_provider)`); `orders`/`orders_products` = supplier orders, feature disabled |
| `internal_uses`, `internal_use_products` | stock withdrawals for internal use (waste/staff), decrement stock |

### Cash & finance

`cash` (one session per calendar day via generated STORED `open_date` UNIQUE; closing snapshots), `outcomes` (expenses), `bank_account` (transfer targets, per-account buy/sell rates, soft delete), `dollar_exchange` (single row; **sell_price** values every córdoba conversion at sale time), `goals` (monthly targets, admin).

### Settings (`settings` key/value; new keys must be seeded by INSERT, `Settings_mdl::save` only UPDATEs)

`company_name`, receipt keys (`receipt_ruc/address/phone/tagline/footer_notice/footer_thanks`), logo keys (`logo_header/login/receipt/purchase_order`, files in `uploads/logos/`), `default_client_id`, `tip_enabled`, `tip_rate`, `tax_rate_iva`, `kitchen_printer_name`, `reservation_hold_minutes` (30), `reservation_default_minutes` (90). Editable in `/settings` (admin): company/receipt/logos, tip, IVA, kitchen printer.

### Framework

`ci_sessions` — CodeIgniter DB sessions.

## Reports (stage 8)

`/reports?from=&to=` (admin): summary (ventas, propinas, IVA, total, ticket promedio, voided items), sales/tips by waiter, by table (no table = counter sale), by shift (Mañana <12h, Tarde <18h, Noche), by category, top 10 products, kitchen-ticket history. Figures are per-sale `$` and `C$ = $ × that sale's rate`. Dashboard (`/panel`) adds live tiles: occupied tables, open consumption, today's pending reservations.

## Known environment gaps

- Printers `CAJA` / `COCINA` may not exist (print failures are logged; the receipt PDF and HTML ticket always exist).
- SMTP login fails on this machine (`sendOpenCash` is best-effort and only logs).
