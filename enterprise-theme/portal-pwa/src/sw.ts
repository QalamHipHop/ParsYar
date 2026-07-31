/// <reference lib="webworker" />
// Service Worker برای PWA — Workbox به‌صورت خودکار از vite-plugin-pwa ساخته می‌شود
// این فایل فقط برای push handler سفارشی است.

// در محیط service worker، `self` به ServiceWorkerGlobalScope اشاره می‌کند
// اما در lib DOM به Window هم اشاره می‌کند — از طریق type assertion اطمینان حاصل می‌کنیم
// که TypeScript نوع درست را می‌بیند.
const sw = self as unknown as ServiceWorkerGlobalScope;

interface PushPayload {
  title?: string;
  body?: string;
  url?: string;
  tag?: string;
}

sw.addEventListener('push', ((event: PushEvent) => {
  let data: PushPayload = { title: 'پورتال ParsYar', body: 'پیام جدید' };
  try {
    if (event.data) data = event.data.json() as PushPayload;
  } catch {
    // ignore
  }
  const title = data.title || 'پورتال ParsYar';
  const options: NotificationOptions & { badge?: string } = {
    body: data.body || '',
    dir: 'rtl',
    lang: 'fa-IR',
    tag: data.tag || 'parsyar-portal',
    badge: '/pwa-192x192.png',
    icon: '/pwa-192x192.png',
    data: { url: data.url || '/dashboard' },
  };
  event.waitUntil(sw.registration.showNotification(title, options));
}) as EventListener);

sw.addEventListener('notificationclick', ((event: NotificationEvent) => {
  event.notification.close();
  const url = ((event.notification.data as { url?: string } | undefined)?.url) || '/dashboard';
  event.waitUntil((async () => {
    const all = await sw.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      const u = new URL(c.url);
      if (u.origin === sw.location.origin) {
        c.focus();
        if (typeof (c as WindowClient & { navigate?: (url: string) => Promise<void> }).navigate === 'function') {
          try { await (c as WindowClient & { navigate: (url: string) => Promise<void> }).navigate(url); } catch { /* ignore */ }
        }
        return;
      }
    }
    await sw.clients.openWindow(url);
  })());
}) as EventListener);
