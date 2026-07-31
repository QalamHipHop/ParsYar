import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, formatCurrency, formatDateJalali } from '../lib/api';
import type { Order } from '../lib/types';

export default function OrdersPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try { setItems(await api.orders()); }
      catch (e: unknown) { setErr(e instanceof Error ? e.message : 'error'); }
      finally { setLoading(false); }
    })();
  }, []);

  return (
    <div className="space-y-3">
      <h1 className="text-lg font-semibold">{t('order.title')}</h1>
      {loading && <div className="card text-sm text-slate-500">{t('common.loading')}</div>}
      {err && <div className="card text-sm text-rose-700">{t('common.error')}: {err}</div>}
      {!loading && !items.length && <div className="card text-sm text-slate-500">{t('common.empty')}</div>}
      <div className="space-y-2">
        {items.map(o => (
          <div key={o.id} className="card flex items-center justify-between">
            <div>
              <div className="font-semibold">{o.number}</div>
              <div className="text-xs text-slate-500">{formatDateJalali(o.order_date)}</div>
            </div>
            <div className="text-left">
              <div className="text-sm font-medium">{formatCurrency(o.total, o.currency)}</div>
              <div className="text-xs"><span className="badge-neutral">{o.status}</span></div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
