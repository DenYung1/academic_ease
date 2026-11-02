<?php
// Enable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Admin Connection Test</h1>";

// Test 1: Check if we can access the includes directory
echo "<h2>Test 1: File Path Check</h2>";
$includes_path = '../includes';
if (file_exists($includes_path)) {
    echo "<p style='color:green'>âœ“ Includes directory exists at: {$includes_path}</p>";
    
    // Check specific files
    if (file_exists("{$includes_path}/config.php")) {
        echo "<p style='color:green'>âœ“ config.php file exists</p>";
    } else {
        echo "<p style='color:red'>âœ— config.php file does not exist</p>";
    }
    
    if (file_exists("{$includes_path}/db_connect.php")) {
        echo "<p style='color:green'>âœ“ db_connect.php file exists</p>";
    } else {
        echo "<p style='color:red'>âœ— db_connect.php file does not exist</p>";
    }
} else {
    echo "<p style='color:red'>âœ— Includes directory does not exist at: {$includes_path}</p>";
    
    // Try alternative paths
    $alt_paths = [
        '../../includes',
        './includes',
        '/includes',
        '../'
    ];
    
    echo "<p>Checking alternative paths:</p>";
    echo "<ul>";
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            echo "<li style='color:green'>{$path} exists</li>";
            
            // Check for config file
            if (file_exists("{$path}/config.php")) {
                echo "<li style='color:green'>- {$path}/config.php exists</li>";
            }
            
            // Check for db_connect file
            if (file_exists("{$path}/db_connect.php")) {
                echo "<li style='color:green'>- {$path}/db_connect.php exists</li>";
            }
        } else {
            echo "<li style='color:red'>{$path} does not exist</li>";
        }
    }
    echo "</ul>";
}

// Test 2: Try to include the files without executing them
echo "<h2>Test 2: File Content Check</h2>";
try {
    // Get config.php content without executing
    $config_content = @file_get_contents("{$includes_path}/config.php");
    if ($config_content !== false) {
        echo "<p style='color:green'>âœ“ Successfully read config.php content</p>";
        echo "<p>First 100 characters: " . htmlspecialchars(substr($config_content, 0, 100)) . "...</p>";
    } else {
        echo "<p style='color:red'>âœ— Could not read config.php content</p>";
    }
    
    // Get db_connect.php content without executing
    $db_content = @file_get_contents("{$includes_path}/db_connect.php");
    if ($db_content !== false) {
        echo "<p style='color:green'>âœ“ Successfully read db_connect.php content</p>";
        echo "<p>First 100 characters: " . htmlspecialchars(substr($db_content, 0, 100)) . "...</p>";
    } else {
        echo "<p style='color:red'>âœ— Could not read db_connect.php content</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
}

// Test 3: Server Information
echo "<h2>Test 3: Server Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p>Current Directory: " . getcwd() . "</p>";

// Test 4: Directory Structure
echo "<h2>Test 4: Directory Structure</h2>";
function list_directory($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                if (is_dir("$dir/$file")) {
                    echo "<li>ðŸ“ $file</li>";
                } else {
                    echo "<li>ðŸ“„ $file</li>";
                }
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Cannot list directory: $dir (not a directory)</p>";
    }
}

echo "<p>Contents of current directory:</p>";
list_directory(".");

echo "<p>Contents of parent directory:</p>";
list_directory("..");

echo "<p>Contents of includes directory (if exists):</p>";
list_directory("../includes");
?>
