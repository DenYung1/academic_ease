<?php
/**
 * Academic Ease - Fix Subscription Links
 * This tool helps fix issues with subscription links by clearing service worker caches
 * and creating a redirect for subscribe.php to subscribe-direct.php
 */

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Ease - Fix Subscription Links</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 50px;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Academic Ease - Fix Subscription Links</h4>
                    </div>
                    <div class="card-body">
                        <p>This tool will fix issues with subscription links by:</p>
                        <ol>
                            <li>Unregistering service workers</li>
                            <li>Clearing all caches</li>
                            <li>Creating a redirect from subscribe.php to subscribe-direct.php</li>
                        </ol>
                        
                        <div id="status" class="status d-none"></div>
                        
                        <div class="d-grid gap-2">
                            <button id="fixLinksBtn" class="btn btn-primary">Fix Subscription Links</button>
                            <a href="index.php" class="btn btn-outline-secondary">Return to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('fixLinksBtn').addEventListener('click', async function() {
            const statusElement = document.getElementById('status');
            statusElement.classList.remove('d-none', 'success', 'error');
            statusElement.innerHTML = '<p>Working... Please wait.</p>';
            
            try {
                // Step 1: Unregister service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                    }
                }
                
                // Step 2: Clear caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map(cacheName => caches.delete(cacheName)));
                }
                
                // Step 3: Create a redirect file (this happens server-side)
                const response = await fetch('create-redirect.php');
                const result = await response.text();
                
                statusElement.classList.add('success');
                statusElement.innerHTML = `
                    <p><strong>Success!</strong> Service worker unregistered and caches cleared.</p>
                    <p>The subscription links should now work correctly.</p>
                    <p>Please try clicking on the "Choose Plan" buttons on the homepage again.</p>
                    <p>If you're still having issues, try closing and reopening your browser.</p>
                `;
            } catch (error) {
                statusElement.classList.add('error');
                statusElement.innerHTML = `
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p>Please try again or manually clear your browser cache.</p>
                `;
                console.error('Fix links error:', error);
            }
        });
    </script>
</body>
</html>
