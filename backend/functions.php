<?php
require_once __DIR__ . '/config.php';

function authCookieSecureFlag(): bool {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function setAuthCookie(array $payload): void {
    $payload['exp'] = time() + AUTH_COOKIE_DURATION;
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }

    $signature = hash_hmac('sha256', $json, AUTH_COOKIE_SECRET, true);
    $value = base64_encode($json) . '.' . base64_encode($signature);

    setcookie(
        AUTH_COOKIE_NAME,
        $value,
        [
            'expires' => time() + AUTH_COOKIE_DURATION,
            'path' => '/',
            'httponly' => true,
            'samesite' => AUTH_COOKIE_SAMESITE,
            'secure' => authCookieSecureFlag(),
        ]
    );
}

function clearAuthCookie(): void {
    setcookie(
        AUTH_COOKIE_NAME,
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => AUTH_COOKIE_SAMESITE,
            'secure' => authCookieSecureFlag(),
        ]
    );
}

function getAuthCookiePayload(): ?array {
    if (empty($_COOKIE[AUTH_COOKIE_NAME]) || !is_string($_COOKIE[AUTH_COOKIE_NAME])) {
        return null;
    }

    $parts = explode('.', $_COOKIE[AUTH_COOKIE_NAME], 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$encodedJson, $encodedSig] = $parts;
    $json = base64_decode($encodedJson, true);
    $sig = base64_decode($encodedSig, true);
    if ($json === false || $sig === false) {
        return null;
    }

    $expectedSig = hash_hmac('sha256', $json, AUTH_COOKIE_SECRET, true);
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['id']) || empty($payload['email']) || empty($payload['role']) || empty($payload['exp'])) {
        return null;
    }

    if (!in_array($payload['role'], ['user', 'admin'], true)) {
        return null;
    }

    if (!is_int($payload['exp']) && !ctype_digit((string) $payload['exp'])) {
        return null;
    }

    if (time() > (int) $payload['exp']) {
        return null;
    }

    return $payload;
}

function restoreLoginFromCookie(PDO $pdo): void {
    if (!empty($_SESSION['user'])) {
        return;
    }

    $payload = getAuthCookiePayload();
    if (!$payload) {
        return;
    }

    $user = getUserById($pdo, $payload['id']);
    if (!$user || $user['email'] !== $payload['email'] || $user['role'] !== $payload['role']) {
        clearAuthCookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone_number' => $user['phone_number'],
    ];
    if ($user['role'] === 'admin') {
        $_SESSION['admin'] = true;
    }
}

restoreLoginFromCookie($pdo);

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

function verifyCsrf(?string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function safeRedirectPath(?string $path, string $default): string {
    if (!is_string($path) || $path === '') {
        return $default;
    }
    // Only allow same-app relative paths: no scheme, host, or protocol-relative URLs.
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path) || str_starts_with($path, '//') || str_contains($path, "\n")) {
        return $default;
    }
    return $path;
}

function ensureVehicleAssetDirectory(): string {
    $dir = __DIR__ . '/assets';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function normalizeVehicleImagePath(?string $image): string {
    $image = trim((string) ($image ?? ''));
    if ($image === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $image = str_replace('\\', '/', $image);

    $rewrites = [
        '../backend/assets/' => '/backend/assets/',
        'backend/assets/' => '/backend/assets/',
        '../frontend/images/' => '/frontend/images/',
        'frontend/images/' => '/frontend/images/',
        'images/' => '/frontend/images/',
    ];

    foreach ($rewrites as $prefix => $replacement) {
        if (str_starts_with($image, $prefix)) {
            return $replacement . ltrim(substr($image, strlen($prefix)), '/');
        }
    }

    if (str_starts_with($image, '/backend/assets/') || str_starts_with($image, '/frontend/images/')) {
        return $image;
    }

    return '/' . ltrim($image, '/');
}

function isValidVehicleImageReference(?string $image): bool {
    $image = normalizeVehicleImagePath($image);
    if ($image === '') {
        return true;
    }

    return (bool) preg_match('/\.(jpg|jpeg|png|webp|svg|gif)(\?.*)?$/i', $image);
}

function handleVehicleImageUpload(?array $file, ?string $existingImage = null): string {
    if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return normalizeVehicleImagePath($existingImage);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return normalizeVehicleImagePath($existingImage);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return normalizeVehicleImagePath($existingImage);
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return normalizeVehicleImagePath($existingImage);
    }

    $assetDir = ensureVehicleAssetDirectory();
    $baseName = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($file['name'] ?? 'vehicle', PATHINFO_FILENAME));
    $baseName = trim((string) $baseName, '-.');
    if ($baseName === '') {
        $baseName = 'vehicle';
    }

    $fileName = $baseName . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $assetDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return normalizeVehicleImagePath($existingImage);
    }

    return '/backend/assets/' . $fileName;
}

function getVehicles(PDO $pdo) {
    $stmt = $pdo->query('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id ORDER BY v.id');
    return $stmt->fetchAll();
}

function getVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id WHERE v.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getVehiclesByOwner(PDO $pdo, $owner_id) {
    $stmt = $pdo->prepare('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id WHERE v.owner_id = ? ORDER BY v.id DESC');
    $stmt->execute([$owner_id]);
    return $stmt->fetchAll();
}

function vehicleImagePath(array $vehicle) {
    $image = normalizeVehicleImagePath($vehicle['image'] ?? '');
    return $image !== '' && isValidVehicleImageReference($image)
        ? $image
        : '/frontend/images/placeholder.svg';
}

function createVehicle(PDO $pdo, $owner_id, $make, $model, $year, $price_per_day, $image, $description = null) {
    $stmt = $pdo->prepare('INSERT INTO vehicles (owner_id, make, model, year, price_per_day, image, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'available\', NOW())');
    return $stmt->execute([$owner_id, $make, $model, $year ?: null, $price_per_day, $image, $description]);
}

function updateVehicle(PDO $pdo, $id, $make, $model, $year, $price_per_day, $image, $description = null) {
    $stmt = $pdo->prepare('UPDATE vehicles SET make = ?, model = ?, year = ?, price_per_day = ?, image = ?, description = ? WHERE id = ?');
    return $stmt->execute([$make, $model, $year ?: null, $price_per_day, $image, $description, $id]);
}

function setVehicleStatus(PDO $pdo, $id, $status) {
    $status = $status === 'booked' ? 'booked' : 'available';
    $stmt = $pdo->prepare('UPDATE vehicles SET status = ? WHERE id = ?');
    return $stmt->execute([$status, $id]);
}

function isVehicleBooked(array $vehicle): bool {
    return ($vehicle['status'] ?? 'available') === 'booked';
}

function deleteVehicle(PDO $pdo, $id) {
    $stmt = $pdo->prepare('DELETE FROM vehicles WHERE id = ?');
    return $stmt->execute([$id]);
}

function createUser(PDO $pdo, $name, $email, $password, $phoneNumber, $role = 'user') {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone_number, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$name, $email, $password, $phoneNumber, $role]);
}

function getUserByEmail(PDO $pdo, $email) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function getUserByPhoneNumber(PDO $pdo, $phoneNumber) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE phone_number = ?');
    $stmt->execute([$phoneNumber]);
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

/**
 * Books a vehicle atomically. Only succeeds if the vehicle is currently
 * 'available'; marks it 'booked' in the same transaction to avoid double
 * booking. Returns true on success, false if already booked / missing.
 */
function createBooking(PDO $pdo, $vehicle_id, $user_id, $name, $email, $start_date, $end_date) {
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT status FROM vehicles WHERE id = ? FOR UPDATE');
        $lock->execute([$vehicle_id]);
        $status = $lock->fetchColumn();

        if ($status === false || $status === 'booked') {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare('INSERT INTO bookings (vehicle_id, user_id, name, email, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, \'active\', NOW())');
        $stmt->execute([$vehicle_id, $user_id ?: null, $name, $email, $start_date, $end_date]);

        $pdo->prepare('UPDATE vehicles SET status = \'booked\' WHERE id = ?')->execute([$vehicle_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Cancels an active booking and frees its vehicle, atomically.
 */
function cancelBooking(PDO $pdo, $booking_id) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT vehicle_id, status FROM bookings WHERE id = ? FOR UPDATE');
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();

        if (!$booking || $booking['status'] === 'cancelled') {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare('UPDATE bookings SET status = \'cancelled\' WHERE id = ?')->execute([$booking_id]);
        $pdo->prepare('UPDATE vehicles SET status = \'available\' WHERE id = ?')->execute([$booking['vehicle_id']]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getBookings(PDO $pdo) {
    $stmt = $pdo->query('SELECT b.*, v.make, v.model, u.name AS user_name, u.email AS user_email FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC');
    return $stmt->fetchAll();
}

function getBookingsByUser(PDO $pdo, int $user_id) {
    $stmt = $pdo->prepare('SELECT b.*, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
