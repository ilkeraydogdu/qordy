import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

// Register plugin once on module load.
gsap.registerPlugin(ScrollTrigger);

// Sensible global defaults tuned for the editorial feel of this design.
gsap.defaults({
  ease: "expo.out",
  duration: 1.1,
});

export { gsap, ScrollTrigger };
