/**
 * Save the delegate ID card on the dashboard as a PNG.
 */
window.hbDownloadCard = async (node, filename) => {
    const { toPng } = await import('html-to-image');
    const dataUrl = await toPng(node, { pixelRatio: 3, cacheBust: true });
    const link = document.createElement('a');
    link.download = filename;
    link.href = dataUrl;
    link.click();
};

/**
 * Browser notifications for dashboard updates. Shown through the service worker, which is the only
 * way Android Chrome allows them, and only while the tab is hidden; a visible tab gets toasts instead.
 * The browser is also subscribed to Web Push, so the server can notify it while the dashboard is closed.
 */
window.hbNotifications = (() => {
    const dismissedKey = 'hb-notifications-dismissed';
    const supported = 'Notification' in window && 'serviceWorker' in navigator && window.isSecureContext;
    let unread = 0;
    const baseTitle = document.title;

    const worker = () => navigator.serviceWorker.register('/sw.js').then(() => navigator.serviceWorker.ready);

    const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;

    const keyBytes = (key) => {
        const raw = atob(key.replace(/-/g, '+').replace(/_/g, '/'));

        return Uint8Array.from(raw, (char) => char.charCodeAt(0));
    };

    const xsrfToken = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

    /**
     * Subscribe this browser to Web Push and save it for the signed-in user, so notifications
     * arrive even when the dashboard is closed. Safe to call on every dashboard load.
     */
    const subscribe = async () => {
        if (! vapidKey || ! ('PushManager' in window)) {
            return;
        }

        const registration = await worker();
        const options = { userVisibleOnly: true, applicationServerKey: keyBytes(vapidKey) };
        let subscription = await registration.pushManager.getSubscription();

        try {
            subscription ??= await registration.pushManager.subscribe(options);
        } catch {
            // A subscription made with an older key has to be replaced.
            await subscription?.unsubscribe();
            subscription = await registration.pushManager.subscribe(options);
        }

        const encodings = PushManager.supportedContentEncodings ?? ['aesgcm'];

        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({
                ...subscription.toJSON(),
                contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0],
            }),
        });
    };

    if (supported && Notification.permission === 'granted') {
        worker().catch(() => {});
    }

    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            unread = 0;
            document.title = baseTitle;
        }
    });

    const isDismissed = () => {
        try {
            return localStorage.getItem(dismissedKey) === '1';
        } catch {
            return false;
        }
    };

    return {
        /** 'default' when we should ask, otherwise 'granted', 'denied', 'dismissed' or 'unsupported'. */
        state() {
            if (! supported) {
                return 'unsupported';
            }

            return Notification.permission === 'default' && isDismissed() ? 'dismissed' : Notification.permission;
        },

        async enable() {
            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                const registration = await worker();
                registration.showNotification('Notifications are on', {
                    body: 'We’ll let you know when something new is posted.',
                    icon: '/icon-192.png',
                    tag: 'welcome',
                });
                subscribe().catch(() => {});
            }

            return permission === 'default' ? 'dismissed' : permission;
        },

        /** Keep the push subscription saved for whoever is signed in, once notifications are allowed. */
        sync() {
            if (supported && Notification.permission === 'granted') {
                subscribe().catch(() => {});
            }
        },

        dismiss() {
            try {
                localStorage.setItem(dismissedKey, '1');
            } catch {}
        },

        async show(updates) {
            if (! document.hidden || ! updates?.length) {
                return;
            }

            unread += updates.length;
            document.title = `(${unread}) ${baseTitle}`;

            if (! supported || Notification.permission !== 'granted') {
                return;
            }

            const registration = await worker();
            updates.forEach((update) => registration.showNotification(update.title, {
                body: update.body,
                icon: '/icon-192.png',
                badge: '/favicon-32.png',
                tag: update.tag,
                data: { url: '/dashboard' },
            }));
        },
    };
})();
