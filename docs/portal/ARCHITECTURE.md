# Customer Portal (PWA) — Architecture

> فاز ۱.۷.۰ — پورتال مشتریان به‌صورت PWA (Progressive Web App)

## چشم‌انداز

یک پورتال سلف‌سرویس برای مشتریان (B2B/B2C) که:

- بدون نیاز به نصب اپلیکیشن، روی موبایل و دسکتاپ قابل استفاده باشد (PWA)
- در حالت آفلاین، حداقل صفحات کلیدی (لیست سفارش‌ها، فاکتورها، وضعیت پرداخت) را نمایش دهد
- با مکانیزم **Magic Link + JWT** لاگین کند (بدون نیاز به رمز عبور)
- به‌صورت خودکار Push Notification برای رویدادهای کلیدی بفرستد
- با سرویس‌های موجود ParsYar (CRM/ERP) صحبت کند ولی به هیچ جدول دامنه‌ای آن‌ها وابسته نباشد (read-only + ایجاد ticket/quote)

## معماری لایه‌ای

```
PWA Frontend (React 18 + Vite + Tailwind)
   │  pages, hooks, api client, service worker (Workbox)
   ▼
/wp-json/enterprise/v1/portal/*
   │  auth, me, invoices, orders, payments, tickets, push, events
   ▼
PortalService / AuthService / TicketService (PHP 8.1+)
   │  read-only over contacts/invoices/orders/payments
   │  + own tables: parsyar_portal_*
   ▼
wpdb + Object Cache (Redis)
```

## جداول جدید (۶ جدول)

| نام | کاربرد |
|-----|--------|
| `wp_parsyar_portal_tokens` | magic link tokens (hashed, single-use, TTL) |
| `wp_parsyar_portal_sessions` | JWT sessions (jti, refresh_token, device, expires) |
| `wp_parsyar_portal_tickets` | tickets مشتریان (subject, body, status, priority) |
| `wp_parsyar_quote_requests` | درخواست پیش‌فاکتور/استعلام قیمت |
| `wp_parsyar_push_subscriptions` | endpointهای WebPush |
| `wp_parsyar_portal_events` | لاگ رویدادهای سمت کلاینت |

## امنیت

- Magic link با `hash_equals` روی token هش‌شده (bcrypt)
- JWT با HS256 و کلید از options
- Rate limit: ۱ magic link / ۲ دقیقه / هر ایمیل، ۵ تلاش ناموفق → ۱۰ دقیقه ban
- CSP برای endpointهای portal
- Push فقط روی endpointهای متعلق به customer

## PWA Frontend

- React 18 + Vite 5 + Tailwind 3 + Workbox 7 + i18next
- RTL-first (Vazirmatn)
- Offline: NetworkFirst برای API، StaleWhileRevalidate برای استاتیک
- Install prompt با `beforeinstallprompt`
- VAPID keypair در activation

## معیارهای پذیرش

- ۶ جدول در installer ساخته شوند
- Auth flow end-to-end کار کند
- ۱۲ endpoint REST فعال و تست‌شده
- PWA frontend قابل استفاده (login, dashboard, invoices, orders, tickets)
- Service worker + install banner
- WebPush ارسال موفق
- تست واحد برای AuthService و PortalService
- CI pass
- Changelog + version bump
