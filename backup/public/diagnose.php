<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Function to display information in a readable format
function displaySection($title, $data) {
    echo "<div style='margin-bottom: 20px;'>";
    echo "<h2>$title</h2>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    if (is_array($data) || is_object($data)) {
        print_r($data);
    } else {
        echo htmlspecialchars($data);
    }
    echo "</pre>";
    echo "</div>";
}

// Set page title
$pageTitle = "System Diagnostics";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
        }
        .card {
            margin-bottom: 20px;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><?php echo $pageTitle; ?></h1>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Session Information</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        displaySection("Session ID", session_id());
                        displaySection("Session Variables", $_SESSION);
                        ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Database Connection Test</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Test Supabase connection
                        try {
                            // Check if we can connect to Supabase
                            $testResult = supabaseRequest('', 'GET');
                            displaySection("Supabase Connection", "Connection successful");
                            displaySection("Supabase Response", $testResult);
                            
                            // Check if SUPABASE_SERVICE_KEY is defined
                            if (defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY)) {
                                displaySection("Service Role Key", "Defined and not empty");
                            } else {
                                displaySection("Service Role Key", "Not defined or empty - This is a problem!");
                            }
                        } catch (Exception $e) {
                            displaySection("Supabase Connection Error", $e->getMessage());
                        }
                        ?>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">User Data Test</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Test user data retrieval
                        try {
                            // Try with regular key first
                            $users1 = supabaseSelect('users', [
                                'filter' => [
                                    'user_id' => 'eq.' . $_SESSION['user_id']
                                ]
                            ], false);
                            
                            displaySection("User Query (Regular Key)", [
                                'user_id' => $_SESSION['user_id'],
                                'result' => $users1
                            ]);
                            
                            // Now try with service role key
                            $users2 = supabaseSelect('users', [
                                'filter' => [
                                    'user_id' => 'eq.' . $_SESSION['user_id']
                                ]
                            ], true);
                            
                            displaySection("User Query (Service Role Key)", [
                                'user_id' => $_SESSION['user_id'],
                                'result' => $users2
                            ]);
                        } catch (Exception $e) {
                            displaySection("User Query Error", $e->getMessage());
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Environment Information</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Display PHP and server information
                        $serverInfo = [
                            'PHP Version' => phpversion(),
                            'Server Name' => $_SERVER['SERVER_NAME'],
                            'Server Port' => $_SERVER['SERVER_PORT'],
                            'Request URI' => $_SERVER['REQUEST_URI'],
                            'User Agent' => $_SERVER['HTTP_USER_AGENT'],
                            'Remote Address' => $_SERVER['REMOTE_ADDR']
                        ];
                        
                        displaySection("Server Information", $serverInfo);
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h3>Actions</h3>
                <div class="btn-group">
                    <a href="index.php" class="btn btn-primary">Home</a>
                    <a href="login.php" class="btn btn-success">Login Page</a>
                    <a href="logout.php" class="btn btn-warning">Logout</a>
                    <a href="dashboard.php" class="btn btn-info">Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
