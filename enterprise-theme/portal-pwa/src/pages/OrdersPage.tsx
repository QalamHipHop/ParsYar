import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api, type Order } from '../lib/api';
import { fmtMoney, fmtDate } from '../lib/format';

export default function OrdersPage() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Order[]>([]);
  useEffect(() => { api.listOrders({}).then(setItems).catch(() => {}); }, []);
  return (
    <div className="space-y-2">
      <h1 className="text-base font-bold mb-2">{t('nav.orders')}</h1>
      {items.length === 0 && <div className="card text-center text-xs text-slate-500">{t('dashboard.noData')}</div>}
      {items.map(o => (
        <div key={o.id} className="card flex items-center justify-between">
          <div>
            <div className="font-semibold text-sm">{o.number}</div>
            <div className="text-xs text-slate-500">{fmtDate(o.order_date)}</div>
          </div>
          <div className="text-left">
            <div className="font-semibold text-sm">{fmtMoney(o.total, o.currency)}</div>
            <span className="badge-neutral">{o.status}</span>
          </div>
        </div>
      ))}
    </div>
  );
}
