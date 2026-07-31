import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, type Payment } from '../lib/api';
import { fmtMoney, fmtDate } from '../lib/format';

export default function PaymentsPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Payment[]>([]);
  useEffect(() => { api.listPayments().then(setItems).catch(() => {}); }, []);
  return (
    <div className="space-y-2">
      <h1 className="text-base font-bold mb-2">{t('nav.payments')}</h1>
      {items.length === 0 && <div className="card text-center text-xs text-slate-500">{t('dashboard.noData')}</div>}
      {items.map(p => (
        <div key={p.id} className="card flex items-center justify-between">
          <div>
            <div className="font-semibold text-sm">{p.gateway || p.method || '—'}</div>
            <div className="text-xs text-slate-500">{fmtDate(p.paid_at)}</div>
            {p.ref_id && <div className="text-[10px] text-slate-400 mt-0.5">{p.ref_id}</div>}
          </div>
          <div className="text-left">
            <div className="font-semibold text-sm">{fmtMoney(p.amount, p.currency)}</div>
            <span className="badge-ok">{p.status}</span>
          </div>
        </div>
      ))}
    </div>
  );
}
