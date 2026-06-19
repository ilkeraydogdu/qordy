import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";
import { fade } from "./primitives";
import { HERO_STATS } from "./data";
import { btnPrimary } from "./primitives";
import { DashboardMock } from "./DashboardMock";

export function HeroSection() {
  return (
    <section className="relative overflow-hidden">
      {/* Background — light mesh / dark glow */}
      <div className="absolute inset-0 -z-10" aria-hidden="true">
        <div className="absolute inset-0 bg-[#F4F5FA] dark:bg-ink-950" />
        <div className="absolute inset-0 bg-hero-mesh dark:opacity-0" />
        <div className="hidden dark:block absolute top-[-15%] left-[-5%] w-[680px] h-[680px] rounded-full bg-brand-500/[0.14] blur-[140px]" />
        <div className="hidden dark:block absolute top-[10%] right-[-10%] w-[520px] h-[520px] rounded-full bg-brand-600/[0.10] blur-[120px]" />
      </div>

      {/* hero-top class uses max() + env(safe-area-inset-top) to always clear
          the fixed navbar, including notched iPhones with Dynamic Island. */}
      <div className="container-q hero-top pb-16 md:pb-24">
        <div className="grid lg:grid-cols-[0.92fr_1.08fr] gap-10 lg:gap-12 items-center">
          {/* Left */}
          <div>
            {/* Heading — text-display-2xl clamps down to 2.75rem (44px) on
                320px which still fits two lines comfortably. */}
            <motion.h1 {...fade(0)} className="text-display-2xl font-bold leading-[1.05] tracking-tight text-ink-900 dark:text-ink-50">
              500+ Şubeyi<br />
              Tek Panelden <span className="gradient-text">Yönetin</span>
            </motion.h1>

            <motion.p {...fade(0.08)} className="mt-5 text-base sm:text-lg leading-relaxed text-ink-600 dark:text-ink-300 max-w-lg">
              POS, QR Menü, Mutfak, Stok, Finans ve daha fazlası. Tüm operasyonlarınızı tek ekosistemde birleştirin.
            </motion.p>

            {/* CTA buttons — stack vertically on 320-479px, side-by-side on 480px+ */}
            <motion.div {...fade(0.14)} className="mt-8 flex flex-col min-[480px]:flex-row min-[480px]:flex-wrap gap-3">
              <Link to="/register" className={`${btnPrimary} w-full min-[480px]:w-auto justify-center`}>
                Ücretsiz Demo Talep Et
                <Icon name="arrow" size={16} />
              </Link>
              <a
                href="#panel"
                className="inline-flex items-center justify-center gap-2.5 rounded-xl pl-5 min-[480px]:pl-6 pr-2 py-2 text-sm font-semibold border transition-colors duration-300 border-ink-200 bg-white text-ink-700 hover:border-brand-300 dark:bg-white/[0.05] dark:border-white/[0.12] dark:text-ink-100 dark:hover:border-white/25 w-full min-[480px]:w-auto"
              >
                Canlı İncele
                <span className="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-600 text-white shrink-0">
                  <Icon name="play" size={14} />
                </span>
              </a>
            </motion.div>

            {/* Feature checklist — wraps neatly; 2-col grid on tiny screens */}
            <motion.div {...fade(0.2)} className="mt-7 grid grid-cols-1 min-[380px]:grid-cols-2 sm:flex sm:flex-wrap gap-x-6 gap-y-2.5">
              {["15 Dakikada Kurulum", "Kredi Kartı Gerekmez", "7/24 Destek"].map((t) => (
                <span key={t} className="inline-flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                  <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-300">
                    <Icon name="check" size={12} />
                  </span>
                  {t}
                </span>
              ))}
            </motion.div>

            {/* Stats band — 2×2 on mobile, single row on sm+ */}
            <motion.div {...fade(0.26)} className="mt-8 grid grid-cols-2 sm:grid-cols-4 rounded-2xl border border-ink-200/80 dark:border-white/[0.07] bg-white/60 dark:bg-white/[0.02] divide-y sm:divide-y-0 sm:divide-x divide-ink-200/70 dark:divide-white/[0.06] overflow-hidden">
              {HERO_STATS.map((s) => (
                <div key={s.label} className="px-3 py-4 sm:px-4 sm:py-5 text-center sm:text-left">
                  <div className="text-[22px] sm:text-[26px] md:text-[28px] leading-none font-bold text-brand-500 dark:text-brand-400 tabular-nums">{s.value}</div>
                  <div className="text-[11px] sm:text-xs text-ink-500 dark:text-ink-400 mt-1.5 sm:mt-2">{s.label}</div>
                </div>
              ))}
            </motion.div>
          </div>

          {/* Right — dashboard preview */}
          <motion.div
            initial={{ opacity: 0, y: 28, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ delay: 0.2, duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
            className="relative"
          >
            <div className="absolute -inset-6 rounded-[36px] bg-gradient-to-br from-brand-500/20 to-brand-600/10 blur-3xl dark:from-brand-500/25 dark:to-brand-700/10" aria-hidden="true" />
            <DashboardMock />
          </motion.div>
        </div>
      </div>
    </section>
  );
}
