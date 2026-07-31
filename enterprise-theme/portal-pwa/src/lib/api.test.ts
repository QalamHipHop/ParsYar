import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { formatCurrency, formatDateJalali } from './api';

describe('format helpers', () => {
  it('formats currency with fa-IR locale', () => {
    const out = formatCurrency(1500000, 'IRR');
    expect(out).toContain('IRR');
    expect(out).toMatch(/[۰-۹0-9]/);
  });
  it('returns placeholder for null date', () => {
    expect(formatDateJalali(null)).toBe('—');
    expect(formatDateJalali(undefined)).toBe('—');
  });
  it('formats a valid ISO date', () => {
    const out = formatDateJalali('2025-05-01T10:00:00Z');
    expect(out).not.toBe('—');
    expect(out.length).toBeGreaterThan(3);
  });
});

describe('localStorage session helpers', () => {
  beforeEach(() => {
    localStorage.clear();
    (globalThis as any).PARSYAR_PORTAL_CONFIG = {
      apiBase: '/wp-json/enterprise/v1/portal',
      nonce: '',
      vapidPublicKey: '',
      company: { name: 'X', supportEmail: '', supportPhone: '' },
    };
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });
  it('roundtrips session in storage', async () => {
    const { api } = await import('./api');
    const s = { access_token: 'a', access_exp: 1, refresh_token: 'r', refresh_exp: 2, token_type: 'Bearer' as const };
    api.saveSession(s);
    expect(api.getSession()).toEqual(s);
    api.clearSession();
    expect(api.getSession()).toBeNull();
  });
});
