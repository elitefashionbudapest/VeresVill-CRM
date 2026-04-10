/**
 * VeresVill CRM - Service Worker (Push Notifications)
 */

self.addEventListener('push', function(event) {
    let data = { title: 'VeresVill CRM', body: 'Új értesítés' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || '',
        icon: '../veresvill_logo.webp',
        badge: '../favico/favicon-192x192.png',
        vibrate: [300, 100, 300],
        tag: data.tag || 'veresvill-notification',
        renotify: true,
        data: {
            url: data.url || './index.php#orders'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'VeresVill CRM', options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const url = event.notification.data?.url || './index.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            // Ha már nyitva van az admin, fókuszáljunk rá
            for (const client of clientList) {
                if (client.url.includes('/admin/') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            // Egyébként nyissunk új ablakot
            return clients.openWindow(url);
        })
    );
});

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(clients.claim());
});
