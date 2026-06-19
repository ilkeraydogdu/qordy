import { motion } from "framer-motion";
import { type ReactNode } from "react";
import Tilt from "react-parallax-tilt";
import { Icon } from "@/components/ui/Icon";
import { fade, useInViewAnim } from "./primitives";

// iOS-style status bar that sells the "real device" illusion.
function PhoneStatusBar() {
  return (
    <div className="relative flex items-center justify-between px-5 pt-2.5 pb-1 text-[10px] font-semibold text-ink-800 dark:text-ink-100">
      <span className="tabular-nums">9:41</span>
      <span aria-hidden className="absolute left-1/2 top-2 h-[16px] w-16 -translate-x-1/2 rounded-full bg-ink-900 dark:bg-black" />
      <span className="flex items-center gap-1.5 text-ink-700 dark:text-ink-200">
        <svg width="15" height="10" viewBox="0 0 15 10" fill="currentColor" aria-hidden>
          <rect x="0" y="7" width="2.4" height="3" rx="0.6" />
          <rect x="4" y="5" width="2.4" height="5" rx="0.6" />
          <rect x="8" y="2.5" width="2.4" height="7.5" rx="0.6" />
          <rect x="12" y="0" width="2.4" height="10" rx="0.6" opacity="0.4" />
        </svg>
        <svg width="13" height="10" viewBox="0 0 13 10" fill="currentColor" aria-hidden>
          <path d="M6.5 1C4 1 1.8 2 .3 3.6l1.1 1.1C2.6 3.4 4.4 2.6 6.5 2.6s3.9.8 5.1 2.1l1.1-1.1C11.2 2 9 1 6.5 1Z" />
          <path d="M6.5 4.4c-1.4 0-2.7.6-3.6 1.5l1.1 1.1c.6-.7 1.5-1.1 2.5-1.1s1.9.4 2.5 1.1l1.1-1.1C9.2 5 7.9 4.4 6.5 4.4Z" />
          <circle cx="6.5" cy="8.6" r="1.3" />
        </svg>
        <span className="flex items-center gap-0.5">
          <span className="relative h-[10px] w-[20px] rounded-[3px] border border-current">
            <span className="absolute inset-[1.5px] right-[5px] rounded-[1px] bg-current" />
          </span>
          <span className="h-[4px] w-[1.5px] rounded-r bg-current" />
        </span>
      </span>
    </div>
  );
}

function PhoneFrame({
  title,
  role,
  tiltDir = 0,
  children,
}: {
  title: string;
  role: "waiter" | "kitchen";
  tiltDir?: number;
  children: ReactNode;
}) {
  const isWaiter = role === "waiter";
  return (
    <div className="w-[230px] shrink-0 sm:w-[256px]">
      {/* Section label badge */}
      <div className="mb-4 flex justify-center">
        <span
          className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-semibold backdrop-blur-sm ${
            isWaiter
              ? "border-brand-500/20 bg-brand-500/10 text-brand-600 dark:text-brand-300"
              : "border-gold-500/25 bg-gold-500/10 text-gold-600 dark:text-gold-400"
          }`}
        >
          <Icon name={isWaiter ? "plate" : "kitchen"} size={13} strokeWidth={1.75} />
          {title}
        </span>
      </div>

      <Tilt
        tiltMaxAngleX={6}
        tiltMaxAngleY={8}
        perspective={1200}
        scale={1.02}
        transitionSpeed={1600}
        glareEnable
        glareMaxOpacity={0.14}
        glareColor="#ffffff"
        glarePosition="all"
        glareBorderRadius="46px"
        tiltAngleYInitial={tiltDir}
        className="will-change-transform"
      >
        <div className="relative animate-float" style={{ animationDelay: isWaiter ? "0s" : "-3s" }}>
          {/* ambient glow */}
          <div aria-hidden className={`absolute -inset-5 rounded-[52px] blur-2xl ${isWaiter ? "bg-brand-500/25" : "bg-gold-500/20"}`} />
          {/* device bezel — iPhone-like 19.5:9 proportions */}
          <div className="relative rounded-[46px] border-[6px] border-ink-900 bg-ink-900 p-[3px] shadow-[0_44px_100px_-30px_rgba(15,18,40,0.7),0_0_0_1px_rgba(255,255,255,0.06)_inset] dark:border-ink-700 dark:bg-ink-800">
            {/* side buttons */}
            <span aria-hidden className="absolute -left-[7px] top-[96px] h-7 w-[3px] rounded-l bg-ink-800 dark:bg-ink-600" />
            <span aria-hidden className="absolute -left-[7px] top-[134px] h-12 w-[3px] rounded-l bg-ink-800 dark:bg-ink-600" />
            <span aria-hidden className="absolute -right-[7px] top-[116px] h-16 w-[3px] rounded-r bg-ink-800 dark:bg-ink-600" />
            <div className="flex min-h-[492px] flex-col overflow-hidden rounded-[40px] bg-white dark:bg-ink-900">
              <PhoneStatusBar />
              <div className="flex-1">{children}</div>
              {/* home indicator */}
              <div className="flex justify-center pb-2 pt-1">
                <span aria-hidden className="h-1 w-24 rounded-full bg-ink-900/20 dark:bg-white/20" />
              </div>
            </div>
          </div>
        </div>
      </Tilt>
    </div>
  );
}

function WaiterApp() {
  const items = [
    { n: "Cheeseburger", p: "₺280", qty: 1, img: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=240&q=70" },
    { n: "Pizza Margherita", p: "₺350", qty: 1, img: "https://images.unsplash.com/photo-1574071318508-1cdbab80d002?auto=format&fit=crop&w=240&q=70" },
    { n: "Caesar Salad", p: "₺220", qty: 0, img: "https://images.unsplash.com/photo-1550304943-4f24f54ddde9?auto=format&fit=crop&w=240&q=70" },
    { n: "Limonata", p: "₺130", qty: 0, img: "https://images.unsplash.com/photo-1621263764928-df1444c5e859?auto=format&fit=crop&w=240&q=70" },
  ];
  return (
    <div className="px-3 pb-3">
      <div className="flex items-center justify-between py-2">
        <span className="flex items-center gap-2 text-sm font-bold text-ink-900 dark:text-ink-50">
          <span className="grid h-6 w-6 place-items-center rounded-lg bg-brand-500/12 text-[10px] font-bold text-brand-600 dark:text-brand-300">12</span>
          Masa 12
        </span>
        <span className="inline-flex items-center gap-1 rounded-full bg-success-500/10 px-2 py-0.5 text-[9px] font-semibold text-success-600 dark:text-success-500">
          <span className="h-1.5 w-1.5 rounded-full bg-success-500" /> Açık
        </span>
      </div>
      <div className="mb-2.5 flex gap-1.5">
        {["Tümü", "Yiyecek", "İçecek", "Tatlı"].map((t, i) => (
          <span
            key={t}
            className={`rounded-lg px-2 py-1 text-[10px] font-medium ${
              i === 0 ? "bg-brand-500 text-white shadow-brand-sm" : "bg-ink-100 text-ink-500 dark:bg-white/[0.06] dark:text-ink-400"
            }`}
          >
            {t}
          </span>
        ))}
      </div>
      <div className="grid grid-cols-2 gap-2">
        {items.map((it) => (
          <div
            key={it.n}
            className={`relative rounded-xl border p-2 transition-colors ${
              it.qty > 0 ? "border-brand-500/40 bg-brand-500/[0.04]" : "border-ink-200 dark:border-white/[0.06]"
            }`}
          >
            {it.qty > 0 && (
              <span className="absolute -right-1.5 -top-1.5 z-10 grid h-4 w-4 place-items-center rounded-full bg-brand-500 text-[9px] font-bold text-white shadow-brand-sm">
                {it.qty}
              </span>
            )}
            <div className="mb-1.5 h-12 overflow-hidden rounded-lg bg-gradient-to-br from-gold-200 to-orange-300 dark:from-gold-300/40 dark:to-orange-400/40">
              <img
                src={it.img}
                alt={it.n}
                loading="lazy"
                draggable={false}
                className="h-full w-full object-cover"
                onError={(e) => { e.currentTarget.style.display = "none"; }}
              />
            </div>
            <div className="truncate text-[10px] font-medium text-ink-800 dark:text-ink-100">{it.n}</div>
            <div className="text-[10px] font-bold text-brand-600 dark:text-brand-300">{it.p}</div>
          </div>
        ))}
      </div>
      <div className="mt-3 flex items-center justify-between px-0.5">
        <span className="text-[10px] text-ink-500 dark:text-ink-400">3 ürün · Toplam</span>
        <span className="text-xs font-bold text-ink-900 dark:text-ink-50">₺750</span>
      </div>
      <button className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 py-2.5 text-xs font-semibold text-white shadow-brand-sm">
        Sipariş Gönder
        <Icon name="arrow" size={13} strokeWidth={2.25} />
      </button>
    </div>
  );
}

const KITCHEN_GROUPS = [
  {
    key: "new",
    label: "Yeni Siparişler",
    dot: "bg-brand-500",
    badge: "bg-brand-500/12 text-brand-600 dark:text-brand-300",
    bar: "border-l-brand-500 bg-brand-500/[0.05]",
    fresh: true,
    orders: [
      { id: "#12345", table: "Masa 12", time: "şimdi" },
      { id: "#12346", table: "Masa 8", time: "3 dk" },
    ],
  },
  {
    key: "prep",
    label: "Hazırlanıyor",
    dot: "bg-gold-500",
    badge: "bg-gold-500/15 text-gold-600 dark:text-gold-400",
    bar: "border-l-gold-500 bg-gold-500/[0.06]",
    fresh: false,
    orders: [{ id: "#12343", table: "Masa 5", time: "5 dk" }],
  },
  {
    key: "ready",
    label: "Hazır",
    dot: "bg-success-500",
    badge: "bg-success-500/12 text-success-600 dark:text-success-500",
    bar: "border-l-success-500 bg-success-500/[0.06]",
    fresh: false,
    orders: [{ id: "#12342", table: "Paket Servis", time: "1 dk" }],
  },
] as const;

function KitchenScreen() {
  return (
    <div className="px-3 pb-3 pt-1.5">
      <div className="flex items-center justify-between pb-2">
        <span className="text-sm font-bold text-ink-900 dark:text-ink-50">Mutfak Akışı</span>
        <span className="inline-flex items-center gap-1 rounded-full bg-success-500/10 px-2 py-0.5 text-[9px] font-semibold text-success-600 dark:text-success-500">
          <span className="h-1.5 w-1.5 animate-pulse-lime rounded-full bg-success-500" /> Canlı
        </span>
      </div>
      <div className="space-y-2.5">
        {KITCHEN_GROUPS.map((g) => (
          <div key={g.key}>
            <div className="mb-1.5 flex items-center gap-1.5 px-0.5">
              <span className={`h-1.5 w-1.5 rounded-full ${g.dot}`} />
              <span className="text-[10px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">{g.label}</span>
              <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[9px] font-bold ${g.badge}`}>{g.orders.length}</span>
            </div>
            <div className="space-y-1.5">
              {g.orders.map((o, i) => (
                <div key={o.id} className={`relative rounded-lg border border-l-[3px] border-ink-200 px-2.5 py-1.5 dark:border-white/[0.06] ${g.bar}`}>
                  {g.fresh && i === 0 && (
                    <span aria-hidden className="absolute -right-1 -top-1 flex h-2.5 w-2.5">
                      <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-500 opacity-70" />
                      <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-500" />
                    </span>
                  )}
                  <div className="flex items-center justify-between">
                    <span className="text-[11px] font-bold text-ink-800 dark:text-ink-100">{o.id}</span>
                    <span className="text-[9px] text-ink-400">{o.time}</span>
                  </div>
                  <div className="mt-0.5 flex items-center justify-between">
                    <span className="text-[10px] text-ink-500 dark:text-ink-400">{o.table}</span>
                    <span className={`rounded-full px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide ${g.badge}`}>{g.label.split(" ")[0]}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// Animated "order travels waiter → kitchen" connector (a flying ticket).
function OrderFlowConnector() {
  const ticket = (
    <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-brand-500/30 bg-white px-2 py-1 text-[9px] font-bold text-ink-800 shadow-[0_6px_18px_-6px_rgba(99,102,241,0.6)] dark:bg-ink-800 dark:text-ink-100">
      <Icon name="check" size={9} strokeWidth={3} className="text-brand-500" />
      Masa 12 · ₺750
    </span>
  );
  return (
    <div aria-hidden className="relative flex w-full shrink-0 items-center justify-center py-3 lg:w-40 lg:py-0">
      {/* mobile: vertical flow */}
      <div className="relative flex h-20 w-px items-center justify-center lg:hidden">
        <div className="h-full w-[2px] rounded-full bg-gradient-to-b from-brand-500/30 via-brand-500/80 to-gold-500/50" />
        <motion.div
          className="absolute left-1/2 -translate-x-1/2"
          animate={{ top: ["6%", "94%"], opacity: [0, 1, 1, 0] }}
          transition={{ duration: 2.4, repeat: Infinity, ease: "easeInOut", times: [0, 0.12, 0.85, 1] }}
        >
          {ticket}
        </motion.div>
        <span className="absolute -bottom-1 text-gold-500">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 5v14M6 13l6 6 6-6" />
          </svg>
        </span>
      </div>

      {/* desktop: horizontal flow */}
      <div className="relative hidden w-full items-center lg:flex">
        <div className="h-[2px] w-full rounded-full bg-gradient-to-r from-brand-500/30 via-brand-500/80 to-gold-500/50" />
        <span className="absolute right-0 -translate-y-px text-gold-500">
          <Icon name="arrow" size={16} strokeWidth={2.5} />
        </span>
        <motion.div
          className="absolute top-1/2 -translate-y-1/2"
          animate={{ left: ["2%", "82%"], opacity: [0, 1, 1, 0] }}
          transition={{ duration: 2.4, repeat: Infinity, ease: "easeInOut", times: [0, 0.12, 0.85, 1] }}
        >
          {ticket}
        </motion.div>
        <span className="absolute -bottom-7 left-1/2 -translate-x-1/2 whitespace-nowrap font-mono text-[10px] uppercase tracking-wider text-brand-500">
          ~80&thinsp;ms
        </span>
      </div>
    </div>
  );
}

const FLOW_PROOF = [
  { icon: "check" as const, label: "Kağıt yok" },
  { icon: "shield" as const, label: "Hata yok" },
  { icon: "bolt" as const, label: "Gecikme yok" },
];

export function MobileSection() {
  const { ref, inView } = useInViewAnim();
  return (
    <section id="pos" ref={ref} className="scroll-mt-28 section-pad relative overflow-hidden bg-[#F4F5FA] dark:bg-ink-950">
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute left-[8%] top-[18%] h-72 w-72 rounded-full bg-brand-500/10 blur-[120px]" />
        <div className="absolute bottom-[12%] right-[8%] h-72 w-72 rounded-full bg-gold-500/10 blur-[120px]" />
      </div>

      <div className="container-q relative">
        <motion.div {...fade(0)} className="mx-auto mb-14 max-w-2xl text-center">
          <div className="eyebrow mb-5 justify-center">Sahada</div>
          <h2 className="text-display-lg font-bold tracking-tight text-ink-900 dark:text-ink-50">
            Garson ve mutfak, <span className="gradient-text">aynı anda</span>.
          </h2>
          <p className="mt-4 text-ink-600 dark:text-ink-300">
            Sipariş garsonun elinden anında mutfağa düşer. Kağıt yok, hata yok, gecikme yok.
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 28 }}
          animate={inView ? { opacity: 1, y: 0 } : {}}
          transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
          className="flex flex-col items-center justify-center gap-2 [perspective:1400px] lg:flex-row lg:gap-4"
        >
          <PhoneFrame title="Garson Uygulaması" role="waiter" tiltDir={6}>
            <WaiterApp />
          </PhoneFrame>

          <OrderFlowConnector />

          <PhoneFrame title="Mutfak Ekranı" role="kitchen" tiltDir={-6}>
            <KitchenScreen />
          </PhoneFrame>
        </motion.div>

        <motion.div {...fade(0.2)} className="mt-12 flex flex-wrap items-center justify-center gap-3">
          {FLOW_PROOF.map((p) => (
            <span
              key={p.label}
              className="inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-700 shadow-soft-sm dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-ink-200"
            >
              <span className="grid h-5 w-5 place-items-center rounded-full bg-success-500/12 text-success-600 dark:text-success-500">
                <Icon name={p.icon} size={12} strokeWidth={2.5} />
              </span>
              {p.label}
            </span>
          ))}
        </motion.div>
      </div>
    </section>
  );
}
