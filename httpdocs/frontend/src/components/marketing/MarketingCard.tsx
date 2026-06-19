import { motion } from "framer-motion";
import type { ReactNode } from "react";
import { Icon } from "@/components/ui/Icon";

/* ================================================================
   QORDY — Marketing feature card

   The white→hover-lift card used across sub-pages. One component so
   every grid item shares identical radius, shadow and icon chip.
   ================================================================ */

interface Props {
  icon?: React.ComponentProps<typeof Icon>["name"];
  title: ReactNode;
  children?: ReactNode;
  tag?: string;
  index?: number;
  className?: string;
}

export function MarketingCard({ icon, title, children, tag, index = 0, className = "" }: Props) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-50px" }}
      transition={{ duration: 0.5, delay: index * 0.04 }}
      className={`group flex h-full flex-col rounded-[22px] border border-ink-200 bg-white p-7 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-soft-lg dark:border-white/[0.08] dark:bg-white/[0.03] ${className}`}
    >
      {icon && (
        <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 transition-colors group-hover:bg-brand-600 group-hover:text-white dark:bg-brand-500/15 dark:ring-white/10 dark:text-brand-300">
          <Icon name={icon} size={22} />
        </div>
      )}
      <h3 className="flex items-center gap-2 font-display text-lg font-semibold text-ink-900 dark:text-ink-50">
        {title}
        {tag && (
          <span className="rounded-full bg-accent-500/12 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-accent-600">
            {tag}
          </span>
        )}
      </h3>
      {children && <p className="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{children}</p>}
    </motion.div>
  );
}

export default MarketingCard;
