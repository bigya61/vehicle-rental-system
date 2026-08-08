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

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

function uniqueIndexExistsForColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
           AND NON_UNIQUE = 0'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnEnumHasValue(PDO $pdo, string $table, string $column, string $value): bool
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    $columnType = (string) $stmt->fetchColumn();
    return $columnType !== '' && str_contains($columnType, "'" . $value . "'");
}

function ensureDatabaseSchema(PDO $pdo): void
{
    if (!tableExists($pdo, 'users')) {
        $pdo->exec(
            "CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                phone_number VARCHAR(50) NOT NULL UNIQUE,
                role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        if (!uniqueIndexExistsForColumn($pdo, 'users', 'email')) {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY email (email)');
        }

        if (!columnExists($pdo, 'users', 'phone_number')) {
            if (columnExists($pdo, 'users', 'phone')) {
                $pdo->exec('ALTER TABLE users CHANGE COLUMN phone phone_number VARCHAR(50) DEFAULT NULL');
            } else {
                $pdo->exec('ALTER TABLE users ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL AFTER password');
            }
        }

        $pdo->exec("UPDATE users SET phone_number = CONCAT('migration-', id) WHERE phone_number IS NULL OR phone_number = ''");

        if (!indexExists($pdo, 'users', 'phone_number')) {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY phone_number (phone_number)');
        }

        $pdo->exec('ALTER TABLE users MODIFY phone_number VARCHAR(50) NOT NULL');
    }

    if (!tableExists($pdo, 'vehicles')) {
        $pdo->exec(
            "CREATE TABLE vehicles (
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
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
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
        if (!columnExists($pdo, 'vehicles', 'transmission')) {
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN transmission ENUM('manual', 'automatic') NOT NULL DEFAULT 'automatic' AFTER year");
        }
        if (!columnExists($pdo, 'vehicles', 'description')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN description TEXT DEFAULT NULL');
        }
        if (!columnExists($pdo, 'vehicles', 'status')) {
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN status ENUM('available', 'booked') NOT NULL DEFAULT 'available'");
        }
        if (!columnExists($pdo, 'vehicles', 'created_at')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
        if (!columnExists($pdo, 'vehicles', 'is_deleted')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0');
        }

        $pdo->exec("UPDATE vehicles SET transmission = 'automatic' WHERE transmission IS NULL OR transmission NOT IN ('manual', 'automatic')");
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
                drive_mode ENUM('self_drive', 'with_driver') NOT NULL DEFAULT 'self_drive',
                daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        if (!columnExists($pdo, 'bookings', 'user_id')) {
            $pdo->exec('ALTER TABLE bookings ADD COLUMN user_id INT DEFAULT NULL AFTER vehicle_id');
        }
        if (!columnExists($pdo, 'bookings', 'status')) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active'");
        } elseif (!columnEnumHasValue($pdo, 'bookings', 'status', 'completed')) {
            $pdo->exec("ALTER TABLE bookings MODIFY COLUMN status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active'");
        }
        if (!columnExists($pdo, 'bookings', 'drive_mode')) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN drive_mode ENUM('self_drive', 'with_driver') NOT NULL DEFAULT 'self_drive' AFTER end_date");
        }
        if (!columnExists($pdo, 'bookings', 'daily_rate')) {
            $pdo->exec('ALTER TABLE bookings ADD COLUMN daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER drive_mode');
        }
        if (!columnExists($pdo, 'bookings', 'total_amount')) {
            $pdo->exec('ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER daily_rate');
        }

        $pdo->exec("UPDATE bookings SET drive_mode = 'self_drive' WHERE drive_mode IS NULL OR drive_mode NOT IN ('self_drive', 'with_driver')");
        $pdo->exec(
            "UPDATE bookings b
            JOIN vehicles v ON v.id = b.vehicle_id
            SET b.daily_rate = v.price_per_day + CASE WHEN b.drive_mode = 'with_driver' THEN 2000 ELSE 0 END
            WHERE b.daily_rate IS NULL OR b.daily_rate <= 0"
        );
        $pdo->exec(
            "UPDATE bookings
            SET total_amount = (GREATEST(DATEDIFF(end_date, start_date) + 1, 1) * daily_rate)
            WHERE total_amount IS NULL OR total_amount <= 0"
        );
    }

    $adminPassword = '$2y$10$mjNpkDAzWr417cRyWS2as.N1pPL5xLQupISxJg.Djz9dvLTbBVoVa';
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, phone_number, role)
         SELECT 'Admin', 'admin@example.com', ?, '9000000000', 'admin'
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
            'INSERT INTO vehicles (owner_id, make, model, year, transmission, price_per_day, image) VALUES
             (?, ?, ?, ?, ?, ?, ?),
             (?, ?, ?, ?, ?, ?, ?),
             (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId, 'Toyota', 'Corolla', 2019, 'automatic', 4000.00, 'images/toyota.jpg',
            $adminId, 'Honda', 'Civic', 2020, 'manual', 4500.00, 'images/honda.jpg',
            $adminId, 'Ford', 'Escape', 2018, 'automatic', 5500.00, 'images/ford.jpg',
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
