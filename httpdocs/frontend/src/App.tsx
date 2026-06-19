import { Routes, Route, useLocation } from "react-router-dom";
import { lazy, Suspense, useEffect } from "react";
import { SmoothScroll } from "@/components/layout/SmoothScroll";
import { LegacyMarketingRedirect } from "@/components/routing/LegacyMarketingRedirect";

const LandingPage = lazy(() => import("@/pages/LandingPage"));
const PrivacyPage = lazy(() => import("@/pages/legal/PrivacyPage"));
const TermsPage = lazy(() => import("@/pages/legal/TermsPage"));
const LoginPage = lazy(() => import("@/pages/LoginPage"));
const RegisterPage = lazy(() => import("@/pages/RegisterPage"));

function Fallback() {
  return (
    <div className="min-h-screen bg-ink-50 flex items-center justify-center">
      <div className="flex flex-col items-center gap-4 text-brand-600">
        <span className="h-10 w-10 rounded-full border-2 border-brand-600/20 border-t-brand-600 animate-spin" />
        <span className="text-sm uppercase tracking-[0.3em] text-ink-500">qordy</span>
      </div>
    </div>
  );
}

function ScrollToTop() {
  const { pathname, hash } = useLocation();
  useEffect(() => {
    if (hash) {
      const el = document.getElementById(hash.replace("#", ""));
      if (el) {
        el.scrollIntoView({ behavior: "smooth" });
        return;
      }
    }
    window.scrollTo({ top: 0, behavior: "instant" in window ? ("instant" as ScrollBehavior) : "auto" });
  }, [pathname, hash]);
  return null;
}

const legacyMarketingPaths = [
  "/ozellikler",
  "/features",
  "/fiyatlandirma",
  "/fiyatlar",
  "/pricing",
  "/hakkimizda",
  "/iletisim",
];

export default function App() {
  return (
    <SmoothScroll>
      <ScrollToTop />
      <Suspense fallback={<Fallback />}>
        <Routes>
          <Route path="/" element={<LandingPage />} />
          {legacyMarketingPaths.map((path) => (
            <Route key={path} path={path} element={<LegacyMarketingRedirect />} />
          ))}
          <Route path="/gizlilik" element={<PrivacyPage />} />
          <Route path="/kullanim-sartlari" element={<TermsPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="*" element={<LandingPage />} />
        </Routes>
      </Suspense>
    </SmoothScroll>
  );
}
