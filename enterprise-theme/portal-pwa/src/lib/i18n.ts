/**
 * i18n bootstrap for ParsYar Customer Portal.
 * Default locale: fa-IR. Auto-detect from browser if fa-* / fa-IR unavailable.
 */
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

const fa = {
  app: { name: 'پورتال مشتریان', poweredBy: 'قدرت گرفته توسط ParsYar' },
  common: {
    offline: 'اتصال اینترنت برقرار نیست — تغییرات بعد از اتصال همگام می‌شوند.',
    loading: 'در حال بارگذاری…',
    error: 'خطا',
    retry: 'تلاش دوباره',
    submit: 'ارسال',
    cancel: 'انصراف',
    save: 'ذخیره',
    close: 'بستن',
    back: 'بازگشت',
    yes: 'بله',
    no: 'خیر',
    empty: 'موردی یافت نشد',
  },
  nav: {
    dashboard: 'داشبورد',
    invoices: 'فاکتورها',
    orders: 'سفارش‌ها',
    payments: 'پرداخت‌ها',
    tickets: 'تیکت‌ها',
    logout: 'خروج',
  },
  login: {
    title: 'ورود به پورتال',
    subtitle: 'ایمیل خود را وارد کنید تا لینک ورود برایتان ارسال شود.',
    email: 'ایمیل',
    emailPlaceholder: 'name@example.com',
    send: 'ارسال لینک ورود',
    sent: 'لینک ورود به ایمیل شما ارسال شد. لطفاً صندوق ورودی (و پوشه اسپم) را بررسی کنید.',
    devHint: 'در محیط توسعه، لینک در پاسخ سرور نمایش داده می‌شود.',
    openVerify: 'باز کردن لینک',
    rateLimited: 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.',
    invalidEmail: 'ایمیل نامعتبر است.',
  },
  verify: {
    title: 'تأیید لینک ورود',
    inProgress: 'در حال بررسی لینک…',
    success: 'ورود موفقیت‌آمیز. در حال انتقال…',
    failed: 'لینک نامعتبر یا منقضی شده است.',
    backToLogin: 'بازگشت به صفحه ورود',
  },
  dashboard: {
    welcome: 'خوش آمدید',
    summary: 'خلاصه حساب',
    totalInvoices: 'تعداد فاکتورها',
    unpaid: 'پرداخت نشده',
    openTickets: 'تیکت‌های باز',
    lastInvoice: 'آخرین فاکتور',
    noInvoice: 'هنوز فاکتوری صادر نشده است.',
    recentPayments: 'آخرین پرداخت‌ها',
    noPayment: 'هنوز پرداختی ثبت نشده است.',
  },
  invoice: {
    title: 'فاکتورها',
    number: 'شماره',
    date: 'تاریخ صدور',
    due: 'سررسید',
    total: 'مبلغ کل',
    paid: 'پرداخت‌شده',
    status: 'وضعیت',
    pay: 'پرداخت',
  },
  order: {
    title: 'سفارش‌ها',
    number: 'شماره',
    date: 'تاریخ',
    total: 'مبلغ',
    status: 'وضعیت',
  },
  payment: {
    title: 'پرداخت‌ها',
    amount: 'مبلغ',
    method: 'روش',
    gateway: 'درگاه',
    date: 'تاریخ',
    status: 'وضعیت',
    ref: 'شناسه مرجع',
  },
  ticket: {
    title: 'تیکت‌ها',
    new: 'تیکت جدید',
    subject: 'موضوع',
    body: 'شرح',
    category: 'دسته',
    priority: 'اولویت',
    status: 'وضعیت',
    create: 'ثبت تیکت',
    reply: 'ارسال پاسخ',
    cat: {
      other: 'سایر',
      billing: 'مالی',
      technical: 'فنی',
      sales: 'فروش',
    },
    pri: {
      low: 'کم',
      normal: 'معمولی',
      high: 'بالا',
      urgent: 'فوری',
    },
  },
  install: {
    title: 'نصب نسخه موبایل',
    body: 'می‌توانید پورتال را روی دستگاه خود نصب کنید.',
    install: 'نصب',
    dismiss: 'بعداً',
  },
  push: {
    title: 'فعال‌سازی اعلان‌ها',
    body: 'با فعال‌سازی اعلان‌ها، از رویدادهای مهم باخبر می‌شوید.',
    enable: 'فعال‌سازی',
    dismiss: 'بعداً',
  },
};

i18n
  .use(initReactI18next)
  .init({
    resources: { 'fa-IR': { translation: fa } },
    lng: 'fa-IR',
    fallbackLng: 'fa-IR',
    interpolation: { escapeValue: false },
  });

export default i18n;
