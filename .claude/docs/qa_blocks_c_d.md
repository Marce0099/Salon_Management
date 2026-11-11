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

## Block D — Modifiers
_(not started)_
