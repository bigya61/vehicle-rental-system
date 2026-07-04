<?php
require_once __DIR__ . '/../backend/functions.php';

$query = trim((string) ($_GET['q'] ?? ''));
$vehicles = $query !== '' ? searchVehicles($pdo, $query) : [];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Search Vehicles</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <div>
      <h1>Vehicle Search</h1>
      <p class="subtitle">Find your next ride by car name.</p>
    </div>
    <nav>
      <a href="index.php">Home</a>
      <a href="index.php#fleet">Fleet</a>
      <?php if (!empty($_SESSION['user'])): ?>
        <a href="my-bookings.php">My Bookings</a>
      <?php endif; ?>
      <form class="header-search" method="get" action="<?php echo htmlspecialchars(appBaseUrl() . '/frontend/search.php'); ?>" role="search">
        <input
          type="search"
          name="q"
          placeholder="Search car name"
          value="<?php echo htmlspecialchars($query); ?>"
          aria-label="Search vehicles"
          required
        >
        <button type="submit">Search</button>
      </form>
      <?php if (!empty($_SESSION['user']['name'])): ?>
        <span class="user-pill">
          Logged in as <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
          (<a href="logout.php">Logout</a>)
        </span>
      <?php else: ?>
        <a href="login.php">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <section class="search-results">
      <div class="section-header">
        <div>
          <span class="section-pill">Search Results</span>
          <h2>
            <?php if ($query === ''): ?>
              Enter a term to start searching
            <?php else: ?>
              <?php echo htmlspecialchars((string) count($vehicles)); ?> vehicle(s) found for "<?php echo htmlspecialchars($query); ?>"
            <?php endif; ?>
          </h2>
        </div>
        <p class="section-copy">
          Search by car name, for example: Ford, Toyota Corolla, or Civic.
        </p>
      </div>

      <?php if ($query !== '' && empty($vehicles)): ?>
        <div class="search-empty">
          <h3>No matching vehicles found</h3>
          <p>Try a broader keyword or browse the full fleet from the home page.</p>
          <a class="btn btn-outline" href="index.php#fleet">Browse Fleet</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($vehicles)): ?>
        <div class="vehicles">
          <?php foreach ($vehicles as $v): ?>
            <?php
            $img = vehicleImagePath($v);
            $transmission = normalizeTransmission($v['transmission'] ?? 'automatic');
            ?>
            <article class="vehicle">
              <div class="vehicle-image-wrap">
                <img
                  src="<?php echo htmlspecialchars($img); ?>"
                  alt="<?php echo htmlspecialchars($v['make'] . ' ' . $v['model']); ?>"
                >

                <?php if (isVehicleBooked($v)): ?>
                  <span class="vehicle-tag" style="left:auto;right:12px;background:#d97706;color:#fff;">Some dates unavailable</span>
                <?php endif; ?>
              </div>

              <div class="vehicle-details">
                <h3><?php echo htmlspecialchars($v['make'] . ' ' . $v['model']); ?></h3>
                <p class="vehicle-meta">
                  <?php echo htmlspecialchars((string) $v['year']); ?>
                  · <?php echo htmlspecialchars(ucfirst($transmission)); ?>
                </p>
                <div class="vehicle-footer">
                  <p class="vehicle-price">
                    NPR <?php echo htmlspecialchars(number_format((float) $v['price_per_day'], 0)); ?> / day
                  </p>

                  <a class="btn btn-primary btn-small" href="book.php?vehicle_id=<?php echo $v['id']; ?>">
                    Book now
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
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