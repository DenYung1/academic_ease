<?php
/**
 * Academic Ease - Subscription Redirect
 * This file redirects to subscribe-direct.php while preserving query parameters
 */

// Start session
session_start();

// Preserve any query parameters
$query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';

// Redirect to the direct subscription page
header('Location: subscribe-direct.php' . $query);
exit;