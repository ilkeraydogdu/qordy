<?php
/**
 * Trial Started Email Template
 * Variables: $fullName, $firstName, $trialDays, $trialEndsAt, $baseUrl
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($firstName ?: $fullName); ?>,</h2>

<p style="font-size: 16px;">Qordy'ye hoş geldiniz! <strong><?php echo $trialDays; ?> günlük ücretsiz deneme süreniz</strong> başladı.</p>

<div class="q-gradient-brand" style="color: white; padding: 24px; border-radius: 12px; margin: 24px 0; text-align: center;">
    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 4px;">Deneme süreniz</div>
    <div style="font-size: 32px; font-weight: 800;"><?php echo $trialDays; ?> Gün</div>
    <div style="font-size: 13px; opacity: 0.8; margin-top: 8px;">
        <?php if ($trialEndsAt): ?>
        Bitiş: <?php echo date('d.m.Y', strtotime($trialEndsAt)); ?>
        <?php endif; ?>
    </div>
</div>

<p>Bu süre boyunca tüm özelliklere erişebilirsiniz:</p>

<table style="width: 100%; margin: 16px 0;">
    <tr>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">QR Menü Sistemi</td>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">POS Sistemi</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">Mutfak Ekranı</td>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">Mobil Uygulama</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">7/24 Destek</td>
        <td style="padding: 6px 0; color: #22c55e;">✓</td>
        <td style="padding: 6px 8px;">256-bit SSL</td>
    </tr>
</table>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/login" style="display: inline-block; background: #6366f1; color: white; padding: 14px 36px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px;">Sisteme Giriş Yap</a>
</div>

<p style="color: #6b7280; font-size: 14px;">Herhangi bir sorunuz olursa destek ekibimiz 7/24 yanınızda.</p>
