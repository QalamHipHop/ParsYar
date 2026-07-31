import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, RefreshControl, Pressable, StyleSheet, Modal, TextInput, Alert } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, Ticket, formatDateJalali } from '../lib/api';
import { Card, StatusBadge, Empty, Button, Input } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function TicketsScreen({ navigation }: any) {
  const { t } = useTranslation();
  const [items, setItems] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [replyTo, setReplyTo] = useState<Ticket | null>(null);
  const [reply, setReply] = useState('');

  const load = useCallback(async () => {
    try { setItems(await api.listTickets({}, 100, 0)); } catch { /* ignore */ } finally { setLoading(false); setRefreshing(false); }
  }, []);

  useEffect(() => { load(); }, [load]);
  useEffect(() => {
    const unsub = navigation.addListener('focus', load);
    return unsub;
  }, [navigation, load]);

  const sendReply = async () => {
    if (!replyTo || reply.length < 2) return;
    try {
      await api.replyTicket(replyTo.id, reply);
      setReply(''); setReplyTo(null);
      load();
    } catch (e: any) {
      Alert.alert('خطا', e?.response?.data?.error?.message ?? e?.message ?? 'Failed');
    }
  };

  return (
    <>
      <FlatList
        style={{ flex: 1, backgroundColor: theme.colors.surface1 }}
        contentContainerStyle={{ padding: 16 }}
        data={items}
        keyExtractor={(t) => String(t.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
        ListEmptyComponent={!loading ? <Empty title={t('dashboard.noData')} /> : null}
        ListHeaderComponent={
          <Button title={`+ ${t('tickets.new')}`} onPress={() => navigation.navigate('NewTicket')} style={{ marginBottom: 12 }} />
        }
        renderItem={({ item }) => (
          <Pressable onPress={() => setReplyTo(item)}>
            <Card>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text style={{ fontWeight: '700', fontSize: 15, flex: 1, marginEnd: 8 }} numberOfLines={1}>{item.subject}</Text>
                <StatusBadge status={item.status} />
              </View>
              <Text style={{ fontSize: 12, color: theme.colors.ink2 }}>{item.category} · {item.priority} · {formatDateJalali(item.created_at)}</Text>
            </Card>
          </Pressable>
        )}
      />
      <Modal visible={!!replyTo} animationType="slide" transparent onRequestClose={() => setReplyTo(null)}>
        <View style={s.modalBackdrop}>
          <View style={s.modalCard}>
            <Text style={{ fontWeight: '700', fontSize: 16, marginBottom: 4 }}>{replyTo?.subject}</Text>
            <Text style={{ fontSize: 12, color: theme.colors.ink2, marginBottom: 12 }}>{t('tickets.reply')}</Text>
            <Input multiline numberOfLines={4} value={reply} onChangeText={setReply} placeholder={t('tickets.createBody')} style={{ minHeight: 100 }} />
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <Button title={t('common.cancel')} variant="ghost" onPress={() => { setReply(''); setReplyTo(null); }} style={{ flex: 1 }} />
              <Button title={t('tickets.reply')} onPress={sendReply} style={{ flex: 1 }} />
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

const s = StyleSheet.create({
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  modalCard: { backgroundColor: theme.colors.surface0, padding: 20, borderTopLeftRadius: 20, borderTopRightRadius: 20 },
});
