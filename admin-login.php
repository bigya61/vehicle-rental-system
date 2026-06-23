<?php
$query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: backend/admin-login.php' . $query);
exit;
