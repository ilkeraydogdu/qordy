# Qordy Design System — MASTER v4.0

*Marketing site tasarım sistemi — Qordy Akıllı Restoran Yönetim Sistemi*

## 🎯 OVERVIEW
- **Product Type**: Restaurant Management SaaS
- **Target Audience**: Restaurant managers & staff
- **Style**: Açık (light) mavi tema — profesyonel, sade, modern
- **Stack**: React + Vite + TypeScript + Tailwind CSS
- **Color Palette**: Brand Blue (#1b66c9) + Inter tipografi + Playfair Display italik vurgu
- **Version**: 4.0 (redesigned June 2026 — marketing statik tasarımdan React'e taşındı)

## 🎨 COLOR SYSTEM

### Brand (Mavi)
| Token | Hex | Kullanım |
|-------|-----|----------|
| brand-50 | #eff4ff | Çok açık mavi yüzeyler |
| brand-100 | #dbeafe | Açık hover state |
| brand-200 | #bfdbfe | Borders / soft tints |
| brand-300 | #93c5fd | Disabled icon |
| brand-400 | #60a5fa | Bright accents |
| brand-500 | #3b82f6 | Yardımcı vurgu |
| **brand-600** | **#1b66c9** | **PRIMARY — butonlar, linkler, başlık vurgu** |
| brand-700 | #15509e | PRIMARY DARK — hover state |
| brand-800 | #1e40af | Footer headings |
| brand-900 | #0b1c30 | TEXT DARK |
| brand-950 | #0a0f1f | — |

### Ink (Neutral / Slate)
| Token | Hex | Kullanım |
|-------|-----|----------|
| ink-50 | #f8f9ff | Body background |
| ink-100 | #f1f2f8 | Hover backgrounds |
| ink-200 | #e2e4ed | Borders (subtle) |
| ink-300 | #c6c8d3 | Scrollbar |
| ink-400 | #9a9caa | Placeholder text |
| ink-500 | #76777d | Muted text |
| ink-600 | #5a5b62 | Sub-text |
| ink-700 | #45464d | Body text |
| ink-800 | #2b3648 | Headlines |
| ink-900 | #1a2235 | Strong surface |
| ink-950 | #0b1c30 | Footer background |

### Accents
| Token | Hex | Kullanım |
|-------|-----|----------|
| success-50 | #ecfdf5 | Success bg |
| success-500 | #059669 | Check icon, "uygun" |
| success-600 | #047857 | — |
| accent-500 | #fd761a | Turuncu (kullanılmıyor; reserve) |
| accent-600 | #ea580c | — |
| star-amber | #f59e0b | Yıldızlar |
| fire | #ba1a1a | Error border |
| error-light | #fef2f2 | Error bg |

### Footer
| Token | Hex | Kullanım |
|-------|-----|----------|
| footer-900 | #131b2e | Footer background |
| footer-500 | #7c839b | Footer text |
| footer-heading | #1b66c9 | Footer column headings |

## 📐 TYPOGRAPHY

### Font Stacks
- **Sans (body & headings)**: `"Inter", system-ui, -apple-system, sans-serif`
- **Display (italik vurgu)**: `"Playfair Display", Georgia, serif` (`.font-display` veya `font-display` class)

### Display Sizes
| Class | Size | LH | Tracking |
|-------|------|----|----------|
| `text-display-2xl` | clamp(2.5rem, 5.5vw, 4.5rem) | 1.05 | -0.025em |
| `text-display-xl` | clamp(2.25rem, 4.5vw, 3.5rem) | 1.1 | -0.02em |
| `text-display-lg` | clamp(1.875rem, 3.5vw, 2.75rem) | 1.15 | -0.02em |
| `text-display-md` | clamp(1.5rem, 2.5vw, 2rem) | 1.2 | -0.015em |

### Italic Accent Pattern
- Başlıklarda Playfair italik vurgu için: `<span className="font-display italic text-brand-600">vurgu</span>`

## 🏗 LAYOUT

### Container
- `container-q` class — max-width 1440px, padding 16px (mobile) / 32px (md+)

### Section
- `section` class — padding 80px / 120px (md+)

### Breakpoints
- `sm`: 640px · `md`: 768px · `lg`: 1024px · `xl`: 1280px · `2xl`: 1536px

## ✨ COMPONENTS

### Button Tokens
- **Primary** (`.btn-primary`): `bg-brand-600` + `shadow-brand-sm` → hover: `bg-brand-700` + `translate-y-[-2px]`
- **Secondary** (`.btn-cream`): `bg-white` + `border` → hover: `bg-brand-50`
- **Ghost** (`.btn-ghost`): `bg-transparent` + `border` → hover: tint bg
- **Sizes**: `.btn-sm` (8/16), default (14/28), `.btn-lg` (16/32), `.btn-xl` (1.15rem/2.5rem)

### Card
- `card` class: bg-white, border-light, rounded-24px → hover: shadow-soft-lg + translateY(-4)
- `card-glass`: glassmorphism for navbar/drawer

### Inputs
- `q-input`: 12/16 padding, 12px radius, border 1.5px, focus: 4px brand glow
- `q-input-group`: flex column container
- `q-addon`: inline addon left/right of input
- `q-addon-button`: button addon

## 🎭 ANIMATIONS

| Animation | Class | Duration | Easing |
|-----------|-------|----------|--------|
| Float | `animate-float` | 6s | ease-in-out infinite |
| Blob | `animate-blob` | 7s | infinite (morf blob bg) |
| Marquee | `animate-marquee-slow` | 40s | linear infinite |
| Fade up | `animate-fade-up` | 0.8s | cubic-bezier(0.16,1,0.3,1) |
| Shimmer | `animate-shimmer` | 3s | linear infinite (skeleton) |

## 📱 RESPONSIVE BEHAVIORS

- **Navbar**: scroll-state ile backdrop-blur + shadow
- **Mobile drawer**: fixed inset-0 z-40 + pt-24 (header yüksekliği)
- **Hero**: 1-col mobil, sticky columns md+
- **Bento Grid**: 1-col (mobile) → 12-col (md+)
- **Pricing Grid**: 1-col → 3-col md+
- **Process Flow**: dikey (mobil) → yatay (md+)

## 🎯 ACCESSIBILITY

- `*:focus-visible` — 2px brand outline, 2px offset
- `prefers-reduced-motion` — tüm animasyonlar 0.01ms'ye indirgenir
- Form label/input ilişkilendirmesi (htmlFor / id)
- ARIA labels: `aria-label`, `aria-expanded`, `aria-hidden`
- Renk kontrastı: brand-600 on white = WCAG AA

## 🛠 DESIGN TOKENS — Tailwind reference

```js
colors: {
 brand: { 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950 },
 ink: { 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950 },
 success: { 50, 100, 500, 600 },
 accent: { 50, 500, 600 },
 footer: { 900, 800, 700, 500, 400 },
}
fontFamily: { sans, display }
fontSize: { display-2xl, display-xl, display-lg, display-md }
boxShadow: { brand-sm, brand, brand-lg, soft-sm, soft, soft-lg }
```

## 🚨 COMPONENT ORGANIZATION

```
src/
├── pages/ → LandingPage, LoginPage, RegisterPage
├── components/
│ ├── layout/ → Navbar, Footer, SmoothScroll
│ ├── landing/ → Hero, FeatureGrid, FeaturesShowcase, Pricing, Contact, FAQ, CTA, HowItWorks, MobileApp, StatsBar, Testimonials, TrustMarquee
│ ├── auth/ → AuthSidebar, LoginForm, RegisterForm
│ └── ui/ → Button, Icon, Logo, SectionTitle
├── lib/ → api.ts, bootstrap.ts, gsap.ts (KORUMA — mevcut sözleşme)
├── hooks/ → useReveal (no-op, framer-motion kullanılır)
├── design-system/ → MASTER.md
├── App.tsx → Router
├── main.tsx → BrowserRouter + mount
└── index.css → Design system tokens + utility classes
```
