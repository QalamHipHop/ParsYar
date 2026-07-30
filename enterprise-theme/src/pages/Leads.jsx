import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Leads() {
  const [rows, setRows]   = useState([]);
  const [form, setForm]   = useState({ full_name: '', email: '', phone: '', source: 'web_form' });

  async function load() { setRows(await api.leads()); }
  useEffect(() => { load(); }, []);

  async function submit(e) {
    e.preventDefault();
    await api.createLead(form);
    setForm({ full_name: '', email: '', phone: '', source: 'web_form' });
    await load();
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">سرنخ‌ها (Lead Scoring)</h1>
      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-5 gap-3">
          <input className="input" placeholder="نام" value={form.full_name} onChange={e => setForm({ ...form, full_name: e.target.value })} />
          <input className="input" placeholder="ایمیل" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} />
          <input className="input" placeholder="موبایل" value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} />
          <select className="input" value={form.source} onChange={e => setForm({ ...form, source: e.target.value })}>
            <option value="web_form">فرم وب</option>
            <option value="referral">معرفی</option>
            <option value="campaign">کمپین</option>
            <option value="cold_call">تماس سرد</option>
          </select>
          <button className="btn-primary">ایجاد</button>
        </form>
      </div>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>نام</th><th>ایمیل</th><th>موبایل</th><th>منبع</th><th>امتیاز</th><th>مرحله</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td>{r.full_name}</td>
                <td>{r.email}</td>
                <td>{r.phone}</td>
                <td>{r.source}</td>
                <td><span className="badge bg-brand-50 text-brand-700">{r.score}</span></td>
                <td>{r.stage}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
