import { Link } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";
import { Logo } from "@/components/ui/Logo";

/* ================================================================
   QORDY — Unified marketing footer (dark surface on every page)

   Every link points to a real route, in-page anchor or mailto.
   No placeholder columns to pages that don't exist.
   ================================================================ */

type FootLink = { label: string; to?: string; href?: string };

const columns: { title: string; links: FootLink[] }[] = [
  {
    title: "Ürün",
    links: [
      { label: "Özellikler", to: "/#moduller" },
      { label: "QR Menü & Sipariş", to: "/#qr" },
      { label: "Mutfak Ekranı", to: "/#pos" },
      { label: "Fiyatlandırma", to: "/#fiyat" },
    ],
  },
  {
    title: "Şirket",
    links: [
      { label: "Hakkımızda", to: "/#hakkimizda" },
      { label: "İletişim", to: "/#iletisim" },
      { label: "Ücretsiz Başla", to: "/register" },
      { label: "Giriş Yap", to: "/login" },
    ],
  },
  {
    title: "Destek & Yasal",
    links: [
      { label: "destek@qordy.com", href: "mailto:destek@qordy.com" },
      { label: "İletişim Formu", to: "/#iletisim" },
      { label: "Gizlilik Politikası", to: "/gizlilik" },
      { label: "Kullanım Şartları", to: "/kullanim-sartlari" },
    ],
  },
];

export function MarketingFooter() {
  return (
    <footer className="relative overflow-hidden bg-ink-950 pt-16 pb-8 border-t border-white/[0.06]">
      <div aria-hidden className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand-600/40 to-transparent" />
      <div aria-hidden className="absolute top-0 left-1/4 w-96 h-96 rounded-full bg-brand-600/[0.08] blur-3xl pointer-events-none" />

      <div className="container-q relative">
        <div className="grid gap-12 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]">
          <div className="space-y-5">
            <Logo asLink variant="light" className="h-9 w-auto" />
            <p className="text-sm text-ink-400 leading-relaxed max-w-xs">
              Restoran ve zincirler için akıllı işletim sistemi. QR menü, POS, mutfak,
              stok ve finansı tek panelde birleştirin.
            </p>
            <div className="flex items-center gap-3 pt-1">
              <Social label="Web sitemiz" href="https://qordy.com" icon="globe" />
              <Social label="LinkedIn" href="https://www.linkedin.com/company/qordy" icon="share" />
              <Social label="E-posta" href="mailto:info@qordy.com" icon="mail" />
            </div>
          </div>

          {columns.map((col) => (
            <div key={col.title}>
              <h4 className="text-[11px] font-mono font-bold uppercase tracking-[0.2em] text-brand-300/80">
                {col.title}
              </h4>
              <ul className="mt-5 space-y-3">
                {col.links.map((link) => (
                  <li key={link.label}>
                    {link.to ? (
                      <Link to={link.to} className="text-sm text-ink-400 hover:text-brand-300 transition-colors">
                        {link.label}
                      </Link>
                    ) : (
                      <a href={link.href} className="text-sm text-ink-400 hover:text-brand-300 transition-colors">
                        {link.label}
                      </a>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-14 pt-6 border-t border-white/[0.06] flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs text-ink-500">
          <span>© {new Date().getFullYear()} Qordy Smart Restaurant Systems. Tüm hakları saklıdır.</span>
          <div className="flex items-center gap-3 font-medium">
            <Link to="/gizlilik" className="hover:text-brand-300 transition-colors">Gizlilik</Link>
            <span className="opacity-30">·</span>
            <Link to="/kullanim-sartlari" className="hover:text-brand-300 transition-colors">Kullanım Şartları</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}

function Social({ label, href, icon }: { label: string; href: string; icon: React.ComponentProps<typeof Icon>["name"] }) {
  return (
    <a
      aria-label={label}
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="grid h-10 w-10 place-items-center rounded-full bg-white/[0.06] border border-white/10 text-ink-300 hover:text-white hover:bg-brand-600 hover:border-brand-600 transition-all"
    >
      <Icon name={icon} size={16} />
    </a>
  );
}

export default MarketingFooter;
