import { getBootstrap } from "@/lib/bootstrap";

export const TRIAL_DAYS = getBootstrap()?.trial?.duration_days ?? 14;

export const HERO_STATS = [
  { value: "500+", label: "Mutlu Marka" },
  { value: "10.000+", label: "Aktif Kullanıcı" },
  { value: "250M+", label: "Aylık İşlem" },
  { value: "%99.9", label: "Sistem Uptime" },
];

export const MODULES = [
  { icon: "qr", title: "QR Menü", desc: "Dijital menülerle siparişleri artırın", tint: "violet" },
  { icon: "pos", title: "POS Sistemi", desc: "Hızlı, güvenli ve kolay kullanım", tint: "blue" },
  { icon: "kitchen", title: "Mutfak Yönetimi", desc: "Anlık sipariş akışı ve mutfak ekranı", tint: "orange" },
  { icon: "stock", title: "Stok Yönetimi", desc: "Akıllı stok takibi ve malzeme yönetimi", tint: "pink" },
  { icon: "finance", title: "Finans & Raporlar", desc: "Detaylı raporlar ve finansal analiz", tint: "green" },
  { icon: "shield", title: "Franchise Yönetimi", desc: "Merkezi kontrol ile tüm şubeler elinizde", tint: "amber" },
] as const;

export const TRUST = [
  { icon: "shield", title: "Güvenli Altyapı", desc: "Verileriniz 256-bit SSL ile korunur", tint: "green" },
  { icon: "bell", title: "7/24 Destek", desc: "Her zaman yanınızdayız", tint: "violet" },
  { icon: "share", title: "Kolay Entegrasyon", desc: "Mevcut sistemlerinizle kolayca entegre olur", tint: "amber" },
  { icon: "bolt", title: "Kesintisiz Hizmet", desc: "%99.9 uptime garantisi", tint: "green" },
] as const;

// ---- HERO dashboard (compact preview) ----------------------------
export const HERO_NAV = ["Ana Sayfa", "Şubeler", "POS", "QR Menü", "Siparişler", "Mutfak", "Stok", "Finans"];
export const HERO_KPI = [
  { label: "Toplam Ciro", value: "₺8.450.231", delta: "+12.5%" },
  { label: "Toplam Sipariş", value: "12.842", delta: "+8.2%" },
  { label: "Ortalama Sepet", value: "₺658", delta: "+6.1%" },
  { label: "Aktif Şube", value: "128", delta: "+2" },
];
export const HERO_DONUT = [
  { label: "Paket Servis", value: 45, color: "#6366F1" },
  { label: "QR Menü", value: 30, color: "#F59E0B" },
  { label: "Salonda", value: 25, color: "#10B981" },
];
export const HERO_PRODUCTS = [
  { name: "Cheeseburger", count: "2.421" },
  { name: "Pizza Margherita", count: "1.882" },
  { name: "Limonata", count: "1.543" },
];

// ---- #panel analytics dashboard (rich / realistic) ---------------
export const PANEL_NAV = [
  "Ana Sayfa", "Şubeler", "POS", "QR Menü", "Siparişler",
  "Mutfak", "Stok", "Finans", "Raporlar", "Kampanyalar", "Kullanıcılar", "Ayarlar",
];

export const PANEL_KPI = [
  { label: "Toplam Ciro", prefix: "₺", value: 8450231, delta: "+12.5%", up: true },
  { label: "Toplam Sipariş", prefix: "", value: 12842, delta: "+8.2%", up: true },
  { label: "Ortalama Sepet", prefix: "₺", value: 658, delta: "+6.1%", up: true },
  { label: "Aktif Şube", prefix: "", value: 128, delta: "+2", up: true },
];

// Live order feed (Canlı Sipariş Akışı)
export const PANEL_ORDERS = [
  { id: "#12451", branch: "Beşiktaş Şubesi", amount: "₺842", time: "şimdi", state: "new" },
  { id: "#12450", branch: "Kadıköy Şubesi", amount: "₺1.240", time: "1 dk", state: "prep" },
  { id: "#12449", branch: "Ankara Çankaya", amount: "₺560", time: "3 dk", state: "prep" },
  { id: "#12448", branch: "Bursa Nilüfer", amount: "₺318", time: "5 dk", state: "ready" },
  { id: "#12447", branch: "İzmir Alsancak", amount: "₺975", time: "6 dk", state: "ready" },
] as const;

// Popular products with real food photography (Unsplash) + gradient fallback.
export const PANEL_PRODUCTS = [
  { name: "Cheeseburger", count: "2.421", revenue: "₺678.040", img: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=96&h=96&fit=crop&auto=format&q=70", grad: "from-gold-300 to-orange-400" },
  { name: "Pizza Margherita", count: "1.882", revenue: "₺658.700", img: "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=96&h=96&fit=crop&auto=format&q=70", grad: "from-red-300 to-gold-400" },
  { name: "Caesar Salad", count: "1.543", revenue: "₺339.460", img: "https://images.unsplash.com/photo-1550304943-4f24f54ddde9?w=96&h=96&fit=crop&auto=format&q=70", grad: "from-emerald-300 to-emerald-500" },
  { name: "Limonata", count: "1.211", revenue: "₺157.430", img: "https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=96&h=96&fit=crop&auto=format&q=70", grad: "from-yellow-200 to-gold-300" },
] as const;

// Category revenue donut (Kategorilere Göre Ciro)
export const PANEL_CATEGORIES = [
  { label: "Yiyecek", value: 55, color: "#6366F1" },
  { label: "İçecek", value: 30, color: "#F59E0B" },
  { label: "Tatlı", value: 10, color: "#EC4899" },
  { label: "Diğer", value: 5, color: "#10B981" },
];

// Hourly revenue bars (Saatlik Ciro) — 10:00 → 23:00
export const PANEL_HOURS = [
  { h: "10", v: 18 }, { h: "11", v: 26 }, { h: "12", v: 58 }, { h: "13", v: 72 },
  { h: "14", v: 64 }, { h: "15", v: 41 }, { h: "16", v: 38 }, { h: "17", v: 49 },
  { h: "18", v: 70 }, { h: "19", v: 95 }, { h: "20", v: 100 }, { h: "21", v: 88 },
  { h: "22", v: 60 }, { h: "23", v: 34 },
];

// Turkey branch network — coordinates as % within the map viewBox, plus
// a load tier driving dot colour (yüksek / orta / düşük).
export type BranchTier = "high" | "mid" | "low";
export const TURKEY_BRANCHES: { city: string; x: number; y: number; tier: BranchTier }[] = [
  { city: "İstanbul", x: 21, y: 26, tier: "high" },
  { city: "İzmir", x: 12, y: 56, tier: "high" },
  { city: "Ankara", x: 41, y: 42, tier: "high" },
  { city: "Antalya", x: 33, y: 74, tier: "mid" },
  { city: "Bursa", x: 24, y: 38, tier: "mid" },
  { city: "Adana", x: 55, y: 70, tier: "mid" },
  { city: "Gaziantep", x: 64, y: 68, tier: "mid" },
  { city: "Trabzon", x: 72, y: 30, tier: "low" },
  { city: "Diyarbakır", x: 76, y: 58, tier: "low" },
  { city: "Erzurum", x: 80, y: 42, tier: "low" },
  { city: "Van", x: 90, y: 56, tier: "low" },
];
