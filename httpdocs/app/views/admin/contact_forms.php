<?php
require_once __DIR__ . '/../../helpers/translations.php';

$title = 'İletişim Formları' . ' - ' . getAppConfig()->getAppName();
$contactForms = $contactForms ?? [];
$status = $status ?? 'all';
$allCount = $allCount ?? 0;
$newCount = $newCount ?? 0;
$contactedCount = $contactedCount ?? 0;
$closedCount = $closedCount ?? 0;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 sm:mb-6 lg:mb-8 gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">İletişim Formları</h1>
            <p class="text-slate-400 font-bold uppercase text-[8px] sm:text-[9px] lg:text-[10px] tracking-widest mt-1">Müşteri İletişim Talepleri</p>
        </div>
    </header>

    <!-- Status Filter Tabs -->
    <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-5 lg:p-6 shadow-soft border border-slate-100 mb-4 sm:mb-6">
        <div class="flex flex-wrap gap-2 sm:gap-3">
            <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms?status=all" 
               class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm transition-all <?php echo $status === 'all' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                Tümü <span class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"><?php echo $allCount; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms?status=new" 
               class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm transition-all <?php echo $status === 'new' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                Yeni <span class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"><?php echo $newCount; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms?status=contacted" 
               class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm transition-all <?php echo $status === 'contacted' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                İletişime Geçildi <span class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"><?php echo $contactedCount; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms?status=closed" 
               class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm transition-all <?php echo $status === 'closed' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                Kapatıldı <span class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"><?php echo $closedCount; ?></span>
            </a>
        </div>
    </div>

    <!-- Contact Forms List -->
    <div class="bg-white rounded-xl sm:rounded-2xl lg:rounded-[30px] border border-slate-50 shadow-soft overflow-hidden">
        <?php if (empty($contactForms)): ?>
            <div class="p-8 sm:p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <p class="text-slate-400 font-bold text-sm sm:text-base">Henüz iletişim formu gönderilmemiş.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="q-table">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">Ad Soyad</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">E-posta</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">Telefon</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">İşletme</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">Durum</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">Tarih</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($contactForms as $form): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 text-sm font-bold text-slate-900"><?php echo htmlspecialchars($form['full_name']); ?></td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <a href="mailto:<?php echo htmlspecialchars($form['email']); ?>" class="hover:text-indigo-600 transition-colors">
                                        <?php echo htmlspecialchars($form['email']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <?php if (!empty($form['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($form['phone']); ?>" class="hover:text-indigo-600 transition-colors">
                                            <?php echo htmlspecialchars($form['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <?php echo !empty($form['company_name']) ? htmlspecialchars($form['company_name']) : '<span class="text-slate-400">-</span>'; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statusColors = [
                                        'new' => 'bg-blue-100 text-blue-700',
                                        'contacted' => 'bg-yellow-100 text-yellow-700',
                                        'closed' => 'bg-green-100 text-green-700'
                                    ];
                                    $statusLabels = [
                                        'new' => 'Yeni',
                                        'contacted' => 'İletişime Geçildi',
                                        'closed' => 'Kapatıldı'
                                    ];
                                    $currentStatus = $form['status'] ?? 'new';
                                    ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $statusColors[$currentStatus] ?? 'bg-slate-100 text-slate-700'; ?>">
                                        <?php echo $statusLabels[$currentStatus] ?? 'Bilinmiyor'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <?php 
                                    $createdAt = new DateTime($form['created_at']);
                                    echo $createdAt->format('d.m.Y H:i');
                                    ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms/view/<?php echo htmlspecialchars($form['contact_id']); ?>" 
                                           class="px-3 sm:px-4 py-1.5 sm:py-2 bg-indigo-500 text-white rounded-lg sm:rounded-xl text-xs font-black hover:bg-indigo-700 transition-all shadow-lg hover:shadow-xl active:scale-95">
                                            Detay
                                        </a>
                                        <?php if ($currentStatus === 'new'): ?>
                                            <button onclick="updateStatus('<?php echo htmlspecialchars($form['contact_id']); ?>', 'contacted')" 
                                                    class="px-3 sm:px-4 py-1.5 sm:py-2 bg-yellow-500 text-white rounded-lg sm:rounded-xl text-xs font-black hover:bg-yellow-600 transition-all shadow-lg hover:shadow-xl active:scale-95">
                                                İletişime Geç
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($currentStatus !== 'closed'): ?>
                                            <button onclick="updateStatus('<?php echo htmlspecialchars($form['contact_id']); ?>', 'closed')" 
                                                    class="px-3 sm:px-4 py-1.5 sm:py-2 bg-green-500 text-white rounded-lg sm:rounded-xl text-xs font-black hover:bg-green-600 transition-all shadow-lg hover:shadow-xl active:scale-95">
                                                Kapat
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteContactForm('<?php echo htmlspecialchars($form['contact_id']); ?>')" 
                                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-red-500 text-white rounded-lg sm:rounded-xl text-xs font-black hover:bg-red-600 transition-all shadow-lg hover:shadow-xl active:scale-95">
                                            Sil
                                        </button>
                                    </div>
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
<script>
async function updateStatus(contactId, status) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Durumu güncellemek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Durumu güncellemek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/qodmin/contact-forms/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.NotificationManager) window.NotificationManager.success('✓ ' + (result.message || 'Durum başarıyla güncellendi'));
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('✗ ' + (result.message || 'Bir hata oluştu'));
        }
    } catch (error) {
        console.error('Error updating status:', error);
        if (window.NotificationManager) window.NotificationManager.error('✗ Bir hata oluştu');
    }
}

async function deleteContactForm(contactId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu iletişim formunu silmek istediğinize emin misiniz? Bu işlem geri alınamaz.', 'Onay');
    } else {
        confirmed = confirm('Bu iletişim formunu silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');
    }
    if (!confirmed) return;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/qodmin/contact-forms/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.NotificationManager) window.NotificationManager.success('✓ ' + (result.message || 'İletişim formu başarıyla silindi'));
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('✗ ' + (result.message || 'Bir hata oluştu'));
        }
    } catch (error) {
        console.error('Error deleting contact form:', error);
        if (window.NotificationManager) window.NotificationManager.error('✗ Bir hata oluştu');
    }
}
</script>
