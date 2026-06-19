<?php
/**
 * Business Deactivated Email Template
 * Variables: $fullName, $businessName, $qrMenuStatus, $qrMenuStatusLabel, $deactivatedAt, $baseUrl, $siteName
 */
?>

<h2 style="color: #1f2937; margin-top: 0;">Merhaba <?php echo htmlspecialchars($fullName); ?>,</h2>

<p>İşletmeniz <strong><?php echo htmlspecialchars($businessName); ?></strong> pasife alınmıştır.</p>

<div style="background: #fef2f2; padding: 20px; border-left: 4px solid #ef4444; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #dc2626; margin-top: 0;">⚠️ İşletme Durumu: Pasif</h3>
    <p style="color: #991b1b; margin-bottom: 0;">Bu değişiklik <strong><?php echo htmlspecialchars($deactivatedAt); ?></strong> tarihinde yapılmıştır.</p>
</div>

<div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #f97316; margin-top: 0;">QR Menü Durumu</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">QR Menü:</td>
            <td style="padding: 8px 0; color: #1f2937;">
                <?php if ($qrMenuStatus === 'menu_only'): ?>
                    <span style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 13px;">📋 <?php echo htmlspecialchars($qrMenuStatusLabel); ?></span>
                <?php elseif ($qrMenuStatus === 'passive'): ?>
                    <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 13px;">🚫 <?php echo htmlspecialchars($qrMenuStatusLabel); ?></span>
                <?php else: ?>
                    <span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 13px;">✅ <?php echo htmlspecialchars($qrMenuStatusLabel); ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    
    <?php if ($qrMenuStatus === 'menu_only'): ?>
    <div style="background: #fffbeb; padding: 12px; border-radius: 6px; margin-top: 12px;">
        <p style="margin: 0; color: #92400e; font-size: 13px;">
            <strong>Sadece Menü Görüntüleme:</strong> Müşterileriniz QR kodu okutarak menünüzü görüntüleyebilir, ancak sipariş veremez, garson çağıramaz ve hesap isteyemez.
        </p>
    </div>
    <?php elseif ($qrMenuStatus === 'passive'): ?>
    <div style="background: #fef2f2; padding: 12px; border-radius: 6px; margin-top: 12px;">
        <p style="margin: 0; color: #991b1b; font-size: 13px;">
            <strong>Tamamen Kapalı:</strong> Müşterileriniz QR kodu okuttuğunda "QR menümüz geçici olarak servis dışıdır" mesajı görecektir. Menü dahil hiçbir içerik gösterilmeyecektir.
        </p>
    </div>
    <?php endif; ?>
</div>

<div style="background: #eff6ff; padding: 15px; border-left: 4px solid #3b82f6; border-radius: 4px; margin: 20px 0;">
    <strong style="color: #1e40af;">Ne yapılması gerekiyor?</strong><br>
    <span style="color: #1e3a8a;">Bu değişiklik hakkında sorularınız varsa veya işletmenizi tekrar aktife almak istiyorsanız, lütfen bizimle iletişime geçin.</span>
</div>

<div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <h4 style="color: #374151; margin-top: 0;">Pasif Durumda Neler Etkilenir?</h4>
    <ul style="color: #4b5563; font-size: 14px; padding-left: 20px;">
        <li>İşletmeye ait tüm kullanıcılar (personeller dahil) giriş yapamayacaktır</li>
        <li>Aboneliğiniz askıya alınmıştır</li>
        <li>QR menü durumu yukarıda belirtilen şekilde değiştirilmiştir</li>
    </ul>
</div>

<p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
    Herhangi bir sorunuz varsa bizlere ulaşmaktan çekinmeyin.<br>
    <strong>Qordy Ekibi</strong>
</p>
