import React from 'react';
import { View, Text, ScrollView, StyleSheet, Switch, Pressable, Alert } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useAppDispatch, useAppSelector, setBiometric, setPush, setLocale } from '../store';
import { Card } from '../components/UI';
import { lightTheme as theme } from '../theme';
import ReactNativeBiometrics from 'react-native-biometrics';

const rnBiometrics = new ReactNativeBiometrics();

export default function SettingsScreen() {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const ui = useAppSelector((s) => s.ui);

  const onToggleBiometric = async (v: boolean) => {
    if (!v) { dispatch(setBiometric(false)); return; }
    try {
      const { available } = await rnBiometrics.isSensorAvailable();
      if (!available) { Alert.alert('خطا', 'حسگر بیومتریک در دسترس نیست'); return; }
      const { success } = await rnBiometrics.simplePrompt({ promptMessage: 'تأیید برای فعال‌سازی ورود بیومتریک' });
      if (success) dispatch(setBiometric(true));
    } catch (e: any) { Alert.alert('خطا', e?.message ?? 'Failed'); }
  };

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Row label={t('profile.biometric')}>
          <Switch value={ui.biometricEnabled} onValueChange={onToggleBiometric} />
        </Row>
        <Row label={t('profile.notifications')}>
          <Switch value={ui.pushEnabled} onValueChange={(v: boolean) => { dispatch(setPush(v)); }} />
        </Row>
        <Row label={t('profile.language')}>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            {(['fa', 'en'] as const).map((lng) => (
              <Pressable key={lng} onPress={() => dispatch(setLocale(lng))}>
                <Text style={[s.langChip, ui.locale === lng && s.langChipActive]}>{lng === 'fa' ? 'فارسی' : 'English'}</Text>
              </Pressable>
            ))}
          </View>
        </Row>
      </Card>

      <Text style={s.foot}>{t('app.poweredBy')}</Text>
    </ScrollView>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View style={s.row}>
      <Text style={s.rowLabel}>{label}</Text>
      {children}
    </View>
  );
}

const s = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.ink4 },
  rowLabel: { fontSize: 15, color: theme.colors.ink0 },
  langChip: { paddingHorizontal: 14, paddingVertical: 6, borderRadius: 999, backgroundColor: theme.colors.surface2, color: theme.colors.ink0, fontWeight: '600', fontSize: 13 },
  langChipActive: { backgroundColor: theme.colors.brand500, color: theme.colors.surface0 },
  foot: { textAlign: 'center', color: theme.colors.ink3, fontSize: 11, marginTop: 12 },
});
