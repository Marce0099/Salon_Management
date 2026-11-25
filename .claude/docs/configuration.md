# Configuration contract (PHP today, Rust/Tauri later)

## Rule: never write the process environment
Apache on Windows (mpm_winnt, thread-safe PHP) serves requests as threads of **one process**. The process environment is
shared by all of them, and PHP undoes a request's `putenv()` when that request ends, so a finishing request erased the
variables another one was about to read: `Access denied for user ''@'localhost'`. Reproduced 2026-11-24: 50 of 400
requests failed at 25 in parallel; after the fix 0 of 2400.

- PHP: no `putenv()`. Read configuration only through `app_env($key, $default)` (`application/config/env.php`).
- Rust: no `std::env::set_var` after startup (the same hazard; it is `unsafe` since the 2024 edition). Load the file into
  a config struct once, pass it around.

## Resolution
Real environment variable (blank counts as unset) > `.env` file > default.
This is the order the Coco Cosmetics Rust backend uses (`environment > config.toml > install default`).
An environment variable set by Apache (`SetEnv`) or Windows therefore **overrides** `.env`.

## File format (`.env`, next to `index.php`, never committed)
One `KEY=value` per line. UTF-8 (BOM tolerated), LF or CRLF. Lines starting with `#` or `;` are comments. Key and value
are trimmed. One pair of surrounding quotes is removed; everything else is literal: no escapes, no `$` expansion, no
inline comments, `true`/`null` stay text. Lines without a valid key are ignored; the last duplicate wins.
Cases: `tools/env_cases.json` (PHP check: `C:/xampp/php/php.exe tools/test_env.php`). A Rust loader must pass the same file.

## Keys
| Key | Default | Used for |
|---|---|---|
| `DB_HOSTNAME` | `localhost` | database host |
| `DB_PORT` | `3306` | database port |
| `DB_USERNAME` | none, **required** | database user |
| `DB_PASSWORD` | empty | database password |
| `DB_NAME` | none, **required** | database name |
| `EMAIL_HOST`, `EMAIL_PORT`, `EMAIL_USERNAME`, `EMAIL_PASSWORD`, `EMAIL_FROM`, `EMAIL_FROM_NAME` | empty | SMTP (best effort) |
| `CI_ENCRYPTION_KEY` | empty | CodeIgniter only; no Rust equivalent needed |

A missing `DB_USERNAME` or `DB_NAME` stops the request with "Database configuration missing" instead of trying to
connect with empty credentials.

## For the Rust backend (not built yet for this app)
An installed service does not see a user's environment variables and should not keep settings beside the program files.
Following the Coco Cosmetics backend, put them in `%ProgramData%\<app>\config.toml` (`database_url` built from the `DB_*`
values) with environment variables kept only as test/development overrides. Importing an existing `.env` at install time
should use the format above.
