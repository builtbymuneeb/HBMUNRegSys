// Shows dashboard notifications, including Web Push ones, and brings the dashboard forward when one is clicked.

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Web Push from the server. The tag matches the open dashboard's own notification for the same update,
// so the two replace each other; a dashboard on screen shows a toast instead.
self.addEventListener('push', (event) => {
    const update = event.data?.json() ?? {};

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const dashboardOnScreen = windows.some((client) => client.visibilityState === 'visible'
                && new URL(client.url).pathname.startsWith('/dashboard'));

            if (dashboardOnScreen) {
                return;
            }

            return self.registration.showNotification(update.title || 'New update', {
                body: update.body,
                icon: '/icon-192.png',
                badge: '/favicon-32.png',
                tag: update.tag,
                data: { url: update.url || '/dashboard' },
            });
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => client.url.startsWith(url));

            return open ? open.focus() : self.clients.openWindow(url);
        }),
    );
});
