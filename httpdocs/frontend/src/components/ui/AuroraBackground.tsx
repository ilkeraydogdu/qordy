interface AuroraBackgroundProps {
  className?: string;
  intensity?: "subtle" | "medium" | "strong";
}

/**
 * Soft aurora blobs + grid mask — cinematic hero backdrop without blocking content.
 */
export function AuroraBackground({ className = "", intensity = "medium" }: AuroraBackgroundProps) {
  const opacity = intensity === "subtle" ? "opacity-40" : intensity === "strong" ? "opacity-80" : "opacity-60";

  return (
    <div aria-hidden className={`pointer-events-none absolute inset-0 overflow-hidden ${className}`}>
      <div className={`absolute inset-0 bg-hero-mesh ${opacity}`} />
      <div className="absolute inset-0 bg-grid-pattern opacity-[0.35]" />
      <div className="absolute -top-32 left-[10%] h-[420px] w-[420px] rounded-full bg-brand-300/30 blur-[100px] animate-blob" />
      <div
        className="absolute top-[20%] right-[5%] h-[360px] w-[360px] rounded-full bg-brand-400/20 blur-[90px] animate-blob"
        style={{ animationDelay: "2s" }}
      />
      <div
        className="absolute bottom-0 left-[35%] h-[280px] w-[280px] rounded-full bg-brand-200/40 blur-[80px] animate-blob"
        style={{ animationDelay: "4s" }}
      />
    </div>
  );
}
