# Architectural Patterns

## Controller Hierarchy

All authenticated controllers extend `Admin_Controller` (defined in `application/core/MY_Controller.php:11`), which:
- Reads `id_admin` from the session on every request and redirects to `login` if absent.
- Provides `renderPanel($view)` that composes `header` + content view + `footer`.
- Exposes `$this->content_data` (array merged into the content view) and `get_userID()`.

Public-only controllers (e.g. `Login`) extend `MX_Controller` directly.

## View Rendering

Controllers populate `$this->content_data` then call `$this->renderPanel('view_name')`.
`renderPanel` appends `.php` automatically (`MY_Controller.php:32`).
Header and footer are always loaded; they include all global CSS/JS.

## Model Layer

Models extend `CI_Model` and hold only DB access logic — no business rules.
Naming: `<Domain>_mdl` (e.g. `Sales_mdl`, `Products_mdl`).
Controllers load their models in `__construct` via `$this->load->model('sales_mdl')`.
Raw SQL strings are used for joins; CI's Active Record (`$this->db->get`, `insert`, `query`) is used for simple CRUD.

## Easysearch Pattern

Every list view uses the `Easysearch` library (`application/libraries/Easysearch.php`) for filterable, paginated results:

```
$search['query']     = 'SELECT ...';        // base SQL
$search['fields']    = [ 'name' => ['column'=>'name','type'=>'string'], … ];
$search['order_by']  = ['id_x' => 'desc'];
$search['limit']     = 150;
$search['return_as'] = 'key_name';          // key in returned array
$data = $this->easysearch->search($search);
$this->content_data = array_merge($data, $view_data);
```

`search()` returns the paginated result under `$data[$return_as]` plus `no_limit_count`, `get_query`, and a configured CI pagination object.

## AJAX Response Shape

All form submissions return JSON. The consistent shape used across controllers:

```php
$response['type']    = 'success' | 'error' | 'warning';
$response['message'] = '…';
echo json_encode($response);
exit();
```

Views consume this with `$.ajax` / `$.post` and display using PNotify.

## Session Flash (Cross-Request Status)

To pass status messages across a redirect, controllers write to `view_data` in the session:

```php
$sess_data['status'] = ['message' => '…', 'class' => 'warning'];
$this->session->set_userdata('view_data', $sess_data);
redirect('target/route');
```

The destination controller calls `load_data($this->session)` (`application/helpers/data_helper.php:6`) to drain and return the session payload; the helper clears it immediately after reading.

## CSRF / Double-Submit Token

`formSubmited()` (`application/helpers/misc_helper.php:3`) validates a `tk` field in POST against a session token and clears it on success — preventing double-submit. Use `formSubmited()` for full token validation; use `formPosted()` for plain `!empty($_POST)` checks (used when token is not needed, e.g. partial AJAX operations).

## Database Transactions

Multi-step writes (e.g. saving a sale + its products + payments) use CI2's transaction wrappers:

```php
$this->db->trans_start();
// … multiple inserts/updates …
$this->db->trans_complete();
if ($this->db->trans_status() === FALSE) { /* handle error */ }
```

See `application/controllers/Sales.php:188`.

## Thermal Receipt Printing

Sales trigger ESC/POS printing via `mike42/escpos-php` loaded through `print/autoload.php`.
The printer is addressed by Windows printer name `'CAJA'` using `WindowsPrintConnector`.
Printing is wrapped in `try/catch`; failure returns `FALSE` but does not abort the sale.
See `Sales::printInvoice()` at `application/controllers/Sales.php:298`.

## Autoloaded Globals

`application/config/autoload.php:63,91` registers these for every request:
- **Libraries:** `database`, `session`, `form_validation`
- **Helpers:** `url`, `data`, `misc`, `date`
