<?php
require_once __DIR__ . '/../backend/functions.php';

$vehicles = getVehicles($pdo);

$vehicleTypes = [
    1 => 'Sports',
    2 => 'SUV',
    3 => 'Sedan',
];
?>

<!doctype html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <title>Vehicle Rental</title>

  <link rel="stylesheet" href="css/styles.css">

</head>

<body>

  <header class="site-header">

    <div>

      <h1>Vehicle Rental System</h1>

      <p class="subtitle">
        Discover the best cars for every trip,
        with smooth booking and premium support.
      </p>

    </div>

    <nav>

      <a href="index.php">Home</a>

      <a href="#fleet">Fleet</a>

      <?php if (!empty($_SESSION['admin'])): ?>
        <a href="../backend/admin.php">Admin Dashboard</a>
      <?php endif; ?>

      <?php if (!empty($_SESSION['user'])): ?>
        <a href="my-bookings.php">My Bookings</a>
      <?php endif; ?>

      <?php if (!empty($_SESSION['user']['name'])): ?>

        <span class="user-pill">

          Logged in as
          <?php echo htmlspecialchars($_SESSION['user']['name']); ?>

          (<a href="logout.php">Logout</a>)

        </span>

      <?php else: ?>

        <a href="login.php">Login</a>

      <?php endif; ?>

    </nav>

  </header>

  <main>

    <section class="hero">

      <div class="hero-copy">

        <span class="eyebrow">
          Drive with confidence
        </span>

        <h1>
          Premium vehicles for every trip,
          booked in seconds.
        </h1>

        <p class="hero-text">
          Discover luxury cars, rugged SUVs,
          with transparent pricing, flexible pickup,
          and 24/7 support.
        </p>

        <div class="hero-actions">

          <a class="btn btn-primary" href="#fleet">
            Browse Fleet
          </a>

          <a class="btn btn-outline" href="book.php?vehicle_id=1">
            Start Booking
          </a>

        </div>

      </div>

      <div class="hero-media">

        <div class="hero-media-card">

          <img
            src="images/index.jpg"
            alt="Premium vehicle"
          >

        </div>

      </div>

    </section>

    <section id="fleet" class="featured">

      <div class="section-header">

        <div>

          <span class="section-pill">
            FEATURED FLEET
          </span>

          <h2>
            Top vehicles ready to rent
          </h2>

        </div>

        <p class="section-copy">
          Select your next ride from a curated
          collection of cars built for comfort,
          performance, and adventure.
        </p>

      </div>

      <div class="vehicles">

        <?php if (empty($vehicles)): ?>

          <p>No vehicles available.</p>

        <?php else: ?>

          <?php foreach ($vehicles as $v): ?>

            <?php

            /*
            |--------------------------------------------------------------------------
            | DIFFERENT IMAGE FOR DIFFERENT VEHICLES
            |--------------------------------------------------------------------------
            */

            $img = vehicleImagePath($v);
            $transmission = normalizeTransmission($v['transmission'] ?? 'automatic');

            ?>

            <article class="vehicle">

              <div class="vehicle-image-wrap">

                <img
                  src="<?php echo htmlspecialchars($img); ?>"
                  alt="<?php echo htmlspecialchars($v['make'] . ' ' . $v['model']); ?>"
                >

                <span class="vehicle-tag">

                  <?php echo htmlspecialchars($vehicleTypes[$v['id']] ?? 'Vehicle'); ?>

                </span>

                <?php if (isVehicleBooked($v)): ?>
                  <span class="vehicle-tag" style="left:auto;right:12px;background:#dc2626;color:#fff;">Booked</span>
                <?php endif; ?>

              </div>

              <div class="vehicle-details">

                <h3>

                  <?php echo htmlspecialchars($v['make'] . ' ' . $v['model']); ?>

                </h3>

                <p class="vehicle-meta">

                  <?php echo htmlspecialchars($v['year']); ?>
                  · <?php echo htmlspecialchars(ucfirst($transmission)); ?>

                </p>

                <div class="vehicle-footer">

                  <p class="vehicle-price">

                    NPR
                    <?php echo htmlspecialchars(number_format((float) $v['price_per_day'], 0)); ?>
                    / day

                  </p>

                  <?php if (isVehicleBooked($v)): ?>
                    <span
                      class="btn btn-small"
                      style="background:#374151;color:#9ca3af;cursor:not-allowed;"
                      aria-disabled="true"
                    >
                      Booked
                    </span>
                  <?php else: ?>
                    <a
                      class="btn btn-primary btn-small"
                      href="book.php?vehicle_id=<?php echo $v['id']; ?>"
                    >
                      Book now
                    </a>
                  <?php endif; ?>

                </div>

              </div>

            </article>

          <?php endforeach; ?>

        <?php endif; ?>

      </div>

    </section>

  </main>

  <footer class="page-footer">

    &copy; <?php echo date('Y'); ?>
    Vehicle Rental

  </footer>

</body>
</html>
