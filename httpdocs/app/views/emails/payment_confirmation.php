<?php
/**
 * Payment Confirmation Email Template
 * Variables available:
 * - $fullName
 * - $firstName
 * - $lastName
 * - $email
 * - $packageName
 * - $amount
 * - $currency
 * - $paymentMethod
 * - $paymentDate
 * - $subscriptionId
 * - $paymentStatus
 * - $baseUrl
 * - $siteName
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($fullName); ?>,</h2>

<p>Ödemeniz başarıyla alındı! Paketiniz aktif edildi ve kullanıma hazır.</p>

<div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #f97316; margin-top: 0;">Ödeme Detayları</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Paket:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($packageName); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Tutar:</td>
            <td style="padding: 8px 0; color: #1f2937; font-weight: bold;"><?php echo number_format($amount, 2, ',', '.'); ?> <?php echo htmlspecialchars($currency); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Ödeme Yöntemi:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($paymentMethod); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Ödeme Tarihi:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($paymentDate); ?></td>
        </tr>
        <?php if (!empty($subscriptionId)): ?>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Abonelik ID:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($subscriptionId); ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div style="background: #d1fae5; padding: 15px; border-left: 4px solid #10b981; border-radius: 4px; margin: 20px 0;">
    <strong style="color: #065f46;">✓ Ödemeniz başarıyla tamamlandı!</strong><br>
    <span style="color: #047857;">Paketiniz aktif edildi ve kullanıma hazır.</span>
</div>

<p style="margin-top: 20px;">Paketinizi yönetmek ve sisteminizi kullanmaya devam etmek için panele giriş yapabilirsiniz.</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/login" style="display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Panele Giriş Yap</a>
</div>

<p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
    Bizi tercih ettiğiniz için teşekkür ederiz!<br>
    <strong>Qordy Ekibi</strong>
</p>
