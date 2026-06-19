import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";
import { useInViewAnim } from "./primitives";
import { TRIAL_DAYS } from "./data";

export function FinalCTA() {
  const { ref, inView } = useInViewAnim();
  return (
    <section ref={ref} className="relative py-24 md:py-32 overflow-hidden bg-[#F4F5FA] dark:bg-ink-950">
      <div className="container-q">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          animate={inView ? { opacity: 1, y: 0 } : {}}
          transition={{ duration: 0.7 }}
          className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-600 to-brand-700 px-6 py-16 sm:px-8 md:px-16 text-center"
        >
          <div className="absolute inset-0 opacity-20" style={{ backgroundImage: "radial-gradient(circle at 20% 20%, rgba(255,255,255,0.25), transparent 40%), radial-gradient(circle at 80% 80%, rgba(245,158,11,0.3), transparent 40%)" }} />
          <div className="relative">
            <h2 className="text-display-md font-bold tracking-tight text-white">
              {TRIAL_DAYS} gün ücretsiz. Kredi kartı yok.
            </h2>
            <p className="mt-5 text-white/80 text-base sm:text-lg max-w-md mx-auto">
              Kurulum bizden. Eğitim bizden. İstediğiniz zaman iptal.
            </p>
            <div className="mt-9 flex flex-wrap justify-center gap-3">
              <Link to="/register" className="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-700 hover:-translate-y-0.5 transition-transform">
                Hemen Başla <Icon name="arrow" size={16} />
              </Link>
              <a href="#iletisim" className="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white hover:bg-white/15 transition-colors">
                Satış Ekibiyle Konuş
              </a>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
