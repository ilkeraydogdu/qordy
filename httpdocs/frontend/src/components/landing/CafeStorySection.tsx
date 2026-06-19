import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Icon } from "@/components/ui/Icon";
import { SectionHeader } from "@/components/marketing/SectionHeader";
import { FlowSteps, type FlowStep } from "@/components/marketing/FlowSteps";
import { QrOrderShowcase } from "@/components/marketing/QrOrderShowcase";
import { useInViewAnim } from "./primitives";

/* ================================================================
   QORDY — Cafe QR-order narrative ("kurgu")

   A guest at the table scans the QR → the order is sent → it lands
   in the kitchen. A premium customer-phone QR-menu mockup on the
   left (QrOrderShowcase), a clean 3-step stepper that lights up in
   sequence on the right (FlowSteps). No more hand-drawn line art.
   ================================================================ */

const STEPS: FlowStep[] = [
  { icon: "qr", title: "Masada QR okutulur", desc: "Misafir telefonuyla masadaki QR'ı okutur, menüyü görür, sipariş verir." },
  { icon: "pos", title: "Garson tabletine düşer", desc: "Sipariş anında garsonun el terminaline ulaşır — onay tek dokunuş." },
  { icon: "kitchen", title: "Mutfak ekranı hazırlar", desc: "Aynı saniye mutfak ekranında belirir, hazırlık başlar. Kağıt yok." },
];

const PROOF = ["Kağıt yok", "Hata yok", "Bekleme yok"];

export function CafeStorySection() {
  const reduce = useReducedMotion();
  const { ref, inView } = useInViewAnim();
  const [active, setActive] = useState(0);

  // Cycle the active step while the section is on screen (Scan → Send → Kitchen).
  useEffect(() => {
    if (!inView || reduce) {
      if (reduce) setActive(STEPS.length - 1);
      return;
    }
    const t = setInterval(() => setActive((i) => (i + 1) % STEPS.length), 2400);
    return () => clearInterval(t);
  }, [inView, reduce]);

  return (
    <section id="qr" ref={ref} className="scroll-mt-28 section-pad relative overflow-hidden bg-white dark:bg-ink-950">
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute right-[6%] top-[14%] h-72 w-72 rounded-full bg-brand-500/10 blur-[120px]" />
        <div className="absolute bottom-[10%] left-[6%] h-72 w-72 rounded-full bg-gold-500/10 blur-[120px]" />
      </div>

      <div className="container-q relative">
        <SectionHeader
          eyebrow="Sipariş Hikayesi"
          title={<>Masadan mutfağa, <span className="gradient-text">tek akış</span>.</>}
          lead="QR'dan başlayan sipariş, garson tabletinden mutfak ekranına saniyeler içinde akar."
          className="mb-14"
        />

        <div className="grid items-center gap-12 lg:grid-cols-[1.05fr_1fr] lg:gap-16">
          <QrOrderShowcase activeStep={active} />

          <div>
            <FlowSteps steps={STEPS} active={active} inView={inView} className="space-y-0" />

            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={inView ? { opacity: 1, y: 0 } : {}}
              transition={{ delay: 0.45, duration: 0.55 }}
              className="mt-5 flex flex-wrap items-center gap-2"
            >
              {PROOF.map((t) => (
                <span
                  key={t}
                  className="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs font-semibold text-ink-700 dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-ink-200"
                >
                  <span className="grid h-4 w-4 place-items-center rounded-full bg-success-500/12 text-success-600 dark:text-success-500">
                    <Icon name="check" size={10} strokeWidth={3} />
                  </span>
                  {t}
                </span>
              ))}
            </motion.div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default CafeStorySection;
