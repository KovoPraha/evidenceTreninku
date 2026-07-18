/**
 * sw.js — Service Worker pro Web Push notifikace
 * Umístěn v kořeni aplikace (scope = celá /evidence/)
 */
const CACHE_NAME = 'evidence-v1';

// Push event — zobrazení notifikace
self.addEventListener('push', function(event) {
    let data = { title: 'Evidence tréninků', body: 'Nová zpráva', url: '/evidence/' };
    try {
        if (event.data) data = Object.assign(data, event.data.json());
    } catch (e) {
        if (event.data) data.body = event.data.text();
    }

    const options = {
        body:    data.body,
        icon:    '/evidence/icon-192.png',
        badge:   '/evidence/icon-72.png',
        data:    { url: data.url || '/evidence/' },
        vibrate: [200, 100, 200],
        tag:     data.tag || 'evidence-push',
        renotify: true,
        actions: data.actions || [],
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Click na notifikaci — otevře relevantní URL
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data?.url || '/evidence/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(windowClients) {
                for (var client of windowClients) {
                    if (client.url.includes('/evidence/') && 'focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                return clients.openWindow(url);
            })
    );
});
