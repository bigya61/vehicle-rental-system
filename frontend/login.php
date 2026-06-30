<?php
require_once __DIR__ . '/../backend/functions.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . (!empty($_SESSION['admin']) ? '../backend/admin.php' : 'index.php'));
    exit;
}

$error   = '';
$name    = $_POST['name']         ?? '';
$email   = $_POST['email']        ?? '';
$phoneNumber = $_POST['phone_number'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $name        = trim($name);
    $email       = trim($email);
    $password    = $_POST['password'] ?? '';
    $phoneNumber = trim($phoneNumber);

    if ($email && $password) {
        $existingUser      = getUserByEmail($pdo, $email);
        $existingPhoneUser = $phoneNumber !== '' ? getUserByPhoneNumber($pdo, $phoneNumber) : false;

        if ($existingUser) {
            // Existing account — authenticate and route by role
            if ($user = authenticateUser($pdo, $email, $password)) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'           => $user['id'],
                    'name'         => $user['name'],
                    'email'        => $user['email'],
                    'phone_number' => $user['phone_number'],
                ];
                if ($user['role'] === 'admin') {
                    $_SESSION['admin'] = true;
                } else {
                    unset($_SESSION['admin']);
                }
                setAuthCookie(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
                $defaultRedirect = $user['role'] === 'admin' ? '../backend/admin.php' : 'index.php';
                $redirect = safeRedirectPath($_SESSION['redirect_after_login'] ?? null, $defaultRedirect);
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            }
            $error = 'Invalid login credentials.';
        } else {
            // New account registration
            if ($name === '' || $phoneNumber === '') {
                $error = 'New accounts require a full name and phone number.';
            } elseif ($existingPhoneUser) {
                $error = 'That phone number is already registered to another account.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                createUser($pdo, $name, $email, $passwordHash, $phoneNumber);
                $user = getUserByEmail($pdo, $email);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'           => $user['id'],
                    'name'         => $user['name'],
                    'email'        => $user['email'],
                    'phone_number' => $user['phone_number'],
                ];
                unset($_SESSION['admin']);
                setAuthCookie(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
                $redirect = safeRedirectPath($_SESSION['redirect_after_login'] ?? null, 'index.php');
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            }
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign In — Vehicle Rental</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <h1>Vehicle Rental</h1>
    <nav>
      <a href="index.php">Home</a>
    </nav>
  </header>

  <main>
    <div class="login-container">

      <!-- Left: info panel -->
      <div class="login-left">
        <div class="login-promo">
          <span class="section-pill">SINGLE SIGN-IN</span>
          <h2>One page for customers and admins</h2>
          <p>Enter your credentials and the system routes you automatically. Admins land on the dashboard; customers land on the booking site.</p>

          <div class="login-role-cards">
            <div class="login-role-card">
              <div class="login-role-icon">👤</div>
              <div>
                <strong>Customer</strong>
                <p>Browse vehicles and manage your bookings.</p>
              </div>
            </div>
            <div class="login-role-card login-role-card--admin">
              <div class="login-role-icon">🛡</div>
              <div>
                <strong>Administrator</strong>
                <p>Manage the fleet, view all bookings, and control the platform.</p>
              </div>
            </div>
          </div>

          <div class="promo-features">
            <div class="feature-item">
              <h4>✓ Automatic role detection</h4>
              <p>No separate admin URL needed — your role is detected from your credentials.</p>
            </div>
            <div class="feature-item">
              <h4>✓ Register in the same form</h4>
              <p>New customers can create an account by filling in name and phone number below.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: form -->
      <div class="login-right">
        <div class="login-card">
          <div class="login-card-header">
            <span class="login-badge">SIGN IN OR REGISTER</span>
            <h3>Welcome back</h3>
            <p>Existing accounts need only email and password. New users also fill in name and phone number.</p>
          </div>

          <?php if (!empty($error)): ?>
            <p class="message error-msg"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>

          <form method="post" action="login.php" class="login-form">
            <?php echo csrfField(); ?>

            <div class="form-group">
              <label for="email">Email Address <span class="field-required">*</span></label>
              <input type="email" id="email" name="email" placeholder="you@example.com"
                     required value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
              <label for="password">Password <span class="field-required">*</span></label>
              <input type="password" id="password" name="password"
                     placeholder="Enter your password" required>
            </div>

            <div class="login-divider">
              <span>New account? Also fill in below</span>
            </div>

            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" id="name" name="name"
                     placeholder="Required for new accounts only"
                     value="<?php echo htmlspecialchars($name); ?>">
            </div>

            <div class="form-group">
              <label for="phone_number">Phone Number</label>
              <input type="tel" id="phone_number" name="phone_number"
                     placeholder="Required for new accounts only"
                     value="<?php echo htmlspecialchars($phoneNumber); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-large">Sign In / Register</button>
          </form>

          <p class="login-footer">Admin credentials redirect to the dashboard automatically.</p>
        </div>
      </div>

    </div>
  </main>

  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
