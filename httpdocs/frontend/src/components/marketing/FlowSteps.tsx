import { Fragment } from "react";
import { motion } from "framer-motion";
import { Icon } from "@/components/ui/Icon";

/* ================================================================
   QORDY — FlowSteps

   A clean, premium stepper used to explain a 3-step flow. Replaces
   the old full-height vertical-rule list. Each step is a self-
   contained card with a numbered icon chip; steps are joined by a
   small arrow connector (chevron) — never a full-height line.

   · `active` highlights the current step (parent can cycle it).
   ================================================================ */

export interface FlowStep {
  icon: React.ComponentProps<typeof Icon>["name"];
  title: string;
  desc: string;
}

interface Props {
  steps: FlowStep[];
  active?: number;
  inView?: boolean;
  className?: string;
}

export function FlowSteps({ steps, active = -1, inView = true, className = "" }: Props) {
  return (
    <ol className={`relative ${className}`}>
      {steps.map((s, i) => {
        const isActive = active === i;
        return (
          <Fragment key={s.title}>
            <motion.li
              initial={{ opacity: 0, y: 18 }}
              animate={inView ? { opacity: 1, y: 0 } : {}}
              transition={{ delay: i * 0.12, duration: 0.55, ease: [0.16, 1, 0.3, 1] }}
              className={`group relative flex items-start gap-4 rounded-2xl border p-4 transition-all duration-500 sm:p-5 ${
                isActive
                  ? "border-brand-300 bg-brand-50/70 shadow-soft dark:border-brand-400/40 dark:bg-white/[0.06]"
                  : "border-ink-200/80 bg-white hover:border-brand-200 dark:border-white/[0.07] dark:bg-white/[0.02]"
              }`}
            >
              <span
                className={`relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition-colors duration-500 ${
                  isActive
                    ? "bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-brand-sm"
                    : "bg-brand-500/10 text-brand-600 dark:text-brand-300"
                }`}
              >
                <Icon name={s.icon} size={22} />
                {isActive && (
                  <motion.span
                    aria-hidden
                    className="absolute inset-0 rounded-2xl ring-2 ring-brand-400"
                    initial={{ opacity: 0.7, scale: 1 }}
                    animate={{ opacity: 0, scale: 1.35 }}
                    transition={{ duration: 1.2, repeat: Infinity, ease: "easeOut" }}
                  />
                )}
              </span>

              <div className="min-w-0 pt-0.5">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-xs font-bold text-brand-500/80">0{i + 1}</span>
                  <h3 className="font-display text-base font-semibold text-ink-900 dark:text-ink-50 sm:text-lg">
                    {s.title}
                  </h3>
                </div>
                <p className="mt-1 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{s.desc}</p>
              </div>
            </motion.li>

            {/* arrow connector (not a full-height rule) */}
            {i < steps.length - 1 && (
              <li aria-hidden className="flex justify-center py-1.5">
                <motion.span
                  initial={{ opacity: 0, y: -4 }}
                  animate={inView ? { opacity: 1, y: 0 } : {}}
                  transition={{ delay: i * 0.12 + 0.25, duration: 0.4 }}
                  className={`grid h-7 w-7 place-items-center rounded-full border transition-colors duration-500 ${
                    active >= i + 1
                      ? "border-brand-300 bg-brand-500/10 text-brand-600 dark:text-brand-300"
                      : "border-ink-200 bg-white text-ink-400 dark:border-white/10 dark:bg-white/[0.03]"
                  }`}
                >
                  <Icon name="chevronDown" size={14} strokeWidth={2.5} />
                </motion.span>
              </li>
            )}
          </Fragment>
        );
      })}
    </ol>
  );
}

export default FlowSteps;
