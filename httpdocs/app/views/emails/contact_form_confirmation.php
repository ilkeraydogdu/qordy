<?php
/**
 * Contact Form Confirmation Email Template
 * Variables available:
 * - $fullName
 * - $email
 * - $companyName (optional)
 * - $phone (optional)
 * - $message (optional)
 * - $siteName
 * - $restaurantPhone (optional)
 * - $restaurantAddress (optional)
 */

// Extract first name from full name for personalized greeting
$firstName = !empty($fullName) ? explode(' ', trim($fullName))[0] : 'Sayın Müşterimiz';
$greeting = "Sayın {$firstName}";
?>

<h2 style="color: #1f2937; margin-top: 0;">İletişim Formunuz Başarıyla Alındı</h2>

<p style="color: #4b5563; line-height: 1.6; margin: 20px 0;">
    <?php echo htmlspecialchars($greeting); ?>,
</p>

<p style="color: #4b5563; line-height: 1.6; margin: 20px 0;">
    Bize ulaştığınız için çok teşekkür ederiz. İletişim formunuz başarıyla alınmıştır ve ekibimiz tarafından incelenmektedir.
</p>

<p style="color: #4b5563; line-height: 1.6; margin: 20px 0;">
    <strong>En kısa sürede sizinle iletişime geçeceğiz.</strong> Sorularınız veya acil ihtiyaçlarınız için lütfen bizimle iletişime geçmekten çekinmeyin.
</p>

<div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #f97316; margin-top: 0; margin-bottom: 15px;">Gönderilen Bilgiler</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280; width: 40%;">Ad Soyad:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($fullName ?? 'Belirtilmemiş'); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">E-posta:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($email ?? ''); ?></td>
        </tr>
        <?php if (!empty($companyName)): ?>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">İşletme Adı:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($companyName); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($phone)): ?>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Telefon:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($phone); ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<?php if (!empty($message)): ?>
<div style="background: #eff6ff; padding: 15px; border-left: 4px solid #3b82f6; border-radius: 4px; margin: 20px 0;">
    <strong style="color: #1e40af;">Mesajınız:</strong><br>
    <p style="color: #1e40af; margin: 10px 0 0 0; line-height: 1.6;">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </p>
</div>
<?php endif; ?>

<p style="color: #4b5563; line-height: 1.6; margin: 30px 0 20px 0;">
    Tekrar teşekkür ederiz.<br>
    <strong><?php echo htmlspecialchars($siteName ?? 'Qordy'); ?> Ekibi</strong>
</p>
