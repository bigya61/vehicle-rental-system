<?php

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensureDatabaseSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!tableExists($pdo, 'vehicles')) {
        $pdo->exec(
            "CREATE TABLE vehicles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                make VARCHAR(100) NOT NULL,
                model VARCHAR(100) NOT NULL,
                year INT DEFAULT NULL,
                price_per_day DECIMAL(8,2) DEFAULT 0.00,
                image VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        if (!columnExists($pdo, 'vehicles', 'owner_id')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN owner_id INT NULL AFTER id');
        }
        if (!columnExists($pdo, 'vehicles', 'image')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN image VARCHAR(255) DEFAULT NULL');
        }
        if (!columnExists($pdo, 'vehicles', 'created_at')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    if (!tableExists($pdo, 'bookings')) {
        $pdo->exec(
            "CREATE TABLE bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } elseif (!columnExists($pdo, 'bookings', 'user_id')) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN user_id INT DEFAULT NULL AFTER vehicle_id');
    }

    $adminPassword = '$2y$10$mjNpkDAzWr417cRyWS2as.N1pPL5xLQupISxJg.Djz9dvLTbBVoVa';
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role)
         SELECT 'Admin', 'admin@example.com', ?, 'admin'
         WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@example.com')"
    );
    $stmt->execute([$adminPassword]);

    $adminId = (int) $pdo->query("SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1")->fetchColumn();
    if ($adminId > 0 && columnExists($pdo, 'vehicles', 'owner_id')) {
        $stmt = $pdo->prepare('UPDATE vehicles SET owner_id = ? WHERE owner_id IS NULL OR owner_id = 0');
        $stmt->execute([$adminId]);
    }

    $vehicleCount = (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
    if ($vehicleCount === 0 && $adminId > 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO vehicles (owner_id, make, model, year, price_per_day, image) VALUES
             (?, ?, ?, ?, ?, ?),
             (?, ?, ?, ?, ?, ?),
             (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId, 'Toyota', 'Corolla', 2019, 4000.00, 'images/toyota.jpg',
            $adminId, 'Honda', 'Civic', 2020, 4500.00, 'images/honda.jpg',
            $adminId, 'Ford', 'Escape', 2018, 5500.00, 'images/ford.jpg',
        ]);
    }

    $pdo->exec(
        "UPDATE vehicles SET price_per_day = CASE
            WHEN make = 'Toyota' AND model = 'Corolla' AND price_per_day = 40.00 THEN 4000.00
            WHEN make = 'Honda' AND model = 'Civic' AND price_per_day = 45.00 THEN 4500.00
            WHEN make = 'Ford' AND model = 'Escape' AND price_per_day = 55.00 THEN 5500.00
            ELSE price_per_day
        END"
    );
}
