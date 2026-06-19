<?php
/**
 * Receipt PDF View
 * A4 format için optimize edilmiş adisyon görüntüleme
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';

$receipt = $receipt ?? [];
$order = $order ?? [];
$items = $items ?? [];
$settings = $settings ?? [];

$receiptNumber = $receipt['receipt_number'] ?? '';
$totalAmount = floatval($receipt['total_amount'] ?? 0);
$taxAmount = floatval($receipt['tax_amount'] ?? 0);
$serviceCharge = floatval($receipt['service_charge'] ?? 0);
$discountAmount = floatval($receipt['discount_amount'] ?? 0);
$subtotal = $totalAmount - $taxAmount - $serviceCharge + $discountAmount;

// İşletme adı: controller ensureBusinessNameInSettings ile doldurur; site_name (Qordy) kullanılmaz
$restaurantName = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
if ($restaurantName === '') {
    $restaurantName = 'İşletme';
}
$restaurantAddress = $settings['business_address'] ?? $settings['restaurant_address'] ?? '';
$restaurantPhone = $settings['business_phone'] ?? $settings['restaurant_phone'] ?? '';
$restaurantEmail = '';
$taxId = $settings['tax_id'] ?? '';
$taxOffice = '';
$footerText = $settings['footer_text'] ?? '';

$paymentMethod = $receipt['payment_method'] ?? 'CASH';
$paymentMethods = [
    'CASH' => 'Nakit',
    'CARD' => 'Kredi Kartı',
    'QR' => 'QR Kod',
    'MIXED' => 'Karışık'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adisyon - <?php echo htmlspecialchars($receiptNumber); ?></title>
    <style>
        <?php echo getPrintCSS('a4'); ?>
        
        body {
            font-family: Arial, sans-serif;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            background: white;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #000;
            padding-bottom: 20px;
        }
        
        .receipt-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .receipt-info {
            font-size: 12px;
            margin: 5px 0;
            color: #666;
        }
        
        .receipt-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .receipt-detail-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        
        .receipt-detail-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .receipt-detail-value {
            font-size: 16px;
            font-weight: bold;
        }
        
        .receipt-items {
            margin: 30px 0;
        }
        
        .receipt-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .receipt-items-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
            border-bottom: 2px solid #000;
        }
        
        .receipt-items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .receipt-items-table tr:last-child td {
            border-bottom: none;
        }
        
        .receipt-totals {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding-top: 20px;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .receipt-total-row.grand-total {
            font-weight: bold;
            font-size: 20px;
            border-top: 2px solid #000;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000;
            font-size: 11px;
            color: #666;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .print-button {
            padding: 12px 24px;
            background: #f97316;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Print Buttons -->
    <div class="no-print">
        <button onclick="window.print()" class="print-button">Yazdır</button>
        <button onclick="window.close()" class="print-button" style="background: #64748b;">Kapat</button>
    </div>
    
    <!-- Receipt Content -->
    <div class="receipt">
        <div class="receipt-header">
            <div class="receipt-title"><?php echo htmlspecialchars($restaurantName); ?></div>
            <?php if (!empty($restaurantAddress)): ?>
                <div class="receipt-info"><?php echo htmlspecialchars($restaurantAddress); ?></div>
            <?php endif; ?>
            <?php if (!empty($restaurantPhone)): ?>
                <div class="receipt-info">Tel: <?php echo htmlspecialchars($restaurantPhone); ?></div>
            <?php endif; ?>
            <?php if (!empty($taxId)): ?>
                <div class="receipt-info">Vergi No: <?php echo htmlspecialchars($taxId); ?></div>
            <?php endif; ?>
            <?php if (!empty($taxOffice)): ?>
                <div class="receipt-info">Vergi Dairesi: <?php echo htmlspecialchars($taxOffice); ?></div>
            <?php endif; ?>
            <?php if (!empty($restaurantEmail)): ?>
                <div class="receipt-info">E-posta: <?php echo htmlspecialchars($restaurantEmail); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-details">
            <div class="receipt-detail-box">
                <div class="receipt-detail-label">Adisyon No</div>
                <div class="receipt-detail-value"><?php echo htmlspecialchars($receiptNumber); ?></div>
            </div>
            <div class="receipt-detail-box">
                <div class="receipt-detail-label">Tarih</div>
                <div class="receipt-detail-value"><?php echo date('d.m.Y H:i', strtotime($receipt['created_at'] ?? 'now')); ?></div>
            </div>
            <?php if (!empty($order['table_name'])): ?>
                <div class="receipt-detail-box">
                    <div class="receipt-detail-label">Masa</div>
                    <div class="receipt-detail-value"><?php echo htmlspecialchars($order['table_name']); ?></div>
                </div>
            <?php endif; ?>
            <?php 
            $createdByName = trim($receipt['created_by_name'] ?? '');
            $createdByRole = strtoupper(trim($receipt['created_by_role'] ?? ''));
            if ($createdByName !== ''): 
                $label = ($createdByRole === 'WAITER') ? 'Garson' : 'Kasiyer';
            ?>
                <div class="receipt-detail-box">
                    <div class="receipt-detail-label"><?php echo htmlspecialchars($label); ?></div>
                    <div class="receipt-detail-value"><?php echo htmlspecialchars($createdByName); ?></div>
                </div>
            <?php endif; ?>
            <div class="receipt-detail-box">
                <div class="receipt-detail-label">Ödeme Yöntemi</div>
                <div class="receipt-detail-value">
                    <?php if ($paymentMethod === 'MIXED'): 
                        $breakdown = $receipt['payment_breakdown'] ?? null;
                        if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
                        $cashAmt = is_array($breakdown) ? floatval($breakdown['cash'] ?? 0) : 0;
                        $cardAmt = is_array($breakdown) ? floatval($breakdown['card'] ?? 0) : 0;
                    ?>
                        Karışık;<br>
                        <span style="font-size:11px;">Kart: <?php echo number_format($cardAmt, 2, ',', '.'); ?> ₺</span><br>
                        <span style="font-size:11px;">Nakit: <?php echo number_format($cashAmt, 2, ',', '.'); ?> ₺</span>
                    <?php else: ?>
                        <?php echo $paymentMethods[$paymentMethod] ?? $paymentMethod; ?> — <?php echo number_format($totalAmount, 2, ',', '.'); ?> ₺
                    <?php endif; ?>
                    <?php
                    $paymentTime = $receipt['payment_time'] ?? $receipt['paid_at'] ?? null;
                    if ($paymentTime && $paymentMethod !== 'PENDING'):
                    ?>
                        <div style="font-size:10px;margin-top:6px;">Ödeme Zamanı: <?php echo date('d.m.Y H:i', strtotime($paymentTime)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="receipt-items">
            <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Sipariş Detayları</h3>
            <table class="receipt-items-table">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th style="text-align: center; width: 80px;">Adet</th>
                        <th style="text-align: right; width: 100px;">Birim Fiyat</th>
                        <th style="text-align: right; width: 120px;">Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemName = $item['item_name'] ?? $item['name'] ?? 'Ürün';
                        $quantity = intval($item['quantity'] ?? 1);
                        $price = floatval($item['price'] ?? 0);
                        $itemTotal = $price * $quantity;
                        ?>
                        <?php
                        $variantName = $item['variant_name'] ?? '';
                        $excluded = $item['excluded_ingredients'] ?? [];
                        if (is_string($excluded)) $excluded = json_decode($excluded, true) ?: [];
                        $extras = $item['selected_extras'] ?? [];
                        if (is_string($extras)) $extras = json_decode($extras, true) ?: [];
                        $itemNote = $item['notes'] ?? $item['note'] ?? $item['item_note'] ?? '';
                        $hasDetails = !empty($variantName) || !empty($excluded) || !empty($extras) || !empty($itemNote);
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($itemName); ?>
                                <?php if (!empty($variantName)): ?>
                                    <div style="font-size:11px;color:#555;">Varyant: <?php echo htmlspecialchars($variantName); ?></div>
                                <?php endif; ?>
                                <?php foreach ($excluded as $ex):
                                    $exName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                                    if (empty($exName)) continue;
                                ?>
                                    <div style="font-size:11px;color:#c00;font-weight:bold;">✕ ÇIKAR: <?php echo htmlspecialchars($exName); ?></div>
                                <?php endforeach; ?>
                                <?php foreach ($extras as $ext):
                                    $extName = is_array($ext) ? ($ext['name'] ?? $ext['extra_name'] ?? '') : $ext;
                                    $extPrice = is_array($ext) ? floatval($ext['price'] ?? 0) : 0;
                                    if (empty($extName)) continue;
                                ?>
                                    <div style="font-size:11px;color:#060;">+ <?php echo htmlspecialchars($extName); ?><?php echo $extPrice > 0 ? ' (+' . number_format($extPrice, 2, ',', '.') . ' ₺)' : ''; ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($itemNote)): ?>
                                    <div style="font-size:11px;color:#333;font-style:italic;">NOT: <?php echo htmlspecialchars($itemNote); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;"><?php echo $quantity; ?></td>
                            <td style="text-align: right;"><?php echo number_format($price, 2, ',', '.'); ?> ₺</td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($itemTotal, 2, ',', '.'); ?> ₺</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php 
        $customerNote = $order['customer_note'] ?? $order['note'] ?? '';
        if (!empty($customerNote)): 
        ?>
        <div style="margin:15px 0;padding:10px;border:1px dashed #000;border-radius:4px;">
            <div style="font-weight:bold;font-size:13px;">SİPARİŞ NOTU:</div>
            <div style="font-size:12px;margin-top:5px;"><?php echo htmlspecialchars($customerNote); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="receipt-totals">
            <div class="receipt-total-row">
                <span>Ara Toplam:</span>
                <span><?php echo number_format($subtotal, 2, ',', '.'); ?> ₺</span>
            </div>
            <?php if ($serviceCharge > 0): ?>
                <div class="receipt-total-row">
                    <span>Servis Ücreti:</span>
                    <span><?php echo number_format($serviceCharge, 2, ',', '.'); ?> ₺</span>
                </div>
            <?php endif; ?>
            <?php if ($taxAmount > 0): ?>
                <div class="receipt-total-row">
                    <span>KDV:</span>
                    <span><?php echo number_format($taxAmount, 2, ',', '.'); ?> ₺</span>
                </div>
            <?php endif; ?>
            <?php if ($discountAmount > 0): ?>
                <div class="receipt-total-row">
                    <span>İndirim:</span>
                    <span>-<?php echo number_format($discountAmount, 2, ',', '.'); ?> ₺</span>
                </div>
            <?php endif; ?>
            <div class="receipt-total-row grand-total">
                <span>TOPLAM:</span>
                <span><?php echo number_format($totalAmount, 2, ',', '.'); ?> ₺</span>
            </div>
        </div>
        
        <?php if (($receipt['status'] ?? '') === 'VOIDED'): ?>
            <div style="text-align: center; margin: 20px 0; padding: 20px; background: #fee; border: 3px solid #f00; border-radius: 8px;">
                <div style="font-weight: bold; color: #f00; font-size: 18px;">İPTAL EDİLMİŞ ADİSYON</div>
                <?php if (!empty($receipt['void_reason'])): ?>
                    <div style="font-size: 12px; margin-top: 10px;">Neden: <?php echo htmlspecialchars($receipt['void_reason']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="receipt-footer">
            <?php if (!empty($footerText)): ?>
                <div style="margin: 20px 0; font-size: 12px;"><?php echo nl2br(htmlspecialchars($footerText)); ?></div>
            <?php endif; ?>
            <div style="margin: 20px 0; font-size: 14px; font-weight: bold;">Teşekkür Ederiz!</div>
            <div style="font-size: 10px; margin-top: 10px;">
                Bu adisyon <?php echo date('d.m.Y H:i'); ?> tarihinde oluşturulmuştur.
            </div>
            <div style="font-size: 10px; margin-top: 5px;">
                Adisyon No: <?php echo htmlspecialchars($receiptNumber); ?>
            </div>
            <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #000;">
                <div style="font-weight: bold;"><?php echo htmlspecialchars($restaurantName); ?></div>
                <div style="font-weight: bold;">Yönetim Sistemi</div>
                <div><?php echo htmlspecialchars(!empty($settings['website']) ? $settings['website'] : (!empty($settings['site_url']) ? $settings['site_url'] : 'www.qordy.com')); ?></div>
            </div>
        </div>
    </div>
</body>
</html>

