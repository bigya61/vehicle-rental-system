<?php
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    $_SESSION['redirect_after_login'] = '../backend/admin.php';
    header('Location: ../frontend/login.php');
    exit;
}

$flash = '';
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$vehicles = getVehicles($pdo);
$bookings = getBookings($pdo);

$availableVehicles = 0;
foreach ($vehicles as $vehicle) {
    if (!isVehicleBooked($vehicle)) {
        $availableVehicles++;
    }
}

$activeBookings = 0;
foreach ($bookings as $booking) {
    if (($booking['status'] ?? 'active') !== 'cancelled') {
        $activeBookings++;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Vehicle Rental</title>
  <link rel="stylesheet" href="../frontend/css/styles.css">
  <style>
    body { margin:0; font-family:Arial,Helvetica,sans-serif; background:#071126; color:#e5e7eb; }
    .admin-header { display:flex; justify-content:space-between; align-items:center; gap:20px; padding:20px 40px; background:#111827; border-bottom:1px solid #1f2937; }
    .admin-header h1 { margin:0; font-size:28px; }
    .admin-header nav a { color:#9ca3af; text-decoration:none; margin-left:20px; font-weight:700; padding-bottom:4px; border-bottom:2px solid transparent; }
    .admin-header nav a:hover { color:#f59e0b; }
    .admin-header nav a.active { color:#f59e0b; border-bottom-color:#f59e0b; }
    .dashboard { padding:30px 40px; }
    .stats { max-width:1100px; margin:0 auto 30px; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
    .stat { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:20px; }
    .stat h2 { margin:0 0 8px; color:#9ca3af; font-size:14px; text-transform:uppercase; }
    .stat p { margin:0; color:#f8fafc; font-size:32px; font-weight:700; }
    .message, .error { max-width:1100px; margin:0 auto 20px; padding:16px 20px; border-radius:12px; }
    .message { background:#134e4a; color:#d1fae5; }
    .error { background:#7f1d1d; color:#fee2e2; }
    .nav-cards { max-width:1100px; margin:0 auto; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
    .nav-card { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:30px; text-decoration:none; display:block; transition:border-color .2s ease, transform .2s ease; }
    .nav-card:hover { border-color:#f59e0b; transform:translateY(-2px); }
    .nav-card h2 { margin:0 0 10px; color:#f8fafc; font-size:22px; }
    .nav-card p { margin:0 0 16px; color:#9ca3af; }
    .nav-card span.btn { display:inline-block; padding:10px 18px; border-radius:8px; background:#f59e0b; color:#111827; font-weight:700; }
    .page-footer { padding:20px 40px; text-align:center; color:#9ca3af; }
    @media(max-width:900px) {
      .admin-header { flex-direction:column; align-items:flex-start; }
      .admin-header nav a { margin:0 16px 0 0; }
      .dashboard { padding:24px 16px; }
      .stats, .nav-cards { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
  <header class="admin-header">
    <h1>Admin Dashboard</h1>
    <nav>
      <a href="admin.php" class="active">Dashboard</a>
      <a href="admin-vehicles.php">Vehicles</a>
      <a href="admin-bookings.php">Bookings</a>
      <a href="../frontend/index.php">Home</a>
      <a href="../frontend/logout.php">Logout</a>
    </nav>
  </header>

  <main class="dashboard">
    <?php if ($flash): ?>
      <div class="message"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <section class="stats" aria-label="Dashboard totals">
      <div class="stat">
        <h2>Vehicles</h2>
        <p><?php echo count($vehicles); ?></p>
      </div>
      <div class="stat">
        <h2>Available</h2>
        <p><?php echo $availableVehicles; ?></p>
      </div>
      <div class="stat">
        <h2>Bookings</h2>
        <p><?php echo count($bookings); ?></p>
      </div>
      <div class="stat">
        <h2>Active Bookings</h2>
        <p><?php echo $activeBookings; ?></p>
      </div>
    </section>

    <section class="nav-cards">
      <a class="nav-card" href="admin-vehicles.php">
        <h2>Manage Vehicles</h2>
        <p>Add new vehicles, edit details, upload images, and manage the current fleet.</p>
        <span class="btn">Go to Vehicles</span>
      </a>
      <a class="nav-card" href="admin-bookings.php">
        <h2>Manage Bookings</h2>
        <p>Review bookings, check statuses, and cancel active reservations.</p>
        <span class="btn">Go to Bookings</span>
      </a>
    </section>
  </main>

  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
