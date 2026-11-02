<?php
// Start session
session_start();

// Display session information
echo "<h1>Session Debug Information</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n\n";
echo "SESSION Variables:\n";
print_r($_SESSION);
echo "</pre>";

// Display cookie information
echo "<h2>Cookie Information</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Display server information
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
echo "</pre>";

// Link to clear session
echo "<p><a href='logout.php'>Clear Session and Logout</a></p>";
echo "<p><a href='index.php'>Go to Homepage</a></p>";
?>
