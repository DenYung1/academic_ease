<?php
// Start session
session_start();

// Clear session data
session_unset();
session_destroy();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Cache - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Academic Ease - Fix Cache</h4>
                    </div>
                    <div class="card-body">
                        <h5>Fixing Browser Cache and Service Worker Issues</h5>
                        <p>This tool will help fix any caching issues with the Academic Ease platform.</p>
                        
                        <div class="alert alert-info">
                            <strong>Step 1:</strong> Your session has been cleared.
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Step 2:</strong> Please click the button below to unregister service workers and clear browser caches.
                        </div>
                        
                        <button id="fixButton" class="btn btn-primary mb-3">Fix Service Worker & Clear Cache</button>
                        
                        <div id="status" class="alert alert-secondary d-none">
                            Working...
                        </div>
                        
                        <div class="mt-4">
                            <p>After fixing, please try:</p>
                            <a href="login.php" class="btn btn-outline-primary me-2">Go to Login</a>
                            <a href="index.php" class="btn btn-outline-secondary">Go to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.getElementById('fixButton').addEventListener('click', async function() {
            const statusDiv = document.getElementById('status');
            statusDiv.classList.remove('d-none');
            statusDiv.classList.remove('alert-success', 'alert-danger');
            statusDiv.classList.add('alert-secondary');
            statusDiv.innerHTML = 'Working...';
            
            try {
                // Clear caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(
                        cacheNames.map(cacheName => caches.delete(cacheName))
                    );
                }
                
                // Unregister service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    await Promise.all(
                        registrations.map(registration => registration.unregister())
                    );
                }
                
                statusDiv.classList.remove('alert-secondary');
                statusDiv.classList.add('alert-success');
                statusDiv.innerHTML = 'Success! Service workers unregistered and caches cleared. You can now log in again.';
            } catch (error) {
                console.error('Error:', error);
                statusDiv.classList.remove('alert-secondary');
                statusDiv.classList.add('alert-danger');
                statusDiv.innerHTML = 'Error: ' + error.message;
            }
        });
    </script>
</body>
</html>
