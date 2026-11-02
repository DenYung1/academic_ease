// This script helps disable service workers that might be causing navigation issues
if ('serviceWorker' in navigator) {
    // Unregister all service workers
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister();
            console.log('Service worker unregistered');
        }
    });
    
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
        });
    }
}

// Function to safely navigate without service worker interference
function safeNavigate(url) {
    // Temporarily disable any fetch event listeners
    if (window.disableFetchListeners) {
        window.disableFetchListeners();
    }
    
    // Use replace to avoid adding to history
    window.location.replace(url);
    return false;
}

// Add this to the global scope
window.safeNavigate = safeNavigate;
