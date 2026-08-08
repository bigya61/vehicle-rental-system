<?php
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    $_SESSION['redirect_after_login'] = '../backend/admin-bookings.php';
    header('Location: ../frontend/login.php');
    exit;
}

$flash = '';
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cancel_booking'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $bookingId = (int) $_POST['cancel_booking'];
    $bookingToCancel = $bookingId > 0 ? getBookingById($pdo, $bookingId) : null;
    if ($bookingToCancel && bookingEffectiveStatus($bookingToCancel) === 'completed') {
        $_SESSION['flash'] = 'This booking has already completed and cannot be cancelled.';
    } elseif ($bookingId > 0 && cancelBooking($pdo, $bookingId)) {
        $_SESSION['flash'] = 'Booking cancelled and vehicle freed.';
    }
    header('Location: admin-bookings.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'active', 'completed', 'cancelled'], true)) {
    $statusFilter = 'all';
}
$vehicleFilter = (int) ($_GET['vehicle_id'] ?? 0);

$todayDate = new DateTimeImmutable('today');
$allBookings = getBookings($pdo);

$vehicleOptions = [];
foreach ($allBookings as $booking) {
    $vid = (int) $booking['vehicle_id'];
    if (!isset($vehicleOptions[$vid])) {
        $vehicleOptions[$vid] = trim($booking['make'] . ' ' . $booking['model']);
    }
}
asort($vehicleOptions, SORT_STRING | SORT_FLAG_CASE);

$bookings = array_values(array_filter($allBookings, function ($booking) use ($statusFilter, $vehicleFilter, $todayDate) {
    if ($vehicleFilter > 0 && (int) $booking['vehicle_id'] !== $vehicleFilter) {
        return false;
    }
    return $statusFilter === 'all' || bookingEffectiveStatus($booking, $todayDate) === $statusFilter;
}));

$totalCount = count($allBookings);
$activeCount = 0;
$completedCount = 0;
$cancelledCount = 0;
$totalRevenue = 0.0;
$remainingPayment = 0.0;
foreach ($allBookings as $booking) {
    $amount = (float) ($booking['total_amount'] ?? 0);
    switch (bookingEffectiveStatus($booking, $todayDate)) {
        case 'completed':
            $completedCount++;
            $totalRevenue += $amount;
            break;
        case 'cancelled':
            $cancelledCount++;
            break;
        default:
            $activeCount++;
            $totalRevenue += $amount;
            $remainingPayment += $amount;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Bookings - Vehicle Rental</title>
  <link rel="stylesheet" href="../frontend/css/styles.css">
  <style>
    body { margin:0; font-family:Arial,Helvetica,sans-serif; background:#071126; color:#e5e7eb; }
    .admin-header { display:flex; justify-content:space-between; align-items:center; gap:20px; padding:20px 40px; background:#111827; border-bottom:1px solid #1f2937; }
    .admin-header h1 { margin:0; font-size:28px; }
    .admin-header nav a { color:#9ca3af; text-decoration:none; margin-left:20px; font-weight:700; padding-bottom:4px; border-bottom:2px solid transparent; }
    .admin-header nav a:hover { color:#f59e0b; }
    .admin-header nav a.active { color:#f59e0b; border-bottom-color:#f59e0b; }
    .dashboard { padding:30px 10px; }
    .stats { max-width:99vw; margin:0 auto 30px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .stat { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:20px; }
    .stat h2 { margin:0 0 8px; color:#9ca3af; font-size:14px; text-transform:uppercase; }
    .stat p { margin:0; color:#f8fafc; font-size:32px; font-weight:700; }
    .message, .error { max-width:99vw; margin:0 auto 20px; padding:16px 20px; border-radius:12px; }
    .message { background:#134e4a; color:#d1fae5; }
    .error { background:#7f1d1d; color:#fee2e2; }
    .table-container { max-width:99vw; margin:0 auto 30px; background:#111827; border-radius:12px; border:1px solid #1f2937; overflow:hidden; }
    .table-header { padding:24px 30px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
    .table-header h2 { margin:0; }
    .status-tabs { display:flex; gap:8px; padding:0 30px; margin-top:16px; align-items:center; flex-wrap:wrap; }
    .status-tabs a { color:#9ca3af; text-decoration:none; padding:8px 16px; border-radius:999px; border:1px solid #1f2937; font-size:13px; font-weight:700; }
    .status-tabs a.active { background:#f59e0b; color:#111827; border-color:#f59e0b; }
    .status-tabs a:hover:not(.active) { border-color:#475569; color:#e2e8f0; }
    .vehicle-filter { margin-left:auto; }
    .vehicle-filter select { background:#0b1220; color:#e5e7eb; border:1px solid #1f2937; border-radius:999px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
    .vehicle-filter select:hover { border-color:#475569; }
    .table-scroll { margin-top:20px; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    thead { background:#1f2937; }
    th, td { padding:12px 10px; text-align:left; border-bottom:1px solid #1f2937; white-space:nowrap; font-size:13px; }
    th { color:#fbbf24; font-size:12px; letter-spacing:.02em; text-transform:uppercase; }
    td { color:#cbd5e1; }
    .empty { padding:24px 30px; color:#9ca3af; }
    .delete-link { color:#f87171; }
    .page-footer { padding:20px 40px; text-align:center; color:#9ca3af; }
    @media(max-width:1400px) {
      th, td { padding:10px 10px; font-size:12px; }
    }
    @media(max-width:1100px) {
      .admin-header { flex-direction:column; align-items:flex-start; }
      .admin-header nav a { margin:0 16px 0 0; }
      .dashboard { padding:24px 16px; }
      .stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:700px) {
      .stats { grid-template-columns:1fr; }
      .table-header { padding:20px 16px 0; }
      .status-tabs { padding:0 16px; }
      .vehicle-filter { margin-left:0; width:100%; }
      .vehicle-filter select { width:100%; }
      th, td { padding:8px 8px; font-size:11px; }
    }
  </style>
</head>
<body>
  <header class="admin-header">
    <h1>Manage Bookings</h1>
    <nav>
      <a href="admin.php">Dashboard</a>
      <a href="admin-vehicles.php">Vehicles</a>
      <a href="admin-bookings.php" class="active">Bookings</a>
      <a href="../frontend/index.php">Home</a>
      <a href="../frontend/logout.php">Logout</a>
    </nav>
  </header>

  <main class="dashboard">
    <?php if ($flash): ?>
      <div class="message"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <section class="stats" aria-label="Booking totals">
      <div class="stat">
        <h2>Total Revenue</h2>
        <p>NPR <?php echo htmlspecialchars(number_format($totalRevenue, 0)); ?></p>
      </div>
      <div class="stat">
        <h2>Vehicles Active</h2>
        <p><?php echo $activeCount; ?></p>
      </div>
      <div class="stat">
        <h2>Payment Remaining</h2>
        <p>NPR <?php echo htmlspecialchars(number_format($remainingPayment, 0)); ?></p>
      </div>
    </section>

    <section class="table-container">
      <div class="table-header">
        <h2>Bookings</h2>
      </div>
      <div class="status-tabs">
        <a href="admin-bookings.php?status=all&vehicle_id=<?php echo $vehicleFilter; ?>" class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All (<?php echo $totalCount; ?>)</a>
        <a href="admin-bookings.php?status=active&vehicle_id=<?php echo $vehicleFilter; ?>" class="<?php echo $statusFilter === 'active' ? 'active' : ''; ?>">Active (<?php echo $activeCount; ?>)</a>
        <a href="admin-bookings.php?status=completed&vehicle_id=<?php echo $vehicleFilter; ?>" class="<?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">Completed (<?php echo $completedCount; ?>)</a>
        <a href="admin-bookings.php?status=cancelled&vehicle_id=<?php echo $vehicleFilter; ?>" class="<?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled (<?php echo $cancelledCount; ?>)</a>
        <form method="get" action="admin-bookings.php" class="vehicle-filter">
          <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
          <select name="vehicle_id" onchange="this.form.submit()">
            <option value="0">All vehicles</option>
            <?php foreach ($vehicleOptions as $vid => $label): ?>
              <option value="<?php echo $vid; ?>" <?php echo $vehicleFilter === $vid ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if (empty($bookings)): ?>
        <div class="empty">No bookings found for this filter.</div>
      <?php else: ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Drive mode</th>
                <th>Rate / day</th>
                <th>Total</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <?php $effectiveStatus = bookingEffectiveStatus($booking, $todayDate); ?>
                <tr>
                  <td>
                    <?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?>
                    <?php if (!empty($booking['vehicle_deleted'])): ?>
                      <span style="color:#9ca3af;font-size:12px;">(deleted)</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($booking['name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['user_phone_number'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars(($booking['drive_mode'] ?? 'self_drive') === 'with_driver' ? 'With driver' : 'Self-drive'); ?></td>
                  <td>NPR <?php echo htmlspecialchars(number_format((float) ($booking['daily_rate'] ?? 0), 0)); ?></td>
                  <td>NPR <?php echo htmlspecialchars(number_format((float) ($booking['total_amount'] ?? 0), 0)); ?></td>
                  <td><?php echo htmlspecialchars($booking['start_date']); ?></td>
                  <td><?php echo htmlspecialchars($booking['end_date']); ?></td>
                  <td>
                    <?php if ($effectiveStatus === 'cancelled'): ?>
                      <span style="color:#9ca3af;">Cancelled</span>
                    <?php elseif ($effectiveStatus === 'completed'): ?>
                      <span style="color:#60a5fa;font-weight:700;">Completed</span>
                    <?php else: ?>
                      <span style="color:#34d399;font-weight:700;">Active</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars(substr((string) $booking['created_at'], 0, 10)); ?></td>
                  <td>
                    <?php if ($effectiveStatus === 'active'): ?>
                      <form method="post" action="admin-bookings.php" style="display:inline;" onsubmit="return confirm('Cancel this booking and free the vehicle?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="cancel_booking" value="<?php echo (int) $booking['id']; ?>">
                        <button type="submit" class="delete-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Cancel</button>
                      </form>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
