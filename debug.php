<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debugging Information</h1>";

// Check if includes directory exists
echo "<h2>Directory Structure</h2>";
if (is_dir('includes')) {
    echo "âœ… includes directory exists<br>";
    
    // List files in includes directory
    echo "<h3>Files in includes directory:</h3>";
    $files = scandir('includes');
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
    
    // Check for config.php specifically
    if (file_exists('includes/config.php')) {
        echo "âœ… includes/config.php exists<br>";
    } else {
        echo "âŒ includes/config.php not found<br>";
    }
    
    // Check for db_connect.php
    if (file_exists('includes/db_connect.php')) {
        echo "âœ… includes/db_connect.php exists<br>";
    } else {
        echo "âŒ includes/db_connect.php not found<br>";
    }
} else {
    echo "âŒ includes directory not found<br>";
}

// Check PHP version and extensions
echo "<h2>PHP Environment</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Loaded Extensions:<br>";
$extensions = get_loaded_extensions();
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext</li>";
}
echo "</ul>";

// Test database connection if possible
echo "<h2>Database Connection Test</h2>";
if (file_exists('includes/config.php')) {
    echo "Attempting to include config.php...<br>";
    try {
        include_once 'includes/config.php';
        echo "âœ… config.php included successfully<br>";
        
        // Check if database constants are defined
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            echo "âœ… Database constants are defined<br>";
            echo "DB_HOST: " . DB_HOST . "<br>";
            echo "DB_NAME: " . DB_NAME . "<br>";
            echo "DB_USER: " . DB_USER . "<br>";
            
            // Try to connect to the database
            try {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    echo "âŒ Database connection failed: " . $conn->connect_error . "<br>";
                } else {
                    echo "âœ… Database connection successful<br>";
                    $conn->close();
                }
            } catch (Exception $e) {
                echo "âŒ Database connection error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "âŒ Database constants are not defined<br>";
        }
    } catch (Exception $e) {
        echo "âŒ Error including config.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Cannot test database connection because config.php doesn't exist<br>";
}

// Check for Supabase configuration
echo "<h2>Supabase Configuration</h2>";
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY')) {
    echo "âœ… Supabase constants are defined<br>";
    echo "SUPABASE_URL: " . SUPABASE_URL . "<br>";
    // Don't display the full key for security reasons
    echo "SUPABASE_KEY: " . (strlen(SUPABASE_KEY) > 10 ? substr(SUPABASE_KEY, 0, 10) . '...' : SUPABASE_KEY) . "<br>";
} else {
    echo "âŒ Supabase constants are not defined<br>";
}

echo "<h2>Current Directory Information</h2>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Current file: " . __FILE__ . "<br>";
?>
