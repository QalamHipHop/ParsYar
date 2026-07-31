import React, { useEffect } from 'react';
import { cx } from '../lib/format.js';

/**
 * Modal — fullscreen overlay, glass-strong card, focus trap, escape to close
 */
export default function Modal({ open, onClose, title, size = 'md', footer, children, className }) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => { if (e.key === 'Escape') onClose?.(); };
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
  }, [open, onClose]);

  if (!open) return null;
  const sizes = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', full: 'max-w-[95vw]' };
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 animate-fade-in" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-ink-950/40 backdrop-blur-sm" onClick={onClose} />
      <div className={cx('relative w-full surface-glass-strong rounded-2xl shadow-glass-lg animate-scale-in', sizes[size] || sizes.md, className)}>
        {title && (
          <div className="flex items-center justify-between px-5 py-4 border-b border-ink-200/50 dark:border-white/10">
            <h2 className="text-base font-bold tracking-tight text-ink-900 dark:text-ink-50">{title}</h2>
            <button onClick={onClose} className="text-ink-400 hover:text-ink-700 dark:hover:text-ink-100 transition" aria-label="بستن">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
        )}
        <div className="px-5 py-4 max-h-[70vh] overflow-y-auto">{children}</div>
        {footer && (
          <div className="px-5 py-3 border-t border-ink-200/50 dark:border-white/10 flex items-center justify-end gap-2">{footer}</div>
        )}
      </div>
    </div>
  );
}
