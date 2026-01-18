/**
 * Chinemerem Foods Service Worker - Performance Optimized
 * Aggressive caching for blazing fast load times
 */

const CACHE_NAME = 'cfi-cache-v4';
const OFFLINE_URL = '/offline.html';

// Static assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/wp-content/plugins/chinemerem-foods-inventory/assets/css/main.css',
    '/wp-content/plugins/chinemerem-foods-inventory/assets/js/main.js',
    '/wp-content/plugins/chinemerem-foods-inventory/assets/images/logo.svg'
];

// External CDN assets to cache
const CDN_ASSETS = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
];

// Install event - precache critical assets
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            console.log('CFI: Precaching critical assets for fast loading');
            // Cache local assets
            const localPromise = cache.addAll(STATIC_ASSETS.filter(url => !url.startsWith('http')));
            // Cache CDN assets with fetch
            const cdnPromise = Promise.all(CDN_ASSETS.map(url => 
                fetch(url, { mode: 'cors' })
                    .then(response => cache.put(url, response))
                    .catch(() => console.log('CFI: Could not cache CDN asset:', url))
            ));
            return Promise.all([localPromise, cdnPromise]);
        })
    );
    // Activate immediately for faster updates
    self.skipWaiting();
});

// Activate event - clean old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.filter(function(cacheName) {
                    return cacheName.startsWith('cfi-') && cacheName !== CACHE_NAME;
                }).map(function(cacheName) {
                    return caches.delete(cacheName);
                })
            );
        })
    );
    // Take control of all pages immediately
    self.clients.claim();
});

// Fetch event - Cache-first strategy for speed, but skip dynamic pages
self.addEventListener('fetch', function(event) {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip admin requests and AJAX
    if (event.request.url.includes('/wp-admin/') || 
        event.request.url.includes('admin-ajax.php')) {
        return;
    }
    
    // NEVER cache dynamic pages - always fetch fresh data
    // These pages must show real-time data without cache delays
    const noCachePages = [
        'transfer-history',
        'financial-summary',
        'financial-history',
        'order-history',
        'debtors-record',
        'debtors-history',
        'debtor-order-summary',
        'stock-record',
        'stock-history',
        'packing-store',
        'packing-history',
        'expenses',
        'expenses-history',
        'cash-out',
        'cash-out-history',
        'not-supplied',
        'not-supplied-history',
        'supplied-today',
        'supplied-today-history',
        'reconciliation',
        'reconciliation-history',
        'take-order',
        'import-record',
        'import-history'
    ];
    
    // Check if this is a dynamic page that should not be cached
    const shouldBypassCache = noCachePages.some(page => event.request.url.includes(page));
    
    if (shouldBypassCache) {
        // Network-only for dynamic pages - no caching at all
        event.respondWith(
            fetch(event.request).catch(function() {
                return new Response('Network error', { status: 503 });
            })
        );
        return;
    }

    // Use stale-while-revalidate for HTML pages (instant load + background update)
    if (event.request.mode === 'navigate' || 
        event.request.headers.get('accept').includes('text/html')) {
        event.respondWith(
            caches.match(event.request).then(function(cachedResponse) {
                const fetchPromise = fetch(event.request).then(function(networkResponse) {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then(function(cache) {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    return networkResponse;
                }).catch(function() {
                    return cachedResponse || caches.match(OFFLINE_URL);
                });
                
                // Return cached response immediately, update in background
                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Cache-first for static assets (CSS, JS, images)
    if (event.request.url.includes('/assets/') || 
        event.request.url.includes('.css') ||
        event.request.url.includes('.js') ||
        event.request.url.includes('.png') ||
        event.request.url.includes('.jpg') ||
        event.request.url.includes('.svg') ||
        event.request.url.includes('.woff')) {
        event.respondWith(
            caches.match(event.request).then(function(response) {
                if (response) {
                    return response;
                }
                return fetch(event.request).then(function(response) {
                    if (!response || response.status !== 200) {
                        return response;
                    }
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, responseToCache);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // Network-first for API/dynamic content
    event.respondWith(
        fetch(event.request).then(function(response) {
            return response;
        }).catch(function() {
            return caches.match(event.request);
        })
    );
});

// Background sync for offline orders
self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-orders') {
        event.waitUntil(syncOrders());
    }
});

async function syncOrders() {
    // Get pending orders from IndexedDB or localStorage
    // This would be implemented with a proper IndexedDB setup
    console.log('CFI: Syncing offline orders');
}

// Push notifications (future enhancement)
self.addEventListener('push', function(event) {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body,
            icon: '/wp-content/plugins/chinemerem-foods-inventory/assets/images/logo.svg',
            badge: '/wp-content/plugins/chinemerem-foods-inventory/assets/images/badge.png',
            vibrate: [100, 50, 100],
            data: {
                url: data.url
            }
        };
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    if (event.notification.data && event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});
