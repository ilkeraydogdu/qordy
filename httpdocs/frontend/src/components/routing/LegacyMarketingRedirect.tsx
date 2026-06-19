import { useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";

/** Default landing section when visiting a retired marketing path. */
const PATH_TO_SECTION: Record<string, string> = {
  "/ozellikler": "moduller",
  "/features": "moduller",
  "/fiyatlandirma": "fiyat",
  "/fiyatlar": "fiyat",
  "/pricing": "fiyat",
  "/hakkimizda": "hakkimizda",
  "/iletisim": "iletisim",
};

/** Map legacy feature hashes from /ozellikler#* to landing anchors. */
const HASH_ALIASES: Record<string, string> = {
  qr: "qr",
  kitchen: "pos",
  pos: "pos",
  "tum-ozellikler": "moduller",
};

function resolveTargetSection(pathname: string, hash: string): string | null {
  const fromHash = hash ? HASH_ALIASES[hash] ?? hash : null;
  if (fromHash) return fromHash;
  return PATH_TO_SECTION[pathname] ?? null;
}

/**
 * Client-side redirect for retired marketing routes → home + section anchor.
 * PHP also 301-redirects these paths; this handles in-SPA navigation and hash preservation.
 */
export function LegacyMarketingRedirect() {
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    const hash = location.hash.replace("#", "");
    const target = resolveTargetSection(location.pathname, hash);

    navigate({ pathname: "/", hash: target ? `#${target}` : "" }, { replace: true });

    if (!target) return;

    const scrollToTarget = () => {
      document.getElementById(target)?.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    requestAnimationFrame(() => {
      scrollToTarget();
      window.setTimeout(scrollToTarget, 150);
    });
  }, [location.pathname, location.hash, navigate]);

  return null;
}

export default LegacyMarketingRedirect;
