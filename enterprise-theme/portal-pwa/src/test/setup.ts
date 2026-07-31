import { vi } from 'vitest';

// matchMedia stub
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((q: string) => ({
    matches: false,
    media: q,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn()
  }))
});

// IntersectionObserver stub
class IO { observe(){} unobserve(){} disconnect(){} takeRecords(){return [];} }
(window as any).IntersectionObserver = IO;

// localStorage stub for jsdom
const _ls: Record<string, string> = {};
const ls = {
  getItem: (k: string) => k in _ls ? _ls[k] : null,
  setItem: (k: string, v: string) => { _ls[k] = String(v); },
  removeItem: (k: string) => { delete _ls[k]; },
  clear: () => { for (const k in _ls) delete _ls[k]; },
  key: (i: number) => Object.keys(_ls)[i] || null,
  get length() { return Object.keys(_ls).length; }
};
Object.defineProperty(window, 'localStorage', { value: ls, writable: true });

// crypto.randomBytes polyfill is provided by jsdom
