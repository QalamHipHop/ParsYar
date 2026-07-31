/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{js,jsx,ts,tsx}', './portal-pwa/src/**/*.{js,jsx,ts,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Brand — Ink Black & Pearl White core
        ink: {
          0:   '#ffffff',
          50:  '#fafafa',
          100: '#f4f4f5',
          200: '#e4e4e7',
          300: '#d4d4d8',
          400: '#a1a1aa',
          500: '#71717a',
          600: '#52525b',
          700: '#3f3f46',
          800: '#27272a',
          900: '#18181b',
          950: '#09090b',
        },
        brand: {
          50:  '#eef6ff',
          100: '#d9eaff',
          200: '#bcd9ff',
          300: '#8ec0ff',
          400: '#599cff',
          500: '#3478ff',
          600: '#1d4ed8',
          700: '#1e40af',
          800: '#1e3a8a',
          900: '#0b1d4a',
          950: '#050d24',
        },
        // Status
        success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669', 700: '#047857' },
        warning: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
        danger:  { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
        info:    { 50: '#eff6ff', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
        // Glass surfaces (light + dark)
        glass: {
          light: 'rgba(255, 255, 255, 0.6)',
          dark:  'rgba(15, 15, 20, 0.6)',
        },
      },
      fontFamily: {
        sans: ['Vazirmatn', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Menlo', 'Consolas', 'monospace'],
      },
      backdropBlur: {
        xs: '2px',
        '3xl': '64px',
      },
      boxShadow: {
        // Neo-brutalist hard shadows
        brutal:     '6px 6px 0 0 rgba(9, 9, 11, 1)',
        'brutal-sm':'3px 3px 0 0 rgba(9, 9, 11, 1)',
        'brutal-lg':'10px 10px 0 0 rgba(9, 9, 11, 1)',
        // Glass
        glass:      '0 8px 32px 0 rgba(9, 9, 11, 0.12), inset 0 1px 0 0 rgba(255, 255, 255, 0.4)',
        'glass-lg': '0 20px 60px 0 rgba(9, 9, 11, 0.18), inset 0 1px 0 0 rgba(255, 255, 255, 0.4)',
        // Glow
        glow:       '0 0 0 1px rgba(52, 120, 255, 0.3), 0 0 24px rgba(52, 120, 255, 0.4)',
      },
      animation: {
        'fade-in':    'fadeIn 240ms cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'slide-up':   'slideUp 320ms cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'slide-down': 'slideDown 320ms cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'scale-in':   'scaleIn 200ms cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'shimmer':    'shimmer 2s linear infinite',
        'pulse-slow': 'pulse 3s ease-in-out infinite',
        'spin-slow':  'spin 3s linear infinite',
        'float':      'float 6s ease-in-out infinite',
      },
      keyframes: {
        fadeIn:    { from: { opacity: 0 }, to: { opacity: 1 } },
        slideUp:   { from: { opacity: 0, transform: 'translateY(12px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
        slideDown: { from: { opacity: 0, transform: 'translateY(-12px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
        scaleIn:   { from: { opacity: 0, transform: 'scale(0.96)' }, to: { opacity: 1, transform: 'scale(1)' } },
        shimmer:   { '0%': { backgroundPosition: '-1000px 0' }, '100%': { backgroundPosition: '1000px 0' } },
        float:     { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-8px)' } },
      },
      backgroundImage: {
        'grid-pattern': "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'><path d='M0 0h40v40H0V0zm1 1h38v38H1V1z' fill='%23000' fill-opacity='0.04'/></svg>\")",
        'dot-pattern':  "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'><circle cx='1' cy='1' r='1' fill='%23000' fill-opacity='0.06'/></svg>\")",
        'gradient-radial': 'radial-gradient(circle at 50% 0%, rgba(52, 120, 255, 0.15), transparent 70%)',
      },
    },
  },
  plugins: [],
};
