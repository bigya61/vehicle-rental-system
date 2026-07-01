-- SQL schema for Vehicle Rental System
CREATE DATABASE IF NOT EXISTS rentalsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rentalsystem;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone_number VARCHAR(50) NOT NULL UNIQUE,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  make VARCHAR(100) NOT NULL,
  model VARCHAR(100) NOT NULL,
  year INT DEFAULT NULL,
  transmission ENUM('manual', 'automatic') NOT NULL DEFAULT 'automatic',
  price_per_day DECIMAL(8,2) DEFAULT 0.00,
  image VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  status ENUM('available', 'booked') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  drive_mode ENUM('self_drive', 'with_driver') NOT NULL DEFAULT 'self_drive',
  daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password, phone_number, role) VALUES
('Admin', 'admin@example.com', '$2y$10$mjNpkDAzWr417cRyWS2as.N1pPL5xLQupISxJg.Djz9dvLTbBVoVa', '9000000000', 'admin');

INSERT INTO vehicles (owner_id, make, model, year, transmission, price_per_day, image) VALUES
(1, 'Toyota', 'Corolla', 2019, 'automatic', 40.00, 'images/toyota-corolla.svg'),
(1, 'Honda', 'Civic', 2020, 'manual', 45.00, 'images/honda-civic.svg'),
(1, 'Ford', 'Escape', 2018, 'automatic', 55.00, 'images/ford-escape.svg');
