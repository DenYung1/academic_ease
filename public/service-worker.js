// Last updated: 2025-05-09 00:31:00
// PRODUCTION MODE: Service Worker Enabled with Caching

// Cache names with version numbers to help with cache busting
const STATIC_CACHE_NAME = 'academic-ease-static-v1';
const DYNAMIC_CACHE_NAME = 'academic-ease-dynamic-v1';
const TIMESTAMP = new Date().toISOString();

// Resources to cache immediately on install
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/offline.html',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/img/logo.png',
  '/assets/img/favicon.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Pages that should bypass the service worker caching
const BYPASS_PAGES = [
  '/login.php',
  '/register.php',
  '/dashboard.php',
  '/study.php',
  '/ai-assistant.php',
  '/progress.php',
  '/profile.php',
  '/subscribe-direct.php',
  '/quizzes.php',
  '/quiz.php',
  '/logout.php',
  '/settings.php',
  '/schedule.php',
  '/parent-dashboard.php',
  '/link-student.php'
];

console.log(`[Service Worker] Initialized in production mode at ${TIMESTAMP}`);

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('[Service Worker] Install event - caching static assets');
  
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('[Service Worker] Static assets cached successfully');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('[Service Worker] Cache error:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activate event - cleaning up old caches');
  
  const currentCaches = [STATIC_CACHE_NAME, DYNAMIC_CACHE_NAME];
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (!currentCaches.includes(cacheName)) {
              console.log(`[Service Worker] Deleting old cache: ${cacheName}`);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[Service Worker] Cache cleanup complete');
        return self.clients.claim();
      })
  );
});

// Helper function to check if a URL should bypass caching
function shouldBypassCache(url) {
  const urlPath = new URL(url).pathname;
  
  // Check if the URL matches any of the bypass patterns
  return BYPASS_PAGES.some(page => urlPath.includes(page));
}

// Fetch event - implement cache strategy with special handling for certain pages
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = request.url;
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Special handling for pages that should bypass caching
  if (shouldBypassCache(url)) {
    console.log(`[Service Worker] Bypassing cache for: ${url}`);
    
    // Special handling for pages that involve redirects - let the browser handle them naturally
    if (url.includes('/logout.php') || url.includes('/dashboard.php') || url.includes('/login.php')) {
      console.log(`[Service Worker] Special handling for page with redirects: ${url}`);
      // Don't use respondWith - let the browser handle it naturally
      return;
    }
    
    event.respondWith(
      fetch(request, {
        redirect: 'follow',
        credentials: 'include',
        mode: 'no-cors'
      }).catch(error => {
        console.error(`[Service Worker] Fetch error for ${url}:`, error);
        return caches.match('/offline.html');
      })
    );
    return;
  }
  
  // Cache-first strategy for static assets
  if (url.includes('/assets/') || url.includes('cdn.jsdelivr.net')) {
    event.respondWith(
      caches.match(request)
        .then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          return fetch(request)
            .then(networkResponse => {
              // Cache the fetched response
              const responseToCache = networkResponse.clone();
              
              caches.open(STATIC_CACHE_NAME)
                .then(cache => {
                  cache.put(request, responseToCache);
                });
                
              return networkResponse;
            });
        })
        .catch(() => {
          // If both cache and network fail, show offline page
          if (request.headers.get('Accept').includes('text/html')) {
            return caches.match('/offline.html');
          }
        })
    );
    return;
  }
  
    // Function to check if a URL is cacheable
  function isCacheableUrl(url) {
    // Don't cache URLs with these characteristics
    return url && 
           typeof url === 'string' && 
           !url.includes('/api/') && 
           !url.includes('?') && 
           !url.startsWith('chrome-extension:') &&
           !url.startsWith('chrome:') &&
           !url.startsWith('chrome-search:') &&
           !url.startsWith('devtools:') &&
           (url.startsWith('http:') || url.startsWith('https:'));
  }

  // Network-first strategy for everything else
  event.respondWith(
    fetch(request, {
      redirect: 'follow',
      credentials: 'same-origin',
      mode: 'cors'
    })
      .then(networkResponse => {
        // Only cache successful responses from cacheable URLs
        if (networkResponse.status === 200) {
          const requestUrl = request.url || '';
          
          if (isCacheableUrl(requestUrl)) {
            const responseToCache = networkResponse.clone();
            
            caches.open(DYNAMIC_CACHE_NAME)
              .then(cache => {
                try {
                  cache.put(request, responseToCache);
                } catch (error) {
                  console.error('Cache storage error:', error);
                }
              });
          }
        }
        
        return networkResponse;
      })
      .catch(() => {
        // If network fails, try the cache
        return caches.match(request)
          .then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            
            // If the request is for an HTML page, show offline page
            if (request.headers.get('Accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
          });
      })
  );
});
