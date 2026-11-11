# QA audit — Blocks C (sub-recipes) and D (modifiers)

All rows executed by hand in the browser (Chrome automation) as `admin`, localhost, DB `restaurant`, test data prefixed `ZZTEST` (removed afterwards).
`PASS` = observed result matched the expected one; numbers were verified against the database, not only the UI.
Concurrency rows used a temporary, never-committed harness controller (the form token is single-use, so parallel POSTs through the normal endpoint only reach the DB locks through the kitchen `send`, which reuses its token).

## Block C — Sub-recipes (2026-11-10)

### Setup / regression
- [x] PASS Migration `restaurant_012` applied twice (idempotent); `batch_yield` column, `ingredient_components` table, movement enum extended.
- [x] PASS `/ingredients`, `/ingredients/register`, `/ingredients/usage`, `/panel`, `/services`, `/kitchen/tab/N` render 200 with no PHP errors/notices; `application/logs` shows only the known missing-printer warnings.
- [x] PASS PHP lint on every touched file.

### Define a prepared ingredient
- [x] PASS Created "ZZTEST Salsa" (ml, yield 2000, tomate 1500 g + aceite 200 ml + sal 30 g) through the real form + Guardar button; list shows "Preparado" badge, "Preparar lote" instead of "Recibir". Form shows live batch cost ($8.00) and unit cost ($0.004) with the missing-cost warning.
- [x] PASS Validation (14 cases, all refused with a Spanish message and **no row created**): yield 0 / blank / negative / text / 100,000,000; no components; invalid JSON; qty 0 / negative / text; duplicate component; nonexistent id; id 0; 41 components.
- [x] PASS Self as component refused; prepared ingredient as component refused ("solo ingredientes simples").
- [x] PASS Raw ingredient that is a component of a prepared one cannot become prepared. Raw -> prepared with empty list refused.
- [x] PASS prepared -> raw -> prepared again: stock kept (1800 -> receive allowed again while raw), components removed/re-added, `prepare` on the now-raw ingredient refused.
- [x] PASS Editing components/yield while stock exists leaves stock untouched (future batches only).
- [x] PASS Name `<script>` is neutralised by the global XSS filter ("[removed]x[removed]"); emoji name refused.

### Prepare batch
- [x] PASS 1 batch: Tomate -1500, Aceite -200, Sal -30, Salsa +2000; movements `prepare_out`/`prepare_in` with reason and user in history.
- [x] PASS Decimal batches `0.5` and `0,5` work (-750/-100/-15, +1000). Invalid: 0, -1, abc, empty, 10000, 1.234, 1e2, 0.001 refused, nothing changed.
- [x] PASS Exactly-enough stock (Aceite 200, batch needs 200) succeeds and leaves 0; Aceite 199 refused ("No alcanza ZZTEST Aceite (faltan 1 ml)") and **no component moved** (atomic).
- [x] PASS Inactive component at prepare time -> refused naming it. Missing token / bad token -> "Sesión inválida". Raw id / unknown id -> "Ingrediente inválido".
- [x] PASS Concurrency: 8 simultaneous prepares for exactly one batch of Aceite -> 1 winner, 7 refused, stock consistent (no negatives, no deadlock).
- [x] PASS Concurrency mix: 6 sends of Pasta + 2 prepares of Salsa in parallel -> 4 sends + 1 prepare won, 2 sends refused before the batch landed, 1 prepare refused (Aceite); final Salsa = 20 + 2000 - 4x50 = 1820 exactly.

### Use in dishes / orders
- [x] PASS Dish with 50 ml Salsa: menu showed 200 disp. (10000/50); dish with 100 ml Salsa + 10 g Sal showed 35 (limited by Sal). Salsa 40 -> both "Agotado" and disabled; send refused "está agotado (faltan ingredientes)" with `availability` payload.
- [x] PASS Send 2 Pasta + 1 Lasagna through the real "Enviar a cocina" button: Salsa -200, Sal -10, raw tomate/aceite untouched; `order` movements tied to the item ids.
- [x] PASS Partial void (1 of 2) restocks +50; swap Pasta -> Lasagna restocks +50 and takes 100 + 10 g Sal; whole void restocks +100/+10; void validation (qty too big, empty reason) refused.
- [x] PASS Salsa 120 ml, ask 3 Pastas -> "Solo quedan 2 porciones". Race of 6 single-portion sends with 2 portions -> exactly 2 winners.
- [x] PASS Void on a bill marked `paid` does not restock (Salsa stayed 1000). (Paid state set by SQL; the rule itself is pre-existing.)

### Cost / reports / alerts
- [x] PASS Recipe page: with Sal cost unknown the cost warning shows; after giving Sal a cost the Salsa cost becomes 0.00403 $/ml ((6+2+0.06)/2000) live.
- [x] PASS Usage report: Salsa consumed 450 ml with cost $1.81 (= 450 x 0.00403), raw ingredients show "Usado en lotes" / "Producido" separately; total $1.83, no double counting. (Fixed during the audit: the report used the stored cost and showed "—" for prepared ingredients.)
- [x] PASS Low-stock bell/tile lists Salsa (250 ml <= 300) with the Ingrediente tag.

### Guards
- [x] PASS Deactivating Tomate (component) refused naming Salsa; deactivating Salsa refused ("lo usan 2 receta(s)"); receive on prepared refused; waste (-30) and physical count (+10) on prepared work.
- [x] PASS Waiter (`trabajador`) gets "No se encontro resultado" for `/ingredients`, `/ingredients/usage`, `/register`, `/recipe/N` and the `prepare` POST, but can open `/kitchen/tab/N`.
- [x] PASS 390 px iframe: prepare modal readable; ingredient form component table usable (fixed during the audit: modal yield showed "2 ml" because of a thousands separator in `data-yield`; component dropdown widened and table made scrollable).

### Known observations (not defects of this block)
- Under parallel `fetch` bursts the app occasionally answers with the login page / a 500 once every ~5 minutes: CodeIgniter's DB session regenerates its id (`sess_time_to_update` = 300 s) while requests are in flight. Only affects automated parallel calls.
- "Stock inicial" is logged as an `adjustment`, so it appears in the usage report's "Diferencia de conteo" (pre-existing from Block B).

## Block D — Modifiers (2026-11-10)

### Setup / regression
- [x] PASS Migration `restaurant_013` applied twice (idempotent); 4 new tables.
- [x] PASS `/modifiers`, `/modifiers/register`, `/ingredients`, `/panel`, `/services`, `/kitchen/tab/N`, `/ingredients/usage` render 200 without PHP errors; `application/logs` has no new errors beyond the known missing-printer warnings.
- [x] PASS Orders WITHOUT modifiers behave as before: plain send deducts the full recipe (item sent with no `modifiers` key at all, e.g. an old client, deducts 30/150/20 exactly); void, partial void, swap, move, merge, equal split + undo, bills view all work with mixed modified/plain lines. (Full checkout in bill mode was NOT executed: it needs today's cash drawer open and the code path is unchanged — bill totals are `SUM(quantity*unit_price)`, verified equal before/after split/undo.)

### Catalog
- [x] PASS Created "Sin queso" (queso, whole amount), "Poco arroz" (arroz 50 g), "Término medio" (text only, accent), "Sin queso ni tomate" (two ingredients), "Quitar 500 queso" (500 > recipe), "Coma" (qty `1,5` accepted). List shows the removal summary ("Queso (todo)", "Arroz (50 g)", "Solo texto").
- [x] PASS Validation refused with a Spanish message and nothing saved: duplicate name; blank / 61-char / `<b>` / emoji name; HTML / 61-char kitchen text; qty 0 / negative / text / 100,000,000; nonexistent ingredient; invalid JSON; 21 rows; missing token.
- [x] **FIXED during audit**: the same ingredient listed twice was accepted when the first row had a blank (remove-all) quantity (`isset()` is false for NULL) — now `array_key_exists`; re-tested both orders ("está repetido").
- [x] PASS Deactivate hides it from the active list and from the order screen (chips) and sending its id is refused; Reactivar brings it back; editing a nonexistent id redirects to the list.
- [x] PASS Attach/detach from the recipe page (real checkboxes + button): 4 modifiers on the burger, 3 on the rice dish, text-only on a dish with no recipe; detach all works. Tampering: nonexistent id, text id, bad JSON, bad product, inactive modifier -> refused, attachments unchanged.

### Ordering
- [x] PASS Order screen: chips appear only on dishes that have modifiers (verified as admin and as waiter, "Agua" has none); price unchanged when toggled; same dish with different sets does NOT merge, identical sets merge into one line (x3); 390 px iframe shows large touch chips.
- [x] PASS Real "Enviar a cocina" click, 3 burgers [Sin queso, Término medio]: queso untouched, carne -450, tomate -60, price 8.00, snapshot rows `queso 30` + text row, movements log only carne/tomate.
- [x] PASS Clamping: [Sin queso + Sin queso ni tomate + Quitar 500] x2 -> queso removal 30 once (never over-removed), tomate 20, text-only row for the third, carne -300; [Quitar 500 queso] alone clamps to 30; "Poco arroz" x3 -> arroz -450 (150 each) and queso -30; "Sin queso" on the rice dish -> arroz -400, no queso; text-only on a dish without recipe moves no stock.
- [x] PASS Tampering refused with nothing deducted: modifier of another dish, nonexistent id, duplicate ids, non-numeric, non-array, 11 ids, `1.5`, `-1`, inactive modifier.
- [x] PASS Comanda: HTML ticket lists `** TEXT` lines under the dish (also on voided lines, struck through); the thermal path ran without PHP errors (printer missing, reported as "no se pudo imprimir"); order-screen history and bill view show the texts. Thermal output itself could not be inspected (no printer).
- [x] PASS Waiter (`trabajador`) can pick modifiers and send (rice -150 for "Poco arroz"); gets "No se encontro resultado" for `/modifiers*`, `modifiers/save`, `modifiers/attach` and `ingredients/save_recipe`; nothing changed.

### Void / change / bills
- [x] PASS Whole void of the x3 line restocks carne +450 / tomate +60 and no queso (recipe - removal); partial void 1 of 3 restocks arroz +150 and queso +10, remaining line keeps its snapshot and the voided clone has its own copy.
- [x] PASS Swap "Arroz plato x2 [Sin queso]" -> Burger x1: arroz +400, queso +0 (it had been removed), new line has no modifiers and takes the full burger recipe.
- [x] PASS Recipe edited between send and void (arroz 200 -> 300): restock = (300 - 50) x 2 = 500 although 300 was taken — the pre-existing "restock by the CURRENT recipe" behavior; the modifier snapshot only protects the removal part. Documented, not changed.
- [x] PASS Void on a bill marked `paid` (set by SQL): no restock.
- [x] PASS Bills: partial move of a modified line clones the snapshot rows to the new bill; merge carries lines by bill id; voiding the moved clone restocks only what it took (carne +150, no queso/tomate); equal split into 2 and undo keep the totals; modifier text visible on the bill view.

### Concurrency
- [x] PASS 6 simultaneous sends (3 plain burgers, 3 "sin queso") with exactly one portion of queso: exactly 1 plain won, queso ended at 0 (never negative), carne/tomate consistent with the winner, no deadlock/500. (The "sin queso" requests were refused as "agotado" because availability ignores modifiers — known limitation below.)
- [x] PASS (Block C) prepare-vs-send races on shared ingredients unchanged by this block.

### Reports
- [x] PASS Usage report "consumo teórico" equals the net of `order`/`void` movements per ingredient (carne 750, queso 100, tomate 80, arroz -50 from the drift test) — removals are reflected.

### Known limitations / observations
- The four limitations originally listed here (Agotado ignoring modifiers, swap dropping modifiers, receipts without modifiers, restock by the current recipe) were fixed afterwards: see "Limitation fixes" below.
- Parallel `fetch` bursts occasionally hit the CodeIgniter session regeneration (see Block C note).

## Limitation fixes (branch `fix/modifier-limitations`, migration 014, 2026-11-10)

### 1. Agotado nets the modifiers
- [x] PASS Queso at 0: Burger, Burger doble and Arroz plato tiles show "Solo con modificador" and stay enabled; "Sin receta" unaffected. A dish short of an ingredient that NO modifier covers (queso 0 AND carne 0, modifier only removes queso) shows "Agotado", the tile is disabled and tapping it adds nothing; after restocking the carne the same tile switches to "Solo con modificador".
- [x] PASS Order screen with queso 0: tapping Burger adds a flagged line (one warning per product) and disables "Enviar"; ticking "Sin queso" clears the warning and enables send; "+" to 2 still fits; turning "Sin queso" OFF is refused; adding a plain Burger flags the product again and disables send. Real "Enviar a cocina" click: 2 burgers [Sin queso, Término medio] with queso 0 -> carne -300, tomate -40, queso stays 0.
- [x] PASS Server pre-check: plain burger with queso 0 -> "está agotado"; [sin queso + plain] with queso 0 -> "Ingredientes insuficientes para ZZTEST Burger (falta ZZTEST Queso)" (+ fresh stock payload); only "sin queso" -> OK; plain x2 with one portion of queso -> "Solo quedan 1 porciones" (old path kept); [plain + sin queso] with one portion -> OK.
- [x] PASS Race: 3 plain + 3 "sin queso" burgers simultaneously with exactly one portion of queso -> 1 plain + 3 sin queso won, queso 0 (never negative), carne/tomate consistent (5000 - 4 x 150 / 4 x 20).
- [x] PASS 390 px iframe: tile text "Solo con modificador", warning + disabled send readable.

### 2. "Cambiar plato" keeps compatible modifiers
- [x] PASS Burger [Sin queso, Término medio] swapped for Burger doble (only Sin queso attached): new line keeps Sin queso (queso 60 removed, carne -300 only), drops Término medio; old unit restocked exactly (carne +150, tomate +20, no queso).
- [x] PASS Same line swapped for "Sin receta" (only Término medio attached): keeps Término medio only. Swap to the same product still refused.

### 3. Receipts list the modifiers
- [x] PASS Real checkout of the bill in bill mode (cash drawer opened for the test, then removed): `sale_products.mods_text` = "Sin queso x6", "Sin queso x1", "Término medio x1" per product; `/sales/details/N` shows them under each dish; the archived PDF receipt prints "* Sin queso x6" under the burger line; totals/tip unchanged; stock NOT deducted again at checkout; bill paid, empty leftover bill voided.
- [x] PASS Thermal receipt code ran twice (receipt + copy) during the sale without PHP errors (printer missing, usual warning); its output could not be inspected. The PDF accent for "Término" was not re-checked after fixing a mis-seeded name (the test data had a literal `?` from the CLI seed; the app stores accents correctly, details page verified).
- [x] NOT CHANGED Receipts for free-form sales (no bill) have no modifiers; equal-split shares carry none.

### 4. Exact restock (snapshot of what each line took)
- [x] PASS Send 3 Arroz plato; recipe then changed (arroz 200 -> 300, queso removed from the recipe, carne 50 added): partial void of 1 gives back arroz +200 and queso +10 (NOT 300 / nothing / carne), the clone keeps the snapshot rows; voiding the remaining 2 returns the stock to exactly the starting values (arroz 5000, queso 100, carne untouched).
- [x] PASS Partial move of a modified line clones the snapshot; after raising the burger's carne to 400, voiding the moved clone returns carne +150 / tomate +20 (the amounts taken).
- [x] PASS Legacy line (snapshot flag cleared by SQL) still restocks by the current recipe like before (+30/+150/+20).
- [x] PASS Swap restocks exact snapshot amounts (see section 2). The paid-bill "no restock" rule was NOT re-run this round (the code path was not touched).

### Remaining notes
- The swap picker tiles ("Cambiar") still disable a dish whose full recipe is short even if the carried modifier would cover it (the server accepts it); only the order screen was made modifier-aware.
- Waiter role was not re-run for this round (same front-end code, role-independent; the waiter flow was verified in Block D).
- Free-form sale receipts and equal-split shares show no modifiers by design.
