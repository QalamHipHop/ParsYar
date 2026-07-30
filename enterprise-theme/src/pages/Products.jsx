import React, { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function Products() {
  const [rows, setRows]   = useState([]);
  const [form, setForm]   = useState({ sku: '', name: '', cost: 0, price: 0, stock: 0 });

  async function load() { setRows(await api.products()); }
  useEffect(() => { load(); }, []);

  async function submit(e) {
    e.preventDefault();
    try { await api.createProduct(form); } catch (_) {}
    setForm({ sku: '', name: '', cost: 0, price: 0, stock: 0 });
    await load();
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">انبار و محصولات</h1>
      <div className="card mb-6">
        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-6 gap-3">
          <input className="input" placeholder="SKU" value={form.sku} onChange={e => setForm({ ...form, sku: e.target.value })} />
          <input className="input md:col-span-2" placeholder="نام" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
          <input className="input" type="number" placeholder="بها" value={form.cost} onChange={e => setForm({ ...form, cost: +e.target.value })} />
          <input className="input" type="number" placeholder="قیمت" value={form.price} onChange={e => setForm({ ...form, price: +e.target.value })} />
          <input className="input" type="number" placeholder="موجودی" value={form.stock} onChange={e => setForm({ ...form, stock: +e.target.value })} />
          <button className="btn-primary md:col-span-6">افزودن</button>
        </form>
      </div>
      <div className="card overflow-x-auto">
        <table className="table">
          <thead><tr><th>SKU</th><th>نام</th><th>بها</th><th>قیمت</th><th>موجودی</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td><code>{r.sku}</code></td>
                <td>{r.name}</td>
                <td>{Number(r.cost).toLocaleString()}</td>
                <td>{Number(r.price).toLocaleString()}</td>
                <td>{r.stock}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
