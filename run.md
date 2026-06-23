# Run Instructions

## Requirements

- XAMPP installed at `/Applications/XAMPP`
- Apache and MySQL available from XAMPP
- Project located at `/Applications/XAMPP/xamppfiles/htdocs/rentalsystem`

## Option 1: Run With XAMPP Apache

1. Start MySQL:

   ```bash
   sudo /Applications/XAMPP/xamppfiles/xampp startmysql
   ```

2. Start Apache:

   ```bash
   sudo /Applications/XAMPP/xamppfiles/xampp startapache
   ```

3. Open the frontend:

   ```text
   http://localhost/rentalsystem/frontend/index.php
   ```

4. Open the user car listing page after logging in:

   ```text
   http://localhost/rentalsystem/frontend/add-car.php
   ```

5. Open the backend admin login:

   ```text
   http://localhost/rentalsystem/backend/admin-login.php
   ```

## Option 2: Run With PHP Built-In Server

1. Start MySQL:

   ```bash
   sudo /Applications/XAMPP/xamppfiles/xampp startmysql
   ```

2. Start the PHP server from the project folder:

   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/rentalsystem
   /Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8080
   ```

3. Open the frontend:

   ```text
   http://127.0.0.1:8080/frontend/index.php
   ```

4. Open the user car listing page after logging in:

   ```text
   http://127.0.0.1:8080/frontend/add-car.php
   ```

5. Open the backend admin login:

   ```text
   http://127.0.0.1:8080/backend/admin-login.php
   ```

## Database

The app connects to MySQL with these defaults from `backend/config.php`:

```text
host: 127.0.0.1
database: rentalsystem
user: root
password: empty
```

On first run, the app creates the `rentalsystem` database if it is missing, creates missing tables and columns, seeds an admin user, and adds default vehicles if the fleet is empty.

Default admin login:

```text
email: admin@example.com
password: admin123
```

## Manual Database Import

If you prefer to initialize the database manually:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot < backend/db.sql
```

For an older database that already has `vehicles` and `bookings`, you can also run:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h127.0.0.1 -uroot rentalsystem < backend/db-update-users.sql
```
