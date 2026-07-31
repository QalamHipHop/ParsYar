import React, { useEffect, useMemo, useState, useRef, useCallback } from 'react';
import { api } from '../api/client.js';

/* ------------------------------------------------------------------ *
 *  Visual Workflow Editor — نسخهٔ ۱.۵
 *  - Drag-drop node graph (SVG-based)
 *  - 12 نوع گره (start, end, condition, set_field, send_sms, send_email,
 *    notify_admin, http_request, delay, create_task, branch, merge)
 *  - True/False/default edge labels
 *  - Templates, Duplicate, Run, Logs
 * ------------------------------------------------------------------ */

const NODE_W = 180;
const NODE_H = 72;

function uid(prefix = 'n') {
  return prefix + '_' + Math.random().toString(36).slice(2, 9);
}

function emptyGraph() {
  return {
    nodes: [
      { id: 'start', type: 'start', label: 'شروع', x: 60, y: 200, config: {} },
      { id: 'end',   type: 'end',   label: 'پایان', x: 720, y: 200, config: {} },
    ],
    edges: [{ id: uid('e'), from: 'start', to: 'end', label: 'default' }],
  };
}

export default function Workflows() {
  const [rows, setRows]           = useState([]);
  const [triggers, setTriggers]   = useState({});
  const [nodeMeta, setNodeMeta]   = useState({ nodes: {}, ops: [] });
  const [templates, setTemplates] = useState([]);
  const [stats, setStats]         = useState(null);
  const [editing, setEditing]     = useState(null); // null | {id?, name, trigger_event, graph, description}
  const [logs, setLogs]           = useState(null);
  const [dragging, setDragging]   = useState(null); // {id, offsetX, offsetY}
  const [linking, setLinking]     = useState(null); // {from}
  const [selected, setSelected]   = useState(null); // id node
  const svgRef                    = useRef(null);
  const [error, setError]         = useState('');

  const load = useCallback(async () => {
    try {
      const [w, t, nt, tp, st] = await Promise.all([
        api.workflows(), api.workflowTriggers(), api.workflowNodeTypes(),
        api.workflowTemplates(), api.workflowStats(),
      ]);
      const data = w?.data || w || [];
      setRows(Array.isArray(data) ? data : []);
      setTriggers(t?.data || t || {});
      setNodeMeta(nt?.data || { nodes: {}, ops: [] });
      setTemplates(tp?.data || tp || []);
      setStats(st?.data || st || null);
    } catch (e) { setError(e.message); }
  }, []);

  useEffect(() => { load(); }, [load]);

  function startNew() {
    setEditing({ name: '', trigger_event: Object.keys(triggers)[0] || 'invoice.paid', graph: emptyGraph(), description: '', is_active: true });
    setSelected(null); setLogs(null); setError('');
  }
  function startEdit(row) {
    setEditing({
      id: row.id, name: row.name, trigger_event: row.trigger_event,
      graph: row.graph || emptyGraph(), description: row.description || '',
      is_active: !!row.is_active,
    });
    setSelected(null); setLogs(null); setError('');
  }
  async function loadFromTemplate(tpl) {
    setEditing({ name: tpl.name, trigger_event: tpl.trigger, graph: tpl.graph, description: '', is_active: false });
    setSelected(null); setLogs(null); setError('');
  }

  /* ---- mutation helpers ---- */
  function patchGraph(fn) {
    setEditing(ed => ({ ...ed, graph: fn(ed.graph) }));
  }
  function addNode(type) {
    patchGraph(g => {
      const maxX = g.nodes.reduce((m, n) => Math.max(m, n.x || 0), 0);
      const node = {
        id: uid(type), type, label: nodeMeta.nodes[type]?.label || type,
        x: maxX + 220, y: 200, config: defaultConfig(type),
      };
      return { ...g, nodes: [...g.nodes, node] };
    });
  }
  function removeNode(id) {
    if (id === 'start' || id === 'end') return;
    patchGraph(g => ({
      nodes: g.nodes.filter(n => n.id !== id),
      edges: g.edges.filter(e => e.from !== id && e.to !== id),
    }));
    if (selected === id) setSelected(null);
  }
  function updateNodeConfig(id, partial) {
    patchGraph(g => ({
      ...g,
      nodes: g.nodes.map(n => n.id === id ? { ...n, config: { ...n.config, ...partial } } : n),
    }));
  }
  function renameNode(id, label) {
    patchGraph(g => ({
      ...g,
      nodes: g.nodes.map(n => n.id === id ? { ...n, label } : n),
    }));
  }
  function commitEdge(from, to, label = 'default') {
    if (from === to) return;
    if (editing.graph.edges.some(e => e.from === from && e.to === to && e.label === label)) return;
    patchGraph(g => ({ ...g, edges: [...g.edges, { id: uid('e'), from, to, label }] }));
  }
  function removeEdge(id) {
    patchGraph(g => ({ ...g, edges: g.edges.filter(e => e.id !== id) }));
  }
  function setEdgeLabel(id, label) {
    patchGraph(g => ({ ...g, edges: g.edges.map(e => e.id === id ? { ...e, label } : e) }));
  }

  /* ---- drag handlers ---- */
  function onNodeMouseDown(e, n) {
    if (e.button !== 0) return;
    const pt = svgPoint(e);
    setDragging({ id: n.id, offsetX: pt.x - (n.x || 0), offsetY: pt.y - (n.y || 0) });
    setSelected(n.id);
  }
  function onSvgMouseMove(e) {
    if (dragging) {
      const pt = svgPoint(e);
      patchGraph(g => ({
        ...g,
        nodes: g.nodes.map(n => n.id === dragging.id
          ? { ...n, x: Math.max(0, pt.x - dragging.offsetX), y: Math.max(0, pt.y - dragging.offsetY) }
          : n),
      }));
    }
  }
  function onSvgMouseUp() { setDragging(null); }
  function svgPoint(e) {
    const svg = svgRef.current;
    if (!svg) return { x: 0, y: 0 };
    const r = svg.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  }

  /* ---- save / delete / run ---- */
  async function save() {
    setError('');
    try {
      const payload = {
        name: editing.name,
        trigger_event: editing.trigger_event,
        description: editing.description,
        is_active: !!editing.is_active,
        graph: editing.graph,
      };
      if (editing.id) await api.updateWorkflow(editing.id, payload);
      else            await api.createWorkflow(payload);
      setEditing(null);
      await load();
    } catch (e) { setError(e.message); }
  }
  async function remove(id) {
    if (!confirm('حذف شود؟')) return;
    await api.deleteWorkflow(id);
    if (editing?.id === id) setEditing(null);
    await load();
  }
  async function duplicate(id) {
    await api.duplicateWorkflow(id, '');
    await load();
  }
  async function runNow() {
    if (!editing?.id) return;
    try {
      await api.runWorkflow(editing.id, {});
      const l = await api.workflowLogs(editing.id);
      setLogs(l.data || l);
    } catch (e) { setError(e.message); }
  }
  async function openLogs(id) {
    const l = await api.workflowLogs(id);
    setLogs(l.data || l);
  }

  /* ---- editor render ---- */
  if (editing) {
    return (
      <EditorView
        editing={editing} setEditing={setEditing}
        triggers={triggers} nodeMeta={nodeMeta}
        selected={selected} setSelected={setSelected}
        linking={linking} setLinking={setLinking}
        svgRef={svgRef}
        onMouseMove={onSvgMouseMove} onMouseUp={onSvgMouseUp}
        onNodeMouseDown={onNodeMouseDown}
        addNode={addNode} removeNode={removeNode} updateNodeConfig={updateNodeConfig}
        renameNode={renameNode} commitEdge={commitEdge} removeEdge={removeEdge}
        setEdgeLabel={setEdgeLabel}
        save={save} runNow={runNow} cancel={() => setEditing(null)}
        error={error} logs={logs}
      />
    );
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-2xl font-bold">گردش کار</h1>
        <div className="flex gap-2">
          <button className="btn-primary" onClick={startNew}>+ گردش کار جدید</button>
        </div>
      </div>

      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <Stat label="کل"          value={stats.workflows_total} />
          <Stat label="فعال"        value={stats.workflows_active} />
          <Stat label="اجراها"      value={stats.runs_total} />
          <Stat label="موفق"        value={stats.runs_success} />
          <Stat label="نرخ موفقیت"  value={stats.success_rate + '%'} />
        </div>
      )}

      {templates.length > 0 && (
        <div className="card mb-6">
          <h2 className="font-bold mb-2">قالب‌های آماده</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
            {templates.map(t => (
              <button key={t.id} onClick={() => loadFromTemplate(t)}
                className="text-right p-3 border border-neutral-200 dark:border-neutral-800 rounded-lg hover:border-blue-500 transition">
                <div className="font-semibold">{t.name}</div>
                <div className="text-xs text-neutral-500 mt-1">Trigger: {t.trigger}</div>
                <div className="text-xs text-neutral-500">{t.graph?.nodes?.length || 0} گره</div>
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
              <th>رویداد</th>
              <th>گره‌ها</th>
              <th>وضعیت</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(r => {
              let nodeCount = 0;
              try { nodeCount = (JSON.parse(r.graph_json || '{"nodes":[]}').nodes || []).length; } catch {}
              return (
                <tr key={r.id}>
                  <td>{r.name}</td>
                  <td><code>{r.trigger_event}</code></td>
                  <td>{nodeCount}</td>
                  <td>{r.is_active == 1 ? <span className="badge green">فعال</span> : <span className="badge">غیرفعال</span>}</td>
                  <td className="flex gap-1 flex-wrap">
                    <button className="btn-xs" onClick={() => startEdit(r)}>ویرایش</button>
                    <button className="btn-xs" onClick={() => duplicate(r.id)}>کپی</button>
                    <button className="btn-xs" onClick={() => openLogs(r.id)}>لاگ</button>
                    <button className="btn-xs danger" onClick={() => remove(r.id)}>حذف</button>
                  </td>
                </tr>
              );
            })}
            {rows.length === 0 && <tr><td colSpan="5" className="text-center text-neutral-500 py-4">گردش کاری وجود ندارد.</td></tr>}
          </tbody>
        </table>
      </div>

      {logs && (
        <div className="card mt-6">
          <h2 className="font-bold mb-2">لاگ‌های اخیر</h2>
          <pre className="text-xs bg-neutral-50 dark:bg-neutral-900 p-3 rounded overflow-x-auto" dir="ltr">
{JSON.stringify(logs.slice(0, 50), null, 2)}
          </pre>
          <button className="btn-xs mt-2" onClick={() => setLogs(null)}>بستن</button>
        </div>
      )}
    </div>
  );
}

function Stat({ label, value }) {
  return (
    <div className="card">
      <div className="text-xs text-neutral-500">{label}</div>
      <div className="text-2xl font-bold mt-1">{value}</div>
    </div>
  );
}

function defaultConfig(type) {
  switch (type) {
    case 'condition':    return { field: '', op: '==', value: '' };
    case 'set_field':    return { object: 'contact', id_path: 'record_id', field: '', value: '' };
    case 'send_sms':     return { to: '', message: '' };
    case 'send_email':   return { to: '', subject: '', body: '' };
    case 'notify_admin': return { message: '' };
    case 'http_request': return { url: '', method: 'POST', body: '' };
    case 'delay':        return { seconds: 60 };
    case 'create_task':  return { title: '', assignee: 0, due: '+1 day' };
    default:             return {};
  }
}

/* ----------------- Editor View ----------------- */

function EditorView(props) {
  const {
    editing, setEditing, triggers, nodeMeta, selected, setSelected,
    linking, setLinking, svgRef,
    onMouseMove, onMouseUp, onNodeMouseDown,
    addNode, removeNode, updateNodeConfig, renameNode,
    commitEdge, removeEdge, setEdgeLabel,
    save, runNow, cancel, error, logs,
  } = props;

  const sel = useMemo(
    () => editing.graph.nodes.find(n => n.id === selected) || null,
    [editing, selected]
  );

  return (
    <div>
      <div className="flex items-center justify-between mb-3">
        <h1 className="text-xl font-bold">ویرایشگر گردش کار</h1>
        <div className="flex gap-2">
          <button className="btn-xs" onClick={cancel}>انصراف</button>
          {editing.id && <button className="btn-xs" onClick={runNow}>اجرای دستی</button>}
          <button className="btn-primary" onClick={save}>ذخیره</button>
        </div>
      </div>

      <div className="card mb-3">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
          <input className="input" placeholder="نام گردش کار"
            value={editing.name}
            onChange={e => setEditing({ ...editing, name: e.target.value })} />
          <select className="input"
            value={editing.trigger_event}
            onChange={e => setEditing({ ...editing, trigger_event: e.target.value })}>
            {Object.entries(triggers).map(([k, v]) => (
              <option key={k} value={k}>{v} ({k})</option>
            ))}
          </select>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox"
              checked={!!editing.is_active}
              onChange={e => setEditing({ ...editing, is_active: e.target.checked })} />
            فعال
          </label>
          <input className="input" placeholder="توضیح (اختیاری)"
            value={editing.description}
            onChange={e => setEditing({ ...editing, description: e.target.value })} />
        </div>
      </div>

      <div className="grid grid-cols-12 gap-3">
        {/* Sidebar: node types */}
        <div className="col-span-12 md:col-span-2">
          <div className="card">
            <div className="font-bold text-sm mb-2">افزودن گره</div>
            <div className="grid grid-cols-1 gap-1">
              {Object.entries(nodeMeta.nodes || {}).map(([k, v]) => (
                <button key={k}
                  className="text-right text-xs p-2 border border-neutral-200 dark:border-neutral-800 rounded hover:border-blue-500"
                  onClick={() => addNode(k)}>
                  <span className="inline-block w-2 h-2 rounded-full ml-1" style={{ background: v.color }} />
                  {v.label} <span className="text-neutral-400">({k})</span>
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Canvas */}
        <div className="col-span-12 md:col-span-7">
          <div className="card p-0 overflow-hidden" style={{ height: 540 }}>
            <svg ref={svgRef} width="100%" height="100%"
              onMouseMove={onMouseMove} onMouseUp={onMouseUp}
              style={{ background: 'var(--surface-1, #fafafa)', cursor: dragging ? 'grabbing' : 'default' }}>

              {/* Edges */}
              {editing.graph.edges.map(e => {
                const a = editing.graph.nodes.find(n => n.id === e.from);
                const b = editing.graph.nodes.find(n => n.id === e.to);
                if (!a || !b) return null;
                const x1 = (a.x || 0) + NODE_W;
                const y1 = (a.y || 0) + NODE_H / 2;
                const x2 = (b.x || 0);
                const y2 = (b.y || 0) + NODE_H / 2;
                const mx = (x1 + x2) / 2;
                const path = `M ${x1} ${y1} C ${mx} ${y1}, ${mx} ${y2}, ${x2} ${y2}`;
                const color = e.label === 'true' ? '#16a34a' : e.label === 'false' ? '#dc2626' : '#94a3b8';
                const midX = (x1 + x2) / 2;
                const midY = (y1 + y2) / 2;
                return (
                  <g key={e.id}>
                    <path d={path} stroke={color} strokeWidth="2" fill="none" />
                    <circle cx={x2 - 4} cy={y2} r="3" fill={color} />
                    <foreignObject x={midX - 30} y={midY - 12} width="60" height="24">
                      <div style={{ textAlign: 'center' }}>
                        <span onClick={() => {
                          const nl = prompt('label (true|false|default):', e.label);
                          if (nl) setEdgeLabel(e.id, nl);
                        }} className="px-1 py-0.5 bg-white dark:bg-neutral-800 border rounded text-[10px] cursor-pointer">
                          {e.label}
                        </span>
                        <span onClick={() => removeEdge(e.id)} className="px-1 mr-1 text-red-500 cursor-pointer text-[10px]">✕</span>
                      </div>
                    </foreignObject>
                  </g>
                );
              })}

              {/* Nodes */}
              {editing.graph.nodes.map(n => {
                const meta = nodeMeta.nodes?.[n.type] || { color: '#64748b', label: n.type };
                return (
                  <g key={n.id} transform={`translate(${n.x || 0}, ${n.y || 0})`}
                     onMouseDown={(e) => onNodeMouseDown(e, n)}
                     onClick={() => setSelected(n.id)}
                     style={{ cursor: 'grab' }}>
                    <rect width={NODE_W} height={NODE_H} rx="10" ry="10"
                      fill="white" stroke={selected === n.id ? '#2563eb' : meta.color} strokeWidth={selected === n.id ? 3 : 2} />
                    <rect x="0" y="0" width="6" height={NODE_H} fill={meta.color} rx="3" ry="3" />
                    <text x="16" y="22" fontSize="11" fontWeight="700" fill={meta.color}>{meta.label}</text>
                    <text x="16" y="42" fontSize="13" fill="#0f172a">
                      {(n.label || '').slice(0, 22)}
                    </text>
                    <text x="16" y="60" fontSize="10" fill="#64748b">{n.id}</text>
                    {/* Output handle (for linking) */}
                    <circle cx={NODE_W} cy={NODE_H / 2} r="6" fill={meta.color}
                      onMouseUp={(e) => {
                        e.stopPropagation();
                        if (linking && linking.from && linking.from !== n.id) {
                          const lbl = prompt('label (true|false|default):', 'default') || 'default';
                          commitEdge(linking.from, n.id, lbl);
                        }
                        setLinking(null);
                      }}
                    />
                    {/* Input handle */}
                    <circle cx={0} cy={NODE_H / 2} r="6" fill="#94a3b8"
                      onMouseDown={(e) => {
                        e.stopPropagation();
                        setLinking({ from: n.id });
                      }}
                      onMouseUp={(e) => {
                        e.stopPropagation();
                        if (linking && linking.from && linking.from !== n.id) {
                          const lbl = prompt('label (true|false|default):', 'default') || 'default';
                          commitEdge(linking.from, n.id, lbl);
                        }
                        setLinking(null);
                      }}
                    />
                  </g>
                );
              })}
            </svg>
          </div>
          <div className="text-xs text-neutral-500 mt-2">
            برای اتصال دو گره: روی دایرهٔ خروجی (راست) کلیک کنید، به گرهٔ مقصد بکشید، label را تعیین کنید.
          </div>
        </div>

        {/* Inspector */}
        <div className="col-span-12 md:col-span-3">
          <div className="card">
            <div className="font-bold text-sm mb-2">ویژگی‌ها</div>
            {!sel && <div className="text-xs text-neutral-500">یک گره انتخاب کنید.</div>}
            {sel && (
              <div className="space-y-2 text-sm">
                <div>
                  <label className="text-xs text-neutral-500">شناسه</label>
                  <input className="input" value={sel.id} disabled />
                </div>
                <div>
                  <label className="text-xs text-neutral-500">نوع</label>
                  <input className="input" value={sel.type} disabled />
                </div>
                <div>
                  <label className="text-xs text-neutral-500">برچسب</label>
                  <input className="input" value={sel.label}
                    onChange={e => renameNode(sel.id, e.target.value)} />
                </div>
                <ConfigEditor node={sel} onChange={(p) => updateNodeConfig(sel.id, p)} />
                {sel.type !== 'start' && sel.type !== 'end' && (
                  <button className="btn-xs danger w-full" onClick={() => removeNode(sel.id)}>حذف گره</button>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {error && <div className="card mt-3 text-red-500 text-sm">{error}</div>}

      {logs && (
        <div className="card mt-4">
          <h2 className="font-bold mb-2">لاگ اجرای دستی</h2>
          <pre className="text-xs bg-neutral-50 dark:bg-neutral-900 p-3 rounded overflow-x-auto" dir="ltr">
{JSON.stringify(logs.slice(0, 30), null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}

function ConfigEditor({ node, onChange }) {
  const c = node.config || {};
  switch (node.type) {
    case 'condition':
      return (
        <div className="space-y-2">
          <div>
            <label className="text-xs text-neutral-500">فیلد (path)</label>
            <input className="input" value={c.field || ''} onChange={e => onChange({ field: e.target.value })} placeholder="invoice.amount" />
          </div>
          <div>
            <label className="text-xs text-neutral-500">عملگر</label>
            <select className="input" value={c.op || '=='} onChange={e => onChange({ op: e.target.value })}>
              {['==','!=','>','>=','<','<=','contains','starts_with','ends_with','in','not_in','empty','not_empty'].map(o => (
                <option key={o} value={o}>{o}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-xs text-neutral-500">مقدار</label>
            <input className="input" value={c.value ?? ''} onChange={e => onChange({ value: e.target.value })} />
          </div>
        </div>
      );
    case 'set_field':
      return (
        <div className="space-y-2">
          <input className="input" placeholder="object (contact/deal/...)" value={c.object || ''} onChange={e => onChange({ object: e.target.value })} />
          <input className="input" placeholder="id_path" value={c.id_path || ''} onChange={e => onChange({ id_path: e.target.value })} />
          <input className="input" placeholder="field" value={c.field || ''} onChange={e => onChange({ field: e.target.value })} />
          <input className="input" placeholder="value (supports {{...}})" value={c.value || ''} onChange={e => onChange({ value: e.target.value })} />
        </div>
      );
    case 'send_sms':
      return (
        <div className="space-y-2">
          <input className="input" placeholder="to (supports {{...}})" value={c.to || ''} onChange={e => onChange({ to: e.target.value })} />
          <textarea className="input" rows="3" placeholder="message" value={c.message || ''} onChange={e => onChange({ message: e.target.value })} />
        </div>
      );
    case 'send_email':
      return (
        <div className="space-y-2">
          <input className="input" placeholder="to" value={c.to || ''} onChange={e => onChange({ to: e.target.value })} />
          <input className="input" placeholder="subject" value={c.subject || ''} onChange={e => onChange({ subject: e.target.value })} />
          <textarea className="input" rows="3" placeholder="body" value={c.body || ''} onChange={e => onChange({ body: e.target.value })} />
        </div>
      );
    case 'notify_admin':
      return <textarea className="input" rows="3" placeholder="message" value={c.message || ''} onChange={e => onChange({ message: e.target.value })} />;
    case 'http_request':
      return (
        <div className="space-y-2">
          <input className="input" placeholder="url" value={c.url || ''} onChange={e => onChange({ url: e.target.value })} />
          <select className="input" value={c.method || 'POST'} onChange={e => onChange({ method: e.target.value })}>
            {['GET','POST','PUT','PATCH','DELETE'].map(m => <option key={m} value={m}>{m}</option>)}
          </select>
          <textarea className="input" rows="3" placeholder="body (JSON)" value={c.body || ''} onChange={e => onChange({ body: e.target.value })} />
        </div>
      );
    case 'delay':
      return <input className="input" type="number" min="0" max="3600" placeholder="seconds" value={c.seconds || 0} onChange={e => onChange({ seconds: parseInt(e.target.value || 0) })} />;
    case 'create_task':
      return (
        <div className="space-y-2">
          <input className="input" placeholder="title" value={c.title || ''} onChange={e => onChange({ title: e.target.value })} />
          <input className="input" type="number" placeholder="assignee user_id" value={c.assignee || 0} onChange={e => onChange({ assignee: parseInt(e.target.value || 0) })} />
          <input className="input" placeholder="due (+7 days)" value={c.due || ''} onChange={e => onChange({ due: e.target.value })} />
        </div>
      );
    default:
      return <div className="text-xs text-neutral-500">این گره تنظیمات اضافی ندارد.</div>;
  }
}
