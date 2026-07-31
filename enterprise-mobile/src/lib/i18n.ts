/**
 * i18n setup — fa-IR default, EN fallback.
 * Mirrors the PWA's resources for consistency.
 */
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import * as RNLocalize from 'react-native-localize';

const fa = {
  app: { name: 'پارس‌یار', poweredBy: 'قدرت گرفته از ParsYar' },
  nav: {
    dashboard: 'خانه', invoices: 'فاکتورها', orders: 'سفارش‌ها',
    payments: 'پرداخت‌ها', tickets: 'تیکت‌ها', profile: 'پروفایل',
  },
  common: {
    loading: 'در حال بارگذاری…', retry: 'تلاش مجدد', cancel: 'انصراف',
    save: 'ذخیره', submit: 'ارسال', back: 'بازگشت', logout: 'خروج',
    offline: 'آفلاین — تغییرات بعد از اتصال همگام می‌شوند', error: 'خطا',
    success: 'موفقیت‌آمیز', confirm: 'تأیید', search: 'جستجو…',
  },
  login: {
    title: 'به پارس‌یار خوش آمدید',
    subtitle: 'ایمیل خود را وارد کنید تا لینک ورود برایتان ارسال شود',
    email: 'ایمیل', device: 'نام دستگاه (اختیاری)',
    send: 'ارسال لینک ورود', sent: 'لینک ورود به ایمیل شما ارسال شد',
    openEmail: 'باز کردن ایمیل', resendIn: 'ارسال مجدد در {{n}} ثانیه',
  },
  verify: {
    title: 'تأیید لینک ورود', wait: 'لطفاً صبر کنید…', failed: 'تأیید ناموفق بود',
  },
  dashboard: {
    welcome: 'سلام، {{name}}', balance: 'مانده حساب', openInvoices: 'فاکتورهای باز',
    openTickets: 'تیکت‌های باز', recentInvoices: 'آخرین فاکتورها',
    noData: 'موردی یافت نشد',
  },
  invoices: { title: 'فاکتورها', number: 'شماره', date: 'تاریخ', total: 'مبلغ', status: 'وضعیت', paid: 'پرداخت‌شده', unpaid: 'پرداخت‌نشده' },
  orders:   { title: 'سفارش‌ها', number: 'شماره', date: 'تاریخ', total: 'مبلغ', status: 'وضعیت' },
  payments: { title: 'پرداخت‌ها', amount: 'مبلغ', method: 'روش', ref: 'کد پیگیری', date: 'تاریخ' },
  tickets:  { title: 'تیکت‌ها', subject: 'موضوع', status: 'وضعیت', priority: 'اولویت', new: 'تیکت جدید', reply: 'ارسال پاسخ', category: 'دسته‌بندی', createSubject: 'موضوع تیکت', createBody: 'شرح مشکل…' },
  profile:  { title: 'پروفایل', name: 'نام', email: 'ایمیل', company: 'شرکت', settings: 'تنظیمات', biometric: 'ورود با اثر انگشت', language: 'زبان', notifications: 'اعلان‌ها' },
  status: { open: 'باز', pending: 'در انتظار', paid: 'پرداخت‌شده', overdue: 'سررسید گذشته', closed: 'بسته', resolved: 'حل‌شده' },
};

const en = {
  app: { name: 'ParsYar', poweredBy: 'Powered by ParsYar' },
  nav: { dashboard: 'Home', invoices: 'Invoices', orders: 'Orders', payments: 'Payments', tickets: 'Tickets', profile: 'Profile' },
  common: { loading: 'Loading…', retry: 'Retry', cancel: 'Cancel', save: 'Save', submit: 'Submit', back: 'Back', logout: 'Logout', offline: 'Offline — changes will sync when back online', error: 'Error', success: 'Success', confirm: 'Confirm', search: 'Search…' },
  login: { title: 'Welcome to ParsYar', subtitle: 'Enter your email and we will send you a sign-in link', email: 'Email', device: 'Device name (optional)', send: 'Send sign-in link', sent: 'A sign-in link has been sent to your email', openEmail: 'Open email', resendIn: 'Resend in {{n}}s' },
  verify: { title: 'Verifying sign-in link', wait: 'Please wait…', failed: 'Verification failed' },
  dashboard: { welcome: 'Hello, {{name}}', balance: 'Account balance', openInvoices: 'Open invoices', openTickets: 'Open tickets', recentInvoices: 'Recent invoices', noData: 'No data' },
  invoices: { title: 'Invoices', number: 'Number', date: 'Date', total: 'Total', status: 'Status', paid: 'Paid', unpaid: 'Unpaid' },
  orders:   { title: 'Orders', number: 'Number', date: 'Date', total: 'Total', status: 'Status' },
  payments: { title: 'Payments', amount: 'Amount', method: 'Method', ref: 'Reference', date: 'Date' },
  tickets:  { title: 'Tickets', subject: 'Subject', status: 'Status', priority: 'Priority', new: 'New ticket', reply: 'Send reply', category: 'Category', createSubject: 'Ticket subject', createBody: 'Describe the issue…' },
  profile:  { title: 'Profile', name: 'Name', email: 'Email', company: 'Company', settings: 'Settings', biometric: 'Biometric sign-in', language: 'Language', notifications: 'Notifications' },
  status: { open: 'Open', pending: 'Pending', paid: 'Paid', overdue: 'Overdue', closed: 'Closed', resolved: 'Resolved' },
};

const resources = { fa: { translation: fa }, en: { translation: en } };

const locales = RNLocalize.getLocales();
const deviceLang = locales[0]?.languageCode ?? 'fa';
const lng = deviceLang === 'fa' || deviceLang === 'en' ? deviceLang : 'fa';
const rtlLngs = ['fa', 'ar'];

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng,
    fallbackLng: 'fa',
    interpolation: { escapeValue: false },
    compatibilityJSON: 'v4',
  });

export const isRTL = rtlLngs.includes(i18n.language);
export default i18n;
