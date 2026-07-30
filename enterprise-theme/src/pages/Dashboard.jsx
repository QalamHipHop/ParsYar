import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [err, setErr]   = useState(null);

  useEffect(() => {
    Promise.all([
      api.objects().catch(() => []),
      api.leads().catch(() => []),
      api.products().catch(() => []),
      api.invoices().catch(() => []),
      api.employees().catch(() => []),
      api.journal().catch(() => []),
    ]).then(([objs, leads, prods, invs, emps, jrns]) => {
      setStats({
        objects:   objs.length,
        leads:     leads.length,
        products:  prods.length,
        invoices:  invs.length,
        employees: emps.length,
        journals:  jrns.length,
      });
    }).catch(setErr);
  }, []);

  if (err) {
    return <div className="card text-red-600">خطا: {err.message}</div>;
  }
  if (!stats) {
    return <div className="card">در حال بارگذاری...</div>;
  }

  const cards = [
    { label: 'اشیاء',   value: stats.objects,   color: 'bg-brand-50' },
    { label: 'سرنخ‌ها', value: stats.leads,     color: 'bg-amber-50' },
    { label: 'محصولات', value: stats.products,  color: 'bg-emerald-50' },
    { label: 'فاکتورها',value: stats.invoices,  color: 'bg-rose-50' },
    { label: 'پرسنل',   value: stats.employees, color: 'bg-indigo-50' },
    { label: 'اسناد',   value: stats.journals,  color: 'bg-slate-50' },
  ];

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6">داشبورد</h1>
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        {cards.map(c => (
          <div key={c.label} className={`card ${c.color}`}>
            <div className="text-sm text-slate-500">{c.label}</div>
            <div className="text-3xl font-bold mt-1">{c.value}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
