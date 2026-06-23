<?php
require_once __DIR__ . '/../backend/functions.php';

if (empty($_SESSION['user']['id'])) {
    $_SESSION['redirect_after_login'] = 'add-car.php';
    header('Location: login.php');
    exit;
}

$error = '';
$message = '';
$myVehicles = getVehiclesByOwner($pdo, (int) $_SESSION['user']['id']);

$make = trim($_POST['make'] ?? '');
$model = trim($_POST['model'] ?? '');
$year = trim($_POST['year'] ?? '');
$price = trim($_POST['price_per_day'] ?? '');
$image = trim($_POST['image'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($make === '' || $model === '' || $price === '') {
        $error = 'Make, model, and price are required.';
    } elseif (!is_numeric($price) || (float) $price <= 0) {
        $error = 'Price must be a number greater than zero.';
    } elseif ($year !== '' && (!ctype_digit($year) || (int) $year < 1900 || (int) $year > 2100)) {
        $error = 'Year must be between 1900 and 2100.';
    } else {
        $image = $image !== '' ? $image : 'images/placeholder.svg';
        createVehicle($pdo, (int) $_SESSION['user']['id'], $make, $model, $year ?: null, $price, $image);
        $message = 'Car added successfully.';
        $make = $model = $year = $price = $image = '';
        $myVehicles = getVehiclesByOwner($pdo, (int) $_SESSION['user']['id']);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Car - Vehicle Rental</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <h1>Add Your Car</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="#my-cars">My Cars</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <section class="owner-panel">
      <div class="owner-copy">
        <span class="section-pill">OWNER LISTING</span>
        <h2>List a vehicle for rent</h2>
        <p>Add the vehicle details and daily rental price. The car is saved under your account and appears in the public fleet immediately.</p>
      </div>

      <form method="post" action="add-car.php" class="owner-form">
        <?php if ($message): ?>
          <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
          <p class="message message-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <div class="form-grid">
          <div class="form-group">
            <label for="make">Make</label>
            <input id="make" name="make" placeholder="Toyota" required value="<?php echo htmlspecialchars($make); ?>">
          </div>

          <div class="form-group">
            <label for="model">Model</label>
            <input id="model" name="model" placeholder="Corolla" required value="<?php echo htmlspecialchars($model); ?>">
          </div>

          <div class="form-group">
            <label for="year">Year</label>
            <input id="year" name="year" type="number" min="1900" max="2100" placeholder="2022" value="<?php echo htmlspecialchars($year); ?>">
          </div>

          <div class="form-group">
            <label for="price_per_day">Price per day (NPR)</label>
            <input id="price_per_day" name="price_per_day" type="number" step="0.01" min="1" placeholder="5000" required value="<?php echo htmlspecialchars($price); ?>">
          </div>

          <div class="form-group form-group-full">
            <label for="image">Image path</label>
            <input id="image" name="image" placeholder="images/placeholder.svg" value="<?php echo htmlspecialchars($image); ?>">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-large">Add Car</button>
      </form>
    </section>

    <section id="my-cars" class="owner-list">
      <div class="section-header">
        <div>
          <span class="section-pill">MY CARS</span>
          <h2>Your listed vehicles</h2>
        </div>
      </div>

      <?php if (empty($myVehicles)): ?>
        <p class="empty-state">You have not added any cars yet.</p>
      <?php else: ?>
        <div class="vehicles">
          <?php foreach ($myVehicles as $vehicle): ?>
            <article class="vehicle">
              <div class="vehicle-image-wrap">
                <img src="<?php echo htmlspecialchars(vehicleImagePath($vehicle)); ?>" alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>">
                <span class="vehicle-tag">Your Car</span>
              </div>
              <div class="vehicle-details">
                <h3><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                <p class="vehicle-meta"><?php echo htmlspecialchars($vehicle['year'] ?: 'Year not set'); ?></p>
                <p class="vehicle-price">NPR <?php echo htmlspecialchars(number_format((float) $vehicle['price_per_day'], 0)); ?> / day</p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
