/**
 * Biometric gate.
 *
 *  When the user enables biometric in Settings, every cold start requires
 *  a successful prompt before the navigation mounts. We expose:
 *
 *    isBiometricEnabled()  — read user pref
 *    authenticate()        — prompt + return boolean
 *    setBiometricEnabled() — flip pref and persist
 */
import ReactNativeBiometrics, { BiometryTypes } from 'react-native-biometrics';
import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_BIOMETRIC = '@parsyar:biometricEnabled';
const STORAGE_BIOMETRIC_TYPE = '@parsyar:biometricType';

const rnBiometrics = new ReactNativeBiometrics({ allowDeviceCredentials: true });

export type BiometricKind = 'TouchID' | 'FaceID' | 'Fingerprint' | 'Face' | 'Iris' | 'Unknown';

export async function isBiometricEnabled(): Promise<boolean> {
  return (await AsyncStorage.getItem(STORAGE_BIOMETRIC)) === '1';
}

export async function setBiometricEnabled(enabled: boolean): Promise<void> {
  if (enabled) {
    const { available, biometryType } = await rnBiometrics.isSensorAvailable();
    if (!available) {
      throw new Error('Biometric sensor not available on this device');
    }
    const { success } = await rnBiometrics.simplePrompt({
      promptMessage: 'تأیید برای فعال‌سازی ورود با اثر انگشت / چهره',
      cancelButtonText: 'انصراف',
    });
    if (!success) throw new Error('Biometric prompt cancelled');
    await AsyncStorage.multiSet([
      [STORAGE_BIOMETRIC, '1'],
      [STORAGE_BIOMETRIC_TYPE, String(biometryType ?? 'Unknown')],
    ]);
  } else {
    await AsyncStorage.removeItem(STORAGE_BIOMETRIC);
    await AsyncStorage.removeItem(STORAGE_BIOMETRIC_TYPE);
  }
}

export async function getBiometricKind(): Promise<BiometricKind> {
  const t = await AsyncStorage.getItem(STORAGE_BIOMETRIC_TYPE);
  switch (t) {
    case BiometryTypes.TouchID: return 'TouchID';
    case BiometryTypes.FaceID: return 'FaceID';
    case BiometryTypes.Biometrics: return 'Fingerprint';
    default: return 'Unknown';
  }
}

/**
 * Authenticate the user. Returns true on success, false on cancel or error.
 * Callers should re-prompt at most twice before falling back to magic link.
 */
export async function authenticate(promptMessage = 'لطفاً تأیید کنید'): Promise<boolean> {
  try {
    const { available } = await rnBiometrics.isSensorAvailable();
    if (!available) return false;
    const { success } = await rnBiometrics.simplePrompt({
      promptMessage,
      cancelButtonText: 'انصراف',
    });
    return success;
  } catch {
    return false;
  }
}
