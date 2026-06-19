<?php
$payments = $payments ?? [];
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="p-6 h-full overflow-y-auto bg-[#f4f5fa]">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-black text-slate-900">Ödeme Geçmişi</h1>
            <p class="text-slate-600 mt-2">Tüm ödeme işlemlerinizi görüntüleyin</p>
        </div>

        <!-- Payments Table -->
        <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
            <?php if (empty($payments)): ?>
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <p class="text-slate-600 text-lg">Henüz ödeme kaydı bulunmuyor</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-bold text-slate-700">Tarih</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-slate-700">Paket</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-slate-700">Tutar</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-slate-700">Durum</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-slate-700">Ödeme Yöntemi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($payments as $payment): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        <?php echo date('d.m.Y H:i', strtotime($payment['created_at'] ?? '')); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                        <?php echo htmlspecialchars($payment['package_name'] ?? 'Paket'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-900">
                                        ₺<?php echo number_format($payment['amount'] ?? 0, 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $status = strtolower($payment['status'] ?? 'pending');
                                        $statusColors = [
                                            'completed' => 'bg-green-100 text-green-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'failed' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusLabels = [
                                            'completed' => 'Tamamlandı',
                                            'pending' => 'Bekliyor',
                                            'failed' => 'Başarısız'
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-slate-100 text-slate-800';
                                        $label = $statusLabels[$status] ?? ucfirst($status);
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $colorClass; ?>">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <?php echo htmlspecialchars($payment['payment_method'] ?? 'Kredi Kartı'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
