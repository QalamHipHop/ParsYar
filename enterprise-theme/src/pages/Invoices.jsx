import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Invoices() {
  const [rows, setRows]   = useState([]);
  const [form, setForm]   = useState({ issue_date: new Date().toISOString().slice(0,10), subtotal: 0, tax: 0 });

  async function load() { setRows(await api.invoices()); }
  useEffect(() => { load(); }, []);

  async function submit(e) {
    e.preventDefault();
    await api.createInvoice(form);
    setForm({ issue_date: new Date().toISOString().slice(0,10), subtotal: 0, tax: 0 });
    await load();
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">فاکتورها</h1>
      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <input className="input" type="date" value={form.issue_date} onChange={e => setForm({ ...form, issue_date: e.target.value })} />
          <input className="input" type="number" placeholder="مبلغ (ریال)" value={form.subtotal} onChange={e => setForm({ ...form, subtotal: +e.target.value })} />
          <input className="input" type="number" placeholder="مالیات" value={form.tax} onChange={e => setForm({ ...form, tax: +e.target.value })} />
          <button className="btn-primary">صدور فاکتور</button>
        </form>
        <p className="text-xs text-slate-500 mt-2">با صدور فاکتور، یک سند حسابداری دوطرفه به‌طور خودکار ثبت می‌شود.</p>
      </div>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>شماره</th><th>تاریخ</th><th>مبلغ</th><th>مالیات</th><th>جمع</th><th>وضعیت</th><th>UID مؤدیان</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td><code>{r.invoice_no}</code></td>
                <td>{r.issue_date}</td>
                <td>{Number(r.subtotal).toLocaleString()}</td>
                <td>{Number(r.tax).toLocaleString()}</td>
                <td className="font-semibold">{Number(r.total).toLocaleString()}</td>
                <td><span className="badge bg-brand-50 text-brand-700">{r.status}</span></td>
                <td><code className="text-xs">{r.tax_invoice_uid || '—'}</code></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
