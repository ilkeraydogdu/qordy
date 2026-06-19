import type { Package } from "@/lib/api";

/** Hide staging / test tiers from public marketing surfaces. */
export function filterMarketingPackages(packages: Package[]): Package[] {
  return packages.filter((pkg) => {
    const name = (pkg.name ?? "").toLowerCase();
    const id = String(pkg.id ?? pkg.package_id ?? "").toLowerCase();
    if (name.includes("test") || id.includes("test")) return false;
    const monthly = Number(pkg.price_monthly ?? pkg.monthly ?? 0);
    if (name.includes("iyzico") && monthly <= 1) return false;
    return true;
  });
}
