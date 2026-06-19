import { Link } from "react-router-dom";
import type { ReactNode } from "react";
import { Icon } from "@/components/ui/Icon";

/* ================================================================
   QORDY — Marketing CtaBand

   The closing indigo CTA band shared by Features & Pricing so the
   conversion footer is identical everywhere. Real routes only.
   ================================================================ */

interface Props {
  title: ReactNode;
  lead?: ReactNode;
  primaryLabel?: string;
  primaryTo?: string;
  secondaryLabel?: string;
  secondaryTo?: string;
}

export function CtaBand({
  title,
  lead,
  primaryLabel = "Ücretsiz Başla",
  primaryTo = "/register",
  secondaryLabel = "Satış Ekibiyle Görüş",
  secondaryTo = "/#iletisim",
}: Props) {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-brand-800 py-20 text-white md:py-28">
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(245,158,11,0.22),transparent_70%)]" />
      <div aria-hidden className="absolute -top-24 right-[12%] h-72 w-72 rounded-full bg-white/10 blur-3xl" />
      <div className="container-q relative mx-auto max-w-3xl text-center">
        <h2 className="text-display-lg font-display font-bold tracking-tight">{title}</h2>
        {lead && <p className="mt-4 text-lg text-brand-100/85">{lead}</p>}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link
            to={primaryTo}
            className="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-sm font-semibold text-brand-700 shadow-[0_18px_44px_-18px_rgba(0,0,0,0.5)] transition-all hover:-translate-y-0.5 hover:bg-brand-50"
          >
            {primaryLabel}
            <Icon name="arrow" size={16} />
          </Link>
          <Link
            to={secondaryTo}
            className="inline-flex items-center rounded-full border border-white/30 px-7 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-white/10"
          >
            {secondaryLabel}
          </Link>
        </div>
      </div>
    </section>
  );
}

export default CtaBand;
