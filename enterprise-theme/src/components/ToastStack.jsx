import React, { useEffect } from 'react';
import { useToasts } from '../store';
import { cx } from '../lib/format.js';

const ICONS = {
  success: 'v',
  error:   'x',
  warning: '!',
  info:    'i',
};
const COLORS = {
  success: 'text-success-600 dark:text-success-500',
  error:   'text-danger-600  dark:text-danger-500',
  warning: 'text-warning-600 dark:text-warning-500',
  info:    'text-info-600    dark:text-info-500',
};

export default function ToastStack() {
  const items = useToasts(s => s.items);
  const remove = useToasts(s => s.remove);
  useEffect(() => {
    // ensure stack not rendered in DOM twice
  }, []);
  return (
    <div
      className="fixed z-[9999] bottom-5 start-5 flex flex-col gap-2 pointer-events-none"
      style={{ maxWidth: 380 }}
    >
      {items.map(t => (
        <div
          key={t.id}
          className={cx(
            'pointer-events-auto surface-glass-strong rounded-xl px-4 py-3 min-w-[260px]',
            'text-sm flex items-start gap-3 animate-slide-up border-s-4',
            t.type === 'success' && 'border-s-success-500',
            t.type === 'error'   && 'border-s-danger-500',
            t.type === 'warning' && 'border-s-warning-500',
            (!t.type || t.type === 'info') && 'border-s-info-500',
          )}
          role="alert"
        >
          <span className={cx('flex-shrink-0 w-5 h-5 rounded-full grid place-items-center text-white text-[11px] font-bold',
            t.type === 'success' && 'bg-success-500',
            t.type === 'error'   && 'bg-danger-500',
            t.type === 'warning' && 'bg-warning-500',
            (!t.type || t.type === 'info') && 'bg-info-500',
          )}>{ICONS[t.type || 'info']}</span>
          <div className="flex-1 min-w-0">
            {t.title && <div className="font-semibold mb-0.5 text-ink-900 dark:text-ink-50">{t.title}</div>}
            <div className="text-ink-700 dark:text-ink-200 leading-relaxed">{t.message}</div>
          </div>
          <button
            onClick={() => remove(t.id)}
            className="flex-shrink-0 text-ink-400 hover:text-ink-700 dark:hover:text-ink-100 transition"
            aria-label="بستن"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
      ))}
    </div>
  );
}
