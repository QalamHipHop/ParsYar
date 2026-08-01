/**
 * Root navigation — Auth stack vs Main tabs.
 *
 * Deep-link wiring:
 *   - We poll the global side-channel `__parsyarLink` (set by DeepLinkWiring
 *     in App.tsx) when the auth state changes. A verify-token link navigates
 *     to VerifyScreen; an invoice/order/payment/ticket id navigates to the
 *     detail screen of the right tab.
 *   - Polling is cheap and avoids the React Navigation 6 linking-config
 *     boilerplate; we keep the navigator tree explicit and audit-friendly.
 */
import React, { useEffect, useRef } from 'react';
import { NavigationContainer, useNavigation } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAppSelector } from '../store';
import { useTranslation } from 'react-i18next';
import { Text } from 'react-native';
import { lightTheme } from '../theme';
import type { ParsYarDeepLink } from '../lib/deeplink';

import LoginScreen from '../screens/LoginScreen';
import VerifyScreen from '../screens/VerifyScreen';
import DashboardScreen from '../screens/DashboardScreen';
import InvoicesScreen from '../screens/InvoicesScreen';
import OrdersScreen from '../screens/OrdersScreen';
import PaymentsScreen from '../screens/PaymentsScreen';
import TicketsScreen from '../screens/TicketsScreen';
import ProfileScreen from '../screens/ProfileScreen';
import NewTicketScreen from '../screens/NewTicketScreen';
import SettingsScreen from '../screens/SettingsScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

export type RootStackParamList = {
  Main: undefined;
  Login: undefined;
  Verify: { token: string };
  NewTicket: undefined;
  Profile: undefined;
  Settings: undefined;
  InvoiceDetail: { id: number };
  OrderDetail: { id: number };
  PaymentDetail: { id: number };
  TicketDetail: { id: number };
};

function TabIcon({ label, focused }: { label: string; focused: boolean }) {
  return <Text style={{ fontSize: 18, opacity: focused ? 1 : 0.5 }}>{label}</Text>;
}

function MainTabs() {
  const { t } = useTranslation();
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: lightTheme.colors.surface0 },
        headerTitleStyle: { fontWeight: '700' },
        tabBarActiveTintColor: lightTheme.colors.brand500,
        tabBarInactiveTintColor: lightTheme.colors.ink3,
        tabBarStyle: { borderTopColor: lightTheme.colors.ink4 },
      }}
    >
      <Tab.Screen name="Dashboard" component={DashboardScreen} options={{
        title: t('nav.dashboard'),
        tabBarIcon: ({ focused }) => <TabIcon label="داشبورد" focused={focused} />,
      }} />
      <Tab.Screen name="Invoices" component={InvoicesScreen} options={{
        title: t('nav.invoices'),
        tabBarIcon: ({ focused }) => <TabIcon label="🧾" focused={focused} />,
      }} />
      <Tab.Screen name="Orders" component={OrdersScreen} options={{
        title: t('nav.orders'),
        tabBarIcon: ({ focused }) => <TabIcon label="محصولات" focused={focused} />,
      }} />
      <Tab.Screen name="Payments" component={PaymentsScreen} options={{
        title: t('nav.payments'),
        tabBarIcon: ({ focused }) => <TabIcon label="پرداخت‌ها" focused={focused} />,
      }} />
      <Tab.Screen name="Tickets" component={TicketsScreen} options={{
        title: t('nav.tickets'),
        tabBarIcon: ({ focused }) => <TabIcon label="تیکت‌ها" focused={focused} />,
      }} />
    </Tab.Navigator>
  );
}

function DeepLinkBridge() {
  const navigation = useNavigation<any>();
  const isAuthed = useAppSelector((s) => s.auth.isAuthed);
  const lastRef = useRef<ParsYarDeepLink | null>(null);

  useEffect(() => {
    const tick = setInterval(() => {
      const link = (globalThis as any).__parsyarLink as ParsYarDeepLink | undefined;
      if (!link || link === lastRef.current) return;
      lastRef.current = link;
      (globalThis as any).__parsyarLink = null;
      handle(navigation, link, isAuthed);
    }, 400);
    return () => clearInterval(tick);
  }, [navigation, isAuthed]);

  return null;
}

function handle(nav: any, link: ParsYarDeepLink, isAuthed: boolean) {
  switch (link.type) {
    case 'verify':
      nav.navigate('Verify', { token: link.token });
      return;
    case 'invoice':
      if (!isAuthed) return;
      nav.navigate('Main');
      nav.navigate('InvoiceDetail', { id: link.id });
      return;
    case 'order':
      if (!isAuthed) return;
      nav.navigate('Main');
      nav.navigate('OrderDetail', { id: link.id });
      return;
    case 'payment':
      if (!isAuthed) return;
      nav.navigate('Main');
      nav.navigate('PaymentDetail', { id: link.id });
      return;
    case 'ticket':
      if (!isAuthed) return;
      nav.navigate('Main');
      nav.navigate('TicketDetail', { id: link.id });
      return;
    case 'unknown':
    default:
      // log only; navigation does not crash
      // eslint-disable-next-line no-console
      console.warn('[deeplink] unknown', link.raw);
      return;
  }
}

export default function RootNavigator() {
  const isAuthed = useAppSelector((s) => s.auth.isAuthed);
  return (
    <NavigationContainer>
      <DeepLinkBridge />
      <Stack.Navigator>
        {isAuthed ? (
          <>
            <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
            <Stack.Screen name="NewTicket" component={NewTicketScreen} options={{ title: 'تیکت جدید', presentation: 'modal' }} />
            <Stack.Screen name="Profile" component={ProfileScreen} />
            <Stack.Screen name="Settings" component={SettingsScreen} />
            <Stack.Screen name="InvoiceDetail" component={require('../screens/InvoiceDetailScreen').default} options={{ title: 'فاکتور' }} />
            <Stack.Screen name="OrderDetail" component={require('../screens/OrderDetailScreen').default} options={{ title: 'سفارش' }} />
            <Stack.Screen name="PaymentDetail" component={require('../screens/PaymentDetailScreen').default} options={{ title: 'پرداخت' }} />
            <Stack.Screen name="TicketDetail" component={require('../screens/TicketDetailScreen').default} options={{ title: 'تیکت' }} />
          </>
        ) : (
          <>
            <Stack.Screen name="Login" component={LoginScreen} options={{ headerShown: false }} />
            <Stack.Screen name="Verify" component={VerifyScreen} options={{ title: 'تأیید' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
