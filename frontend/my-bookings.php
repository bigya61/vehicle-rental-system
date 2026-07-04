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
      $stmt = $pdo->prepare('SELECT id, user_id, status, created_at FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
      $bookingRow = $stmt->fetch();
      $ownerId = (int) ($bookingRow['user_id'] ?? 0);
      if ($ownerId === (int) $_SESSION['user']['id']) {
        if (!canCustomerCancelBooking((array) $bookingRow)) {
          $error = 'Cancellation is allowed only within 24 hours from booking time.';
        } elseif (cancelBooking($pdo, $bookingId)) {
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
$todayDate = new DateTimeImmutable('today');

function bookingTimelineStatus(array $booking, ?DateTimeImmutable $today = null): string
{
  $rawStatus = strtolower((string) ($booking['status'] ?? 'active'));
  if ($rawStatus === 'cancelled') {
    return 'cancelled';
  }

  $startDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($booking['start_date'] ?? ''));
  $endDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($booking['end_date'] ?? ''));
  if (!$startDate || !$endDate) {
    return 'running';
  }

  $todayRef = $today ?? new DateTimeImmutable('today');
  $start = $startDate->setTime(0, 0, 0);
  $end = $endDate->setTime(0, 0, 0);

  if ($end < $todayRef) {
    return 'completed';
  }
  if ($start <= $todayRef && $end >= $todayRef) {
    return 'running';
  }

  return 'upcoming';
}

$totalBookings = count($bookings);
$runningBookings = 0;
$completedBookings = 0;
$cancelledBookings = 0;
$totalSpent = 0.0;

foreach ($bookings as $booking) {
  $timelineStatus = bookingTimelineStatus((array) $booking, $todayDate);
  if ($timelineStatus === 'cancelled') {
    $cancelledBookings++;
  } elseif ($timelineStatus === 'completed') {
    $completedBookings++;
  } elseif ($timelineStatus === 'running') {
    $runningBookings++;
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
  <style>
    .booking-status--running {
      background: #16a34a;
      color: #ecfdf5;
    }
    .booking-status--completed {
      background: #1d4ed8;
      color: #dbeafe;
    }
    .bookings-history {
      margin-top: 24px;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 14px;
      padding: 16px;
    }
    .bookings-history h3 {
      margin: 0 0 12px;
      color: #e2e8f0;
    }
    .bookings-history-table {
      width: 100%;
      border-collapse: collapse;
      color: #cbd5e1;
      font-size: 0.95rem;
    }
    .bookings-history-table th,
    .bookings-history-table td {
      border-bottom: 1px solid #334155;
      padding: 10px 8px;
      text-align: left;
      vertical-align: top;
    }
    .bookings-history-table th {
      color: #f8fafc;
      font-weight: 600;
    }
    .bookings-history-status {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: capitalize;
    }
    .bookings-history-status--running {
      background: #166534;
      color: #dcfce7;
    }
    .bookings-history-status--completed {
      background: #1e3a8a;
      color: #dbeafe;
    }
    .bookings-history-status--cancelled {
      background: #7f1d1d;
      color: #fecaca;
    }
    .bookings-history-status--upcoming {
      background: #713f12;
      color: #fef3c7;
    }
  </style>
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
          <p class="bookings-summary-label">Running</p>
          <p class="bookings-summary-value bookings-summary-value--active"><?php echo htmlspecialchars((string) $runningBookings); ?></p>
        </article>
        <article class="bookings-summary-card">
          <p class="bookings-summary-label">Completed</p>
          <p class="bookings-summary-value"><?php echo htmlspecialchars((string) $completedBookings); ?></p>
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
              $timelineStatus = bookingTimelineStatus((array) $b, $todayDate);
              $isCancelled = $timelineStatus === 'cancelled';
              $isCompleted = $timelineStatus === 'completed';
              $canCancel = canCustomerCancelBooking((array) $b);
              $driveModeLabel = (($b['drive_mode'] ?? 'self_drive') === 'with_driver') ? 'With driver' : 'Self-drive';
              $statusClass = 'booking-status--active';
              if ($timelineStatus === 'cancelled') {
                $statusClass = 'booking-status--cancelled';
              } elseif ($timelineStatus === 'completed') {
                $statusClass = 'booking-status--completed';
              } elseif ($timelineStatus === 'running') {
                $statusClass = 'booking-status--running';
              }
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
                <span class="booking-status <?php echo $statusClass; ?>">
                  <?php echo htmlspecialchars(ucfirst($timelineStatus)); ?>
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

              <?php if (!$isCancelled && !$isCompleted && $canCancel): ?>
                <form class="booking-card-actions" method="post" action="my-bookings.php" onsubmit="return confirm('Cancel this booking?');">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="cancel_booking" value="<?php echo (int) $b['id']; ?>">
                  <button type="submit" class="booking-cancel-btn">Cancel booking</button>
                </form>
              <?php elseif (!$isCancelled && !$isCompleted): ?>
                <p class="booking-card-user" style="margin-top:8px;color:#b91c1c;">
                  Cancellation closed. You can cancel only within 24 hours from booking time.
                </p>
              <?php elseif ($isCompleted): ?>
                <p class="booking-card-user" style="margin-top:8px;color:#1d4ed8;">
                  Trip completed.
                </p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>

        <section class="bookings-history">
          <h3>Booking History</h3>
          <table class="bookings-history-table">
            <thead>
              <tr>
                <th>Car</th>
                <th>Booked Dates</th>
                <th>Booked On</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <?php $timelineStatus = bookingTimelineStatus((array) $b, $todayDate); ?>
                <tr>
                  <td><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></td>
                  <td>
                    <?php echo htmlspecialchars((string) ($b['start_date'] ?? '')); ?>
                    to
                    <?php echo htmlspecialchars((string) ($b['end_date'] ?? '')); ?>
                  </td>
                  <td><?php echo htmlspecialchars((string) date('Y-m-d', strtotime((string) ($b['created_at'] ?? 'now')))); ?></td>
                  <td>
                    <span class="bookings-history-status bookings-history-status--<?php echo htmlspecialchars($timelineStatus); ?>">
                      <?php echo htmlspecialchars($timelineStatus); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>
    </section>
  </main>
  <footer class="site-footer">
    <div class="footer-top">
      <div class="footer-brand">
        <h2>Vehicle Rental System</h2>
        <p>
          Premium vehicles for every trip, booked in seconds
          with transparent pricing, flexible pickup, and 24/7 support.
        </p>
        <div class="footer-social">
          <a href="#" aria-label="Facebook">FB</a>
          <a href="#" aria-label="Instagram">IG</a>
          <a href="#" aria-label="Twitter">X</a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Explore</h4>
        <a href="index.php">Home</a>
        <a href="index.php#fleet">Fleet</a>
        <a href="my-bookings.php">My Bookings</a>
        <a href="login.php">Login</a>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <a href="mailto:support@vehiclerental.com">support@vehiclerental.com</a>
        <a href="tel:+9779800000000">+977 980-0000000</a>
        <span>Kathmandu, Nepal</span>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> Vehicle Rental System. All rights reserved.</span>
      <span>Built for smooth booking and premium support.</span>
    </div>
  </footer>
</body>
</html>
