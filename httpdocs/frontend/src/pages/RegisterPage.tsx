import { AuthLayout } from "@/components/auth/AuthLayout";
import { RegisterForm } from "@/components/auth/RegisterForm";

export function RegisterPage() {
  return (
    <AuthLayout
      variant="register"
      path="/register"
      formEyebrow="Yeni hesap"
      formTitle="İlk servise başlayın."
      formSubtitle="Birkaç bilgi yeterli — gerisini birlikte hallederiz."
    >
      <RegisterForm />
    </AuthLayout>
  );
}

export default RegisterPage;
