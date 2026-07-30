import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Employees() {
  const [rows, setRows]   = useState([]);
  const [form, setForm]   = useState({
    national_code: '', full_name: '', position: '', base_salary: 0, hire_date: new Date().toISOString().slice(0,10),
  });
  const [payroll, setPayroll] = useState(null);

  async function load() { setRows(await api.employees()); }
  useEffect(() => { load(); }, []);

  async function submit(e) {
    e.preventDefault();
    try { await api.createEmployee(form); } catch (_) {}
    setForm({ national_code: '', full_name: '', position: '', base_salary: 0, hire_date: new Date().toISOString().slice(0,10) });
    await load();
  }

  async function runPayroll() {
    const r = await api.runPayroll({ period: new Date().toISOString().slice(0,7) });
    setPayroll(r);
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">منابع انسانی</h1>
      <div className="card mb-6 flex items-center justify-between">
        <div>
          <h3 className="font-semibold">محاسبه حقوق دوره جاری</h3>
          <p className="text-sm text-slate-500">محاسبه مالیات + بیمه + صدور سند حسابداری</p>
        </div>
        <button onClick={runPayroll} className="btn-primary">اجرای Payroll</button>
      </div>
      {payroll && (
        <div className="card mb-6 bg-emerald-50">
          <p>دوره: <code>{payroll.period}</code></p>
          <p>تعداد فیش: {payroll.issued}</p>
          <p>جمع خالص: {Number(payroll.total_net).toLocaleString()} ریال</p>
        </div>
      )}
      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-6 gap-3">
          <input className="input" placeholder="کد ملی" value={form.national_code} onChange={e => setForm({ ...form, national_code: e.target.value })} />
          <input className="input md:col-span-2" placeholder="نام" value={form.full_name} onChange={e => setForm({ ...form, full_name: e.target.value })} />
          <input className="input" placeholder="سمت" value={form.position} onChange={e => setForm({ ...form, position: e.target.value })} />
          <input className="input" type="number" placeholder="حقوق پایه" value={form.base_salary} onChange={e => setForm({ ...form, base_salary: +e.target.value })} />
          <input className="input" type="date" value={form.hire_date} onChange={e => setForm({ ...form, hire_date: e.target.value })} />
          <button className="btn-primary md:col-span-6">افزودن پرسنل</button>
        </form>
      </div>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>کد ملی</th><th>نام</th><th>سمت</th><th>حقوق پایه</th><th>تاریخ استخدام</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td><code>{r.national_code}</code></td>
                <td>{r.full_name}</td>
                <td>{r.position}</td>
                <td>{Number(r.base_salary).toLocaleString()}</td>
                <td>{r.hire_date}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
