<?php
/**
 * Customer Order Detail Page
 * Müşteriler için sipariş detay sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';

$order = $order ?? [];
$order_items = $order_items ?? [];
$receipt = $receipt ?? null;
$table = $table ?? null;
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6 animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Header -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-800 mb-2 sm:mb-3">Sipariş Detayı</h1>
                <p class="text-sm sm:text-base text-slate-600">Sipariş #<?php echo htmlspecialchars(substr($order['order_id'] ?? '', 0, 12)); ?></p>
            </div>
            <a href="<?php echo BASE_URL; ?>/customer/orders" class="text-indigo-600 hover:text-indigo-700 font-bold text-sm sm:text-base">
                ← Geri Dön
            </a>
        </div>
    </div>
    
    <?php if (empty($order)): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100 text-center py-8 sm:py-10 md:py-12">
        <p class="text-slate-500 text-sm sm:text-base">Sipariş bulunamadı.</p>
    </div>
    <?php else: ?>
    
    <!-- Order Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Order Status -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
                <h2 class="text-lg sm:text-xl font-black text-slate-800 mb-4">Sipariş Bilgileri</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Sipariş No</div>
                        <div class="text-sm sm:text-base font-mono font-black text-slate-800"><?php echo htmlspecialchars($order['order_id'] ?? ''); ?></div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Tarih</div>
                        <div class="text-sm sm:text-base font-black text-slate-800">
                            <?php 
                            if ($order['created_at']) {
                                echo date('d.m.Y H:i', strtotime($order['created_at']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Masa</div>
                        <div class="text-sm sm:text-base font-black text-slate-800"><?php echo htmlspecialchars($order['table_name'] ?? 'Masa Yok'); ?></div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Durum</div>
                        <div>
                            <?php 
                            $status = strtoupper((string)($order['status'] ?? ''));
                            $statusColors = [
                                'PENDING' => 'bg-yellow-100 text-yellow-800',
                                'PREPARING' => 'bg-blue-100 text-blue-800',
                                'READY' => 'bg-indigo-100 text-indigo-800',
                                'SERVED' => 'bg-green-100 text-green-800',
                                'CANCELLED' => 'bg-red-100 text-red-800',
                                'REFUNDED' => 'bg-slate-100 text-slate-800'
                            ];
                            $statusLabels = [
                                'PENDING' => 'Beklemede',
                                'PREPARING' => 'Hazırlanıyor',
                                'READY' => 'Hazır',
                                'SERVED' => 'Servis Edildi',
                                'CANCELLED' => 'İptal',
                                'REFUNDED' => 'İade Edildi'
                            ];
                            $statusColor = $statusColors[$status] ?? 'bg-slate-100 text-slate-800';
                            $statusLabel = $statusLabels[$status] ?? $status;
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm font-black <?php echo $statusColor; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($order['customer_note'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-200">
                    <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Müşteri Notu</div>
                    <div class="text-sm sm:text-base text-slate-700"><?php echo nl2br(htmlspecialchars($order['customer_note'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Items -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
                <h2 class="text-lg sm:text-xl font-black text-slate-800 mb-4">Sipariş İçeriği</h2>
                <?php if (empty($order_items)): ?>
                <p class="text-slate-500 text-sm sm:text-base">Sipariş içeriği bulunamadı.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($order_items as $item): ?>
                    <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
                        <div class="flex-1">
                            <div class="font-black text-slate-800 text-sm sm:text-base"><?php echo htmlspecialchars($item['item_name'] ?? 'Ürün'); ?></div>
                            <?php if (!empty($item['note'])): ?>
                            <div class="text-xs sm:text-sm text-slate-500 mt-1">
                                <strong>Not:</strong> <?php echo htmlspecialchars($item['note']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Özelleştirmeleri göster (malzemeler, ekstralar)
                            $customizations = [];
                            
                            // Çıkarılan malzemeler
                            if (!empty($item['excluded_ingredients'])) {
                                $excludedIngredients = is_string($item['excluded_ingredients']) 
                                    ? json_decode($item['excluded_ingredients'], true) 
                                    : $item['excluded_ingredients'];
                                
                                if (is_array($excludedIngredients) && !empty($excludedIngredients)) {
                                    $customizations[] = '<span class="text-red-600">Çıkarılan:</span> ' . implode(', ', array_map('htmlspecialchars', $excludedIngredients));
                                }
                            }
                            
                            // Eklenen ekstralar
                            if (!empty($item['selected_extras'])) {
                                $selectedExtras = is_string($item['selected_extras']) 
                                    ? json_decode($item['selected_extras'], true) 
                                    : $item['selected_extras'];
                                
                                if (is_array($selectedExtras) && !empty($selectedExtras)) {
                                    $extraNames = [];
                                    foreach ($selectedExtras as $extra) {
                                        if (is_array($extra) && isset($extra['name'])) {
                                            $extraNames[] = htmlspecialchars($extra['name']);
                                        } elseif (is_string($extra)) {
                                            $extraNames[] = htmlspecialchars($extra);
                                        }
                                    }
                                    if (!empty($extraNames)) {
                                        $customizations[] = '<span class="text-green-600">Ekstra:</span> ' . implode(', ', $extraNames);
                                    }
                                }
                            }
                            
                            // Özelleştirmeleri göster
                            if (!empty($customizations)): ?>
                            <div class="text-xs text-slate-600 mt-1">
                                <?php echo implode('<br>', $customizations); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-sm sm:text-base font-black text-slate-800">
                                <?php echo number_format($item['quantity'] ?? 0); ?> x ₺<?php echo number_format($item['price'] ?? 0, 2, ',', '.'); ?>
                            </div>
                            <div class="text-xs sm:text-sm text-slate-600 mt-1">
                                ₺<?php echo number_format(($item['quantity'] ?? 0) * ($item['price'] ?? 0), 2, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Total -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
                <h2 class="text-lg sm:text-xl font-black text-slate-800 mb-4">Özet</h2>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm sm:text-base">
                        <span class="text-slate-600">Ara Toplam</span>
                        <span class="font-black text-slate-800">₺<?php echo number_format($order['total_amount'] ?? 0, 2, ',', '.'); ?></span>
                    </div>
                    <?php if (!empty($order['service_charge']) && $order['service_charge'] > 0): ?>
                    <div class="flex justify-between text-sm sm:text-base">
                        <span class="text-slate-600">Servis Ücreti</span>
                        <span class="font-black text-slate-800">₺<?php echo number_format($order['service_charge'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="pt-3 border-t-2 border-slate-200">
                        <div class="flex justify-between">
                            <span class="text-base sm:text-lg font-black text-slate-800">Toplam</span>
                            <span class="text-base sm:text-lg font-black text-indigo-600">₺<?php echo number_format($order['total_amount'] ?? 0, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
                <h2 class="text-lg sm:text-xl font-black text-slate-800 mb-4">İşlemler</h2>
                <div class="space-y-2">
                    <?php if ($receipt): 
                        $receiptIdForModal = $receipt['receipt_id'];
                        $receiptViewUrl = BASE_URL . '/receipt/' . htmlspecialchars($receiptIdForModal) . '?embed=1';
                        $receiptPdfUrl = BASE_URL . '/receipt/' . htmlspecialchars($receiptIdForModal) . '/pdf';
                        $receiptPrintUrl = BASE_URL . '/receipt/' . htmlspecialchars($receiptIdForModal) . '/print';
                    ?>
                    <button type="button" onclick="openReceiptModal('<?php echo htmlspecialchars($receiptViewUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($receiptPdfUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($receiptPrintUrl, ENT_QUOTES); ?>')" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 sm:py-3 px-4 rounded-lg text-center transition-colors">
                        Fişi Görüntüle
                    </button>
                    <a href="<?php echo BASE_URL; ?>/receipt/<?php echo htmlspecialchars($receipt['receipt_id']); ?>/pdf" target="_blank" class="block w-full bg-slate-600 hover:bg-slate-700 text-white font-bold py-2 sm:py-3 px-4 rounded-lg text-center transition-colors">
                        PDF İndir
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($receipt): ?>
    <!-- Fiş modal: aynı sayfa içinde açılır pencere -->
    <div id="customer-receipt-modal" class="fixed inset-0 z-[9999] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCustomerReceiptModal()"></div>
        <div class="absolute inset-4 sm:inset-6 md:inset-8 flex flex-col items-center justify-center p-0">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <span class="font-black text-slate-800">Fiş</span>
                    <button type="button" onclick="closeCustomerReceiptModal()" class="p-2 rounded-xl hover:bg-slate-200 text-slate-600 transition-colors" aria-label="Kapat">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-hidden">
                    <iframe id="customer-receipt-iframe" src="about:blank" class="w-full h-full min-h-[60vh] border-0" title="Fiş"></iframe>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-3 border-t border-slate-200 bg-slate-50">
                    <a id="customer-receipt-pdf-link" href="#" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-600 text-white text-sm font-bold hover:bg-slate-500 transition-colors">PDF İndir</a>
                    <a id="customer-receipt-print-link" href="#" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-600 text-white text-sm font-bold hover:bg-orange-500 transition-colors">Yazdır</a>
                    <button type="button" onclick="closeCustomerReceiptModal()" class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-300 transition-colors">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function() {
        function closeCustomerReceiptModal() {
            var m = document.getElementById('customer-receipt-modal');
            var iframe = document.getElementById('customer-receipt-iframe');
            if (m) m.classList.add('hidden');
            if (iframe) iframe.src = 'about:blank';
        }
        window.closeCustomerReceiptModal = closeCustomerReceiptModal;
        window.openReceiptModal = function(viewUrl, pdfUrl, printUrl) {
            var m = document.getElementById('customer-receipt-modal');
            var iframe = document.getElementById('customer-receipt-iframe');
            var pdfLink = document.getElementById('customer-receipt-pdf-link');
            var printLink = document.getElementById('customer-receipt-print-link');
            if (m && iframe) {
                iframe.src = viewUrl;
                if (pdfLink) pdfLink.href = pdfUrl || '#';
                if (printLink) printLink.href = printUrl || '#';
                m.classList.remove('hidden');
            }
        };
        window.addEventListener('message', function(e) {
            if (e.data === 'receipt-close') closeCustomerReceiptModal();
        });
    })();
    </script>
    <?php endif; ?>
    
    <?php endif; ?>
</div>
