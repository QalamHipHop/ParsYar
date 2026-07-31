import React, { useEffect, useState, useMemo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import Card from './Card.jsx';
import Button from './Button.jsx';
import Badge from './Badge.jsx';
import Input from './Input.jsx';
import { useToasts } from '../store';
import { cx, formatJalali, formatJalaliShort, timeAgo, formatMoney, debounce, truncate } from '../lib/format.js';

/**
 * ResourceTable — generic table + filters + pagination
 * props:
 *   - fetcher: () => Promise<any[]>
 *   - columns: [{ key, label, render?, sortable?, align? }]
 *   - searchKeys: ['full_name', 'email']
 *   - perPage: 20
 *   - onCreate?, onEdit?, onDelete?
 */
export default function ResourceTable({
  title,
  subtitle,
  fetcher,
  columns = [],
  searchKeys = [],
  perPage = 20,
  onCreate,
  onEdit,
  onDelete,
  createLabel = 'ساخت',
  emptyText = 'موردی یافت نشد',
  toolbarExtras,
}) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [sortKey, setSortKey] = useState(null);
  const [sortDir, setSortDir] = useState('desc');
  const [refreshTick, setRefreshTick] = useState(0);
  const push = useToasts(s => s.push);

  const reload = useCallback(() => setRefreshTick(t => t + 1), []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetcher()
      .then(data => { if (!cancelled) setRows(Array.isArray(data) ? data : (data?.data || [])); })
      .catch(e => { if (!cancelled) { setError(e.message || 'خطا'); push({ type: 'error', message: e.message }); } })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [fetcher, refreshTick, push]);

  const filtered = useMemo(() => {
    const norm = (s) => (s || '').toString().toLowerCase().replace(/\u200c/g, ' ').trim();
    const q2 = norm(q);
    let res = !q2 ? rows : rows.filter(r => searchKeys.some(k => norm(r[k]).includes(q2)));
    if (sortKey) {
      res = [...res].sort((a, b) => {
        const av = a[sortKey], bv = b[sortKey];
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        if (typeof av === 'number' && typeof bv === 'number') return sortDir === 'asc' ? av - bv : bv - av;
        return sortDir === 'asc'
          ? String(av).localeCompare(String(bv), 'fa')
          : String(bv).localeCompare(String(av), 'fa');
      });
    }
    return res;
  }, [rows, q, sortKey, sortDir, searchKeys]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
  const safePage = Math.min(page, totalPages);
  const paged = filtered.slice((safePage - 1) * perPage, safePage * perPage);

  const onSort = (key) => {
    if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  const onDeleteClick = async (row) => {
    if (!onDelete) return;
    if (!window.confirm(`حذف "${row.name || row.title || row.full_name || row.id}"؟ این عملیات غیرقابل بازگشت است.`)) return;
    try {
      await onDelete(row);
      push({ type: 'success', message: 'حذف شد.' });
      reload();
    } catch (e) {
      push({ type: 'error', message: 'خطا در حذف: ' + e.message });
    }
  };

  return (
    <div className="space-y-4 animate-fade-in">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-bold tracking-widest uppercase text-ink-500 dark:text-ink-400">{title}</p>
          {subtitle && <p className="text-sm text-ink-500 dark:text-ink-400 mt-0.5">{subtitle}</p>}
        </div>
        <div className="flex gap-2">
          {toolbarExtras}
          <Button variant="secondary" size="sm" icon={<ReloadIcon className="w-4 h-4" />} onClick={reload}>تازه‌سازی</Button>
          {onCreate && <Button variant="primary" size="sm" icon={<PlusIcon className="w-4 h-4" />} onClick={onCreate}>{createLabel}</Button>}
        </div>
      </div>

      <Card variant="glass" padded={false}>
        <div className="p-3 border-b border-ink-200/50 dark:border-white/5 flex flex-wrap items-center gap-2">
          <Input
            placeholder="جستجو..."
            value={q}
            onChange={(e) => { setQ(e.target.value); setPage(1); }}
            className="max-w-xs"
            prefix={<SearchIcon className="w-4 h-4" />}
          />
          <div className="text-[11px] text-ink-500 dark:text-ink-400 ms-auto">
            {loading ? 'در حال بارگذاری...' : `${filtered.length} مورد`}
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                {columns.map(c => (
                  <th
                    key={c.key}
                    onClick={c.sortable ? () => onSort(c.key) : undefined}
                    className={cx('select-none', c.sortable && 'cursor-pointer hover:text-ink-900 dark:hover:text-ink-50')}
                    style={{ textAlign: c.align || 'right' }}
                  >
                    {c.label}
                    {c.sortable && sortKey === c.key && (
                      <span className="ms-1 inline-block">{sortDir === 'asc' ? '▲' : '▼'}</span>
                    )}
                  </th>
                ))}
                {(onEdit || onDelete) && <th style={{ width: 80 }}></th>}
              </tr>
            </thead>
            <tbody>
              {loading && Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {columns.map(c => <td key={c.key}><div className="skeleton h-3 w-3/4 rounded" /></td>)}
                  {(onEdit || onDelete) && <td><div className="skeleton h-6 w-16 rounded" /></td>}
                </tr>
              ))}
              {!loading && error && (
                <tr><td colSpan={columns.length + 1} className="text-center text-danger-600 py-8">{error}</td></tr>
              )}
              {!loading && !error && paged.length === 0 && (
                <tr><td colSpan={columns.length + 1} className="text-center text-ink-500 py-12">
                  <div className="text-2xl mb-1 opacity-30">∅</div>
                  {emptyText}
                </td></tr>
              )}
              {!loading && !error && paged.map((row, idx) => (
                <tr key={row.id ?? idx}>
                  {columns.map(c => (
                    <td key={c.key} style={{ textAlign: c.align || 'right' }}>
                      {c.render ? c.render(row) : (row[c.key] ?? '—')}
                    </td>
                  ))}
                  {(onEdit || onDelete) && (
                    <td>
                      <div className="flex items-center gap-1">
                        {onEdit && <button onClick={() => onEdit(row)} className="p-1.5 rounded-md text-ink-500 hover:bg-ink-100 dark:hover:bg-ink-900 hover:text-brand-600 transition" title="ویرایش"><PencilIcon className="w-4 h-4" /></button>}
                        {onDelete && <button onClick={() => onDeleteClick(row)} className="p-1.5 rounded-md text-ink-500 hover:bg-danger-50 dark:hover:bg-danger-500/10 hover:text-danger-600 transition" title="حذف"><TrashIcon className="w-4 h-4" /></button>}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {totalPages > 1 && (
          <div className="p-3 border-t border-ink-200/50 dark:border-white/5 flex items-center justify-between text-xs">
            <div className="text-ink-500 dark:text-ink-400">صفحهٔ {safePage} از {totalPages}</div>
            <div className="flex items-center gap-1">
              <Button size="xs" variant="ghost" disabled={safePage === 1} onClick={() => setPage(1)}>«</Button>
              <Button size="xs" variant="ghost" disabled={safePage === 1} onClick={() => setPage(p => p - 1)}>قبلی</Button>
              <Button size="xs" variant="ghost" disabled={safePage === totalPages} onClick={() => setPage(p => p + 1)}>بعدی</Button>
              <Button size="xs" variant="ghost" disabled={safePage === totalPages} onClick={() => setPage(totalPages)}>»</Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}

/* ── Icons ── */
const I = (children) => (props) => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>{children}</svg>
);
const PlusIcon    = I(<><path d="M12 5v14M5 12h14"/></>);
const ReloadIcon  = I(<><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></>);
const SearchIcon  = I(<><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></>);
const PencilIcon  = I(<><path d="M12 20h9M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></>);
const TrashIcon   = I(<><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></>);
