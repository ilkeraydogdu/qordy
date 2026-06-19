import { Logo } from "@/components/ui/Logo";
import { Icon } from "@/components/ui/Icon";
import { surface } from "./primitives";
import { HERO_NAV, HERO_KPI, HERO_DONUT, HERO_PRODUCTS } from "./data";

/* ---- HERO dashboard preview --------------------------------------
   Deliberately COMPACT — a "glance" of the product. The full,
   realistic analytics view lives in <DashboardSection /> (#panel) and
   is intentionally a different layout. */

function MiniLineChart() {
  return (
    <svg viewBox="0 0 320 80" className="w-full h-[64px]" preserveAspectRatio="none" aria-hidden>
      <defs>
        <linearGradient id="hero-lc" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#6366F1" stopOpacity="0.32" />
          <stop offset="100%" stopColor="#6366F1" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d="M0 62 L40 56 L80 58 L120 44 L160 48 L200 32 L240 26 L280 18 L320 12 L320 80 L0 80 Z" fill="url(#hero-lc)" />
      <path
        d="M0 62 L40 56 L80 58 L120 44 L160 48 L200 32 L240 26 L280 18 L320 12"
        fill="none"
        stroke="#6366F1"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function MiniDonut() {
  const r = 26;
  const c = 2 * Math.PI * r;
  let offset = 0;
  return (
    <svg viewBox="0 0 70 70" className="h-[64px] w-[64px] shrink-0 -rotate-90" aria-hidden>
      <circle cx="35" cy="35" r={r} fill="none" stroke="currentColor" strokeWidth="8" className="text-ink-200 dark:text-white/[0.08]" />
      {HERO_DONUT.map((d) => {
        const len = (d.value / 100) * c;
        const seg = (
          <circle
            key={d.label}
            cx="35"
            cy="35"
            r={r}
            fill="none"
            stroke={d.color}
            strokeWidth="8"
            strokeDasharray={`${len} ${c - len}`}
            strokeDashoffset={-offset}
          />
        );
        offset += len;
        return seg;
      })}
    </svg>
  );
}

export function DashboardMock() {
  return (
    <div className={`relative overflow-hidden ${surface} rounded-3xl`}>
      {/* Top bar */}
      <div className="flex items-center gap-3 px-4 py-3 border-b border-ink-200 dark:border-white/[0.06]">
        <Logo variant="dark" asLink={false} className="h-6 w-auto" />
        <span className="ml-2 text-sm font-medium text-ink-500 dark:text-ink-400 hidden sm:inline">Genel Bakış</span>
        <div className="ml-auto flex items-center gap-2">
          <span className="hidden md:inline-flex items-center gap-1.5 rounded-lg border border-ink-200 dark:border-white/[0.1] px-2.5 py-1.5 text-[11px] text-ink-500 dark:text-ink-400">
            Tüm Şubeler <Icon name="chevronDown" size={11} />
          </span>
          <span className="grid h-7 w-7 place-items-center rounded-lg text-ink-400"><Icon name="bell" size={14} /></span>
          <span className="h-7 w-7 rounded-full bg-gradient-to-br from-brand-400 to-brand-600" />
        </div>
      </div>

      <div className="flex">
        {/* Sidebar */}
        <aside className="hidden sm:block w-[120px] shrink-0 border-r border-ink-200 dark:border-white/[0.06] py-3 px-2 space-y-0.5">
          {HERO_NAV.map((n, i) => (
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
        <div className="flex-1 min-w-0 p-3.5 space-y-3.5">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
            {HERO_KPI.map((k) => (
              <div key={k.label} className="rounded-xl border border-ink-200 dark:border-white/[0.06] bg-ink-50/60 dark:bg-white/[0.03] p-2.5">
                <div className="text-[10px] text-ink-500 dark:text-ink-400 truncate">{k.label}</div>
                <div className="text-sm font-bold text-ink-900 dark:text-ink-50 mt-0.5 tabular-nums">{k.value}</div>
                <div className="text-[10px] font-semibold text-success-600 dark:text-success-500 mt-0.5">{k.delta}</div>
              </div>
            ))}
          </div>

          <div className="grid lg:grid-cols-[1.4fr_1fr] gap-2.5">
            <div className="rounded-xl border border-ink-200 dark:border-white/[0.06] bg-ink-50/60 dark:bg-white/[0.03] p-3">
              <div className="flex items-center justify-between mb-1">
                <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Ciro Grafiği</span>
                <span className="text-[10px] font-bold text-brand-600 dark:text-brand-300">+12.5%</span>
              </div>
              <MiniLineChart />
            </div>
            <div className="rounded-xl border border-ink-200 dark:border-white/[0.06] bg-ink-50/60 dark:bg-white/[0.03] p-3">
              <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Sipariş Dağılımı</span>
              <div className="flex items-center gap-3 mt-2">
                <MiniDonut />
                <ul className="space-y-1 min-w-0">
                  {HERO_DONUT.map((d) => (
                    <li key={d.label} className="flex items-center gap-1.5 text-[10px] text-ink-500 dark:text-ink-400">
                      <span className="h-2 w-2 rounded-full shrink-0" style={{ background: d.color }} />
                      <span className="truncate">{d.label}</span>
                      <span className="font-semibold text-ink-700 dark:text-ink-200 ml-auto">%{d.value}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>

          <div className="rounded-xl border border-ink-200 dark:border-white/[0.06] bg-ink-50/60 dark:bg-white/[0.03] p-3">
            <span className="text-[11px] font-semibold text-ink-700 dark:text-ink-200">Popüler Ürünler</span>
            <ul className="mt-2 space-y-1.5">
              {HERO_PRODUCTS.map((p) => (
                <li key={p.name} className="flex items-center gap-2 text-[11px]">
                  <span className="h-5 w-5 shrink-0 rounded-md bg-gradient-to-br from-gold-300 to-orange-400" />
                  <span className="min-w-0 flex-1 truncate text-ink-700 dark:text-ink-200">{p.name}</span>
                  <span className="ml-auto shrink-0 font-semibold text-ink-500 dark:text-ink-400 tabular-nums">{p.count}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
