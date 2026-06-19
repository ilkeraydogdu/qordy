import { motion } from "framer-motion";
import { useState } from "react";
import { Link } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";
import { surface, btnPrimary, fade, useInViewAnim } from "./primitives";
import { TRIAL_DAYS } from "./data";

export function Pricing() {
  const { ref, inView } = useInViewAnim();
  const [yearly, setYearly] = useState(false);

  const plans = [
    { name: "Başlangıç", price: yearly ? "749" : "899", desc: "Tek şube, temel modüller.", features: ["QR Menü · POS · KDS", "Temel Raporlar", "5 Kullanıcı", "7/24 Destek"], cta: "Ücretsiz Dene", featured: false },
    { name: "Profesyonel", price: yearly ? "1.666" : "1.999", desc: "Çoklu şube, tüm özellikler.", features: ["Tüm Modüller", "Sınırsız Kullanıcı", "Çoklu Şube", "AI Önerileri", "Öncelikli Destek"], cta: "Ücretsiz Dene", featured: true },
    { name: "Kurumsal", price: null, desc: "Zincirler için özel çözüm.", features: ["Sınırsız Her Şey", "Özel Entegrasyon", "Dedike Temsilci", "SLA 4 Saat", "Yerinde Eğitim"], cta: "İletişime Geç", featured: false },
  ];

  return (
    <section id="fiyat" ref={ref} className="scroll-mt-28 section-pad bg-white dark:bg-ink-950 border-y border-ink-200/70 dark:border-white/[0.05]">
      <div className="container-q">
        <motion.div {...fade(0)} className="text-center max-w-2xl mx-auto mb-12">
          <div className="eyebrow justify-center mb-5">Fiyatlandırma</div>
          <h2 className="text-display-lg font-bold tracking-tight text-ink-900 dark:text-ink-50">
            Şeffaf. <span className="gradient-text">Taahhütsüz.</span>
          </h2>
          <div className="mt-7 inline-flex items-center gap-1 p-1 rounded-full border border-ink-200 dark:border-white/[0.1] bg-ink-50 dark:bg-white/[0.03]">
            {[false, true].map((y) => (
              <button
                key={String(y)}
                onClick={() => setYearly(y)}
                className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                  yearly === y ? "bg-brand-600 text-white shadow-sm" : "text-ink-500 dark:text-ink-300"
                }`}
              >
                {y ? "Yıllık" : "Aylık"}
                {y && <span className="ml-1.5 text-[10px] font-mono bg-success-500/20 text-success-600 dark:text-success-500 px-1.5 py-0.5 rounded-full">-20%</span>}
              </button>
            ))}
          </div>
        </motion.div>

        <div className="grid md:grid-cols-3 gap-5 max-w-5xl mx-auto">
          {plans.map((p, i) => (
            <motion.div
              key={p.name}
              initial={{ opacity: 0, y: 24 }}
              animate={inView ? { opacity: 1, y: 0 } : {}}
              transition={{ delay: i * 0.08, duration: 0.6 }}
              className={`relative rounded-2xl p-7 transition-all duration-300 hover:-translate-y-1 ${
                p.featured
                  ? "bg-gradient-to-b from-brand-600 to-brand-700 text-white shadow-[0_28px_70px_-24px_rgba(99,102,241,0.7)] md:scale-[1.03]"
                  : surface
              }`}
            >
              {p.featured && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-gold-400 text-ink-900 text-[10px] font-bold uppercase tracking-widest">
                  Öne Çıkan
                </div>
              )}
              <h3 className={`text-xl font-bold ${p.featured ? "text-white" : "text-ink-900 dark:text-ink-50"}`}>{p.name}</h3>
              <p className={`text-sm mt-1 ${p.featured ? "text-white/70" : "text-ink-500 dark:text-ink-400"}`}>{p.desc}</p>
              <div className="mt-6 mb-1">
                {p.price === null ? (
                  <span className={`text-3xl font-bold ${p.featured ? "text-white" : "text-ink-900 dark:text-ink-50"}`}>Teklif al</span>
                ) : (
                  <div className="flex items-baseline gap-1">
                    <span className={`text-4xl font-bold tabular-nums ${p.featured ? "text-white" : "text-brand-600 dark:text-brand-300"}`}>₺{p.price}</span>
                    <span className={`text-sm ${p.featured ? "text-white/70" : "text-ink-400"}`}>/ ay</span>
                  </div>
                )}
              </div>
              <ul className="mt-6 space-y-2.5 mb-8">
                {p.features.map((f) => (
                  <li key={f} className={`flex items-center gap-2 text-sm ${p.featured ? "text-white/90" : "text-ink-600 dark:text-ink-300"}`}>
                    <Icon name="check" size={14} className={p.featured ? "text-gold-300" : "text-brand-500"} />
                    {f}
                  </li>
                ))}
              </ul>
              <Link
                to={p.cta === "İletişime Geç" ? "/#iletisim" : "/register"}
                className={`w-full ${
                  p.featured
                    ? "inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold bg-white text-brand-700 hover:-translate-y-0.5 transition-transform"
                    : btnPrimary
                }`}
              >
                {p.cta}
              </Link>
              <p className={`text-center text-[10px] mt-3 ${p.featured ? "text-white/60" : "text-ink-400"}`}>{TRIAL_DAYS} gün ücretsiz</p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
