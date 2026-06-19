import { type ReactNode } from "react";
import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import { Icon } from "@/components/ui/Icon";
import { Logo } from "@/components/ui/Logo";
import { PageMeta } from "@/components/seo/PageMeta";
import { AuthCharacterScene } from "./AuthCharacterScene";

/* ================================================================
   QORDY — Shared corporate auth shell

   Two-column layout used by /login and /register so the jump from
   the marketing site into auth feels like one product.

   LEFT  → pure BRAND VISUAL: the Qordy logo + a large animated
           line-art character scene (waiter / chef). No marketing
           copy walls — the illustration carries the panel.
   RIGHT → light, corporate-white form area (heading + form slot).
   ================================================================ */

export type AuthVariant = "login" | "register";

interface AuthLayoutProps {
  variant: AuthVariant;
  path: string;
  /** Right panel header */
  formEyebrow: string;
  formTitle: string;
  formSubtitle: string;
  children: ReactNode;
}

export function AuthLayout({
  variant,
  path,
  formEyebrow,
  formTitle,
  formSubtitle,
  children,
}: AuthLayoutProps) {
  return (
    <div className="relative grid min-h-screen overflow-hidden bg-white text-ink-800 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
      <PageMeta path={path} />

      <AuthShowcase variant={variant} />

      {/* RIGHT — form */}
      <main className="relative flex items-start justify-center overflow-y-auto p-6 sm:p-10 lg:items-center lg:p-14">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_90%_0%,rgba(99,102,241,0.06),transparent_60%)]"
        />
        <div className="relative w-full max-w-md">
          {/* Mobile brand strip (left panel is hidden < lg) */}
          <div className="mb-8 flex items-center justify-between rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-4 text-white shadow-[0_20px_50px_-24px_rgba(99,102,241,0.7)] lg:hidden">
            <Logo variant="light" className="h-8 w-auto" />
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold">
              <span className="h-1.5 w-1.5 rounded-full bg-lime-400 animate-pulse-lime" />
              Canlı
            </span>
          </div>

          {/* Desktop back link */}
          <div className="mb-9 hidden lg:block">
            <Link
              to="/"
              className="inline-flex items-center gap-2 text-sm text-ink-500 transition-colors hover:text-brand-600"
            >
              <Icon name="arrow" size={16} className="rotate-180" />
              Ana sayfaya dön
            </Link>
          </div>

          <p className="eyebrow">{formEyebrow}</p>
          <h1 className="mt-3 font-display text-display-md font-semibold leading-[1.05] text-ink-900">
            {formTitle}
          </h1>
          <p className="mt-3 text-[1.05rem] leading-relaxed text-ink-500">{formSubtitle}</p>

          <div className="mt-8 pb-10">{children}</div>
        </div>
      </main>
    </div>
  );
}

/* ----------------------------------------------------------------
   LEFT PANEL — brand canvas, logo + animated character scene only
   ---------------------------------------------------------------- */

function AuthShowcase({ variant }: { variant: AuthVariant }) {
  return (
    <aside className="relative hidden flex-col overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 grain p-12 lg:flex xl:p-14">
      {/* ambient layers */}
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(55%_45%_at_18%_22%,rgba(255,255,255,0.18),transparent_60%)]" />
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(45%_40%_at_85%_85%,rgba(245,158,11,0.18),transparent_60%)]" />
      <motion.div
        aria-hidden
        className="absolute -top-24 -right-16 h-80 w-80 rounded-full bg-white/10 blur-3xl"
        animate={{ y: [0, 28, 0], x: [0, -18, 0] }}
        transition={{ duration: 16, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        aria-hidden
        className="absolute -bottom-28 -left-20 h-80 w-80 rounded-full bg-gold-500/10 blur-3xl"
        animate={{ y: [0, -24, 0], x: [0, 16, 0] }}
        transition={{ duration: 18, repeat: Infinity, ease: "easeInOut" }}
      />

      {/* top — logo only */}
      <motion.div
        initial={{ opacity: 0, y: -12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="relative z-10"
      >
        <Logo variant="light" className="h-10 w-auto" />
      </motion.div>

      {/* center — large brand visual, full focus */}
      <div className="relative z-10 flex flex-1 items-center justify-center">
        <AuthCharacterScene variant={variant} />
      </div>
    </aside>
  );
}

export default AuthLayout;
