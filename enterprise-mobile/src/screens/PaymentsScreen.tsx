import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, RefreshControl, StyleSheet } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, Payment, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge, Empty } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function PaymentsScreen() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try { setItems(await api.listPayments(100, 0)); } catch { /* ignore */ } finally { setLoading(false); setRefreshing(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: theme.colors.surface1 }}
      contentContainerStyle={{ padding: 16 }}
      data={items}
      keyExtractor={(p) => String(p.id)}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      ListEmptyComponent={!loading ? <Empty title={t('dashboard.noData')} /> : null}
      renderItem={({ item }) => (
        <Card>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
            <Text style={{ fontWeight: '700', fontSize: 15 }}>{item.method || '—'}</Text>
            <StatusBadge status={item.status} />
          </View>
          <Text style={{ fontSize: 12, color: theme.colors.ink2, marginBottom: 4 }}>{formatDateJalali(item.paid_at)}</Text>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
            <Text style={{ fontSize: 11, color: theme.colors.ink3 }}>{item.gateway}{item.ref_id ? ` · ${item.ref_id}` : ''}</Text>
            <Text style={{ fontSize: 16, fontWeight: '700' }}>{formatCurrency(item.amount, item.currency)}</Text>
          </View>
        </Card>
      )}
    />
  );
}
