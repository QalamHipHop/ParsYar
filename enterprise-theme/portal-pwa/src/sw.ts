/// <reference lib="webworker" />
// Service Worker برای PWA — Workbox به‌صورت خودکار از vite-plugin-pwa ساخته می‌شود
// این فایل فقط برای push handler سفارشی است.

declare const self: ServiceWorkerGlobalScope;

self.addEventListener('push', (event) => {
  let data: { title?: string; body?: string; url?: string; tag?: string } = { title: 'پورتال ParsYar', body: 'پیام جدید' };
  try {
    if (event.data) data = event.data.json();
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
    data: { url: data.url || '/dashboard' }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data?.url as string) || '/dashboard';
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      const u = new URL(c.url);
      if (u.origin === self.location.origin) {
        c.focus();
        c.navigate(url).catch(() => {});
        return;
      }
    }
    await self.clients.openWindow(url);
  })());
});
