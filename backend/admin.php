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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_vehicle'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $vehicleId = (int) $_POST['delete_vehicle'];
    if ($vehicleId > 0) {
        deleteVehicle($pdo, $vehicleId);
        $_SESSION['flash'] = 'Vehicle deleted successfully.';
    }
    header('Location: admin.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cancel_booking'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $bookingId = (int) $_POST['cancel_booking'];
    if ($bookingId > 0 && cancelBooking($pdo, $bookingId)) {
        $_SESSION['flash'] = 'Booking cancelled and vehicle freed.';
    }
    header('Location: admin.php');
    exit;
}

if (isset($_GET['edit'])) {
    $editVehicle = getVehicle($pdo, (int) $_GET['edit']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_vehicle'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $price = trim($_POST['price_per_day'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = handleVehicleImageUpload($_FILES['image_file'] ?? null, $image);

    if ($make === '' || $model === '' || $price === '') {
        $error = 'make, model, and price are required.';
    } elseif (!is_numeric($price) || (float) $price < 0) {
        $error = 'Price must be a valid number.';
    } else {
        if ($vehicleId > 0) {
            updateVehicle($pdo, $vehicleId, $make, $model, $year ?: null, $price, $image, $description ?: null);
            $_SESSION['flash'] = 'Vehicle updated successfully.';
        } else {
            $ownerId = !empty($_SESSION['user']['id'])
                ? (int) $_SESSION['user']['id']
                : (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
            createVehicle($pdo, $ownerId, $make, $model, $year ?: null, $price, $image, $description ?: null);
            $_SESSION['flash'] = 'Vehicle added successfully.';
        }
        header('Location: admin.php');
        exit;
    }
}

$vehicles = getVehicles($pdo);
$bookings = getBookings($pdo);
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
    .admin-header nav a { color:#f59e0b; text-decoration:none; margin-left:20px; font-weight:700; }
    .dashboard { padding:30px 40px; }
    .stats { max-width:1100px; margin:0 auto 30px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .stat { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:20px; }
    .stat h2 { margin:0 0 8px; color:#9ca3af; font-size:14px; text-transform:uppercase; }
    .stat p { margin:0; color:#f8fafc; font-size:32px; font-weight:700; }
    .message, .error { max-width:1100px; margin:0 auto 20px; padding:16px 20px; border-radius:12px; }
    .message { background:#134e4a; color:#d1fae5; }
    .error { background:#7f1d1d; color:#fee2e2; }
    .vehicle-form, .table-container { max-width:1100px; margin:0 auto 30px; background:#111827; border-radius:12px; border:1px solid #1f2937; overflow:hidden; }
    .form-header, .table-header { padding:24px 30px 0; }
    .form-header h2, .table-header h2 { margin:0; }
    .form-body { padding:24px 30px; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .form-group label { display:block; margin-bottom:8px; color:#9ca3af; }
    .form-group input, .form-group textarea { width:100%; box-sizing:border-box; padding:12px 14px; border:1px solid #334155; border-radius:8px; background:#0f172a; color:#e2e8f0; }
    .image-dropzone { border:2px dashed #475569; border-radius:12px; padding:20px; background:#0f172a; cursor:pointer; text-align:center; transition:border-color .2s ease, transform .2s ease; }
    .image-dropzone.is-dragover { border-color:#f59e0b; transform:translateY(-1px); }
    .image-dropzone p { margin:6px 0; color:#cbd5e1; }
    .dropzone-hint { font-size:13px; color:#94a3b8; }
    .image-dropzone-preview { margin-top:12px; display:flex; justify-content:center; }
    .image-dropzone-preview img { max-height:160px; max-width:100%; border-radius:8px; object-fit:cover; }
    .helper-text { display:block; margin-top:8px; color:#94a3b8; font-size:13px; }
    .form-actions { padding:0 30px 30px; display:flex; gap:12px; }
    .btn { display:inline-block; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:700; border:none; cursor:pointer; }
    .btn-primary { background:#f59e0b; color:#111827; }
    .btn-secondary { background:#0f172a; color:#f8fafc; border:1px solid #334155; }
    .table-scroll { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:860px; }
    thead { background:#1f2937; }
    th, td { padding:14px 18px; text-align:left; border-bottom:1px solid #1f2937; }
    th { color:#fbbf24; font-size:13px; letter-spacing:.02em; text-transform:uppercase; }
    td { color:#cbd5e1; }
    .empty { padding:24px 30px; color:#9ca3af; }
    .action-link { color:#f59e0b; text-decoration:none; margin-right:12px; }
    .delete-link { color:#f87171; }
    .page-footer { padding:20px 40px; text-align:center; color:#9ca3af; }
    @media(max-width:900px) {
      .admin-header { flex-direction:column; align-items:flex-start; }
      .admin-header nav a { margin:0 16px 0 0; }
      .dashboard { padding:24px 16px; }
      .stats, .form-body { grid-template-columns:1fr; }
      .form-group-wide { grid-column:auto; }
    }
  </style>
</head>
<body>
  <header class="admin-header">
    <h1>Admin Dashboard</h1>
    <nav>
      <a href="../frontend/index.php">Home</a>
      <a href="../frontend/logout.php">Logout</a>
    </nav>
  </header>

  <main class="dashboard">
    <?php if ($flash): ?>
      <div class="message"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="stats" aria-label="Dashboard totals">
      <div class="stat">
        <h2>Vehicles</h2>
        <p><?php echo count($vehicles); ?></p>
      </div>
      <div class="stat">
        <h2>Bookings</h2>
        <p><?php echo count($bookings); ?></p>
      </div>
      <div class="stat">
        <h2>Status</h2>
        <p>Active</p>
      </div>
    </section>

    <section class="vehicle-form">
      <div class="form-header">
        <h2><?php echo $editVehicle ? 'Edit Vehicle' : 'Add New Vehicle'; ?></h2>
      </div>
      <form method="post" action="admin.php" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
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
            <input id="price_per_day" name="price_per_day" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($editVehicle['price_per_day'] ?? ''); ?>" required>
          </div>
          <div class="form-group form-group-wide" style="grid-column:span 2;">
            <label for="image_file">Vehicle image</label>
            <div id="image-dropzone" class="image-dropzone" tabindex="0" role="button" aria-label="Upload vehicle image">
              <input id="image_file" name="image_file" type="file" accept="image/*" hidden>
              <p><strong>Drop an image here</strong> or click to browse</p>
              <p class="dropzone-hint">PNG, JPG, WEBP, SVG, or GIF up to 5MB</p>
              <div id="image-dropzone-preview" class="image-dropzone-preview"></div>
            </div>
            <small class="helper-text">You can also type a path manually if you already have an image. Uploading a file overrides the text path.</small>
          </div>
          <div class="form-group form-group-wide" style="grid-column:span 2;">
            <label for="image">Image path</label>
            <input id="image" name="image" value="<?php echo htmlspecialchars($editVehicle['image'] ?? ''); ?>" placeholder="images/toyota.jpg">
          </div>
          <div class="form-group form-group-wide" style="grid-column:span 2;">
            <label for="description">Details</label>
            <textarea id="description" name="description" rows="3" placeholder="Seats, transmission, mileage, condition, etc."><?php echo htmlspecialchars($editVehicle['description'] ?? ''); ?></textarea>
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
      <div class="table-header">
        <h2>Vehicle Inventory</h2>
      </div>
      <?php if (empty($vehicles)): ?>
        <div class="empty">No vehicles available. Add a vehicle above.</div>
      <?php else: ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Make</th>
                <th>Model</th>
                <th>Year</th>
                <th>Owner</th>
                <th>Price / day</th>
                <th>Status</th>
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
                  <td><?php echo htmlspecialchars($vehicle['owner_name'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($vehicle['price_per_day']); ?></td>
                  <td>
                    <?php if (isVehicleBooked($vehicle)): ?>
                      <span style="color:#f87171;font-weight:700;">Booked</span>
                    <?php else: ?>
                      <span style="color:#34d399;font-weight:700;">Available</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($vehicle['image']); ?></td>
                  <td>
                    <a class="action-link" href="admin.php?edit=<?php echo (int) $vehicle['id']; ?>">Edit</a>
                    <form method="post" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this vehicle?');">
                      <?php echo csrfField(); ?>
                      <input type="hidden" name="delete_vehicle" value="<?php echo (int) $vehicle['id']; ?>">
                      <button type="submit" class="action-link delete-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="table-container">
      <div class="table-header">
        <h2>Bookings</h2>
      </div>
      <?php if (empty($bookings)): ?>
        <div class="empty">No bookings yet.</div>
      <?php else: ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td><?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></td>
                  <td><?php echo htmlspecialchars($booking['name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['email']); ?></td>
                  <td><?php echo htmlspecialchars($booking['start_date']); ?></td>
                  <td><?php echo htmlspecialchars($booking['end_date']); ?></td>
                  <td>
                    <?php if (($booking['status'] ?? 'active') === 'cancelled'): ?>
                      <span style="color:#9ca3af;">Cancelled</span>
                    <?php else: ?>
                      <span style="color:#34d399;font-weight:700;">Active</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($booking['created_at']); ?></td>
                  <td>
                    <?php if (($booking['status'] ?? 'active') !== 'cancelled'): ?>
                      <form method="post" action="admin.php" style="display:inline;" onsubmit="return confirm('Cancel this booking and free the vehicle?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="cancel_booking" value="<?php echo (int) $booking['id']; ?>">
                        <button type="submit" class="action-link delete-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Cancel</button>
                      </form>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
  <script>
    (function () {
      const dropzone = document.getElementById('image-dropzone');
      const fileInput = document.getElementById('image_file');
      const preview = document.getElementById('image-dropzone-preview');
      const pathInput = document.getElementById('image');

      if (!dropzone || !fileInput || !preview) {
        return;
      }

      const showSelectedFile = (file) => {
        preview.innerHTML = '';
        if (!file) {
          return;
        }
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = file.name;
        preview.appendChild(image);
        pathInput.value = '';
      };

      dropzone.addEventListener('click', () => fileInput.click());
      dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          fileInput.click();
        }
      });

      ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          dropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          dropzone.classList.remove('is-dragover');
        });
      });

      dropzone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (file) {
          showSelectedFile(file);
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          fileInput.files = dataTransfer.files;
        }
      });

      fileInput.addEventListener('change', (event) => {
        showSelectedFile(event.target.files?.[0] || null);
      });
    })();
  </script>
</body>
</html>
