<?php
/**
 * Trial Expiring Email Template
 * Variables: $fullName, $firstName, $remainingDays, $trialEndsAt, $baseUrl
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($firstName ?: $fullName); ?>,</h2>

<div style="background: #fef2f2; border: 2px solid #fecaca; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center;">
    <div style="font-size: 14px; color: #dc2626; font-weight: 600;">Deneme Süreniz Bitiyor!</div>
    <div style="font-size: 36px; font-weight: 800; color: #dc2626; margin: 8px 0;"><?php echo $remainingDays; ?> Gün</div>
    <div style="font-size: 13px; color: #991b1b;">
        <?php if ($trialEndsAt): ?>
        Bitiş tarihi: <?php echo date('d.m.Y H:i', strtotime($trialEndsAt)); ?>
        <?php endif; ?>
    </div>
</div>

<p>Ücretsiz deneme sürenizin bitmesine <strong><?php echo $remainingDays; ?> gün</strong> kaldı. Süre dolduktan sonra sisteme erişiminiz kısıtlanacaktır.</p>

<p><strong>Verileriniz güvende!</strong> Süre dolsa bile verileriniz 30 gün boyunca korunur. Bir plan seçerek kaldığınız yerden devam edebilirsiniz.</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/customer/packages" style="display: inline-block; background: #6366f1; color: white; padding: 14px 36px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px;">Plan Seçin</a>
</div>

<div style="background: #f3f4f6; padding: 16px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 0; color: #4b5563; font-size: 14px;"><strong>Neden Qordy?</strong></p>
    <ul style="margin: 8px 0 0; padding-left: 20px; color: #6b7280; font-size: 14px;">
        <li>500+ işletme güveniyor</li>
        <li>%99.9 uptime garantisi</li>
        <li>7/24 teknik destek</li>
        <li>İstediğiniz zaman iptal</li>
    </ul>
</div>
