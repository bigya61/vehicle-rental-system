<?php
require_once __DIR__ . '/functions.php';

if (!empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

$_SESSION['redirect_after_login'] = '../backend/admin.php';
header('Location: ../frontend/login.php');
exit;
