# Run Instructions
cd /Applications/XAMPP/xamppfiles/htdocs/rentalsystem
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8081
## Project Overview

Vehicle Rental System is a minimal PHP + MySQL web app. Users can browse vehicles, register/log in, and make bookings. Admins manage the fleet and view bookings through a separate dashboard. There is no JavaScript build step — only PHP, MySQL, and static CSS assets.

---

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| XAMPP | Any recent | Provides Apache + MySQL + PHP |
| PHP | 8.0+ | Must have PDO and PDO_MySQL extensions enabled |
| MySQL / MariaDB | 5.7+ / 10.3+ | Bundled with XAMPP |
| Web browser | Any modern | Chrome, Firefox, Safari, Edge |

> **Linux / Windows users:** Adjust the XAMPP paths below to match your installation.
> - Linux default: `/opt/lampp`
> - Windows default: `C:\xampp`
> - macOS default: `/Applications/XAMPP/xamppfiles`

---

## Project Location

The project folder must be inside XAMPP's `htdocs` directory for Apache to serve it, or anywhere on disk if using the PHP built-in server.

**Recommended path (macOS):**

```
/Applications/XAMPP/xamppfiles/htdocs/rentalsystem/
```

**Recommended path (Linux):**

```
/opt/lampp/htdocs/rentalsystem/
```

If the folder is named differently, update the URLs in the steps below accordingly.

---

## Option 1: Run With XAMPP Apache (Recommended)

This is the standard approach. Apache serves PHP files directly via the XAMPP stack.

### 1. Start MySQL

```bash
# macOS
sudo /Applications/XAMPP/xamppfiles/xampp startmysql

# Linux
sudo /opt/lampp/lampp startmysql
```

### 2. Start Apache

```bash
# macOS
sudo /Applications/XAMPP/xamppfiles/xampp startapache

# Linux
sudo /opt/lampp/lampp startapache
```

### 3. Verify both services are running

```bash
# macOS
sudo /Applications/XAMPP/xamppfiles/xampp status

# Linux
sudo /opt/lampp/lampp status
```

Expected output includes `MySQL is running` and `Apache is running`.

### 4. Open the application

| Page | URL |
|---|---|
| Public vehicle listing | `http://localhost/rentalsystem/frontend/index.php` |
| User login / register | `http://localhost/rentalsystem/frontend/login.php` |
| Book a vehicle | `http://localhost/rentalsystem/frontend/book.php?id=1` |
| List your car (logged in) | `http://localhost/rentalsystem/frontend/add-car.php` |
| Admin login | `http://localhost/rentalsystem/backend/admin-login.php` |
| Admin dashboard | `http://localhost/rentalsystem/backend/admin.php` |

---

## Option 2: Run With PHP Built-In Server

Use this when Apache is unavailable or you want to run from a directory outside `htdocs`. The PHP built-in server is single-threaded and suitable for local development only.

### 1. Start MySQL (XAMPP MySQL only, no Apache needed)

```bash
# macOS
sudo /Applications/XAMPP/xamppfiles/xampp startmysql

# Linux
sudo /opt/lampp/lampp startmysql
```

### 2. Start the PHP server from the project root

```bash
# macOS — using XAMPP's bundled PHP binary
cd /path/to/rentalsystem
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8080

# Linux — using XAMPP's bundled PHP binary
cd /path/to/rentalsystem
/opt/lampp/bin/php -S 127.0.0.1:8080

# Or use system PHP if version >= 8.0
cd /path/to/rentalsystem
php -S 127.0.0.1:8080
```

Leave this terminal open while using the app. Press `Ctrl+C` to stop.

### 3. Open the application

| Page | URL |
|---|---|
| Public vehicle listing | `http://127.0.0.1:8080/frontend/index.php` |
| User login / register | `http://127.0.0.1:8080/frontend/login.php` |
| Book a vehicle | `http://127.0.0.1:8080/frontend/book.php?id=1` |
| List your car (logged in) | `http://127.0.0.1:8080/frontend/add-car.php` |
| Admin login | `http://127.0.0.1:8080/backend/admin-login.php` |
| Admin dashboard | `http://127.0.0.1:8080/backend/admin.php` |

---

## Option 3: Run With VS Code Five Server (Live Reload)

The project includes a `fiveserver.config.js` that points Five Server at the XAMPP PHP binary. Install the **Five Server** VS Code extension, then click **Go Live** in the status bar. PHP files are executed via XAMPP's PHP so the database connection works normally.

```js
// fiveserver.config.js
module.exports = {
  php: "/Applications/XAMPP/xamppfiles/bin/php"
};
```

Update the `php` path for Linux (`/opt/lampp/bin/php`) or Windows (`C:/xampp/php/php.exe`) as needed.

---

## Database

### Connection defaults (`backend/config.php`)

```
host:     127.0.0.1
database: rentalsystem
user:     root
password: (empty)
charset:  utf8mb4
```

To use a different user or password, edit `backend/config.php` and update the `$user` and `$pass` variables.

### Auto-provisioning on first request

When any PHP page is loaded for the first time, `backend/config.php` and `backend/schema.php` automatically:

1. Connect to MySQL at `127.0.0.1`.
2. Create the `rentalsystem` database if it does not exist.
3. Create the `users`, `vehicles`, and `bookings` tables if they are missing.
4. Add any missing columns to existing tables (safe to run on old schemas).
5. Seed the default admin account if no admin exists.
6. Seed three default vehicles (Toyota Corolla, Honda Civic, Ford Escape) if the fleet is empty.

No manual SQL import is required on a clean install.

### Database schema

**`users`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT | Primary key |
| name | VARCHAR(255) | Display name |
| email | VARCHAR(255) UNIQUE | Login identifier |
| password | VARCHAR(255) | bcrypt hash |
| phone | VARCHAR(50) | Optional |
| role | ENUM('user','admin') | Default: `user` |
| created_at | DATETIME | Auto-set |

**`vehicles`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT | Primary key |
| owner_id | INT | FK → users.id |
| make | VARCHAR(100) | e.g. Toyota |
| model | VARCHAR(100) | e.g. Corolla |
| year | INT | e.g. 2019 |
| price_per_day | DECIMAL(8,2) | Daily rate |
| image | VARCHAR(255) | Relative path under `frontend/` |
| created_at | DATETIME | Auto-set |

**`bookings`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT | Primary key |
| vehicle_id | INT | FK → vehicles.id |
| user_id | INT (nullable) | FK → users.id, NULL for guests |
| name | VARCHAR(255) | Renter's name |
| email | VARCHAR(255) | Renter's email |
| start_date | DATE | Rental start |
| end_date | DATE | Rental end |
| created_at | DATETIME | Auto-set |

---

## Default Credentials

### Admin account

```
email:    admin@example.com
password: admin123
```

Log in at `/backend/admin-login.php`. The admin dashboard at `/backend/admin.php` allows managing vehicles and viewing all bookings.

### Regular user account

No default user account is seeded. Register a new account from `/frontend/login.php` using any email and password. Registered users can list their own vehicles and their bookings are linked to their account.

---

## Manual Database Import

If you prefer to initialize the database from the SQL file instead of relying on auto-provisioning:

```bash
# macOS
/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot < backend/db.sql

# Linux
/opt/lampp/bin/mysql -h127.0.0.1 -uroot < backend/db.sql

# Windows (Command Prompt)
C:\xampp\mysql\bin\mysql.exe -h127.0.0.1 -uroot < backend\db.sql
```

`db.sql` creates the database, all three tables, the admin user, and three seed vehicles.

### Migrating an existing schema

If you have an older installation that is missing the `users` table or the `owner_id` / `user_id` foreign key columns, run the migration script:

```bash
# macOS
/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot rentalsystem < backend/db-update-users.sql

# Linux
/opt/lampp/bin/mysql -h127.0.0.1 -uroot rentalsystem < backend/db-update-users.sql
```

This script is idempotent — safe to run multiple times.

---

## File Structure

```
rentalsystem/
├── backend/
│   ├── admin-login.php        # Admin authentication page
│   ├── admin.php              # Admin dashboard (manage vehicles + bookings)
│   ├── config.php             # PDO connection, DB/schema bootstrap
│   ├── db.sql                 # Full schema + seed data for fresh installs
│   ├── db-update-images.sql   # Migration: add image column to vehicles
│   ├── db-update-users.sql    # Migration: add users table + FK columns
│   ├── functions.php          # DB helpers, CSRF utilities, safe redirect
│   └── schema.php             # Runtime schema enforcement (CREATE IF NOT EXISTS)
├── frontend/
│   ├── add-car.php            # Logged-in users list a vehicle for rent
│   ├── book.php               # Booking form + handler with vehicle preview
│   ├── index.php              # Public vehicle listing with responsive cards
│   ├── login.php              # User login + registration
│   ├── logout.php             # Session destroy + redirect
│   ├── css/
│   │   └── styles.css         # Responsive stylesheet
│   └── images/
│       ├── ford-escape.svg
│       ├── honda-civic.svg
│       ├── toyota-corolla.svg
│       ├── placeholder.svg
│       └── hero-vehicle.svg
├── fiveserver.config.js       # Five Server config (points to XAMPP PHP)
├── README.md
└── run.md                     # This file
```

---

## Stopping the Services

```bash
# Stop everything (macOS)
sudo /Applications/XAMPP/xamppfiles/xampp stopall

# Stop everything (Linux)
sudo /opt/lampp/lampp stopall

# Stop individual services (macOS)
sudo /Applications/XAMPP/xamppfiles/xampp stopmysql
sudo /Applications/XAMPP/xamppfiles/xampp stopapache
```

---

## Troubleshooting

### "Database connection failed"

- Verify MySQL is running: `sudo /Applications/XAMPP/xamppfiles/xampp status`
- Confirm the credentials in `backend/config.php` match your MySQL setup.
- If you set a root password in phpMyAdmin, update `$pass` in `config.php`.

### Port 80 already in use (Apache won't start)

Another process is using port 80. Either stop it or change Apache's port in `httpd.conf`:

```bash
# Find what is using port 80
sudo lsof -i :80
```

Alternatively, use Option 2 (PHP built-in server on port 8080).

### "Access denied for user 'root'"

XAMPP MySQL on some systems requires a socket connection. Change `$host` in `backend/config.php` from `127.0.0.1` to `localhost`.

### Blank page or PHP errors visible

Enable PHP error display for development by adding this at the top of `backend/config.php`:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

Remove these lines before deploying to production.

### Images not loading

Vehicle image paths are stored as relative paths from the `frontend/` directory (e.g. `images/toyota-corolla.svg`). Ensure the `frontend/images/` directory exists and the SVG/JPG files are present.

### Sessions not persisting (PHP built-in server)

The PHP built-in server handles one request at a time. If two tabs make simultaneous requests, one may block. This is a known limitation — use XAMPP Apache for more reliable session handling.

For an older database that already has `vehicles` and `bookings`, you can also run:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot rentalsystem < backend/db-update-users.sql
```
