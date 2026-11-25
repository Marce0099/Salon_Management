# Printing plan (print queue, per-printer routing, agent)

Goal: sell the POS massively; customers have either Ethernet/Wi-Fi thermal printers or USB ones, often in a different room/PC from the server. One PHP server, many users hitting it.

## Principle
The server never "prints"; it **renders bytes once, stores a job, and delivers it**. Delivery is a pluggable transport per printer.

| Transport (`printers.conn_type`) | How | Customer needs |
|---|---|---|
| `network` | raw TCP to `address:port` (default 9100), 3 s timeout | nothing (type the IP) |
| `windows` | Windows printer name on the server PC (`WindowsPrintConnector`) or `smb://host/printer` | printer installed (and shared when remote) |
| `agent` (step 4) | a small agent on the PC with the USB printer polls the server | install agent once |

The same job format and tables are meant to be reused by the future Rust/Tauri backend (agent mode = the Tauri terminal / Windows service).

## Steps (status 2026-11-23: 1, 2, 3 and 4 done)
1. **Foundation (this branch).** Migration 030: `printers`, `print_jobs`, setting `receipt_copy`. Library `Printing` (render to bytes with `DummyPrintConnector`, store, deliver, retry). `Printers` admin screen (CRUD, test print, status), queue screen `/printers/queue` (retry, reprint, payload preview). Audit events. Fake network printer for tests (`tools/fake_printer.php`).
2. **Move both paths onto it.** `Kitchen::printTicket` and `Sales::printInvoice` build bytes and call `Printing`; kitchen keeps `orders_kitchen.print_status`; receipts get job status + reprint; receipt copy count becomes a setting. Legacy settings (`kitchen_printer_name`, `'CAJA'`) are seeded into `printers` so existing installs keep working.
3. **Routing (done).** `service_category.id_printer` -> kitchen printer, one comanda per printer; receipt printer per device (cookie). Not done: per-PRODUCT override (would add `products.id_printer`, resolved before the category), routing by terminal id (arrives with the Tauri terminal).
4. **Agent (done)** for USB printers on other PCs: `agent/PosPrintAgent.ps1` long-polls `GET /print_agent/jobs` (token header), prints RAW via winspool, `POST /print_agent/result`. Tables `print_agents` + `printers.id_agent`. Not verified: a real successful print on a physical/virtual printer (no elevation on the dev PC); Windows service wrapper (today: Startup-folder shortcut via -Install).
5. (Rust/Tauri) mirror schema and renderer; PHP output is the reference bytes.

## Delivery states and duplicates (migration 033, 2026-11-24)
`deliver()` reports printed / sent / waiting / failed. A newer copy of a ticket marks the unprinted older ones `cancelled`. Voids and swaps print a cancellation slip (`ref_type` `void`). Verified with the fake printer and the agent in dry-run mode; still no print on a physical printer.

## Testing without hardware
- `tools/fake_printer.php`: a TCP listener on port 9100 that decodes ESC/POS to text and saves each job under `tools/fake_printer_out/`.
- Failure cases: wrong IP (timeout), printer off (connection refused), invalid Windows name.
- Browser: test print from `/printers`, send a comanda from `/kitchen/tab/{id}`, pay a bill, then check `/printers/queue`.
