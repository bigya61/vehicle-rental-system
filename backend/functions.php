<?php
require_once __DIR__ . '/config.php';

function getVehicles(PDO $pdo) {
    $stmt = $pdo->query('SELECT * FROM vehicles ORDER BY id');
    return $stmt->fetchAll();
}

function getVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createVehicle(PDO $pdo, $make, $model, $year, $price_per_day, $image) {
    $stmt = $pdo->prepare('INSERT INTO vehicles (make, model, year, price_per_day, image) VALUES (?, ?, ?, ?, ?)');
    return $stmt->execute([$make, $model, $year ?: null, $price_per_day, $image]);
}

function updateVehicle(PDO $pdo, $id, $make, $model, $year, $price_per_day, $image) {
    $stmt = $pdo->prepare('UPDATE vehicles SET make = ?, model = ?, year = ?, price_per_day = ?, image = ? WHERE id = ?');
    return $stmt->execute([$make, $model, $year ?: null, $price_per_day, $image, $id]);
}

function deleteVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('DELETE FROM vehicles WHERE id = ?');
    return $stmt->execute([$id]);
}

function createBooking(PDO $pdo, $vehicle_id, $name, $email, $start_date, $end_date) {
    $stmt = $pdo->prepare('INSERT INTO bookings (vehicle_id, name, email, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$vehicle_id, $name, $email, $start_date, $end_date]);
}

function getBookings(PDO $pdo) {
    $stmt = $pdo->query('SELECT b.*, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id ORDER BY b.created_at DESC');
    return $stmt->fetchAll();
}
