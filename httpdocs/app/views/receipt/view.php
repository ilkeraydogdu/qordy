<?php
/**
 * Receipt View
 * Adisyon görüntüleme sayfası (print ve PDF linkleri ile)
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';

$receipt = $receipt ?? [];
$order = $order ?? [];
$items = $items ?? [];
$settings = $settings ?? [];
$orderDetailSection = $order_detail_section ?? '';
$otherReceipts = $other_receipts_for_order ?? [];
$receiptListUrl = $receipt_list_url ?? (defined('BASE_URL') ? BASE_URL . '/business/receipts' : '/business/receipts');

$receiptNumber = $receipt['receipt_number'] ?? '';
$receiptId = $receipt['receipt_id'] ?? '';
$orderId = $order['order_id'] ?? '';
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$isEmbed = !empty($_GET['embed']) || (isset($embed) && $embed);
?>

<div class="p-4 sm:p-6 md:p-8 rounded-2xl bg-[#f8fafc] shadow-soft border border-slate-200/80">
    <div class="max-w-4xl mx-auto">
        <!-- Header: minimal -->
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <div class="flex items-center gap-3">
                <?php if (!$isEmbed): ?>
                <a href="<?php echo htmlspecialchars($receiptListUrl); ?>" class="text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-1 text-sm font-bold no-print-receipt">
                    ← Listeye dön
                </a>
                <?php endif; ?>
                <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900">Fiş</h1>
                    <p class="text-slate-400 font-bold uppercase text-[10px] sm:text-xs tracking-widest mt-0.5">
                        <?php echo htmlspecialchars($receiptNumber); ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isEmbed): ?>
                <button type="button" onclick="if(window.parent!==window){window.parent.postMessage('receipt-close','*');}else{window.history.back();}" class="receipt-minimal-close no-print-receipt px-3 py-2 rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 text-sm font-bold transition-colors">
                    Kapat
                </button>
                <?php endif; ?>
                <a href="<?php echo $baseUrl; ?>/receipt/<?php echo htmlspecialchars($receiptId); ?>/print" 
                   target="_blank"
                   class="no-print-receipt px-4 sm:px-6 py-2 sm:py-3 bg-orange-600 text-white rounded-xl sm:rounded-2xl font-black text-xs sm:text-sm hover:bg-orange-500 transition-all flex items-center gap-2">
                    <?php echo icon_printer(['class' => 'w-4 h-4 sm:w-5 sm:h-5']); ?>
                    <span>Yazdır</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/receipt/<?php echo htmlspecialchars($receiptId); ?>/pdf" 
                   target="_blank"
                   class="no-print-receipt px-4 sm:px-6 py-2 sm:py-3 bg-slate-600 text-white rounded-xl sm:rounded-2xl font-black text-xs sm:text-sm hover:bg-slate-500 transition-all flex items-center gap-2">
                    <?php echo icon_file_text(['class' => 'w-4 h-4 sm:w-5 sm:h-5']); ?>
                    <span>PDF</span>
                </a>
            </div>
        </div>

        <?php if ($orderDetailSection !== ''): ?>
        <div class="mb-6">
            <?php echo $orderDetailSection; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($otherReceipts)): ?>
        <div class="mb-6 p-4 sm:p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-sm font-black text-slate-700 uppercase tracking-wider mb-3">Bu siparişe (#<?php echo htmlspecialchars($orderId); ?>) ait diğer fişler</h2>
            <ul class="space-y-2">
                <?php foreach ($otherReceipts as $or): ?>
                <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 border-b border-slate-100 last:border-0 text-sm">
                    <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($or['receipt_number'] ?? $or['receipt_id'] ?? ''); ?></span>
                    <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-600"><?php echo htmlspecialchars($or['receipt_type_label'] ?? $or['receipt_type'] ?? ''); ?></span>
                    <?php if (!empty($or['created_at'])): ?>
                    <span class="text-slate-500 text-xs"><?php echo date('d.m.Y H:i', strtotime($or['created_at'])); ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Receipt Card -->
        <div class="bg-white p-6 sm:p-8 md:p-12 rounded-2xl sm:rounded-3xl border border-slate-50 shadow-soft">
            <!-- Receipt content -->
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-6 border-b-2 border-slate-200 pb-4">
                    <h2 class="text-2xl font-black mb-2"><?php echo htmlspecialchars(trim($settings['business_name'] ?? $settings['restaurant_name'] ?? $settings['site_name'] ?? '') ?: 'İşletme'); ?></h2>
                    <?php if (!empty($settings['business_address']) || !empty($settings['restaurant_address'])): ?>
                        <p class="text-sm text-slate-600"><?php echo htmlspecialchars($settings['business_address'] ?? $settings['restaurant_address'] ?? ''); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($settings['business_phone']) || !empty($settings['restaurant_phone'])): ?>
                        <p class="text-sm text-slate-600">Tel: <?php echo htmlspecialchars($settings['business_phone'] ?? $settings['restaurant_phone'] ?? ''); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="mb-6">
                    <div class="flex justify-between mb-4">
                        <span class="text-slate-600">Adisyon No:</span>
                        <span class="font-black"><?php echo htmlspecialchars($receiptNumber); ?></span>
                    </div>
                    <div class="flex justify-between mb-4">
                        <span class="text-slate-600">Tarih:</span>
                        <span class="font-black"><?php echo date('d.m.Y H:i', strtotime($receipt['created_at'] ?? 'now')); ?></span>
                    </div>
                    <?php if (!empty($order['table_name'])): ?>
                        <div class="flex justify-between mb-4">
                            <span class="text-slate-600">Masa:</span>
                            <span class="font-black"><?php echo htmlspecialchars($order['table_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php 
                    $createdByName = trim($receipt['created_by_name'] ?? '');
                    $createdByRole = strtoupper(trim($receipt['created_by_role'] ?? ''));
                    if ($createdByName !== ''): 
                        $label = ($createdByRole === 'WAITER') ? 'Garson' : 'Kasiyer';
                    ?>
                        <div class="flex justify-between mb-4">
                            <span class="text-slate-600"><?php echo htmlspecialchars($label); ?>:</span>
                            <span class="font-black"><?php echo htmlspecialchars($createdByName); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-6">
                    <h3 class="font-black mb-3 text-lg">Sipariş Detayları</h3>
                    <div class="space-y-2">
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
                            ?>
                            <div class="py-2 border-b border-slate-100">
                                <div class="flex justify-between">
                                    <div>
                                        <div class="font-black"><?php echo htmlspecialchars($itemName); ?></div>
                                        <div class="text-sm text-slate-500"><?php echo $quantity; ?>x <?php echo formatCurrency($price); ?></div>
                                    </div>
                                    <div class="font-black"><?php echo formatCurrency($itemTotal); ?></div>
                                </div>
                                <?php if (!empty($variantName)): ?>
                                    <div class="text-xs text-slate-500 mt-1 ml-2">Varyant: <?php echo htmlspecialchars($variantName); ?></div>
                                <?php endif; ?>
                                <?php foreach ($excluded as $ex):
                                    $exName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                                    if (empty($exName)) continue;
                                ?>
                                    <div class="text-xs text-red-600 font-bold mt-1 ml-2">✕ ÇIKAR: <?php echo htmlspecialchars($exName); ?></div>
                                <?php endforeach; ?>
                                <?php foreach ($extras as $ext):
                                    $extName = is_array($ext) ? ($ext['name'] ?? $ext['extra_name'] ?? '') : $ext;
                                    if (empty($extName)) continue;
                                ?>
                                    <div class="text-xs text-green-700 mt-1 ml-2">+ <?php echo htmlspecialchars($extName); ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($itemNote)): ?>
                                    <div class="text-xs text-slate-600 italic mt-1 ml-2">NOT: <?php echo htmlspecialchars($itemNote); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="border-t-2 border-slate-200 pt-4 space-y-2">
                    <?php
                    $totalAmount = floatval($receipt['total_amount'] ?? 0);
                    $taxAmount = floatval($receipt['tax_amount'] ?? 0);
                    $serviceCharge = floatval($receipt['service_charge'] ?? 0);
                    $discountAmount = floatval($receipt['discount_amount'] ?? 0);
                    $subtotal = $totalAmount - $taxAmount - $serviceCharge + $discountAmount;
                    ?>
                    <div class="flex justify-between">
                        <span>Ara Toplam:</span>
                        <span><?php echo formatCurrency($subtotal); ?></span>
                    </div>
                    <?php if ($serviceCharge > 0): ?>
                        <div class="flex justify-between">
                            <span>Servis:</span>
                            <span><?php echo formatCurrency($serviceCharge); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($taxAmount > 0): ?>
                        <div class="flex justify-between">
                            <span>KDV:</span>
                            <span><?php echo formatCurrency($taxAmount); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($discountAmount > 0): ?>
                        <div class="flex justify-between">
                            <span>İndirim:</span>
                            <span>-<?php echo formatCurrency($discountAmount); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-black pt-2 border-t border-slate-200">
                        <span>TOPLAM:</span>
                        <span class="text-orange-600"><?php echo formatCurrency($totalAmount); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

