# TradeVision Pro — cPanel Deployment Guide

A complete, production-ready trading SaaS built with **PHP 8.2+ and MySQL 8** only.
No Node, npm, React, build step, Docker, Redis, or SSH required.

---

## 1. What you are deploying

```
public_html/         → upload the CONTENTS of this folder to your cPanel public_html
database/            → tradevision_schema.sql (import once)
```

Stack:
- **Frontend:** HTML5, Tailwide CSS (CDN), Vanilla JS, Alpine.js, Chart.js, TradingView widgets
- **Backend:** PHP 8.2+, MySQL 8+, REST API, JWT auth, cron jobs
- **Data sources (free, public):** Binance Spot & Futures, CoinGecko, Yahoo Finance
- **Payments:** Manual USDT (BEP20) with admin approval — no Stripe/PayPal/Razorpay
- **Analysis:** 100% rule-based math. **No AI / LLM / generated signals anywhere.**

---

## 2. Step-by-step deployment

### Step 1 — Upload files
1. In cPanel open **File Manager**.
2. Upload everything inside `public_html/` to your site's `public_html/`
   (or a subfolder if running under a path).
3. Keep the folder structure intact.

### Step 2 — Create the MySQL database
1. cPanel → **MySQL® Databases**.
2. Create a database, e.g. `youracct_tradevision`.
3. Create a user with a strong password and **add the user to the database** with **All Privileges**.

### Step 3 — Import the schema
1. cPanel → **phpMyAdmin** → select your database.
2. **Import** → choose `database/tradevision_schema.sql` → **Go**.
3. This creates all tables and seeds plans, default settings, the wallet row, and the admin account.

### Step 4 — Configure `config.php`
Edit `public_html/config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'youracct_tradevision');
define('DB_USER', 'youracct_dbuser');
define('DB_PASS', 'your-strong-db-password');

define('JWT_SECRET', 'paste-a-64-char-random-string-here');
define('APP_URL', 'https://yourdomain.com'); // optional; auto-detected otherwise
```

Generate a JWT secret locally or in cPanel Terminal:
```
php -r "echo bin2hex(random_bytes(32));"
```

> **Tip:** You can also set credentials via environment variables
> (`TVP_DB_NAME`, `TVP_DB_USER`, `TVP_DB_PASS`, `TVP_DB_HOST`, `TVP_DB_SOCKET`, `TVP_ENV`)
> if your host supports them — handy for keeping secrets out of files.

### Step 5 — Set folder permissions
Make these writable (755 or 775):
```
public_html/uploads  (and charts/ payments/ profiles/)
public_html/storage  (and cache/ logs/)
```

### Step 6 — Launch & log in as admin
Open `https://yourdomain.com/`.

**Default admin (CHANGE IMMEDIATELY):**
- URL: `https://yourdomain.com/admin/`
- Email: `admin@tradevision.pro`
- Password: `Admin@12345`

In the admin panel:
1. Go to **Wallets** → set your real USDT (BEP20) receiving address.
2. Go to **Settings** → review signal weights, scanner limits, set a `cron_key`,
   add your Telegram bot token and support email.
3. Create a new admin / change the default password (see FAQ below).

---

## 3. Cron jobs (the engine)

The scanner and signals run via cron. cPanel → **Cron Jobs**.
Find your PHP CLI path (often `/usr/local/bin/php` or `/opt/cpanel/ea-php82/root/usr/bin/php`).

See `public_html/cron/crontab.example.txt` for the exact lines. Summary:

| Frequency      | Command                                             | Purpose                |
|----------------|-----------------------------------------------------|------------------------|
| every minute   | `php .../cron/scan.php 1m`                           | 1-minute scan          |
| every 5 min    | `php .../cron/scan.php 5m`                           | 5-minute scan          |
| every 15 min   | `php .../cron/scan.php 15m`                          | 15-minute scan         |
| hourly         | `php .../cron/scan.php 1h`                           | 1-hour scan            |
| every 2 min    | `php .../cron/signals_update.php`                   | Track TP/SL hits       |
| every 2 min    | `php .../cron/alerts.php`                            | Fire user alerts       |
| hourly         | `php .../cron/maintenance.php`                       | Expire subs, clean up  |

**No CLI cron?** Set `cron_key` in admin Settings and trigger via URL:
```
https://yourdomain.com/cron/scan.php?cron_key=YOURKEY&tf=15m
```
(use a cPanel/UptimeRobot-style HTTP scheduler).

---

## 4. Important note on Binance access

Binance's global API (`api.binance.com` / `fapi.binance.com`) is **geo-restricted in some
regions** (returns HTTP 451). If your hosting server is located in a restricted region:

- Crypto scanning may return empty until you host in an allowed region, **or**
- The platform still works for **stocks, forex and commodities** via Yahoo Finance,
  and for global metrics via CoinGecko.

CoinGecko and Yahoo Finance are not geo-restricted. The code degrades gracefully —
unreachable sources simply produce no rows rather than errors.

---

## 5. Security checklist (do before going live)

- [ ] Change `JWT_SECRET` to a unique 64-char random string.
- [ ] Change the default admin password.
- [ ] Set strong DB credentials in `config.php`.
- [ ] Confirm `config/`, `includes/`, `storage/` return **403** from the browser.
- [ ] Confirm uploaded files cannot execute (the `uploads/.htaccess` blocks scripts).
- [ ] Enable HTTPS (uncomment the force-HTTPS block in `public_html/.htaccess`).
- [ ] Set `TVP_ENV` to `production` (default) so errors are not displayed.

Built-in protections: CSRF tokens, XSS escaping, PDO prepared statements (SQL-injection safe),
bcrypt password hashing, JWT auth, hardened sessions, upload MIME validation, and
DB-backed rate limiting.

---

## 6. Troubleshooting

| Symptom | Fix |
|--------|-----|
| Blank page / 500 | Set `TVP_ENV=development` temporarily or check `storage/logs/php-error.log`. |
| "Database connection error" | Re-check DB_* values; ensure the user is added to the DB. |
| Login fails for admin | Ensure `PASSWORD_PEPPER` is empty (default) before first login; see FAQ. |
| Scanner table empty | The cron jobs have not run yet, or Binance is geo-blocked (see §4). |
| Auth header missing | Ensure `mod_rewrite` is on; `.htaccess` passes the Authorization header. |
| Uploads fail | Make `uploads/` and subfolders writable (755/775). |

---

## 7. FAQ

**How do I change the admin password?**
Log in, or run in cPanel Terminal:
```
php -r "echo password_hash('YourNewPass', PASSWORD_BCRYPT);"
```
Then in phpMyAdmin update `users.password_hash` (and `admins.password_hash`) for the admin row.
Leave `PASSWORD_PEPPER` empty unless you set it before creating users.

**Is any of the analysis AI-generated?**
No. Every indicator, structure read, zone, SMC detection and signal is computed from live
OHLCV data using standard mathematical formulas and rules. There is no AI/LLM anywhere.

**Can I change the signal logic?**
Yes — adjust the weights and thresholds in the admin **Settings** tab, or edit
`includes/engine/SignalEngine.php` and `Scanner.php`.
