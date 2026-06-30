USE rentalsystem;

-- Rename the old phone column to phone_number.
ALTER TABLE users
  CHANGE COLUMN phone phone_number VARCHAR(50) DEFAULT NULL;

ALTER TABLE users
  ADD UNIQUE KEY email (email);

-- Backfill any missing values so the column can be made NOT NULL and UNIQUE.
UPDATE users
SET phone_number = CONCAT('migration-', id)
WHERE phone_number IS NULL OR phone_number = '';

ALTER TABLE users
  MODIFY phone_number VARCHAR(50) NOT NULL,
  ADD UNIQUE KEY phone_number (phone_number);

-- Add owner relationship to vehicles.
ALTER TABLE vehicles
  ADD COLUMN IF NOT EXISTS owner_id INT NOT NULL DEFAULT 1 AFTER id,
  ADD CONSTRAINT IF NOT EXISTS fk_vehicles_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE;

-- Add optional user reference to bookings.
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER vehicle_id,
  ADD CONSTRAINT IF NOT EXISTS fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Seed an admin account if not already present.
INSERT INTO users (name, email, password, phone_number, role)
SELECT 'Admin', 'admin@example.com', '$2y$10$mjNpkDAzWr417cRyWS2as.N1pPL5xLQupISxJg.Djz9dvLTbBVoVa', '9000000000', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@example.com');

-- If vehicles exist before this migration, assign them to the admin account.
UPDATE vehicles v
JOIN users u ON u.email = 'admin@example.com'
SET v.owner_id = u.id
WHERE v.owner_id IS NULL OR v.owner_id = 0;
