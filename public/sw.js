/**
 * Service worker тренажёра.
 *
 * Стратегии разные по типу запроса:
 *  - собранные ассеты и иконки неизменяемы (в имени хеш) — отдаём из кэша;
 *  - страницы идут в сеть, потому что прогресс считается на сервере,
 *    а при обрыве связи показываем офлайн-заглушку;
 *  - POST никогда не кэшируется: ответы кандидата должны дойти до сервера.
 */
const VERSION = 'v1';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;
const OFFLINE_URL = '/offline';

const SHELL = [OFFLINE_URL, '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => ![SHELL_CACHE, ASSET_CACHE].includes(key)).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Ассеты с хешем в имени не меняются — кэш отдаёт их мгновенно.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));

                return response;
            })),
        );

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL).then((cached) => cached
                || new Response('Нет подключения', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }))),
        );
    }
});
