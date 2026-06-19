import { motion } from "framer-motion";
import { Icon } from "@/components/ui/Icon";
import { surface, tintMap, useInViewAnim } from "./primitives";
import { MODULES } from "./data";

export function ModuleChips() {
  const { ref, inView } = useInViewAnim();
  return (
    <section id="moduller" ref={ref} className="scroll-mt-28 py-14 md:py-20 bg-[#F4F5FA] dark:bg-ink-950">
      <div className="container-q">
        <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
          {MODULES.map((m, i) => (
            <motion.div
              key={m.title}
              initial={{ opacity: 0, y: 24 }}
              animate={inView ? { opacity: 1, y: 0 } : {}}
              transition={{ delay: i * 0.06, duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
              className={`group flex items-start gap-3 p-4 ${surface} hover:-translate-y-1 transition-transform duration-300`}
            >
              <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${tintMap[m.tint]}`}>
                <Icon name={m.icon as never} size={19} />
              </span>
              <span className="min-w-0">
                <span className="block text-[14px] font-bold text-ink-900 dark:text-ink-50">{m.title}</span>
                <span className="block text-[11px] leading-snug text-ink-500 dark:text-ink-400 mt-0.5">{m.desc}</span>
              </span>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
