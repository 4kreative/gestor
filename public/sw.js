// GestorAds — Service Worker PWA
const CACHE_NAME = 'gestorads-v1';

// Arquivos que ficam em cache para funcionar offline
const ASSETS_TO_CACHE = [
  '/dashboard',
  '/public/css/app.css',
  '/public/js/app.js',
  '/public/manifest.json',
  '/public/icons/icon-192x192.png',
  '/public/icons/icon-512x512.png'
];

// Instala e faz cache dos assets principais
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Remove caches antigos ao ativar nova versão
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

// Estratégia: tenta rede primeiro, cai no cache se offline
self.addEventListener('fetch', event => {
  // Ignora requisições que não são GET
  if (event.request.method !== 'GET') return;

  // Ignora requisições de API (sempre precisam de rede)
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/cron/')) return;

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Salva uma cópia no cache
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      })
      .catch(() => {
        // Sem rede: serve do cache
        return caches.match(event.request).then(cached => {
          if (cached) return cached;
          // Fallback para o dashboard se for uma página
          if (event.request.destination === 'document') {
            return caches.match('/dashboard');
          }
        });
      })
  );
});
