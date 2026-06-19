<?php
/**
 * Business Created Email Template
 * Variables available:
 * - $fullName
 * - $firstName
 * - $lastName
 * - $email
 * - $businessName
 * - $businessId
 * - $subdomain
 * - $hasPackage
 * - $packageName
 * - $baseUrl
 * - $siteName
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($fullName); ?>,</h2>

<p>İşletmeniz başarıyla oluşturuldu! Artık Qordy restoran yönetim sistemini kullanmaya başlayabilirsiniz.</p>

<div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #f97316; margin-top: 0;">İşletme Bilgileri</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">İşletme Adı:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($businessName); ?></td>
        </tr>
        <?php if (!empty($subdomain)): ?>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Subdomain:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($subdomain); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($hasPackage && !empty($packageName)): ?>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Paket:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($packageName); ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div style="background: #dbeafe; padding: 15px; border-left: 4px solid #3b82f6; border-radius: 4px; margin: 20px 0;">
    <strong style="color: #1e40af;">İşletmeniz hazır!</strong><br>
    <span style="color: #1e3a8a;">Artık menülerinizi, rezervasyonlarınızı ve siparişlerinizi yönetebilirsiniz.</span>
</div>

<p style="margin-top: 20px;">İşletmenizi yönetmek için panele giriş yapabilirsiniz.</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/login" style="display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Panele Giriş Yap</a>
</div>

<p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
    Herhangi bir sorunuz veya öneriniz için bizlere ulaşmaktan çekinmeyin.<br>
    <strong>Qordy Ekibi</strong>
</p>
