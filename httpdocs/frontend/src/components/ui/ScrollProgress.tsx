import { motion, useScroll, useSpring } from "framer-motion";

/** Thin brand-colored scroll progress bar fixed under the navbar. */
export function ScrollProgress() {
  const { scrollYProgress } = useScroll();
  const scaleX = useSpring(scrollYProgress, { stiffness: 120, damping: 30, restDelta: 0.001 });

  return (
    <motion.div
      aria-hidden
      className="fixed left-0 right-0 top-[72px] z-[60] h-[2px] origin-left bg-brand-600/90"
      style={{ scaleX }}
    />
  );
}
