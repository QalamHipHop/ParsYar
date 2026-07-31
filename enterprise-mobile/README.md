# ParsYar Enterprise Mobile

> **v1.8.0** — React Native app for iOS + Android. Persian-first, JWT-authenticated, push-ready, offline-tolerant.
> Pairs with the WordPress plugin over `/wp-json/enterprise/v1/portal/*`.

[![React Native](https://img.shields.io/badge/React%20Native-0.75-61dafb)](https://reactnative.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.5-3178c6)](https://www.typescriptlang.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)]()

---

## Features

- **Magic Link login** — no passwords, email only
- **JWT (HS256)** with auto-refresh and rotation on 401
- **Persian-first** — `fa-IR` default, Vazirmatn-friendly, RTL throughout
- **Push notifications** — register/unregister + foreground handler
- **Biometric auth** — TouchID / FaceID / Fingerprint (optional)
- **Offline-tolerant** — AsyncStorage caches profile + last data, sync on reconnect
- **8 screens** — Login, Verify, Dashboard, Invoices, Orders, Payments, Tickets (+New), Profile, Settings
- **iOS + Android** — single codebase, platform-tuned native config
- **Deep links** — `parsyar://verify?token=…` and universal links for magic-link landing

## Screens

| Route | Component | Purpose |
|------|-----------|---------|
| Login | `LoginScreen.tsx` | Email + device + site URL setup |
| Verify | `VerifyScreen.tsx` | Verifies the magic-link token, lands on Dashboard |
| Dashboard | `DashboardScreen.tsx` | KPIs (open balance, open invoices), recent invoices, profile card |
| Invoices | `InvoicesScreen.tsx` | Pull-to-refresh list with status badges |
| Orders | `OrdersScreen.tsx` | Pull-to-refresh list |
| Payments | `PaymentsScreen.tsx` | Pull-to-refresh list with gateway + ref |
| Tickets | `TicketsScreen.tsx` | List + inline reply modal |
| NewTicket | `NewTicketScreen.tsx` | Form with category/priority chips |
| Profile | `ProfileScreen.tsx` | Contact card + settings shortcut + logout |
| Settings | `SettingsScreen.tsx` | Biometric, push, language toggles |

## Architecture

```
src/
├── App.tsx                 # Providers (Redux, i18n, Gesture, SafeArea) + RootNavigator
├── components/UI.tsx       # Card, Button, Input, Empty, StatusBadge
├── lib/
│   ├── api.ts              # Axios client + JWT rotation + AsyncStorage persistence
│   └── i18n.ts             # i18next setup with fa-IR default
├── navigation/
│   └── RootNavigator.tsx   # Auth stack ↔ Main tabs (5 tabs)
├── screens/                # 9 screens (see above)
├── store/index.ts          # Redux Toolkit slices (auth + ui)
└── theme/index.ts          # Design tokens (colors, space, radius, shadow, fontSize)
```

## Quickstart

```bash
cd enterprise-mobile
npm install

# iOS
cd ios && pod install && cd ..
npm run ios

# Android
npm run android
```

## Build

```bash
# Android release APK
npm run build:android

# iOS release archive (Xcode)
npm run build:ios
```

## Connect to a WordPress site

1. Make sure the [ParsYar plugin](https://github.com/QalamHipHop/ParsYar) is installed and activated.
2. Open the app, on the **Login** screen enter your site URL (e.g. `https://acme.com`).
3. Enter your email and tap **Send sign-in link**.
4. Open the email on the device, tap the link — it will deep-link back to the app and verify.
5. You're in.

## Deep linking

The app registers two URL schemes:

- **Custom scheme** — `parsyar://verify?token=…` (always works)
- **Universal/App links** — `https://yourdomain.com/portal/verify?token=…` (requires `apple-app-site-association` / `assetlinks.json`)

Update the placeholder hosts in `ios/ParsYarEnterprise/Info.plist` and `android/app/src/main/AndroidManifest.xml` before shipping.

## Tests

```bash
npm test            # run once
npm run test:watch  # watch mode
npm run test:ci     # with coverage
npm run typecheck   # tsc --noEmit
```

## Security

- Tokens stored in encrypted-by-OS `AsyncStorage`
- HTTPS required by default (`usesCleartextTraffic=false`, ATS set)
- Refresh token rotation on every use
- Biometric-protected unlock (optional, opt-in per user)
- Magic-link rate limit enforced server-side (1 / 2 min / email)

## Roadmap

- [ ] Biometric unlock on cold start (when enabled)
- [ ] Offline write queue for ticket creation
- [ ] Camera capture for ticket attachments
- [ ] Apple Watch / Wear OS glance
- [ ] Widgets (home screen recent invoice + balance)

---

ساخته‌شده با دقت در تهران · Built with care in Tehran
