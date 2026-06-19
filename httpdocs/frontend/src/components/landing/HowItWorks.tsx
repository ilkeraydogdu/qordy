import { motion } from "framer-motion";
import { surface, fade, useInViewAnim } from "./primitives";

export function HowItWorks() {
  const { ref, inView } = useInViewAnim();
  const steps = [
    { n: "01", t: "QR oluştur", d: "QR kodunuz bir kez oluşturulur. Masalarınıza yerleştirin. Değişiklik anında her masada." },
    { n: "02", t: "Sipariş akar", d: "Müşteri QR'ı okutur. Sipariş mutfağa ve garson paneline düşer. Saniyeler içinde." },
    { n: "03", t: "Öde, yönet", d: "Hesap kapanır. Ciro, stok, rapor anında güncellenir. Siz odaklanacağınıza odaklanın." },
  ];
  return (
    <section ref={ref} className="section-pad bg-[#F4F5FA] dark:bg-ink-950">
      <div className="container-q">
        <motion.div {...fade(0)} className="mb-12">
          <div className="eyebrow mb-5">Nasıl Çalışır</div>
          <h2 className="text-display-lg font-bold tracking-tight text-ink-900 dark:text-ink-50">
            <span className="gradient-text">Üç adım.</span> Restoran akar.
          </h2>
        </motion.div>
        <div className="grid md:grid-cols-3 gap-5">
          {steps.map((s, i) => (
            <motion.div
              key={s.n}
              initial={{ opacity: 0, y: 24 }}
              animate={inView ? { opacity: 1, y: 0 } : {}}
              transition={{ delay: i * 0.1, duration: 0.6 }}
              className={`p-8 ${surface}`}
            >
              <span className="font-mono text-sm font-bold text-brand-500/70">{s.n}</span>
              <h3 className="text-xl font-bold text-ink-900 dark:text-ink-50 mt-4 mb-2">{s.t}</h3>
              <p className="text-sm text-ink-600 dark:text-ink-300 leading-relaxed">{s.d}</p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
