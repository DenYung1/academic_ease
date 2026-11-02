<?php
// This file helps fix session issues by ensuring proper session configuration

// Start with a clean session
if (session_status() !== PHP_SESSION_NONE) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Set proper session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Start a new session
session_start();

// Set a test value
$_SESSION['session_test'] = 'Session is working properly';

// Redirect to the debug page
header('Location: debug-session.php');
exit;
?>
