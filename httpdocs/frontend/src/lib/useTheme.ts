import { useEffect, useState } from "react";

export type Theme = "light" | "dark";

const STORAGE_KEY = "qordy-theme";

/**
 * Theme state for the marketing surface.
 *
 * The dark canvas is only fully styled on the landing page, so the toggle
 * lives there. The choice is persisted in localStorage and re-read on mount
 * so the landing remembers the visitor's preference between sessions.
 */
export function useTheme(): [Theme, () => void, (t: Theme) => void] {
  const [theme, setTheme] = useState<Theme>(() => {
    if (typeof window === "undefined") return "light";
    return window.localStorage.getItem(STORAGE_KEY) === "dark" ? "dark" : "light";
  });

  useEffect(() => {
    try {
      window.localStorage.setItem(STORAGE_KEY, theme);
    } catch {
      /* storage unavailable — ignore */
    }
  }, [theme]);

  const toggle = () => setTheme((t) => (t === "dark" ? "light" : "dark"));
  return [theme, toggle, setTheme];
}
