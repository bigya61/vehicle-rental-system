<?php
// Keep search reachable from project root URLs as well.
header('Location: frontend/search.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
