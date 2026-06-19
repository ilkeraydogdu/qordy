/* ================================================================
   QORDY LANDING — Franchise Yönetim Platformu

   Reference mockups: webui1.png (light/day) · webui2.png (dark/night)
   Both themes share one layout. Light is the default; a navbar toggle
   (persisted in localStorage) switches to the dark canvas.

   This page is a thin composition — every section lives in
   src/components/landing/* so it can be reused and maintained
   independently.
   ================================================================ */
import { useTheme } from "@/lib/useTheme";
import { MarketingNavbar } from "@/components/layout/MarketingNavbar";
import { HeroSection } from "@/components/landing/HeroSection";
import { ModuleChips } from "@/components/landing/ModuleChips";
import { DashboardSection } from "@/components/landing/DashboardSection";
import { CafeStorySection } from "@/components/landing/CafeStorySection";
import { MobileSection } from "@/components/landing/MobileSection";
import { Pricing } from "@/components/landing/Pricing";
import { HowItWorks } from "@/components/landing/HowItWorks";
import { ContactSection } from "@/components/landing/ContactSection";
import { FinalCTA } from "@/components/landing/FinalCTA";
import { TrustBand } from "@/components/landing/TrustBand";
import { MarketingFooter } from "@/components/layout/MarketingFooter";

export function LandingPage() {
  const [theme, toggleTheme] = useTheme();

  return (
    <div className={theme === "dark" ? "dark" : ""}>
      <div className="min-h-screen overflow-x-hidden bg-[#F4F5FA] dark:bg-ink-950 text-ink-800 dark:text-ink-100 selection:bg-brand-500/25">
        <MarketingNavbar theme={theme} onToggleTheme={toggleTheme} />
        <HeroSection />
        <ModuleChips />
        <DashboardSection />
        <CafeStorySection />
        <MobileSection />
        <Pricing />
        <HowItWorks />
        <ContactSection />
        <FinalCTA />
        <TrustBand />
        <MarketingFooter />
      </div>
    </div>
  );
}

export default LandingPage;
