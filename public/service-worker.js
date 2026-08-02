// Bump this value whenever a release changes a static asset. Keeping the
// version explicit prevents a deployed browser from reusing an old shell.
const CACHE_NAME = 'libsync-v4';
const APP_SHELL = ['/offline', '/css/style.css', '/css/responsive.css', '/css/experience.css', '/css/operations.css?v=20260801-2', '/js/script.js?v=20260802-2'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

    // Halaman aplikasi berisi data dan sesi pengguna, sehingga tidak boleh disimpan untuk
    // dipakai ulang oleh akun lain pada perangkat yang sama.
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match('/offline')));
        return;
    }

    // Profile photos and the authenticated cover fallback are user-scoped;
    // never place those responses in a shared device cache. Public storage
    // assets can still use the normal cache-first path.
    const isPrivateAsset = url.pathname.startsWith('/profile/photo/') || url.pathname.startsWith('/media/');
    const isStaticAsset = !isPrivateAsset && (
        ['style', 'script', 'image', 'font'].includes(event.request.destination)
        || url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/css/')
        || url.pathname.startsWith('/js/')
        || url.pathname.startsWith('/images/')
    );
    if (!isStaticAsset) return;

    event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
        if (!response.ok) return response;
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
        return response;
    })));
});
