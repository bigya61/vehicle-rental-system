-- SQL schema for Vehicle Rental System
CREATE DATABASE IF NOT EXISTS rentalsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rentalsystem;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  make VARCHAR(100) NOT NULL,
  model VARCHAR(100) NOT NULL,
  year INT DEFAULT NULL,
  price_per_day DECIMAL(8,2) DEFAULT 0.00,
  image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO vehicles (make, model, year, price_per_day, image) VALUES
('Toyota','Corolla',2019,40.00,'images/toyota-corolla.svg'),
('Honda','Civic',2020,45.00,'images/honda-civic.svg'),
('Ford','Escape',2018,55.00,'images/ford-escape.svg');
