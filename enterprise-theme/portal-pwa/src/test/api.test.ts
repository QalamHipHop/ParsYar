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
      access_token: 'abc', access_exp: Math.floor(Date.now() / 1000) + 600,
      refresh_token: 'def', refresh_exp: Math.floor(Date.now() / 1000) + 6000,
      token_type: 'Bearer'
    };
    api.setSession(s);
    expect(api.getSession()).not.toBeNull();
    expect(api.getSession()!.access_token).toBe('abc');
  });

  it('clears session on null', () => {
    const s = { access_token: 'a', access_exp: 0, refresh_token: 'b', refresh_exp: 0, token_type: 'Bearer' };
    api.setSession(s);
    api.setSession(null);
    expect(api.getSession()).toBeNull();
  });

  it('expired session returns null', () => {
    api.setSession({ access_token: 'a', access_exp: 100, refresh_token: 'b', refresh_exp: 0, token_type: 'Bearer' });
    expect(api.getSession()).toBeNull();
  });
});

describe('api.fetch', () => {
  beforeEach(() => { localStorage.clear(); });
  afterEach(() => { vi.restoreAllMocks(); });

  it('attaches Authorization header when session present', async () => {
    api.setSession({ access_token: 'token123', access_exp: Math.floor(Date.now() / 1000) + 600, refresh_token: 'r', refresh_exp: 0, token_type: 'Bearer' });
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, data: [] }), { status: 200 }));
    global.fetch = fetchMock as any;
    await api.listInvoices();
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toContain('/portal/invoices');
    expect((init as RequestInit).headers).toMatchObject({ Authorization: 'Bearer token123' });
  });

  it('returns data on 200', async () => {
    global.fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, data: [{ id: 1 }] }), { status: 200 })) as any;
    const r = await api.listInvoices();
    expect(r).toEqual([{ id: 1 }]);
  });

  it('throws on non-2xx', async () => {
    global.fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'no' }), { status: 400 })) as any;
    await expect(api.listInvoices()).rejects.toThrow('no');
  });
});
