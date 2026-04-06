/**
 * Service Worker — Libro Contable PWA
 *
 * Propósito: habilitar la instalación como app de escritorio.
 * NO cachea nada porque la app requiere servidor PHP + MySQL.
 * Si el servidor no está corriendo, muestra una página de error amigable.
 */
const SW_VERSION = 'contable-sw-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('fetch', event => {
    // Solo interceptar peticiones de navegación al propio origen
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => new Response(
                `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
                <meta name="viewport" content="width=device-width,initial-scale=1">
                <title>Sin conexión — Libro Contable</title>
                <style>
                  body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;
                       min-height:100vh;margin:0;background:#312E81;color:#fff;text-align:center;padding:2rem}
                  h1{font-size:1.5rem;margin-bottom:.5rem}
                  p{opacity:.75;font-size:.95rem}
                  a{color:#F59E0B;font-weight:600}
                </style></head>
                <body>
                  <div>
                    <div style="font-size:3rem;margin-bottom:1rem">📒</div>
                    <h1>Servidor no disponible</h1>
                    <p>Libro Contable necesita el servidor local corriendo.<br>
                       Arranca XAMPP / tu servidor PHP y vuelve a intentarlo.</p>
                    <p style="margin-top:1.5rem"><a href="/">Reintentar</a></p>
                  </div>
                </body></html>`,
                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            ))
        );
        return;
    }
    // El resto de recursos (CSS, JS, imágenes) van directo a red
    event.respondWith(fetch(event.request));
});
