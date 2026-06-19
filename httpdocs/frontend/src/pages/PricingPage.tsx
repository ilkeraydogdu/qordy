/**
 * Qordy — Pricing Page
 *
 * Fiyatlandırma sayfası: hero + paketler + karşılaştırma + SSS + CTA.
 * Tutarlılık için landing ile aynı marketing bileşenlerini kullanır
 * (PageHero, SectionHeader, CtaBand).
 */
import { useEffect, useState } from "react";
import { motion } from "framer-motion";
import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { Icon } from "@/components/ui/Icon";
import { LinkButton } from "@/components/ui/Button";
import { PageHero, SectionHeader, CtaBand } from "@/components/marketing";
import { api, type Package } from "@/lib/api";
import { getBootstrap } from "@/lib/bootstrap";

type Pkg = Package;
type BillingPeriod = "monthly" | "yearly";

const FALLBACK: Pkg[] = [
  {
    package_id: "starter",
    name: "Başlangıç",
    description: "Küçük işletmeler için ideal başlangıç paketi.",
    price_monthly: 899,
    price_yearly: 8990,
    features_array: ["Sınırsız Menü Yönetimi", "QR Kod ile Sipariş", "Temel Raporlama", "1 Şube Desteği", "7/24 Canlı Destek"],
    is_featured: false,
  },
  {
    package_id: "professional",
    name: "Profesyonel",
    description: "Büyüyen işletmeler için gelişmiş özellikler.",
    price_monthly: 1999,
    price_yearly: 19990,
    features_array: ["Başlangıç paketinin tüm özellikleri", "Sınırsız Şube Yönetimi", "Garson POS", "Stok Yönetimi", "Gelişmiş Raporlar", "Sadakat Programı"],
    is_featured: true,
  },
  {
    package_id: "enterprise",
    name: "Kurumsal",
    description: "Zincir işletmeler için özel çözümler.",
    price_monthly: 3999,
    price_yearly: 39990,
    features_array: ["Profesyonel paketinin tüm özellikleri", "ERP Entegrasyonları", "Çoklu Marka Yönetimi", "Dedike Müşteri Temsilcisi", "SLA Destek", "Özel Eğitim"],
    is_featured: false,
  },
];

const COMPARISON: Array<{ feature: string; starter: boolean | string; professional: boolean | string; enterprise: boolean | string }> = [
  { feature: "Sınırsız menü", starter: true, professional: true, enterprise: true },
  { feature: "QR ile sipariş", starter: true, professional: true, enterprise: true },
  { feature: "Şube sayısı", starter: "1", professional: "Sınırsız", enterprise: "Sınırsız" },
  { feature: "Garson POS", starter: false, professional: true, enterprise: true },
  { feature: "Stok yönetimi", starter: "Temel", professional: "Gelişmiş", enterprise: "Gelişmiş + ERP" },
  { feature: "Mutfak ekranı", starter: false, professional: true, enterprise: true },
  { feature: "Sadakat programı", starter: false, professional: true, enterprise: true },
  { feature: "Gelişmiş raporlar", starter: "Temel", professional: "Gelişmiş", enterprise: "Custom" },
  { feature: "API erişimi", starter: false, professional: true, enterprise: true },
  { feature: "Özel entegrasyonlar", starter: false, professional: false, enterprise: true },
  { feature: "Dedike destek", starter: false, professional: false, enterprise: true },
  { feature: "SLA garantisi", starter: "7/24", professional: "7/24", enterprise: "4 saat yanıt" },
];

function fmt(n: number): string {
  if (n <= 0) return "Teklif al";
  return "₺" + n.toLocaleString("tr-TR");
}

export default function PricingPage() {
  const [period, setPeriod] = useState<BillingPeriod>("monthly");
  const [packages, setPackages] = useState<Pkg[]>(FALLBACK);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    const boot = getBootstrap();
    if (boot?.packages?.length) {
      setPackages(boot.packages as Pkg[]);
      setLoading(false);
      return;
    }
    api.getPackages()
      .then((r) => alive && r.packages?.length && setPackages(r.packages as Pkg[]))
      .catch(() => {})
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, []);

  const showPrice = (p: Pkg): number => {
    const m = Number(p.price_monthly ?? 0);
    const y = Math.round(Number(p.price_yearly ?? 0) / 12);
    return period === "monthly" ? m : y;
  };

  return (
    <MarketingLayout
      path="/fiyatlandirma"
      breadcrumbs={[{ name: "Ana Sayfa", path: "/" }, { name: "Fiyatlandırma", path: "/fiyatlandirma" }]}
    >
      <Breadcrumbs items={[{ label: "Ana Sayfa", href: "/" }, { label: "Fiyatlandırma" }]} />

      <PageHero
        eyebrow="Şeffaf Fiyatlandırma"
        title={<>İşletmenize uygun <span className="gradient-text">planı</span> seçin.</>}
        lead="14 gün ücretsiz deneyin, kredi kartı gerekmez. İstediğiniz zaman iptal edin, yükseltin veya düşürün."
      >
        <div className="mt-9 flex items-center justify-center">
          <div className="inline-flex rounded-full bg-brand-50 p-1 ring-1 ring-brand-100">
            {(["monthly", "yearly"] as const).map((p) => (
              <button
                key={p}
                onClick={() => setPeriod(p)}
                className={`rounded-full px-5 py-2 text-sm font-medium transition-all ${
                  period === p ? "bg-white text-ink-900 shadow-soft-sm" : "text-ink-600 hover:text-ink-900"
                }`}
                aria-pressed={period === p}
              >
                {p === "monthly" ? "Aylık" : "Yıllık"}
                {p === "yearly" && (
                  <span className="ml-2 inline-block rounded-full bg-success-500 px-1.5 py-0.5 text-[10px] font-bold text-white">-17%</span>
                )}
              </button>
            ))}
          </div>
        </div>
      </PageHero>

      {/* Paket kartları */}
      <section className="section-q-tight">
        <div className="container-q">
          {loading ? (
            <div className="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
              {[0, 1, 2].map((i) => <div key={i} className="card h-96 animate-pulse" />)}
            </div>
          ) : (
            <div className="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
              {packages.slice(0, 3).map((p) => {
                const price = showPrice(p);
                const featured = p.is_featured;
                return (
                  <div key={p.package_id} className={`card relative flex h-full flex-col ${featured ? "ring-2 ring-brand-600 shadow-brand-lg lg:scale-[1.04] lg:-translate-y-2" : ""}`}>
                    {featured && (
                      <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-accent-500 to-accent-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-soft">
                        En Popüler
                      </div>
                    )}
                    <h3 className="font-display text-2xl font-bold text-ink-900">{p.name}</h3>
                    {p.description && <p className="mt-2 text-sm leading-relaxed text-ink-600">{p.description}</p>}
                    <div className="mb-6 mt-6">
                      {price <= 0 ? (
                        <div className="font-display text-3xl font-bold text-ink-900">Teklif al</div>
                      ) : (
                        <>
                          <div className="flex items-baseline gap-1">
                            <span className="font-display text-4xl font-bold tabular-nums text-ink-900">{fmt(price)}</span>
                            <span className="text-sm text-ink-500">/ ay</span>
                          </div>
                          {period === "yearly" && Number(p.price_yearly ?? 0) > 0 && (
                            <div className="mt-1 text-xs text-ink-500">
                              Yıllık {fmt(Number(p.price_yearly!))} · <span className="font-medium text-success-500">2 ay bedava</span>
                            </div>
                          )}
                        </>
                      )}
                    </div>
                    <ul className="mb-8 flex-1 space-y-2.5">
                      {(p.features_array ?? []).slice(0, 8).map((f, j) => {
                        const label = typeof f === "string" ? f : f.name ?? f.title ?? "";
                        return (
                          <li key={j} className="flex items-start gap-2 text-sm text-ink-700">
                            <span className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-success-50 text-success-500">
                              <Icon name="check" className="h-3 w-3" />
                            </span>
                            <span>{label}</span>
                          </li>
                        );
                      })}
                    </ul>
                    <LinkButton
                      href={price <= 0 ? "/iletisim" : "/register"}
                      variant={featured ? "primary" : "ghost"}
                      className="w-full justify-center"
                    >
                      {price <= 0 ? "İletişime Geç" : "Bu Planı Seç"}
                    </LinkButton>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>

      {/* Karşılaştırma tablosu */}
      <section className="section-q-tight">
        <div className="container-q">
          <div className="mx-auto max-w-4xl">
            <SectionHeader eyebrow="Karşılaştırma" title="Hangi plan size uygun?" className="mb-10" />
            <div className="card overflow-hidden p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-brand-100 bg-brand-50/50">
                      <th className="p-4 text-left font-semibold text-ink-700">Özellik</th>
                      <th className="p-4 font-semibold text-ink-700">Başlangıç</th>
                      <th className="bg-brand-50 p-4 font-semibold text-ink-900">Profesyonel</th>
                      <th className="p-4 font-semibold text-ink-700">Kurumsal</th>
                    </tr>
                  </thead>
                  <tbody>
                    {COMPARISON.map((row, i) => (
                      <tr key={i} className="border-b border-brand-50 last:border-0">
                        <td className="p-4 text-ink-700">{row.feature}</td>
                        <td className="p-4 text-center">
                          {typeof row.starter === "boolean" ? (
                            row.starter ? <Icon name="check" className="mx-auto h-4 w-4 text-success-500" /> : <span className="text-ink-300">—</span>
                          ) : (
                            <span className="font-medium text-ink-700">{row.starter}</span>
                          )}
                        </td>
                        <td className="bg-brand-50/30 p-4 text-center">
                          {typeof row.professional === "boolean" ? (
                            row.professional ? <Icon name="check" className="mx-auto h-4 w-4 text-success-500" /> : <span className="text-ink-300">—</span>
                          ) : (
                            <span className="font-semibold text-ink-900">{row.professional}</span>
                          )}
                        </td>
                        <td className="p-4 text-center">
                          {typeof row.enterprise === "boolean" ? (
                            row.enterprise ? <Icon name="check" className="mx-auto h-4 w-4 text-success-500" /> : <span className="text-ink-300">—</span>
                          ) : (
                            <span className="font-medium text-ink-700">{row.enterprise}</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* SSS */}
      <section className="section-q-tight">
        <div className="container-q max-w-3xl">
          <FaqList />
        </div>
      </section>

      <CtaBand
        title={<>14 gün ücretsiz deneyin. <span className="text-gold-400">Taahhüt yok.</span></>}
        lead="Kurulum bizden, eğitim bizden. İstediğiniz zaman iptal edin."
      />
    </MarketingLayout>
  );
}

function FaqList() {
  const FAQS = [
    { q: "Kurulum ne kadar sürer?", a: "Çoğu işletme 24 saat içinde yayında olur. Menünüzü biz yükleriz, QR kodlarınızı basarız, ekibinizi uzaktan eğitiriz." },
    { q: "Mevcut POS'umla entegre olur mu?", a: "Evet. Yaygın POS yazıcıları ve ödeme terminalleriyle doğrudan entegre çalışır." },
    { q: "İnternet kesilirse ne olur?", a: "Siparişler offline kuyruğa alınır, internet geri gelince senkronize olur." },
    { q: "Sözleşme taahhüdü var mı?", a: "Hayır. Aylık abonelik, istediğiniz zaman iptal edebilirsiniz." },
  ];
  const [open, setOpen] = useState<number | null>(0);
  return (
    <>
      <SectionHeader eyebrow="Sık Sorulan Sorular" title="Aklınıza takılanlar" className="mb-10" />
      <div className="space-y-3">
        {FAQS.map((f, i) => {
          const isOpen = open === i;
          return (
            <div key={f.q} className="card-glass overflow-hidden">
              <button
                onClick={() => setOpen(isOpen ? null : i)}
                className="flex w-full items-center justify-between gap-4 px-6 py-5 text-left"
                aria-expanded={isOpen}
              >
                <span className="font-display font-semibold text-ink-900">{f.q}</span>
                <motion.span animate={{ rotate: isOpen ? 180 : 0 }} transition={{ duration: 0.3 }}>
                  <Icon name="chevronDown" className="h-5 w-5 text-ink-500" />
                </motion.span>
              </button>
              <motion.div
                initial={false}
                animate={{ height: isOpen ? "auto" : 0, opacity: isOpen ? 1 : 0 }}
                transition={{ duration: 0.3 }}
                className="overflow-hidden"
              >
                <p className="px-6 pb-5 leading-relaxed text-ink-600">{f.a}</p>
              </motion.div>
            </div>
          );
        })}
      </div>
    </>
  );
}
