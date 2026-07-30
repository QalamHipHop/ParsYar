import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Audit() {
  const [rows, setRows] = useState([]);
  useEffect(() => { api.audit().then(setRows).catch(() => setRows([])); }, []);

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">حسابرسی (Audit Log)</h1>
      <p className="text-sm text-slate-500 mb-3">این لاگ فقط append است و برای انطباق با بازرسی مالیاتی طراحی شده.</p>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>#</th><th>شیء</th><th>عمل</th><th>کاربر</th><th>IP</th><th>زمان</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td>{r.id}</td>
                <td><code>{r.object}#{r.object_id ?? '—'}</code></td>
                <td><span className="badge bg-slate-100 text-slate-700">{r.action}</span></td>
                <td>{r.actor_id || '—'}</td>
                <td><code className="text-xs">{r.ip || '—'}</code></td>
                <td>{r.created_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
