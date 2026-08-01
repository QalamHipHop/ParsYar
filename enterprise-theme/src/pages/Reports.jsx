import React, { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client.js';

/* ------------------------------------------------------------------ *
 *  Custom Report Builder — نسخهٔ ۱.۶
 *  - Data source picker
 *  - Filters (field/op/value)
 *  - Group by + Metrics (count/sum/avg/min/max)
 *  - Live preview (run with current config)
 *  - Save / Run / Export CSV
 *  - Templates starter
 * ------------------------------------------------------------------ */

const OPS = ['==','!=','>','>=','<','<=','contains','in','empty','not_empty'];
const CHARTS = [
  { v: 'table', label: 'جدول' },
  { v: 'bar',   label: 'میله‌ای' },
  { v: 'line',  label: 'خطی' },
  { v: 'pie',   label: 'دایره‌ای' },
  { v: 'area',  label: 'ناحیه‌ای' },
];
const AGGS = ['count','sum','avg','min','max'];

export default function Reports() {
  const [reports, setReports]   = useState([]);
  const [sources, setSources]   = useState({});
  const [templates, setTemplates] = useState([]);
  const [editing, setEditing]   = useState(null);   // null | {...}
  const [preview, setPreview]   = useState(null);   // result of run
  const [loading, setLoading]   = useState(false);
  const [error, setError]       = useState('');

  const load = async () => {
    try {
      const [list, src, tpl] = await Promise.all([
        api.reports(), api.reportSources(), api.reportTemplates(),
      ]);
      setReports(list?.data || list || []);
      setSources(src?.data || src || {});
      setTemplates(tpl?.data || tpl || []);
    } catch (e) { setError(e.message); }
  };
  useEffect(() => { load(); }, []);

  function startNew() {
    setEditing({
      name: '', description: '',
      data_source: Object.keys(sources)[0] || 'contacts',
      chart_type: 'table',
      is_public: false,
      config: { filters: [], group_by: [], metrics: [], sort_by: '', sort_dir: 'asc', limit: 1000 },
    });
    setPreview(null);
    setError('');
  }
  function startEdit(r) {
    setEditing({
      id: r.id, name: r.name, description: r.description || '',
      data_source: r.data_source, chart_type: r.chart_type || 'table',
      is_public: !!r.is_public,
      config: r.config || { filters: [], group_by: [], metrics: [], sort_by: '', sort_dir: 'asc', limit: 1000 },
    });
    setPreview(null);
  }
  function loadTemplate(t) {
    setEditing({
      name: t.name, description: '',
      data_source: t.data_source, chart_type: t.chart_type || 'table',
      is_public: false, config: t.config,
    });
  }
  async function save() {
    setError('');
    try {
      setLoading(true);
      const payload = {
        name: editing.name, description: editing.description,
        data_source: editing.data_source, chart_type: editing.chart_type,
        is_public: editing.is_public, ...editing.config,
      };
      if (editing.id) await api.updateReport(editing.id, payload);
      else            await api.createReport(payload);
      setEditing(null); setPreview(null); await load();
    } catch (e) { setError(e.message); }
    finally { setLoading(false); }
  }
  async function remove(id) {
    if (!confirm('حذف شود؟')) return;
    await api.deleteReport(id);
    if (editing?.id === id) setEditing(null);
    await load();
  }
  async function runPreview() {
    setError('');
    try {
      setLoading(true);
      const r = await api.previewReport({
        data_source: editing.data_source, ...editing.config,
      });
      setPreview(r.data || r);
    } catch (e) { setError(e.message); }
    finally { setLoading(false); }
  }
  async function runSaved(id) {
    setError('');
    try {
      setLoading(true);
      const r = await api.runReport(id);
      setPreview(r.data || r);
    } catch (e) { setError(e.message); }
    finally { setLoading(false); }
  }
  function exportCsv() {
    if (!editing?.id) return;
    const url = (window.EnterpriseConfig?.restUrl || '/wp-json/enterprise/v1')
              + `/reports/${editing.id}/export.csv?_wpnonce=${window.EnterpriseConfig?.nonce || ''}`;
    window.open(url, '_blank');
  }

  if (editing) {
    return <Editor
      editing={editing} setEditing={setEditing}
      sources={sources} preview={preview}
      error={error} loading={loading}
      onPreview={runPreview} onSave={save} onExport={editing.id ? exportCsv : null}
      onCancel={() => { setEditing(null); setPreview(null); }}
    />;
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-2xl font-bold">سازندهٔ گزارش</h1>
        <button className="btn-primary" onClick={startNew}>+ گزارش جدید</button>
      </div>

      {templates.length > 0 && (
        <div className="card mb-4">
          <h2 className="font-bold mb-2">قالب‌های آماده</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2">
            {templates.map(t => (
              <button key={t.id} onClick={() => loadTemplate(t)}
                className="text-right p-3 border border-neutral-200 dark:border-neutral-800 rounded-lg hover:border-blue-500">
                <div className="font-semibold">{t.name}</div>
                <div className="text-xs text-neutral-500 mt-1">{t.data_source} · {t.chart_type}</div>
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="card overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th>نام</th>
              <th>منبع</th>
              <th>نمودار</th>
              <th>اشتراکی</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            {reports.map(r => (
              <tr key={r.id}>
                <td>{r.name}</td>
                <td><code>{r.data_source}</code></td>
                <td>{r.chart_type || 'table'}</td>
                <td>{r.is_public == 1 ? 'بله' : 'خیر'}</td>
                <td className="flex gap-1">
                  <button className="btn-xs" onClick={() => startEdit(r)}>ویرایش</button>
                  <button className="btn-xs" onClick={() => runSaved(r.id)}>اجرا</button>
                  <a className="btn-xs" target="_blank" rel="noreferrer"
                    href={`${window.EnterpriseConfig?.restUrl || '/wp-json/enterprise/v1'}/reports/${r.id}/export.csv?_wpnonce=${window.EnterpriseConfig?.nonce || ''}`}>
                    CSV
                  </a>
                  <button className="btn-xs danger" onClick={() => remove(r.id)}>حذف</button>
                </td>
              </tr>
            ))}
            {reports.length === 0 && <tr><td colSpan="5" className="text-center text-neutral-500 py-4">گزارشی وجود ندارد.</td></tr>}
          </tbody>
        </table>
      </div>

      {preview && (
        <div className="card mt-6">
          <h2 className="font-bold mb-2">نتیجهٔ اجرا</h2>
          <PreviewResult result={preview} />
          <button className="btn-xs mt-2" onClick={() => setPreview(null)}>بستن</button>
        </div>
      )}

      {error && <div className="card mt-3 text-red-500 text-sm">{error}</div>}
    </div>
  );
}

function Editor({ editing, setEditing, sources, preview, error, loading, onPreview, onSave, onExport, onCancel }) {
  const src = sources[editing.data_source] || { columns: [] };
  const columns = src.columns || [];
  const cfg = editing.config;

  const setCfg = (patch) => setEditing({ ...editing, config: { ...cfg, ...patch } });
  const addFilter = () => setCfg({ filters: [...(cfg.filters || []), { field: columns[0] || 'id', op: '==', value: '' }] });
  const addMetric = () => setCfg({ metrics: [...(cfg.metrics || []), { agg: 'count', col: '*', alias: 'count' }] });
  const setFilter = (i, patch) => {
    const next = [...(cfg.filters || [])]; next[i] = { ...next[i], ...patch }; setCfg({ filters: next });
  };
  const setMetric = (i, patch) => {
    const next = [...(cfg.metrics || [])]; next[i] = { ...next[i], ...patch }; setCfg({ metrics: next });
  };
  const removeAt = (key, i) => setCfg({ [key]: cfg[key].filter((_, x) => x !== i) });
  const toggleGroup = (col) => {
    const g = new Set(cfg.group_by || []);
    g.has(col) ? g.delete(col) : g.add(col);
    setCfg({ group_by: Array.from(g) });
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-3">
        <h1 className="text-xl font-bold">{editing.id ? 'ویرایش گزارش' : 'ساخت گزارش'}</h1>
        <div className="flex gap-2">
          <button className="btn-xs" onClick={onCancel}>انصراف</button>
          <button className="btn-xs" onClick={onPreview} disabled={loading}>پیش‌نمایش</button>
          {onExport && <button className="btn-xs" onClick={onExport}>خروجی CSV</button>}
          <button className="btn-primary" onClick={onSave} disabled={loading}>ذخیره</button>
        </div>
      </div>

      <div className="grid grid-cols-12 gap-3">
        <div className="col-span-12 md:col-span-8">
          <div className="card mb-3">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
              <input className="input" placeholder="نام گزارش" value={editing.name}
                onChange={e => setEditing({ ...editing, name: e.target.value })} />
              <select className="input" value={editing.data_source}
                onChange={e => setEditing({ ...editing, data_source: e.target.value, config: { filters: [], group_by: [], metrics: [] } })}>
                {Object.entries(sources).map(([k, v]) => (
                  <option key={k} value={k}>{v.label || k}</option>
                ))}
              </select>
              <select className="input" value={editing.chart_type}
                onChange={e => setEditing({ ...editing, chart_type: e.target.value })}>
                {CHARTS.map(c => <option key={c.v} value={c.v}>{c.label}</option>)}
              </select>
              <input className="input md:col-span-2" placeholder="توضیح (اختیاری)" value={editing.description}
                onChange={e => setEditing({ ...editing, description: e.target.value })} />
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={!!editing.is_public}
                  onChange={e => setEditing({ ...editing, is_public: e.target.checked })} />
                عمومی (قابل دسترسی برای همه)
              </label>
            </div>
          </div>

          <div className="card mb-3">
            <div className="flex items-center justify-between mb-2">
              <h2 className="font-bold">فیلترها</h2>
              <button className="btn-xs" onClick={addFilter}>+ افزودن</button>
            </div>
            {(cfg.filters || []).map((f, i) => (
              <div key={i} className="grid grid-cols-12 gap-2 mb-2">
                <select className="input col-span-4" value={f.field} onChange={e => setFilter(i, { field: e.target.value })}>
                  {columns.map(c => <option key={c} value={c}>{c}</option>)}
                </select>
                <select className="input col-span-2" value={f.op} onChange={e => setFilter(i, { op: e.target.value })}>
                  {OPS.map(o => <option key={o} value={o}>{o}</option>)}
                </select>
                {(f.op === 'empty' || f.op === 'not_empty') ? (
                  <div className="col-span-5 text-xs text-neutral-500 flex items-center">— بدون نیاز به مقدار —</div>
                ) : f.op === 'in' ? (
                  <input className="input col-span-5" placeholder="مقدارها با ویرگول: a,b,c"
                    value={Array.isArray(f.value) ? f.value.join(',') : (f.value || '')}
                    onChange={e => setFilter(i, { value: e.target.value.split(',').map(s => s.trim()) })} />
                ) : (
                  <input className="input col-span-5" value={f.value ?? ''} onChange={e => setFilter(i, { value: e.target.value })} />
                )}
                <button className="btn-xs danger col-span-1" onClick={() => removeAt('filters', i)}>x</button>
              </div>
            ))}
            {(!cfg.filters || cfg.filters.length === 0) && (
              <div className="text-xs text-neutral-500">بدون فیلتر — همهٔ ردیف‌ها.</div>
            )}
          </div>

          <div className="card mb-3">
            <h2 className="font-bold mb-2">گروه‌بندی (Group By)</h2>
            <div className="flex flex-wrap gap-1">
              {columns.map(c => {
                const on = (cfg.group_by || []).includes(c);
                return (
                  <button key={c}
                    onClick={() => toggleGroup(c)}
                    className={'px-2 py-1 text-xs rounded border ' + (on ? 'bg-blue-500 text-white border-blue-500' : 'border-neutral-300 dark:border-neutral-700')}>
                    {c}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="card mb-3">
            <div className="flex items-center justify-between mb-2">
              <h2 className="font-bold">سنجه‌ها (Metrics)</h2>
              <button className="btn-xs" onClick={addMetric}>+ افزودن</button>
            </div>
            {(cfg.metrics || []).map((m, i) => (
              <div key={i} className="grid grid-cols-12 gap-2 mb-2">
                <select className="input col-span-2" value={m.agg} onChange={e => setMetric(i, { agg: e.target.value })}>
                  {AGGS.map(a => <option key={a} value={a}>{a}</option>)}
                </select>
                {m.agg === 'count' ? (
                  <input className="input col-span-4" value="*" disabled />
                ) : (
                  <select className="input col-span-4" value={m.col} onChange={e => setMetric(i, { col: e.target.value })}>
                    {columns.map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                )}
                <input className="input col-span-5" placeholder="نام مستعار" value={m.alias}
                  onChange={e => setMetric(i, { alias: e.target.value })} />
                <button className="btn-xs danger col-span-1" onClick={() => removeAt('metrics', i)}>x</button>
              </div>
            ))}
            {(!cfg.metrics || cfg.metrics.length === 0) && (
              <div className="text-xs text-neutral-500">بدون سنجه — لیست ساده از ردیف‌ها نمایش داده می‌شود.</div>
            )}
          </div>

          <div className="card mb-3">
            <h2 className="font-bold mb-2">مرتب‌سازی و محدودیت</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
              <select className="input" value={cfg.sort_by || ''} onChange={e => setCfg({ sort_by: e.target.value })}>
                <option value="">(بدون مرتب‌سازی)</option>
                {columns.map(c => <option key={c} value={c}>{c}</option>)}
                {(cfg.metrics || []).map(m => m.alias && <option key={'m-'+m.alias} value={m.alias}>{m.alias}</option>)}
              </select>
              <select className="input" value={cfg.sort_dir || 'asc'} onChange={e => setCfg({ sort_dir: e.target.value })}>
                <option value="asc">صعودی</option>
                <option value="desc">نزولی</option>
              </select>
              <input className="input" type="number" min="1" max="5000" placeholder="Limit"
                value={cfg.limit || 1000} onChange={e => setCfg({ limit: parseInt(e.target.value || 1000) })} />
            </div>
          </div>
        </div>

        <div className="col-span-12 md:col-span-4">
          <div className="card">
            <h2 className="font-bold mb-2">پیش‌نمایش</h2>
            {!preview && <div className="text-xs text-neutral-500">روی «پیش‌نمایش» بزنید.</div>}
            {preview && <PreviewResult result={preview} />}
          </div>
        </div>
      </div>

      {error && <div className="card mt-3 text-red-500 text-sm">{error}</div>}
    </div>
  );
}

function PreviewResult({ result }) {
  if (!result) return null;
  if (result.error) {
    return <div className="text-red-500 text-sm">خطا: {result.error}</div>;
  }
  const rows = result.rows || [];
  if (rows.length === 0) {
    return <div className="text-xs text-neutral-500">بدون نتیجه.</div>;
  }
  const headers = Object.keys(rows[0]);
  const total = result.total ?? rows.length;
  return (
    <div>
      <div className="text-xs text-neutral-500 mb-1">
        mode: {result.mode} · total: {total} · source: {result.source}
      </div>
      <div className="overflow-x-auto" style={{ maxHeight: 420 }}>
        <table className="table">
          <thead>
            <tr>{headers.map(h => <th key={h}>{h}</th>)}</tr>
          </thead>
          <tbody>
            {rows.slice(0, 200).map((r, i) => (
              <tr key={i}>
                {headers.map(h => <td key={h} className="text-xs">{String(r[h] ?? '')}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length > 200 && <div className="text-xs text-neutral-500 mt-1">نمایش ۲۰۰ ردیفٔ اول از {rows.length}.</div>}
    </div>
  );
}
