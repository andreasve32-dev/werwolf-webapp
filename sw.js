// Copyright (c) 2026 Andreas Vetter
// Service Worker — Werwolf Web Push

self.addEventListener('push', function (event) {
    const data    = event.data ? event.data.json().catch(() => ({})) : Promise.resolve({});
    event.waitUntil(
        data.then(function (d) {
            var appTitle = (d.app ? '🐺 ' + d.app : '🐺 Spiel');
            return self.registration.showNotification(
                d.title  || appTitle,
                {
                    body    : d.body    || 'Neue Aktivität im Spiel — tippe zum Öffnen.',
                    icon    : d.icon    || '/assets/icons/logo/mini_logo.png',
                    badge   : d.badge   || '/assets/icons/logo/mini_logo.png',
                    tag     : d.tag     || 'werwolf',
                    renotify: true,
                    vibrate : [200, 100, 200],
                    data    : { url: d.url || '/' },
                }
            );
        }).catch(function () {
            return self.registration.showNotification('🐺 Spiel', {
                body   : 'Neue Aktivität im Spiel — tippe zum Öffnen.',
                tag    : 'werwolf',
                renotify: true,
            });
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (const c of list) {
                if ('focus' in c) return c.focus();
            }
            return clients.openWindow(target);
        })
    );
});
