<?php
require_once __DIR__ . '/../backend/functions.php';

unset($_SESSION['user'], $_SESSION['admin']);
header('Location: index.php');
exit;
