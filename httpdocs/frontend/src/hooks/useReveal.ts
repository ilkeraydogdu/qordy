import { useRef } from "react";

/**
 * GSAP/ScrollTrigger tabanlı eski useReveal, React 18 concurrent rendering
 * ile uyumsuz: useGSAP scope cleanup sırasında ScrollTrigger.batch DOM
 * node'larını kaldırmaya çalışırken "removeChild: not a child of this node"
 * hatası fırlatıyor. Yeni implement sadece ref döner; animasyonlar
 * framer-motion `whileInView` ile yapılıyor (component içinde inline).
 *
 * Hook imza aynı kaldığı için mevcut kullanımlar kırılmaz — sadece data-reveal
 * etkisi artık framer-motion'a düşüyor.
 */
export function useReveal<T extends HTMLElement = HTMLElement>(_options?: {
 selector?: string;
 y?: number;
 stagger?: number;
 start?: string;
}) {
 return useRef<T>(null);
}

/**
 * Word-by-word reveal: GSAP SplitText premium plugin olduğu için
 * dependency-free splitter kullanıyordu. React 18'de innerHTML set etmek
 * virtual DOM uyumsuzluğu yaratıyor. Bu fonksiyon artık no-op; başlıklar
 * framer-motion `motion.span` ile kelime-kelime animasyon alıyor.
 */
export function useWordReveal<T extends HTMLElement = HTMLElement>(_active = true) {
 return useRef<T>(null);
}
