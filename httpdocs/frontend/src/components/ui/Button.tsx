import { motion } from "framer-motion";
import type { ComponentPropsWithoutRef, ReactNode } from "react";

type Variant = "primary" | "ember" | "ghost" | "cream" | "lime";

interface BaseProps {
 variant?: Variant;
 size?: "sm" | "md" | "lg" | "xl";
 icon?: ReactNode;
 iconRight?: ReactNode;
 fullWidth?: boolean;
 children?: ReactNode;
}

const variantClass: Record<Variant, string> = {
 primary: "btn-primary",
 ember: "btn-primary",
 ghost: "btn-ghost",
 cream: "btn-cream",
 lime: "btn-accent",
};

const sizeClass: Record<NonNullable<BaseProps["size"]>, string> = {
 sm: "text-sm py-2 px-4",
 md: "text-sm",
 lg: "text-base py-[1.1rem] px-7",
 xl: "text-lg py-[1.2rem] px-8",
};

function buttonClass({ variant = "ember", size = "md", fullWidth }: BaseProps) {
 return [
 "btn-base",
 variantClass[variant],
 sizeClass[size],
 fullWidth ? "w-full" : "",
 ]
 .filter(Boolean)
 .join(" ");
}

export function Button({
 variant = "ember",
 size = "md",
 icon,
 iconRight,
 fullWidth,
 children,
 ...rest
}: BaseProps & ComponentPropsWithoutRef<"button">) {
 return (
 <motion.button
 whileHover={{ y: -2 }}
 whileTap={{ y: 0, scale: 0.98 }}
 transition={{ type: "spring", stiffness: 400, damping: 28 }}
 className={buttonClass({ variant, size, fullWidth, children })}
 {...(rest as ComponentPropsWithoutRef<typeof motion.button>)}
 >
 {icon}
 <span>{children}</span>
 {iconRight}
 </motion.button>
 );
}

interface LinkBtnProps extends BaseProps, ComponentPropsWithoutRef<"a"> {}

export function LinkButton({
 variant = "ember",
 size = "md",
 icon,
 iconRight,
 fullWidth,
 children,
 ...rest
}: LinkBtnProps) {
 return (
 <motion.a
 whileHover={{ y: -2 }}
 whileTap={{ y: 0, scale: 0.98 }}
 transition={{ type: "spring", stiffness: 400, damping: 28 }}
 className={buttonClass({ variant, size, fullWidth, children })}
 {...(rest as ComponentPropsWithoutRef<typeof motion.a>)}
 >
 {icon}
 <span>{children}</span>
 {iconRight}
 </motion.a>
 );
}
