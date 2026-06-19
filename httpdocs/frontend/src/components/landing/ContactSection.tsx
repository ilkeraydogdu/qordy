import { motion } from "framer-motion";
import { useState } from "react";
import { Icon } from "@/components/ui/Icon";
import { api } from "@/lib/api";
import { btnPrimary, surface, useInViewAnim } from "./primitives";

const CONTACT_CHANNELS = [
  { icon: "mail", k: "E-posta", v: "destek@qordy.com", href: "mailto:destek@qordy.com" },
  { icon: "phone", k: "Telefon", v: "+90 (212) 555 00 00", href: "tel:+902125550000" },
  { icon: "location", k: "Adres", v: "Maslak, İstanbul", href: null },
] as const;

const inputCls =
  "w-full rounded-xl px-4 py-3 text-sm transition-colors bg-white border border-ink-200 text-ink-800 placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15 dark:bg-white/[0.04] dark:border-white/[0.1] dark:text-ink-100 dark:placeholder:text-ink-500";
const labelCls = "block text-xs font-semibold text-ink-600 dark:text-ink-300 mb-1.5";

function ContactForm() {
  const [s, setS] = useState<"idle" | "sending" | "ok" | "err">("idle");
  const [err, setErr] = useState("");

  const submit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setS("sending");
    setErr("");
    try {
      const fd = new FormData(e.currentTarget);
      const csrf = await api.refreshCsrf();
      await api.submitContact(
        {
          name: String(fd.get("name") ?? ""),
          email: String(fd.get("email") ?? ""),
          subject: "Demo",
          message: String(fd.get("message") ?? ""),
        },
        typeof csrf === "string" ? csrf : csrf.token
      );
      setS("ok");
    } catch (e) {
      setS("err");
      setErr(e instanceof Error ? e.message : "Bir hata oluştu");
    }
  };

  return (
    <form onSubmit={submit} className={`p-6 sm:p-8 space-y-5 ${surface}`}>
      {s === "ok" && (
        <div className="rounded-xl bg-success-500/10 border border-success-500/20 px-4 py-3 text-sm text-success-600 dark:text-success-500">
          Mesajınız alındı! 24 saat içinde dönüş yapacağız.
        </div>
      )}
      {s === "err" && (
        <div className="rounded-xl bg-red-500/10 border border-red-500/20 px-4 py-3 text-sm text-red-500">{err}</div>
      )}
      <div className="grid sm:grid-cols-2 gap-4">
        <div>
          <label htmlFor="c-name" className={labelCls}>Ad Soyad</label>
          <input id="c-name" name="name" required className={inputCls} placeholder="Adınız Soyadınız" />
        </div>
        <div>
          <label htmlFor="c-email" className={labelCls}>E-posta</label>
          <input id="c-email" name="email" type="email" required className={inputCls} placeholder="ornek@isletme.com" />
        </div>
      </div>
      <div>
        <label htmlFor="c-message" className={labelCls}>Mesaj</label>
        <textarea id="c-message" name="message" required rows={4} className={`${inputCls} resize-y`} placeholder="İşletmeniz ve ihtiyacınız hakkında kısaca bahsedin..." />
      </div>
      <button type="submit" disabled={s === "sending"} className={`${btnPrimary} w-full disabled:opacity-70`}>
        {s === "sending" ? "Gönderiliyor..." : "Mesaj Gönder"}
        {s !== "sending" && <Icon name="arrow" size={16} />}
      </button>
      <p className="text-center text-[11px] text-ink-400 dark:text-ink-500">
        Formu göndererek gizlilik politikamızı kabul etmiş olursunuz.
      </p>
    </form>
  );
}

/**
 * Premium corporate contact section on the one-page landing (#iletisim).
 */
export function ContactSection({ withHeading = true }: { withHeading?: boolean }) {
  const { ref, inView } = useInViewAnim();
  return (
    <section
      id="iletisim"
      ref={ref}
      className="section-pad scroll-mt-28 bg-white dark:bg-ink-950 border-t border-ink-200/70 dark:border-white/[0.05]"
    >
      <div className="container-q">
        <div className="grid lg:grid-cols-[1fr_1.15fr] gap-10 lg:gap-16 items-start">
          <motion.div initial={{ opacity: 0, y: 24 }} animate={inView ? { opacity: 1, y: 0 } : {}} transition={{ duration: 0.7 }}>
            {withHeading && (
              <>
                <div className="eyebrow mb-5">İletişim</div>
                <h2 className="text-display-md font-bold tracking-tight text-ink-900 dark:text-ink-50">
                  Bir haftada demo.<br />
                  <span className="gradient-text">Üç günde kurulum.</span>
                </h2>
                <p className="mt-5 text-ink-600 dark:text-ink-300 leading-relaxed max-w-md">
                  Demo, fiyat teklifi veya özel entegrasyon — talebinizi alın, 24 saat içinde size geri dönelim.
                </p>
              </>
            )}

            <div className="mt-8 space-y-3">
              {CONTACT_CHANNELS.map((c) => {
                const body = (
                  <div className="flex items-center gap-4 rounded-2xl border border-ink-200/80 bg-ink-50/50 p-4 transition-colors hover:border-brand-300 dark:border-white/[0.07] dark:bg-white/[0.03] dark:hover:border-white/20">
                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-500/12 text-brand-600 dark:text-brand-300">
                      <Icon name={c.icon as never} size={19} />
                    </span>
                    <div>
                      <div className="text-[10px] font-mono uppercase tracking-wider text-ink-500 dark:text-ink-400">{c.k}</div>
                      <div className="font-semibold text-ink-800 dark:text-ink-100">{c.v}</div>
                    </div>
                  </div>
                );
                return c.href ? (
                  <a key={c.k} href={c.href} className="block">{body}</a>
                ) : (
                  <div key={c.k}>{body}</div>
                );
              })}
            </div>

            <div className="mt-6 flex items-center gap-3 text-sm text-ink-500 dark:text-ink-400">
              <span className="inline-flex items-center gap-2 rounded-full bg-success-500/10 px-3 py-1.5 font-semibold text-success-600 dark:text-success-500">
                <span className="h-1.5 w-1.5 rounded-full bg-success-500" /> Ortalama yanıt: 2 saat
              </span>
            </div>
          </motion.div>

          <motion.div initial={{ opacity: 0, y: 24 }} animate={inView ? { opacity: 1, y: 0 } : {}} transition={{ delay: 0.1, duration: 0.7 }}>
            <ContactForm />
          </motion.div>
        </div>
      </div>
    </section>
  );
}
