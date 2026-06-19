import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { api } from "@/lib/api";
import { getBootstrap } from "@/lib/bootstrap";

export function LoginForm() {
  const [showPass, setShowPass] = useState(false);
  const bootstrap = getBootstrap();
  const [csrf, setCsrf] = useState(bootstrap.csrfToken);
  const [flashError, setFlashError] = useState<string | null>(
    bootstrap.flash.error ?? bootstrap.flash.warning ?? null
  );
  const [flashSuccess, setFlashSuccess] = useState<string | null>(
    bootstrap.flash.success ?? bootstrap.flash.info ?? null
  );

  useEffect(() => {
    // Refresh CSRF on mount so the form submits with a fresh nonce
    api
      .refreshCsrf()
      .then((d) => d?.token && setCsrf(d.token))
      .catch(() => {
        /* keep server-injected token */
      });
  }, []);

  return (
    <form method="POST" action="/login" className="w-full">
      <input type="hidden" name="csrf_token" value={csrf} />

      <AnimatePresence>
        {flashSuccess && (
          <motion.div
            key="success"
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="mb-6 flex items-start gap-3 p-4 rounded-xl bg-success-50 border border-success-100 text-sm text-success-600"
          >
            <span className="text-success-600 flex-shrink-0 mt-0.5">
              <Icon name="check" size={16} strokeWidth={3} />
            </span>
            <span className="flex-1">{flashSuccess}</span>
            <button
              type="button"
              onClick={() => setFlashSuccess(null)}
              className="text-success-500 hover:text-success-600"
              aria-label="Kapat"
            >
              <Icon name="close" size={14} />
            </button>
          </motion.div>
        )}
        {flashError && (
          <motion.div
            key="error"
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

      <div className="space-y-5">
        <div>
          <label className="q-label" htmlFor="email">E-posta</label>
          <input
            id="email"
            type="email"
            name="email"
            required
            autoComplete="email"
            placeholder="ornek@email.com"
            className="q-input"
          />
        </div>

        <div>
          <label className="q-label" htmlFor="password">Şifre</label>
          <div className="q-input-group">
            <input
              id="password"
              type={showPass ? "text" : "password"}
              name="password"
              required
              autoComplete="current-password"
              placeholder="••••••••"
              className="q-input"
            />
            <button
              type="button"
              onClick={() => setShowPass((s) => !s)}
              aria-label={showPass ? "Şifreyi gizle" : "Şifreyi göster"}
              className="q-addon q-addon-right hover:text-brand-600 transition-colors"
            >
              <Icon name={showPass ? "eyeOff" : "eye"} size={18} />
            </button>
          </div>
        </div>

        <div className="flex items-center justify-between">
          <label className="inline-flex items-center gap-2.5 text-sm text-ink-600 cursor-pointer select-none">
            <input
              type="checkbox"
              name="rememberMe"
              className="h-4 w-4 rounded border-ink-300 accent-brand-600"
            />
            Beni hatırla
          </label>
          <a href="#" className="text-sm text-brand-600 hover:underline">
            Şifremi unuttum
          </a>
        </div>
      </div>

      <div className="mt-8">
        <Button
          type="submit"
          variant="ember"
          size="lg"
          fullWidth
          iconRight={<Icon name="arrow" size={18} />}
        >
          Giriş Yap
        </Button>
      </div>

      <p className="mt-8 text-center text-sm text-ink-500">
        Hesabınız yok mu?{" "}
        <Link to="/register" className="text-brand-600 font-medium hover:underline">
          Ücretsiz kayıt olun
        </Link>
      </p>
    </form>
  );
}
