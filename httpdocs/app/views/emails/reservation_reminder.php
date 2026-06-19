<?php
/**
 * Reservation Reminder Email Template
 * Variables available:
 * - $customerName
 * - $date
 * - $time
 * - $guests
 * - $tableName
 * - $notes (optional)
 * - $hoursBefore
 * - $siteName
 * - $restaurantPhone (optional)
 * - $restaurantAddress (optional)
 */

$greeting = "Rezervasyonunuzu hatırlatmak istiyoruz!";
$timeInfo = !empty($hoursBefore) ? "Rezervasyonunuzdan <strong>{$hoursBefore} saat</strong> önce bu hatırlatmayı alıyorsunuz." : "";
?>

<h2 style="color: #1f2937; margin-top: 0;"><?php echo htmlspecialchars($greeting); ?></h2>

<?php if (!empty($timeInfo)): ?>
<p><?php echo $timeInfo; ?></p>
<?php endif; ?>

<div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #f97316; margin-top: 0;">Rezervasyon Detayları</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Müşteri:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($customerName ?? 'Sayın Müşterimiz'); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Tarih:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($date ?? ''); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Saat:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($time ?? ''); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Kişi Sayısı:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($guests ?? 1); ?> kişi</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Masa:</td>
            <td style="padding: 8px 0; color: #1f2937;"><?php echo htmlspecialchars($tableName ?? 'Belirlenmedi'); ?></td>
        </tr>
    </table>
</div>

<?php if (!empty($notes)): ?>
<div style="background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; border-radius: 4px; margin: 20px 0;">
    <strong>Özel Notlar:</strong><br>
    <?php echo nl2br(htmlspecialchars($notes)); ?>
</div>
<?php endif; ?>

