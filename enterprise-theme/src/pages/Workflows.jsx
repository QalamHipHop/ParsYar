import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Workflows() {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState({
    name: '',
    trigger_event: 'invoice.paid',
    graph: {
      nodes: [
        { id: 'start', type: 'start' },
        { id: 'notify', type: 'notify_admin', config: { message: 'پرداخت ثبت شد' } },
      ],
      edges: [{ from: 'start', to: 'notify' }],
    },
  });

  useEffect(() => { api.workflows().then(setRows).catch(() => setRows([])); }, []);

  async function submit(e) {
    e.preventDefault();
    await api.createWorkflow(form);
    setRows(await api.workflows());
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">گردش کار</h1>
      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <input className="input" placeholder="نام" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
          <select className="input" value={form.trigger_event} onChange={e => setForm({ ...form, trigger_event: e.target.value })}>
            <option value="invoice.paid">پرداخت فاکتور</option>
            <option value="lead.created">سرنخ جدید</option>
            <option value="record.created">رکورد جدید</option>
          </select>
          <button className="btn-primary">ایجاد</button>
        </form>
      </div>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>نام</th><th>رویداد</th><th>گره‌ها</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td>{r.name}</td>
                <td><code>{r.trigger_event}</code></td>
                <td>{(JSON.parse(r.graph_json || '{"nodes":[]}').nodes || []).length} گره</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
