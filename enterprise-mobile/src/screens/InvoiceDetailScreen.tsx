import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { useRoute, RouteProp } from '@react-navigation/native';
import { api, Invoice, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge } from '../components/UI';
import { lightTheme as theme } from '../theme';
import type { RootStackParamList } from '../navigation/RootNavigator';

type R = RouteProp<RootStackParamList, 'InvoiceDetail'>;

export default function InvoiceDetailScreen() {
  const { params } = useRoute<R>();
  const [inv, setInv] = useState<Invoice | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    api.listInvoices({}, 200, 0)
      .then((list) => setInv(list.find((x) => x.id === params.id) ?? null))
      .catch((e) => setErr(String(e?.message ?? e)));
  }, [params.id]);

  if (err) return <Centered><Text style={{ color: 'crimson' }}>{err}</Text></Centered>;
  if (!inv) return <Centered><ActivityIndicator color={theme.colors.brand500} /></Centered>;

  return (
    <ScrollView style={{ flex: 1, backgroundColor: theme.colors.surface1 }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Row label="شماره" value={inv.number} />
        <Row label="تاریخ صدور" value={formatDateJalali(inv.issue_date)} />
        <Row label="سررسید" value={formatDateJalali(inv.due_date)} />
        <Row label="وضعیت"><StatusBadge status={inv.status} /></Row>
        <Row label="مبلغ کل" value={formatCurrency(inv.total, inv.currency)} />
        <Row label="پرداخت‌شده" value={formatCurrency(inv.paid, inv.currency)} />
        <Row label="شناسه مالیاتی" value={inv.tax_invoice_uid || '—'} />
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
