# QORDY Admin Redesign — Design System & Conventions (READ ME FIRST)

> Shared contract for the **business**, **personnel**, and **superadmin** redesign agents.
> Maintained by the Foundation/Business agent. Do not fork the design system — extend it here.

## 1. Direction: "Warm Ember Ops"
Confident, warm restaurant-operations aesthetic. Charcoal ink chrome, warm off-white canvas,
**amber is the ONLY primary action color**, **lime is reserved for live/positive signals**.
Generous whitespace, soft shadows, rounded cards. Restraint over decoration.

## 2. Where things live (single source of truth)
| Layer | File | Rule |
|------|------|------|
| Design tokens (variables) | `public/assets/css/tokens.css` | All colors/space/type/shadow/radius. **No hardcoded hex in views.** |
| Component classes | `public/assets/css/admin-components.css` | All `.q-*` UI classes. Add new shared patterns HERE, not in page `<style>`. |
| Legacy layout | `public/assets/css/admin-layout.css` | Sidebar/nav + legacy scale hacks. Do not extend; migrate off it. |
| PHP components | `app/views/layouts/components/` | Canonical component tree (StatCard, SectionCard, PageHeader, etc.). |
| Layout shell | `app/views/layouts/admin_layout.php` | Loads the 3 CSS layers in order: tokens → admin-layout → admin-components. **Foundation agent owns this file.** |

⚠️ **Tailwind is PRECOMPILED** (`tailwind.min.css`, no Node build on server). Arbitrary NEW Tailwind
utility classes may NOT exist in the compiled output. **Prefer the `.q-*` component classes** below for
any new visual treatment. Common layout utilities already used app-wide (flex, grid, gap-*, p-*, etc.) are safe.

## 3. Token cheat-sheet (use `var(--…)`, never raw hex)
- Brand: `--color-brand-accent` (#f59e0b amber, primary CTA), `--color-brand-accent-hover` (#d97706),
  `--color-brand-lime` (#84cc16 success), `--color-ink` (#1a1a2e chrome).
- Surfaces: `--color-canvas` (warm page bg), `--color-surface-1` (cards), `--color-surface-2` (alt).
- Text: `--color-text-primary` / `--color-text-secondary` / `--color-text-muted`.
- Status: `--color-status-{success,warning,danger,info,neutral}` (+ `-bg` soft variants).
- Type: `--font-display` (Plus Jakarta Sans — headings), `--font-body` (Inter — body).
- Scale: `--space-1..16`, `--radius-{sm,md,lg,xl,card}`, `--shadow-{sm,md,lg,xl,focus}`, `--transition-{fast,base,slow}`.

## 4. Component classes (in admin-components.css)
- **Shell:** `.q-page` (warm padded canvas), `.q-container`, `.q-stack[ --lg]`, `.q-grid--2/3/4` (responsive).
- **Header:** `.q-page-header` + `__eyebrow __title __subtitle __actions`.
- **Card:** `.q-card[ --pad --hover]`, `.q-card__header __title __body`.
- **Stat:** `.q-stat[ --compact]` + `__top __icon __label __value __delta(--up/--down/--flat)`; `.q-pulse-grid` for live pulse strip.
- **Button:** `.q-btn` + `--primary --secondary --soft --ghost --danger --ink --block` and sizes `--sm --lg`.
- **Badge:** `.q-badge--success/warning/danger/info/neutral/live` + `.q-badge__dot`.
- **Table:** `.q-table` (style `<table>` directly).
- **Layout grids:** `.q-grid--sidebar` (list + detail, finance/purchases), `.q-grid--3` (gateway cards).
- **Finance nav:** inline tab row linking overview/expenses/invoices/suppliers/waste (see `finance/*.php`).
- **Reports helpers:** `.reports-emp-bar` (amber progress), `.q-export-menu` (export dropdown), `.q-fin-row` / `.q-fin-row--margin` (financial summary rows).
- **Modal:** `.q-modal-backdrop`, `.q-modal`, `.q-modal__header`, `.q-modal__title`, `.q-modal__body`.
- **Tabs:** `.q-tab-row`, `.q-tab` (AI insights category tabs).
- **Empty:** `.q-empty` + `__icon __title`. **Section:** `.q-section-title`. **Hero:** `.q-hero` + `__glow __title`.
- **Toolbar/filters:** `.q-toolbar`, `.q-filter-bar`, `.q-filter-group__label`, `.q-filter-divider`, `.q-input-icon-wrap`, `.q-date-panel`.
- **Loading:** `.q-spinner`, `.q-loading-inline`, `.q-loading-toast`.
- **Charts:** `.q-chart-wrap[ --sm --lg]` + `[data-chart-placeholder]`.
- **Field:** `.q-field`, `.q-field__label`, `.q-input` (settings/finance forms).
- **Card title:** `.q-card__title` (subsection headings inside cards).
- **Settings toggles:** `.q-toggle-row`, `.q-settings-nav`, `.q-callout`, `.q-upload-zone`; `#admin-settings-root` scoped legacy color remap during migration; day rows use `.q-input` time fields.
- **Queue admin:** `.qd-*` inline styles tokenized to CSS vars; shell uses `.q-page-header`; status badges map to `.q-badge--*`; `#queue-admin-root` scoped legacy remap for remaining slate/amber utilities.
- **Personnel:** Superadmin business-picker blocks wrap in `.q-page`/`.q-container`; KDS (kitchen/prep) keeps dark `#0a0f1e` canvas — only align accents to amber, do not force light `.q-page` on live screens.
- **Stock filters:** Category chip rail inside muted `.q-card`; `.q-spinner` for all loading states (never slate spinners).
- **Receipts / datatable pages:** Map PHP `status_class` to `q-badge--*` tokens; table action buttons `q-btn--ghost` + `q-btn--primary`; detail modal `q-modal-backdrop` + `q-modal__header/body`.
- **Supplier analytics (detail + performance):** Client-rendered KPI tiles as `.q-card q-card--pad`; leaderboard/detail tables as `.q-table`; waste-ratio badges `q-badge--warning|danger|success`.
- **Order detail:** Single-column `.q-container` max ~56rem; item rows as muted `.q-card` toolbars; status actions preserve POST+CSRF forms.
- **a11y:** `.q-sr-only`; buttons have `:focus-visible` ring; `prefers-reduced-motion` respected.

## 4b. Canonical reference page
**`/business/dashboard`** → `app/views/admin/dashboard.php` (commit: canonical Warm Ember Ops reference).
All business admin pages should match its spacing, card surfaces, stat grid, table, empty/loading states.

## 5. Page pattern (copy this skeleton)
```php
<div class="q-page">
  <div class="q-container">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">İŞLETME</p>
        <h1 class="q-page-header__title">Sayfa Başlığı</h1>
        <p class="q-page-header__subtitle">Kısa açıklama</p>
      </div>
      <div class="q-page-header__actions">
        <a href="…" class="q-btn q-btn--primary">Birincil Eylem</a>
      </div>
    </header>
    <section class="q-grid q-grid--4">
      <!-- .q-stat cards -->
    </section>
    <section class="q-card q-card--pad q-stack">
      <!-- content / .q-table -->
    </section>
  </div>
</div>
```

## 6. Hard rules (all agents)
1. One primary action per view (amber `.q-btn--primary`). Lime only for live/success.
2. No new page-level `<style>` blocks — promote shared styles into `admin-components.css` (ping Foundation agent).
3. No raw hex in views — use tokens. No `?v=<?php echo time(); ?>`; use `filemtime` versioning if needed.
4. **Never** add `?v=` to `type="module"` scripts. Never touch `frontend/`, `public/app/`, `react_app.php`.
5. Preserve routes, CSRF (`Controller::view` auto-injects `$csrf_token`), and permission checks.
6. Lint every edited PHP: `/opt/plesk/php/8.3/bin/php -l <file>`. Reset opcache after PHP edits:
   `curl -s https://qordy.com/opcache-reset.php`. Render-check the route (expect 200 authed / 302 unauth, never 500).
7. WCAG AA: text contrast ≥ 4.5:1, keyboard-navigable, semantic HTML (`<header><nav><main><table>` etc.).

## 7. Ownership (no file collisions)
- **Foundation/Business:** this file, `admin_layout.php`, all shared CSS/components/partials, + business pages.
- **Personnel agent:** shifts*, leaves, my-schedule, guest-staff, staff_detail, users, edit_user, roles_permissions.
- **Superadmin agent:** superadmin/*, admin/superadmin/*, subscriptions*, packages*, trial_*, blog/legal/contact/error_logs/bridge_setup.

## 8. Business pages — Warm Ember status (2026-06-14)
**Done (2026-06-14 wave):** dashboard, reports, **settings.php**, **queue/index.php**, **stock.php** (history panels + #stock-admin-root remap), **shifts.php** (modals + superadmin picker), orders modals, preparation-screens, low-stock, purchases, payment-gateways, bank_accounts, bank_transfer_approvals, suppliers, waste, supplier-performance, stock-categories, receipts, order_detail, finance/index, finance/supplier-detail, superadmin/dashboard, waiter dashboard accent.

**Remaining business interiors:** `product_sales.php`, `order_approval_history.php`, `printers.php`, `business_admin/settings.php`, `business_admin/reservations.php`, `business_admin/analysis.php`, `admin/queue/settings.php`, stock.php JS table templates (~68 legacy strings), `zones.php`, `table_history.php`, `payment_links_*`, `menu.php`/`categories.php`, finance hub analytics JS tiles, prep-screens create/edit. Phase 2: `waiter/pos.php` accent pass.
