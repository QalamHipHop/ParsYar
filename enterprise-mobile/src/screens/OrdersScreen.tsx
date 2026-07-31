import React, { useEffect, useState, useCallback } from 'react';
import { FlatList, RefreshControl } from 'react-native';
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
          <Card.Row>
            <Card.Title>{item.number}</Card.Title>
            <StatusBadge status={item.status} />
          </Card.Row>
          <Card.Sub>{formatDateJalali(item.order_date)}</Card.Sub>
          <Card.Amount>{formatCurrency(item.total, item.currency)}</Card.Amount>
        </Card>
      )}
    />
  );
}
