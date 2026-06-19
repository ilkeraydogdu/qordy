<?php
namespace App\Services\Email\EmailType;

/**
 * TrialGraceReminderEmail
 *
 * Deneme süresi bittikten sonraki **7 günlük bekleme (grace) periyodu**
 * boyunca gönderilen kurumsal ve satışa yönlendirici hatırlatma maili.
 * Gün sayısına göre (grace_day_left) ton ve aciliyet farklılaşır.
 *
 * Beklenen $data:
 *   email, first_name, last_name, company_name, customer_id
 *   grace_day      : (int) bekleme periyodu içinde kaçıncı gün (1..7)
 *   grace_days_left: (int) askıya alınmasına kalan gün (6..0)
 *   cta_url        : (string) kullanıcıya özel (kısaltılmış veya düz) satın alma URL'si
 *   pricing_url    : (string, opsiyonel) genel paketler listesi kısa URL'si
 */
class TrialGraceReminderEmail extends AbstractEmailType {

    public function getSubject(): string {
        $left = (int)($this->data['grace_days_left'] ?? 7);
        if ($left <= 0) {
            return 'Son gün — Qordy hesabınız yarın askıya alınacak';
        }
        if ($left === 1) {
            return '1 gün kaldı — Qordy planınızı bugün tamamlayın';
        }
        if ($left <= 3) {
            return "Son {$left} gün — Qordy planınızı seçmeyi unutmayın";
        }
        return "Qordy deneme süreniz bitti — 7 gün içinde paketinizi seçin";
    }

    public function getTemplatePath(): string {
        return 'trial_grace_reminder.php';
    }

    public function getTemplateVariables(): array {
        $first = $this->data['first_name'] ?? '';
        $full  = trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? '')) ?: 'Değerli Yöneticimiz';
        $baseUrl = defined('BASE_URL') ? BASE_URL : 'https://qordy.com';

        return [
            'fullName'      => $full,
            'firstName'     => $first,
            'companyName'   => $this->data['company_name'] ?? '',
            'graceDay'      => max(1, (int)($this->data['grace_day'] ?? 1)),
            'graceDaysLeft' => max(0, (int)($this->data['grace_days_left'] ?? 7)),
            'ctaUrl'        => $this->data['cta_url']     ?? ($baseUrl . '/customer/packages/list'),
            'pricingUrl'    => $this->data['pricing_url'] ?? ($baseUrl . '/pricing'),
            'baseUrl'       => $baseUrl,
        ];
    }

    public function validate(): bool {
        $email = $this->getRecipientEmail();
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function getRecipientEmail(): ?string {
        $email = $this->data['email'] ?? '';
        if (empty($email)) return null;
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function getEmailTitle(): string {
        return 'Qordy — Deneme Süreniz Bitti';
    }
}
