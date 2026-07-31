import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, ScrollView, RefreshControl, StyleSheet, Pressable } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useAppSelector, useAppDispatch, refreshProfile } from '../store';
import { api, Invoice, formatCurrency, formatDateJalali } from '../lib/api';
import { Card, StatusBadge, Empty } from '../components/UI';
import { lightTheme as theme } from '../theme';

export default function DashboardScreen({ navigation }: any) {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const profile = useAppSelector((s) => s.auth.profile);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try { setInvoices(await api.listInvoices({}, 10, 0)); } catch { /* ignore */ } finally { setLoading(false); setRefreshing(false); }
  }, []);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { dispatch(refreshProfile()); }, [dispatch]);

  const onRefresh = useCallback(() => { setRefreshing(true); load(); }, [load]);

  const openInvoices = invoices.filter((i) => i.status !== 'paid' && i.status !== 'cancelled' && i.status !== 'void');
  const totalOpen = openInvoices.reduce((s, i) => s + (i.total - i.paid), 0);

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: theme.colors.surface1 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      <View style={{ padding: 16 }}>
        <Text style={s.hello}>{t('dashboard.welcome', { name: profile?.full_name ?? '...' })}</Text>

        <View style={s.kpiRow}>
          <Card style={s.kpi}>
            <Text style={s.kpiLabel}>{t('dashboard.balance')}</Text>
            <Text style={s.kpiVal}>{formatCurrency(totalOpen)}</Text>
          </Card>
          <Card style={s.kpi}>
            <Text style={s.kpiLabel}>{t('dashboard.openInvoices')}</Text>
            <Text style={s.kpiVal}>{openInvoices.length}</Text>
          </Card>
        </View>

        <Pressable onPress={() => navigation.navigate('Profile')} style={{ marginBottom: 16 }}>
          <Card>
            <Text style={{ fontSize: 13, color: theme.colors.ink2 }}>{t('profile.title')}</Text>
            <Text style={{ fontSize: 16, fontWeight: '700', marginTop: 4 }}>{profile?.full_name}</Text>
            <Text style={{ fontSize: 13, color: theme.colors.ink2, marginTop: 2 }}>{profile?.email}</Text>
            {profile?.company ? <Text style={{ fontSize: 13, color: theme.colors.ink2, marginTop: 2 }}>{profile.company}</Text> : null}
          </Card>
        </Pressable>

        <Text style={s.sectionTitle}>{t('dashboard.recentInvoices')}</Text>
        {loading ? <Text>{t('common.loading')}</Text> :
          invoices.length === 0 ? <Empty title={t('dashboard.noData')} /> :
          invoices.map((inv) => (
            <Card key={inv.id}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text style={{ fontWeight: '700' }}>{inv.number}</Text>
                <StatusBadge status={inv.status} />
              </View>
              <Text style={{ fontSize: 12, color: theme.colors.ink2 }}>{formatDateJalali(inv.issue_date)}</Text>
              <Text style={{ fontSize: 16, fontWeight: '700', marginTop: 6 }}>{formatCurrency(inv.total, inv.currency)}</Text>
            </Card>
          ))
        }
      </View>
    </ScrollView>
  );
}

const s = StyleSheet.create({
  hello: { fontSize: 22, fontWeight: '800', color: theme.colors.ink0, marginBottom: 14 },
  kpiRow: { flexDirection: 'row', gap: 12, marginBottom: 12 },
  kpi: { flex: 1, marginBottom: 0 },
  kpiLabel: { fontSize: 12, color: theme.colors.ink2 },
  kpiVal:   { fontSize: 22, fontWeight: '800', color: theme.colors.ink0, marginTop: 4 },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: theme.colors.ink0, marginTop: 8, marginBottom: 8 },
});
