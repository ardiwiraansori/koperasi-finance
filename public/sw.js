// Service worker: hanya menyimpan file statis dan halaman offline.
// Halaman aplikasi (data anggota/keuangan) TIDAK pernah disimpan di HP; selalu diambil dari server.
// Naikkan CACHE bila offline.html atau daftar PRECACHE berubah.
const CACHE = 'koperasi-v28';
const PRECACHE = ['/offline.html', '/assets/app.css', '/assets/app.js', '/icon-192.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // File statis: tampilkan dari cache, perbarui di belakang layar.
  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/icon-')) {
    e.respondWith(
      caches.open(CACHE).then(async c => {
        const hit = await c.match(req);
        const fresh = fetch(req).then(res => { if (res.ok) c.put(req, res.clone()); return res; });
        return hit || fresh;
      })
    );
  }
});
