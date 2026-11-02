<?php
/**
 * Academic Ease - Plan Selection Handler
 * This file stores the selected plan in a session and redirects to registration/login
 */

// Start session
session_start();

// Get the selected plan from the URL
$plan = isset($_GET['plan']) ? $_GET['plan'] : '';

// Store the selected plan in the session
if ($plan) {
    $_SESSION['selected_plan'] = $plan;
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to subscription page with the selected plan
    header('Location: subscribe-direct.php?plan=' . $plan);
} else {
    // User is not logged in, redirect to registration page
    header('Location: register.php');
}
exit;
?>
