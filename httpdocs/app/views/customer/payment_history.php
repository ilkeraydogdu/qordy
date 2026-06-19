<?php
/**
 * Customer Payment History Page
 * Müşteriler için ödeme geçmişi sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';

$payments = $payments ?? [];
$customer = $customer ?? null;
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6 animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Header -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-800 mb-2 sm:mb-3">Ödeme Geçmişi</h1>
        <p class="text-sm sm:text-base text-slate-600">Tüm ödeme işlemlerinizi buradan görüntüleyebilirsiniz.</p>
    </div>
    
    <!-- Payments List -->
    <?php if (empty($payments)): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100 text-center py-8 sm:py-10 md:py-12">
        <p class="text-slate-500 text-sm sm:text-base">Henüz ödeme kaydınız bulunmamaktadır.</p>
        <a href="<?php echo BASE_URL; ?>/business/dashboard" class="inline-block mt-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 sm:py-3 px-4 sm:px-5 rounded-lg sm:rounded-xl transition-colors shadow-lg hover:shadow-xl">
            Paketleri Görüntüle
        </a>
    </div>
    <?php else: ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm sm:text-base">
                <thead>
                    <tr class="border-b-2 border-slate-200">
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Tarih</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Paket</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Tutar</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Yöntem</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Durum</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">İşlem ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php 
                            if ($payment['payment_date']) {
                                echo date('d.m.Y H:i', strtotime($payment['payment_date']));
                            } elseif ($payment['created_at']) {
                                echo date('d.m.Y H:i', strtotime($payment['created_at']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php echo htmlspecialchars($payment['package_name'] ?? 'Paket'); ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-800">
                            ₺<?php echo number_format($payment['amount'] ?? 0, 2, ',', '.'); ?>
                            <?php if (!empty($payment['currency']) && $payment['currency'] !== 'TRY'): ?>
                            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($payment['currency']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php 
                            $methodLabels = [
                                'iyzico' => 'iyzico',
                                'manual' => 'Manuel',
                                'gateway' => 'Online Ödeme',
                                'bank_transfer' => 'Havale/EFT',
                                'saved_card' => 'Kayıtlı Kart'
                            ];
                            echo $methodLabels[$payment['payment_method']] ?? $payment['payment_method'];
                            ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4">
                            <?php 
                            $statusColors = [
                                'completed' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'failed' => 'bg-red-100 text-red-800',
                                'refunded' => 'bg-slate-100 text-slate-800'
                            ];
                            $statusColor = $statusColors[$payment['payment_status']] ?? 'bg-slate-100 text-slate-800';
                            $statusLabels = [
                                'completed' => 'Tamamlandı',
                                'pending' => 'Beklemede',
                                'failed' => 'Başarısız',
                                'refunded' => 'İade Edildi'
                            ];
                            $statusLabel = $statusLabels[$payment['payment_status']] ?? $payment['payment_status'];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black <?php echo $statusColor; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-500 font-mono text-xs">
                            <?php echo htmlspecialchars(substr($payment['payment_id'] ?? '', 0, 12)); ?>...
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
</div>
