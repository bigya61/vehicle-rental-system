# SETUP — Vehicle Rental System

Machine-followable setup. Execute steps top to bottom. Each step has expected
output. If output differs, jump to TROUBLESHOOTING.

App = plain PHP + MySQL/MariaDB. No Composer, no build step, no npm.

---

## 0. What this app needs

- PHP **8.0+** with extensions: `pdo`, `pdo_mysql`.
- MySQL **5.7+** OR MariaDB **10.3+**.
- A web entrypoint: either Apache (XAMPP) OR PHP's built-in server.

Default DB connection (hardcoded in `backend/config.php`):

```
host: 127.0.0.1   port: 3306   db: rentalsystem   user: root   password: (empty)
```

> The database, all tables, the admin user, and 3 demo cars are created
> AUTOMATICALLY on first page load by `backend/schema.php`. You do NOT run any
> SQL by hand. `backend/db.sql` is optional and only for manual seeding.

---

## 1. Confirm PHP + extensions

```bash
php -v
php -m | grep -E '^pdo_mysql$'
```

Expected: a PHP 8.x version line, and `pdo_mysql` printed.
If `pdo_mysql` missing → TROUBLESHOOTING §A.

---

## 2. Start the database

Pick ONE.

**XAMPP (macOS):**
```bash
sudo /Applications/XAMPP/xamppfiles/xampp startmysql
```

**Linux service (MariaDB/MySQL):**
```bash
sudo service mariadb start   # or: sudo service mysql start
```

Verify it listens on 3306:
```bash
ss -ltn | grep 3306
```
Expected: a line showing `127.0.0.1:3306`.

---

## 3. Make the `root` user reachable over TCP with empty password

The app connects as `root` over TCP (`127.0.0.1`) with **no password**. Fresh
MariaDB on Linux ships `root` using `unix_socket` auth, which rejects TCP. Fix
once (skip on XAMPP — XAMPP root already has empty password):

```bash
sudo mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING ''; FLUSH PRIVILEGES;"
```

> Why: a TCP connection from 127.0.0.1 reverse-resolves to `localhost`, so it
> matches `root@localhost`. Switching that account to empty-password native auth
> lets the app's PDO DSN connect. See TROUBLESHOOTING §B if you get error 1698.

If your environment requires a DB password instead, edit `$pass` in
`backend/config.php` to match — do not commit a real password.

---

## 4. Start the web entrypoint

Pick ONE. Run from the project root (the folder containing `frontend/` and
`backend/`).

**XAMPP Apache** — place project at `htdocs/rentalsystem`, start Apache, then
base URL = `http://localhost/rentalsystem`.

**PHP built-in server:**
```bash
php -S 127.0.0.1:8080
```
Base URL = `http://127.0.0.1:8080`.

---

## 5. Trigger auto-setup + verify

Open (or curl) the home page once — this creates the DB/tables/seed:

```bash
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/frontend/index.php"
```
(set `BASE` to your base URL). Expected: `200`.

Confirm schema + seed exist:
```bash
php -r 'require "backend/config.php";
echo "vehicles=".$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn()."\n";
echo "admin=".$pdo->query("SELECT COUNT(*) FROM users WHERE role=\"admin\"")->fetchColumn()."\n";'
```
Expected: `vehicles=3` and `admin=1`.

---

## 6. Default credentials

```
Admin login page : <BASE>/backend/admin-login.php
  email    : admin@example.com
  password : admin123

User login/signup: <BASE>/frontend/login.php
  (sign up with any name/email/password/phone — auto-creates the account)
```

---

## 7. Feature acceptance checklist

Confirm each works:

1. `GET <BASE>/frontend/index.php` → lists cars, each available car has a **Book now** button.
2. Admin: log in at `admin-login.php` with admin creds → reach **Admin Dashboard**. Add a car (make, model, year, price, image path, **Details**). New car appears in the fleet table and on the home page.
3. User: sign up at `login.php`. Then open a car's **Book now** → fill dates → submit. See "Booking successful".
4. After booking, that car on the home page shows a red **Booked** badge and a greyed, non-clickable **Booked** button. Its booking page hides the form and shows "currently booked".
5. Admin Dashboard → Bookings table → **Cancel** that booking → the car becomes Available again.
6. Visiting `book.php` without being logged in as a user redirects to `login.php`.
7. `frontend/add-car.php` redirects to the home page (car listing is admin-only by design).

---

## 8. Architecture notes (for editing safely)

- `backend/config.php` — PDO connection + auto-runs `ensureDatabaseSchema()`.
- `backend/schema.php` — idempotent DDL: creates/migrates tables, seeds admin + demo cars. Safe to run on every load.
- `backend/functions.php` — all DB helpers + CSRF helpers (`csrfToken`, `csrfField`, `verifyCsrf`, `safeRedirectPath`).
- Every POST form includes `<?php echo csrfField(); ?>` and every POST handler calls `verifyCsrf(...)` (returns HTTP 400 on mismatch). Keep this pattern when adding forms.
- Booking is a "simple flag" model: `vehicles.status` is `available` | `booked`; `bookings.status` is `active` | `cancelled`. Booking flips the car to `booked` in a transaction; cancel flips it back. No date-range overlap logic.

---

## TROUBLESHOOTING

**§A — `pdo_mysql` not loaded**
```bash
# Debian/Ubuntu:
sudo apt-get install -y php-mysql && sudo service mariadb restart
# XAMPP: enable extension=pdo_mysql in php.ini, restart Apache.
```

**§B — page shows `Database connection failed: ... [1698] Access denied for user 'root'@'localhost'`**
Root is on `unix_socket` auth. Run Step 3's `ALTER USER ...` command, then reload the page.

**§C — `[1049] Unknown database 'rentalsystem'`**
Normal only if the DB user lacks CREATE privilege. The app tries to create the DB itself; grant rights:
```bash
sudo mariadb -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;"
```

**§D — `Connection refused` on 3306**
DB not running. Redo Step 2; confirm with `ss -ltn | grep 3306`.

**§E — CSRF: forms return `Invalid CSRF token.` (HTTP 400)**
Sessions aren't persisting. Ensure cookies are enabled and the PHP session save path is writable. Don't strip the hidden `csrf_token` field from forms.

**§F — admin login fails with correct password**
The seeded hash is for `admin123`. If changed, reset:
```bash
php -r '$h=password_hash("admin123",PASSWORD_DEFAULT);
require "backend/config.php";
$s=$pdo->prepare("UPDATE users SET password=? WHERE email=?");
$s->execute([$h,"admin@example.com"]); echo "reset\n";'
```
