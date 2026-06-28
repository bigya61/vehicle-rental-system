<?php
require_once __DIR__ . '/../backend/functions.php';

if (empty($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'my-bookings.php';
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cancel_booking'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $bookingId = (int) $_POST['cancel_booking'];
    if ($bookingId > 0) {
        $stmt = $pdo->prepare('SELECT user_id FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $ownerId = (int) $stmt->fetchColumn();
        if ($ownerId === (int) $_SESSION['user']['id']) {
            if (cancelBooking($pdo, $bookingId)) {
                $message = 'Booking cancelled successfully.';
            } else {
                $error = 'Unable to cancel booking.';
            }
        } else {
            $error = 'Unauthorized to cancel this booking.';
        }
    }
}

$bookings = getBookingsByUser($pdo, (int) $_SESSION['user']['id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Bookings - Vehicle Rental</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .bookings { max-width:900px;margin:2rem auto;background:#fff;padding:20px;border-radius:8px; }
    .booking { border-bottom:1px solid #eee;padding:12px 0; }
    .booking:last-child { border-bottom:none; }
    .booking h4 { margin:0 0 6px; }
    .small-muted { color:#6b7280;font-size:13px; }
    .btn-cancel { background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer; }
  </style>
</head>
<body>
  <header class="page-header">
    <h1>My Bookings</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="../backend/logout.php">Logout</a>
    </nav>
  </header>
  <main>
    <div class="bookings">
      <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (empty($bookings)): ?>
        <p>You have no bookings.</p>
      <?php else: ?>
        <?php foreach ($bookings as $b): ?>
          <div class="booking">
            <h4><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></h4>
            <p class="small-muted">From <?php echo htmlspecialchars($b['start_date']); ?> to <?php echo htmlspecialchars($b['end_date']); ?> — created <?php echo htmlspecialchars($b['created_at']); ?></p>
            <p><?php echo htmlspecialchars($b['name']); ?> &middot; <?php echo htmlspecialchars($b['email']); ?></p>
            <?php if (($b['status'] ?? 'active') !== 'cancelled'): ?>
              <form method="post" action="my-bookings.php" onsubmit="return confirm('Cancel this booking?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="cancel_booking" value="<?php echo (int) $b['id']; ?>">
                <button type="submit" class="btn-cancel">Cancel booking</button>
              </form>
            <?php else: ?>
              <p class="small-muted">Cancelled</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
