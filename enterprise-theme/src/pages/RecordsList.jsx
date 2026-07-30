import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api/client.js';

export default function RecordsList() {
  const { api: apiName } = useParams();
  const [meta, setMeta]   = useState(null);
  const [rows, setRows]   = useState([]);
  const [form, setForm]   = useState({});
  const [err, setErr]     = useState(null);

  async function load() {
    try {
      const m = await api.object(apiName);
      const r = await api.records(apiName);
      setMeta(m);
      setRows(r.data || []);
      const init = {};
      (m.fields || []).forEach(f => { init[f.api_name] = f.default_value || ''; });
      setForm(init);
    } catch (e) {
      setErr(e.message);
    }
  }

  useEffect(() => { load(); }, [apiName]);

  async function submit(e) {
    e.preventDefault();
    try {
      await api.createRecord(apiName, form);
      setForm({});
      await load();
    } catch (e) {
      setErr(e.message);
    }
  }

  async function del(id) {
    if (!confirm('حذف شود؟')) return;
    await api.deleteRecord(id);
    await load();
  }

  if (err) return <div className="card text-red-600">{err}</div>;
  if (!meta) return <div className="card">در حال بارگذاری...</div>;

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">{meta.label}</h1>

      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {meta.fields.map(f => (
            <div key={f.id}>
              <label className="text-xs text-slate-500">{f.label}{f.is_required ? ' *' : ''}</label>
              {f.type === 'textarea' ? (
                <textarea
                  className="input"
                  value={form[f.api_name] || ''}
                  onChange={e => setForm({ ...form, [f.api_name]: e.target.value })}
                />
              ) : f.type === 'boolean' ? (
                <select className="input" value={form[f.api_name] || '0'} onChange={e => setForm({ ...form, [f.api_name]: e.target.value })}>
                  <option value="0">خیر</option><option value="1">بله</option>
                </select>
              ) : f.type === 'select' ? (
                <select className="input" value={form[f.api_name] || ''} onChange={e => setForm({ ...form, [f.api_name]: e.target.value })}>
                  <option value="">—</option>
                  {(f.options || []).map(o => <option key={o} value={o}>{o}</option>)}
                </select>
              ) : (
                <input
                  type={f.type === 'date' || f.type === 'datetime' ? f.type :
                       f.type === 'number' || f.type === 'decimal' || f.type === 'currency' ? 'number' :
                       f.type === 'email' ? 'email' : 'text'}
                  className="input"
                  value={form[f.api_name] || ''}
                  onChange={e => setForm({ ...form, [f.api_name]: e.target.value })}
                />
              )}
            </div>
          ))}
          <div className="md:col-span-2 flex justify-end">
            <button className="btn-primary" type="submit">ایجاد</button>
          </div>
        </form>
      </div>

      <div className="card overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th>#</th>
              {meta.fields.slice(0, 5).map(f => <th key={f.id}>{f.label}</th>)}
              <th></th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 && (
              <tr><td colSpan={meta.fields.length + 2} className="text-center text-slate-400 py-6">رکوردی نیست</td></tr>
            )}
            {rows.map(r => (
              <tr key={r.id}>
                <td>{r.id}</td>
                {meta.fields.slice(0, 5).map(f => (
                  <td key={f.id}>{String(r.data?.[f.api_name] ?? '—')}</td>
                ))}
                <td>
                  <button onClick={() => del(r.id)} className="text-rose-600 text-xs">حذف</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
