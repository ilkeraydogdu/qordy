import { motion } from "framer-motion";
import type { ReactNode } from "react";

/* ================================================================
   QORDY — Marketing SectionHeader

   Single source of truth for every public-page section heading
   (eyebrow + title + lead). Used by landing AND all menu-linked
   sub-pages so headings share one rhythm, type scale and motion.
   ================================================================ */

interface Props {
  eyebrow?: ReactNode;
  title: ReactNode;
  lead?: ReactNode;
  align?: "left" | "center";
  className?: string;
}

export function SectionHeader({ eyebrow, title, lead, align = "center", className = "" }: Props) {
  const center = align === "center";
  return (
    <motion.div
      initial={{ opacity: 0, y: 22 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-60px" }}
      transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
      className={`${center ? "mx-auto max-w-2xl text-center" : "max-w-2xl"} ${className}`}
    >
      {eyebrow && <div className={`eyebrow mb-5 ${center ? "justify-center" : ""}`}>{eyebrow}</div>}
      <h2 className="text-display-lg font-display font-bold tracking-tight text-ink-900 dark:text-ink-50">
        {title}
      </h2>
      {lead && (
        <p className="mt-4 text-base md:text-lg leading-relaxed text-ink-600 dark:text-ink-300">
          {lead}
        </p>
      )}
    </motion.div>
  );
}

export default SectionHeader;
