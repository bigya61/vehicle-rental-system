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

try {
    syncVehicleAvailability($pdo);
} catch (Throwable $e) {
    error_log('[booking-sync] failed to refresh vehicle availability: ' . $e->getMessage());
}

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

    // Local dev: keep uploads resilient even when Apache/PHP runs as a
    // different OS user than the repository owner.
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }

    return $dir;
}

function pathPermissionSummary(string $path): string {
    clearstatcache(true, $path);
    $permsRaw = @fileperms($path);
    $perms = $permsRaw !== false ? substr(sprintf('%o', $permsRaw), -4) : 'unknown';
    $ownerId = @fileowner($path);
    $groupId = @filegroup($path);
    return 'perms=' . $perms . ', owner_uid=' . ($ownerId === false ? 'unknown' : (string) $ownerId) . ', group_gid=' . ($groupId === false ? 'unknown' : (string) $groupId);
}

function logImageIssue(string $message, array $context = []): void {
    $parts = [];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $parts[] = $key . '=' . var_export($value, true);
        } else {
            $parts[] = $key . '=' . json_encode($value, JSON_UNESCAPED_SLASHES);
        }
    }
    $suffix = $parts ? ' | ' . implode(', ', $parts) : '';
    error_log('[vehicle-image] ' . $message . $suffix);
}

function parsePhpSizeToBytes(string $value): int {
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $last = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($last) {
        case 'g':
            $number *= 1024;
            // fallthrough
        case 'm':
            $number *= 1024;
            // fallthrough
        case 'k':
            $number *= 1024;
            break;
    }

    return (int) round($number);
}

function effectivePhpUploadMaxBytes(): int {
    $uploadMax = parsePhpSizeToBytes((string) ini_get('upload_max_filesize'));
    $postMax = parsePhpSizeToBytes((string) ini_get('post_max_size'));

    if ($uploadMax <= 0 && $postMax <= 0) {
        return 0;
    }
    if ($uploadMax <= 0) {
        return $postMax;
    }
    if ($postMax <= 0) {
        return $uploadMax;
    }
    return min($uploadMax, $postMax);
}

function vehicleImageUploadLimitBytes(): int {
    $appLimit = 5 * 1024 * 1024;
    $phpLimit = effectivePhpUploadMaxBytes();
    if ($phpLimit <= 0) {
        return $appLimit;
    }
    return min($appLimit, $phpLimit);
}

function formatBytesHuman(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    if ($bytes >= 1024 * 1024) {
        $mb = $bytes / (1024 * 1024);
        return rtrim(rtrim(number_format($mb, 2, '.', ''), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        $kb = $bytes / 1024;
        return rtrim(rtrim(number_format($kb, 2, '.', ''), '0'), '.') . ' KB';
    }
    return $bytes . ' B';
}

function phpUploadErrorMessage(int $errorCode): string {
    switch ($errorCode) {
        case UPLOAD_ERR_OK:
            return 'No upload error.';
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the server upload limit (' . formatBytesHuman(vehicleImageUploadLimitBytes()) . ').';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the form upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary upload folder on server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write uploaded file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload.';
        default:
            return 'Unknown upload error (code ' . $errorCode . ').';
    }
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

function normalizeTransmission(?string $transmission): string {
    $value = strtolower(trim((string) ($transmission ?? '')));
    return $value === 'manual' ? 'manual' : 'automatic';
}

function normalizeDriveMode(?string $driveMode): string {
    $value = strtolower(trim((string) ($driveMode ?? '')));
    return $value === 'with_driver' ? 'with_driver' : 'self_drive';
}

function isValidVehicleImageReference(?string $image): bool {
    $image = normalizeVehicleImagePath($image);
    if ($image === '') {
        return true;
    }

    return (bool) preg_match('/\.(jpg|jpeg|png|webp|svg|gif)(\?.*)?$/i', $image);
}

function handleVehicleImageUpload(?array $file, ?string $existingImage = null, ?string &$failureReason = null): string {
    $failureReason = null;

    if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $failureReason = 'No uploaded file was received by the server.';
        logImageIssue('upload skipped: no uploaded file present', [
            'name' => $file['name'] ?? null,
            'tmp_name' => $file['tmp_name'] ?? null,
            'error' => $file['error'] ?? null,
            'failure_reason' => $failureReason,
        ]);
        return normalizeVehicleImagePath($existingImage);
    }

    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $failureReason = phpUploadErrorMessage($uploadError);
        logImageIssue('upload failed: php upload error', [
            'name' => $file['name'] ?? null,
            'error' => $uploadError,
            'error_message' => $failureReason,
            'size' => $file['size'] ?? null,
            'max_bytes' => vehicleImageUploadLimitBytes(),
        ]);
        return normalizeVehicleImagePath($existingImage);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        $failureReason = 'Unsupported file extension: .' . ($extension !== '' ? $extension : 'unknown') . '. Allowed: jpg, jpeg, png, webp, svg, gif.';
        logImageIssue('upload failed: extension not allowed', [
            'name' => $file['name'] ?? null,
            'extension' => $extension,
            'failure_reason' => $failureReason,
        ]);
        return normalizeVehicleImagePath($existingImage);
    }

    $maxBytes = vehicleImageUploadLimitBytes();
    if (($file['size'] ?? 0) > $maxBytes) {
        $failureReason = 'File is too large (' . formatBytesHuman((int) ($file['size'] ?? 0)) . '). Maximum allowed is ' . formatBytesHuman($maxBytes) . '.';
        logImageIssue('upload failed: file too large', [
            'name' => $file['name'] ?? null,
            'size' => $file['size'] ?? null,
            'max_bytes' => $maxBytes,
            'failure_reason' => $failureReason,
        ]);
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

    if (!is_writable($assetDir)) {
        $failureReason = 'Assets directory is not writable. ' . pathPermissionSummary($assetDir);
        logImageIssue('upload failed: assets directory not writable', [
            'asset_dir' => $assetDir,
            'target' => $targetPath,
            'failure_reason' => $failureReason,
        ]);
        return normalizeVehicleImagePath($existingImage);
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $failureReason = 'Server failed while moving uploaded file into assets directory. ' . pathPermissionSummary($assetDir);
        logImageIssue('upload failed: move_uploaded_file returned false', [
            'name' => $file['name'] ?? null,
            'tmp_name' => $file['tmp_name'] ?? null,
            'target' => $targetPath,
            'asset_dir' => $assetDir,
            'failure_reason' => $failureReason,
        ]);
        return normalizeVehicleImagePath($existingImage);
    }

    logImageIssue('upload success', [
        'name' => $file['name'] ?? null,
        'stored_as' => '/backend/assets/' . $fileName,
        'size' => $file['size'] ?? null,
    ]);

    return '/backend/assets/' . $fileName;
}

function getVehicles(PDO $pdo) {
    $stmt = $pdo->query('SELECT v.*, u.name AS owner_name, u.email AS owner_email FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id ORDER BY v.id');
    return $stmt->fetchAll();
}

function searchVehicles(PDO $pdo, string $query) {
    $query = trim(preg_replace('/\s+/', ' ', $query));
    if ($query === '') {
        return [];
    }

    $searchTerm = '%' . strtolower($query) . '%';

    $stmt = $pdo->prepare(
        'SELECT v.*, u.name AS owner_name, u.email AS owner_email
         FROM vehicles v
         LEFT JOIN users u ON v.owner_id = u.id
         WHERE LOWER(TRIM(CONCAT(COALESCE(v.make, \'\'), \' \', COALESCE(v.model, \'\')))) LIKE :term
         ORDER BY v.id'
    );
    $stmt->execute(['term' => $searchTerm]);
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

/**
 * Returns the base URL prefix so that root-relative asset paths work whether
 * the project is served from the domain root (PHP dev server) or a
 * subdirectory (XAMPP Apache at /rentalsystem/).
 * External http(s) URLs are returned as-is and must NOT be prefixed.
 */
function appBaseUrl(): string {
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $projectRoot = rtrim(str_replace('\\', '/', (string) realpath(__DIR__ . '/../')), '/');
    if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
        $base = substr($projectRoot, strlen($docRoot));
    } else {
        $base = '';
    }
    return $base;
}

function vehicleImagePath(array $vehicle): string {
    $rawImage = $vehicle['image'] ?? '';
    $image = normalizeVehicleImagePath($rawImage);
    if ($image === '' || !isValidVehicleImageReference($image)) {
        logImageIssue('invalid or empty image path; using placeholder', [
            'vehicle_id' => $vehicle['id'] ?? null,
            'raw_image' => $rawImage,
            'normalized_image' => $image,
        ]);
        $image = '/frontend/images/placeholder.svg';
    }
    // External URLs need no prefix.
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $localPath = __DIR__ . '/../' . ltrim($image, '/');
    if (!is_file($localPath)) {
        logImageIssue('local image file missing; using placeholder', [
            'vehicle_id' => $vehicle['id'] ?? null,
            'image' => $image,
            'expected_file' => $localPath,
        ]);
        return appBaseUrl() . '/frontend/images/placeholder.svg';
    }

    return appBaseUrl() . $image;
}

function createVehicle(PDO $pdo, $owner_id, $make, $model, $year, $transmission, $price_per_day, $image, $description = null) {
    $transmission = normalizeTransmission($transmission);
    $stmt = $pdo->prepare('INSERT INTO vehicles (owner_id, make, model, year, transmission, price_per_day, image, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'available\', NOW())');
    return $stmt->execute([$owner_id, $make, $model, $year ?: null, $transmission, $price_per_day, $image, $description]);
}

function updateVehicle(PDO $pdo, $id, $make, $model, $year, $transmission, $price_per_day, $image, $description = null) {
    $transmission = normalizeTransmission($transmission);
    $stmt = $pdo->prepare('UPDATE vehicles SET make = ?, model = ?, year = ?, transmission = ?, price_per_day = ?, image = ?, description = ? WHERE id = ?');
    return $stmt->execute([$make, $model, $year ?: null, $transmission, $price_per_day, $image, $description, $id]);
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
 * Keeps vehicle availability aligned with booking dates.
 * A vehicle stays booked while it has at least one active booking whose
 * end_date is today or in the future. Once the booking expires, the vehicle
 * becomes available automatically.
 */
function syncVehicleAvailability(PDO $pdo): void {
    $pdo->exec(
        "UPDATE vehicles v
        LEFT JOIN (
            SELECT vehicle_id
            FROM bookings
            WHERE status = 'active' AND end_date >= CURDATE()
            GROUP BY vehicle_id
        ) b ON b.vehicle_id = v.id
        SET v.status = CASE
            WHEN b.vehicle_id IS NULL THEN 'available'
            ELSE 'booked'
        END"
    );
}

/**
 * Books a vehicle atomically. Only succeeds if the vehicle is currently
 * 'available'; marks it 'booked' in the same transaction to avoid double
 * booking. Returns true on success, false if already booked / missing.
 */
function createBooking(PDO $pdo, $vehicle_id, $user_id, $name, $email, $start_date, $end_date, $drive_mode = 'self_drive', $daily_rate = null, $total_amount = null) {
    syncVehicleAvailability($pdo);

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT status, price_per_day FROM vehicles WHERE id = ? FOR UPDATE');
        $lock->execute([$vehicle_id]);
        $vehicle = $lock->fetch();
        $status = $vehicle['status'] ?? false;

        if ($status === false || $status === 'booked') {
            $pdo->rollBack();
            return false;
        }

        $driveMode = normalizeDriveMode($drive_mode);
        $driverSurcharge = $driveMode === 'with_driver' ? 2000.0 : 0.0;
        $basePricePerDay = (float) ($vehicle['price_per_day'] ?? 0);
        $computedDailyRate = $basePricePerDay + $driverSurcharge;

        $startDateObj = DateTimeImmutable::createFromFormat('Y-m-d', (string) $start_date);
        $endDateObj = DateTimeImmutable::createFromFormat('Y-m-d', (string) $end_date);
        $durationDays = 1;
        if ($startDateObj && $endDateObj) {
            $durationDays = max((int) $startDateObj->diff($endDateObj)->format('%a') + 1, 1);
        }

        $finalDailyRate = $daily_rate !== null ? max((float) $daily_rate, 0) : $computedDailyRate;
        $finalTotalAmount = $total_amount !== null ? max((float) $total_amount, 0) : ($finalDailyRate * $durationDays);

        $stmt = $pdo->prepare('INSERT INTO bookings (vehicle_id, user_id, name, email, start_date, end_date, drive_mode, daily_rate, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', NOW())');
        $stmt->execute([$vehicle_id, $user_id ?: null, $name, $email, $start_date, $end_date, $driveMode, $finalDailyRate, $finalTotalAmount]);

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
    $stmt = $pdo->query('SELECT b.*, v.make, v.model, u.name AS user_name, u.email AS user_email, u.phone_number AS user_phone_number FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC');
    return $stmt->fetchAll();
}

function getBookingsByUser(PDO $pdo, int $user_id) {
    $stmt = $pdo->prepare('SELECT b.*, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
