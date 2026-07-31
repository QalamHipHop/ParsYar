/**
 * ParsYar Lightweight Store (zustand-like, ~30 lines)
 * usage:
 *   const useUI = create((set) => ({
 *     sidebarOpen: true,
 *     toggle: () => set(s => ({ sidebarOpen: !s.sidebarOpen })),
 *   }));
 *   const sidebarOpen = useUI(s => s.sidebarOpen);
 */
import { useSyncExternalStore } from 'react';

export function create(initFn) {
  let state;
  const listeners = new Set();
  const get = () => state;
  const set = (patch) => {
    const next = typeof patch === 'function' ? patch(state) : patch;
    state = { ...state, ...next };
    listeners.forEach(l => l());
  };
  const subscribe = (fn) => { listeners.add(fn); return () => listeners.delete(fn); };
  state = initFn(set, get);
  const useStore = (selector = (s) => s) =>
    useSyncExternalStore(subscribe, () => selector(get()), () => selector(get()));
  useStore.getState = get;
  useStore.setState = set;
  useStore.subscribe = subscribe;
  return useStore;
}

// ─── Global UI store ───
export const useUI = create((set) => ({
  sidebarOpen: true,
  sidebarCollapsed: false,
  themeMode: (localStorage.getItem('parsyar:theme') || 'light'),
  locale: 'fa-IR',
  commandPaletteOpen: false,
  toggleSidebar:   () => set(s => ({ sidebarOpen: !s.sidebarOpen })),
  collapseSidebar: () => set(s => ({ sidebarCollapsed: !s.sidebarCollapsed })),
  setTheme:        (mode) => {
    localStorage.setItem('parsyar:theme', mode);
    const root = document.documentElement;
    if (mode === 'dark') root.classList.add('dark');
    else if (mode === 'light') root.classList.remove('dark');
    else if (mode === 'auto') {
      const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.classList.toggle('dark', dark);
    }
    set({ themeMode: mode });
  },
  toggleCommand: () => set(s => ({ commandPaletteOpen: !s.commandPaletteOpen })),
  closeCommand:  () => set({ commandPaletteOpen: false }),
}));

// initialize theme on load
if (typeof window !== 'undefined') {
  const t = useUI.getState().themeMode;
  if (t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
  }
}

// ─── Toast store ───
export const useToasts = create((set) => ({
  items: [],
  push: (t) => set(s => ({ items: [...s.items, { id: t.id || Math.random().toString(36).slice(2), ...t }] })),
  remove: (id) => set(s => ({ items: s.items.filter(x => x.id !== id) })),
  clear: () => set({ items: [] }),
}));
