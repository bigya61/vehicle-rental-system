USE rentalsystem;

-- Create the users table for account ownership and booking relationships.
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add owner relationship to vehicles.
ALTER TABLE vehicles
  ADD COLUMN IF NOT EXISTS owner_id INT NOT NULL DEFAULT 1 AFTER id,
  ADD CONSTRAINT IF NOT EXISTS fk_vehicles_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE;

-- Add optional user reference to bookings.
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER vehicle_id,
  ADD CONSTRAINT IF NOT EXISTS fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Seed an admin account if not already present.
INSERT INTO users (name, email, password, role)
SELECT 'Admin', 'admin@example.com', '$2y$10$mjNpkDAzWr417cRyWS2as.N1pPL5xLQupISxJg.Djz9dvLTbBVoVa', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@example.com');

-- If vehicles exist before this migration, assign them to the admin account.
UPDATE vehicles v
JOIN users u ON u.email = 'admin@example.com'
SET v.owner_id = u.id
WHERE v.owner_id IS NULL OR v.owner_id = 0;
