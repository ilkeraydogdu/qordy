<?php
/**
 * Billing & Invoices Page for BUSINESS_MANAGER
 * Fatura bilgileri ve ödeme geçmişi
 */

require_once __DIR__ . '/../../helpers/translations.php';

$customer = $customer ?? null;
$invoices = $invoices ?? [];

$companyName = $customer['company_name'] ?? '';
$taxNumber = $customer['tax_number'] ?? '';
$address = $customer['address'] ?? '';
$city = $customer['city'] ?? '';
$country = $customer['country'] ?? 'Türkiye';
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-4 sm:space-y-5 md:space-y-6 no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Page Header -->
    <div class="q-card q-card--pad">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-800 mb-2">Fatura Bilgileri</h1>
        <p class="text-slate-600">Faturalarınızı görüntüleyin ve indirin.</p>
    </div>
    
    <!-- Billing Address Card -->
    <div class="q-card q-card--pad">
        <h2 class="text-xl font-black text-slate-800 mb-4">Fatura Adresi</h2>
        <div class="text-slate-700 space-y-2">
            <?php if (!empty($companyName)): ?>
                <p class="font-bold"><?php echo htmlspecialchars($companyName); ?></p>
            <?php endif; ?>
            <?php if (!empty($taxNumber)): ?>
                <p class="text-sm">Vergi No: <?php echo htmlspecialchars($taxNumber); ?></p>
            <?php endif; ?>
            <?php if (!empty($address)): ?>
                <p class="text-sm"><?php echo htmlspecialchars($address); ?></p>
            <?php endif; ?>
            <?php if (!empty($city)): ?>
                <p class="text-sm"><?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($country); ?></p>
            <?php endif; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/business/company" class="inline-flex items-center gap-2 mt-4 text-sm font-bold text-indigo-600 hover:text-indigo-700">
            Düzenle
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </a>
    </div>
    
    <!-- Invoices List -->
    <div class="q-card q-card--pad">
        <h2 class="text-xl font-black text-slate-800 mb-6">Faturalar</h2>
        
        <?php if (empty($invoices)): ?>
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-black text-slate-800 mb-2">Henüz Fatura Yok</h3>
                <p class="text-slate-600">Paket satın aldığınızda faturalarınız burada görünecektir.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-slate-200">
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Fatura No</th>
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Tarih</th>
                            <th class="text-right py-3 px-4 text-sm font-black text-slate-700">Tutar</th>
                            <th class="text-center py-3 px-4 text-sm font-black text-slate-700">Durum</th>
                            <th class="text-center py-3 px-4 text-sm font-black text-slate-700">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-4 px-4 font-medium text-slate-800"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td class="py-4 px-4 text-slate-600"><?php echo date('d.m.Y', strtotime($invoice['date'])); ?></td>
                            <td class="py-4 px-4 text-right font-bold text-slate-800"><?php echo number_format($invoice['amount'], 2); ?> ₺</td>
                            <td class="py-4 px-4 text-center">
                                <?php if ($invoice['status'] === 'paid'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-green-100 text-green-700 rounded-full">Ödendi</span>
                                <?php elseif ($invoice['status'] === 'pending'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-amber-100 text-amber-700 rounded-full">Bekliyor</span>
                                <?php else: ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-red-100 text-red-700 rounded-full">İptal</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <a href="<?php echo BASE_URL; ?>/business/billing/<?php echo $invoice['id']; ?>/download" 
                                   class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:text-indigo-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    İndir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
</div>
