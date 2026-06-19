import { useRef } from "react";
import { useInView } from "framer-motion";

/* ================================================================
   Shared landing primitives — button/surface classes + motion helpers.
   Kept in one place so every extracted section shares the exact same
   visual language and the pixel-perfect mockup palette.
   ================================================================ */

export const btnPrimary =
  "inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold text-white bg-gradient-to-br from-brand-500 to-brand-600 shadow-[0_14px_32px_-14px_rgba(99,102,241,0.7)] hover:-translate-y-0.5 hover:shadow-[0_20px_44px_-14px_rgba(99,102,241,0.8)] transition-all duration-300";

export const btnOutline =
  "inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold border transition-colors duration-300 border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:text-brand-600 dark:bg-white/[0.05] dark:border-white/[0.12] dark:text-ink-100 dark:hover:border-white/25";

export const surface =
  "rounded-2xl border bg-white border-ink-200 shadow-[0_14px_44px_-28px_rgba(15,18,40,0.3)] dark:bg-ink-900/70 dark:border-white/[0.08] dark:shadow-none";

// Feature-card icon tints — mapped to the mockup palette:
// indigo #6366F1 · cyan #06B6D4 · orange #F97316 · pink #EC4899
// emerald #10B981 · gold #F59E0B
export const tintMap: Record<string, string> = {
  violet: "bg-brand-500/15 text-brand-600 dark:text-brand-300",
  blue: "bg-cyan-500/15 text-cyan-600 dark:text-cyan-300",
  orange: "bg-orange-500/15 text-orange-600 dark:text-orange-300",
  pink: "bg-pink-500/15 text-pink-600 dark:text-pink-300",
  green: "bg-success-500/15 text-success-600 dark:text-success-500",
  amber: "bg-gold-500/15 text-gold-600 dark:text-gold-400",
};

export function useInViewAnim(margin = "-80px") {
  const ref = useRef(null);
  const inView = useInView(ref, { once: true, margin: margin as never });
  return { ref, inView };
}

export const fade = (delay = 0) => ({
  initial: { opacity: 0, y: 24 },
  whileInView: { opacity: 1, y: 0 },
  viewport: { once: true, margin: "-60px" },
  transition: { delay, duration: 0.7, ease: [0.16, 1, 0.3, 1] as never },
});
