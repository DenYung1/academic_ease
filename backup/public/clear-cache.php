<?php
/**
 * Academic Ease - Cache Clearing Tool
 * This tool helps clear service worker caches and unregister service workers
 * Use during development to see changes immediately without having to clear browser cookies
 */

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Force a timestamp parameter to bust cache
$cache_buster = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Ease - Clear Cache</title>
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
                        <h4 class="mb-0">Academic Ease - Clear Cache Tool</h4>
                    </div>
                    <div class="card-body">
                        <p>This tool helps you clear the service worker cache and unregister service workers. Use this during development to see your changes immediately without having to clear browser cookies.</p>
                        
                        <div id="status" class="status d-none"></div>
                        
                        <div class="d-grid gap-2">
                            <button id="clearCacheBtn" class="btn btn-primary">Clear Cache & Unregister Service Worker</button>
                            <a href="index.php" class="btn btn-outline-secondary">Return to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('clearCacheBtn').addEventListener('click', async function() {
            const statusElement = document.getElementById('status');
            statusElement.classList.remove('d-none', 'success', 'error');
            statusElement.innerHTML = '<p>Working... Please wait.</p>';
            
            try {
                // Unregister service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                        console.log('Service worker unregistered:', registration);
                    }
                }
                
                // Clear all caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map(cacheName => {
                        console.log('Deleting cache:', cacheName);
                        return caches.delete(cacheName);
                    }));
                }
                
                // Clear localStorage
                localStorage.clear();
                console.log('localStorage cleared');
                
                // Clear sessionStorage
                sessionStorage.clear();
                console.log('sessionStorage cleared');
                
                // Add cache busting parameter to all links
                const timestamp = new Date().getTime();
                const links = document.querySelectorAll('a[href]');
                links.forEach(link => {
                    const url = new URL(link.href, window.location.origin);
                    if (url.pathname.endsWith('.php')) {
                        url.searchParams.set('cache_bust', timestamp);
                        link.href = url.toString();
                    }
                });
                
                statusElement.classList.add('success');
                statusElement.innerHTML = `
                    <p><strong>Success!</strong> All caches have been cleared:</p>
                    <ul>
                        <li>Service workers unregistered</li>
                        <li>Browser caches cleared</li>
                        <li>Local storage cleared</li>
                        <li>Session storage cleared</li>
                    </ul>
                    <p>You will be redirected to the homepage with cache busting in 3 seconds...</p>
                    <p>If you're still having issues, please try closing and reopening your browser.</p>
                `;
                
                // Redirect to homepage with cache busting parameter
                setTimeout(() => {
                    window.location.href = 'index.php?cache_bust=' + timestamp;
                }, 3000);
                
            } catch (error) {
                statusElement.classList.add('error');
                statusElement.innerHTML = `
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p>Please try again or manually clear your browser cache.</p>
                `;
                console.error('Cache clearing error:', error);
            }
        });
    </script>
</body>
</html>
