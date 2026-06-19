/**
 * Canonical Qordy brand assets — single source of truth for the React SPA.
 * PHP mirrors this via resolveQordyCorporateLogoUrl() / getQordyLogoUrl().
 */
export const QORDY_LOGO = {
  /** Full wordmark on light backgrounds (slate "ordy" + blue "Q"). */
  default: "/assets/images/logo.png",
  /** White wordmark for dark / tinted surfaces. */
  light: "/assets/images/logo_light.png",
} as const;

export type LogoVariant = "light" | "dark";

/** Map Logo variant prop → asset path. */
export function qordyLogoSrc(variant: LogoVariant = "dark"): string {
  return variant === "light" ? QORDY_LOGO.light : QORDY_LOGO.default;
}
