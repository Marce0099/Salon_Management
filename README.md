# Coco Cosmetics — Billing & Inventory System

A point-of-sale and inventory management web application built for a cosmetics retailer in Nicaragua. It handles the full sales cycle — from product catalog and client management through cash drawer control, purchasing, and business statistics.

---

## Features

- **Sales** — register sales with multiple payment methods (cash, POS/card, bank transfer, coupons), split payments, per-product discounts, and employee commissions
- **Products** — barcode-based catalog with cost/sell prices, stock tracking, service items (no stock deduction), service categories with default discount rates, and low-stock alerts
- **Appointments** — per-employee hourly scheduling grid with a mandatory transfer deposit at booking time; continues seamlessly into a sale
- **Loyalty program** — points accrual on service purchases and tiered redemption discounts, with a full per-client points ledger
- **Clients** — customer profiles with national ID, VIP flag, birthday discounts, and purchase history
- **Employees** — sales staff management with soft-delete and commission rates
- **Cash drawer** — daily open/close sessions that gate sale registration; tracks cordobas, dollars, POS, transfers, coupons, and cancelled-appointment deposits separately
- **Purchases (Invoices)** — record supplier invoices that restock products and update cost/sell prices
- **Purchase Orders** — generate orders to suppliers with optional PDF attachment
- **Returns** — log product returns to suppliers
- **Internal product use** — track stock withdrawn for internal business use (display, damage, employee use) rather than sold
- **Providers** — supplier/brand catalog
- **Product import** — bulk-import products from Excel spreadsheets
- **Dollar exchange** — configurable buy/sell exchange rate used across all transactions
- **Bank accounts** — manage accounts used as transfer payment targets
- **Statistics** — sales charts and revenue reporting (Chart.js)
- **Monthly goals** — set and track sales targets per month/year (admin only)
- **Settings** — app-wide config (company name, discount rates) and customizable logos (header, login, receipt, purchase order)
- **Thermal receipt printing** — ESC/POS receipts via `mike42/escpos-php`, plus a reprintable PDF copy of every sale receipt
- **PDF export** — order PDFs via `html2pdf`
- **Email notifications** — PHPMailer integration

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.0–8.3 · CodeIgniter 2 (CI2) · HMVC (`MX` module extension) |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 3 · Gentelella admin theme · jQuery · Chart.js · PNotify |
| Printing | `mike42/escpos-php` (ESC/POS thermal printers) |
| PDF | `html2pdf` |
| Email | PHPMailer |
| Server | Apache + MySQL via XAMPP |

---

## Prerequisites

- **[XAMPP 8.2.12](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe/download)** (Apache + MariaDB + PHP 8.2.12) — recommended version. This is the closest official Windows build to the PHP version this project is developed and tested against (PHP 8.3.30); the codebase uses no syntax newer than PHP 7.0 (only the `??` null-coalescing operator), so anything from PHP 7.0 up through 8.3 runs it identically.
- A web browser
- A thermal receipt printer (optional, for printing receipts)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Marce0099/Salon_Management.git
cd Salon_Management
```

Place the cloned folder inside your XAMPP `htdocs` directory (e.g. `C:\xampp\htdocs\coco-cosmetics`), or clone directly into `htdocs` root.

### 2. PHP dependencies

No `composer install` needed — third-party libraries (`mike42/escpos-php`, PHPMailer, `html2pdf`, PHPExcel) are vendored directly in the repo under `print/`, `application/libraries/`, and `includes/`.

### 3. Create the database

1. Start **XAMPP** and make sure **Apache** and **MySQL** are running.
2. Open [phpMyAdmin](http://localhost/phpmyadmin) (or use any MySQL client).
3. Create a new database named `coco_cosmetics2`:
   ```sql
   CREATE DATABASE coco_cosmetics2 CHARACTER SET utf8 COLLATE utf8_general_ci;
   ```
4. Select the `coco_cosmetics2` database and import the provided dump:
   - **phpMyAdmin:** Import tab → choose `coco_cosmetics2.sql` → Go
   - **CLI:**
     ```bash
     mysql -u root -p coco_cosmetics2 < coco_cosmetics2.sql
     ```

### 4. Configure environment variables

Copy the example file and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```dotenv
# Database
DB_HOSTNAME=localhost
DB_USERNAME=root
DB_PASSWORD=           # leave empty if XAMPP default (no password)
DB_NAME=coco_cosmetics2
DB_PORT=3306

# Email (used for notifications — optional)
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USERNAME=your_email@gmail.com
EMAIL_PASSWORD="your_app_password"
EMAIL_FROM=noreply@yourdomain.com
EMAIL_FROM_NAME="Coco Cosmetics"
OWNER_EMAIL=owner@example.com

# CodeIgniter session encryption key (generate any random 32-char string)
CI_ENCRYPTION_KEY="replace_with_a_random_secret_key"
```

> **Gmail tip:** If using Gmail for email, generate an [App Password](https://myaccount.google.com/apppasswords) instead of your account password — regular passwords won't work with SMTP when 2FA is enabled.

### 5. Set file permissions (Linux/macOS only)

```bash
chmod -R 775 application/cache application/logs uploads orders_pdf
```

### 6. Configure the virtual host (optional but recommended)

For a clean URL like `http://coco.local`, add a virtual host in your XAMPP `httpd-vhosts.conf`. Otherwise the app is accessible at `http://localhost/<folder-name>/`.

---

## Running the App

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open your browser and navigate to:
   - `http://localhost/` (if installed at `htdocs` root), or
   - `http://localhost/coco-cosmetics/` (if inside a subfolder)
3. You will land on the **login page**. Use your admin credentials stored in the `admins` table.

> **Default credentials:** The SQL dump seeds a demo admin account — username `admin`, password `ChangeMe123!`. Change this password immediately after first login; it's a placeholder for local evaluation only, not meant for any real deployment.

---

## Project Structure

```
application/
  controllers/    # One controller per domain (Sales, Products, Cash, …)
  models/         # DB-access layer; named <Domain>_mdl.php
  views/          # PHP templates; forms prefixed form_, lists match domain
  core/           # MY_Controller.php — base classes
  libraries/      # Easysearch (filterable pagination), Imagetool, PHPMailer
  helpers/        # data_helper.php, misc_helper.php
  config/         # CI2 config files
assets/           # CSS (admin.css), JS, Gentelella vendor bundle
print/            # ESC/POS thermal printing (mike42/escpos-php)
includes/         # html2pdf and PHPExcel libraries
uploads/          # Product images, receipts, and logos (git-ignored)
orders_pdf/       # Generated order PDFs (git-ignored)
```

---

## Environment Notes

- The `.env` file is **git-ignored** — never commit it with real credentials.
- `uploads/` and `orders_pdf/` are also git-ignored; create them manually if they don't exist after cloning:
  ```bash
  mkdir uploads orders_pdf
  ```
- The `products.active` column enables soft-delete; deleted products are filtered with `WHERE active=1` throughout the app.
- Monetary values are stored inconsistently (some `VARCHAR`, some `DECIMAL`) — this is intentional to preserve existing data behavior.

---

## License

This repository is shared publicly for portfolio and demonstration purposes. All rights reserved — see [LICENSE](LICENSE) for details; it is not licensed for reuse, redistribution, or deployment.
