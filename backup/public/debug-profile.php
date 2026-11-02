<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Log session information
error_log('DEBUG PROFILE - Session ID: ' . session_id());
error_log('DEBUG PROFILE - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
error_log('DEBUG PROFILE - Is logged in: ' . ($is_logged_in ? 'Yes' : 'No'));

// Get server and request information
$request_uri = $_SERVER['REQUEST_URI'];
$http_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Not set';
error_log('DEBUG PROFILE - Request URI: ' . $request_uri);
error_log('DEBUG PROFILE - HTTP Referer: ' . $http_referer);

// Check for service worker
$has_service_worker = isset($_SERVER['HTTP_SERVICE_WORKER']);
error_log('DEBUG PROFILE - Has Service Worker: ' . ($has_service_worker ? 'Yes' : 'No'));

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Try to get user data if logged in
$user_data = 'Not logged in';
if ($is_logged_in) {
    try {
        // Direct query using the service role key
        $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
        $users = supabaseRequest($endpoint, 'GET', null, true);
        
        if (!empty($users)) {
            $user_data = json_encode($users[0]);
            error_log('DEBUG PROFILE - User data: ' . $user_data);
        } else {
            error_log('DEBUG PROFILE - User not found in database');
        }
    } catch (Exception $e) {
        error_log('DEBUG PROFILE - Error getting user data: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Profile - Academic Ease</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Profile Page Diagnostic Tool</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p>This page displays diagnostic information about your profile page access. This will help us identify why redirects are happening.</p>
                        </div>
                        
                        <h4 class="mb-3">Session Information</h4>
                        <div class="mb-4">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                                    <p><strong>Logged In:</strong> <?php echo $is_logged_in ? 'Yes' : 'No'; ?></p>
                                    <p><strong>Session Data:</strong></p>
                                    <pre class="mb-0"><?php print_r($_SESSION); ?></pre>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">Request Information</h4>
                        <div class="mb-4">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <p><strong>Request URI:</strong> <?php echo htmlspecialchars($request_uri); ?></p>
                                    <p><strong>HTTP Referer:</strong> <?php echo htmlspecialchars($http_referer); ?></p>
                                    <p><strong>Service Worker:</strong> <?php echo $has_service_worker ? 'Yes' : 'No'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">User Data</h4>
                        <div class="mb-4">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <pre class="mb-0"><?php echo htmlspecialchars($user_data); ?></pre>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">Browser Information</h4>
                        <div class="mb-4">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <p><strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Not available'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">Test Links</h4>
                        <div class="mb-4">
                            <a href="dashboard.php" class="btn btn-primary me-2">Go to Dashboard</a>
                            <a href="profile-direct.php" class="btn btn-success me-2">Try Profile Direct</a>
                            <a href="profile-test.php" class="btn btn-info me-2">Try Profile Test</a>
                            <a href="fix-service-worker.html" class="btn btn-warning">Fix Service Worker</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Check if service worker is registered
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                const serviceWorkerInfo = document.createElement('div');
                serviceWorkerInfo.className = 'card mt-4';
                serviceWorkerInfo.innerHTML = `
                    <div class="card-header">
                        <h5 class="mb-0">Service Worker Information</h5>
                    </div>
                    <div class="card-body bg-light">
                        <p><strong>Service Workers Registered:</strong> ${registrations.length}</p>
                        <ul>
                            ${registrations.map(reg => `<li>Scope: ${reg.scope}</li>`).join('')}
                        </ul>
                    </div>
                `;
                document.querySelector('.card-body').appendChild(serviceWorkerInfo);
            });
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
