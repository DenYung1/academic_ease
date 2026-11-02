/**
 * Academic Ease - Service Worker (Fixed Version)
 * Handles PWA functionality including enhanced caching and offline support
 * With proper redirect handling
 */

const CACHE_NAME = 'academic-ease-v2';
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/login.php',
  '/register.php',
  '/dashboard.php',
  '/study.php',
  '/features.php',
  '/pricing.php',
  '/quiz.php',
  '/progress.php',
  '/schedule.php',
  '/parent-dashboard.php',
  '/subscribe.php',
  '/ai-assistant.php',
  '/offline.html',
  '/manifest.json',
  '../assets/css/style.css',
  '../assets/js/main.js',
  '../assets/img/logo.png',
  '../assets/img/favicon.png',
  '../assets/img/offline.svg',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Content to cache for offline study
const STUDY_CONTENT_CACHE = 'academic-ease-study-v2';
const QUIZ_CACHE = 'academic-ease-quiz-v2';

// Install event - cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(ASSETS_TO_CACHE);
      })
  );
  // Activate immediately
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME, STUDY_CONTENT_CACHE, QUIZ_CACHE];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // Claim clients immediately
  self.clients.claim();
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;
  
  // Skip API requests
  if (event.request.url.includes('/api/')) return;
  
  // Skip unsupported URL schemes (chrome-extension, data, etc.)
  const url = new URL(event.request.url);
  if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
  
  // Skip PHP files that might involve redirects or sessions
  if (url.pathname.endsWith('.php')) {
    // Let the browser handle these normally
    return;
  }
  
  // Handle study materials separately
  if (event.request.url.includes('/study-materials/')) {
    event.respondWith(handleStudyMaterialRequest(event.request));
    return;
  }
  
  // Handle quiz content separately
  if (event.request.url.includes('/quiz.php') || event.request.url.includes('/api/quiz/')) {
    event.respondWith(handleQuizRequest(event.request));
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached response if found
        if (response) {
          return response;
        }
        
        // Clone the request
        const fetchRequest = event.request.clone();
        
        // Make network request with redirect: 'follow'
        return fetch(fetchRequest, { 
          redirect: 'follow',
          credentials: 'same-origin'
        })
          .then(response => {
            // Check if valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // Clone the response
            const responseToCache = response.clone();
            
            // Cache the response - only for http/https URLs
            const requestUrl = new URL(event.request.url);
            if (requestUrl.protocol === 'http:' || requestUrl.protocol === 'https:') {
              caches.open(CACHE_NAME)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
            }
            
            return response;
          })
          .catch(error => {
            // Return offline fallback for HTML requests
            if (event.request.headers.get('accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
            
            console.error('Fetch failed:', error);
          });
      })
  );
});

// Handle study material requests with special caching
async function handleStudyMaterialRequest(request) {
  // Try to get from cache first
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // If not in cache, fetch from network with redirect: 'follow'
  try {
    const response = await fetch(request, { 
      redirect: 'follow',
      credentials: 'same-origin'
    });
    
    // Cache the study material for offline use
    if (response.status === 200) {
      const responseToCache = response.clone();
      const cache = await caches.open(STUDY_CONTENT_CACHE);
      await cache.put(request, responseToCache);
      
      // Store metadata about this study material in IndexedDB
      const url = new URL(request.url);
      const fileName = url.pathname.split('/').pop();
      const db = await openDatabase();
      await storeStudyMaterialMetadata(db, {
        url: request.url,
        fileName: fileName,
        cachedAt: new Date().toISOString()
      });
    }
    
    return response;
  } catch (error) {
    console.error('Failed to fetch study material:', error);
    // Return generic fallback for PDFs if available
    return caches.match('/offline-study-fallback.pdf');
  }
}

// Handle quiz requests with special caching
async function handleQuizRequest(request) {
  // Try to get from cache first
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // If not in cache, fetch from network with redirect: 'follow'
  try {
    const response = await fetch(request, { 
      redirect: 'follow',
      credentials: 'same-origin'
    });
    
    // Cache the quiz content for offline use
    if (response.status === 200) {
      const responseToCache = response.clone();
      const cache = await caches.open(QUIZ_CACHE);
      await cache.put(request, responseToCache);
    }
    
    return response;
  } catch (error) {
    console.error('Failed to fetch quiz content:', error);
    // Return offline fallback for HTML requests
    if (request.headers.get('accept').includes('text/html')) {
      return caches.match('/offline.html');
    }
    return new Response('Quiz content unavailable offline', { status: 503 });
  }
}

// Background sync for offline form submissions
self.addEventListener('sync', event => {
  if (event.tag === 'sync-messages') {
    event.waitUntil(syncMessages());
  }
});

// Push notification event
self.addEventListener('push', event => {
  const data = event.data.json();
  
  const options = {
    body: data.body,
    icon: '../assets/img/logo.png',
    badge: '../assets/img/favicon.png',
    data: {
      url: data.url
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});

// Function to sync offline messages
async function syncMessages() {
  try {
    // Get all pending messages from IndexedDB
    const db = await openDatabase();
    const pendingMessages = await getPendingMessages(db);
    
    // Send each message to the server
    const promises = pendingMessages.map(async message => {
      try {
        const response = await fetch('/api/assistant/question.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(message),
          redirect: 'follow',
          credentials: 'same-origin'
        });
        
        if (response.ok) {
          // Remove message from pending queue
          await deleteMessage(db, message.id);
        }
      } catch (error) {
        console.error('Failed to sync message:', error);
      }
    });
    
    await Promise.all(promises);
  } catch (error) {
    console.error('Sync failed:', error);
  }
}

// Open IndexedDB database
function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('academic-ease-db', 1);
    
    request.onupgradeneeded = event => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('pending-messages')) {
        db.createObjectStore('pending-messages', { keyPath: 'id', autoIncrement: true });
      }
    };
    
    request.onsuccess = event => {
      resolve(event.target.result);
    };
    
    request.onerror = event => {
      reject(event.target.error);
    };
  });
}

// Get all pending messages
function getPendingMessages(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pending-messages'], 'readonly');
    const store = transaction.objectStore('pending-messages');
    const request = store.getAll();
    
    request.onsuccess = event => {
      resolve(event.target.result);
    };
    
    request.onerror = event => {
      reject(event.target.error);
    };
  });
}

// Delete a message from the pending queue
function deleteMessage(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['pending-messages'], 'readwrite');
    const store = transaction.objectStore('pending-messages');
    const request = store.delete(id);
    
    request.onsuccess = _event => {
      resolve();
    };
    
    request.onerror = event => {
      reject(event.target.error);
    };
  });
}

// Store study material metadata for offline access
function storeStudyMaterialMetadata(db, metadata) {
  return new Promise((resolve, reject) => {
    // Check if database has the required object store
    if (!db.objectStoreNames.contains('study-materials')) {
      // Close the database and upgrade it to add the new object store
      db.close();
      const request = indexedDB.open('academic-ease-db', 2);
      
      request.onupgradeneeded = event => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('study-materials')) {
          db.createObjectStore('study-materials', { keyPath: 'url' });
        }
      };
      
      request.onsuccess = event => {
        const upgradedDb = event.target.result;
        storeStudyMaterialMetadata(upgradedDb, metadata)
          .then(resolve)
          .catch(reject);
      };
      
      request.onerror = event => {
        reject(event.target.error);
      };
      
      return;
    }
    
    // Store the metadata
    const transaction = db.transaction(['study-materials'], 'readwrite');
    const store = transaction.objectStore('study-materials');
    const request = store.put(metadata);
    
    request.onsuccess = _event => {
      resolve();
    };
    
    request.onerror = event => {
      reject(event.target.error);
    };
  });
}
