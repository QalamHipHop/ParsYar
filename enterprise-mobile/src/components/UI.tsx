/**
 * Reusable UI components — Card, Button, Input, Empty, StatusBadge.
 */
import React from 'react';
import { View, Text, TextInput, TextInputProps, Pressable, StyleSheet, ViewStyle, TextStyle, ActivityIndicator } from 'react-native';
import { lightTheme as theme } from '../theme';

export const Card: React.FC<{ children: React.ReactNode; style?: ViewStyle }> = ({ children, style }) => (
  <View style={[s.card, style]}>{children}</View>
);

interface BtnProps {
  title: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  style?: ViewStyle;
}
export const Button: React.FC<BtnProps> = ({ title, onPress, loading, disabled, variant = 'primary', style }) => {
  const isDisabled = disabled || loading;
  const v = stylesByVariant[variant];
  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      style={({ pressed }) => [
        { borderRadius: theme.radius.md, paddingVertical: 14, paddingHorizontal: 20, alignItems: 'center', justifyContent: 'center', flexDirection: 'row' },
        v.container,
        pressed && !isDisabled && { opacity: 0.85 },
        isDisabled && { opacity: 0.5 },
        style,
      ]}
    >
      {loading && <ActivityIndicator size="small" color={v.text.color as string} style={{ marginEnd: 8 }} />}
      <Text style={[{ fontSize: theme.fontSize.md, fontWeight: theme.fontWeight.semibold }, v.text]}>{title}</Text>
    </Pressable>
  );
};

export const Input: React.FC<TextInputProps & { label?: string; error?: string }> = ({ label, error, style, ...rest }) => (
  <View style={{ marginBottom: 14 }}>
    {label && <Text style={{ fontSize: theme.fontSize.sm, fontWeight: theme.fontWeight.medium, color: theme.colors.ink2, marginBottom: 6 }}>{label}</Text>}
    <TextInput
      placeholderTextColor={theme.colors.ink3}
      style={[
        {
          borderWidth: 1,
          borderColor: error ? theme.colors.danger : theme.colors.ink4,
          backgroundColor: theme.colors.surface0,
          color: theme.colors.ink0,
          borderRadius: theme.radius.md,
          paddingHorizontal: 14,
          paddingVertical: 12,
          fontSize: theme.fontSize.md,
          textAlign: theme.isRTL ? 'right' : 'left',
        },
        style as any,
      ]}
      {...rest}
    />
    {error && <Text style={{ color: theme.colors.danger, fontSize: theme.fontSize.xs, marginTop: 4 }}>{error}</Text>}
  </View>
);

export const Empty: React.FC<{ title: string; hint?: string }> = ({ title, hint }) => (
  <View style={{ alignItems: 'center', paddingVertical: 40 }}>
    <Text style={{ fontSize: 48, opacity: 0.3 }}>∅</Text>
    <Text style={{ color: theme.colors.ink2, fontSize: theme.fontSize.md, marginTop: 8 }}>{title}</Text>
    {hint && <Text style={{ color: theme.colors.ink3, fontSize: theme.fontSize.sm, marginTop: 4 }}>{hint}</Text>}
  </View>
);

export const StatusBadge: React.FC<{ status: string }> = ({ status }) => {
  const map: Record<string, { bg: string; fg: string; label?: string }> = {
    open:        { bg: theme.colors.infoBg,    fg: theme.colors.info },
    pending:     { bg: theme.colors.warningBg, fg: theme.colors.warning },
    paid:        { bg: theme.colors.successBg, fg: theme.colors.success },
    overdue:     { bg: theme.colors.dangerBg,  fg: theme.colors.danger },
    closed:      { bg: theme.colors.ink4,      fg: theme.colors.ink1 },
    resolved:    { bg: theme.colors.successBg, fg: theme.colors.success },
    issued:      { bg: theme.colors.infoBg,    fg: theme.colors.info },
    partial:     { bg: theme.colors.warningBg, fg: theme.colors.warning },
    cancelled:   { bg: theme.colors.dangerBg,  fg: theme.colors.danger },
    void:        { bg: theme.colors.ink4,      fg: theme.colors.ink1 },
  };
  const v = map[status] ?? { bg: theme.colors.ink4, fg: theme.colors.ink1 };
  return (
    <View style={{ backgroundColor: v.bg, paddingHorizontal: 10, paddingVertical: 4, borderRadius: theme.radius.full, alignSelf: 'flex-start' }}>
      <Text style={{ color: v.fg, fontSize: theme.fontSize.xs, fontWeight: theme.fontWeight.semibold }}>{status}</Text>
    </View>
  );
};

const stylesByVariant: Record<NonNullable<BtnProps['variant']>, { container: ViewStyle; text: TextStyle }> = {
  primary:   { container: { backgroundColor: theme.colors.brand500 }, text: { color: theme.colors.surface0 } },
  secondary: { container: { backgroundColor: theme.colors.surface2, borderWidth: 1, borderColor: theme.colors.ink4 }, text: { color: theme.colors.ink0 } },
  ghost:     { container: { backgroundColor: 'transparent' }, text: { color: theme.colors.ink0 } },
  danger:    { container: { backgroundColor: theme.colors.danger }, text: { color: theme.colors.surface0 } },
};

const s = StyleSheet.create({
  card: {
    backgroundColor: theme.colors.surface0,
    borderRadius: theme.radius.lg,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.ink4,
    ...theme.shadow.sm,
  },
});
