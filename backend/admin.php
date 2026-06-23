<!-- <?php
require_once __DIR__ . '/functions.php';
$bookings = getBookings($pdo);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Bookings</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <h1>Admin — Bookings</h1>
    <nav>
      <a href="index.php">Home</a>
      <?php if (!empty($_SESSION['user']['name'])): ?>
        | Logged in as <?php echo htmlspecialchars($_SESSION['user']['name']); ?> (<a href="logout.php">Logout</a>)
      <?php else: ?>
        | <a href="login.php">Login</a>
      <?php endif; ?>
    </nav>
  </header>
  <main>
    <?php if (empty($bookings)): ?>
      <p>No bookings yet.</p>
    <?php else: ?>
      <table class="bookings">
        <thead><tr><th>Vehicle</th><th>Name</th><th>Email</th><th>Start</th><th>End</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></td>
            <td><?php echo htmlspecialchars($b['name']); ?></td>
            <td><?php echo htmlspecialchars($b['email']); ?></td>
            <td><?php echo htmlspecialchars($b['start_date']); ?></td>
            <td><?php echo htmlspecialchars($b['end_date']); ?></td>
            <td><?php echo htmlspecialchars($b['created_at']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
  <footer>&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html> -->








<?php
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin-login.php');
    exit;
}

$flash = '';
$error = '';
$editVehicle = null;

if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if (isset($_GET['delete_vehicle'])) {
    $vehicleId = (int) $_GET['delete_vehicle'];
    if ($vehicleId > 0) {
        deleteVehicle($pdo, $vehicleId);
        $_SESSION['flash'] = 'Vehicle deleted successfully.';
    }
    header('Location: admin.php');
    exit;
}

if (isset($_GET['edit'])) {
    $editVehicle = getVehicle($pdo, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $price = trim($_POST['price_per_day'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if ($make === '' || $model === '' || $price === '') {
        $error = 'Make, model, and price are required.';
    } elseif (!is_numeric($price) || $price < 0) {
        $error = 'Price must be a valid number.';
    } else {
        if ($vehicleId) {
            updateVehicle($pdo, $vehicleId, $make, $model, $year ?: null, $price, $image);
            $_SESSION['flash'] = 'Vehicle updated successfully.';
        } else {
            createVehicle($pdo, $make, $model, $year ?: null, $price, $image);
            $_SESSION['flash'] = 'Vehicle added successfully.';
        }
        header('Location: admin.php');
        exit;
    }
}

$vehicles = getVehicles($pdo);
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
    .admin-header { display:flex; justify-content:space-between; align-items:center; padding:20px 40px; background:#111827; border-bottom:1px solid #1f2937; }
    .admin-header h1 { margin:0; font-size:28px; }
    .admin-header nav a { color:#f59e0b; text-decoration:none; margin-left:20px; font-weight:700; }
    .dashboard { padding:30px 40px; }
    .message, .error { max-width:960px; margin:0 auto 20px; padding:16px 20px; border-radius:12px; }
    .message { background:#134e4a; color:#d1fae5; }
    .error { background:#7f1d1d; color:#fee2e2; }
    .vehicle-form, .table-container { max-width:960px; margin:0 auto 30px; background:#111827; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.25); }
    .form-body { padding:30px; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .form-group label { display:block; margin-bottom:8px; color:#9ca3af; }
    .form-group input { width:100%; padding:12px 14px; border:1px solid #334155; border-radius:12px; background:#0f172a; color:#e2e8f0; }
    .form-actions { padding:0 30px 30px; }
    .btn { display:inline-block; padding:12px 20px; border-radius:10px; text-decoration:none; font-weight:700; border:none; cursor:pointer; }
    .btn-primary { background:#f59e0b; color:#111827; }
    .btn-secondary { background:#0f172a; color:#f8fafc; border:1px solid #334155; }
    .table-container table { width:100%; border-collapse:collapse; }
    thead { background:#1f2937; }
    th, td { padding:16px 20px; text-align:left; border-bottom:1px solid #1f2937; }
    th { color:#fbbf24; font-size:14px; letter-spacing:.02em; }
    td { color:#cbd5e1; }
    a.action-link { color:#f59e0b; text-decoration:none; margin-right:12px; }
    a.delete-link { color:#f87171; }
    @media(max-width:900px) { .admin-header { flex-direction:column; align-items:flex-start; gap:15px; } .form-body { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <header class="admin-header">
    <h1>Admin Dashboard</h1>
    <nav>
      <a href="../index.php">Home</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </header>

  <main class="dashboard">
    <?php if ($flash): ?>
      <div class="message"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="vehicle-form">
      <div class="form-header" style="padding:30px 30px 0;">
        <h2><?php echo $editVehicle ? 'Edit Vehicle' : 'Add New Vehicle'; ?></h2>
        <p style="color:#9ca3af; margin-top:8px;">Manage make, model, year, pricing, and image path for rental vehicles.</p>
      </div>
      <form method="post" action="admin.php">
        <div class="form-body">
          <div class="form-group">
            <label for="make">Make</label>
            <input id="make" name="make" value="<?php echo htmlspecialchars($editVehicle['make'] ?? ''); ?>" required>
          </div>
          <div class="form-group">
            <label for="model">Model</label>
            <input id="model" name="model" value="<?php echo htmlspecialchars($editVehicle['model'] ?? ''); ?>" required>
          </div>
          <div class="form-group">
            <label for="year">Year</label>
            <input id="year" name="year" type="number" min="1900" max="2100" value="<?php echo htmlspecialchars($editVehicle['year'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="price_per_day">Price per day</label>
            <input id="price_per_day" name="price_per_day" type="number" step="0.01" value="<?php echo htmlspecialchars($editVehicle['price_per_day'] ?? ''); ?>" required>
          </div>
          <div class="form-group" style="grid-column:span 2;">
            <label for="image">Image path</label>
            <input id="image" name="image" value="<?php echo htmlspecialchars($editVehicle['image'] ?? ''); ?>" placeholder="images/your-image.jpg">
          </div>
        </div>
        <div class="form-actions">
          <?php if ($editVehicle): ?>
            <input type="hidden" name="vehicle_id" value="<?php echo (int) $editVehicle['id']; ?>">
          <?php endif; ?>
          <button type="submit" name="save_vehicle" class="btn btn-primary"><?php echo $editVehicle ? 'Save changes' : 'Add vehicle'; ?></button>
          <?php if ($editVehicle): ?>
            <a href="admin.php" class="btn btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="table-container">
      <div class="table-header" style="padding:30px;">
        <h2>Vehicle Inventory</h2>
      </div>
      <?php if (empty($vehicles)): ?>
        <div class="empty">No vehicles available. Add a vehicle above.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Make</th>
              <th>Model</th>
              <th>Year</th>
              <th>Price / day</th>
              <th>Image path</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vehicles as $vehicle): ?>
              <tr>
                <td><?php echo (int) $vehicle['id']; ?></td>
                <td><?php echo htmlspecialchars($vehicle['make']); ?></td>
                <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                <td><?php echo htmlspecialchars($vehicle['price_per_day']); ?></td>
                <td><?php echo htmlspecialchars($vehicle['image']); ?></td>
                <td>
                  <a class="action-link" href="admin.php?edit=<?php echo (int) $vehicle['id']; ?>">Edit</a>
                  <a class="action-link delete-link" href="admin.php?delete_vehicle=<?php echo (int) $vehicle['id']; ?>" onclick="return confirm('Delete this vehicle?');">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>

  <footer class="page-footer" style="padding:20px 40px; text-align:center; color:#9ca3af;">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>

      }

      table{
        min-width:1000px;
      }

    }

  </style>

</head>

<body>

  <header class="admin-header">

    <h1>🚘 Admin Dashboard</h1>

    <nav class="admin-nav">

      <a href="index.php">Home</a>

      <?php if (!empty($_SESSION['user']['name'])): ?>

        <span style="color:white; margin-left:20px;">
          Welcome,
          <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
        </span>

        <a href="logout.php">Logout</a>

      <?php else: ?>

        <a href="login.php">Login</a>

      <?php endif; ?>

    </nav>

  </header>

  <main class="dashboard">

    <div class="cards">

      <div class="card">
        <h3>Total Bookings</h3>
        <p><?php echo count($bookings); ?></p>
      </div>

      <div class="card">
        <h3>Total Users</h3>
        <p><?php echo count($bookings); ?></p>
      </div>

      <div class="card">
        <h3>Vehicles</h3>
        <p>12</p>
      </div>

      <div class="card">
        <h3>System Status</h3>
        <p style="font-size:20px;">Active</p>
      </div>

    </div>

    <?php if (empty($bookings)): ?>

      <div class="empty">
        No bookings available yet.
      </div>

    <?php else: ?>

      <div class="table-container">

        <div class="table-header">
          <h2>Manage Bookings</h2>
        </div>

        <table>

          <thead>

            <tr>
              <th>Vehicle</th>
              <th>Customer</th>
              <th>Email</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>

          </thead>

          <tbody>

            <?php foreach ($bookings as $b): ?>

              <tr>

                <td>
                  <?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?>
                </td>

                <td>
                  <?php echo htmlspecialchars($b['name']); ?>
                </td>

                <td>
                  <?php echo htmlspecialchars($b['email']); ?>
                </td>

                <td>
                  <?php echo htmlspecialchars($b['start_date']); ?>
                </td>

                <td>
                  <?php echo htmlspecialchars($b['end_date']); ?>
                </td>

                <td>
                  <span class="status">
                    Confirmed
                  </span>
                </td>

                <td>
                  <?php echo htmlspecialchars($b['created_at']); ?>
                </td>

                <td>

                  <a
                    class="delete-btn"
                    href="admin.php?delete=<?php echo $b['id']; ?>"
                    onclick="return confirm('Delete this booking?')"
                  >
                    Delete
                  </a>

                </td>

              </tr>

            <?php endforeach; ?>

          </tbody>

        </table>

      </div>

    <?php endif; ?>

  </main>

  <footer>
    &copy; <?php echo date('Y'); ?> Vehicle Rental System
  </footer>

</body>
</html>