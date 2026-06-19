import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";

export default function TermsPage() {
  return (
    <MarketingLayout path="/kullanim-sartlari">
      <Breadcrumbs
        items={[
          { label: "Ana Sayfa", href: "/" },
          { label: "Kullanım Şartları" },
        ]}
      />
      <article className="container-q max-w-3xl pb-24 prose prose-ink">
        <h1 className="text-display-lg font-bold text-ink-800">Kullanım Şartları</h1>
        <p className="text-ink-600 text-sm">Son güncelleme: {new Date().getFullYear()}</p>

        <section className="mt-10 space-y-4 text-ink-700 leading-relaxed">
          <p>
            QORDY platformunu kullanarak aşağıdaki şartları kabul etmiş sayılırsınız. Lütfen
            hizmetimizi kullanmadan önce bu metni dikkatlice okuyun.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">Hizmet kapsamı</h2>
          <p>
            QORDY, restoran yönetimi için bulut tabanlı yazılım hizmeti sunar. Özellikler
            seçilen abonelik paketine göre değişebilir.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">Hesap güvenliği</h2>
          <p>
            Hesap bilgilerinizin gizliliğinden siz sorumlusunuz. Yetkisiz erişim şüphesinde
            derhal destek ekibimizle iletişime geçin.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">Abonelik ve iptal</h2>
          <p>
            Deneme süresi sonunda seçilen plan üzerinden faturalandırma yapılır. Aboneliğinizi
            panel üzerinden istediğiniz zaman iptal edebilirsiniz.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">İletişim</h2>
          <p>
            Sorularınız için:{" "}
            <a href="mailto:destek@qordy.com" className="text-brand-600 hover:underline">
              destek@qordy.com
            </a>
          </p>
        </section>
      </article>
    </MarketingLayout>
  );
}
