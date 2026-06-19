import type { Package } from "@/lib/api";

/**
 * Server-injected bootstrap payload (see app/views/react_app.php).
 * Lets the SPA paint with real packages, CSRF and flash messages on
 * the very first frame instead of after an API round-trip.
 */
export interface QordyTrialSettings {
  enabled: boolean;
  duration_days: number;
  data_retention_days: number;
}

export interface QordyBootstrap {
  csrfToken: string;
  baseUrl: string;
  flash: {
    error: string | null;
    success: string | null;
    warning?: string | null;
    info?: string | null;
  };
  packages: Package[] | null;
  trial: QordyTrialSettings;
  oldInput: Record<string, unknown> | null;
}

declare global {
  interface Window {
    __QORDY__?: Partial<QordyBootstrap>;
  }
}

const defaultTrial: QordyTrialSettings = {
  enabled: true,
  duration_days: 14,
  data_retention_days: 37,
};

const fallback: QordyBootstrap = {
  csrfToken: "",
  baseUrl: "",
  flash: { error: null, success: null },
  packages: null,
  trial: defaultTrial,
  oldInput: null,
};

export function getBootstrap(): QordyBootstrap {
  if (typeof window === "undefined") return fallback;
  const raw = window.__QORDY__ ?? {};
  const rawTrial = (raw as Partial<QordyBootstrap>).trial;
  return {
    csrfToken: raw.csrfToken ?? "",
    baseUrl: raw.baseUrl ?? "",
    flash: {
      error: raw.flash?.error ?? null,
      success: raw.flash?.success ?? null,
      warning: raw.flash?.warning ?? null,
      info: raw.flash?.info ?? null,
    },
    packages: raw.packages ?? null,
    trial: {
      enabled: rawTrial?.enabled ?? defaultTrial.enabled,
      duration_days:
        typeof rawTrial?.duration_days === "number" && rawTrial.duration_days > 0
          ? rawTrial.duration_days
          : defaultTrial.duration_days,
      data_retention_days:
        typeof rawTrial?.data_retention_days === "number" && rawTrial.data_retention_days > 0
          ? rawTrial.data_retention_days
          : defaultTrial.data_retention_days,
    },
    oldInput: raw.oldInput ?? null,
  };
}

export function consumeFlash(): { error: string | null; success: string | null } {
  if (typeof window === "undefined") return { error: null, success: null };
  const raw = window.__QORDY__ ?? {};
  const flash = {
    error: raw.flash?.error ?? null,
    success: raw.flash?.success ?? null,
  };
  if (window.__QORDY__) window.__QORDY__.flash = { error: null, success: null };
  return flash;
}
