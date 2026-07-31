export function fmtMoney(n: number, currency = 'IRR'): string {
  try {
    return new Intl.NumberFormat('fa-IR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
  } catch {
    return `${n.toLocaleString('fa-IR')} ${currency}`;
  }
}

export function fmtDate(s: string): string {
  if (!s) return '';
  try {
    const d = new Date(s);
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium' }).format(d);
  } catch {
    return s;
  }
}
