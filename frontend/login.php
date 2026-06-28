<?php
require_once __DIR__ . '/../backend/functions.php';

$error = '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $name = trim($name);
    $email = trim($email);
    $password = $_POST['password'] ?? '';

    if ($name && $email && $password) {
        $existingUser = getUserByEmail($pdo, $email);

        if ($existingUser) {
            if ($user = authenticateUser($pdo, $email, $password)) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']];
                if ($user['role'] === 'admin') {
                    $_SESSION['admin'] = true;
                }
                setAuthCookie(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
                $redirect = safeRedirectPath($_SESSION['redirect_after_login'] ?? null, 'index.php');
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            }

            $error = 'Invalid login credentials.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            createUser($pdo, $name, $email, $passwordHash, $phone);
            $user = getUserByEmail($pdo, $email);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']];
            setAuthCookie(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
            $redirect = safeRedirectPath($_SESSION['redirect_after_login'] ?? null, 'index.php');
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    } else {
        $error = 'Please provide your name, email, and password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - Vehicle Rental</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header class="page-header">
    <h1>Save Your Details</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="../backend/admin-login.php">Admin</a>
    </nav>
  </header>
  <main>
    <div class="login-container">
      <div class="login-left">
        <div class="login-promo">
          <span class="section-pill">QUICK CHECKOUT</span>
          <h2>Save your details for faster bookings</h2>
          <p>Store your information once and breeze through bookings on your next trip. No hassle, just smooth reservations.</p>
          
          <div class="promo-features">
            <div class="feature-item">
              <h4>✓ Fast Checkout</h4>
              <p>Your details auto-fill on every booking form</p>
            </div>
            <div class="feature-item">
              <h4>✓ Always Updated</h4>
              <p>Modify your info anytime, instantly</p>
            </div>
            <div class="feature-item">
              <h4>✓ Secure & Private</h4>
              <p>Your data is encrypted and kept safe</p>
            </div>
          </div>
        </div>
      </div>

      <div class="login-right">
        <div class="login-card">
          <?php if (!empty($error)): ?>
            <p class="message"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>

          <form method="post" action="login.php" class="login-form">
            <?php echo csrfField(); ?>
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" id="name" name="name" placeholder="Enter your name" required value="<?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="form-group">
    <label for="phone">Phone Number</label>

    <input
      type="tel" id="phone" name="phone" placeholder="Enter your phone number"
      required value="<?php echo htmlspecialchars($_SESSION['user']['phone'] ?? ''); ?>"
    >
  </div>

            <button type="submit" class="btn btn-primary btn-large">Save Details</button>
          </form>

          <p class="login-footer">Already saved? <a href="index.php">Continue browsing</a></p>
        </div>
      </div>
    </div>
  </main>
  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
