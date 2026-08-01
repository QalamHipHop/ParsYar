import React from 'react';
import { Text, ScrollView, StyleSheet, Pressable } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useAppDispatch, useAppSelector, logout } from '../store';
import { Card, Button } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function ProfileScreen({ navigation }: any) {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const profile = useAppSelector((s) => s.auth.profile);

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Text style={s.name}>{profile?.full_name}</Text>
        <Text style={s.line}>{profile?.email}</Text>
        {profile?.phone ? <Text style={s.line}>{profile.phone}</Text> : null}
        {profile?.mobile ? <Text style={s.line}>{profile.mobile}</Text> : null}
        {profile?.company ? <Text style={s.line}>{profile.company}</Text> : null}
        {profile?.position ? <Text style={s.line}>{profile.position}</Text> : null}
      </Card>

      <Card>
        <Pressable onPress={() => navigation.navigate('Settings')} style={s.row}>
          <Text style={s.rowText}>تنظیمات {t('profile.settings')}</Text>
          <Text style={s.chev}>‹</Text>
        </Pressable>
      </Card>

      <Button title={`← ${t('common.logout')}`} variant="danger" onPress={() => dispatch(logout())} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  name: { fontSize: 20, fontWeight: '800', color: theme.colors.ink0 },
  line: { fontSize: 14, color: theme.colors.ink2, marginTop: 4 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 8 },
  rowText: { fontSize: 15, color: theme.colors.ink0 },
  chev: { fontSize: 20, color: theme.colors.ink3 },
});
