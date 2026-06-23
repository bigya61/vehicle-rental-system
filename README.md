Vehicle Rental System — minimal PHP app

Setup (XAMPP on macOS):

1. Copy the `rentalsystem` folder into XAMPP htdocs (already in `/Applications/XAMPP/xamppfiles/htdocs/rentalsystem`).
2. Start Apache and MySQL via XAMPP Control Panel.
3. Open phpMyAdmin (http://localhost/phpmyadmin) and import `db.sql` or run the SQL in the SQL tab.
4. Visit http://localhost/rentalsystem/frontend/index.php to view the site.

Default DB credentials: host `127.0.0.1`, user `root`, empty password (update `config.php` if different).
Admin login: email `admin@example.com`, password `admin123`.

Database design:
- `users` stores registered users and admins
- `vehicles` now has `owner_id` to map which user posted each car
- `bookings` now optionally links to `user_id` for registered renters while keeping guest name/email

Files:
- `frontend/index.php` — public listing of vehicles with responsive car cards
- `frontend/book.php` — booking form and handler with vehicle image preview
- `frontend/login.php` — user login and signup page
- `frontend/logout.php` — logout handler
- `backend/admin.php` — admin dashboard to manage vehicles and view bookings
- `backend/admin-login.php` — admin login page
- `backend/config.php` — PDO connection
- `backend/functions.php` — DB helpers and user/vehicle/booking mappings
- `backend/db.sql` — current schema for fresh database installs
- `backend/db-update-users.sql` — migration script for existing installs
- `frontend/css/styles.css` — responsive styles
- `frontend/images/` — vehicle image assets

Next steps:
- Add authentication for `admin.php`.
- Add availability checks and date validation.
- Add image uploads for vehicles.

If you already imported the old database schema, add vehicle image paths with:
```sql
ALTER TABLE vehicles ADD COLUMN image VARCHAR(255) DEFAULT NULL;
UPDATE vehicles SET image = 'images/placeholder.svg' WHERE image IS NULL;
```
Then refresh the home page.

