<?php
// Start session
session_start();

// Debug output
echo "<h1>Session Debug</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<p>User is logged in with ID: " . $_SESSION['user_id'] . "</p>";
    
    // Add a logout link
    echo "<p><a href='logout.php'>Logout</a></p>";
    
    // Add links to test pages
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    echo "<p><a href='profile.php'>Go to Profile (redirect)</a></p>";
    echo "<p><a href='profile-direct.php'>Go to Profile Direct</a></p>";
} else {
    echo "<p>User is NOT logged in</p>";
    
    // Add a login link
    echo "<p><a href='login.php'>Login</a></p>";
}
?>
