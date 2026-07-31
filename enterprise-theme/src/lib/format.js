/**
 * ParsYar Shared Format Helpers
 * ─ Money, Jalali date, Persian digits, pluralize, truncate
 */

const persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
const englishDigits = ['0','1','2','3','4','5','6','7','8','9'];

/** تبدیل ارقام فارسی به انگلیسی */
export function persianToEnglish(input) {
  if (input == null) return '';
  return String(input).replace(/[۰-۹]/g, ch => englishDigits[persianDigits.indexOf(ch)]);
}

/** تبدیل ارقام انگلیسی به فارسی */
export function englishToPersian(input) {
  if (input == null) return '';
  return String(input).replace(/[0-9]/g, ch => persianDigits[parseInt(ch, 10)]);
}

/** فرمت پول با واحد */
export function formatMoney(amount, currency = 'IRT') {
  const n = Number(amount || 0);
  const symbols = {
    IRT: 'تومان', IRR: 'ریال', USD: '$', EUR: '€', AED: 'د.إ', TRY: '₺', GBP: '£',
  };
  const sym = symbols[currency] || currency;
  const formatted = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(n);
  return `${englishToPersian(formatted)} ${sym}`;
}

/** فرمت عدد ساده با جداکنندهٔ هزارگان */
export function formatNumber(n, opts = {}) {
  return new Intl.NumberFormat('en-US', opts).format(Number(n || 0));
}

/** فرمت تاریخ میلادی به جلالی (ساده با Intl) */
export function formatJalali(iso, opts = { year: 'numeric', month: 'long', day: 'numeric' }) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('fa-IR', opts).format(d);
  } catch {
    return iso;
  }
}

/** فرمت تاریخ کوتاه */
export function formatJalaliShort(iso) {
  return formatJalali(iso, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

/** فرمت زمان نسبی (۲ دقیقه پیش) */
export function timeAgo(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  const diff = (Date.now() - date.getTime()) / 1000;
  if (diff < 60) return 'چند لحظه پیش';
  if (diff < 3600) return `${Math.floor(diff / 60)} دقیقه پیش`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} ساعت پیش`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} روز پیش`;
  return formatJalaliShort(iso);
}

/** کوتاه کردن متن */
export function truncate(s, n = 80) {
  if (!s) return '';
  s = String(s);
  return s.length > n ? s.slice(0, n - 1) + '…' : s;
}

/** کلاس‌های کمکی برای ادغام */
export function cx(...args) {
  return args.filter(Boolean).join(' ');
}

/** uuid کوتاه */
export function uid(prefix = 'id') {
  return prefix + '_' + Math.random().toString(36).slice(2, 10);
}

/** debounce */
export function debounce(fn, ms = 250) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

/** خواب */
export function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

/** تابع کمکی برای memoize ساده */
export function memo(fn) {
  const cache = new Map();
  return (...args) => {
    const k = JSON.stringify(args);
    if (!cache.has(k)) cache.set(k, fn(...args));
    return cache.get(k);
  };
}

/** slugify فارسی */
export function slugify(s) {
  if (!s) return '';
  return String(s)
    .toLowerCase()
    .replace(/[\s_]+/g, '-')
    .replace(/[^\u0600-\u06FFa-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}
