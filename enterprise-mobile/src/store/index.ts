/**
 * Redux store — auth + ui state.
 */
import { configureStore, createSlice, PayloadAction, createAsyncThunk } from '@reduxjs/toolkit';
import { TypedUseSelectorHook, useDispatch, useSelector } from 'react-redux';
import { api, Profile } from '../lib/api';

interface AuthState {
  baseUrl: string;
  isAuthed: boolean;
  profile: Profile | null;
  loading: boolean;
  error: string | null;
}

const initialAuth: AuthState = {
  baseUrl: '',
  isAuthed: false,
  profile: null,
  loading: false,
  error: null,
};

export const bootstrap = createAsyncThunk('auth/bootstrap', async () => {
  await api.init();
  const s = await api.getSession();
  if (s && s.access_token) {
    try {
      const profile = await api.getProfile();
      return { isAuthed: true, profile, baseUrl: api.getBaseUrl() };
    } catch {
      await api.clearSession();
      return { isAuthed: false, profile: null, baseUrl: api.getBaseUrl() };
    }
  }
  return { isAuthed: false, profile: null, baseUrl: api.getBaseUrl() };
});

export const setBaseUrl = createAsyncThunk('auth/setBaseUrl', async (url: string) => {
  await api.setBaseUrl(url);
  return url;
});

export const requestMagic = createAsyncThunk(
  'auth/requestMagic',
  async (args: { email: string; device?: string }, { rejectWithValue }) => {
    try { return await api.requestMagicLink(args.email, args.device); }
    catch (e: any) { return rejectWithValue(e?.response?.data?.error?.message ?? e?.message ?? 'Failed'); }
  }
);

export const verifyMagic = createAsyncThunk(
  'auth/verify',
  async (token: string, { rejectWithValue }) => {
    try {
      await api.verifyMagicLink(token);
      const profile = await api.getProfile();
      return profile;
    } catch (e: any) { return rejectWithValue(e?.response?.data?.error?.message ?? e?.message ?? 'Failed'); }
  }
);

export const logout = createAsyncThunk('auth/logout', async () => {
  await api.logout();
});

export const refreshProfile = createAsyncThunk('auth/refreshProfile', async () => {
  return await api.getProfile();
});

const authSlice = createSlice({
  name: 'auth',
  initialState: initialAuth,
  reducers: {
    clearError(s) { s.error = null; },
  },
  extraReducers: (b) => {
    b.addCase(bootstrap.fulfilled, (s, a: PayloadAction<{ isAuthed: boolean; profile: Profile | null; baseUrl: string }>) => {
      s.isAuthed = a.payload.isAuthed; s.profile = a.payload.profile; s.baseUrl = a.payload.baseUrl;
    });
    b.addCase(setBaseUrl.fulfilled, (s, a) => { s.baseUrl = a.payload; });
    b.addCase(requestMagic.pending, (s) => { s.loading = true; s.error = null; });
    b.addCase(requestMagic.fulfilled, (s) => { s.loading = false; });
    b.addCase(requestMagic.rejected, (s, a) => { s.loading = false; s.error = String(a.payload ?? a.error.message ?? 'Failed'); });
    b.addCase(verifyMagic.pending, (s) => { s.loading = true; s.error = null; });
    b.addCase(verifyMagic.fulfilled, (s, a) => { s.loading = false; s.isAuthed = true; s.profile = a.payload; });
    b.addCase(verifyMagic.rejected, (s, a) => { s.loading = false; s.error = String(a.payload ?? a.error.message ?? 'Failed'); });
    b.addCase(logout.fulfilled, (s) => { s.isAuthed = false; s.profile = null; });
    b.addCase(refreshProfile.fulfilled, (s, a) => { s.profile = a.payload; });
  },
});

export const { clearError } = authSlice.actions;

interface UIState {
  online: boolean;
  pushEnabled: boolean;
  biometricEnabled: boolean;
  locale: 'fa' | 'en';
}
const initialUI: UIState = { online: true, pushEnabled: false, biometricEnabled: false, locale: 'fa' };

const uiSlice = createSlice({
  name: 'ui',
  initialState: initialUI,
  reducers: {
    setOnline(s, a: PayloadAction<boolean>) { s.online = a.payload; },
    setPush(s, a: PayloadAction<boolean>) { s.pushEnabled = a.payload; },
    setBiometric(s, a: PayloadAction<boolean>) { s.biometricEnabled = a.payload; },
    setLocale(s, a: PayloadAction<'fa' | 'en'>) { s.locale = a.payload; },
  },
});

export const { setOnline, setPush, setBiometric, setLocale } = uiSlice.actions;

export const store = configureStore({
  reducer: { auth: authSlice.reducer, ui: uiSlice.reducer },
  middleware: (gDM) => gDM({ serializableCheck: false }),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
