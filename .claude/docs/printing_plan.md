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

## Steps
1. **Foundation (this branch).** Migration 030: `printers`, `print_jobs`, setting `receipt_copy`. Library `Printing` (render to bytes with `DummyPrintConnector`, store, deliver, retry). `Printers` admin screen (CRUD, test print, status), queue screen `/printers/queue` (retry, reprint, payload preview). Audit events. Fake network printer for tests (`tools/fake_printer.php`).
2. **Move both paths onto it.** `Kitchen::printTicket` and `Sales::printInvoice` build bytes and call `Printing`; kitchen keeps `orders_kitchen.print_status`; receipts get job status + reprint; receipt copy count becomes a setting. Legacy settings (`kitchen_printer_name`, `'CAJA'`) are seeded into `printers` so existing installs keep working.
3. **Routing.** Several kitchen stations (kitchen/bar): a product or category points to a printer role; one order may split into several jobs. Receipt printer per cashier station (terminal id).
4. **Agent** for USB printers on other PCs: `GET /print/jobs?station=X` (token auth), print locally, `POST /print/jobs/{id}/result`. Reuses `print_jobs` as-is.
5. (Rust/Tauri) mirror schema and renderer; PHP output is the reference bytes.

## Testing without hardware
- `tools/fake_printer.php`: a TCP listener on port 9100 that decodes ESC/POS to text and saves each job under `tools/fake_printer_out/`.
- Failure cases: wrong IP (timeout), printer off (connection refused), invalid Windows name.
- Browser: test print from `/printers`, send a comanda from `/kitchen/tab/{id}`, pay a bill, then check `/printers/queue`.
