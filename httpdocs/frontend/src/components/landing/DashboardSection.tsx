import { motion } from "framer-motion";
import CountUp from "react-countup";
import { Icon } from "@/components/ui/Icon";
import { Logo } from "@/components/ui/Logo";
import { fade, surface, useInViewAnim } from "./primitives";
import { TurkeyMap } from "./TurkeyMap";
import {
  PANEL_NAV,
  PANEL_KPI,
  PANEL_ORDERS,
  PANEL_PRODUCTS,
  PANEL_CATEGORIES,
  PANEL_HOURS,
} from "./data";

const ORDER_STATE: Record<string, { label: string; cls: string; dot: string }> = {
  new: { label: "Yeni", cls: "bg-brand-500/12 text-brand-600 dark:text-brand-300", dot: "bg-brand-500" },
  prep: { label: "Hazırlanıyor", cls: "bg-gold-500/15 text-gold-600 dark:text-gold-400", dot: "bg-gold-500" },
  ready: { label: "Hazır", cls: "bg-success-500/12 text-success-600 dark:text-success-500", dot: "bg-success-500" },
};

function CategoryDonut({ animate }: { animate: boolean }) {
  const r = 30;
  const c = 2 * Math.PI * r;
  let offset = 0;
  return (
    <motion.svg
      viewBox="0 0 80 80"
      className="h-[92px] w-[92px] shrink-0 -rotate-90"
      initial={{ scale: 0.8, opacity: 0 }}
      animate={animate ? { scale: 1, opacity: 1 } : {}}
      transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
      aria-hidden
    >
      <circle cx="40" cy="40" r={r} fill="none" stroke="currentColor" strokeWidth="9" className="text-ink-200 dark:text-white/[0.08]" />
      {PANEL_CATEGORIES.map((d) => {
        const len = (d.value / 100) * c;
        const seg = (
          <motion.circle
            key={d.label}
            cx="40"
            cy="40"
            r={r}
            fill="none"
            stroke={d.color}
            strokeWidth="9"
            strokeDasharray={`${len} ${c - len}`}
            initial={{ strokeDashoffset: -offset - len }}
            animate={animate ? { strokeDashoffset: -offset } : {}}
            transition={{ duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
          />
        );
        offset += len;
        return seg;
      })}
    </motion.svg>
  );
}

function HourlyBars({ animate }: { animate: boolean }) {
  return (
    <div className="mt-3 flex h-[120px] items-end gap-[3px] sm:gap-1.5">
      {PANEL_HOURS.map((b, i) => (
        <div key={b.h} className="flex flex-1 flex-col items-center gap-1.5">
          <motion.span
            className="w-full rounded-t-[3px] bg-gradient-to-t from-brand-500/40 to-brand-500"
            initial={{ height: 0 }}
            animate={animate ? { height: `${b.v}%` } : {}}
            transition={{ duration: 0.7, delay: i * 0.04, ease: [0.16, 1, 0.3, 1] }}
            style={{ minHeight: 4 }}
          />
          <span className="hidden text-[8px] text-ink-400 sm:block">{b.h}</span>
        </div>
      ))}
    </div>
  );
}

function AnalyticsDashboard() {
  const { ref, inView } = useInViewAnim("-40px");
  return (
    <div ref={ref} className={`relative overflow-hidden ${surface} rounded-3xl`}>
      {/* App top bar */}
      <div className="flex items-center gap-3 border-b border-ink-200 px-4 py-3 dark:border-white/[0.06]">
        <Logo variant="dark" asLink={false} className="h-6 w-auto" />
        <span className="ml-2 hidden text-sm font-medium text-ink-500 dark:text-ink-400 sm:inline">Genel Bakış</span>
        <div className="ml-auto flex items-center gap-2">
          <span className="hidden items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-[11px] text-ink-500 dark:border-white/[0.1] dark:text-ink-400 md:inline-flex">
            <Icon name="calendar" size={12} /> 1 – 30 Haziran 2026
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-[11px] text-ink-500 dark:border-white/[0.1] dark:text-ink-400">
            Tüm Şubeler <Icon name="chevronDown" size={11} />
          </span>
          <span className="grid h-7 w-7 place-items-center rounded-lg text-ink-400"><Icon name="bell" size={14} /></span>
          <span className="h-7 w-7 rounded-full bg-gradient-to-br from-brand-400 to-brand-600" />
        </div>
      </div>

      <div className="flex">
        {/* Sidebar */}
        <aside className="hidden w-[150px] shrink-0 space-y-0.5 border-r border-ink-200 px-2 py-3 dark:border-white/[0.06] md:block">
          {PANEL_NAV.map((n, i) => (
            <div
              key={n}
              className={`flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-medium ${
                i === 0 ? "bg-brand-500/12 text-brand-600 dark:text-brand-300" : "text-ink-500 dark:text-ink-400"
              }`}
            >
              <span className={`h-1.5 w-1.5 rounded-full ${i === 0 ? "bg-brand-500" : "bg-ink-300 dark:bg-white/20"}`} />
              {n}
            </div>
          ))}
        </aside>

        {/* Content */}
        <div className="min-w-0 flex-1 space-y-3 p-3 sm:space-y-4 sm:p-4">
          {/* KPIs */}
          <div className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            {PANEL_KPI.map((k) => (
              <div key={k.label} className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
                <div className="text-[10px] text-ink-500 dark:text-ink-400">{k.label}</div>
                <div className="mt-1 text-base font-bold tabular-nums text-ink-900 dark:text-ink-50 sm:text-lg">
                  {k.prefix}
                  {inView ? <CountUp end={k.value} duration={1.8} separator="." /> : 0}
                </div>
                <div className="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-success-600 dark:text-success-500">
                  <Icon name="arrow" size={9} className="-rotate-45" /> {k.delta}
                </div>
              </div>
            ))}
          </div>

          {/* Live feed + Turkey map */}
          <div className="grid gap-3 lg:grid-cols-[1.55fr_1fr]">
            <div className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
              <div className="mb-2 flex items-center justify-between">
                <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Canlı Sipariş Akışı</span>
                <span className="inline-flex items-center gap-1 rounded-full bg-success-500/10 px-2 py-0.5 text-[9px] font-semibold text-success-600 dark:text-success-500">
                  <span className="h-1.5 w-1.5 animate-pulse-lime rounded-full bg-success-500" /> Canlı
                </span>
              </div>
              <ul className="space-y-1">
                {PANEL_ORDERS.map((o) => {
                  const st = ORDER_STATE[o.state];
                  return (
                    <li key={o.id} className="flex items-center gap-2 rounded-lg px-1.5 py-1.5 text-[11px] hover:bg-white dark:hover:bg-white/[0.04]">
                      <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${st.dot}`} />
                      <span className="shrink-0 font-semibold tabular-nums text-ink-800 dark:text-ink-100">{o.id}</span>
                      <span className="min-w-0 flex-1 truncate text-ink-500 dark:text-ink-400">{o.branch}</span>
                      <span className="ml-auto shrink-0 font-semibold tabular-nums text-ink-700 dark:text-ink-200">{o.amount}</span>
                      <span className={`hidden shrink-0 rounded-full px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide sm:inline ${st.cls}`}>
                        {st.label}
                      </span>
                      <span className="hidden w-9 shrink-0 text-right text-[9px] text-ink-400 sm:inline">{o.time}</span>
                    </li>
                  );
                })}
              </ul>
            </div>

            <div className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
              <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Şube Performansı</span>
              <TurkeyMap className="mt-1" />
              <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[9px] text-ink-500 dark:text-ink-400">
                <span className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-success-500" />Yüksek</span>
                <span className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-gold-500" />Orta</span>
                <span className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-brand-500" />Düşük</span>
              </div>
            </div>
          </div>

          {/* Products + categories + hourly */}
          <div className="grid gap-3 lg:grid-cols-[1.1fr_1fr_1.3fr]">
            {/* Popüler Ürünler with food photos */}
            <div className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
              <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Popüler Ürünler</span>
              <ul className="mt-2 space-y-2">
                {PANEL_PRODUCTS.map((p) => (
                  <li key={p.name} className="flex items-center gap-2.5">
                    <span className={`h-9 w-9 shrink-0 overflow-hidden rounded-full bg-gradient-to-br ${p.grad} ring-1 ring-black/5`}>
                      <img src={p.img} alt={p.name} loading="lazy" className="h-full w-full object-cover" />
                    </span>
                    <span className="min-w-0">
                      <span className="block truncate text-[11px] font-semibold text-ink-800 dark:text-ink-100">{p.name}</span>
                      <span className="block text-[9px] text-ink-500 dark:text-ink-400">{p.count} adet</span>
                    </span>
                    <span className="ml-auto shrink-0 text-[10px] font-bold tabular-nums text-ink-700 dark:text-ink-200">{p.revenue}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* Kategorilere Göre Ciro */}
            <div className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
              <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Kategorilere Göre Ciro</span>
              <div className="mt-2 flex items-center gap-3">
                <div className="relative shrink-0">
                  <CategoryDonut animate={inView} />
                  <span className="absolute inset-0 grid place-items-center text-center">
                    <span className="text-[10px] font-bold leading-none text-ink-900 dark:text-ink-50">₺8.45M</span>
                  </span>
                </div>
                <ul className="min-w-0 space-y-1">
                  {PANEL_CATEGORIES.map((d) => (
                    <li key={d.label} className="flex items-center gap-1.5 text-[10px] text-ink-500 dark:text-ink-400">
                      <span className="h-2 w-2 shrink-0 rounded-full" style={{ background: d.color }} />
                      <span className="truncate">{d.label}</span>
                      <span className="ml-auto font-semibold text-ink-700 dark:text-ink-200">%{d.value}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {/* Saatlik Ciro */}
            <div className="rounded-xl border border-ink-200 bg-ink-50/60 p-3 dark:border-white/[0.06] dark:bg-white/[0.03]">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Saatlik Ciro</span>
                <span className="text-[10px] font-bold text-brand-600 dark:text-brand-300">Pik 20:00</span>
              </div>
              <HourlyBars animate={inView} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export function DashboardSection() {
  return (
    <section id="panel" className="section-pad bg-white dark:bg-ink-950 border-y border-ink-200/70 dark:border-white/[0.05]">
      <div className="container-q">
        <motion.div {...fade(0)} className="max-w-2xl mb-12">
          <div className="eyebrow mb-5">Yönetim Paneli</div>
          <h2 className="text-display-lg font-bold tracking-tight text-ink-900 dark:text-ink-50">
            Tüm operasyon, <span className="gradient-text">tek ekranda</span>.
          </h2>
          <p className="mt-4 text-ink-600 dark:text-ink-300 leading-relaxed">
            Canlı sipariş akışı, şube performans haritası, kategori kırılımı ve saatlik ciro — karar vermek için rapor beklemeyin.
          </p>
        </motion.div>

        <motion.div {...fade(0.1)} className="relative">
          <div className="absolute -inset-6 rounded-[40px] bg-gradient-to-br from-brand-500/15 to-transparent blur-3xl" aria-hidden="true" />
          <AnalyticsDashboard />
        </motion.div>
      </div>
    </section>
  );
}
