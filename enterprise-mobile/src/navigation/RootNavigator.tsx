/**
 * Root navigation — Auth stack vs Main tabs.
 */
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAppSelector } from '../store';
import { useTranslation } from 'react-i18next';
import { Text } from 'react-native';
import { lightTheme } from '../theme';

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
        tabBarIcon: ({ focused }) => <TabIcon label="🏠" focused={focused} />,
      }} />
      <Tab.Screen name="Invoices" component={InvoicesScreen} options={{
        title: t('nav.invoices'),
        tabBarIcon: ({ focused }) => <TabIcon label="🧾" focused={focused} />,
      }} />
      <Tab.Screen name="Orders" component={OrdersScreen} options={{
        title: t('nav.orders'),
        tabBarIcon: ({ focused }) => <TabIcon label="📦" focused={focused} />,
      }} />
      <Tab.Screen name="Payments" component={PaymentsScreen} options={{
        title: t('nav.payments'),
        tabBarIcon: ({ focused }) => <TabIcon label="💳" focused={focused} />,
      }} />
      <Tab.Screen name="Tickets" component={TicketsScreen} options={{
        title: t('nav.tickets'),
        tabBarIcon: ({ focused }) => <TabIcon label="💬" focused={focused} />,
      }} />
    </Tab.Navigator>
  );
}

export default function RootNavigator() {
  const isAuthed = useAppSelector((s) => s.auth.isAuthed);
  return (
    <NavigationContainer>
      <Stack.Navigator>
        {isAuthed ? (
          <>
            <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
            <Stack.Screen name="NewTicket" component={NewTicketScreen} options={{ title: 'تیکت جدید', presentation: 'modal' }} />
            <Stack.Screen name="Profile" component={ProfileScreen} />
            <Stack.Screen name="Settings" component={SettingsScreen} />
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
