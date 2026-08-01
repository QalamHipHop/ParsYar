import React, { useEffect } from 'react';
import { View, Text, ActivityIndicator, StyleSheet } from 'react-native';
import { useAppDispatch, useAppSelector, verifyMagic, clearError } from '../store';
import { lightTheme as theme } from '../theme';

export default function VerifyScreen({ route, navigation }: any) {
  const dispatch = useAppDispatch();
  const { loading, error } = useAppSelector((s) => s.auth);
  const token: string = route?.params?.token ?? '';

  useEffect(() => {
    if (!token) return;
    dispatch(verifyMagic(token));
  }, [dispatch, token]);

  useEffect(() => {
    if (!loading && !error) {
      // success → RootNavigator will switch to Main automatically
    }
  }, [loading, error]);

  return (
    <View style={s.center}>
      {loading ? (
        <>
          <ActivityIndicator size="large" color={theme.colors.brand500} />
          <Text style={s.txt}>در حال تأیید لینک ورود…</Text>
        </>
      ) : error ? (
        <>
          <Text style={s.errIcon}>هشدار</Text>
          <Text style={[s.txt, { color: theme.colors.danger }]}>{error}</Text>
          <Text style={[s.txt, { color: theme.colors.ink2, fontSize: 13, marginTop: 8 }]} onPress={() => { dispatch(clearError()); navigation.goBack(); }}>
            بازگشت
          </Text>
        </>
      ) : (
        <Text style={s.txt}>ورود موفق — در حال انتقال…</Text>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: theme.colors.surface0 },
  txt: { marginTop: 16, fontSize: 15, color: theme.colors.ink0, textAlign: 'center' },
  errIcon: { fontSize: 48 },
});
