<?php
/**
 * Welcome Email Template
 * Variables available:
 * - $fullName
 * - $firstName
 * - $lastName
 * - $email
 * - $customerId
 * - $baseUrl
 * - $siteName
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($fullName); ?>,</h2>

<p>Qordy'ye kaydınız başarıyla tamamlandı! Restoran yönetim sisteminizi kullanmaya başlamak için hazırsınız.</p>

<div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #6366f1; margin-top: 0;">Hesap Bilgileriniz</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">E-posta Adresiniz:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($email); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Giriş Paneli:</td>
            <td style="padding: 8px 0; color: #1f2937;">
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/login" style="color: #6366f1; text-decoration: none;">
                    <?php echo htmlspecialchars($baseUrl); ?>/login
                </a>
            </td>
        </tr>
    </table>
</div>

<p style="margin-top: 20px;">Hemen giriş yaparak sisteminizi kullanmaya başlayabilirsiniz!</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/login" style="display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Panele Giriş Yap</a>
</div>

<p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
    Herhangi bir sorunuz veya öneriniz için bizlere ulaşmaktan çekinmeyin.
</p>
