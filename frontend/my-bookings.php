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

$totalBookings = count($bookings);
$activeBookings = 0;
$cancelledBookings = 0;
$totalSpent = 0.0;

foreach ($bookings as $booking) {
    $status = strtolower((string) ($booking['status'] ?? 'active'));
    if ($status === 'cancelled') {
        $cancelledBookings++;
    } else {
        $activeBookings++;
    }
    $totalSpent += (float) ($booking['total_amount'] ?? 0);
}

function formatBookingDate(string $dateValue): string
{
    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
        return $dateValue;
    }
    return date('M d, Y', $timestamp);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Bookings - Vehicle Rental</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <div>
      <h1>My Bookings</h1>
      <p class="subtitle">Track your reserved vehicles, review trip details, and cancel upcoming plans when needed.</p>
    </div>
    <nav>
      <a href="index.php">Home</a>
      <a href="index.php#fleet">Browse Fleet</a>
      <form class="header-search" method="get" action="<?php echo htmlspecialchars(appBaseUrl() . '/frontend/search.php'); ?>" role="search">
        <input
          type="search"
          name="q"
          placeholder="Search car name"
          aria-label="Search vehicles"
        >
        <button type="submit">Search</button>
      </form>
      <a href="logout.php">Logout</a>
    </nav>
  </header>
  <main>
    <section class="bookings-hero">
      <div>
        <span class="bookings-hero-badge">Booking Center</span>
        <h2>Everything you booked, in one place.</h2>
        <p>Review your active trips, inspect rates, and manage cancellations without digging through old messages.</p>
      </div>
      <div class="bookings-hero-actions">
        <a class="btn btn-primary" href="index.php#fleet">Book another car</a>
        <a class="btn btn-outline" href="index.php">View home</a>
      </div>
    </section>

    <section class="bookings-dashboard">
      <div class="bookings-summary-grid">
        <article class="bookings-summary-card">
          <p class="bookings-summary-label">Total bookings</p>
          <p class="bookings-summary-value"><?php echo htmlspecialchars((string) $totalBookings); ?></p>
        </article>
        <article class="bookings-summary-card">
          <p class="bookings-summary-label">Active</p>
          <p class="bookings-summary-value bookings-summary-value--active"><?php echo htmlspecialchars((string) $activeBookings); ?></p>
        </article>
        <article class="bookings-summary-card">
          <p class="bookings-summary-label">Cancelled</p>
          <p class="bookings-summary-value bookings-summary-value--cancelled"><?php echo htmlspecialchars((string) $cancelledBookings); ?></p>
        </article>
        <article class="bookings-summary-card">
          <p class="bookings-summary-label">Total spent</p>
          <p class="bookings-summary-value">NPR <?php echo htmlspecialchars(number_format($totalSpent, 0)); ?></p>
        </article>
      </div>

      <?php if ($message): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="message message-error"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>

      <?php if (empty($bookings)): ?>
        <div class="bookings-empty">
          <h2>No bookings yet</h2>
          <p>Start by choosing a vehicle from our available fleet.</p>
          <a class="btn btn-primary" href="index.php#fleet">Browse vehicles</a>
        </div>
      <?php else: ?>
        <div class="bookings-list">
          <?php foreach ($bookings as $b): ?>
            <?php
              $status = strtolower((string) ($b['status'] ?? 'active'));
              $isCancelled = $status === 'cancelled';
              $driveModeLabel = (($b['drive_mode'] ?? 'self_drive') === 'with_driver') ? 'With driver' : 'Self-drive';
            ?>
            <article class="booking-card">
              <div class="booking-card-accent <?php echo $isCancelled ? 'booking-card-accent--cancelled' : 'booking-card-accent--active'; ?>"></div>
              <div class="booking-card-head">
                <div>
                  <h2><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></h2>
                  <p class="booking-card-subtitle">
                    <?php echo htmlspecialchars($driveModeLabel); ?> booking
                  </p>
                </div>
                <span class="booking-status <?php echo $isCancelled ? 'booking-status--cancelled' : 'booking-status--active'; ?>">
                  <?php echo $isCancelled ? 'Cancelled' : 'Active'; ?>
                </span>
              </div>

              <div class="booking-card-meta">
                <div class="booking-meta-item">
                  <span>Trip dates</span>
                  <strong><?php echo htmlspecialchars(formatBookingDate((string) ($b['start_date'] ?? ''))); ?> to <?php echo htmlspecialchars(formatBookingDate((string) ($b['end_date'] ?? ''))); ?></strong>
                </div>
                <div class="booking-meta-item">
                  <span>Booked on</span>
                  <strong><?php echo htmlspecialchars(formatBookingDate((string) ($b['created_at'] ?? ''))); ?></strong>
                </div>
                <div class="booking-meta-item">
                  <span>Mode</span>
                  <strong><?php echo htmlspecialchars($driveModeLabel); ?></strong>
                </div>
                <div class="booking-meta-item">
                  <span>Rate</span>
                  <strong>NPR <?php echo htmlspecialchars(number_format((float) ($b['daily_rate'] ?? 0), 0)); ?>/day</strong>
                </div>
                <div class="booking-meta-item booking-meta-item--total">
                  <span>Total</span>
                  <strong>NPR <?php echo htmlspecialchars(number_format((float) ($b['total_amount'] ?? 0), 0)); ?></strong>
                </div>
              </div>

              <p class="booking-card-user">
                <?php echo htmlspecialchars($b['name']); ?>
                &middot;
                <?php echo htmlspecialchars($b['email']); ?>
              </p>

              <?php if (!$isCancelled): ?>
                <form class="booking-card-actions" method="post" action="my-bookings.php" onsubmit="return confirm('Cancel this booking?');">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="cancel_booking" value="<?php echo (int) $b['id']; ?>">
                  <button type="submit" class="booking-cancel-btn">Cancel booking</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
