/**
 * Qordy — Features Page
 *
 * Özellikler sayfası: hero + öne çıkan modüller (görsel showcase) +
 * istatistik + tüm özellikler grid + CTA. Landing ile aynı tasarım
 * dilini paylaşır: ortak PageHero, SectionHeader, MarketingCard,
 * StatBand ve CtaBand bileşenleri. (Kırık görsel referansları
 * kaldırıldı — showcase artık tamamen ikon tabanlı.)
 */
import { motion } from "framer-motion";
import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { Icon } from "@/components/ui/Icon";
import { LinkButton } from "@/components/ui/Button";
import { PageHero, SectionHeader, MarketingCard, StatBand, CtaBand } from "@/components/marketing";

type FeatureIcon = "qr" | "pos" | "kitchen" | "stock" | "finance" | "chart" | "users" | "calendar";

const FEATURES: Array<{ icon: FeatureIcon; title: string; description: string; tag?: string }> = [
  {
    icon: "qr",
    title: "QR Menü & Sipariş",
    description: "Müşteri masadaki QR kodu okutur, menüyü görür, sipariş verir. Bekleme süresi sıfıra iner, hata oranı düşer.",
    tag: "En popüler",
  },
  {
    icon: "kitchen",
    title: "Mutfak Ekranı (KDS)",
    description: "Siparişler anlık mutfağa yansır, hazırlık süreleri ölçülür, eksik ürün unutulmaz. Servis hızlanır, müşteri memnuniyeti artar.",
  },
  {
    icon: "pos",
    title: "Garson El Terminali",
    description: "Hafif, ergonomik, dayanıklı. Masa yönetimi, bölünmüş hesap, hızlı tahsilat. Garsonlar müşteriyle ilgilenir, kağıt kalemle değil.",
  },
  {
    icon: "stock",
    title: "Stok Yönetimi",
    description: "Otomatik stok düşümü, kritik seviye uyarıları, tedarikçi yönetimi. Fire ve kaçak minimuma iner, kâr marjınız netleşir.",
  },
  {
    icon: "finance",
    title: "Finans & Kasa",
    description: "Günlük ciro, gider, kâr analizi. Kasa açılış-kapanış, nakit ve kart tahsilatları tek ekranda.",
  },
  {
    icon: "chart",
    title: "Gelişmiş Raporlar",
    description: "Şube karşılaştırma, ürün performansı, garson bazlı satış. Veriye dayalı kararlar alın.",
  },
  {
    icon: "users",
    title: "Müşteri Sadakati",
    description: "Puan, kampanya, doğum günü indirimi. Müşteriniz bir daha gelsin istiyorsanız doğru yerdesiniz.",
  },
  {
    icon: "calendar",
    title: "Rezervasyon Sistemi",
    description: "Online ve telefonla rezervasyon, masa planı, no-show takibi. Doluluğunuzu maksimize edin.",
  },
];

// Öne çıkan üç modül — ikon tabanlı, alternatif yerleşimli showcase.
const SHOWCASE: Array<{ icon: FeatureIcon; title: string; description: string; tag?: string; points: string[] }> = [
  {
    icon: "qr",
    title: "QR Menü & Sipariş",
    tag: "En popüler",
    description: "Masadaki QR'dan saniyeler içinde sipariş. Menünüzü anında güncelleyin, kampanyaları öne çıkarın, garson çağırmadan ödeme alın.",
    points: ["Temassız dijital menü", "Anlık menü güncelleme", "Masadan ödeme"],
  },
  {
    icon: "kitchen",
    title: "Mutfak Ekranı (KDS)",
    description: "Sipariş aynı saniye mutfak ekranında belirir. Hazırlık süreleri ölçülür, sıralanır ve hiçbir kalem unutulmaz.",
    points: ["Gerçek zamanlı kuyruk", "Hazırlık süresi takibi", "İstasyon bazlı yönlendirme"],
  },
  {
    icon: "pos",
    title: "Garson El Terminali",
    description: "Masa yönetimi, bölünmüş hesap ve hızlı tahsilat avuç içinde. Garsonlarınız kağıt kalemle değil, misafirle ilgilenir.",
    points: ["Masa & adisyon yönetimi", "Bölünmüş hesap", "Hızlı tahsilat"],
  },
];

function ShowcaseVisual({ icon }: { icon: FeatureIcon }) {
  return (
    <div className="relative aspect-[4/3] overflow-hidden rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white shadow-brand">
      <div aria-hidden className="absolute inset-0 bg-grid-pattern opacity-60" />
      <div aria-hidden className="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-brand-500/15 blur-2xl" />
      <div aria-hidden className="absolute -bottom-10 -left-8 h-40 w-40 rounded-full bg-gold-500/15 blur-2xl" />
      <div className="absolute inset-0 grid place-items-center">
        <motion.div
          initial={{ scale: 0.85, opacity: 0 }}
          whileInView={{ scale: 1, opacity: 1 }}
          viewport={{ once: true, margin: "-80px" }}
          transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          className="grid h-28 w-28 place-items-center rounded-[28px] bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-brand-lg"
        >
          <Icon name={icon} size={52} strokeWidth={1.5} />
        </motion.div>
      </div>
    </div>
  );
}

export default function FeaturesPage() {
  return (
    <MarketingLayout
      path="/ozellikler"
      breadcrumbs={[{ name: "Ana Sayfa", path: "/" }, { name: "Özellikler", path: "/ozellikler" }]}
    >
      <Breadcrumbs items={[{ label: "Ana Sayfa", href: "/" }, { label: "Özellikler" }]} />

      <PageHero
        eyebrow="Ürün Özellikleri"
        title={<>Restoran operasyonunun <span className="gradient-text">her adımı</span> tek platformda.</>}
        lead="QR menüden mutfak ekranına, stok yönetiminden çoklu şube raporlamasına kadar Qordy, işletmenizin ihtiyaç duyduğu tüm modülleri kurumsal ölçekte sunar."
        actions={
          <>
            <LinkButton href="/register" variant="primary" size="lg">
              Ücretsiz Deneyin
              <Icon name="arrow" className="ml-2 h-4 w-4" />
            </LinkButton>
            <LinkButton href="/fiyatlandirma" variant="ghost" size="lg">
              Paketleri İnceleyin
            </LinkButton>
          </>
        }
      />

      {/* Showcase — öne çıkan modüller */}
      <section className="section-q-tight">
        <div className="container-q space-y-20 md:space-y-24">
          {SHOWCASE.map((f, i) => {
            const reverse = i % 2 === 1;
            return (
              <div key={f.title} id={f.icon} className="grid scroll-mt-28 items-center gap-12 md:grid-cols-2">
                <motion.div
                  initial={{ opacity: 0, x: reverse ? 40 : -40 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true, margin: "-100px" }}
                  transition={{ duration: 0.6 }}
                  className={reverse ? "md:order-2" : ""}
                >
                  <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100">
                    <Icon name={f.icon} className="h-6 w-6" />
                  </div>
                  <h2 className="font-display text-display-md font-bold tracking-tight text-ink-900">
                    {f.title}
                    {f.tag && (
                      <span className="ml-3 align-middle rounded-full bg-accent-500/10 px-2 py-1 text-xs font-bold uppercase tracking-wider text-accent-600">
                        {f.tag}
                      </span>
                    )}
                  </h2>
                  <p className="mt-4 text-lg leading-relaxed text-ink-600">{f.description}</p>
                  <ul className="mt-6 space-y-2.5">
                    {f.points.map((p) => (
                      <li key={p} className="flex items-center gap-2.5 text-sm font-medium text-ink-700">
                        <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-success-500/12 text-success-600">
                          <Icon name="check" size={12} strokeWidth={2.5} />
                        </span>
                        {p}
                      </li>
                    ))}
                  </ul>
                </motion.div>
                <motion.div
                  initial={{ opacity: 0, scale: 0.95 }}
                  whileInView={{ opacity: 1, scale: 1 }}
                  viewport={{ once: true, margin: "-100px" }}
                  transition={{ duration: 0.7, delay: 0.1 }}
                  className={reverse ? "md:order-1" : ""}
                >
                  <ShowcaseVisual icon={f.icon} />
                </motion.div>
              </div>
            );
          })}
        </div>
      </section>

      <StatBand />

      {/* Tüm özellikler grid */}
      <section id="tum-ozellikler" className="section-q scroll-mt-28">
        <div className="container-q">
          <SectionHeader
            eyebrow="Tüm Özellikler"
            title="İhtiyacınız olan her şey."
            lead="Modüler yapı: ihtiyacınız olmayanı açmazsınız, büyüdükçe eklersiniz."
            className="mb-14"
          />
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {FEATURES.map((f, i) => (
              <MarketingCard key={f.title} icon={f.icon} title={f.title} tag={f.tag} index={i}>
                {f.description}
              </MarketingCard>
            ))}
          </div>
        </div>
      </section>

      <CtaBand
        title={<>Tüm özellikleri <span className="text-gold-400">14 gün ücretsiz</span> deneyin.</>}
        lead="Kurulum yok, taahhüt yok. Sadece işinizi büyütün."
      />
    </MarketingLayout>
  );
}
