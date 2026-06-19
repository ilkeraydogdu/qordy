import { motion, useReducedMotion, AnimatePresence } from "framer-motion";
import { Icon } from "@/components/ui/Icon";

/* ================================================================
   QORDY — QrOrderShowcase

   The premium "guest scans the QR and orders" visual. A realistic
   customer phone (QR menu) sits inside a soft brand stage, with a
   floating QR stand + scan ring and a status toast that reacts to
   the active step (scan → sent → kitchen). Replaces the old hand-
   drawn line-art cafe scene. Real UI chrome = SaaS-grade quality.

   `activeStep`: 0 scan · 1 sent to waiter · 2 in kitchen.
   ================================================================ */

const MENU = [
  {
    name: "Cheeseburger",
    price: "₺280",
    qty: 1,
    img: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=200&q=70",
    grad: "from-gold-200 to-orange-300",
  },
  {
    name: "Margherita",
    price: "₺350",
    qty: 1,
    img: "https://images.unsplash.com/photo-1574071318508-1cdbab80d002?auto=format&fit=crop&w=200&q=70",
    grad: "from-red-200 to-gold-300",
  },
  {
    name: "Limonata",
    price: "₺120",
    qty: 0,
    img: "https://images.unsplash.com/photo-1621263764928-df1444c5e859?auto=format&fit=crop&w=200&q=70",
    grad: "from-yellow-100 to-gold-200",
  },
];

const TOASTS = [
  { icon: "qr" as const, label: "QR okutuldu", sub: "Masa 7 menüsü açıldı", tone: "brand" },
  { icon: "pos" as const, label: "Sipariş gönderildi", sub: "Garson tabletine düştü", tone: "brand" },
  { icon: "kitchen" as const, label: "Mutfakta hazırlanıyor", sub: "Tahmini 12 dk", tone: "gold" },
] as const;

export function QrOrderShowcase({ activeStep = 0 }: { activeStep?: number }) {
  const reduce = useReducedMotion();
  const step = Math.max(0, Math.min(2, activeStep));
  const toast = TOASTS[step];

  return (
    <motion.div
      initial={{ opacity: 0, y: 24 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-60px" }}
      transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
      className="relative mx-auto w-full max-w-md"
    >
      {/* soft brand stage */}
      <div className="relative overflow-hidden rounded-[32px] border border-ink-200 bg-gradient-to-br from-brand-50 via-white to-white p-8 shadow-soft dark:border-white/[0.08] dark:from-white/[0.05] dark:via-ink-900 dark:to-ink-900 sm:p-10">
        <div aria-hidden className="pointer-events-none absolute inset-0">
          <div className="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-brand-500/15 blur-2xl" />
          <div className="absolute -bottom-12 -left-10 h-44 w-44 rounded-full bg-gold-500/15 blur-2xl" />
        </div>

        {/* live tag */}
        <div className="absolute right-5 top-5 z-20 inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white/80 px-2.5 py-1 text-[10px] font-bold text-success-600 backdrop-blur dark:border-white/10 dark:bg-white/10 dark:text-success-500">
          <span className="h-1.5 w-1.5 rounded-full bg-success-500 animate-pulse-lime" /> CANLI
        </div>

        <div className="relative mx-auto w-[230px]">
          {/* phone */}
          <motion.div
            animate={reduce ? {} : { y: [0, -8, 0] }}
            transition={{ duration: 6, repeat: Infinity, ease: "easeInOut" }}
            className="relative rounded-[40px] border-[6px] border-ink-900 bg-ink-900 p-[3px] shadow-[0_44px_100px_-30px_rgba(15,18,40,0.7),0_0_0_1px_rgba(255,255,255,0.06)_inset] dark:border-ink-700 dark:bg-ink-800"
          >
            <span aria-hidden className="absolute -left-[7px] top-[88px] h-10 w-[3px] rounded-l bg-ink-800 dark:bg-ink-600" />
            <span aria-hidden className="absolute -right-[7px] top-[108px] h-14 w-[3px] rounded-r bg-ink-800 dark:bg-ink-600" />

            <div className="relative overflow-hidden rounded-[34px] bg-white dark:bg-ink-900">
              {/* status bar */}
              <div className="relative flex items-center justify-between px-5 pb-1 pt-2.5 text-[10px] font-semibold text-ink-800 dark:text-ink-100">
                <span className="tabular-nums">9:41</span>
                <span aria-hidden className="absolute left-1/2 top-2 h-[15px] w-14 -translate-x-1/2 rounded-full bg-ink-900 dark:bg-black" />
                <span className="flex items-center gap-1">
                  <Icon name="bolt" size={11} className="text-ink-700 dark:text-ink-200" />
                </span>
              </div>

              {/* restaurant header */}
              <div className="flex items-center justify-between px-3.5 pt-1.5">
                <div className="flex items-center gap-2">
                  <span className="grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-600 text-[10px] font-black text-white">L</span>
                  <div className="leading-tight">
                    <div className="text-[11px] font-bold text-ink-900 dark:text-ink-50">Lezzet Durağı</div>
                    <div className="text-[9px] text-ink-500 dark:text-ink-400">Masa 7 · QR Menü</div>
                  </div>
                </div>
                <span className="inline-flex items-center gap-1 rounded-full bg-success-500/10 px-1.5 py-0.5 text-[8px] font-bold text-success-600 dark:text-success-500">
                  <span className="h-1 w-1 rounded-full bg-success-500" /> Açık
                </span>
              </div>

              {/* category chips */}
              <div className="mt-2.5 flex gap-1.5 px-3.5">
                {["Tümü", "Burger", "Pizza", "İçecek"].map((t, i) => (
                  <span
                    key={t}
                    className={`rounded-lg px-2 py-1 text-[9px] font-semibold ${
                      i === 0 ? "bg-brand-500 text-white shadow-brand-sm" : "bg-ink-100 text-ink-500 dark:bg-white/[0.06] dark:text-ink-400"
                    }`}
                  >
                    {t}
                  </span>
                ))}
              </div>

              {/* menu items */}
              <div className="mt-2.5 space-y-2 px-3.5 pb-2">
                {MENU.map((it) => (
                  <div
                    key={it.name}
                    className={`relative flex items-center gap-2.5 rounded-xl border p-2 transition-colors ${
                      it.qty > 0 ? "border-brand-500/40 bg-brand-500/[0.04]" : "border-ink-200 dark:border-white/[0.06]"
                    }`}
                  >
                    <div className={`h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-gradient-to-br ${it.grad}`}>
                      <img
                        src={it.img}
                        alt={it.name}
                        loading="lazy"
                        draggable={false}
                        className="h-full w-full object-cover"
                        onError={(e) => { e.currentTarget.style.display = "none"; }}
                      />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-[11px] font-semibold text-ink-800 dark:text-ink-100">{it.name}</div>
                      <div className="text-[11px] font-bold text-brand-600 dark:text-brand-300">{it.price}</div>
                    </div>
                    <span
                      className={`grid h-6 w-6 shrink-0 place-items-center rounded-lg text-[11px] font-bold ${
                        it.qty > 0 ? "bg-brand-500 text-white shadow-brand-sm" : "bg-ink-100 text-ink-500 dark:bg-white/[0.06] dark:text-ink-300"
                      }`}
                    >
                      {it.qty > 0 ? it.qty : <Icon name="add" size={13} strokeWidth={2.5} />}
                    </span>
                  </div>
                ))}
              </div>

              {/* cart bar */}
              <div className="mx-3.5 mb-3 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-3 py-2 shadow-brand-sm">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-medium text-white/85">2 ürün · ₺630</span>
                  <span className="inline-flex items-center gap-1 text-[11px] font-bold text-white">
                    Sipariş Ver <Icon name="arrow" size={12} strokeWidth={2.5} />
                  </span>
                </div>
              </div>
            </div>
          </motion.div>

          {/* floating QR stand + scan ring */}
          <motion.div
            animate={reduce ? {} : { y: [0, -6, 0] }}
            transition={{ duration: 5, repeat: Infinity, ease: "easeInOut", delay: -1.5 }}
            className="absolute -left-12 top-10 z-20 hidden sm:block"
          >
            <div className="relative rounded-2xl border border-ink-200 bg-white p-2.5 shadow-soft dark:border-white/10 dark:bg-ink-800">
              <div className="grid h-14 w-14 grid-cols-3 grid-rows-3 gap-0.5">
                {[1, 1, 0, 1, 0, 1, 0, 1, 1, 1, 0, 1].slice(0, 9).map((on, i) => (
                  <span key={i} className={`rounded-[2px] ${on ? "bg-ink-900 dark:bg-white" : "bg-transparent"}`} />
                ))}
              </div>
              <div className="mt-1 text-center text-[7px] font-bold uppercase tracking-wider text-ink-500">Tara</div>
              {step === 0 && !reduce && (
                <motion.span
                  aria-hidden
                  className="absolute inset-0 rounded-2xl ring-2 ring-gold-400"
                  initial={{ opacity: 0.7, scale: 1 }}
                  animate={{ opacity: 0, scale: 1.25 }}
                  transition={{ duration: 1.4, repeat: Infinity, ease: "easeOut" }}
                />
              )}
            </div>
          </motion.div>

          {/* status toast (reacts to active step) */}
          <div className="absolute -right-6 bottom-16 z-20 w-[178px]">
            <AnimatePresence mode="wait">
              <motion.div
                key={step}
                initial={{ opacity: 0, y: 10, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: -8, scale: 0.95 }}
                transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
                className="flex items-center gap-2.5 rounded-2xl border border-ink-200 bg-white/95 p-2.5 shadow-soft backdrop-blur dark:border-white/10 dark:bg-ink-800/95"
              >
                <span
                  className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${
                    toast.tone === "gold"
                      ? "bg-gold-500/15 text-gold-600 dark:text-gold-400"
                      : "bg-brand-500/15 text-brand-600 dark:text-brand-300"
                  }`}
                >
                  <Icon name={toast.icon} size={18} />
                </span>
                <div className="min-w-0">
                  <div className="truncate text-[11px] font-bold text-ink-900 dark:text-ink-50">{toast.label}</div>
                  <div className="truncate text-[9px] text-ink-500 dark:text-ink-400">{toast.sub}</div>
                </div>
              </motion.div>
            </AnimatePresence>
          </div>
        </div>
      </div>
    </motion.div>
  );
}

export default QrOrderShowcase;
