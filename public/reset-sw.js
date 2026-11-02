// Script to unregister service worker and clear caches
if ('serviceWorker' in navigator) {
    // Unregister all service workers
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister();
            console.log('Service worker unregistered');
        }
        
        // Clear caches
        if ('caches' in window) {
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        return caches.delete(cacheName);
                    })
                );
            }).then(function() {
                console.log('Caches cleared');
                // Reload the page after a short delay
                setTimeout(function() {
                    window.location.reload(true);
                }, 1000);
            });
        } else {
            // Reload the page after a short delay
            setTimeout(function() {
                window.location.reload(true);
            }, 1000);
        }
    });
}
