<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Service Worker - Academic Ease</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Fix Service Worker</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p>This page will help fix service worker issues that might be causing redirect problems.</p>
                        </div>
                        
                        <div class="mb-4">
                            <h4>Step 1: Unregister Current Service Worker</h4>
                            <button id="unregisterBtn" class="btn btn-warning">Unregister Service Worker</button>
                            <div id="unregisterStatus" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-4">
                            <h4>Step 2: Clear Cache</h4>
                            <button id="clearCacheBtn" class="btn btn-danger">Clear Cache</button>
                            <div id="clearCacheStatus" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-4">
                            <h4>Step 3: Reload Application</h4>
                            <a href="index.php" class="btn btn-success">Go to Homepage</a>
                            <a href="login.php" class="btn btn-primary ms-2">Go to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Function to unregister service worker
        document.getElementById('unregisterBtn').addEventListener('click', async () => {
            const statusElement = document.getElementById('unregisterStatus');
            statusElement.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Unregistering service worker...';
            
            try {
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    
                    if (registrations.length === 0) {
                        statusElement.innerHTML = '<div class="alert alert-info">No service workers found to unregister.</div>';
                        return;
                    }
                    
                    for (let registration of registrations) {
                        await registration.unregister();
                    }
                    
                    statusElement.innerHTML = '<div class="alert alert-success">Service worker successfully unregistered!</div>';
                } else {
                    statusElement.innerHTML = '<div class="alert alert-warning">Service Worker API not supported in this browser.</div>';
                }
            } catch (error) {
                statusElement.innerHTML = `<div class="alert alert-danger">Error unregistering service worker: ${error.message}</div>`;
                console.error('Service Worker unregister error:', error);
            }
        });
        
        // Function to clear cache
        document.getElementById('clearCacheBtn').addEventListener('click', async () => {
            const statusElement = document.getElementById('clearCacheStatus');
            statusElement.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Clearing caches...';
            
            try {
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(
                        cacheNames.map(cacheName => caches.delete(cacheName))
                    );
                    statusElement.innerHTML = '<div class="alert alert-success">All caches cleared successfully!</div>';
                } else {
                    statusElement.innerHTML = '<div class="alert alert-warning">Cache API not supported in this browser.</div>';
                }
            } catch (error) {
                statusElement.innerHTML = `<div class="alert alert-danger">Error clearing caches: ${error.message}</div>`;
                console.error('Cache clear error:', error);
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
