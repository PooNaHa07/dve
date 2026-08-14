const CACHE_NAME = 'dve-work-hub-v2';
const ASSETS_TO_CACHE = [
  '/DVE_DATA_FULL/roles/student.php',
  '/DVE_DATA_FULL/student/submit_report.php',
  '/DVE_DATA_FULL/assets/js/offline-db.js',
  '/DVE_DATA_FULL/assets/css/student-premium.css',
  '/DVE_DATA_FULL/assets/img/logo-192.png',
  '/DVE_DATA_FULL/assets/img/logo-512.png',
  '/DVE_DATA_FULL/assets/img/default-avatar.png',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[Service Worker] Pre-caching static assets');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            console.log('[Service Worker] Clearing old cache', key);
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event (Network falling back to Cache)
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  // Do not intercept or cache dynamic pages, AJAX requests, or administrative/teacher folders
  if (
    url.search || 
    url.pathname.includes('/db/') || 
    url.pathname.includes('/admin/') || 
    url.pathname.includes('/staff/') || 
    url.pathname.includes('/teacher/') || 
    url.pathname.includes('/director/') ||
    url.pathname.includes('check_new_notifications.php')
  ) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cache new successful requests dynamically if they are from our domain
        if (response.status === 200 && event.request.url.includes(self.location.origin)) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        // Fallback to cache when offline
        return caches.match(event.request).then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If offline and request is student dashboard or submit report
          if (event.request.url.includes('student.php') || event.request.url.includes('submit_report.php')) {
            return caches.match('/DVE_DATA_FULL/roles/student.php');
          }
        });
      })
  );
});
