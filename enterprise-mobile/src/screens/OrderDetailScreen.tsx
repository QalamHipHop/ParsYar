import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { useRoute, RouteProp } from '@react-navigation/native';
import { api, Order, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge } from '../components/UI';
import { lightTheme as theme } from '../theme';
import type { RootStackParamList } from '../navigation/RootNavigator';

type R = RouteProp<RootStackParamList, 'OrderDetail'>;

export default function OrderDetailScreen() {
  const { params } = useRoute<R>();
  const [o, setO] = useState<Order | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    api.listOrders({}, 200, 0)
      .then((list) => setO(list.find((x) => x.id === params.id) ?? null))
      .catch((e) => setErr(String(e?.message ?? e)));
  }, [params.id]);

  if (err) return <Centered><Text style={{ color: 'crimson' }}>{err}</Text></Centered>;
  if (!o) return <Centered><ActivityIndicator color={theme.colors.brand500} /></Centered>;

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Row label="شماره سفارش" value={o.number} />
        <Row label="تاریخ" value={formatDateJalali(o.order_date)} />
        <Row label="وضعیت"><StatusBadge status={o.status} /></Row>
        <Row label="مبلغ" value={formatCurrency(o.total, o.currency)} />
      </Card>
    </ScrollView>
  );
}

function Row({ label, value, children }: { label: string; value?: string; children?: React.ReactNode }) {
  return (
    <View style={s.row}>
      <Text style={s.label}>{label}</Text>
      {children ?? <Text style={s.value}>{value}</Text>}
    </View>
  );
}
function Centered({ children }: { children: React.ReactNode }) {
  return <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.surface1 }}>{children}</View>;
}
const s = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 10, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.ink4 },
  label: { color: theme.colors.ink2, fontSize: 13 },
  value: { color: theme.colors.ink0, fontSize: 14, fontWeight: '600', textAlign: 'left', flexShrink: 1, maxWidth: '60%' },
});
