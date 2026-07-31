import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { useRoute, RouteProp } from '@react-navigation/native';
import { api, Payment, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge } from '../components/UI';
import { lightTheme as theme } from '../theme';
import type { RootStackParamList } from '../navigation/RootNavigator';

type R = RouteProp<RootStackParamList, 'PaymentDetail'>;

export default function PaymentDetailScreen() {
  const { params } = useRoute<R>();
  const [p, setP] = useState<Payment | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    api.listPayments(200, 0)
      .then((list) => setP(list.find((x) => x.id === params.id) ?? null))
      .catch((e) => setErr(String(e?.message ?? e)));
  }, [params.id]);

  if (err) return <Centered><Text style={{ color: 'crimson' }}>{err}</Text></Centered>;
  if (!p) return <Centered><ActivityIndicator color={theme.colors.brand500} /></Centered>;

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Row label="مبلغ" value={formatCurrency(p.amount, p.currency)} />
        <Row label="تاریخ پرداخت" value={formatDateJalali(p.paid_at)} />
        <Row label="روش" value={p.method} />
        <Row label="درگاه" value={p.gateway} />
        <Row label="شناسه مرجع" value={p.ref_id} />
        <Row label="وضعیت"><StatusBadge status={p.status} /></Row>
        <Row label="شناسه فاکتور" value={String(p.invoice_id)} />
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
