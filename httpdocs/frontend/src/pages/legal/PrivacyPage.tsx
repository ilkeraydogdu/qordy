import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";

export default function PrivacyPage() {
  return (
    <MarketingLayout path="/gizlilik">
      <Breadcrumbs
        items={[
          { label: "Ana Sayfa", href: "/" },
          { label: "Gizlilik Politikası" },
        ]}
      />
      <article className="container-q max-w-3xl pb-24 prose prose-ink">
        <h1 className="text-display-lg font-bold text-ink-800">Gizlilik Politikası</h1>
        <p className="text-ink-600 text-sm">Son güncelleme: {new Date().getFullYear()}</p>

        <section className="mt-10 space-y-4 text-ink-700 leading-relaxed">
          <p>
            QORDY Smart Restaurant Systems (&quot;QORDY&quot;) olarak kişisel verilerinizin güvenliğine
            önem veriyoruz. Bu politika, platformumuzu kullanırken toplanan verilerin nasıl
            işlendiğini açıklar.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">Toplanan veriler</h2>
          <p>
            Hesap oluşturma, sipariş yönetimi ve destek süreçlerinde ad, e-posta, telefon ve
            işletme bilgileri toplanabilir. Ödeme bilgileri PCI-DSS uyumlu ödeme sağlayıcıları
            üzerinden işlenir; kart verileri QORDY sunucularında saklanmaz.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">KVKK haklarınız</h2>
          <p>
            6698 sayılı KVKK kapsamında verilerinize erişim, düzeltme, silme ve itiraz haklarına
            sahipsiniz. Talepleriniz için destek@qordy.com adresine başvurabilirsiniz.
          </p>
          <h2 className="text-xl font-bold text-ink-800 pt-4">İletişim</h2>
          <p>
            Gizlilik ile ilgili sorularınız için:{" "}
            <a href="mailto:destek@qordy.com" className="text-brand-600 hover:underline">
              destek@qordy.com
            </a>
          </p>
        </section>
      </article>
    </MarketingLayout>
  );
}
