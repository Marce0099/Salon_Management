# Restaurant POS — Floor, Orders, Bills & Billing System

Internal point-of-sale for a restaurant in Nicaragua (originally a salon/cosmetics billing app; converted in 8 stages, see "Restaurant flow"). Waiters use it on phones/tablets: free-form floor plan with table states, reservations, ordering with a kitchen ticket, floating/split bills, and checkout through the original multi-currency payment engine. Also: purchases, stock, cash drawer, waiters (`employees`), clients, reports.

## Tech Stack

- **Backend:** PHP 7/8 · CodeIgniter **3.0** (`system/`) + HMVC via the `MX` module extension. (Older notes say "CI2": the query builder `group_start()` etc. is CI3.)
- **Database:** MariaDB/MySQL (XAMPP), DB name `restaurant` (credentials in `application/config/database.php`, `.env` `DB_NAME=restaurant`). Charset of the app connection is 3-byte `utf8`; the mysql CLI needs `--default-character-set=utf8mb4` for accents.
- **Frontend:** Bootstrap 3 · Gentelella admin theme · jQuery · Chart.js · PNotify · bootbox. Mobile layer: `assets/css/mobile.css` (touch sizes, bottom nav, slide-in drawer) and `assets/css/floor.css` (floor canvas).
- **Printing:** ESC/POS thermal printers through `mike42/escpos-php` (`print/autoload.php`): receipts → Windows printer `CAJA`; kitchen tickets → printer named in setting `kitchen_printer_name` (default `COCINA`). Neither printer exists on every machine: failures are logged/recorded and never fail a sale or order.
- **PDF:** ht`unit` g|ml|u|mg|gota|pizca|rebanada|diente|hoja (single base unit; migration 027 — deliberately no larger units like kg/l/lb, model those as packs)2pdf (`includes/html2pdf/`) → `uploads/receipts/` (receipt copy per sale).

## Key Directories

| Path | Purpose |
|---|---|
| `application/controllers/` | One controller per domain (Floor, Reservations, Kitchen, Bills, Sales, Reports, Products, Cash, …) |
| `application/models/` | Thin DB layer `<Domain>_mdl.php` (`Floor_mdl`, `Kitchen_mdl`, `Bills_mdl`, `Reservations_mdl`, `Reports_mdl`, …) |
| `application/views/` | PHP templates; forms prefixed `form_` |
| `application/core/MY_Controller.php` | `Admin_Controller` base (session login, `renderPanel`, `is_admin()`, `is_super()`) |
| `application/libraries/` | `Easysearch.php`, `Imagetool.php`, `MY_PHPMailer.php` |
| `migrations/` | `restaurant_00N_*.sql`, applied by hand to DB `restaurant` (pre-stage dumps are git-excluded) |
| `.claude/docs/audit_events.md` | **Audit trail contract** (table, write rules, action catalog, read API v1) shared with the future Rust/Tauri backend; JSON Schema in `docs/audit_event.schema.json` |
| `.claude/docs/architectural_patterns.md` | Controller/Model conventions, Easysearch, AJAX shape, session flash, form token, thermal printing |

## Running the App

No build step. Start Apache + MySQL from XAMPP, open `http://localhost/`. Default route `login`. No automated tests: verify in the browser (resize_window does not change the viewport in Chrome automation; use a 390px iframe to emulate a phone). PHP lint: `C:\xampp\php\php.exe -l file`.

## Working agreements

- **IMPORTANT:** for any new feature or bug, create a git branch first and stay on it. Finish by merging into `backup` (never push without the owner's OK).
- **IMPORTANT:** when the schema changes, update the "Database Schema" section below in the same task.
- UI text is Spanish; backend strings/log messages stay English (JSON `message` shown to users is Spanish).
- Files mostly use CRLF line endings; edit with tools that preserve them.

## UI/CSS gotchas

- Gentelella floats EVERY `.col-*` (even with no width at that breakpoint). `mobile.css` re-stacks columns that only declare `col-sm-*`/`col-md-*` on phones; still add `col-xs-12` where a field must be full width.
- `mobile.css` turns every `.right_col table.table` into a horizontally scrolling block (so `table-layout:fixed` is ignored). Tables that need real layout are opted out by id at the top of that rule (`#invoice-table`, `#tbl`, `#bill-extras-table`, `.bill-card`); do NOT use `:not(#id)` for that (it raises specificity and defeats the opt-outs).
- Money pairs: `fmt_cor_pair`/`fmt_money_pair` and `FX.pair` use a non-breaking space after `C$`; the dollar part (`<small class="money-usd">`, nowrap, muted) is only emitted under `/cash/*` and `/sales/register`. C$ amounts in `<input>` use an `input-group` with a `C$` addon (not `has-feedback-left`) and `fmt_plain()` for the value.
- Never write `*/` inside a CSS comment (a stray `col-sm-*/col-md-*` silently dropped the rules after it).

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

**CÓRDOBA-ONLY (migrations 023-026).** Money is stored ONLY in C$ with 2 decimals (rounded with `round(x, 2)` on every write); dollars are never stored: they are derived at read time (`cor_to_usd($cor, $rate = live)`, 2 decimals; a sale's own `dollar_ex_sell` snapshot is the rate for that sale) and are VISIBLE ONLY under `/cash/*` and `/sales/register` (`show_usd()` in `money_helper.php`; `fmt_cor_pair`/`fmt_money_pair`/`fmt_cor_usd` append the `money-usd` span only there; `window.FX.showUsd` does the same for JS `FX.pair`). Source of truth: `products.price_cost_cor` (DECIMAL 14,4) / `price_sell_cor` (12,2), `product_packs.price_cost_cor`, `invoice_products.price_cost_cor/price_sell_cor`, `returns_products.price_cost_cor/price_sell_cor`, `internal_use_products.price_cost_cor`, `product_cost_history.old/new_cost_cor, old/new_sell_cor`, `ingredient_purchases.total_cost_cor`, `ingredient_purchase_lines.line_cost_cor`, and (migration 026, converted in place at each sale's own rate) `sale_products.price`, `sales.tip`, `sales.iva`, `order_items.unit_price`, `bills.amount_override` (all DECIMAL 12,2). **Only exception to 2 decimals: per-unit costs** (`*.price_cost_cor` per base unit and `ingredients.cost_unit` DECIMAL 12,4) because a pack/ingredient cost split into units is fractional; `fmt_cor_unit()` shows up to 4 decimals. The legacy dollar columns were DROPPED in migration 026 (`products.price_cost/price_sell`, `invoice_products`/`returns_products` dollar prices, `product_cost_history` dollar columns, `ingredient_purchases.total_cost/dollar_ex_sell`, `ingredient_purchase_lines.line_cost`, `invoices.dollar_ex_sell`, `product_packs.price_cost`, `internal_use_products.price_cost`) and `Dollar_exchange_mdl::save` only updates the rate (nothing is re-derived), so changing the rate never alters a stored price. Helpers: `application/helpers/money_helper.php` (`fx_rate`, `usd_to_cor`, `cor_to_usd`, `show_usd`, `fmt_cor`, `fmt_usd`, `fmt_cor_pair`, `fmt_cor_unit`, `fmt_plain`) and `assets/js/money.js` (`FX.cor/pair/toUsd`). `Bills_mdl::q()`/`floorShare()` snap to C$ cents (equal-split shares: floor, last part takes the remainder). Tip is typed in C$ (`tip_amount`). Real dollar tender is still recorded in `sale_payments.dolar` / `cash.*` (actual currency received) and converted with the sale's `dollar_ex_buy/sell`. `goals.amount` is C$.

Money convention: prices, tip and IVA are C$ (see CÓRDOBA-ONLY above). Tip = `tip_rate`% of the subtotal (if `tip_enabled`), IVA = `tax_rate_iva`% — both ADDED on top of the subtotal, each rounded to the cent.

## Database Schema (`restaurant`)

> PKs are auto-increment ints. Many tables use soft delete via `active`. `products.commisssion_rate` keeps its historical typo. Money is DECIMAL in C$ (2 decimals; unit costs 4); payment amounts and rates are DECIMAL. Several tables still carry salon-era columns that are no longer read (`clients.is_vip_client`, `cash.close_quantity_cancelled_deposits`, `sale_products.commission_rate`/`id_employee`, `products.commisssion_rate`).

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

**Cost audit (migration 020).** `product_cost_history` (`id_product`->products CASCADE, `source` purchase|manual|import, `id_invoice`->invoices SET NULL, `id_invoice_product`, `id_provider` snapshot, `old_cost_cor`/`new_cost_cor` DECIMAL(14,4) per base unit in C$ (migrations 024/026 dropped the dollar columns), `quantity` base units, `old_sell_cor`/`new_sell_cor` (12,2), `id_admin`, `created_at`). One row per purchase line written inside `Invoices_mdl::receive` (old cost = the locked product row, so every purchase is logged even at the same cost; lines without cost log nothing), plus manual cost edits (`Products_mdl::edit`/`save`) and Excel import of new products (`Cost_history_mdl::log`). Migration 020 backfills past invoices. Screen `/cost_history` (admin; menu Proveedores > Historial de costos): summary per product or, with a product, stats (current, weighted average by units, highest, lowest) and the per-invoice list with previous vs purchased cost; filters product/provider/source/dates/only changes.

Availability while ordering = `products.stock` (`Kitchen_mdl::available`), since the decrement happens when the order is sent. `Floor::close_tab` refuses a table with active items. Equal-split money: parts apportion subtotal/tip/IVA with floor-to-cents, last part takes the remainder (`Bills_mdl::childTotals`).

### Audit trail (migration 022)

`audit_log` (append-only, BIGINT `id`, `occurred_at` DATETIME(3) **UTC**, `request_id`, `source` php|rust|tauri, actor snapshot `id_admin`/`admin_name`/`role`, `ip`, `terminal_id`, `user_agent`, `request`, `action` dotted verb, `result` ok|denied|error, `entity_type`/`entity_id`, context `id_tab`/`id_bill`/`id_sale`, Spanish `summary`, `before_json`/`after_json`, `schema_version`). Every state-changing action writes ONE row through the `Audit` library (`$this->audit->log/change/denied`, autoloaded) INSIDE the action's transaction; contract + action catalog in `.claude/docs/audit_events.md`. Viewer `/audit` (admin; `login.*`/`admin.*` super only; menu "Auditoría"), timelines `/audit/timeline/{tab|bill|sale}/{id}`, CSV `/audit/export`, JSON `/audit/api/v1/events|entity/...` (controller `Audit_trail`, routed from `/audit/...` because the class name `Audit` is the library). `sales.id_admin` = cashier who collected the sale (`id_employee` stays the waiter). New write paths MUST log an event. CI 3.0 `(:any)` routes do not match slashes: use `(.+)`.

### Ingredients & recipes (Block B)

| Table | Key columns / rules |
|---|---|
| `ingredients` | `name` UNIQUE, `unit` g|ml|u|mg|gota|pizca|rebanada|diente|hoja (single base unit; migration 027 — deliberately no larger units like kg/l/lb, model those as packs)\|ml\|u (single base unit), `stock` DECIMAL(12,3), `alert_threshold` (low-stock banner at `stock <= threshold`, 0 = off), `cost_unit` C$/base unit, 4 decimals (refreshed when receiving with a total cost), optional pack (`pack_name`, `pack_size` base units), `active` soft delete (refused while an active dish recipe uses it) |
| `recipe_items` | `id_product`->products CASCADE, `id_ingredient`->ingredients CASCADE, `quantity` per portion; UNIQUE(product, ingredient). Edited at `/ingredients/recipe/{id_product}` (admin; link in Platillos menu) with cost/portion and margin |
| `ingredient_movements` | log: `type` receive\|order\|void\|waste\|adjustment\|prepare_in\|prepare_out, signed `quantity`, `reason`, `id_admin`, `id_order_item` |
| `ingredient_components` | **Sub-recipes (Block C, migration 012).** An ingredient with `ingredients.batch_yield` NOT NULL is *prepared* (sauce/dough): PK(`id_ingredient` prepared, `id_component` raw), `quantity` of the component per batch. Components must be raw (not prepared, not itself, active): one level, no cycles. Dishes keep deducting the prepared ingredient's own stock; `Ingredients_mdl::prepare()` ("Preparar lote", `/ingredients/prepare`) converts components into it in one transaction (ascending-id row locks, whole batch checked, logs `prepare_out`/`prepare_in`). Cost of a prepared ingredient is the live roll-up `Ingredients_mdl::costSql()` (NULL if a component has no cost). `receive` is refused for prepared ingredients; the usage report keeps batches out of "consumo" (separate columns) to avoid double-counting cost |

**Bulk loading (migration 015).** `ingredient_packs` (`id_ingredient` CASCADE, `name` UNIQUE per ingredient, `size` in base units) = SEVERAL presentations per ingredient (sack, bag...), edited in the ingredient form (`packs` JSON); it replaces the legacy `ingredients.pack_name`/`pack_size` (kept, no longer read). `/ingredients/purchase` (admin) = one centralized receipt: many ingredients, quantity by presentation, cost paid per line, inline "crear ingrediente"; the same ingredient may appear in several lines, one per presentation (base unit included; a repeated presentation is rejected in the UI and in `purchase_save`); `Ingredients_mdl::receivePurchase` does it in ONE transaction (rows locked in ascending id, retried on deadlock), adds stock + a `receive` movement per line (reason "Compra #N") and sets `cost_unit` to the WEIGHTED AVERAGE of existing stock and the purchase. The cost paid per line is typed and stored in córdobas (`line_cost_cor`/`total_cost_cor`, 2 decimals; migration 026 dropped the dollar columns and `dollar_ex_sell`) and `cost_unit` (C$ per base unit) is the weighted average. Saved in `ingredient_purchases` (`id_provider`->providers ON DELETE SET NULL, optional, picked in the shared provider modal `views/_provider_picker.php` which can also create a provider by name via `Ingredients::quick_provider` (migration 017 replaced the old free-text `store` column); date, totals, admin) + `ingredient_purchase_lines` (pack name/qty and base qty as snapshots); history at `/ingredients/purchases`. `/ingredients/count` = full-list physical count; each ingredient takes SEVERAL lines (quantity x presentation x "% lleno", e.g. 4 sacks @100% + 1 @50% + 1 @75% + loose base units), `count_save` receives `parts:[{id_pack,qty,pct}]` (legacy single `id_pack,qty` still accepted, max 30 parts, pct 0..100 only for packs) and sums them in base units; `countMany` writes only differences, one `adjustment` movement each ("Conteo físico dd/mm/yyyy"). The old per-row "Recibir" modal/endpoint was removed; waste/count modals per row remain.

**Modifiers (Block D, migration 013).** A global catalog (`modifiers`: `name` UNIQUE, `kitchen_text`, `exclusive_group` (migration 021: VARCHAR(40) NULL; modifiers sharing a group, case-insensitive, are mutually exclusive on one line, e.g. "Término" for doneness: the order screen switches the others off when one is picked, `Modifiers_mdl::resolve` rejects two of a group), `active`) attached per dish (`product_modifiers`, edited on the dish's `/ingredients/recipe/{id}` page; catalog CRUD at `/modifiers`, admin). `modifier_ingredients` lists what a modifier REMOVES: `quantity` NULL = the dish's whole amount of that ingredient, a value = that amount, always clamped so the sum over all modifiers of a line never exceeds the recipe amount (`Modifiers_mdl::resolve`). Modifiers never change the price and never add ingredients. At send time each order line snapshots them in `order_item_modifiers` (one row per modifier x removed ingredient, or one text-only row; `qty_removed` is PER PORTION). `Kitchen_mdl::takeStock` deducts recipe minus removal per line, `restoreIngredients` (void/replace) gives back exactly what the line took (see **Exact restock** below), partial void (`doVoid`) and partial move (`Bills_mdl::doMove`) clone the per-line snapshots (`Kitchen_mdl::cloneItemData`). The text prints on the comanda (thermal + HTML), the order screen history, the bill view and the receipts (`sale_products.mods_text`, e.g. "Sin queso x2, Término medio x1", built per product by `Bills_mdl::checkoutLines`; HTML/PDF/thermal receipts and `/sales/details`). **Availability** nets modifiers: `Kitchen_mdl::menu()` sends each dish's `rec` (recipe + ingredient stock), `base` (product stock) and each modifier's `rem`; the order screen (`fitsLines`) lets a dish whose full recipe is short stay orderable ("Solo con modificador") when a modifier covers the missing ingredient, flags/blocks lines that do not fit, and `Kitchen::send` checks products that have modified lines on the net aggregate need (message "Ingredientes insuficientes para X (falta Y)"); products without modifiers keep the old portions check. "Cambiar plato" keeps the old line's modifiers that are active and attached to the new dish.

**Exact restock (migration 014).** `order_item_ingredients` (PK `id_order_item`, `id_ingredient`; `qty_per_unit`) is the snapshot of what ONE unit of a line really took (recipe minus modifier removals) written by `insertOrder`; `order_items.stock_snapshot = 1` marks such lines. Voids/swaps return exactly `qty_per_unit x units` whatever the recipe says later (`voidItem`/`replaceItem` also lock the snapshot's ingredient ids). Lines sent before migration 014 (`stock_snapshot = 0`) keep the old rule: current recipe minus the modifier-removal snapshot. `sale_products.mods_text` VARCHAR(255) NULL holds the receipt text of a bill line.

`Kitchen_mdl::insertOrder` takes ingredients per line in the same transaction (atomic `UPDATE … WHERE stock >= need`; failure = -2 + `short_ingredient`, order refused); voiding/replacing a line returns them (by the CURRENT recipe) unless its bill is paid. Menu `available` for a recipe dish = portions covered by the scarcest ingredient (0 = "Agotado"); `Kitchen::send` also pre-checks. **Concurrency:** `Kitchen_mdl` locks product and ingredient rows (`SELECT … FOR UPDATE`) in one global order (products by id, then ingredients by id) for sends, voids and swaps, checks the WHOLE order against the locked stock, then decrements; the first transaction to get the locks wins and a competing one is refused untouched (`-2`, rolled back). `runTx()` retries deadlock/lock-timeout exceptions up to 3 times (the DB driver is PDO and throws), then answers `-3` ("mucha actividad"). Stock rejections return `availability` so the order screen updates itself. Free-form sales (`Sales::register`) and internal use take product stock through `Products_mdl::takeStock()` (same ascending-id row locks, whole-document check against locked stock, then decrement), so they can neither oversell nor deadlock against kitchen orders; `Sales_mdl::saveSaleProduct` no longer decrements.  Admin UI `/ingredients` (receive by pack, waste, physical count, history).

### Sales & payments

| Table | Key columns / rules |
|---|---|
| `sales` | `id_employee` (waiter), `client` (default Consumidor Final when none), `date_time`, `dollar_ex_buy`/`dollar_ex_sell` snapshot, `file` (receipt PDF name in `uploads/receipts/`), `tip`, `iva` (C$, on top of lines), `id_bill` (bill paid; no FK) |
| `sale_products` | `id_sale`, `id_product`, `quantity`, `price` (DECIMAL 12,2, unit sell price in C$ at sale), `discount` % (admins only; bill checkout forces 0), stock decremented on save unless `is_service=1`. An equal-split share is one line of the placeholder product `barcode='SPLIT-CONSUMO'` (service, hidden from the kitchen menu) |
| `sale_payments` | cash\|pos\|transfer\|cupon rows per sale (split payments). `account` -> `bank_account` (transfer amounts stored in the account's currency), coupons (`cupon_code` UNIQUE, `cupon_discount_rate`), `tax_rate` (POS fee), change given (`change_*`). Only cash may overpay; one cash row and one coupon row max |
| `sale_comments` | append-only chat notes per sale (`Sale_comments` JSON API, utf8mb4) |

`Sales::register` (bill mode): rebuilds lines from `Bills_mdl::checkoutContext`, locks the bill `FOR UPDATE` in the transaction, rejects paid/split/empty/changed bills, validates tip 0..subtotal, then `Bills_mdl::settle()` marks the bill paid (+`id_sale`), marks the parent paid when all shares are paid, voids empty leftover bills and closes the tab when nothing remains. Receipts (thermal/PDF/`/sales/details`) print Mesa, Subtotal, IVA, Propina. The form's single-use token `tk` rotates on success.

### Core entities

| Table | Notes |
|---|---|
| `admins` | `username` UNIQUE, `password` bcrypt (legacy SHA1 rehashed on login), `role` super\|administrador\|trabajador, `active` |
| `employees` | waiters (`name`, `phone`, `active`) — UI calls them "Meseros" |
| `clients` | optional customers: `id_card_client` UNIQUE nullable, names, `birth_client` **nullable**, `active`; `default_client_id` setting points at "Consumidor Final" |
| `products` | `barcode` UNIQUE, `name`, `price_cost_cor` (14,4, per unit)/`price_sell_cor` (12,2), `stock`, `stock_alert_threshold` (low-stock bell), `is_service` (1 = "platillo": made to order, no stock), `id_service_category`, `prints_kitchen` (appears on the comanda), `id_provider`, `active` |
| `product_packs` | purchase presentations of a product: `id_product`->products CASCADE, `name` (e.g. "Six-pack"), `units` (>=2), optional `barcode` (UNIQUE among packs; app keeps it disjoint from `products.barcode` via `Products_mdl::barcodeTaken`), optional `price_cost` (pack cost). `/invoices/quick` takes a scanner: code + Enter calls `Products::scan` (`Products_mdl::scanCode`) which resolves a product barcode (unit line) or a pack barcode (that pack preselected); scanning again adds +1 to the same product+pack line. Receiving a pack in `Invoices::save` stores `qty*units` base units with cost per unit; stock stays one integer per product. Not for services. **Purchase receiving has ONE screen, `/invoices/quick` ("Registrar compra", phone + desktop, admin only; `/invoices/register` GET just redirects to it); it saves through `Invoices::save` -> `Invoices_mdl::receive` (one transaction, named lock per invoice number, products locked in ascending id, deadlock retry, future dates refused). Returns (`/returns/register`) work from the invoice itself: the provider select is only a filter (all / "Sin proveedor" / one provider) and `returns.id_provider` is taken from the invoice (NULL allowed); `invoices/details/{id}` has a "Devolver" shortcut. **Return lines are INVOICE LINES** (migration 019: `returns_products.id_invoice_product` + snapshots `pack_name`/`pack_qty`/`pack_units`; NULL = pre-019 return): the form lists each `invoice_products` row (box line / loose line) with its pending units (`Returns_mdl::invoiceLines`), you return it as `unit` or `pack` (`Returns_mdl::saveProductsReturn(id_lines, modes, qtys)`; pack qty x `pack_units`), limits are checked per product AND per line in base units, stock decrements in units, cost comes from that line. Units taken out of a box are just units of the box line (no "opened box" state); pre-019 returns reduce the product's total and are netted in `invoiceLines`** |
| `service_category` | menu categories (`name_category` UNIQUE), admin-editable via `/service_categories` |
| `providers`, `invoices`, `invoice_products`, `returns`, `returns_products` | purchasing (`UNIQUE(invoice_no,id_provider)`); `invoice_products.quantity` is always base units, and migration 018 adds nullable snapshots `id_pack`/`pack_name`/`pack_qty`/`pack_units` (set when the line was received by presentation, NULL = loose units or pre-018 rows; shown on `invoices/details`); `orders`/`orders_products` = supplier orders, feature disabled |
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
