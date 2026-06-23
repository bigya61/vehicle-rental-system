<?php
require_once __DIR__ . '/../backend/functions.php';

$vehicle = null;

if (!empty($_GET['vehicle_id'])) {
    $vehicle = getVehicle($pdo, (int)$_GET['vehicle_id']);
}

$message = '';

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

    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    $userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;

    if ($vehicle_id && $name && $email && $start_date && $end_date) {

        if (createBooking(
            $pdo,
            $vehicle_id,
            $userId,
            $name,
            $email,
            $start_date,
            $end_date
        )) {

            $message = 'Booking successful — we will contact you shortly.';

        } else {

            $message = 'Booking failed — please try again.';
        }

    } else {

        $message = 'Please fill all fields.';
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

</head>

<body>

<header class="page-header">

  <h1>Reserve Your Ride</h1>

  <nav>
    <a href="index.php">Home</a>
    <?php if (!empty($_SESSION['user']['id'])): ?>
      <a href="add-car.php">Add Car</a>
    <?php endif; ?>
    <a href="../backend/admin.php">Admin</a>
  </nav>

</header>

<main>

<?php if ($message): ?>

  <p class="message">
    <?php echo htmlspecialchars($message); ?>
  </p>

<?php endif; ?>

<?php if (!$vehicle && empty($_POST['vehicle_id'])): ?>

  <p>
    Vehicle not specified.
    Go back to <a href="index.php">listing</a>.
  </p>

<?php else: ?>

<?php

if (!$vehicle && !empty($_POST['vehicle_id'])) {

    $vehicle = getVehicle($pdo, (int)$_POST['vehicle_id']);
}

/*
|--------------------------------------------------------------------------
| DIFFERENT IMAGE FOR DIFFERENT VEHICLES
|--------------------------------------------------------------------------
*/

$img = vehicleImagePath($vehicle);

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
        Book the
        <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
        for your next trip.
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
          <p>5 seats · Automatic</p>
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

      <form method="post" action="book.php" class="booking-form">

        <input
          type="hidden"
          name="vehicle_id"
          value="<?php echo htmlspecialchars($vehicle['id']); ?>"
        >

        <div class="form-group">

          <label for="name">Full Name</label>

          <input
            type="text"
            id="name"
            name="name"
            placeholder="Enter your name"
            required
            value="<?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?>"
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
          >

        </div>

        <div class="form-group">

          <label for="start_date">Pickup date</label>

          <input
            type="date"
            id="start_date"
            name="start_date"
            required
          >

        </div>

        <div class="form-group">

          <label for="end_date">Return date</label>

          <input
            type="date"
            id="end_date"
            name="end_date"
            required
          >

        </div>

        <div class="total-row">

          <span>Total:</span>

          <span class="total-price">
            NPR <?php echo htmlspecialchars(number_format((float) $vehicle['price_per_day'], 0)); ?> / day
          </span>

        </div>

        <button type="submit" class="btn btn-primary btn-large">
          Continue to Book
        </button>

      </form>

    </div>

  </div>

</div>

<?php endif; ?>

</main>

<footer class="page-footer">
  &copy; <?php echo date('Y'); ?> Vehicle Rental
</footer>

</body>
</html>
