import { Link } from "react-router-dom";
import { qordyLogoSrc, type LogoVariant } from "@/lib/brand";

interface LogoProps {
  /** "light" => white wordmark for dark surfaces. "dark" => full-color on light surfaces. */
  variant?: LogoVariant;
  /** Tailwind height class. Defaults to h-8 (32px). */
  className?: string;
  /** When true, wraps the logo in a Link to "/". */
  asLink?: boolean;
  /** Compact surfaces: same wordmark asset, smaller height. */
  markOnly?: boolean;
}

/**
 * Official Qordy wordmark from corporate PNG assets.
 * Keeps brand consistent across marketing, auth, and dashboard previews.
 */
export function Logo({
  variant = "dark",
  className = "h-8 w-auto",
  asLink = true,
  markOnly = false,
}: LogoProps) {
  const sizeClass = markOnly ? className || "h-7 w-auto" : className;
  const src = qordyLogoSrc(variant);

  const img = (
    <img
      src={src}
      alt="Qordy"
      className={`${sizeClass} select-none object-contain object-left drop-shadow-[0_4px_12px_rgba(31,90,171,0.28)] dark:drop-shadow-[0_4px_12px_rgba(99,102,241,0.22)]`}
      draggable={false}
      loading="eager"
      decoding="async"
    />
  );

  if (!asLink) return img;

  return (
    <Link to="/" aria-label="Qordy ana sayfa" className="inline-flex items-center">
      {img}
    </Link>
  );
}

/** @deprecated Use `<Logo markOnly />` — kept for existing imports. */
export function LogoMark({
  variant = "dark",
  className = "h-9 w-auto",
  asLink = false,
}: Omit<LogoProps, "markOnly">) {
  return <Logo variant={variant} className={className} asLink={asLink} markOnly />;
}
