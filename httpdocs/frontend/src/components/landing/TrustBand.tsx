import { motion } from "framer-motion";
import { Icon } from "@/components/ui/Icon";
import { tintMap, useInViewAnim } from "./primitives";
import { TRUST } from "./data";

export function TrustBand() {
  const { ref, inView } = useInViewAnim();
  return (
    <section ref={ref} className="scroll-mt-28 py-12 bg-white dark:bg-ink-950 border-t border-ink-200/70 dark:border-white/[0.05]" id="hakkimizda">
      <div className="container-q grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {TRUST.map((t, i) => (
          <motion.div
            key={t.title}
            initial={{ opacity: 0, y: 16 }}
            animate={inView ? { opacity: 1, y: 0 } : {}}
            transition={{ delay: i * 0.08, duration: 0.5 }}
            className="flex items-center gap-3"
          >
            <span className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${tintMap[t.tint]}`}>
              <Icon name={t.icon as never} size={20} />
            </span>
            <span>
              <span className="block text-sm font-bold text-ink-900 dark:text-ink-50">{t.title}</span>
              <span className="block text-xs text-ink-500 dark:text-ink-400">{t.desc}</span>
            </span>
          </motion.div>
        ))}
      </div>
    </section>
  );
}
