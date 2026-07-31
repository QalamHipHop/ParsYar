import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, formatCurrency, formatDateJalali } from '../lib/api';
import type { Payment } from '../lib/types';

export default function PaymentsPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try { setItems(await api.payments()); }
      catch (e: unknown) { setErr(e instanceof Error ? e.message : 'error'); }
      finally { setLoading(false); }
    })();
  }, []);

  return (
    <div className="space-y-3">
      <h1 className="text-lg font-semibold">{t('payment.title')}</h1>
      {loading && <div className="card text-sm text-slate-500">{t('common.loading')}</div>}
      {err && <div className="card text-sm text-rose-700">{t('common.error')}: {err}</div>}
      {!loading && !items.length && <div className="card text-sm text-slate-500">{t('common.empty')}</div>}
      <div className="space-y-2">
        {items.map(p => (
          <div key={p.id} className="card">
            <div className="flex items-center justify-between">
              <div className="font-semibold">{formatCurrency(p.amount, p.currency)}</div>
              <span className={p.status === 'success' || p.status === 'paid' ? 'badge-ok' : 'badge-warn'}>{p.status}</span>
            </div>
            <div className="text-xs text-slate-500 mt-1">
              {p.gateway} · {p.method} · {formatDateJalali(p.paid_at)}
            </div>
            {p.ref_id && <div className="text-[11px] text-slate-400 mt-1">ref: {p.ref_id}</div>}
          </div>
        ))}
      </div>
    </div>
  );
}
