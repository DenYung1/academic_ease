<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers to prevent caching of this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Caching - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Academic Ease - Cache Management</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-speedometer2 text-primary" style="font-size: 4rem;"></i>
                            <h2 class="mt-3">Enabling Performance Caching</h2>
                            <p class="lead">This will improve page loading speed and overall performance.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> We're updating your browser's cache settings to improve performance. This will only take a moment.
                        </div>
                        
                        <div id="status" class="alert alert-secondary">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border text-primary me-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div>
                                    <strong>Processing...</strong>
                                    <p class="mb-0" id="statusMessage">Checking service worker registration...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="actions" class="text-center mt-4" style="display: none;">
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-house me-2"></i> Go to Homepage
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary btn-lg ms-2">
                                <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update the status message
        function updateStatus(message, type = 'secondary') {
            const statusElement = document.getElementById('status');
            const statusMessageElement = document.getElementById('statusMessage');
            
            statusElement.className = `alert alert-${type}`;
            statusMessageElement.textContent = message;
        }
        
        // Show completion and action buttons
        function showCompletion(success) {
            const statusElement = document.getElementById('status');
            const actionsElement = document.getElementById('actions');
            
            // Remove spinner
            statusElement.innerHTML = success ? 
                '<i class="bi bi-check-circle-fill text-success me-2" style="font-size: 1.5rem;"></i> <strong>Success!</strong> <p class="mb-0">Caching has been enabled. Your pages should now load faster.</p>' :
                '<i class="bi bi-exclamation-triangle-fill text-warning me-2" style="font-size: 1.5rem;"></i> <strong>Notice:</strong> <p class="mb-0">Caching setup completed with some warnings. Most features should work correctly.</p>';
            
            statusElement.className = success ? 'alert alert-success' : 'alert alert-warning';
            
            // Show action buttons
            actionsElement.style.display = 'block';
        }
        
        // Main function to enable caching
        async function enableCaching() {
            try {
                // Check if service workers are supported
                if (!('serviceWorker' in navigator)) {
                    updateStatus('Your browser does not support service workers. Some features may not work correctly.', 'warning');
                    showCompletion(false);
                    return;
                }
                
                updateStatus('Unregistering any existing service workers...');
                
                // Unregister any existing service workers
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const registration of registrations) {
                    await registration.unregister();
                    console.log('Service worker unregistered:', registration);
                }
                
                updateStatus('Clearing browser caches...');
                
                // Clear all caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(
                        cacheNames.map(cacheName => caches.delete(cacheName))
                    );
                    console.log('All caches cleared');
                }
                
                updateStatus('Registering new service worker...');
                
                // Register the new service worker
                const registration = await navigator.serviceWorker.register('/service-worker.js', {
                    scope: '/'
                });
                
                console.log('Service worker registered:', registration);
                
                updateStatus('Service worker registered successfully!', 'success');
                
                // Wait a moment to show completion
                setTimeout(() => {
                    showCompletion(true);
                }, 1000);
                
            } catch (error) {
                console.error('Service worker registration failed:', error);
                updateStatus(`Error: ${error.message}. Some features may not work correctly.`, 'danger');
                showCompletion(false);
            }
        }
        
        // Start the process when the page loads
        document.addEventListener('DOMContentLoaded', enableCaching);
    </script>
</body>
</html>
