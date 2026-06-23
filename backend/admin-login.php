<?php
require_once __DIR__ . '/functions.php';

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = getUserByEmail($pdo, $email);

    if ($user && $user['role'] === 'admin' && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
        header('Location: admin.php');
        exit;
    }

    $error = 'Invalid admin credentials.';
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login - Vehicle Rental</title>
  <link rel="stylesheet" href="../frontend/css/styles.css">
</head>
<body>
  <header class="page-header">
    <h1>Admin Login</h1>
    <nav>
      <a href="../frontend/index.php">Home</a>
      <a href="../frontend/login.php">User Login</a>
    </nav>
  </header>
  <main>
    <div class="login-container">
      <div class="login-card" style="max-width:450px;margin:3rem auto;">
        <h2>Sign in to manage vehicles</h2>
        <?php if ($error): ?>
          <p class="message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post" action="admin-login.php" class="login-form">
          <?php echo csrfField(); ?>
          <div class="form-group">
            <label for="email">Admin email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-large">Login</button>
        </form>
      </div>
    </div>
  </main>
  <footer class="page-footer">&copy; <?php echo date('Y'); ?> Vehicle Rental</footer>
</body>
</html>
