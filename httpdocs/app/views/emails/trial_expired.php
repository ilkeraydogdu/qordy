<?php
/**
 * Trial Expired Email Template
 * Variables: $fullName, $firstName, $companyName, $baseUrl
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($firstName ?: $fullName); ?>,</h2>

<div style="background: #f8fafc; border: 2px solid #e2e8f0; padding: 24px; border-radius: 12px; margin: 20px 0; text-align: center;">
    <div style="font-size: 48px; margin-bottom: 12px;">⏰</div>
    <div style="font-size: 18px; font-weight: 700; color: #1f2937;">Deneme Süreniz Sona Erdi</div>
    <div style="font-size: 14px; color: #64748b; margin-top: 8px;">
        <?php if ($companyName): ?>
        <?php echo htmlspecialchars($companyName); ?> için
        <?php endif; ?>
    </div>
</div>

<p>Ücretsiz deneme süreniz doldu. Ancak <strong>tüm verileriniz güvende</strong> ve 30 gün boyunca korunmaktadır.</p>

<p>Sistemi kullanmaya devam etmek ve işletmenizi dijitalleştirmeye devam etmek için bir plan seçmeniz yeterli:</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/customer/packages" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 14px 40px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px;">Planları İncele ve Satın Al</a>
</div>

<p style="color: #6b7280; font-size: 14px;">Yardıma ihtiyacınız varsa <a href="mailto:destek@qordy.com" style="color: #6366f1;">destek@qordy.com</a> adresinden veya <a href="tel:+908503093253" style="color: #6366f1;">0850 309 32 53</a> numarasından bize ulaşabilirsiniz.</p>
