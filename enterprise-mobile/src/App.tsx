/**
 * App entry — wires up Redux, theme, navigation, network listener, push handler,
 * deep-link handler, and biometric lock screen.
 */
import React, { useEffect, useState, useRef, useCallback } from 'react';
import { StatusBar, useColorScheme, View, Text, Pressable, AppState, AppStateStatus } from 'react-native';
import { Provider as ReduxProvider, useDispatch } from 'react-redux';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { I18nextProvider } from 'react-i18next';
import i18n, { isRTL } from './lib/i18n';
import { store, bootstrap, setOnline } from './store';
import { lightTheme } from './theme';
import RootNavigator from './navigation/RootNavigator';
import { initPush, consumePendingDeepLink } from './lib/push';
import { startDeepLinking, onDeepLink, ParsYarDeepLink, buildVerifyLink } from './lib/deeplink';
import { isBiometricEnabled, authenticate } from './lib/biometric';
import { Card } from './components/UI';

function Bootstrapper({ children }: { children: React.ReactNode }) {
  const dispatch = useDispatch();
  useEffect(() => {
    dispatch(bootstrap());
  }, [dispatch]);
  return <>{children}</>;
}

function NetworkListener({ children }: { children: React.ReactNode }) {
  const dispatch = useDispatch();
  useEffect(() => {
    const sub = AppState.addEventListener('change', (s: AppStateStatus) => {
      dispatch(setOnline(s === 'active'));
    });
    return () => sub.remove();
  }, [dispatch]);
  return <>{children}</>;
}

function LockScreen({ onUnlock }: { onUnlock: () => void }) {
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const prompt = useCallback(async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const ok = await authenticate('قفل برنامه — لطفاً تأیید کنید');
    setBusy(false);
    if (ok) onUnlock();
    else setError('تأیید ناموفق بود. دوباره تلاش کنید.');
  }, [busy, onUnlock]);
  useEffect(() => { prompt(); }, []); // prompt on mount
  return (
    <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: lightTheme.colors.surface1, padding: 24 }}>
      <Card>
        <Text style={{ fontSize: 20, fontWeight: '700', textAlign: 'center', color: lightTheme.colors.ink0 }}>
          🔒 قفل برنامه
        </Text>
        <Text style={{ marginTop: 8, color: lightTheme.colors.ink2, textAlign: 'center' }}>
          برای ادامه، لطفاً با اثر انگشت یا چهره تأیید کنید.
        </Text>
        {error ? <Text style={{ marginTop: 8, color: 'crimson', textAlign: 'center' }}>{error}</Text> : null}
        <Pressable
          onPress={prompt}
          disabled={busy}
          style={{ marginTop: 16, paddingVertical: 12, borderRadius: 8, backgroundColor: lightTheme.colors.brand500, alignItems: 'center', opacity: busy ? 0.5 : 1 }}
        >
          <Text style={{ color: lightTheme.colors.surface0, fontWeight: '700' }}>
            {busy ? 'در حال بررسی…' : 'تلاش دوباره'}
          </Text>
        </Pressable>
      </Card>
    </View>
  );
}

function BiometricGate({ children }: { children: React.ReactNode }) {
  const [unlocked, setUnlocked] = useState(false);
  const [enabled, setEnabled] = useState<boolean | null>(null);
  const appState = useRef(AppState.currentState);

  useEffect(() => {
    isBiometricEnabled().then(setEnabled);
  }, []);

  useEffect(() => {
    if (enabled !== true) return;
    const sub = AppState.addEventListener('change', (s) => {
      if (appState.current.match(/active/) && s.match(/inactive|background/)) {
        // going to background → lock on next foreground
        setUnlocked(false);
      } else if (s === 'active' && !unlocked) {
        // returning to foreground → re-prompt
        setUnlocked(false);
      }
      appState.current = s;
    });
    return () => sub.remove();
  }, [enabled, unlocked]);

  if (enabled === null) return null;
  if (enabled && !unlocked) {
    return <LockScreen onUnlock={() => setUnlocked(true)} />;
  }
  return <>{children}</>;
}

function PushWiring() {
  useEffect(() => {
    initPush((n) => {
      // Lightweight: log only. Real apps would show a banner.
      // eslint-disable-next-line no-console
      console.log('[push:fg]', n.title, n.body);
    });
  }, []);
  return null;
}

function DeepLinkWiring() {
  // Mount once. Wires Linking listeners and dispatches a navigation effect
  // through a side-channel (window.__parsyarLink) that RootNavigator subscribes to.
  useEffect(() => {
    startDeepLinking();
    const off = onDeepLink((link: ParsYarDeepLink) => {
      // Stash on a side channel; the navigator polls on its mount/focus.
      (globalThis as any).__parsyarLink = link;
    });
    consumePendingDeepLink().then((u) => {
      if (u) {
        const link = (require('./lib/deeplink').parseLink(u));
        if (link) (globalThis as any).__parsyarLink = link;
      }
    });
    return off;
  }, []);
  return null;
}

export default function App() {
  const scheme = useColorScheme();
  const theme = lightTheme;
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <ReduxProvider store={store}>
        <SafeAreaProvider>
          <I18nextProvider i18n={i18n}>
            <Bootstrapper>
              <NetworkListener>
                <PushWiring />
                <DeepLinkWiring />
                <StatusBar
                  barStyle={scheme === 'dark' ? 'light-content' : 'dark-content'}
                  backgroundColor={theme.colors.surface0}
                />
                <BiometricGate>
                  <RootNavigator />
                </BiometricGate>
              </NetworkListener>
            </Bootstrapper>
          </I18nextProvider>
        </SafeAreaProvider>
      </ReduxProvider>
    </GestureHandlerRootView>
  );
}

// Re-export so screens can use it without circular imports
export { buildVerifyLink };
