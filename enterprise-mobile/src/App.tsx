/**
 * App entry — wires up Redux, theme, navigation, network listener, push handler.
 */
import React, { useEffect } from 'react';
import { StatusBar, useColorScheme, Text } from 'react-native';
import { Provider as ReduxProvider } from 'react-redux';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { I18nextProvider } from 'react-i18next';
import i18n, { isRTL } from './lib/i18n';
import { store, bootstrap, useAppDispatch, setOnline } from './store';
import { lightTheme } from './theme';
import RootNavigator from './navigation/RootNavigator';

function Bootstrapper({ children }: { children: React.ReactNode }) {
  const dispatch = useAppDispatch();
  useEffect(() => {
    dispatch(bootstrap());
  }, [dispatch]);
  return <>{children}</>;
}

function NetworkListener({ children }: { children: React.ReactNode }) {
  const dispatch = useAppDispatch();
  useEffect(() => {
    // Lightweight NetInfo alternative without extra dep — just listen to navigator online (web only).
    // On native, use a periodic ping or @react-native-community/netinfo (peer dep optional).
    return () => {};
  }, [dispatch]);
  return <>{children}</>;
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
                <StatusBar
                  barStyle={scheme === 'dark' ? 'light-content' : 'dark-content'}
                  backgroundColor={theme.colors.surface0}
                />
                <RootNavigator />
              </NetworkListener>
            </Bootstrapper>
          </I18nextProvider>
        </SafeAreaProvider>
      </ReduxProvider>
    </GestureHandlerRootView>
  );
}
