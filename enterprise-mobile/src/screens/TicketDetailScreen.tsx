import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, ActivityIndicator, TextInput, Pressable, StyleSheet, KeyboardAvoidingView, Platform } from 'react-native';
import { useRoute, RouteProp } from '@react-navigation/native';
import { api, Ticket, formatDateJalali } from '../lib/api';
import { Card, StatusBadge } from '../components/UI';
import { lightTheme as theme } from '../theme';
import type { RootStackParamList } from '../navigation/RootNavigator';

type R = RouteProp<RootStackParamList, 'TicketDetail'>;

export default function TicketDetailScreen() {
  const { params } = useRoute<R>();
  const [t, setT] = useState<Ticket | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [reply, setReply] = useState('');
  const [sending, setSending] = useState(false);

  useEffect(() => {
    api.getTicket(params.id)
      .then(setT)
      .catch((e) => setErr(String(e?.message ?? e)));
  }, [params.id]);

  const send = async () => {
    if (!reply.trim() || !t) return;
    setSending(true);
    try {
      await api.replyTicket(t.id, reply.trim());
      setReply('');
    } catch (e: any) { setErr(String(e?.message ?? e)); }
    finally { setSending(false); }
  };

  if (err && !t) return <Centered><Text style={{ color: 'crimson' }}>{err}</Text></Centered>;
  if (!t) return <Centered><ActivityIndicator color={theme.colors.brand500} /></Centered>;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        <Card>
          <Text style={s.subject}>{t.subject}</Text>
          <View style={s.metaRow}>
            <StatusBadge status={t.status} />
            <Text style={s.metaText}>{t.category} · اولویت {t.priority}</Text>
          </View>
          <Text style={s.date}>ایجاد: {formatDateJalali(t.created_at)}</Text>
          {t.updated_at ? <Text style={s.date}>بروزرسانی: {formatDateJalali(t.updated_at)}</Text> : null}
        </Card>
        <Card>
          <Text style={s.label}>پاسخ</Text>
          <TextInput
            value={reply}
            onChangeText={setReply}
            multiline
            placeholder="پاسخ شما…"
            placeholderTextColor={theme.colors.ink3}
            style={s.input}
          />
          <Pressable
            onPress={send}
            disabled={sending || !reply.trim()}
            style={[s.btn, (!reply.trim() || sending) && { opacity: 0.5 }]}
          >
            <Text style={s.btnTxt}>{sending ? 'در حال ارسال…' : 'ارسال پاسخ'}</Text>
          </Pressable>
        </Card>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function Centered({ children }: { children: React.ReactNode }) {
  return <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.surface1 }}>{children}</View>;
}
const s = StyleSheet.create({
  subject: { fontSize: 18, fontWeight: '700', color: theme.colors.ink0, marginBottom: 8 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  metaText: { color: theme.colors.ink2, fontSize: 12 },
  date: { color: theme.colors.ink3, fontSize: 11, marginTop: 6 },
  label: { color: theme.colors.ink2, fontSize: 13, marginBottom: 6 },
  input: { minHeight: 100, textAlignVertical: 'top', color: theme.colors.ink0, borderWidth: 1, borderColor: theme.colors.ink4, borderRadius: 8, padding: 10, fontSize: 14 },
  btn: { marginTop: 10, backgroundColor: theme.colors.brand500, paddingVertical: 10, borderRadius: 8, alignItems: 'center' },
  btnTxt: { color: theme.colors.surface0, fontWeight: '700' },
});
