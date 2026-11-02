// DEVELOPMENT MODE: Service Worker Disabled
// This is a minimal service worker that doesn't cache anything
// Re-enable the full service worker when ready for production

// Current timestamp to help with cache busting
const TIMESTAMP = new Date().toISOString();
console.log(`[Service Worker] Initialized in development mode at ${TIMESTAMP}`);

// Install event - just activate immediately
self.addEventListener('install', () => {
  console.log('[Service Worker] Install event - skipping waiting');
  self.skipWaiting();
});

// Activate event - claim clients and clear all caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activate event - clearing all caches');
  
  // Clear all caches
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          console.log(`[Service Worker] Deleting cache: ${cacheName}`);
          return caches.delete(cacheName);
        })
      );
    }).then(() => {
      console.log('[Service Worker] All caches cleared');
      return self.clients.claim();
    })
  );
});

// Fetch event - always go to network, never cache
self.addEventListener('fetch', event => {
  // Simply pass all requests through to the network
  // This effectively disables all caching
  event.respondWith(
    fetch(event.request, { 
      redirect: 'follow',
      credentials: 'same-origin',
      mode: 'cors',
      cache: 'no-store' // Tell the browser not to cache this request
    }).catch(error => {
      console.error('[Service Worker] Fetch error:', error);
      throw error;
    })
  );
});
