<?php
/**
 * Academic Ease - Logout Handler
 * Enhanced to bypass service worker caching issues
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout attempt
error_log('Logout attempt - Session ID: ' . session_id());
error_log('Logout attempt - User ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown'));

// Specifically clear success messages first
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Clear all session variables
$_SESSION = array();

// If a session cookie is used, clear it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set aggressive cache control headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 01 Jan 1990 00:00:00 GMT");

// Set cache control headers to prevent caching issues
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect to homepage after logout with a clean URL
header("Location: index.php");
exit;
?>
