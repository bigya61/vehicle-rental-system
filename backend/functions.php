<?php
require_once __DIR__ . '/config.php';

function getVehicles(PDO $pdo) {
    $stmt = $pdo->query('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v JOIN users u ON v.owner_id = u.id ORDER BY v.id');
    return $stmt->fetchAll();
}

function getVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v JOIN users u ON v.owner_id = u.id WHERE v.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getVehiclesByOwner(PDO $pdo, $owner_id) {
    $stmt = $pdo->prepare('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v JOIN users u ON v.owner_id = u.id WHERE v.owner_id = ? ORDER BY v.id DESC');
    $stmt->execute([$owner_id]);
    return $stmt->fetchAll();
}

function vehicleImagePath(array $vehicle) {
    $image = trim($vehicle['image'] ?? '');
    return $image !== '' ? $image : 'images/placeholder.svg';
}

function createVehicle(PDO $pdo, $owner_id, $make, $model, $year, $price_per_day, $image) {
    $stmt = $pdo->prepare('INSERT INTO vehicles (owner_id, make, model, year, price_per_day, image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$owner_id, $make, $model, $year ?: null, $price_per_day, $image]);
}

function updateVehicle(PDO $pdo, $id, $make, $model, $year, $price_per_day, $image) {
    $stmt = $pdo->prepare('UPDATE vehicles SET make = ?, model = ?, year = ?, price_per_day = ?, image = ? WHERE id = ?');
    return $stmt->execute([$make, $model, $year ?: null, $price_per_day, $image, $id]);
}

function deleteVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('DELETE FROM vehicles WHERE id = ?');
    return $stmt->execute([$id]);
}

function createUser(PDO $pdo, $name, $email, $password, $phone = null, $role = 'user') {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$name, $email, $password, $phone, $role]);
}

function getUserByEmail(PDO $pdo, $email) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function getUserById(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function authenticateUser(PDO $pdo, $email, $password) {
    $user = getUserByEmail($pdo, $email);
    if (!$user) {
        return false;
    }
    return password_verify($password, $user['password']) ? $user : false;
}

function createBooking(PDO $pdo, $vehicle_id, $user_id, $name, $email, $start_date, $end_date) {
    $stmt = $pdo->prepare('INSERT INTO bookings (vehicle_id, user_id, name, email, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$vehicle_id, $user_id ?: null, $name, $email, $start_date, $end_date]);
}

function getBookings(PDO $pdo) {
    $stmt = $pdo->query('SELECT b.*, v.make, v.model, u.name AS user_name, u.email AS user_email FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC');
    return $stmt->fetchAll();
}
