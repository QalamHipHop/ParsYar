/**
 * ParsYar Toast — simple global toast manager
 * usage:  toast.success('ذخیره شد');
 *         toast.error('خطایی رخ داد');
 *         toast.info('...', 6000);
 */
import { uid } from './format.js';

let container = null;
const listeners = new Set();

function ensureContainer() {
  if (container) return container;
  container = document.createElement('div');
  container.id = 'parsyar-toast-root';
  Object.assign(container.style, {
    position: 'fixed', bottom: '20px', left: '20px',
    zIndex: 9999, display: 'flex', flexDirection: 'column',
    gap: '10px', pointerEvents: 'none',
  });
  document.body.appendChild(container);
  // RTL support
  if (document.documentElement.dir === 'rtl') {
    container.style.left = 'auto';
    container.style.right = '20px';
  }
  return container;
}

function emit(toasts) {
  for (const l of listeners) l(toasts);
}
function add(t) {
  ensureContainer();
  const id = t.id || uid('toast');
  const node = { id, type: 'info', duration: 4000, ...t };
  emit([{ ...node }]);
  if (node.duration > 0) {
    setTimeout(() => remove(id), node.duration);
  }
  return id;
}
function remove(id) {
  emit((window.__parsyarToasts || []).filter(t => t.id !== id));
}

export const toast = {
  success: (message, opts = {}) => add({ type: 'success', message, ...opts }),
  error:   (message, opts = {}) => add({ type: 'error',   message, duration: 6000, ...opts }),
  warning: (message, opts = {}) => add({ type: 'warning', message, ...opts }),
  info:    (message, opts = {}) => add({ type: 'info',    message, ...opts }),
  remove,
  subscribe(fn) { listeners.add(fn); return () => listeners.delete(fn); },
  getSnapshot() { return window.__parsyarToasts || []; },
  setSnapshot(arr) { window.__parsyarToasts = arr; },
};
