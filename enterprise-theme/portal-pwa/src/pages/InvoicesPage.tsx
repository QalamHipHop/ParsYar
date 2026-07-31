import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, type Invoice } from '../lib/api';
import { fmtMoney, fmtDate } from '../lib/format';

function statusBadge(s: string): string {
  if (['paid', 'completed', 'success'].includes(s)) return 'badge-ok';
  if (['overdue', 'failed', 'cancelled'].includes(s)) return 'badge-err';
  if (['pending', 'partial', 'sent'].includes(s)) return 'badge-warn';
  return 'badge-neutral';
}

export default function InvoicesPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Invoice[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    api.listInvoices({ limit: 100 })
      .then(setItems)
      .catch((e: Error) => setErr(e.message));
  }, []);

  return (
    <div className="space-y-2">
      <h1 className="text-base font-bold mb-2">{t('nav.invoices')}</h1>
      {err && <div className="text-rose-600 text-xs">{err}</div>}
      {items.length === 0 && !err && <div className="card text-center text-xs text-slate-500">{t('dashboard.noData')}</div>}
      {items.map(inv => (
        <div key={inv.id} className="card">
          <div className="flex items-center justify-between">
            <div>
              <div className="font-semibold text-sm">{inv.number}</div>
              <div className="text-xs text-slate-500">{fmtDate(inv.issue_date)}</div>
            </div>
            <div className="text-left">
              <div className="font-semibold text-sm">{fmtMoney(inv.total, inv.currency)}</div>
              <span className={statusBadge(inv.status)}>{inv.status}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
