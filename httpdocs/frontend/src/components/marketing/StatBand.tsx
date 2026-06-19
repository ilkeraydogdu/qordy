import { useInView } from "react-intersection-observer";
import CountUp from "react-countup";

/* ================================================================
   QORDY — Marketing StatBand

   The indigo gradient KPI band shared by sub-pages (Features,
   Pricing, About). Counts up on scroll-in.
   ================================================================ */

export interface Stat {
  value: number;
  suffix?: string;
  label: string;
  decimals?: number;
}

const DEFAULT_STATS: Stat[] = [
  { value: 1200, suffix: "+", label: "Aktif İşletme" },
  { value: 24, suffix: "/7", label: "Canlı Destek" },
  { value: 14, suffix: " gün", label: "Ücretsiz Deneme" },
  { value: 99.9, suffix: "%", label: "Uptime SLA", decimals: 1 },
];

export function StatBand({ stats = DEFAULT_STATS }: { stats?: Stat[] }) {
  const { ref, inView } = useInView({ triggerOnce: true, threshold: 0.3 });
  return (
    <section
      ref={ref}
      className="relative overflow-hidden bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 py-16 text-white"
    >
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(245,158,11,0.28),transparent_60%)]" />
      <div className="container-q relative">
        <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
          {stats.map((s) => (
            <div key={s.label} className="text-center">
              <div className="font-display text-4xl font-bold tabular-nums md:text-5xl">
                {inView ? <CountUp end={s.value} duration={2.2} decimals={s.decimals ?? 0} separator="." /> : "0"}
                {s.suffix}
              </div>
              <div className="mt-2 text-sm font-medium uppercase tracking-wider text-brand-100/80">{s.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

export default StatBand;
