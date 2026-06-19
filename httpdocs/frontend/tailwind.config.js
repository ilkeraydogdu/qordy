/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        // ============================================================
        // QORDY — Violet/Indigo SaaS system (kaka webui reference)
        // Works for BOTH the dark homepage canvas and the light
        // marketing sub-pages. The `ink` scale runs dark(950)→light(50)
        // so `bg-ink-950` is the dark canvas while `text-ink-900` reads
        // as near-black on white surfaces.
        // ============================================================

        // Neutral scale — EXACTLY the mockup palette.
        // Light neutrals = Tailwind slate (#F8FAFC…#0F172A from "Nötr Renkler"
        // in webui1). Dark canvas = #0B1020 (webui2 darkest swatch).
        ink: {
          950: "#0B1020", // dark canvas (webui2)
          900: "#0F172A", // headline on light / dark surface
          800: "#1E293B", // raised dark surface
          700: "#334155", // strong body text on light
          600: "#475569", // sub-text on light
          500: "#64748B", // muted text (mockup #64748B)
          400: "#94A3B8", // placeholder
          300: "#CBD5E1", // disabled / mockup #CBD5E1
          200: "#E2E8F0", // borders (mockup #E2E8F0)
          100: "#F1F5F9", // hover backgrounds (mockup #F1F5F9)
          50: "#F8FAFC",  // light body background (mockup #F8FAFC)
        },

        // Signature indigo — primary accent.
        // Mockup: light primary = #6366F1, dark primary = #6D5DF6 (visually
        // identical). Ramp = Tailwind indigo so brand-500 === #6366F1.
        // NOTE: token name `amber` retained for legacy markup; same indigo.
        amber: {
          50: "#EEF2FF",
          100: "#E0E7FF",
          200: "#C7D2FE",
          300: "#A5B4FC",
          400: "#818CF8",
          500: "#6366F1", // primary signature (webui1)
          600: "#4F46E5", // primary CTA (AA on white)
          700: "#4338CA",
          800: "#3730A3",
          900: "#312E81",
        },

        // Same indigo ramp under the `brand` name
        brand: {
          50: "#EEF2FF",
          100: "#E0E7FF",
          200: "#C7D2FE",
          300: "#A5B4FC",
          400: "#818CF8",
          500: "#6366F1", // mockup light primary
          600: "#4F46E5",
          700: "#4338CA",
          800: "#3730A3",
          900: "#312E81",
        },

        // Secondary violet (mockup #8B5CF6) — gradient partner for indigo
        violet2: {
          400: "#A78BFA",
          500: "#8B5CF6",
          600: "#7C3AED",
        },

        // True amber/gold accent (mockup #F59E0B) — kitchen / badge / Franchise.
        // Separate from the `amber` alias above (which is indigo for legacy).
        gold: {
          50: "#FFFBEB",
          100: "#FEF3C7",
          300: "#FCD34D",
          400: "#FBBF24",
          500: "#F59E0B", // mockup gold
          600: "#D97706",
        },

        // Electric lime — reserved exclusively for LIVE indicators
        lime: {
          400: "#A8E840",
          500: "#84CC16",
          600: "#65A30D",
        },

        // Emerald success
        success: {
          50: "#ECFDF5",
          100: "#D1FAE5",
          500: "#10B981",
          600: "#059669",
        },

        // Warm amber accent — complementary highlight against violet
        accent: {
          50: "#FFF7ED",
          500: "#F59E0B",
          600: "#EA580C",
        },

        // Footer surface (dark)
        footer: {
          900: "#0B0D17",
          800: "#11141F",
          700: "#1A1E2C",
          500: "#8A90A6",
          400: "#A7ACBE",
        },

        // Surface tints
        paper: {
          DEFAULT: "#F6F7FB",
          dark: "#0E1018",
        },
      },
      fontFamily: {
        sans: ['"Inter"', "ui-sans-serif", "system-ui", "-apple-system", "sans-serif"],
        display: ['"Fraunces"', '"Playfair Display"', "ui-serif", "Georgia", "serif"],
        mono: ['"JetBrains Mono"', "ui-monospace", "monospace"],
      },
      fontSize: {
        "display-3xl": ["clamp(3.5rem, 9vw, 7.5rem)", { lineHeight: "0.92", letterSpacing: "-0.04em" }],
        "display-2xl": ["clamp(2.75rem, 6.5vw, 5.5rem)", { lineHeight: "0.95", letterSpacing: "-0.035em" }],
        "display-xl": ["clamp(2.25rem, 5vw, 4rem)", { lineHeight: "1.0", letterSpacing: "-0.03em" }],
        "display-lg": ["clamp(1.75rem, 3.5vw, 2.75rem)", { lineHeight: "1.1", letterSpacing: "-0.025em" }],
        "display-md": ["clamp(1.375rem, 2.5vw, 1.875rem)", { lineHeight: "1.2", letterSpacing: "-0.02em" }],
        "eyebrow": ["0.7rem", { lineHeight: "1.0", letterSpacing: "0.18em" }],
      },
      boxShadow: {
        // Indigo brand glows (#6366F1)
        "amber": "0 12px 32px -8px rgba(99, 102, 241, 0.45)",
        "amber-lg": "0 24px 60px -12px rgba(99, 102, 241, 0.55)",
        "brand-sm": "0 6px 18px -8px rgba(99, 102, 241, 0.5)",
        "brand": "0 14px 36px -12px rgba(99, 102, 241, 0.45)",
        "brand-lg": "0 28px 70px -18px rgba(99, 102, 241, 0.5)",
        // Neutral soft shadows for light surfaces
        "soft-sm": "0 4px 16px -6px rgba(15, 18, 40, 0.12)",
        "soft": "0 14px 44px -18px rgba(15, 18, 40, 0.18)",
        "soft-lg": "0 30px 80px -28px rgba(15, 18, 40, 0.24)",
        // Dark canvas shadows
        "ink": "0 12px 40px -10px rgba(7, 8, 12, 0.5)",
        "ink-lg": "0 30px 80px -20px rgba(7, 8, 12, 0.65)",
      },
      backgroundImage: {
        "grain": "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' /%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.4'/%3E%3C/svg%3E\")",
        "amber-grad": "linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)",
        "ink-grad": "linear-gradient(180deg, #0F172A 0%, #0B1020 100%)",
        "amber-shine": "linear-gradient(110deg, #4F46E5 0%, #6366F1 50%, #8B5CF6 100%)",
      },
      keyframes: {
        "fade-up": {
          "0%": { opacity: "0", transform: "translate3d(0,32px,0)" },
          "100%": { opacity: "1", transform: "translate3d(0,0,0)" },
        },
        "slide-in": {
          "0%": { opacity: "0", transform: "translate3d(-24px,0,0)" },
          "100%": { opacity: "1", transform: "translate3d(0,0,0)" },
        },
        "marquee": {
          "0%": { transform: "translate3d(0,0,0)" },
          "100%": { transform: "translate3d(-50%,0,0)" },
        },
        "marquee-slow": {
          "0%": { transform: "translate3d(0,0,0)" },
          "100%": { transform: "translate3d(-50%,0,0)" },
        },
        "pulse-lime": {
          "0%,100%": { opacity: "1", transform: "scale(1)" },
          "50%": { opacity: "0.6", transform: "scale(0.9)" },
        },
        "float": {
          "0%,100%": { transform: "translateY(0)" },
          "50%": { transform: "translateY(-12px)" },
        },
        "blob": {
          "0%,100%": { transform: "translate(0px, 0px) scale(1)" },
          "33%": { transform: "translate(24px, -32px) scale(1.06)" },
          "66%": { transform: "translate(-18px, 18px) scale(0.96)" },
        },
        "ticker": {
          "0%": { transform: "translate3d(100%,0,0)" },
          "100%": { transform: "translate3d(-100%,0,0)" },
        },
        "shimmer": {
          "0%": { backgroundPosition: "-200% 0" },
          "100%": { backgroundPosition: "200% 0" },
        },
      },
      animation: {
        "fade-up": "fade-up 0.9s cubic-bezier(0.16,1,0.3,1) both",
        "slide-in": "slide-in 0.7s cubic-bezier(0.16,1,0.3,1) both",
        "marquee-slow": "marquee-slow 50s linear infinite",
        "ticker": "ticker 30s linear infinite",
        "pulse-lime": "pulse-lime 1.6s ease-in-out infinite",
        "float": "float 6s ease-in-out infinite",
        "blob": "blob 18s ease-in-out infinite",
        "shimmer": "shimmer 2.5s linear infinite",
      },
    },
  },
  plugins: [],
};
