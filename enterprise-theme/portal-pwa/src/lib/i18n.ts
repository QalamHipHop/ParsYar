import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

const fa = {
  app: { name: 'پورتال مشتریان', poweredBy: 'قدرت گرفته از ParsYar' },
  nav: { dashboard: 'داشبورد', invoices: 'فاکتورها', orders: 'سفارش‌ها', payments: 'پرداخت‌ها', tickets: 'تیکت‌ها', quotes: 'استعلام قیمت', logout: 'خروج' },
  auth: { title: 'ورود به پورتال', email: 'ایمیل', device: 'دستگاه (اختیاری)', send: 'ارسال لینک ورود', sent: 'لینک ورود به ایمیل شما ارسال شد. ایمیل را بررسی کنید.', backToLogin: 'بازگشت به ورود', verifying: 'در حال تأیید…', verifyTitle: 'تأیید لینک', verifyFailed: 'لینک نامعتبر یا منقضی شده است.' },
  dashboard: { welcome: 'خوش آمدید', profile: 'پروفایل', quickActions: 'اقدامات سریع', lastInvoices: 'آخرین فاکتورها', lastOrders: 'آخرین سفارش‌ها', openTickets: 'تیکت‌های باز', noData: 'موردی یافت نشد.' },
  invoice: { number: 'شماره', date: 'تاریخ صدور', dueDate: 'سررسید', total: 'مبلغ کل', paid: 'پرداخت‌شده', status: 'وضعیت', currency: 'ارز' },
  ticket: { new: 'تیکت جدید', subject: 'موضوع', body: 'شرح', category: 'دسته', priority: 'اولویت', submit: 'ارسال', low: 'کم', normal: 'معمولی', high: 'بالا', urgent: 'فوری', billing: 'مالی', technical: 'فنی', sales: 'فروش', shipping: 'ارسال', other: 'سایر' },
  push: { enableTitle: 'فعال‌سازی اعلان‌ها', enableBody: 'با فعال‌سازی، از وضعیت فاکتورها و سفارش‌ها باخبر می‌شوید.', enable: 'فعال‌سازی', dismiss: 'بعداً' },
  install: { title: 'نصب اپلیکیشن', body: 'می‌توانید پورتال را روی دستگاه خود نصب کنید.', install: 'نصب', dismiss: 'حالا نه' },
  common: { retry: 'تلاش مجدد', cancel: 'انصراف', save: 'ذخیره', loading: 'در حال بارگذاری…', error: 'خطا', offline: 'آفلاین — نمایش آخرین داده‌های ذخیره‌شده', updated: 'به‌روزرسانی موجود' }
};

const en = {
  app: { name: 'Customer Portal', poweredBy: 'Powered by ParsYar' },
  nav: { dashboard: 'Dashboard', invoices: 'Invoices', orders: 'Orders', payments: 'Payments', tickets: 'Tickets', quotes: 'Quote Requests', logout: 'Logout' },
  auth: { title: 'Sign in to the portal', email: 'Email', device: 'Device (optional)', send: 'Send sign-in link', sent: 'Sign-in link sent. Please check your email.', backToLogin: 'Back to sign in', verifying: 'Verifying…', verifyTitle: 'Verifying link', verifyFailed: 'Invalid or expired link.' },
  dashboard: { welcome: 'Welcome', profile: 'Profile', quickActions: 'Quick actions', lastInvoices: 'Recent invoices', lastOrders: 'Recent orders', openTickets: 'Open tickets', noData: 'No data.' },
  invoice: { number: 'Number', date: 'Issue date', dueDate: 'Due date', total: 'Total', paid: 'Paid', status: 'Status', currency: 'Currency' },
  ticket: { new: 'New ticket', subject: 'Subject', body: 'Description', category: 'Category', priority: 'Priority', submit: 'Submit', low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent', billing: 'Billing', technical: 'Technical', sales: 'Sales', shipping: 'Shipping', other: 'Other' },
  push: { enableTitle: 'Enable notifications', enableBody: 'Get notified about invoice and order updates.', enable: 'Enable', dismiss: 'Later' },
  install: { title: 'Install app', body: 'Install the portal on your device.', install: 'Install', dismiss: 'Not now' },
  common: { retry: 'Retry', cancel: 'Cancel', save: 'Save', loading: 'Loading…', error: 'Error', offline: 'Offline — showing cached data', updated: 'Update available' }
};

i18n
  .use(initReactI18next)
  .init({
    resources: { fa: { translation: fa }, en: { translation: en } },
    lng: localStorage.getItem('parsyar.locale') || 'fa',
    fallbackLng: 'fa',
    interpolation: { escapeValue: false }
  });

export default i18n;
