/**
 * ParsYar design tokens — black/white/glassmorphism.
 * Mirrors the web theme's tokens.css but as a TS module.
 */
export const colors = {
  // Surfaces (white → black)
  surface0: '#ffffff',
  surface1: '#fafafa',
  surface2: '#f4f4f5',
  surface3: '#e4e4e7',
  surface4: '#18181b',

  // Ink (text)
  ink0: '#09090b',
  ink1: '#27272a',
  ink2: '#52525b',
  ink3: '#a1a1aa',
  ink4: '#d4d4d8',
  ink5: '#fafafa',

  // Brand
  brand50:  '#f4f4f5',
  brand100: '#e4e4e7',
  brand500: '#18181b', // primary CTA = near-black
  brand600: '#000000',
  brand900: '#000000',

  // Status
  success: '#10b981',
  warning: '#f59e0b',
  danger:  '#ef4444',
  info:    '#3b82f6',

  // Status backgrounds
  successBg: '#d1fae5',
  warningBg: '#fef3c7',
  dangerBg:  '#fee2e2',
  infoBg:    '#dbeafe',
} as const;

export const space = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  xxxl: 48,
} as const;

export const radius = {
  sm: 6,
  md: 10,
  lg: 14,
  xl: 20,
  full: 9999,
} as const;

export const fontSize = {
  xs: 11,
  sm: 13,
  md: 15,
  lg: 17,
  xl: 20,
  xxl: 24,
  xxxl: 32,
} as const;

export const fontWeight = {
  regular: '400' as const,
  medium: '500' as const,
  semibold: '600' as const,
  bold: '700' as const,
};

export const shadow = {
  sm: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  md: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 3,
  },
  lg: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.12,
    shadowRadius: 24,
    elevation: 8,
  },
} as const;

export type Theme = {
  colors: typeof colors;
  space: typeof space;
  radius: typeof radius;
  fontSize: typeof fontSize;
  fontWeight: typeof fontWeight;
  shadow: typeof shadow;
  isRTL: boolean;
};

export const lightTheme: Theme = {
  colors,
  space,
  radius,
  fontSize,
  fontWeight,
  shadow,
  isRTL: true,
};
