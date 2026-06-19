import { MarketingNavbar } from "@/components/layout/MarketingNavbar";
import { MarketingFooter } from "@/components/layout/MarketingFooter";
import { PageMeta } from "@/components/seo/PageMeta";
import { ScrollProgress } from "@/components/ui/ScrollProgress";
import type { ReactNode } from "react";

export function MarketingLayout({
  children,
  title,
  description,
  path,
  breadcrumbs,
  showScrollProgress = false,
}: {
  children: ReactNode;
  title?: string;
  description?: string;
  path?: string;
  breadcrumbs?: { name: string; path: string }[];
  showScrollProgress?: boolean;
}) {
  return (
    <div className="q-canvas text-ink-800 min-h-screen flex flex-col">
      <PageMeta title={title} description={description} path={path} breadcrumbs={breadcrumbs} />
      <MarketingNavbar />
      {showScrollProgress && <ScrollProgress />}
      <main className="relative flex-1">{children}</main>
      <MarketingFooter />
    </div>
  );
}
