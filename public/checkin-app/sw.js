/* Event Check-in Service Worker
 * Note: for this SW to claim scope '/' it needs the response header
 *   Service-Worker-Allowed: /
 * Add to .htaccess or server config for the theme directory.
 */

const CACHE_NAME = 'evtmgr-checkin-v1';

self.addEventListener('install', function (e) {
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (k) { return k !== CACHE_NAME; })
                    .map(function (k) { return caches.delete(k); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (e) {
    // Pass through REST API calls without caching
    if (e.request.url.includes('/wp-json/')) {
        return;
    }

    e.respondWith(
        fetch(e.request).catch(function () {
            return caches.match(e.request);
        })
    );
});
