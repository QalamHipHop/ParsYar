/**
 * @jest-environment node
 */
import axios from 'axios';

jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(async (k) => global.__storage?.[k] ?? null),
  setItem: jest.fn(async (k, v) => { global.__storage[k] = v; }),
  multiSet: jest.fn(async (pairs) => { pairs.forEach(([k, v]: [string, string]) => { global.__storage[k] = v; }); }),
  multiRemove: jest.fn(async (keys: string[]) => { keys.forEach((k) => delete global.__storage[k]); }),
  removeItem: jest.fn(async (k) => { delete global.__storage[k]; }),
}));

jest.mock('axios');

import { api, formatCurrency, formatDateJalali } from '../src/lib/api';

describe('api client', () => {
  beforeEach(() => { global.__storage = {}; (axios.create as jest.Mock).mockReturnValue(axios); (axios.post as jest.Mock).mockReset(); (axios.get as jest.Mock).mockReset(); });

  test('formatCurrency returns Persian formatted string', () => {
    expect(formatCurrency(1234567, 'IRR')).toContain('IRR');
    expect(formatCurrency(0, 'IRR')).toContain('IRR');
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
    expect(axios.post).toHaveBeenCalledWith('https://e.com/auth/magic-link', null, expect.objectContaining({ params: { email: 'a@b.com', device_label: 'iPhone' } }));
  });

  test('verifyMagicLink stores tokens', async () => {
    (axios.get as jest.Mock).mockResolvedValueOnce({ data: { data: { access_token: 'A', access_exp: 9999999999, refresh_token: 'R', refresh_exp: 9999999999, token_type: 'Bearer' } } });
    await api.setBaseUrl('https://e.com');
    const s = await api.verifyMagicLink('token123');
    expect(s.access_token).toBe('A');
    expect(await api.getSession()).not.toBeNull();
  });
});
