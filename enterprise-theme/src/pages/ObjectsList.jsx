import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';

export default function ObjectsList() {
  const [rows, setRows] = useState([]);
  useEffect(() => { api.objects().then(setRows).catch(() => setRows([])); }, []);
  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">اشیاء تعریف‌شده</h1>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead>
            <tr><th>API</th><th>برچسب</th><th>توضیح</th><th>سیستمی</th><th></th></tr>
          </thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td><code>{r.api_name}</code></td>
                <td>{r.label}</td>
                <td className="text-slate-500">{r.description || '—'}</td>
                <td>{r.is_system ? 'بله' : 'خیر'}</td>
                <td>
                  <Link to={`/records/${r.api_name}`} className="btn-ghost text-brand-600">رکوردها →</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
