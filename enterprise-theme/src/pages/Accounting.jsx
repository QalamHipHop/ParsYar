import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Accounting() {
  const [tb, setTb]     = useState([]);
  const [journal, setJ] = useState([]);
  const [entry, setEntry] = useState({
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    lines: [
      { account_code: '1100', debit: 0, credit: 0 },
      { account_code: '4100', debit: 0, credit: 0 },
    ],
  });
  const [accounts, setAccounts] = useState([]);

  async function load() {
    const [t, j, a] = await Promise.all([api.trialBalance(), api.journal(), api.accounts()]);
    setTb(t); setJ(j); setAccounts(a);
  }
  useEffect(() => { load(); }, []);

  function setLine(i, k, v) {
    const lines = [...entry.lines];
    lines[i] = { ...lines[i], [k]: parseFloat(v) || 0 };
    if (k === 'debit' && v)  lines[i].credit = 0;
    if (k === 'credit' && v) lines[i].debit = 0;
    setEntry({ ...entry, lines });
  }

  async function submit() {
    const totalDebit  = entry.lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = entry.lines.reduce((s, l) => s + l.credit, 0);
    if (Math.abs(totalDebit - totalCredit) > 0.005) {
      alert('سند تراز نیست!');
      return;
    }
    await api.postEntry(entry);
    await load();
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">حسابداری</h1>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="card">
          <h2 className="font-semibold mb-3">تراز آزمایشی</h2>
          <table className="table">
            <thead><tr><th>کد</th><th>نام</th><th>بدهکار</th><th>بستانکار</th></tr></thead>
            <tbody>
              {tb.map(r => (
                <tr key={r.code}>
                  <td><code>{r.code}</code></td>
                  <td>{r.name}</td>
                  <td>{Number(r.total_debit).toLocaleString()}</td>
                  <td>{Number(r.total_credit).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="card">
          <h2 className="font-semibold mb-3">ثبت سند جدید</h2>
          <label className="text-xs text-slate-500">تاریخ</label>
          <input className="input mb-2" type="date" value={entry.entry_date}
                 onChange={e => setEntry({ ...entry, entry_date: e.target.value })} />
          <input className="input mb-3" placeholder="شرح" value={entry.description}
                 onChange={e => setEntry({ ...entry, description: e.target.value })} />
          {entry.lines.map((l, i) => (
            <div key={i} className="flex gap-2 mb-2">
              <select className="input flex-1" value={l.account_code}
                      onChange={e => setLine(i, 'account_code', e.target.value)}>
                {accounts.map(a => <option key={a.id} value={a.code}>{a.code} - {a.name}</option>)}
              </select>
              <input className="input w-28" type="number" placeholder="بدهکار" value={l.debit}
                     onChange={e => setLine(i, 'debit', e.target.value)} />
              <input className="input w-28" type="number" placeholder="بستانکار" value={l.credit}
                     onChange={e => setLine(i, 'credit', e.target.value)} />
            </div>
          ))}
          <button onClick={() => setEntry({ ...entry, lines: [...entry.lines, { account_code: '1100', debit: 0, credit: 0 }] })}
                  className="btn-ghost text-sm">+ سطر</button>
          <div className="mt-3 text-right">
            <button onClick={submit} className="btn-primary">ثبت سند</button>
          </div>
        </div>

        <div className="card lg:col-span-2">
          <h2 className="font-semibold mb-3">آخرین اسناد</h2>
          <table className="table">
            <thead><tr><th>شماره</th><th>تاریخ</th><th>شرح</th><th>وضعیت</th></tr></thead>
            <tbody>
              {journal.map(j => (
                <tr key={j.id}>
                  <td><code>{j.entry_no}</code></td>
                  <td>{j.entry_date}</td>
                  <td>{j.description}</td>
                  <td><span className="badge bg-emerald-50 text-emerald-700">{j.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
