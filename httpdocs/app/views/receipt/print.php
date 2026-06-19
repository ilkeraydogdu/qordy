<?php
/**
 * Receipt Print View
 * Termal yazıcı için optimize edilmiş adisyon görüntüleme
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
        <?php echo getPrintCSS('thermal'); ?>
        
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 10mm 5mm;
            background: white;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .receipt-info {
            font-size: 11px;
            margin: 3px 0;
        }
        
        .receipt-items {
            margin: 15px 0;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 11px;
        }
        
        .receipt-item-name {
            flex: 1;
        }
        
        .receipt-item-qty {
            margin: 0 10px;
            text-align: center;
            min-width: 30px;
        }
        
        .receipt-item-price {
            text-align: right;
            min-width: 60px;
        }
        
        .receipt-totals {
            margin-top: 15px;
            border-top: 2px dashed #000;
            padding-top: 10px;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 12px;
        }
        
        .receipt-total-row.grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 10px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px dashed #000;
            font-size: 10px;
        }
        
        .no-print {
            display: none;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 5mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button (hidden when printing) -->
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #f97316; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
            Yazdır
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #64748b; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-left: 10px;">
            Kapat
        </button>
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
        
        <div class="receipt-info" style="text-align: center; margin: 10px 0;">
            <div style="font-weight: bold;">ADİSYON</div>
            <div>No: <?php echo htmlspecialchars($receiptNumber); ?></div>
            <div>Tarih: <?php echo date('d.m.Y', strtotime($receipt['created_at'] ?? 'now')); ?> Saat: <?php echo date('H:i', strtotime($receipt['created_at'] ?? 'now')); ?></div>
            <?php if (!empty($order['table_name'])): ?>
                <div>Masa: <?php echo htmlspecialchars($order['table_name']); ?></div>
            <?php endif; ?>
            <?php 
            $createdByName = trim($receipt['created_by_name'] ?? '');
            $createdByRole = strtoupper(trim($receipt['created_by_role'] ?? ''));
            if ($createdByName !== ''): 
                $label = ($createdByRole === 'WAITER') ? 'Garson' : 'Kasiyer';
            ?>
                <div><?php echo htmlspecialchars($label); ?>: <?php echo htmlspecialchars($createdByName); ?></div>
            <?php endif; ?>
        </div>
        
        <div style="border-top: 1px dashed #000; margin: 10px 0;"></div>
        
        <div class="receipt-items">
            <h3 style="font-size: 14px; font-weight: bold; margin-bottom: 8px;">Sipariş Detayları</h3>
            <table style="width:100%; border-collapse: collapse; font-size: 11px; margin-bottom: 10px;">
                <thead>
                    <tr style="border-bottom: 2px solid #000;">
                        <th style="text-align: left; padding: 4px 0;">Ürün</th>
                        <th style="text-align: center; width: 50px;">Adet</th>
                        <th style="text-align: right; width: 70px;">Birim Fiyat</th>
                        <th style="text-align: right; width: 70px;">Toplam</th>
                    </tr>
                </thead>
                <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $itemName = $item['item_name'] ?? $item['name'] ?? 'Ürün';
                $quantity = intval($item['quantity'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $itemTotal = $price * $quantity;
                
                // Parse excluded ingredients
                $excluded = $item['excluded_ingredients'] ?? [];
                if (is_string($excluded)) $excluded = json_decode($excluded, true) ?: [];
                
                // Parse selected extras
                $extras = $item['selected_extras'] ?? [];
                if (is_string($extras)) $extras = json_decode($extras, true) ?: [];
                
                $variantName = $item['variant_name'] ?? '';
                $itemNote = $item['notes'] ?? $item['note'] ?? $item['item_note'] ?? '';
                $itemOptions = $item['options'] ?? [];
                if (is_string($itemOptions)) $itemOptions = json_decode($itemOptions, true) ?: [];
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 4px 0; vertical-align: top;">
                        <?php echo htmlspecialchars($itemName); ?>
                        <?php if (!empty($variantName)): ?>
                        <div style="font-size:10px;color:#555;">Varyant: <?php echo htmlspecialchars($variantName); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($excluded)): ?>
                            <?php foreach ($excluded as $ex):
                                $exName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                                if (empty($exName)) continue;
                            ?>
                            <div style="font-size:10px;color:#c00;font-weight:bold;">✕ ÇIKAR: <?php echo htmlspecialchars($exName); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($extras)): ?>
                            <?php foreach ($extras as $ext):
                                $extName = is_array($ext) ? ($ext['name'] ?? $ext['extra_name'] ?? '') : $ext;
                                $extPrice = is_array($ext) ? floatval($ext['price'] ?? 0) : 0;
                                if (empty($extName)) continue;
                            ?>
                            <div style="font-size:10px;color:#060;">+ <?php echo htmlspecialchars($extName); ?><?php echo $extPrice > 0 ? ' (+' . number_format($extPrice, 2, ',', '.') . ' ₺)' : ''; ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (empty($excluded) && empty($extras) && empty($variantName) && !empty($itemOptions)): ?>
                            <?php foreach ($itemOptions as $opt):
                                $optText = is_array($opt) ? ($opt['text'] ?? ($opt['label'] ?? '')) : $opt;
                                if (empty($optText)) continue;
                            ?>
                            <div style="font-size:10px;color:#555;"><?php echo htmlspecialchars($optText); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($itemNote)): ?>
                        <div style="font-size:10px;color:#333;font-style:italic;">NOT: <?php echo htmlspecialchars($itemNote); ?></div>
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
        <div style="border-top:1px dashed #000;margin:8px 0;padding-top:6px;">
            <div style="font-size:11px;font-weight:bold;">SİPARİŞ NOTU:</div>
            <div style="font-size:10px;margin-top:3px;"><?php echo htmlspecialchars($customerNote); ?></div>
        </div>
        <?php endif; ?>
        
        <div style="border-top: 1px dashed #000; margin: 10px 0;"></div>
        
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
        
        <div style="border-top: 1px dashed #000; margin: 10px 0;"></div>
        
        <div class="receipt-info" style="text-align: center; margin: 10px 0;">
            <?php if ($paymentMethod === 'MIXED'): 
                $breakdown = $receipt['payment_breakdown'] ?? null;
                if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
                $cashAmt = is_array($breakdown) ? floatval($breakdown['cash'] ?? 0) : 0;
                $cardAmt = is_array($breakdown) ? floatval($breakdown['card'] ?? 0) : 0;
            ?>
                <div style="font-weight: bold;">Ödeme: Karışık;</div>
                <div style="font-size:10px;">Kart: <?php echo number_format($cardAmt, 2, ',', '.'); ?> ₺</div>
                <div style="font-size:10px;">Nakit: <?php echo number_format($cashAmt, 2, ',', '.'); ?> ₺</div>
            <?php else: ?>
                <div style="font-weight: bold;">Ödeme: <?php echo $paymentMethods[$paymentMethod] ?? $paymentMethod; ?></div>
            <?php endif; ?>
            <?php
            $paymentTime = $receipt['payment_time'] ?? $receipt['paid_at'] ?? null;
            if ($paymentTime && $paymentMethod !== 'PENDING'):
            ?>
                <div style="font-size:10px;margin-top:2px;">Ödeme Zamanı: <?php echo date('d.m.Y H:i', strtotime($paymentTime)); ?></div>
            <?php endif; ?>
        </div>
        
        <?php if (($receipt['status'] ?? '') === 'VOIDED'): ?>
            <div style="text-align: center; margin: 10px 0; padding: 10px; background: #fee; border: 2px solid #f00;">
                <div style="font-weight: bold; color: #f00;">İPTAL EDİLMİŞ ADİSYON</div>
                <?php if (!empty($receipt['void_reason'])): ?>
                    <div style="font-size: 10px; margin-top: 5px;">Neden: <?php echo htmlspecialchars($receipt['void_reason']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="receipt-footer">
            <?php if (!empty($footerText)): ?>
                <div style="margin: 10px 0; font-size: 11px;"><?php echo nl2br(htmlspecialchars($footerText)); ?></div>
            <?php endif; ?>
            <div style="margin: 10px 0;">Teşekkür Ederiz!</div>
            <div style="font-size: 9px; margin-top: 10px;">
                Bu adisyon <?php echo date('d.m.Y H:i'); ?> tarihinde oluşturulmuştur. Adisyon No: <?php echo htmlspecialchars($receiptNumber); ?>
            </div>
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #000;">
                <div style="font-weight: bold;"><?php echo htmlspecialchars($restaurantName); ?></div>
                <div style="font-weight: bold;">Yönetim Sistemi</div>
                <div><?php echo htmlspecialchars(!empty($settings['website']) ? $settings['website'] : (!empty($settings['site_url']) ? $settings['site_url'] : 'www.qordy.com')); ?></div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto print on load (optional - can be disabled)
        // window.onload = function() {
        //     setTimeout(() => window.print(), 500);
        // };
    </script>
</body>
</html>

