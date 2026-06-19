<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Google Play Data Safety bölümü "Account deletion request URL"
 * alanı zorunlu olduğu için, web sitesi üzerinden kullanıcıların
 * hesap silme talebi oluşturabileceği basit bir formu barındıran
 * public controller. Sadece talep oluşturur — silme işlemi manuel
 * olarak destek ekibi tarafından işletilir. Gerçekte kullanıcının
 * çok nadir dokunacağı bir uç — bu yüzden gereksiz yere karmaşık
 * bir self-service silme akışına gitmiyoruz; sade, form + e-posta.
 */
class AccountDeletionController extends \App\Core\Controller {

    public function __construct() {
        parent::__construct();
    }

    /**
     * GET /hesap-sil
     *
     * Sayfayı ve formu render eder. Query string'deki `ok=1` değeri
     * son POST başarıyla işlendiğinde gösterilen teşekkür ekranını
     * tetikler.
     */
    public function show() {
        $sent = isset($_GET['ok']) && $_GET['ok'] === '1';
        $error = isset($_GET['err']) ? (string)$_GET['err'] : '';

        $this->render('legal/account_deletion', [
            'sent'  => $sent,
            'error' => $error,
            'title' => 'Hesap Silme Talebi - Qordy',
        ]);
    }

    /**
     * POST /hesap-sil
     *
     * Form gönderimini işler:
     *  - CSRF doğrulaması
     *  - temel validasyon
     *  - basit bir rate-limit (session cooldown)
     *  - talebi destek kutusuna e-posta ile iletir
     *  - başarı/hata mesajı ile aynı sayfaya geri yönlendirir
     */
    public function submit() {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $redirect = function (string $qs) use ($baseUrl) {
            header('Location: ' . $baseUrl . '/hesap-sil' . $qs);
            exit;
        };

        $csrf = $_POST['csrf_token'] ?? '';
        if (!$this->validateCSRFToken($csrf)) {
            $redirect('?err=csrf');
        }

        // Session-based rate limit: 5 dakika içinde aynı tarayıcıdan
        // ikinci gönderimi engelle. Abuse için yeterli; destek ekibi
        // zaten her talebi manuel değerlendiriyor.
        \App\Core\SessionManager::ensureSession();
        $last = (int)(\App\Core\SessionManager::get('account_delete_last') ?? 0);
        if ($last && (time() - $last) < 300) {
            $redirect('?err=rate');
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $businessName = trim((string)($_POST['business_name'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $confirm = isset($_POST['confirm']) ? (string)$_POST['confirm'] : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $redirect('?err=email');
        }
        if ($confirm !== 'yes') {
            $redirect('?err=confirm');
        }
        if (mb_strlen($businessName) > 200 || mb_strlen($reason) > 2000) {
            $redirect('?err=length');
        }

        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeBiz = htmlspecialchars($businessName !== '' ? $businessName : '—', ENT_QUOTES, 'UTF-8');
        $safeReason = nl2br(htmlspecialchars($reason !== '' ? $reason : '—', ENT_QUOTES, 'UTF-8'));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = htmlspecialchars((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), ENT_QUOTES, 'UTF-8');
        $ts = date('d.m.Y H:i:s');

        $subject = '[Qordy] Hesap silme talebi — ' . $email;
        $body = <<<HTML
<h2 style="font-family:Inter,system-ui,sans-serif;color:#0f172a;">Yeni hesap silme talebi</h2>
<p style="font-family:Inter,system-ui,sans-serif;color:#334155;line-height:1.7;">
  Bir kullanıcı <strong>qordy.com/hesap-sil</strong> sayfası üzerinden hesap silme talebi oluşturdu.
  Talebi değerlendirip ilgili işletme / müşteri kaydını GDPR/KVKK prosedürüne uygun şekilde silmeniz gerekiyor.
</p>
<table style="font-family:Inter,system-ui,sans-serif;border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:6px 12px;color:#64748b;">E-posta</td><td style="padding:6px 12px;color:#0f172a;"><strong>{$safeEmail}</strong></td></tr>
  <tr><td style="padding:6px 12px;color:#64748b;">İşletme</td><td style="padding:6px 12px;color:#0f172a;">{$safeBiz}</td></tr>
  <tr><td style="padding:6px 12px;color:#64748b;vertical-align:top;">Gerekçe</td><td style="padding:6px 12px;color:#0f172a;">{$safeReason}</td></tr>
  <tr><td style="padding:6px 12px;color:#64748b;">IP</td><td style="padding:6px 12px;color:#0f172a;">{$ip}</td></tr>
  <tr><td style="padding:6px 12px;color:#64748b;">User-Agent</td><td style="padding:6px 12px;color:#0f172a;">{$ua}</td></tr>
  <tr><td style="padding:6px 12px;color:#64748b;">Zaman</td><td style="padding:6px 12px;color:#0f172a;">{$ts}</td></tr>
</table>
<p style="font-family:Inter,system-ui,sans-serif;color:#64748b;font-size:13px;margin-top:24px;">
  Bu e-posta <strong>Qordy</strong> web sitesindeki otomatik hesap silme formu tarafından gönderildi.
</p>
HTML;

        $ok = false;
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $ok = $emailService->sendEmail('destek@qordy.com', $subject, $body);
            // Kullanıcıya da bir konfirmasyon gönderelim — Google Play
            // incelemesinde "kullanıcıya geri dönüş yapıldığına dair
            // kanıt" aranabiliyor.
            $userBody = <<<HTML
<div style="font-family:Inter,system-ui,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#0f172a;">
  <h2 style="margin:0 0 12px;">Talebinizi aldık</h2>
  <p style="line-height:1.7;color:#334155;">
    Merhaba, Qordy hesap silme talebiniz destek ekibimize iletildi.
    Talebiniz <strong>7 iş günü</strong> içerisinde değerlendirilip kayıtlı verileriniz
    (hesap bilgileri, kişisel tanımlayıcılar ve cihaz verileri) sistemlerimizden silinir.
    Bazı kayıtlar (örn. e-Arşiv fatura, mali/yasal zorunluluk bulunan kayıtlar)
    ilgili mevzuatta öngörülen süreler boyunca anonim olarak saklanır.
  </p>
  <p style="line-height:1.7;color:#334155;">
    Talebi iptal etmek veya eklemek istediğiniz bir şey olursa
    <a href="mailto:destek@qordy.com" style="color:#6366f1;">destek@qordy.com</a>
    adresine yanıt verebilirsiniz.
  </p>
  <p style="color:#94a3b8;font-size:13px;margin-top:24px;">— Qordy</p>
</div>
HTML;
            try { $emailService->sendEmail($email, 'Qordy hesap silme talebiniz alındı', $userBody); } catch (\Throwable $e) { /* best-effort */ }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('AccountDeletionController::submit', ['error' => $e->getMessage()]);
            $ok = false;
        }

        // Mail driver başarısız olsa bile biz talebi log'a yazıyoruz
        // ki ops ekibi log grep'leyip de manuel işleme alabilsin.
        \App\Core\Logger::info('AccountDeletionController::submit', [
            'email' => $email,
            'business' => $businessName,
            'reason_len' => mb_strlen($reason),
            'mail_ok' => $ok,
            'ip' => $ip,
        ]);

        \App\Core\SessionManager::set('account_delete_last', time());
        $redirect($ok ? '?ok=1' : '?err=send');
    }
}
