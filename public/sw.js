// Minimal service worker: exists only so the browser considers the ERP
// installable as an app. It intentionally does not cache anything -
// every request still goes straight to the network - so it cannot cause
// the stale-content problems a caching service worker would introduce.

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
