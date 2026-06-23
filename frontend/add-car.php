<?php
// Car listing is admin-only. This page is retained as a redirect so old
// links/bookmarks land somewhere sensible instead of 404ing.
require_once __DIR__ . '/../backend/functions.php';

header('Location: index.php');
exit;
