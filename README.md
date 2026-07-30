# ParsYar (پارس‌یار)

A native WordPress-based Enterprise CRM/ERP platform inspired by the best-in-class:
**Salesforce** (Custom Object Engine), **SAP** (Double-entry accounting core), and
**HubSpot** (Visual workflow & automation).

## Philosophy

- **Native first**: No WooCommerce, no Elementor, no third-party CRM plugins.
  Everything is built in-house for full control and a clean dependency surface.
- **Modular monolith**: One cohesive codebase that activates modules per business need.
- **Audit-grade**: Immutable audit trail suitable for Iranian tax authority (سامانه مؤدیان) and
  enterprise compliance audits.
- **Modern UX**: Headless React/Tailwind dashboard on top of WordPress for sub-500ms
  perceived loads on cached routes.

## Repository Layout

```
pars-yar/
├── enterprise-theme/         # WordPress theme + React SPA dashboard
├── enterprise-core-plugin/   # Backend engine (objects, accounting, workflows)
└── docs/                     # Architecture, ERD, deployment notes
```

## Phase 1 Scope (this iteration)

1. **Object Engine** — define custom entities with typed fields and relationships.
2. **CRM 360 (Lite)** — Contacts, Leads, Accounts.
3. **Audit Trail** — append-only change log with hash-chained entries.
4. **React SPA shell** — headless dashboard skeleton with Tailwind.
5. **Installer** — one-click provisioning wizard for tables, roles, demo data.

## Roadmap

- Phase 2: Visual Workflow Builder + Lead Scoring.
- Phase 3: ERP/Inventory (multi-warehouse, COGS).
- Phase 4: HRM & Payroll (Iranian labor law compliance).
- Phase 5: Full double-entry accounting + سامانه مؤدیان integration.

## Author

**Qalam** — see commit history.
