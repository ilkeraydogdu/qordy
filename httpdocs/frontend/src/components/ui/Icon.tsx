import type { SVGProps } from "react";

// A small, curated icon set drawn on a 24 × 24 grid with consistent
// stroke weight so that the marketing site doesn't look like "AI gave
// me random icons".
const paths = {
  qr: (
    <>
      <rect x="3" y="3" width="7" height="7" rx="1.5" />
      <rect x="14" y="3" width="7" height="7" rx="1.5" />
      <rect x="3" y="14" width="7" height="7" rx="1.5" />
      <path d="M14 14h3v3h-3zM20 14h1v3M14 20h3v1M20 20h1" />
    </>
  ),
  pos: (
    <>
      <rect x="3" y="6" width="18" height="12" rx="2" />
      <path d="M3 10h18M7 14h2m4 0h4" />
    </>
  ),
  kitchen: (
    <>
      <path d="M6 3h12l-1 4H7L6 3Z" />
      <path d="M7 7v14M17 7v14M4 21h16" />
    </>
  ),
  stock: (
    <>
      <path d="M4 7h16v13H4zM4 7l2-3h12l2 3" />
      <path d="M9 11h6" />
    </>
  ),
  finance: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v10M9 10h4a2 2 0 1 1 0 4h-2a2 2 0 1 0 0 4h5" />
    </>
  ),
  chart: (
    <>
      <path d="M4 20V10M10 20V4M16 20v-8M22 20H2" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="3" />
      <path d="M3 20c0-3 3-5 6-5s6 2 6 5" />
      <circle cx="17" cy="9" r="2.5" />
      <path d="M15 20c0-2 2-4 5-4" />
    </>
  ),
  calendar: (
    <>
      <rect x="3" y="5" width="18" height="16" rx="2" />
      <path d="M3 10h18M8 3v4M16 3v4" />
    </>
  ),
  bolt: <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8Z" />,
  shield: (
    <>
      <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z" />
      <path d="M9 12l2 2 4-4" />
    </>
  ),
  check: <path d="M5 12l4 4L19 7" />,
  arrow: <path d="M5 12h14M13 6l6 6-6 6" />,
  chef: (
    <>
      <path d="M7 11a4 4 0 1 1 4-7 4 4 0 0 1 7 2 4 4 0 0 1-1 8v5H6v-5a4 4 0 0 1-1-8 4 4 0 0 1 2-2" />
      <path d="M7 16h10" />
    </>
  ),
  plate: (
    <>
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="5" />
    </>
  ),
  phone: (
    <>
      <rect x="7" y="2" width="10" height="20" rx="2" />
      <path d="M10 18h4" />
    </>
  ),
  mail: (
    <>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 7l9 6 9-6" />
    </>
  ),
  location: (
    <>
      <path d="M12 21c-4-4-7-7.5-7-11a7 7 0 1 1 14 0c0 3.5-3 7-7 11Z" />
      <circle cx="12" cy="10" r="2.5" />
    </>
  ),
  bell: (
    <>
      <path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2Z" />
      <path d="M10 20a2 2 0 0 0 4 0" />
    </>
  ),
  download: <path d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14" />,
  flame: (
    <path d="M12 3s4 4 4 8a4 4 0 1 1-8 0c0-1.5.5-2.5 1.5-3.5C10 6 9 4.5 12 3Z" />
  ),
  close: <path d="M6 6l12 12M18 6 6 18" />,
  chevronDown: <path d="M6 9l6 6 6-6" />,
  chevronRight: <path d="M9 6l6 6-6 6" />,
  star: (
    <path d="M12 3l2.9 6 6.6.6-5 4.6 1.5 6.5L12 17.8 5.9 20.7l1.6-6.5L2.5 9.6 9.1 9 12 3Z" />
  ),
  sparkle: (
    <path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8" />
  ),
  menu: <path d="M4 6h16M4 12h16M4 18h16" />,
 play: <path d="M6 4l14 8-14 8V4Z" />,
  heart: (
    <path d="M12 21s-7-5-7-11a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 6-7 11-7 11Z" />
  ),
  whatsapp: (
    <>
      <path d="M12 3a9 9 0 0 0-7.7 13.7L3 21l4.5-1.2A9 9 0 1 0 12 3Z" />
      <path d="M8.5 9.5c0 4 4 6.5 6.8 6.8.7 0 2-.3 2-1.4v-1.3l-2-.8-.9 1c-1-.4-2.2-1.5-2.6-2.6l1-.9-.8-2h-1.3c-.7 0-1.2.7-1.2 1.2Z" />
    </>
  ),
 touch: (
 <>
 <path d="M9 11V6.5a2 2 0 1 1 4 0V12" />
 <path d="M13 9a2 2 0 1 1 4 0v3" />
 <path d="M17 10.5a2 2 0 1 1 3 1.5V16a6 6 0 0 1-6 6h-2a6 6 0 0 1-6-6v-2.5a2 2 0 0 1 3-1.5" />
 </>
 ),
 globe: (
 <>
 <circle cx="12" cy="12" r="9" />
 <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
 </>
 ),
 share: (
 <>
 <circle cx="6" cy="12" r="2.5" />
 <circle cx="18" cy="6" r="2.5" />
 <circle cx="18" cy="18" r="2.5" />
 <path d="M8.2 11 15.8 7M8.2 13l7.6 4" />
 </>
 ),
 add: (
 <>
 <path d="M12 5v14M5 12h14" />
 </>
 ),
 shop: (
 <>
 <path d="M3 9h18l-2 11H5L3 9Z" />
 <path d="M8 9V5a4 4 0 0 1 8 0v4" />
 </>
 ),
  sun: (
    <>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4" />
    </>
  ),
  moon: <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />,
  eye: (
    <>
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </>
  ),
  eyeOff: (
    <>
      <path d="M9.9 4.2A10.9 10.9 0 0 1 12 4c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.3 4.1M6.6 6.6A17.6 17.6 0 0 0 2 11s3.5 7 10 7a10.9 10.9 0 0 0 4.1-.8" />
      <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18" />
    </>
  ),
};

type IconName = keyof typeof paths;

interface Props extends SVGProps<SVGSVGElement> {
  name: IconName;
  size?: number | string;
}

export function Icon({ name, size = 20, strokeWidth = 1.75, className, ...rest }: Props) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
      {...rest}
    >
      {paths[name]}
    </svg>
  );
}
