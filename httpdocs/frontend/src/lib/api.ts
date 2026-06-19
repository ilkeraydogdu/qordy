// API client for the existing Qordy PHP backend.
// All endpoints are served by the same origin in production.

const BASE = "";

export interface Package {
  id?: string | number;
  package_id?: string | number;
  name: string;
  description?: string;
  desc?: string;
  price_monthly?: number | string;
  price_yearly?: number | string;
  monthly?: number | string;
  yearly?: number | string;
  is_featured?: boolean | number;
  popular?: boolean | number;
  features_array?: Array<string | { name?: string; title?: string }>;
}

async function asJson<T>(res: Response): Promise<T> {
  if (!res.ok) {
    let msg = res.statusText;
    try {
      const body = (await res.json()) as { error?: string };
      if (body?.error) msg = body.error;
    } catch {
      /* noop */
    }
    throw new Error(msg);
  }
  return res.json() as Promise<T>;
}

export const api = {
  // Public (no CSRF)
  getPackages: (): Promise<{ packages: Package[] }> =>
    fetch(`${BASE}/api/packages`, {
      headers: { Accept: "application/json" },
    }).then((r) => asJson(r)),

  // CSRF lifecycle. Backend returns `{ csrf_token, token }` — the `token`
  // alias is kept for older SPA builds that destructure it.
  refreshCsrf: (): Promise<{ token: string; csrf_token?: string }> =>
    fetch(`${BASE}/api/csrf-token`, {
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => asJson<{ token?: string; csrf_token?: string }>(r))
      .then((d) => ({ token: d.token ?? d.csrf_token ?? "", csrf_token: d.csrf_token })),

  // Contact form
  submitContact: (
    payload: {
      name: string;
      email: string;
      subject: string;
      message: string;
      captcha?: string;
      captcha_id?: string;
    },
    csrf: string
  ) =>
    fetch(`${BASE}/api/contact/submit`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-Token": csrf,
      },
      body: JSON.stringify(payload),
    }).then((r) => asJson<{ success: boolean; error?: string }>(r)),

  // Register: 3-step verification
  checkSubdomain: (subdomain: string) =>
    fetch(
      `${BASE}/api/register/check-subdomain?subdomain=${encodeURIComponent(subdomain)}`,
      { headers: { "X-Requested-With": "XMLHttpRequest" } }
    ).then((r) => asJson<{ available: boolean; error?: string }>(r)),

  sendEmailCode: (email: string) =>
    fetch(`${BASE}/api/register/send-email-code`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ email }),
    }).then((r) => asJson<{ success: boolean; error?: string }>(r)),

  verifyEmail: (email: string, code: string) =>
    fetch(`${BASE}/api/register/verify-email`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ email, code }),
    }).then((r) =>
      asJson<{ success: boolean; verified?: boolean; error?: string }>(r)
    ),

  sendPhoneCode: (phone: string, countryCode: string) =>
    fetch(`${BASE}/api/register/send-phone-code`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ phone, country_code: countryCode }),
    }).then((r) => asJson<{ success: boolean; error?: string }>(r)),

  verifyPhone: (phone: string, countryCode: string, code: string) =>
    fetch(`${BASE}/api/register/verify-phone`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ phone, country_code: countryCode, code }),
    }).then((r) =>
      asJson<{ success: boolean; verified?: boolean; error?: string }>(r)
    ),
};

export function csrfFromDom(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? "";
}
