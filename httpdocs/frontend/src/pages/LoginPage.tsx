import { AuthLayout } from "@/components/auth/AuthLayout";
import { LoginForm } from "@/components/auth/LoginForm";

export function LoginPage() {
  return (
    <AuthLayout
      variant="login"
      path="/login"
      formEyebrow="Hoş geldiniz"
      formTitle="Tekrar hoş geldiniz."
      formSubtitle="İşletmeniz sizi bekliyor — giriş yapın, kaldığınız yerden devam edin."
    >
      <LoginForm />
    </AuthLayout>
  );
}

export default LoginPage;
