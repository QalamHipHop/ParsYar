import React, { useState } from 'react';
import { View, Text, ScrollView, StyleSheet, Alert } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useAppDispatch } from '../store';
import { api, logEvent } from '../lib/api';
import { Button, Input, Card } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function NewTicketScreen({ navigation }: any) {
  const { t } = useTranslation();
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [category, setCategory] = useState('other');
  const [priority, setPriority] = useState('normal');
  const [loading, setLoading] = useState(false);

  const onSubmit = async () => {
    if (subject.length < 3 || body.length < 10) {
      Alert.alert('خطا', 'موضوع ≥ ۳ و شرح ≥ ۱۰ کاراکتر'); return;
    }
    setLoading(true);
    try {
      await api.createTicket({ subject, body, category, priority });
      api.logEvent('mobile.ticket.created', { category, priority });
      Alert.alert('موفقیت', 'تیکت ثبت شد', [{ text: 'باشه', onPress: () => navigation.goBack() }]);
    } catch (e: any) {
      Alert.alert('خطا', e?.response?.data?.error?.message ?? e?.message ?? 'Failed');
    } finally { setLoading(false); }
  };

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Input label={t('tickets.createSubject')} value={subject} onChangeText={setSubject} />
        <Input label={t('tickets.createBody')} value={body} onChangeText={setBody} multiline numberOfLines={6} style={{ minHeight: 120 }} />

        <Text style={s.lbl}>{t('tickets.category')}</Text>
        <View style={s.chipRow}>
          {['billing', 'technical', 'sales', 'shipping', 'other'].map((c) => (
            <Chip key={c} label={c} active={category === c} onPress={() => setCategory(c)} />
          ))}
        </View>

        <Text style={s.lbl}>{t('tickets.priority')}</Text>
        <View style={s.chipRow}>
          {['low', 'normal', 'high', 'urgent'].map((p) => (
            <Chip key={p} label={p} active={priority === p} onPress={() => setPriority(p)} />
          ))}
        </View>

        <Button title={t('common.submit')} onPress={onSubmit} loading={loading} />
      </Card>
    </ScrollView>
  );
}

function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Text onPress={onPress} style={{
      paddingHorizontal: 14, paddingVertical: 8, borderRadius: 999,
      backgroundColor: active ? theme.colors.brand500 : theme.colors.surface2,
      color: active ? theme.colors.surface0 : theme.colors.ink0,
      fontSize: 13, fontWeight: '600',
      marginEnd: 8, marginBottom: 8,
      overflow: 'hidden',
    }}>{label}</Text>
  );
}

const s = StyleSheet.create({
  lbl: { fontSize: 13, fontWeight: '600', color: theme.colors.ink2, marginTop: 8, marginBottom: 8 },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap' },
});
