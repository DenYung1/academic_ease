<?php
/**
 * Academic Ease - Reset Cache
 * This simple tool clears the service worker cache and unregisters service workers
 */

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Cache - Academic Ease</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Reset Cache</h4>
                    </div>
                    <div class="card-body">
                        <p>Click the button below to clear the service worker cache and reset your browser's cache.</p>
                        <div id="status" class="alert alert-info d-none my-3"></div>
                        <button id="resetBtn" class="btn btn-primary">Reset Cache</button>
                        <a href="index.php" class="btn btn-outline-secondary ms-2">Return to Homepage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('resetBtn').addEventListener('click', async function() {
            const statusEl = document.getElementById('status');
            statusEl.classList.remove('d-none');
            statusEl.textContent = 'Clearing cache...';
            
            try {
                // Unregister service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                    }
                }
                
                // Clear caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map(cacheName => caches.delete(cacheName)));
                }
                
                statusEl.className = 'alert alert-success my-3';
                statusEl.innerHTML = 'Cache cleared successfully! <br>Please <a href="index.php" class="alert-link">return to the homepage</a> and try again.';
            } catch (error) {
                statusEl.className = 'alert alert-danger my-3';
                statusEl.textContent = 'Error clearing cache: ' + error.message;
            }
        });
    </script>
</body>
</html>
