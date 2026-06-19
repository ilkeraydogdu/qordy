import type { ReactNode } from "react";

interface Props {
  eyebrow?: string;
  title: ReactNode;
  lead?: ReactNode;
  align?: "left" | "center";
  tone?: "dark" | "light";
}

export function SectionTitle({
  eyebrow,
  title,
  lead,
  align = "center",
  tone = "light",
}: Props) {
  const textAlign = align === "center" ? "text-center" : "text-left";
  const alignItems = align === "center" ? "items-center" : "items-start";

  return (
    <div className={`flex flex-col ${alignItems} gap-5 max-w-3xl mx-auto ${textAlign}`}>
      {eyebrow && (
        <span className="eyebrow" data-reveal>
          {eyebrow}
        </span>
      )}
      <h2
        data-reveal
        className={`font-display font-semibold leading-[1.02] tracking-tight text-display-lg ${
          tone === "light" ? "text-ink-900" : "text-white"
        }`}
      >
        {title}
      </h2>
      {lead && (
        <p
          data-reveal
          className={`text-base md:text-lg leading-relaxed max-w-2xl ${
            tone === "light" ? "text-ink-700" : "text-white/70"
          }`}
        >
          {lead}
        </p>
      )}
    </div>
  );
}
