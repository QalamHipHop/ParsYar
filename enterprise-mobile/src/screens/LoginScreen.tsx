import React, { useState } from 'react';
import { View, Text, ScrollView, KeyboardAvoidingView, Platform, StyleSheet, Linking } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useAppDispatch, useAppSelector, requestMagic, setBaseUrl } from '../store';
import { Button, Input, Card } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function LoginScreen() {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const { baseUrl, loading, error } = useAppSelector((s) => s.auth);
  const [email, setEmail] = useState('');
  const [device, setDevice] = useState('');
  const [url, setUrl] = useState(baseUrl || 'https://');
  const [sent, setSent] = useState(false);
  const [resendIn, setResendIn] = useState(0);

  React.useEffect(() => {
    if (resendIn <= 0) return;
    const id = setInterval(() => setResendIn((n) => (n > 0 ? n - 1 : 0)), 1000);
    return () => clearInterval(id);
  }, [resendIn]);

  const onSaveUrl = async () => {
    if (!url.startsWith('http')) return;
    await dispatch(setBaseUrl(url));
  };

  const onSend = async () => {
    if (!email) return;
    if (!baseUrl) { await onSaveUrl(); }
    const r = await dispatch(requestMagic({ email, device: device || undefined }));
    if (requestMagic.fulfilled.match(r)) {
      setSent(true);
      setResendIn(60);
    }
  };

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1, backgroundColor: theme.colors.surface1 }}>
      <ScrollView contentContainerStyle={{ padding: 20, paddingTop: 60 }} keyboardShouldPersistTaps="handled">
        <View style={{ alignItems: 'center', marginBottom: 28 }}>
          <View style={s.logo}><Text style={s.logoText}>پ</Text></View>
          <Text style={s.title}>{t('app.name')}</Text>
        </View>

        <Card>
          <Text style={s.h1}>{t('login.title')}</Text>
          <Text style={s.muted}>{t('login.subtitle')}</Text>

          <View style={{ marginTop: 18 }}>
            <Input label={t('login.email')} value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} />
            <Input label={t('login.device')} value={device} onChangeText={setDevice} placeholder="iPhone 15 Pro" />
            {error ? <Text style={{ color: theme.colors.danger, fontSize: 12, marginBottom: 8 }}>{error}</Text> : null}
            <Button title={sent ? `v ${t('login.sent')}` : t('login.send')} onPress={onSend} loading={loading} disabled={sent && resendIn > 0} />
            {sent && resendIn > 0 && (
              <Text style={{ color: theme.colors.ink2, fontSize: 12, textAlign: 'center', marginTop: 8 }}>
                {t('login.resendIn', { n: resendIn })}
              </Text>
            )}
            {sent && (
              <Button title={t('login.openEmail')} variant="ghost" onPress={() => Linking.openURL('mailto:')} style={{ marginTop: 6 }} />
            )}
          </View>
        </Card>

        <Card>
          <Text style={s.h2}>آدرس سایت</Text>
          <Input label="Site URL" value={url} onChangeText={setUrl} autoCapitalize="none" autoCorrect={false} placeholder="https://example.com" />
          <Button title="ذخیره" variant="secondary" onPress={onSaveUrl} />
        </Card>

        <Text style={{ color: theme.colors.ink3, fontSize: 11, textAlign: 'center', marginTop: 12 }}>{t('app.poweredBy')}</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  logo: { width: 64, height: 64, borderRadius: 16, backgroundColor: theme.colors.brand500, alignItems: 'center', justifyContent: 'center' },
  logoText: { color: theme.colors.surface0, fontSize: 32, fontWeight: '800' },
  title: { fontSize: 22, fontWeight: '800', marginTop: 12, color: theme.colors.ink0 },
  h1: { fontSize: 18, fontWeight: '700', color: theme.colors.ink0, marginBottom: 6 },
  h2: { fontSize: 15, fontWeight: '700', color: theme.colors.ink0, marginBottom: 10 },
  muted: { fontSize: 13, color: theme.colors.ink2, lineHeight: 20 },
});
