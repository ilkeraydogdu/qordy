import { motion } from "framer-motion";
import { TURKEY_BRANCHES, type BranchTier } from "./data";

/* Stylised Türkiye silhouette (decorative, not survey-accurate) on a
   1000×440 grid. Branch dots live in the same coordinate space so they
   always stay anchored to the map at any container size. */

const TURKEY_PATH =
  "M58 150 C120 116 210 110 300 122 C402 136 520 110 660 124 C792 137 900 146 958 182 L982 214 C966 236 944 252 952 292 L922 322 C862 332 820 300 762 322 C706 342 648 360 566 358 L552 410 L536 358 C462 358 384 350 304 350 C226 350 162 360 112 330 L72 300 C92 272 62 250 84 220 C62 200 50 176 58 150 Z";

const TIER_COLOR: Record<BranchTier, string> = {
  high: "#10B981", // Yüksek
  mid: "#F59E0B", // Orta
  low: "#6366F1", // Düşük
};

export function TurkeyMap({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 1000 440" className={`w-full h-auto ${className}`} role="img" aria-label="Şube ağı haritası">
      <defs>
        <linearGradient id="tr-fill" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#6366F1" stopOpacity="0.16" />
          <stop offset="100%" stopColor="#8B5CF6" stopOpacity="0.05" />
        </linearGradient>
      </defs>

      <path
        d={TURKEY_PATH}
        fill="url(#tr-fill)"
        stroke="currentColor"
        strokeOpacity="0.35"
        strokeWidth="2.5"
        strokeLinejoin="round"
        className="text-brand-500/70 dark:text-brand-300/60"
      />

      {TURKEY_BRANCHES.map((b, i) => {
        const cx = (b.x / 100) * 1000;
        const cy = (b.y / 100) * 440;
        const color = TIER_COLOR[b.tier];
        return (
          <g key={b.city}>
            <motion.circle
              cx={cx}
              cy={cy}
              r={6}
              fill={color}
              fillOpacity="0.35"
              initial={{ r: 6, opacity: 0.5 }}
              animate={{ r: [6, 20, 6], opacity: [0.5, 0, 0.5] }}
              transition={{ duration: 2.6, repeat: Infinity, delay: i * 0.22, ease: "easeOut" }}
            />
            <circle cx={cx} cy={cy} r={5} fill={color} stroke="#fff" strokeWidth="1.5" />
          </g>
        );
      })}
    </svg>
  );
}
