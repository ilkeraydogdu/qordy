import { motion, useReducedMotion, type Transition } from "framer-motion";
import type { AuthVariant } from "./AuthLayout";

/* ================================================================
   QORDY — Auth line-art character scenes

   Hand-coded monoline SVG "characters" animated with framer-motion
   (pathLength line-draw + subtle looping motion). No typewriter,
   no stock photo, no external Lottie download — fully on-brand,
   license-clean and corporate.

   · login    → a waiter presents a secure login panel; he "peeks"
                at the password (blink + shielded dots that reveal).
   · register → a chef sets up the restaurant: a QR code draws
                itself, setup blocks rise, the first plate is served.

   Strokes are white/gold on the indigo auth panel. Respects
   prefers-reduced-motion (draws once, no looping).
   ================================================================ */

const STROKE = "#FFFFFF";
const GOLD = "#FBBF24";
const SOFT = "rgba(255,255,255,0.55)";

function draw(delay: number, duration = 1.1): {
  initial: Record<string, number>;
  animate: Record<string, number>;
  transition: Transition;
} {
  return {
    initial: { pathLength: 0, opacity: 0 },
    animate: { pathLength: 1, opacity: 1 },
    transition: { pathLength: { delay, duration, ease: [0.16, 1, 0.3, 1] }, opacity: { delay, duration: 0.3 } },
  };
}

export function AuthCharacterScene({ variant }: { variant: AuthVariant }) {
  const reduce = useReducedMotion();
  return (
    <div className="relative w-full max-w-lg">
      {/* deliberate illustration card so the scene reads as designed art */}
      <div className="relative overflow-hidden rounded-[32px] border border-white/15 bg-white/[0.07] p-7 shadow-[0_40px_100px_-30px_rgba(8,9,15,0.65)] backdrop-blur-xl sm:p-9">
        <div aria-hidden className="absolute inset-0 bg-[radial-gradient(70%_60%_at_30%_15%,rgba(255,255,255,0.10),transparent_65%)]" />
        <svg
          viewBox="0 0 400 300"
          role="img"
          aria-label={variant === "login" ? "Qordy güvenli giriş illüstrasyonu" : "Qordy kurulum illüstrasyonu"}
          className="relative w-full"
          fill="none"
          stroke={STROKE}
          strokeWidth={2.4}
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          {variant === "login" ? <LoginScene reduce={!!reduce} /> : <RegisterScene reduce={!!reduce} />}
        </svg>
      </div>
    </div>
  );
}

/* ---------------------------------------------------------------- */
/*  LOGIN — waiter presents a secure login panel (peek at password) */
/* ---------------------------------------------------------------- */

function LoginScene({ reduce }: { reduce: boolean }) {
  const float = reduce ? {} : { y: [0, -7, 0] };
  const floatT: Transition = { duration: 6, repeat: Infinity, ease: "easeInOut" };

  return (
    <>
      {/* ground */}
      <motion.line x1="24" y1="270" x2="376" y2="270" stroke={SOFT} {...draw(0.1, 0.9)} />

      {/* ---- secure login panel (right) ---- */}
      <motion.g animate={float} transition={floatT}>
        <motion.rect x="206" y="78" width="150" height="150" rx="18" {...draw(0.35)} />
        {/* avatar */}
        <motion.circle cx="281" cy="118" r="15" {...draw(0.7, 0.7)} />
        <motion.path d="M268 140c0-8 6-13 13-13s13 5 13 13" {...draw(0.85, 0.6)} />
        {/* username line */}
        <motion.line x1="232" y1="162" x2="330" y2="162" stroke={SOFT} {...draw(1.0, 0.5)} />
        {/* password field */}
        <motion.rect x="232" y="178" width="98" height="22" rx="7" {...draw(1.1, 0.6)} />
        {/* shielded password dots reveal in sequence */}
        {[244, 258, 272, 286].map((cx, i) => (
          <motion.circle
            key={cx}
            cx={cx}
            cy="189"
            r="3.4"
            fill={STROKE}
            stroke="none"
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ delay: 1.5 + i * 0.16, type: "spring", stiffness: 380, damping: 18 }}
          />
        ))}
        {/* eye toggle (the "peek") */}
        <motion.g
          animate={reduce ? {} : { opacity: [1, 0.35, 1] }}
          transition={{ duration: 2.4, repeat: Infinity, ease: "easeInOut", delay: 2 }}
        >
          <motion.path d="M308 189c4-5 12-5 16 0c-4 5-12 5-16 0Z" stroke={GOLD} {...draw(1.8, 0.5)} />
          <motion.circle cx="316" cy="189" r="2.2" fill={GOLD} stroke="none" {...draw(2.0, 0.3)} />
        </motion.g>
        {/* sign-in button */}
        <motion.rect x="232" y="208" width="98" height="14" rx="7" stroke={GOLD} {...draw(2.1, 0.5)} />
      </motion.g>

      {/* ---- waiter character (left) presents the panel ---- */}
      <motion.g animate={reduce ? {} : { y: [0, -5, 0] }} transition={{ duration: 6.5, repeat: Infinity, ease: "easeInOut" }}>
        {/* head */}
        <motion.circle cx="96" cy="92" r="22" {...draw(0.5, 0.8)} />
        {/* eyes — blink */}
        <motion.g
          animate={reduce ? {} : { scaleY: [1, 0.15, 1] }}
          transition={{ duration: 0.5, repeat: Infinity, repeatDelay: 2.6, ease: "easeInOut", delay: 2.2 }}
          style={{ transformOrigin: "96px 90px" } as never}
        >
          <line x1="89" y1="90" x2="91" y2="90" />
          <line x1="101" y1="90" x2="103" y2="90" />
        </motion.g>
        {/* smile */}
        <motion.path d="M90 100c3 3 9 3 12 0" {...draw(1.0, 0.5)} />
        {/* bowtie (gold) */}
        <motion.path d="M88 120l8 5 8-5-8-5-8 5Z" stroke={GOLD} {...draw(1.1, 0.5)} />
        {/* body / apron */}
        <motion.path d="M78 132c0-10 8-16 18-16s18 6 18 16v52H78v-52Z" {...draw(0.9)} />
        <motion.line x1="96" y1="130" x2="96" y2="184" stroke={SOFT} {...draw(1.4, 0.6)} />
        {/* legs */}
        <motion.path d="M86 184v34M106 184v34" {...draw(1.2, 0.6)} />
        {/* presenting arm + serving tray pointing to panel */}
        <motion.path d="M114 140c18 2 34 0 48 -6" {...draw(1.3, 0.7)} />
        <motion.ellipse cx="172" cy="128" rx="20" ry="6" {...draw(1.5, 0.6)} />
        {/* shield-check rising from the tray */}
        <motion.g animate={reduce ? {} : { y: [0, -4, 0] }} transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}>
          <motion.path d="M172 96l14 5v10c0 9-6 13-14 16-8-3-14-7-14-16V101l14-5Z" stroke={GOLD} {...draw(1.7, 0.8)} />
          <motion.path d="M166 110l5 5 8-8" stroke={GOLD} {...draw(2.1, 0.5)} />
        </motion.g>
      </motion.g>

      {/* sparkle accents */}
      {!reduce && (
        <motion.g
          animate={{ opacity: [0, 1, 0], scale: [0.6, 1, 0.6] }}
          transition={{ duration: 3, repeat: Infinity, ease: "easeInOut", delay: 2.4 }}
          stroke={GOLD}
        >
          <path d="M348 60v10M343 65h10" />
        </motion.g>
      )}
    </>
  );
}

/* ---------------------------------------------------------------- */
/*  REGISTER — chef sets up the restaurant (QR draws, blocks rise)  */
/* ---------------------------------------------------------------- */

function RegisterScene({ reduce }: { reduce: boolean }) {
  return (
    <>
      {/* ground */}
      <motion.line x1="24" y1="270" x2="376" y2="270" stroke={SOFT} {...draw(0.1, 0.9)} />

      {/* ---- chef character (left) ---- */}
      <motion.g animate={reduce ? {} : { y: [0, -5, 0] }} transition={{ duration: 6.5, repeat: Infinity, ease: "easeInOut" }}>
        {/* toque (chef hat) */}
        <motion.path d="M74 70c-9 0-15-7-12-15 2-7 11-9 15-4 2-9 18-9 20 0 4-5 13-3 15 4 3 8-3 15-12 15v6H74v-6Z" {...draw(0.45)} />
        <motion.line x1="74" y1="82" x2="100" y2="82" stroke={SOFT} {...draw(1.0, 0.5)} />
        {/* head */}
        <motion.circle cx="87" cy="100" r="18" {...draw(0.6, 0.8)} />
        {/* eyes — blink */}
        <motion.g
          animate={reduce ? {} : { scaleY: [1, 0.15, 1] }}
          transition={{ duration: 0.5, repeat: Infinity, repeatDelay: 2.8, ease: "easeInOut", delay: 2 }}
          style={{ transformOrigin: "87px 98px" } as never}
        >
          <line x1="81" y1="98" x2="83" y2="98" />
          <line x1="91" y1="98" x2="93" y2="98" />
        </motion.g>
        <motion.path d="M82 107c3 3 7 3 10 0" {...draw(1.1, 0.5)} />
        {/* body */}
        <motion.path d="M70 138c0-9 7-15 17-15s17 6 17 15v46H70v-46Z" {...draw(0.95)} />
        <motion.path d="M87 124v60" stroke={SOFT} {...draw(1.3, 0.6)} />
        {/* buttons */}
        <motion.circle cx="79" cy="150" r="2" fill={STROKE} stroke="none" {...draw(1.5, 0.3)} />
        <motion.circle cx="79" cy="164" r="2" fill={STROKE} stroke="none" {...draw(1.6, 0.3)} />
        {/* legs */}
        <motion.path d="M78 184v34M96 184v34" {...draw(1.2, 0.6)} />
        {/* arm presenting the QR */}
        <motion.path d="M104 146c16 0 30 -2 42 -8" {...draw(1.4, 0.7)} />
      </motion.g>

      {/* ---- QR code drawing itself (the setup) ---- */}
      <motion.g animate={reduce ? {} : { y: [0, -6, 0] }} transition={{ duration: 5, repeat: Infinity, ease: "easeInOut" }}>
        <motion.rect x="168" y="92" width="92" height="92" rx="12" {...draw(0.7)} />
        {/* finder squares */}
        <motion.rect x="180" y="104" width="20" height="20" rx="4" {...draw(1.2, 0.6)} />
        <motion.rect x="228" y="104" width="20" height="20" rx="4" {...draw(1.35, 0.6)} />
        <motion.rect x="180" y="152" width="20" height="20" rx="4" {...draw(1.5, 0.6)} />
        {/* data modules pop in */}
        {[
          [216, 116], [216, 132], [232, 148], [216, 164], [232, 164],
          [248, 132], [248, 160], [200, 140], [212, 152],
        ].map(([x, y], i) => (
          <motion.rect
            key={`${x}-${y}`}
            x={x}
            y={y}
            width="9"
            height="9"
            rx="2"
            fill={STROKE}
            stroke="none"
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ delay: 1.7 + i * 0.1, type: "spring", stiffness: 360, damping: 18 }}
          />
        ))}
        {/* scan line */}
        {!reduce && (
          <motion.line
            x1="172"
            x2="256"
            stroke={GOLD}
            strokeWidth={2}
            initial={{ y1: 100, y2: 100, opacity: 0 }}
            animate={{ y1: [104, 176, 104], y2: [104, 176, 104], opacity: [0, 0.9, 0] }}
            transition={{ duration: 2.6, repeat: Infinity, ease: "easeInOut", delay: 2.6 }}
          />
        )}
      </motion.g>

      {/* ---- setup progress blocks rise (right) ---- */}
      {[
        { x: 296, h: 26, d: 1.9, c: STROKE },
        { x: 320, h: 42, d: 2.05, c: STROKE },
        { x: 344, h: 60, d: 2.2, c: GOLD },
      ].map((b) => (
        <motion.rect
          key={b.x}
          x={b.x}
          width="16"
          rx="4"
          stroke={b.c}
          initial={{ height: 0, y: 230, opacity: 0 }}
          animate={{ height: b.h, y: 230 - b.h, opacity: 1 }}
          transition={{ delay: b.d, duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
        />
      ))}
      <motion.path d="M296 196l24 -16 24 8" stroke={GOLD} {...draw(2.4, 0.7)} />

      {/* spark — "you're live" */}
      {!reduce && (
        <motion.g
          animate={{ opacity: [0, 1, 0], scale: [0.5, 1.1, 0.5] }}
          transition={{ duration: 2.8, repeat: Infinity, ease: "easeInOut", delay: 2.8 }}
          stroke={GOLD}
        >
          <path d="M344 150v-12M338 144h12" />
        </motion.g>
      )}
    </>
  );
}

export default AuthCharacterScene;
