<?php
/**
 * Academic Ease - Create Redirect File
 * This script creates a subscribe.php file that redirects to subscribe-direct.php
 * while preserving any query parameters
 */

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Create the subscribe.php file with redirect code
$redirect_file = __DIR__ . '/subscribe.php';
$redirect_code = <<<'EOD'
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
EOD;

// Write the file
if (file_put_contents($redirect_file, $redirect_code)) {
    echo "Redirect file created successfully!";
} else {
    echo "Error creating redirect file.";
}
?>
