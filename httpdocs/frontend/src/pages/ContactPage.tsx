import { MarketingLayout } from "@/components/layout/MarketingLayout";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { PageHero } from "@/components/marketing";
import { ContactSection } from "@/components/landing/ContactSection";

export default function ContactPage() {
  return (
    <MarketingLayout
      path="/iletisim"
      breadcrumbs={[
        { name: "Ana Sayfa", path: "/" },
        { name: "İletişim", path: "/iletisim" },
      ]}
    >
      <Breadcrumbs items={[{ label: "Ana Sayfa", href: "/" }, { label: "İletişim" }]} />

      <PageHero
        eyebrow="İletişim"
        title={<>Size nasıl <span className="gradient-text">yardımcı</span> olabiliriz?</>}
        lead={
          <>
            Satış, demo ve teknik destek talepleriniz için formu doldurun veya{" "}
            <a href="mailto:destek@qordy.com" className="font-medium text-brand-600 hover:underline">
              destek@qordy.com
            </a>{" "}
            adresine yazın.
          </>
        }
      />

      <ContactSection withHeading={false} />
    </MarketingLayout>
  );
}
