<?php
require_once __DIR__ . '/../backend/functions.php';

// Booking requires a logged-in user account (separate from admin login).
if (empty($_SESSION['user']['id'])) {
    $vehicleParam = !empty($_GET['vehicle_id']) ? '?vehicle_id=' . (int) $_GET['vehicle_id'] : '';
    $_SESSION['redirect_after_login'] = 'book.php' . $vehicleParam;
    header('Location: login.php');
    exit;
}

$vehicle = null;
$vehicleUnavailable = false;

if (!empty($_GET['vehicle_id'])) {
    $vehicle = getVehicle($pdo, (int)$_GET['vehicle_id']);
    if ($vehicle && !empty($vehicle['is_deleted'])) {
        $vehicle = null;
        $vehicleUnavailable = true;
    }
}

$message = '';
$today = new DateTimeImmutable('today');
$minimumBookingDate = $today->modify('+1 day');
$minimumBookingDateValue = $minimumBookingDate->format('Y-m-d');
$driverSurchargePerDay = 2000;
$currentUserId = (int) $_SESSION['user']['id'];

$activeVehicleId = (int) ($_POST['vehicle_id'] ?? $_GET['vehicle_id'] ?? 0);
$existingUserBooking = null;
if ($activeVehicleId > 0) {
  $existingUserBooking = getActiveBookingByUserAndVehicle($pdo, $currentUserId, $activeVehicleId);
}

$selectedDriveMode = normalizeDriveMode($_POST['drive_mode'] ?? ($existingUserBooking['drive_mode'] ?? 'self_drive'));

$vehicleTypes = [
    1 => 'Sports',
    2 => 'SUV',
    3 => 'Sedan'
];

/*
|--------------------------------------------------------------------------
| HANDLE BOOKING
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $selectedDriveMode = normalizeDriveMode($_POST['drive_mode'] ?? 'self_drive');

    $startDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $start_date);
    $endDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $end_date);
    $startDateValid = $startDateObj && $startDateObj->format('Y-m-d') === $start_date;
    $endDateValid = $endDateObj && $endDateObj->format('Y-m-d') === $end_date;

    $userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;

    if ($vehicle_id > 0) {
      $existingUserBooking = getActiveBookingByUserAndVehicle($pdo, $userId ?? 0, $vehicle_id);
    }

    $targetVehicle = $vehicle_id ? getVehicle($pdo, $vehicle_id) : null;

    $basePricePerDay = $targetVehicle ? (float) ($targetVehicle['price_per_day'] ?? 0) : 0.0;
    $dailyRate = $basePricePerDay + ($selectedDriveMode === 'with_driver' ? $driverSurchargePerDay : 0);
    $durationDays = 1;
    if ($startDateValid && $endDateValid) {
      $durationDays = max((int) $startDateObj->diff($endDateObj)->format('%a') + 1, 1);
    }
    $totalAmount = $dailyRate * $durationDays;

    if (!$vehicle_id || !$start_date || !$end_date) {
        $message = 'Please fill all fields.';
    } elseif (!$existingUserBooking && (!$name || !$email)) {
      $message = 'Please fill all fields.';
    } elseif (!$startDateValid || !$endDateValid) {
      $message = 'Please provide valid booking dates.';
    } elseif (!$targetVehicle || !empty($targetVehicle['is_deleted'])) {
        $message = 'Selected vehicle does not exist.';
    } elseif (!$existingUserBooking && !in_array($selectedDriveMode, ['self_drive', 'with_driver'], true)) {
      $message = 'Please choose a valid drive option.';
    } elseif ($startDateObj < $minimumBookingDate) {
      $message = 'Pickup date must be at least one day after today.';
    } elseif ($endDateObj < $startDateObj) {
        $message = 'Return date must be on or after the pickup date.';
    } elseif (!$existingUserBooking && $booking_id > 0) {
      $message = 'Unauthorized booking update request.';
    } elseif ($existingUserBooking && $booking_id > 0 && $booking_id !== (int) $existingUserBooking['id']) {
      $message = 'Invalid booking reference for reschedule request.';
    } elseif ($existingUserBooking && $booking_id <= 0) {
      $message = 'You already have an active booking for this vehicle. Update your booking dates below.';
    } elseif ($existingUserBooking && $booking_id > 0 && !canCustomerCancelBooking((array) $existingUserBooking)) {
      $message = 'You can change dates only within 24 hours from booking time.';
    } else {

      if ($booking_id > 0 && $existingUserBooking) {
        if (!isVehicleDateRangeAvailableExcludingBooking($pdo, $vehicle_id, $start_date, $end_date, $booking_id)) {
          $message = 'These dates are already booked for this vehicle. Please choose different dates.';
        } elseif (rescheduleCustomerBooking($pdo, $booking_id, $userId ?? 0, $start_date, $end_date)) {
          $message = 'Booking dates updated successfully.';
          $existingUserBooking = getActiveBookingByUserAndVehicle($pdo, $userId ?? 0, $vehicle_id);
        } else {
          $message = 'Unable to update booking dates. Please try again.';
        }

      } elseif (!isVehicleDateRangeAvailable($pdo, $vehicle_id, $start_date, $end_date)) {
        $message = 'These dates are already booked for this vehicle. Please choose different dates.';

      } else {

        if (createBooking(
          $pdo,
          $vehicle_id,
          $userId,
          $name,
          $email,
          $start_date,
          $end_date,
          $selectedDriveMode,
          $dailyRate,
          $totalAmount
        )) {

          $message = 'Booking successful — ' . ucfirst(str_replace('_', ' ', $selectedDriveMode)) . ' at NPR ' . number_format($dailyRate, 0) . '/day.';
          $existingUserBooking = getActiveBookingByUserAndVehicle($pdo, $userId ?? 0, $vehicle_id);

        } else {

          $message = 'Sorry, these dates were just booked by another user. Please select different dates.';
        }
        }
    }
}

  if (!$vehicle && !empty($_POST['vehicle_id'])) {
    $vehicle = getVehicle($pdo, (int) $_POST['vehicle_id']);
    if ($vehicle && !empty($vehicle['is_deleted'])) {
        $vehicle = null;
        $vehicleUnavailable = true;
    }
  }

  $canEditExistingBooking = $existingUserBooking ? canCustomerCancelBooking((array) $existingUserBooking) : false;
  $isRescheduleMode = $existingUserBooking !== null;
  $formLockedForReschedule = $isRescheduleMode && !$canEditExistingBooking;
  $dateOnlyRescheduleMode = $isRescheduleMode;
  $startDateValue = $_POST['start_date'] ?? ($existingUserBooking['start_date'] ?? '');
  $endDateValue = $_POST['end_date'] ?? ($existingUserBooking['end_date'] ?? '');

  $blockedDateRanges = [];
  $customerBookedRanges = [];
  if ($vehicle) {
    $allRanges = getVehicleBookedDateRangesDetailed($pdo, (int) $vehicle['id']);
    foreach ($allRanges as $range) {
      $isCurrentUser = (int) ($range['user_id'] ?? 0) === $currentUserId;
      if ($isCurrentUser) {
        $customerBookedRanges[] = [
          'from' => $range['from'],
          'to' => $range['to'],
        ];
      }

      // Keep current booking selectable while rescheduling it.
      if ($existingUserBooking && (int) $range['booking_id'] === (int) ($existingUserBooking['id'] ?? 0)) {
        continue;
      }

      $blockedDateRanges[] = [
        'from' => $range['from'],
        'to' => $range['to'],
      ];
    }
  }
?>

<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Book Vehicle</title>

<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
  .flatpickr-day.customer-booked-date {
    background: #dbeafe;
    border-color: #60a5fa;
    color: #1e3a8a;
    font-weight: 600;
  }
</style>

</head>

<body>

<header class="page-header">

  <h1>Reserve Your Ride</h1>

  <nav>
    <a href="index.php">Home</a>
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

<?php if ($message): ?>

  <p class="message">
    <?php echo htmlspecialchars($message); ?>
  </p>

<?php endif; ?>

<?php if (!$vehicle): ?>

  <p>
    <?php echo $vehicleUnavailable ? 'This vehicle is no longer available.' : 'Vehicle not specified.'; ?>
    Go back to <a href="index.php">listing</a>.
  </p>

<?php else: ?>

<?php
/*
|--------------------------------------------------------------------------
| DIFFERENT IMAGE FOR DIFFERENT VEHICLES
|--------------------------------------------------------------------------
*/

$img = vehicleImagePath($vehicle);
$transmission = normalizeTransmission($vehicle['transmission'] ?? 'automatic');
$displayDailyRate = (float) ($vehicle['price_per_day'] ?? 0) + ($selectedDriveMode === 'with_driver' ? $driverSurchargePerDay : 0);

?>

<div class="booking-container">

  <div class="booking-left">

    <article class="vehicle-card">

      <span class="vehicle-type-badge">
        <?php echo htmlspecialchars($vehicleTypes[$vehicle['id']] ?? 'Vehicle'); ?>
      </span>

      <h2>
        <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
      </h2>

      <p class="vehicle-description">
        <?php
        $desc = trim($vehicle['description'] ?? '');
        echo $desc !== ''
            ? nl2br(htmlspecialchars($desc))
            : htmlspecialchars('Book the ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' for your next trip.');
        ?>
      </p>

      <div class="vehicle-image">

        <img
          src="<?php echo htmlspecialchars($img); ?>"
          alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>"
        >

      </div>

      <div class="vehicle-specs">

        <div class="spec-item">
          <h4>Availability</h4>
          <p>Check calendar for availability</p>
        </div>

        <div class="spec-item">
          <h4>Seating</h4>
          <p>5 seats · <?php echo htmlspecialchars(ucfirst($transmission)); ?></p>
        </div>

      </div>

    </article>

  </div>

  <div class="booking-right">

    <div class="rental-summary">

      <span class="summary-badge">
        Rental Summary
      </span>

      <div class="price-display">
        NPR <?php echo htmlspecialchars(number_format((float) $vehicle['price_per_day'], 0)); ?> / day
      </div>

      <p class="summary-description">
        Book your selected vehicle with flexible pickup options and clear pricing.
      </p>

      <p class="summary-description" style="margin-top:8px;">
        Dates already booked by other users are disabled in the calendar.
      </p>

      <?php if ($isRescheduleMode): ?>
        <p class="summary-description" style="margin-top:8px;">
          Your current booking dates are highlighted in blue.
          <?php if ($canEditExistingBooking): ?>
            You can reschedule within 24 hours from when this booking was created.
          <?php else: ?>
            Rescheduling is closed. You can reschedule only within 24 hours from booking time.
          <?php endif; ?>
        </p>

        <?php if (!empty($customerBookedRanges)): ?>
          <div class="summary-description" style="margin-top:8px;">
            <strong>Your booking range(s) (YYYY-MM-DD):</strong>
            <?php foreach ($customerBookedRanges as $range): ?>
              <div>
                <?php echo htmlspecialchars((string) $range['from']); ?> to <?php echo htmlspecialchars((string) $range['to']); ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="book.php" class="booking-form">

        <?php echo csrfField(); ?>

        <input
          type="hidden"
          name="vehicle_id"
          value="<?php echo htmlspecialchars($vehicle['id']); ?>"
        >

        <?php if ($isRescheduleMode): ?>
          <input
            type="hidden"
            name="booking_id"
            value="<?php echo (int) $existingUserBooking['id']; ?>"
          >
        <?php endif; ?>

        <div class="form-group">

          <label for="name">Full Name</label>

          <input
            type="text"
            id="name"
            name="name"
            placeholder="Enter your name"
            required
            value="<?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?>"
            <?php echo $dateOnlyRescheduleMode ? 'readonly' : ''; ?>
          >

        </div>

        <div class="form-group">

          <label for="email">Email Address</label>

          <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            required
            value="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>"
            <?php echo $dateOnlyRescheduleMode ? 'readonly' : ''; ?>
          >

        </div>

        <div class="form-group">

          <label for="location">Pickup location</label>

          <input
            type="text"
            id="location"
            name="location"
            placeholder="City Center Terminal"
            value="City Center Terminal"
            <?php echo $dateOnlyRescheduleMode ? 'readonly' : ''; ?>
          >

        </div>

        <div class="form-group">

          <label for="drive_mode">Drive option</label>

          <select id="drive_mode" name="drive_mode" required <?php echo $dateOnlyRescheduleMode ? 'disabled' : ''; ?>>
            <option value="self_drive" <?php echo $selectedDriveMode === 'self_drive' ? 'selected' : ''; ?>>Self-drive</option>
            <option value="with_driver" <?php echo $selectedDriveMode === 'with_driver' ? 'selected' : ''; ?>>Requires driver (+NPR <?php echo htmlspecialchars(number_format($driverSurchargePerDay, 0)); ?>/day)</option>
          </select>
          <?php if ($dateOnlyRescheduleMode): ?>
            <input type="hidden" name="drive_mode" value="<?php echo htmlspecialchars($selectedDriveMode); ?>">
          <?php endif; ?>

        </div>

        <div class="form-group">

          <label for="start_date">Pickup date</label>

          <input
            type="date"
            id="start_date"
            name="start_date"
            min="<?php echo htmlspecialchars($minimumBookingDateValue); ?>"
            required
            value="<?php echo htmlspecialchars($startDateValue); ?>"
            <?php echo $formLockedForReschedule ? 'readonly' : ''; ?>
          >

        </div>

        <div class="form-group">

          <label for="end_date">Return date</label>

          <input
            type="date"
            id="end_date"
            name="end_date"
            min="<?php echo htmlspecialchars($minimumBookingDateValue); ?>"
            required
            value="<?php echo htmlspecialchars($endDateValue); ?>"
            <?php echo $formLockedForReschedule ? 'readonly' : ''; ?>
          >

        </div>

        <div class="total-row">

          <span>Total:</span>

          <span id="daily-rate-price" class="total-price">
            NPR <?php echo htmlspecialchars(number_format($displayDailyRate, 0)); ?> / day
          </span>

        </div>

        <p id="drive-mode-note" class="summary-description" style="margin-top:8px;">
          <?php if ($selectedDriveMode === 'with_driver'): ?>
            Driver service surcharge applied: NPR <?php echo htmlspecialchars(number_format($driverSurchargePerDay, 0)); ?>/day.
          <?php else: ?>
            Self-drive selected: base daily vehicle price applies.
          <?php endif; ?>
        </p>

        <button type="submit" class="btn btn-primary btn-large" <?php echo $formLockedForReschedule ? 'disabled' : ''; ?>>
          <?php echo $isRescheduleMode ? 'Update Booking Dates' : 'Continue to Book'; ?>
        </button>

      </form>

    </div>

  </div>

</div>

<?php endif; ?>

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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  (function () {
    const driveMode = document.getElementById('drive_mode');
    const dailyRatePrice = document.getElementById('daily-rate-price');
    const driveModeNote = document.getElementById('drive-mode-note');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const minimumBookingDate = <?php echo json_encode($minimumBookingDateValue, JSON_UNESCAPED_SLASHES); ?>;
    const blockedDateRanges = <?php echo json_encode($blockedDateRanges, JSON_UNESCAPED_SLASHES); ?>;
    const customerBookedRanges = <?php echo json_encode($customerBookedRanges, JSON_UNESCAPED_SLASHES); ?>;
    const formLockedForReschedule = <?php echo $formLockedForReschedule ? 'true' : 'false'; ?>;
    if (!driveMode || !dailyRatePrice || !driveModeNote) {
      return;
    }

    const baseRate = <?php echo json_encode((float) ($vehicle['price_per_day'] ?? 0), JSON_UNESCAPED_SLASHES); ?>;
    const driverSurcharge = <?php echo (int) $driverSurchargePerDay; ?>;

    const formatNpr = (value) => Math.round(value).toLocaleString('en-US');

    const render = () => {
      const isWithDriver = driveMode.value === 'with_driver';
      const rate = baseRate + (isWithDriver ? driverSurcharge : 0);
      dailyRatePrice.textContent = 'NPR ' + formatNpr(rate) + ' / day';
      driveModeNote.textContent = isWithDriver
        ? 'Driver service surcharge applied: NPR ' + formatNpr(driverSurcharge) + '/day.'
        : 'Self-drive selected: base daily vehicle price applies.';
    };

    driveMode.addEventListener('change', render);
    render();

    const hasOverlap = (startValue, endValue) => {
      if (!startValue || !endValue) {
        return false;
      }
      return blockedDateRanges.some((range) => startValue <= range.to && endValue >= range.from);
    };

    const isDateWithinRanges = (dateValue, ranges) => {
      return ranges.some((range) => dateValue >= range.from && dateValue <= range.to);
    };

    const decorateCustomerBookedDates = (instance) => {
      if (!instance || !instance.daysContainer) {
        return;
      }

      instance.daysContainer
        .querySelectorAll('.flatpickr-day')
        .forEach((dayElem) => {
          if (!dayElem.dateObj) {
            return;
          }
          const dateValue = instance.formatDate(dayElem.dateObj, 'Y-m-d');
          if (isDateWithinRanges(dateValue, customerBookedRanges)) {
            dayElem.classList.add('customer-booked-date');
            dayElem.title = 'Your existing booking date';
          }
        });
    };

    const syncReturnDateConstraint = () => {
      if (!startDate || !endDate) {
        return;
      }

      const minEndDate = startDate.value && startDate.value > minimumBookingDate
        ? startDate.value
        : minimumBookingDate;

      endDate.min = minEndDate;

      if (endDate.value && endDate.value < minEndDate) {
        endDate.value = minEndDate;
      }

      if (startDate.value && endDate.value && endDate.value < startDate.value) {
        endDate.setCustomValidity('Return date cannot be less than pickup date.');
      } else if (hasOverlap(startDate.value, endDate.value)) {
        endDate.setCustomValidity('Selected date range overlaps an existing booking.');
      } else {
        endDate.setCustomValidity('');
      }
    };

    if (startDate && endDate && !formLockedForReschedule) {
      if (typeof flatpickr !== 'undefined') {
        let endPicker = null;

        endPicker = flatpickr(endDate, {
          dateFormat: 'Y-m-d',
          minDate: minimumBookingDate,
          disable: blockedDateRanges,
          defaultDate: endDate.value || null,
          onReady: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onMonthChange: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onYearChange: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onOpen: function () {
            const minEndDate = startDate.value && startDate.value > minimumBookingDate
              ? startDate.value
              : minimumBookingDate;
            endPicker.set('minDate', minEndDate);
            decorateCustomerBookedDates(endPicker);
          },
          onChange: syncReturnDateConstraint,
        });

        flatpickr(startDate, {
          dateFormat: 'Y-m-d',
          minDate: minimumBookingDate,
          disable: blockedDateRanges,
          defaultDate: startDate.value || null,
          onReady: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onMonthChange: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onYearChange: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onOpen: function (_selectedDates, _dateStr, instance) {
            decorateCustomerBookedDates(instance);
          },
          onChange: function (selectedDates, dateStr) {
            const minEndDate = dateStr && dateStr > minimumBookingDate
              ? dateStr
              : minimumBookingDate;
            endPicker.set('minDate', minEndDate);
            syncReturnDateConstraint();
          },
        });
      }

      startDate.addEventListener('change', syncReturnDateConstraint);
      endDate.addEventListener('change', syncReturnDateConstraint);
      syncReturnDateConstraint();
    } else if (startDate && endDate) {
      syncReturnDateConstraint();
    }
  })();
</script>

</body>
</html>
