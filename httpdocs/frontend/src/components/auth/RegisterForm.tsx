import { useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { api } from "@/lib/api";
import { getBootstrap } from "@/lib/bootstrap";

const countries = [
  { dial: "+90", code: "tr", label: "Türkiye" },
  { dial: "+49", code: "de", label: "Almanya" },
  { dial: "+44", code: "gb", label: "Birleşik Krallık" },
  { dial: "+33", code: "fr", label: "Fransa" },
  { dial: "+31", code: "nl", label: "Hollanda" },
  { dial: "+39", code: "it", label: "İtalya" },
  { dial: "+34", code: "es", label: "İspanya" },
  { dial: "+1", code: "us", label: "ABD" },
];

const slugify = (s: string) =>
  s
    .toLowerCase()
    .replace(/ğ/g, "g")
    .replace(/ü/g, "u")
    .replace(/ş/g, "s")
    .replace(/ı/g, "i")
    .replace(/ö/g, "o")
    .replace(/ç/g, "c")
    .replace(/[\s_]+/g, "-")
    .replace(/[^a-z0-9-]/g, "")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 40);

const isValidSlug = (s: string) =>
  /^[a-z0-9][a-z0-9-]{0,38}[a-z0-9]$|^[a-z0-9]{2,40}$/.test(s);

type Step = 1 | 2 | 3;

export function RegisterForm() {
  const [step, setStep] = useState<Step>(1);
  const bootstrap = getBootstrap();
  const [csrf, setCsrf] = useState(bootstrap.csrfToken);
  const [flashError, setFlashError] = useState<string | null>(bootstrap.flash.error);

  // Step 1
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [subdomain, setSubdomain] = useState("");
  const [subdomainManual, setSubdomainManual] = useState(false);
  const [subdomainStatus, setSubdomainStatus] = useState<
    "idle" | "checking" | "available" | "taken" | "invalid"
  >("idle");
  const subdomainTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Step 2
  const [email, setEmail] = useState("");
  const [emailCode, setEmailCode] = useState("");
  const [emailSending, setEmailSending] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [emailVerified, setEmailVerified] = useState(false);
  const [emailError, setEmailError] = useState<string | null>(null);

  const [country, setCountry] = useState(countries[0]);
  const [countryOpen, setCountryOpen] = useState(false);
  const [phone, setPhone] = useState("");
  const [phoneCode, setPhoneCode] = useState("");
  const [phoneSending, setPhoneSending] = useState(false);
  const [phoneSent, setPhoneSent] = useState(false);
  const [phoneVerified, setPhoneVerified] = useState(false);
  const [phoneError, setPhoneError] = useState<string | null>(null);

  // Step 3
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [acceptTerms, setAcceptTerms] = useState(false);

  useEffect(() => {
    api.refreshCsrf().then((d) => d?.token && setCsrf(d.token)).catch(() => void 0);
  }, []);

  // Auto-suggest subdomain from company name until user types their own.
  useEffect(() => {
    if (!subdomainManual) setSubdomain(slugify(companyName));
  }, [companyName, subdomainManual]);

  // Async availability check
  useEffect(() => {
    if (!subdomain) {
      setSubdomainStatus("idle");
      return;
    }
    if (!isValidSlug(subdomain)) {
      setSubdomainStatus("invalid");
      return;
    }
    setSubdomainStatus("checking");
    if (subdomainTimer.current) clearTimeout(subdomainTimer.current);
    subdomainTimer.current = setTimeout(async () => {
      try {
        const d = await api.checkSubdomain(subdomain);
        setSubdomainStatus(d.available ? "available" : "taken");
      } catch {
        setSubdomainStatus("available"); // optimistic — form validates again on submit
      }
    }, 450);
  }, [subdomain]);

  const step1Valid = useMemo(
    () =>
      firstName.trim() &&
      lastName.trim() &&
      companyName.trim() &&
      subdomainStatus === "available",
    [firstName, lastName, companyName, subdomainStatus]
  );

  const step2Valid = emailVerified && phoneVerified;

  const pwChecks = {
    len: password.length >= 8,
    up: /[A-Z]/.test(password),
    low: /[a-z]/.test(password),
    num: /[0-9]/.test(password),
    sp: /[^\w\s]/.test(password),
  };
  const pwScore = Object.values(pwChecks).filter(Boolean).length;
  const pwMatch = password && password === confirm;

  const step3Valid = pwScore === 5 && pwMatch && acceptTerms;

  async function sendEmailCode() {
    if (emailSending) return;
    setEmailError(null);
    if (!email.trim()) {
      setEmailError("E-posta adresi girin");
      return;
    }
    setEmailSending(true);
    try {
      const d = await api.sendEmailCode(email.trim().toLowerCase());
      if (d.success) setEmailSent(true);
      else setEmailError(d.error ?? "Hata oluştu");
    } catch (e) {
      setEmailError((e as Error).message || "Bağlantı hatası");
    } finally {
      setEmailSending(false);
    }
  }

  async function verifyEmail() {
    if (!emailCode) return;
    setEmailError(null);
    try {
      const d = await api.verifyEmail(email.trim().toLowerCase(), emailCode);
      if (d.success && d.verified) setEmailVerified(true);
      else setEmailError(d.error ?? "Geçersiz kod");
    } catch (e) {
      setEmailError((e as Error).message || "Bağlantı hatası");
    }
  }

  async function sendPhoneCode() {
    if (phoneSending) return;
    setPhoneError(null);
    if (!phone || phone.length < 8) {
      setPhoneError("Telefon numarası girin");
      return;
    }
    if (country.dial === "+90" && phone[0] !== "5") {
      setPhoneError("Türkiye numarası 5 ile başlamalıdır");
      return;
    }
    setPhoneSending(true);
    try {
      const d = await api.sendPhoneCode(phone, country.dial);
      if (d.success) setPhoneSent(true);
      else setPhoneError(d.error ?? "Hata oluştu");
    } catch (e) {
      setPhoneError((e as Error).message || "Bağlantı hatası");
    } finally {
      setPhoneSending(false);
    }
  }

  async function verifyPhone() {
    if (!phoneCode) return;
    setPhoneError(null);
    try {
      const d = await api.verifyPhone(phone, country.dial, phoneCode);
      if (d.success && d.verified) setPhoneVerified(true);
      else setPhoneError(d.error ?? "Geçersiz kod");
    } catch (e) {
      setPhoneError((e as Error).message || "Bağlantı hatası");
    }
  }

  const nextDisabled =
    (step === 1 && !step1Valid) ||
    (step === 2 && !step2Valid) ||
    (step === 3 && !step3Valid);

  return (
    <form method="POST" action="/register" className="w-full">
      <input type="hidden" name="csrf_token" value={csrf} />
      <input type="hidden" name="first_name" value={firstName} />
      <input type="hidden" name="last_name" value={lastName} />
      <input type="hidden" name="company_name" value={companyName} />
      <input type="hidden" name="subdomain" value={subdomain} />
      <input type="hidden" name="email" value={email} />
      <input type="hidden" name="country_code" value={country.dial} />
      <input type="hidden" name="phone" value={phone} />
      <input type="hidden" name="password" value={password} />
      <input type="hidden" name="password_confirm" value={confirm} />
      <input type="hidden" name="acceptTerms" value={acceptTerms ? "1" : ""} />

      <Stepper step={step} />

      <AnimatePresence>
        {flashError && (
          <motion.div
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="mb-6 flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700"
          >
            <span className="text-red-500 flex-shrink-0 mt-0.5">
              <Icon name="close" size={16} strokeWidth={3} />
            </span>
            <span className="flex-1">{flashError}</span>
            <button
              type="button"
              onClick={() => setFlashError(null)}
              className="text-red-400 hover:text-red-600"
              aria-label="Kapat"
            >
              <Icon name="close" size={14} />
            </button>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence mode="wait">
        {step === 1 && (
          <motion.div
            key="s1"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
            className="space-y-4"
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="q-label">Ad</label>
                <input
                  className="q-input"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  placeholder="Adınız"
                  required
                />
              </div>
              <div>
                <label className="q-label">Soyad</label>
                <input
                  className="q-input"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  placeholder="Soyadınız"
                  required
                />
              </div>
            </div>

            <div>
              <label className="q-label">İşletme Adı</label>
              <input
                className="q-input"
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                placeholder="Örn. Kafe İstanbul"
                required
              />
            </div>

            <div>
              <label className="q-label">
                Kısa Bağlantı <span className="text-ink-500 normal-case tracking-normal">— personel giriş adresi</span>
              </label>
              <div className="q-input-group">
                <span className="q-addon q-addon-left font-mono text-brand-600">
                  qordy.com/
                </span>
                <input
                  className="q-input"
                  value={subdomain}
                  onChange={(e) => {
                    setSubdomainManual(true);
                    setSubdomain(slugify(e.target.value));
                  }}
                  placeholder="kafe-istanbul"
                  maxLength={40}
                />
                <SubdomainStatus status={subdomainStatus} />
              </div>
              <p className="mt-2 text-xs text-ink-500">
                Sadece küçük harf, rakam ve tire. Örnek: <span className="text-brand-600">cafe-istanbul</span>
              </p>
            </div>
          </motion.div>
        )}

        {step === 2 && (
          <motion.div
            key="s2"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
            className="space-y-6"
          >
            {/* E-mail */}
            <div>
              <label className="q-label">E-posta</label>
              <div className="q-input-group">
                <input
                  className="q-input"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  readOnly={emailVerified}
                  placeholder="ornek@email.com"
                  autoComplete="email"
                />
                {!emailVerified && (
                  <button
                    type="button"
                    className="q-addon-button"
                    onClick={sendEmailCode}
                    disabled={emailSending}
                  >
                    {emailSending ? "Gönderiliyor…" : emailSent ? "Tekrar gönder" : "Kod gönder"}
                  </button>
                )}
              </div>

              {emailSent && !emailVerified && (
                <div className="mt-3 q-input-group">
                  <input
                    className="q-input"
                    inputMode="numeric"
                    maxLength={6}
                    value={emailCode}
                    onChange={(e) => setEmailCode(e.target.value.replace(/\D/g, ""))}
                    placeholder="6 haneli kod"
                  />
                  <button
                    type="button"
                    className="q-addon-button"
                    onClick={verifyEmail}
                    disabled={emailCode.length !== 6}
                  >
                    Doğrula
                  </button>
                </div>
              )}
              {emailVerified && (
                <VerifiedBadge>E-posta doğrulandı</VerifiedBadge>
              )}
              {emailError && (
                <p className="mt-2 text-xs text-red-500">{emailError}</p>
              )}
            </div>

            {/* Phone */}
            <AnimatePresence initial={false}>
              {emailVerified && (
                <motion.div
                  initial={{ opacity: 0, height: 0 }}
                  animate={{ opacity: 1, height: "auto" }}
                  exit={{ opacity: 0, height: 0 }}
                  className="overflow-hidden"
                >
                  <div className="pt-4 border-t border-ink-100">
                    <div className="flex items-start gap-2 p-3 rounded-xl bg-brand-50 border border-brand-100 text-xs text-ink-600 mb-4">
                      <Icon name="whatsapp" size={16} className="text-success-600 flex-shrink-0 mt-0.5" />
                      <span>
                        WhatsApp hesabı olan numaranızı girin. Doğrulama kodu WhatsApp ile gönderilecektir.
                      </span>
                    </div>
                    <label className="q-label">Telefon</label>
                    <div className="relative">
                      <div className="q-input-group">
                        <button
                          type="button"
                          onClick={() => setCountryOpen((o) => !o)}
                          className="q-addon q-addon-left flex items-center gap-2 hover:bg-ink-100 transition-colors disabled:cursor-not-allowed"
                          disabled={phoneVerified}
                        >
                          <img
                            src={`https://flagcdn.com/w20/${country.code}.png`}
                            alt=""
                            width={20}
                            className="rounded"
                          />
                          <span className="text-sm font-medium text-ink-800">{country.dial}</span>
                          <Icon name="chevronDown" size={12} className="text-ink-400" />
                        </button>
                        <input
                          className="q-input"
                          inputMode="numeric"
                          maxLength={15}
                          value={phone}
                          readOnly={phoneVerified}
                          onChange={(e) => {
                            let v = e.target.value.replace(/\D/g, "");
                            if (country.dial === "+90") {
                              v = v.slice(0, 10);
                              if (v && v[0] !== "5") v = "5" + v.slice(1);
                            } else {
                              v = v.slice(0, 15);
                            }
                            setPhone(v);
                          }}
                          placeholder={country.dial === "+90" ? "5321234567" : "Numara"}
                        />
                      </div>
                      <AnimatePresence>
                        {countryOpen && (
                          <motion.div
                            initial={{ opacity: 0, y: -4 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -4 }}
                            className="absolute z-20 mt-2 w-64 max-h-60 overflow-y-auto rounded-xl bg-white border border-ink-200 shadow-2xl"
                          >
                            {countries.map((c) => (
                              <button
                                type="button"
                                key={c.code}
                                onClick={() => {
                                  setCountry(c);
                                  setCountryOpen(false);
                                }}
                                className="w-full flex items-center gap-2 px-3 py-2 text-sm text-ink-700 hover:bg-brand-50 hover:text-ink-900"
                              >
                                <img src={`https://flagcdn.com/w20/${c.code}.png`} alt="" width={20} className="rounded" />
                                <span>{c.label}</span>
                                <span className="ml-auto text-ink-400">{c.dial}</span>
                              </button>
                            ))}
                          </motion.div>
                        )}
                      </AnimatePresence>
                    </div>

                    {!phoneVerified && !phoneSent && (
                      <div className="mt-3">
                        <Button
                          type="button"
                          variant="ghost"
                          size="md"
                          onClick={sendPhoneCode}
                          disabled={phoneSending || phone.length < 8}
                          icon={<Icon name="whatsapp" size={16} />}
                        >
                          {phoneSending ? "Gönderiliyor…" : "WhatsApp ile kod gönder"}
                        </Button>
                      </div>
                    )}

                    {phoneSent && !phoneVerified && (
                      <div className="mt-3 q-input-group">
                        <input
                          className="q-input"
                          inputMode="numeric"
                          maxLength={6}
                          value={phoneCode}
                          onChange={(e) => setPhoneCode(e.target.value.replace(/\D/g, ""))}
                          placeholder="6 haneli kod"
                        />
                        <button
                          type="button"
                          className="q-addon-button"
                          onClick={verifyPhone}
                          disabled={phoneCode.length !== 6}
                        >
                          Doğrula
                        </button>
                      </div>
                    )}

                    {phoneVerified && <VerifiedBadge>Telefon doğrulandı</VerifiedBadge>}
                    {phoneError && <p className="mt-2 text-xs text-red-500">{phoneError}</p>}
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </motion.div>
        )}

        {step === 3 && (
          <motion.div
            key="s3"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
            className="space-y-5"
          >
            <div>
              <label className="q-label">Şifre</label>
              <input
                type="password"
                className="q-input"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Güçlü bir şifre"
                minLength={8}
                required
              />
              {password && (
                <div className="mt-3">
                  <div className="h-1.5 rounded-full bg-ink-100 overflow-hidden">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${(pwScore / 5) * 100}%` }}
                      transition={{ type: "spring", stiffness: 220, damping: 26 }}
                      className={`h-full rounded-full ${
                        pwScore <= 2
                          ? "bg-red-500"
                          : pwScore <= 4
                          ? "bg-brand-400"
                          : "bg-success-500"
                      }`}
                    />
                  </div>
                  <div className="mt-3 grid grid-cols-2 gap-1.5 text-xs">
                    {[
                      { ok: pwChecks.len, label: "En az 8 karakter" },
                      { ok: pwChecks.up, label: "Büyük harf" },
                      { ok: pwChecks.low, label: "Küçük harf" },
                      { ok: pwChecks.num, label: "Rakam" },
                      { ok: pwChecks.sp, label: "Özel karakter" },
                    ].map((r) => (
                      <span
                        key={r.label}
                        className={`inline-flex items-center gap-1.5 ${
                          r.ok ? "text-success-600" : "text-ink-400"
                        }`}
                      >
                        <Icon name={r.ok ? "check" : "close"} size={12} />
                        {r.label}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <div>
              <label className="q-label">Şifre tekrar</label>
              <input
                type="password"
                className="q-input"
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                placeholder="Şifrenizi tekrar yazın"
                minLength={8}
                required
              />
              {confirm && (
                <p
                  className={`mt-2 text-xs ${
                    pwMatch ? "text-success-600" : "text-red-500"
                  }`}
                >
                  {pwMatch ? "✓ Şifreler eşleşiyor" : "✗ Şifreler eşleşmiyor"}
                </p>
              )}
            </div>

            <label className="flex items-start gap-3 text-sm text-ink-600 cursor-pointer">
              <input
                type="checkbox"
                checked={acceptTerms}
                onChange={(e) => setAcceptTerms(e.target.checked)}
                className="mt-1 h-4 w-4 rounded border-ink-300 bg-white accent-brand-600"
              />
              <span>
                <Link to="/gizlilik" className="text-brand-600 hover:underline">Gizlilik Politikası</Link>,{" "}
                <Link to="/kullanim-sartlari" className="text-brand-600 hover:underline">Kullanım Koşulları</Link> ve{" "}
                <a href="/sayfa/mesafeli-satis-sozlesmesi" target="_blank" rel="noopener noreferrer" className="text-brand-600 hover:underline">Mesafeli Satış Sözleşmesi</a>'ni okudum, kabul ediyorum.
              </span>
            </label>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="mt-8 flex items-center gap-3">
        {step > 1 && (
          <Button
            type="button"
            variant="ghost"
            size="md"
            onClick={() => setStep((s) => (s - 1) as Step)}
          >
            Geri
          </Button>
        )}
        {step < 3 ? (
          <Button
            type="button"
            variant="ember"
            size="md"
            fullWidth={step === 1}
            onClick={() => setStep((s) => (s + 1) as Step)}
            disabled={nextDisabled}
            iconRight={<Icon name="arrow" size={16} />}
          >
            Devam Et
          </Button>
        ) : (
          <Button
            type="submit"
            variant="ember"
            size="md"
            fullWidth
            disabled={nextDisabled}
            iconRight={<Icon name="arrow" size={16} />}
          >
            Ücretsiz denemeyi başlat
          </Button>
        )}
      </div>

      <p className="mt-8 text-center text-sm text-ink-500">
        Zaten hesabınız var mı?{" "}
        <Link to="/login" className="text-brand-600 font-medium hover:underline">
          Giriş yapın
        </Link>
      </p>
    </form>
  );
}

function Stepper({ step }: { step: Step }) {
  const labels = ["Bilgiler", "Doğrulama", "Şifre"];
  return (
    <div className="mb-8 flex items-center gap-2">
      {labels.map((label, i) => {
        const n = (i + 1) as Step;
        const active = step === n;
        const done = step > n;
        return (
          <div key={label} className="flex items-center gap-2 flex-1">
            <span
              className={`h-8 w-8 flex-shrink-0 rounded-full flex items-center justify-center text-xs font-semibold transition-colors ${
                active
                  ? "bg-brand-600 text-white shadow-[0_0_0_4px_rgba(27,102,201,0.2)]"
                  : done
                  ? "bg-brand-100 text-brand-600 border border-brand-200"
                  : "bg-ink-100 text-ink-400 border border-ink-200"
              }`}
            >
              {done ? <Icon name="check" size={14} strokeWidth={3} /> : n}
            </span>
            <span
              className={`text-xs uppercase tracking-[0.18em] ${
                active ? "text-ink-800" : "text-ink-400"
              }`}
            >
              {label}
            </span>
            {i < labels.length - 1 && (
              <span className="flex-1 h-px bg-ink-200 ml-2" />
            )}
          </div>
        );
      })}
    </div>
  );
}

function SubdomainStatus({ status }: { status: string }) {
  if (status === "idle") return null;
  const map: Record<string, { label: string; color: string }> = {
    checking: { label: "Kontrol ediliyor…", color: "text-ink-500" },
    available: { label: "✓ Uygun", color: "text-success-600" },
    taken: { label: "✗ Alınmış", color: "text-red-500" },
    invalid: { label: "Geçersiz", color: "text-red-500" },
  };
  const item = map[status];
  if (!item) return null;
  return (
    <span
      className={`q-addon q-addon-right text-xs font-medium ${item.color}`}
    >
      {item.label}
    </span>
  );
}

function VerifiedBadge({ children }: { children: React.ReactNode }) {
  return (
    <div className="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-success-50 border border-success-100 text-success-600 text-xs font-medium">
      <Icon name="check" size={14} strokeWidth={3} />
      {children}
    </div>
  );
}
