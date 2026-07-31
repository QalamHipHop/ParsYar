import React, { useEffect, useState, useCallback } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, Order, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge, Empty } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function OrdersScreen() {
  const { t } = useTranslation();
  const [items, setItems] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try { setItems(await api.listOrders({}, 100, 0)); } catch { /* ignore */ } finally { setLoading(false); setRefreshing(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: theme.colors.surface1 }}
      contentContainerStyle={{ padding: 16 }}
      data={items}
      keyExtractor={(o) => String(o.id)}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      ListEmptyComponent={!loading ? <Empty title={t('dashboard.noData')} /> : null}
      renderItem={({ item }) => (
        <Card>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
            <Text style={{ fontWeight: '700', fontSize: 15 }}>{item.number}</Text>
            <StatusBadge status={item.status} />
          </View>
          <Text style={{ fontSize: 12, color: theme.colors.ink2, marginBottom: 8 }}>{formatDateJalali(item.order_date)}</Text>
          <Text style={{ fontSize: 16, fontWeight: '700', textAlign: 'left' }}>{formatCurrency(item.total, item.currency)}</Text>
        </Card>
      )}
    />
  );
}
