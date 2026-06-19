import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";
import { Logo } from "@/components/ui/Logo";
import type { Theme } from "@/lib/useTheme";

/* ================================================================
   QORDY — Unified marketing navbar

   One-page landing: nav links scroll to in-page section anchors.
   Separate routes: /gizlilik  /kullanim-sartlari  /login  /register
   ================================================================ */

type DropItem = {
  label: string;
  to: string;
  desc: string;
  icon: React.ComponentProps<typeof Icon>["name"];
};
type NavEntry = { label: string; to?: string; items?: DropItem[] };

const NAV: NavEntry[] = [
  {
    label: "Ürünler",
    items: [
      { label: "QR Menü & Sipariş", to: "/#qr", desc: "Temassız dijital menü", icon: "qr" },
      { label: "Mutfak Ekranı (KDS)", to: "/#pos", desc: "Anlık sipariş akışı", icon: "kitchen" },
      { label: "Garson El Terminali", to: "/#pos", desc: "Hızlı masa & tahsilat", icon: "pos" },
      { label: "Stok, Finans & Rapor", to: "/#moduller", desc: "Maliyet ve kâr kontrolü", icon: "chart" },
    ],
  },
  { label: "Fiyatlar", to: "/#fiyat" },
  { label: "Hakkımızda", to: "/#hakkimizda" },
  { label: "İletişim", to: "/#iletisim" },
];

export function MarketingNavbar({
  theme = "light",
  onToggleTheme,
}: {
  theme?: Theme;
  onToggleTheme?: () => void;
}) {
  const [scrolled, setScrolled] = useState(false);
  const [openMenu, setOpenMenu] = useState(false);
  const [active, setActive] = useState<string | null>(null);
  const [mobileGroup, setMobileGroup] = useState<string | null>("Ürünler");
  const location = useLocation();
  const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const logoVariant = theme === "dark" ? "light" : "dark";

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Body scroll lock while the mobile drawer is open
  useEffect(() => {
    document.body.style.overflow = openMenu ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [openMenu]);

  // Close everything on navigation
  useEffect(() => {
    setOpenMenu(false);
    setActive(null);
  }, [location.pathname, location.hash]);

  // Escape closes drawer / dropdown
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpenMenu(false);
        setActive(null);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const openDrop = (label: string) => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    setActive(label);
  };
  const scheduleClose = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setActive(null), 120);
  };

  const triggerCls = (isActive: boolean) =>
    `inline-flex items-center gap-1 px-3.5 py-2 text-sm font-medium rounded-lg transition-colors ${
      isActive
        ? "text-brand-600 dark:text-white"
        : "text-ink-600 hover:text-brand-600 dark:text-ink-200 dark:hover:text-white"
    }`;

  return (
    <header className="fixed inset-x-0 top-0 z-50">
      {/* Navbar bar — z-[60] within the header stacking context so it renders
          above the mobile drawer (z-[55]) and the backdrop (z-40). The
          navbar-safe-top class adds env(safe-area-inset-top) padding so the
          logo/hamburger stay below the notch on iPhones. */}
      <motion.div
        initial={{ y: -20, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
        className={`navbar-safe-top relative z-[60] transition-all duration-500 ${
          scrolled
            ? "bg-white/90 dark:bg-ink-950/90 backdrop-blur-2xl border-b border-ink-200/70 dark:border-white/[0.06] shadow-[0_8px_30px_-18px_rgba(15,18,40,0.25)] dark:shadow-none"
            : "bg-transparent border-b border-transparent"
        }`}
      >
        <div className="container-q flex items-center justify-between h-16 md:h-[72px]">
          {/* Logo — shrink-0 prevents it from squeezing on very narrow screens */}
          <Logo asLink variant={logoVariant} className={`shrink-0 w-auto transition-all ${scrolled ? "h-7" : "h-7 md:h-8"}`} />

          {/* Desktop nav */}
          <nav className="hidden lg:flex items-center gap-1" aria-label="Ana menü" onMouseLeave={scheduleClose}>
            {NAV.map((entry) => {
              if (entry.items) {
                const isOpen = active === entry.label;
                return (
                  <div key={entry.label} className="relative" onMouseEnter={() => openDrop(entry.label)}>
                    <button
                      type="button"
                      aria-expanded={isOpen}
                      aria-haspopup="true"
                      onClick={() => setActive((a) => (a === entry.label ? null : entry.label))}
                      className={triggerCls(isOpen)}
                    >
                      {entry.label}
                      <Icon name="chevronDown" size={14} className={`transition-transform ${isOpen ? "rotate-180" : ""}`} />
                    </button>

                    <AnimatePresence>
                      {isOpen && (
                        <motion.div
                          initial={{ opacity: 0, y: 8 }}
                          animate={{ opacity: 1, y: 0 }}
                          exit={{ opacity: 0, y: 6 }}
                          transition={{ duration: 0.18, ease: [0.16, 1, 0.3, 1] }}
                          className="absolute left-0 top-full pt-3 w-[320px]"
                          onMouseEnter={() => openDrop(entry.label)}
                        >
                          <div className="rounded-2xl border bg-white border-ink-200 shadow-[0_24px_60px_-24px_rgba(15,18,40,0.4)] dark:bg-ink-900 dark:border-white/[0.08] p-2">
                            {entry.items.map((it) => (
                              <Link
                                key={it.label}
                                to={it.to}
                                className="flex items-start gap-3 rounded-xl px-3 py-2.5 hover:bg-ink-50 dark:hover:bg-white/[0.05] transition-colors group/item"
                              >
                                <span className="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-500/12 text-brand-600 dark:text-brand-300 group-hover/item:bg-brand-600 group-hover/item:text-white transition-colors">
                                  <Icon name={it.icon} size={17} />
                                </span>
                                <span className="min-w-0">
                                  <span className="block text-sm font-semibold text-ink-800 dark:text-ink-100">{it.label}</span>
                                  <span className="block text-xs text-ink-500 dark:text-ink-400">{it.desc}</span>
                                </span>
                              </Link>
                            ))}
                            <Link
                              to="/#moduller"
                              className="mt-1 flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-brand-600 dark:text-brand-300 hover:bg-brand-50 dark:hover:bg-white/[0.05] transition-colors"
                            >
                              Tüm özellikleri keşfet
                              <Icon name="arrow" size={16} />
                            </Link>
                          </div>
                        </motion.div>
                      )}
                    </AnimatePresence>
                  </div>
                );
              }
              return (
                <NavLink
                  key={entry.to}
                  to={entry.to!}
                  className={({ isActive }) => triggerCls(isActive)}
                  onMouseEnter={() => openDrop("__none__")}
                >
                  {entry.label}
                </NavLink>
              );
            })}
          </nav>

          {/* Right actions — gap shrinks on very small screens to keep row compact */}
          <div className="flex items-center gap-1.5 sm:gap-2 md:gap-3">
            {onToggleTheme && <ThemeToggle theme={theme} onToggle={onToggleTheme} />}
            <Link
              to="/login"
              className="hidden md:inline-flex items-center rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 hover:border-brand-300 hover:text-brand-600 dark:bg-white/[0.05] dark:border-white/[0.12] dark:text-ink-100 dark:hover:border-white/25 transition-colors"
            >
              Giriş Yap
            </Link>
            <Link
              to="/register"
              className="hidden sm:inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 md:px-4 md:py-2.5 text-sm font-semibold text-white bg-gradient-to-br from-brand-500 to-brand-600 shadow-[0_10px_24px_-12px_rgba(99,102,241,0.7)] hover:-translate-y-0.5 transition-all"
            >
              Ücretsiz Başla
              <Icon name="arrow" size={15} />
            </Link>
            {/* Hamburger — always on top of the mobile drawer (z-[60]) via parent */}
            <button
              type="button"
              className="lg:hidden grid h-10 w-10 place-items-center rounded-xl text-ink-700 dark:text-ink-100 hover:bg-ink-100 dark:hover:bg-white/[0.06] transition-colors active:scale-95"
              onClick={() => setOpenMenu((v) => !v)}
              aria-label={openMenu ? "Menüyü kapat" : "Menüyü aç"}
              aria-expanded={openMenu}
            >
              <Icon name={openMenu ? "close" : "menu"} size={22} />
            </button>
          </div>
        </div>
      </motion.div>

      {/* ── Mobile drawer ───────────────────────────────────────────────
           z-[55]: sits above the backdrop (z-40) but BELOW the navbar bar
           (z-[60]) so the hamburger/close button in the bar is always
           visible and tappable above the drawer.

           The drawer starts at top-0 and mirrors the safe-area + h-16
           header row so the top of the drawer content aligns perfectly
           with the bottom of the navbar bar on every device, including
           iPhones with a Dynamic Island or notch.
          ────────────────────────────────────────────────────────────── */}
      <AnimatePresence>
        {openMenu && (
          <>
            {/* Backdrop — closes drawer when tapped on tablet/large phone */}
            <motion.button
              type="button"
              aria-label="Menüyü kapat"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.22 }}
              onClick={() => setOpenMenu(false)}
              className="fixed inset-0 z-40 lg:hidden bg-ink-950/40 backdrop-blur-[2px]"
            />

            {/* Drawer panel */}
            <motion.div
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
              className="fixed inset-x-0 top-0 z-[55] lg:hidden max-h-[100dvh] overflow-y-auto bg-white dark:bg-ink-950 shadow-soft-lg"
            >
              {/* Drawer header — mirrors navbar-safe-top + h-16 so it aligns
                  with the visible bar above (which renders at z-[60]). */}
              <div
                className="navbar-safe-top border-b border-ink-100 dark:border-white/[0.05]"
              >
                <div className="flex items-center justify-between px-5 h-16">
                  <Logo asLink variant={logoVariant} className="h-7 w-auto shrink-0" />
                  <button
                    type="button"
                    onClick={() => setOpenMenu(false)}
                    className="grid h-10 w-10 place-items-center rounded-xl text-ink-700 dark:text-ink-100 hover:bg-ink-100 dark:hover:bg-white/[0.06] transition-colors active:scale-95"
                    aria-label="Menüyü kapat"
                  >
                    <Icon name="close" size={20} />
                  </button>
                </div>
              </div>

              {/* Nav items */}
              <nav className="flex flex-col px-5 pt-2" aria-label="Mobil menü">
                {NAV.map((entry) => {
                  if (entry.items) {
                    const expanded = mobileGroup === entry.label;
                    return (
                      <div key={entry.label} className="border-b border-ink-100 dark:border-white/[0.05]">
                        <button
                          type="button"
                          onClick={() => setMobileGroup((g) => (g === entry.label ? null : entry.label))}
                          className="w-full flex items-center justify-between py-4 text-[15px] font-semibold text-ink-900 dark:text-ink-50"
                          aria-expanded={expanded}
                        >
                          {entry.label}
                          <Icon name="chevronDown" size={16} className={`transition-transform text-ink-400 ${expanded ? "rotate-180" : ""}`} />
                        </button>
                        <AnimatePresence initial={false}>
                          {expanded && (
                            <motion.div
                              initial={{ height: 0, opacity: 0 }}
                              animate={{ height: "auto", opacity: 1 }}
                              exit={{ height: 0, opacity: 0 }}
                              transition={{ duration: 0.22 }}
                              className="overflow-hidden"
                            >
                              <div className="pb-3 space-y-0.5">
                                {entry.items.map((it) => (
                                  <Link
                                    key={it.label}
                                    to={it.to}
                                    onClick={() => setOpenMenu(false)}
                                    className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-white/[0.05] transition-colors"
                                  >
                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-500/[0.12] text-brand-600 dark:text-brand-300">
                                      <Icon name={it.icon} size={15} />
                                    </span>
                                    <span>
                                      <span className="block text-[14px] font-semibold">{it.label}</span>
                                      <span className="block text-[12px] text-ink-500 dark:text-ink-400">{it.desc}</span>
                                    </span>
                                  </Link>
                                ))}
                              </div>
                            </motion.div>
                          )}
                        </AnimatePresence>
                      </div>
                    );
                  }
                  return (
                    <Link
                      key={entry.to}
                      to={entry.to!}
                      onClick={() => setOpenMenu(false)}
                      className="py-4 text-[15px] font-semibold text-ink-900 dark:text-ink-50 border-b border-ink-100 dark:border-white/[0.05] hover:text-brand-600 dark:hover:text-brand-300 transition-colors"
                    >
                      {entry.label}
                    </Link>
                  );
                })}
              </nav>

              {/* CTA area */}
              <div className="px-5 mt-6 mb-8 flex flex-col gap-3">
                <Link
                  to="/login"
                  onClick={() => setOpenMenu(false)}
                  className="flex items-center justify-center rounded-xl border border-ink-200 bg-white px-5 py-3 text-sm font-semibold text-ink-700 hover:border-brand-300 hover:text-brand-600 dark:bg-white/[0.05] dark:border-white/[0.12] dark:text-ink-100 dark:hover:border-white/25 transition-colors"
                >
                  Giriş Yap
                </Link>
                <Link
                  to="/register"
                  onClick={() => setOpenMenu(false)}
                  className="flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold text-white bg-gradient-to-br from-brand-500 to-brand-600 shadow-[0_10px_24px_-12px_rgba(99,102,241,0.7)] hover:-translate-y-0.5 transition-all"
                >
                  Ücretsiz Başla
                  <Icon name="arrow" size={15} />
                </Link>

                {/* Theme toggle in drawer footer */}
                {onToggleTheme && (
                  <button
                    type="button"
                    onClick={onToggleTheme}
                    className="flex items-center justify-center gap-2 rounded-xl border border-ink-200 dark:border-white/[0.10] px-5 py-3 text-sm font-medium text-ink-600 dark:text-ink-300 hover:bg-ink-50 dark:hover:bg-white/[0.04] transition-colors"
                  >
                    <Icon name={theme === "dark" ? "sun" : "moon"} size={15} />
                    {theme === "dark" ? "Aydınlık mod" : "Karanlık mod"}
                  </button>
                )}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </header>
  );
}

function ThemeToggle({ theme, onToggle }: { theme: Theme; onToggle: () => void }) {
  return (
    <button
      type="button"
      onClick={onToggle}
      aria-label={theme === "dark" ? "Aydınlık moda geç" : "Karanlık moda geç"}
      className="grid h-10 w-10 place-items-center rounded-xl border border-ink-200 bg-white text-ink-600 hover:text-brand-600 hover:border-brand-300 dark:bg-white/[0.05] dark:border-white/[0.12] dark:text-gold-400 dark:hover:border-white/25 transition-colors"
    >
      <Icon name={theme === "dark" ? "sun" : "moon"} size={18} />
    </button>
  );
}

export default MarketingNavbar;
