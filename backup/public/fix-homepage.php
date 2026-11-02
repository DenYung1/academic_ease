<?php
// Start session
session_start();

// Clear session data
session_unset();
session_destroy();

// Redirect to homepage
header('Location: index.php');
exit;
?>
