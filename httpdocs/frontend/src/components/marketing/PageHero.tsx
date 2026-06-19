import { motion } from "framer-motion";
import type { ReactNode } from "react";

/* ================================================================
   QORDY — Marketing PageHero

   One hero header for EVERY menu-linked sub-page (About, Pricing,
   Features, Contact). Single eyebrow / title / lead rhythm so the
   sub-pages never drift from the landing type scale again. Optional
   action slot for CTA buttons.
   ================================================================ */

interface Props {
  eyebrow: ReactNode;
  title: ReactNode;
  lead?: ReactNode;
  actions?: ReactNode;
  /** extra content rendered under the actions (e.g. a billing toggle) */
  children?: ReactNode;
  className?: string;
}

export function PageHero({ eyebrow, title, lead, actions, children, className = "" }: Props) {
  return (
    <section className={`relative overflow-hidden ${className}`}>
      <div aria-hidden className="pointer-events-none absolute inset-0 bg-hero-mesh" />
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -top-24 right-[12%] h-72 w-72 rounded-full bg-brand-500/10 blur-[120px]" />
        <div className="absolute -bottom-28 left-[8%] h-72 w-72 rounded-full bg-gold-500/10 blur-[120px]" />
      </div>

      <div className="container-q relative">
        <motion.div
          initial={{ opacity: 0, y: 22 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
          className="mx-auto max-w-3xl pb-12 pt-6 text-center md:pb-16 md:pt-10"
        >
          <div className="eyebrow mb-6 justify-center">{eyebrow}</div>
          <h1 className="font-display text-display-xl font-bold leading-[1.05] tracking-tight text-ink-900">
            {title}
          </h1>
          {lead && <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-ink-600">{lead}</p>}
          {actions && <div className="mt-9 flex flex-wrap items-center justify-center gap-3">{actions}</div>}
          {children}
        </motion.div>
      </div>
    </section>
  );
}

export default PageHero;
