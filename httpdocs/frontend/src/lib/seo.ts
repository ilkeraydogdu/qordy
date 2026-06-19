import { getTrialDays } from "@/lib/trial";

export type SeoConfig = {
  title: string;
  description: string;
  path: string;
  robots?: string;
  jsonLd?: Record<string, unknown>[];
};

const SITE = "https://qordy.com";

function withTrialCopy(text: string, days: number): string {
  return text
    .replace(/14 Gün/gi, `${days} Gün`)
    .replace(/14 gün/gi, `${days} gün`);
}

export const seoRoutes: Record<string, SeoConfig> = {
  "/": {
    title: "QORDY — Restoran Yönetim Yazılımı · QR Menü, Adisyon ve Mutfak Ekranı",
    description:
      "QORDY ile restoranınızı baştan sona dijitalleştirin: QR menü, adisyon programı, mutfak ekranı (KDS), stok, rezervasyon ve raporlama tek platformda. 14 gün ücretsiz deneyin.",
    path: "/",
  },
  "/ozellikler": {
    title: "Özellikler — QR Menü, POS, Mutfak Ekranı, Stok · QORDY",
    description:
      "QR menü sistemi, adisyon/POS, mutfak ekranı (KDS), rezervasyon, çoklu şube ve stok yönetimi. QORDY ile restoran operasyonunu uçtan uca otomatikleştirin.",
    path: "/ozellikler",
  },
  "/features": {
    title: "Özellikler — QR Menü, POS, Mutfak Ekranı, Stok · QORDY",
    description:
      "QR menü sistemi, adisyon/POS, mutfak ekranı (KDS), rezervasyon, çoklu şube ve stok yönetimi. QORDY ile restoran operasyonunu uçtan uca otomatikleştirin.",
    path: "/features",
  },
  "/fiyatlandirma": {
    title: "Fiyatlandırma — Aylık ve Yıllık Paketler · QORDY Restoran Yazılımı",
    description:
      "QORDY restoran yönetim yazılımı paketleri: esnek aylık ve yıllık fiyatlandırma. 14 gün ücretsiz deneme, kredi kartı gerekmez, istediğiniz zaman iptal edin.",
    path: "/fiyatlandirma",
  },
  "/pricing": {
    title: "Fiyatlandırma — Aylık ve Yıllık Paketler · QORDY Restoran Yazılımı",
    description:
      "QORDY restoran yönetim yazılımı paketleri: esnek aylık ve yıllık fiyatlandırma. 14 gün ücretsiz deneme, kredi kartı gerekmez, istediğiniz zaman iptal edin.",
    path: "/fiyatlandirma",
  },
  "/hakkimizda": {
    title: "Hakkımızda — QORDY Smart Restaurant Systems",
    description:
      "QORDY, restoran ve kafe işletmelerinin dijital dönüşümünü hızlandıran Türkiye merkezli bulut tabanlı yönetim platformudur.",
    path: "/hakkimizda",
  },
  "/iletisim": {
    title: "İletişim — QORDY Destek ve Satış",
    description:
      "QORDY satış, destek ve demo talepleri için bizimle iletişime geçin. Uzman ekibimiz işletmenize özel çözüm önerir.",
    path: "/iletisim",
  },
  "/gizlilik": {
    title: "Gizlilik Politikası · QORDY",
    description: "QORDY gizlilik politikası ve kişisel verilerin korunması hakkında bilgiler.",
    path: "/gizlilik",
    robots: "index, follow",
  },
  "/kullanim-sartlari": {
    title: "Kullanım Şartları · QORDY",
    description: "QORDY hizmet kullanım şartları ve sözleşme koşulları.",
    path: "/kullanim-sartlari",
    robots: "index, follow",
  },
  "/login": {
    title: "Giriş — QORDY Restoran Yönetim Paneli",
    description: "QORDY hesabınıza giriş yapın.",
    path: "/login",
    robots: "noindex, follow",
  },
  "/register": {
    title: "Kayıt Ol — QORDY · 14 Gün Ücretsiz Deneme",
    description:
      "QORDY restoran yönetim yazılımına kayıt olun. Kredi kartı gerekmez, 14 gün ücretsiz deneyin.",
    path: "/register",
    robots: "noindex, follow",
  },
};

export function breadcrumbJsonLd(items: { name: string; path: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: item.name,
      item: `${SITE}${item.path === "/" ? "" : item.path}`,
    })),
  };
}

export function faqPageJsonLd(items: { q: string; a: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((item) => ({
      "@type": "Question",
      name: item.q,
      acceptedAnswer: {
        "@type": "Answer",
        text: item.a,
      },
    })),
  };
}

export function getSeoForPath(pathname: string): SeoConfig {
  const legacyHomeAnchors = new Set([
    "/ozellikler",
    "/features",
    "/fiyatlandirma",
    "/fiyatlar",
    "/pricing",
    "/hakkimizda",
    "/iletisim",
  ]);
  const normalized = legacyHomeAnchors.has(pathname) ? "/" : pathname;
  const base = seoRoutes[normalized] ?? seoRoutes["/"];
  const days = getTrialDays();
  return {
    ...base,
    title: withTrialCopy(base.title, days),
    description: withTrialCopy(base.description, days),
  };
}
