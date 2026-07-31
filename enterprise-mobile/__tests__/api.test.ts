/**
 * @jest-environment node
 */
import axios from 'axios';

// Storage stub typed as a generic Record for the test environment.
declare const global: typeof globalThis & { __storage?: Record<string, string> };

jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(async (k: string) => global.__storage?.[k] ?? null),
  setItem: jest.fn(async (k: string, v: string) => { global.__storage![k] = v; }),
  multiSet: jest.fn(async (pairs: Array<[string, string]>) => { pairs.forEach(([k, v]) => { global.__storage![k] = v; }); }),
  multiRemove: jest.fn(async (keys: string[]) => { keys.forEach((k) => delete global.__storage![k]); }),
  removeItem: jest.fn(async (k: string) => { delete global.__storage![k]; }),
}));

jest.mock('axios');

import { api, formatCurrency, formatDateJalali } from '../src/lib/api';
import { parseLink, buildVerifyLink } from '../src/lib/deeplink';

describe('api client', () => {
  beforeEach(async () => {
    global.__storage = {};
    (axios.create as jest.Mock).mockReturnValue(axios);
    (axios.post as jest.Mock).mockReset();
    (axios.get as jest.Mock).mockReset();
    // init() must run before any other call so this.instance is set
    await api.init();
  });

  test('formatCurrency returns a non-empty formatted string', () => {
    // ICU/Intl behaviour varies per Node version + platform. The contract is:
    //  - returns a non-empty string
    //  - zero produces a defined string
    //  - either a locale-aware number (e.g. "۱٬۲۳۴٬۵۶۷") or our fallback ("1,234,567 IRR")
    const out = formatCurrency(1234567, 'IRR');
    expect(typeof out).toBe('string');
    expect(out.length).toBeGreaterThan(0);
    expect(formatCurrency(0, 'IRR')).toBeTruthy();
    // Either has Persian/Arabic digits or Latin digits; both are valid
    expect(out).toMatch(/[0-9۰-۹]/);
  });

  test('formatDateJalali returns empty for empty input', () => {
    expect(formatDateJalali('')).toBe('');
  });

  test('setBaseUrl stores trimmed URL', async () => {
    await api.setBaseUrl('https://example.com/');
    expect(api.getBaseUrl()).toBe('https://example.com');
  });

  test('requestMagicLink posts to /auth/magic-link', async () => {
    (axios.post as jest.Mock).mockResolvedValueOnce({ data: { data: { sent: true, contact_id: 42 } } });
    await api.setBaseUrl('https://e.com');
    const r = await api.requestMagicLink('a@b.com', 'iPhone');
    expect(r.sent).toBe(true);
    expect(r.contact_id).toBe(42);
    // axios applies baseURL internally; we only check the path + params
    expect(axios.post).toHaveBeenCalledWith('/auth/magic-link', null, expect.objectContaining({ params: expect.objectContaining({ email: 'a@b.com', device_label: 'iPhone' }) }));
  });

  test('verifyMagicLink stores tokens', async () => {
    (axios.get as jest.Mock).mockResolvedValueOnce({ data: { data: { access_token: 'A', access_exp: 9999999999, refresh_token: 'R', refresh_exp: 9999999999, token_type: 'Bearer' } } });
    await api.setBaseUrl('https://e.com');
    const s = await api.verifyMagicLink('token123');
    expect(s.access_token).toBe('A');
    expect(await api.getSession()).not.toBeNull();
  });

  test('subscribePush posts to /push/subscribe', async () => {
    (axios.post as jest.Mock).mockResolvedValueOnce({ data: { data: { id: 7 } } });
    await api.setBaseUrl('https://e.com');
    const r = await api.subscribePush({ endpoint: 'TOKEN', platform: 'android', keys: { p256dh: '', auth: '' } });
    expect(r.id).toBe(7);
  });

  test('unsubscribePush swallows errors', async () => {
    (axios.post as jest.Mock).mockRejectedValueOnce(new Error('offline'));
    await api.setBaseUrl('https://e.com');
    await expect(api.unsubscribePush('T')).resolves.toBeUndefined();
  });
});

describe('deep link parser', () => {
  test('parses parsyar://verify', () => {
    expect(parseLink('parsyar://verify?token=abc')).toEqual({ type: 'verify', token: 'abc' });
  });

  test('parses https verify link', () => {
    expect(parseLink('https://example.com/portal/verify?token=xyz')).toEqual({ type: 'verify', token: 'xyz' });
  });

  test('parses invoice id', () => {
    expect(parseLink('parsyar://invoices/42')).toEqual({ type: 'invoice', id: 42 });
  });

  test('parses ticket id', () => {
    expect(parseLink('parsyar://tickets/9')).toEqual({ type: 'ticket', id: 9 });
  });

  test('returns unknown for garbage', () => {
    expect(parseLink('parsyar://foo?bar=baz')).toEqual({ type: 'unknown', raw: 'parsyar://foo?bar=baz' });
  });

  test('buildVerifyLink encodes token', () => {
    expect(buildVerifyLink('a b')).toContain('a%20b');
  });
});
