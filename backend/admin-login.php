<?php
require_once __DIR__ . '/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        $_SESSION['user'] = ['name' => 'Admin', 'email' => 'admin@example.com'];
        header('Location: admin.php');
        exit;
    }

    $error = 'Invalid username or password.';
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
      <a href="../index.php">Home</a>
      <a href="../login.php">User Login</a>
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
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
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
