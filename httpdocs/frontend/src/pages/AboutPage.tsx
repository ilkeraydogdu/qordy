import { motion } from "framer-motion";
import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { Icon } from "@/components/ui/Icon";
import { PageHero, SectionHeader, MarketingCard, StatBand, CtaBand } from "@/components/marketing";
import { trialLabel } from "@/lib/trial";

const VALUES = [
  {
    icon: "shield" as const,
    title: "Güvenilirlik",
    desc: "Banka seviyesi şifreleme, günlük yedekleme ve %99.9 uptime taahhüdü.",
  },
  {
    icon: "bolt" as const,
    title: "Hız",
    desc: "Bulut mimarisi sayesinde anında kurulum, gerçek zamanlı senkronizasyon.",
  },
  {
    icon: "users" as const,
    title: "İş ortaklığı",
    desc: "Satış sonrası onboarding, eğitim ve uçtan uca Türkçe destek ekibi.",
  },
];

const STORY = [
  {
    icon: "qr" as const,
    title: "Sahadan doğdu",
    desc: "Qordy, gerçek restoran ve kafelerin günlük operasyon sorunlarını çözmek için sahada geliştirildi. Her özellik bir işletmenin gerçek ihtiyacından çıktı.",
  },
  {
    icon: "chart" as const,
    title: "Tek platform",
    desc: "QR menü, adisyon, mutfak ekranı, stok, personel ve finansal raporlama tek bir entegre deneyimde birleşir — dağınık araçlar yerine tek panel.",
  },
  {
    icon: "globe" as const,
    title: "Ölçeklenebilir",
    desc: "Tek masadan binlerce şubeye kadar büyüyen altyapı. İşletmeniz büyüdükçe Qordy sizinle birlikte ölçeklenir.",
  },
];

export default function AboutPage() {
  return (
    <MarketingLayout
      path="/hakkimizda"
      breadcrumbs={[
        { name: "Ana Sayfa", path: "/" },
        { name: "Hakkımızda", path: "/hakkimizda" },
      ]}
    >
      <Breadcrumbs items={[{ label: "Ana Sayfa", href: "/" }, { label: "Hakkımızda" }]} />

      <PageHero
        eyebrow="Kurumsal"
        title={<>Restoran teknolojisinde <span className="gradient-text">güvenilir partneriniz</span>.</>}
        lead="QORDY Smart Restaurant Systems, restoran ve kafe işletmelerinin dijital dönüşümünü hızlandırmak için geliştirilmiş bulut tabanlı bir yönetim platformudur."
      />

      {/* Story */}
      <section className="section-q-tight">
        <div className="container-q">
          <div className="grid gap-5 md:grid-cols-3">
            {STORY.map((s, i) => (
              <motion.div
                key={s.title}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-50px" }}
                transition={{ duration: 0.5, delay: i * 0.06 }}
                className="rounded-[22px] border border-ink-200 bg-white p-7 shadow-soft"
              >
                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100">
                  <Icon name={s.icon} size={22} />
                </div>
                <h3 className="font-display text-lg font-semibold text-ink-900">{s.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-ink-600">{s.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Values */}
      <section className="section-q">
        <div className="container-q">
          <SectionHeader
            eyebrow="Değerlerimiz"
            title="Neye göre çalışıyoruz?"
            lead="Her özellik sahadaki gerçek ihtiyaçlardan doğar; üç ilke etrafında."
            className="mb-14"
          />
          <div className="grid gap-5 md:grid-cols-3">
            {VALUES.map((v, i) => (
              <MarketingCard key={v.title} icon={v.icon} title={v.title} index={i}>
                {v.desc}
              </MarketingCard>
            ))}
          </div>
        </div>
      </section>

      <StatBand />

      <CtaBand
        title="Birlikte büyüyelim"
        lead={`Demo talep edin veya ${trialLabel()} deneme ile QORDY'yi kendi işletmenizde test edin.`}
        primaryLabel="Ücretsiz Başla"
        secondaryLabel="Bize Ulaşın"
      />
    </MarketingLayout>
  );
}
