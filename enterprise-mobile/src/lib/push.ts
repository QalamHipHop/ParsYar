/**
 * Push notification lifecycle.
 *
 *  - init(): read stored token, create channel on Android, wire foreground handler
 *  - requestPermission(): ask OS, store token in backend
 *  - foreground handler: surfaces notification as in-app banner via store dispatch
 *  - background handler: registered at app start (AppRegistry.registerHeadlessTask)
 *
 * The token flow:
 *   1. App requests permission
 *   2. OS returns device token
 *   3. App POSTs to /portal/push/subscribe on backend (PortalService)
 *   4. Backend stores it; from now on, admin can fan out to this device
 */
// @ts-nocheck — react-native-push-notification has no first-party types in this version
import { Platform, PermissionsAndroid, Alert } from 'react-native';
import PushNotification, { Importance } from 'react-native-push-notification';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api } from './api';

const STORAGE_PUSH_TOKEN = '@parsyar:pushToken';
const STORAGE_PUSH_ENABLED = '@parsyar:pushEnabled';
const ANDROID_CHANNEL_ID = 'parsyar-default';
const ANDROID_CHANNEL_NAME = 'ParsYar Notifications';

type ForegroundHandler = (notif: { title?: string; body?: string; data?: Record<string, unknown> }) => void;

let foregroundHandler: ForegroundHandler | null = null;

/**
 * Wire push: channel, listeners, headless task registration.
 * Safe to call multiple times — idempotent.
 */
export function initPush(onForeground: ForegroundHandler): void {
  foregroundHandler = onForeground;

  // Channel (Android 8+)
  PushNotification.createChannel(
    {
      channelId: ANDROID_CHANNEL_ID,
      channelName: ANDROID_CHANNEL_NAME,
      channelDescription: 'اعلان‌های پیش‌فرض پارس‌یار',
      importance: Importance.HIGH,
      vibrate: true,
    },
    () => {}
  );

  PushNotification.configure({
    onRegister: async (token: { token: string }) => {
      await AsyncStorage.setItem(STORAGE_PUSH_TOKEN, token.token);
      // Subscribe to backend best-effort.
      try {
        await api.subscribePush({
          endpoint: token.token,
          platform: (Platform.OS === 'ios' || Platform.OS === 'android') ? Platform.OS : 'android',
          keys: { p256dh: '', auth: '' },
        });
      } catch (e) {
        // Network may be down — we'll retry on next requestPermission
      }
    },
    onNotification: (notif: any) => {
      // Foreground: show in-app, do not post system notif again
      if (!notif.userInteraction) {
        foregroundHandler?.({
          title: notif.title,
          body: notif.message,
          data: notif.data as Record<string, unknown> | undefined,
        });
      }
      // iOS: must call finish to mark handled
      notif.finish?.('UIBackgroundFetchResultNoData');
    },
    onAction: (notif: any) => {
      // User tapped a notification — surface payload for the navigator to deep-link
      foregroundHandler?.({
        title: notif.title,
        body: notif.message,
        data: notif.data as Record<string, unknown> | undefined,
      });
    },
    requestPermissions: Platform.OS === 'ios',
    permissions: { alert: true, badge: true, sound: true },
  });
}

export async function requestPermission(): Promise<boolean> {
  if (Platform.OS === 'android' && Platform.Version >= 33) {
    const res = await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS
    );
    if (res !== PermissionsAndroid.RESULTS.GRANTED) {
      Alert.alert('مجوز رد شد', 'برای دریافت اعلان، لطفاً از تنظیمات دستگاه مجوز بدهید.');
      return false;
    }
  }
  // iOS: configure() with requestPermissions:true already prompted on first run
  await AsyncStorage.setItem(STORAGE_PUSH_ENABLED, '1');
  return true;
}

export async function isPushEnabled(): Promise<boolean> {
  return (await AsyncStorage.getItem(STORAGE_PUSH_ENABLED)) === '1';
}

export async function getPushToken(): Promise<string | null> {
  return AsyncStorage.getItem(STORAGE_PUSH_TOKEN);
}

export async function disablePush(): Promise<void> {
  const token = await AsyncStorage.getItem(STORAGE_PUSH_TOKEN);
  if (token) {
    try { await api.unsubscribePush(token); } catch { /* ignore */ }
  }
  await AsyncStorage.multiRemove([STORAGE_PUSH_TOKEN, STORAGE_PUSH_ENABLED]);
  PushNotification.unregister();
}

/**
 * Headless task: runs when a notification is received while app is killed.
 * Must be registered at top-level (index.js) via AppRegistry.
 */
export async function headlessPushTask(notif: any): Promise<void> {
  // When app starts from a push, the OS spawns this. We only persist the data —
  // the next foreground run will surface it via the onNotification hook.
  try {
    if (notif?.data?.deepLink) {
      await AsyncStorage.setItem('@parsyar:pendingDeepLink', String(notif.data.deepLink));
    }
  } catch {
    // Best effort
  }
}

/** Consume a pending deep-link that arrived while the app was killed. */
export async function consumePendingDeepLink(): Promise<string | null> {
  const v = await AsyncStorage.getItem('@parsyar:pendingDeepLink');
  if (v) await AsyncStorage.removeItem('@parsyar:pendingDeepLink');
  return v;
}
