import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { api } from '../lib/api';

describe('api.session', () => {
  beforeEach(() => { localStorage.clear(); });
  afterEach(() => { vi.restoreAllMocks(); });

  it('returns null when no session', () => {
    expect(api.getSession()).toBeNull();
  });

  it('stores and retrieves session', () => {
    const s = {
      access_token: 'abc',
      access_exp: Math.floor(Date.now() / 1000) + 600,
      refresh_token: 'def',
      refresh_exp: Math.floor(Date.now() / 1000) + 6000,
      token_type: 'Bearer' as const,
    };
    api.saveSession(s);
    expect(api.getSession()).not.toBeNull();
    expect(api.getSession()!.access_token).toBe('abc');
  });

  it('clears session on clearSession()', () => {
    const s = {
      access_token: 'a',
      access_exp: 0,
      refresh_token: 'b',
      refresh_exp: 0,
      token_type: 'Bearer' as const,
    };
    api.saveSession(s);
    api.clearSession();
    expect(api.getSession()).toBeNull();
  });
});

describe('api.fetch', () => {
  beforeEach(() => { localStorage.clear(); });
  afterEach(() => { vi.restoreAllMocks(); });

  it('attaches Authorization header when session present', async () => {
    api.saveSession({
      access_token: 'token123',
      access_exp: Math.floor(Date.now() / 1000) + 600,
      refresh_token: 'r',
      refresh_exp: 0,
      token_type: 'Bearer',
    });
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify([{ id: 1 }]), { status: 200 }));
    globalThis.fetch = fetchMock as unknown as typeof fetch;
    await api.invoices();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain('/invoices');
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer token123');
  });

  it('returns data on 200', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify([{ id: 1 }]), { status: 200 })) as unknown as typeof fetch;
    const r = await api.invoices();
    expect(r).toEqual([{ id: 1 }]);
  });

  it('throws on non-2xx', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'no' }), { status: 400 })) as unknown as typeof fetch;
    await expect(api.invoices()).rejects.toThrow('no');
  });
});
