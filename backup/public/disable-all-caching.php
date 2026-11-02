<?php
/**
 * Academic Ease - Complete Cache Disabler
 * This tool completely disables all caching for development purposes
 */

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Force a timestamp parameter to bust cache
$cache_buster = time();

// Copy the disabled service worker to the main service worker location
$source = __DIR__ . '/service-worker-disabled.js';
$destination = __DIR__ . '/service-worker.js';

$success = false;
$error = '';

try {
    if (file_exists($source)) {
        // Read the content of the disabled service worker
        $content = file_get_contents($source);
        
        // Add a timestamp to ensure it's always fresh
        $content = "// Last updated: " . date('Y-m-d H:i:s') . "\n" . $content;
        
        // Write it to the main service worker file
        if (file_put_contents($destination, $content) !== false) {
            $success = true;
        } else {
            $error = "Failed to write to service-worker.js";
        }
    } else {
        $error = "Source file service-worker-disabled.js not found";
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Ease - Disable All Caching</title>
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
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">Academic Ease - Disable All Caching</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Development Mode Only!</strong> This tool completely disables all caching, which means offline functionality will not work. Use this only during development.
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Success!</strong> Service worker has been replaced with a non-caching version.
                            </div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <p>This tool will:</p>
                        <ol>
                            <li>Replace the service worker with a version that doesn't cache anything</li>
                            <li>Unregister all existing service workers</li>
                            <li>Clear all browser caches</li>
                            <li>Clear localStorage and sessionStorage</li>
                        </ol>
                        
                        <div id="status" class="status d-none"></div>
                        
                        <div class="d-grid gap-2">
                            <button id="disableBtn" class="btn btn-danger">Disable All Caching</button>
                            <a href="index.php?cache_bust=<?php echo $cache_buster; ?>" class="btn btn-outline-secondary">Return to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('disableBtn').addEventListener('click', async function() {
            const statusElement = document.getElementById('status');
            statusElement.classList.remove('d-none', 'success', 'error');
            statusElement.innerHTML = '<p>Working... Please wait.</p>';
            
            try {
                // Step 1: Unregister all service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                        console.log('Service worker unregistered:', registration);
                    }
                }
                
                // Step 2: Clear all caches
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map(cacheName => {
                        console.log('Deleting cache:', cacheName);
                        return caches.delete(cacheName);
                    }));
                }
                
                // Step 3: Clear localStorage and sessionStorage
                localStorage.clear();
                console.log('localStorage cleared');
                
                sessionStorage.clear();
                console.log('sessionStorage cleared');
                
                // Step 4: Add cache busting parameter to all links
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
                    <p><strong>Success!</strong> All caching has been disabled:</p>
                    <ul>
                        <li>Service worker replaced with non-caching version</li>
                        <li>All service workers unregistered</li>
                        <li>All browser caches cleared</li>
                        <li>Local and session storage cleared</li>
                    </ul>
                    <p>You will be redirected to the homepage in 3 seconds...</p>
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
                console.error('Error disabling caching:', error);
            }
        });
    </script>
</body>
</html>
