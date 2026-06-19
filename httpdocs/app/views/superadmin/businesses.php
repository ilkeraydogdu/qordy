<?php
/**
 * Super Admin - All Businesses List
 * Tüm müşterilerin listesi ve yönetimi
 */

require_once __DIR__ . '/../../helpers/translations.php';

$customers = $customers ?? [];
$filter = $filter ?? 'all';
$search = $search ?? '';
?>

<div class="q-page animate-slide-up">
  <div class="q-container">
    
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 mb-2">Tüm İşletmeler</h1>
            <p class="text-slate-600">Platform üzerindeki tüm müşterileri görüntüleyin ve yönetin.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/qodmin/businesses/create" 
           class="px-6 py-3 bg-orange-500 text-white rounded-xl font-black hover:bg-orange-600 transition-colors whitespace-nowrap">
            + Yeni İşletme Ekle
        </a>
    </div>

    <?php
    // Meta WhatsApp izin dağılımı — sütun doldurulduğundan emin olmak için
    // isset olanları say, customers array'i filtrelenmiş de olsa yeterli.
    $metaOnCount  = 0;
    $metaAllCount = count($customers);
    foreach ($customers as $_c) {
        if ((int)($_c['meta_whatsapp_enabled'] ?? 0) === 1) { $metaOnCount++; }
    }
    ?>
    <div class="bg-white p-4 rounded-xl shadow-soft border border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 2.1.55 4.15 1.6 5.96L2 22l4.24-1.11a9.9 9.9 0 004.77 1.21c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01C17.18 3.03 14.69 2 12.04 2z"/></svg>
            </div>
            <div>
                <div class="text-sm font-black text-slate-800">Meta WhatsApp Kullanım İzinleri</div>
                <div class="text-xs text-slate-600 mt-0.5">
                    Toplam <strong><?php echo $metaAllCount; ?></strong> işletmeden
                    <strong class="text-emerald-700"><?php echo $metaOnCount; ?></strong> tanesi Meta WhatsApp sıra bildirimlerini kullanıyor.
                    Aşağıdaki tabloda <b>Meta WhatsApp</b> sütunundaki rozete tıklayarak hızlıca aç/kapat yapabilirsiniz.
                </div>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/qodmin/settings?tab=meta"
           class="text-xs font-bold text-orange-600 hover:text-orange-700 whitespace-nowrap">
            Meta API ayarlarına git →
        </a>
    </div>
    
    <!-- Filters -->
    <div class="bg-white p-4 rounded-xl shadow-soft border border-slate-100">
        <form method="GET" action="<?php echo buildUrl('/qodmin/businesses'); ?>" class="flex flex-col sm:flex-row gap-3" id="businesses-filter-form">
            <!-- Search -->
            <div class="flex-1">
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="İsim, email veya şirket adıyla ara..."
                       class="w-full px-4 py-2 rounded-lg border-2 border-slate-200 focus:border-indigo-500 transition-all">
            </div>
            
            <!-- Filter -->
            <select name="filter" 
                    class="px-4 py-2 rounded-lg border-2 border-slate-200 focus:border-orange-500 transition-all font-bold bg-white"
                    onchange="this.form.submit()">
                <option value="all" <?php echo ($filter === 'all' || empty($filter)) ? 'selected' : ''; ?>>📋 Tümü</option>
                <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>✅ Aktif (Paket Almış)</option>
                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>⏳ Beklemede (Paket Almamış)</option>
                <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>❌ Pasif</option>
            </select>
            
            <button type="submit" 
                    class="px-6 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white font-black transition-all shadow-md hover:shadow-lg">
                🔍 Filtrele
            </button>
        </form>
    </div>
    
    <!-- Customers List -->
    <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
        <?php if (empty($customers)): ?>
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h3 class="text-lg font-black text-slate-800 mb-2">Müşteri Bulunamadı</h3>
                <p class="text-slate-600 mb-4">
                    <?php if ($search): ?>
                        Arama kriterlerinize uygun müşteri bulunamadı.
                    <?php elseif ($filter && $filter !== 'all'): ?>
                        Bu filtre için müşteri bulunamadı. <strong>"<?php echo htmlspecialchars($filter); ?>"</strong> durumunda müşteri yok.
                    <?php else: ?>
                        Henüz kayıtlı müşteri bulunmamaktadır.
                    <?php endif; ?>
                </p>
                <?php if ($filter && $filter !== 'all'): ?>
                    <a href="<?php echo BASE_URL; ?>/qodmin/businesses" 
                       class="inline-block px-6 py-2 bg-orange-500 text-white rounded-lg font-bold hover:bg-orange-600 transition-all">
                        Tüm İşletmeleri Göster
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="q-table">
                    <thead>
                        <tr class="border-b-2 border-slate-200">
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Müşteri</th>
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Email</th>
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Şirket</th>
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Subdomain</th>
                            <th class="text-left py-3 px-4 text-sm font-black text-slate-700">Paket</th>
                            <th class="text-center py-3 px-4 text-sm font-black text-slate-700">Durum</th>
                            <th class="text-center py-3 px-4 text-sm font-black text-slate-700">Meta WhatsApp</th>
                            <th class="text-center py-3 px-4 text-sm font-black text-slate-700">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-4 px-4 font-medium text-slate-800">
                                <?php 
                                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                                echo htmlspecialchars($name ?: 'Adsız');
                                ?>
                            </td>
                            <td class="py-4 px-4 text-slate-600"><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td class="py-4 px-4 text-slate-600"><?php echo htmlspecialchars($customer['company_name'] ?? '-'); ?></td>
                            <td class="py-4 px-4 text-slate-600 font-mono text-xs">
                                <?php if (!empty($customer['subdomain'])): ?>
                                    <span class="text-orange-600"><?php echo htmlspecialchars($customer['subdomain']); ?>.qordy.com</span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php 
                                $hasActiveSubscription = !empty($customer['subscription_id']);
                                $packageName = $customer['package_name'] ?? null;
                                $isTrial = !empty($customer['is_trial']);
                                
                                if ($hasActiveSubscription && !empty($packageName)) {
                                    if ($isTrial) {
                                        echo '<div class="flex flex-col gap-1">';
                                        echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Deneme</span>';
                                        echo '<span class="text-[10px] text-slate-500">' . htmlspecialchars($packageName) . '</span>';
                                        echo '</div>';
                                    } else {
                                        echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700">';
                                        echo htmlspecialchars($packageName);
                                        echo '</span>';
                                    }
                                } else {
                                    echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">—</span>';
                                }
                                ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <?php 
                                $isActive = isset($customer['is_active']) ? (int)$customer['is_active'] : 1;
                                $qrMenuStatus = $customer['qr_menu_status'] ?? 'active';
                                $hasActiveSubscription = !empty($customer['subscription_id']);
                                $isTrial = !empty($customer['is_trial']);
                                $latestStatus = $customer['latest_subscription_status'] ?? null;
                                $latestIsTrial = !empty($customer['latest_subscription_is_trial']);
                                $latestEnd = $customer['latest_subscription_end'] ?? null;

                                if (!$isActive): ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-block px-3 py-1 text-xs font-bold bg-red-100 text-red-700 rounded-full">Pasif</span>
                                        <?php if ($qrMenuStatus === 'menu_only'): ?>
                                            <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 rounded-full">QR: Sadece Menü</span>
                                        <?php elseif ($qrMenuStatus === 'passive'): ?>
                                            <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-red-50 text-red-600 rounded-full">QR: Kapalı</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($hasActiveSubscription && $isTrial): ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-block px-3 py-1 text-xs font-bold bg-blue-100 text-blue-700 rounded-full">Deneme</span>
                                        <?php if ($latestEnd): ?>
                                            <span class="text-[10px] text-slate-400"><?php echo date('d.m.Y', strtotime($latestEnd)); ?>'e kadar</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($hasActiveSubscription): ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-block px-3 py-1 text-xs font-bold bg-green-100 text-green-700 rounded-full">Aktif</span>
                                        <?php if ($latestEnd): ?>
                                            <span class="text-[10px] text-slate-400"><?php echo date('d.m.Y', strtotime($latestEnd)); ?>'e kadar</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($latestStatus === 'expired' && $latestIsTrial): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-amber-100 text-amber-700 rounded-full">Deneme Bitti</span>
                                <?php elseif ($latestStatus === 'expired'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-red-100 text-red-700 rounded-full">Süresi Doldu</span>
                                <?php elseif ($latestStatus === 'cancelled'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-slate-100 text-slate-600 rounded-full">İptal Edildi</span>
                                <?php elseif ($latestStatus === 'suspended'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-orange-100 text-orange-700 rounded-full">Askıda</span>
                                <?php elseif ($latestStatus === 'pending'): ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">Ödeme Bekliyor</span>
                                <?php else: ?>
                                    <span class="inline-block px-3 py-1 text-xs font-bold bg-slate-100 text-slate-500 rounded-full">Abonelik Yok</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <?php $metaOn = (int)($customer['meta_whatsapp_enabled'] ?? 0) === 1; ?>
                                <button type="button"
                                        data-meta-toggle
                                        data-customer-id="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                        data-enabled="<?php echo $metaOn ? '1' : '0'; ?>"
                                        title="Bu işletmenin Meta WhatsApp (sıra bildirimi) kullanımını aç/kapat"
                                        class="px-2.5 py-1 rounded-full text-xs font-black transition-all inline-flex items-center gap-1.5 <?php echo $metaOn ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'; ?>">
                                    <span class="w-2 h-2 rounded-full <?php echo $metaOn ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                    <span data-meta-label><?php echo $metaOn ? 'Açık' : 'Kapalı'; ?></span>
                                </button>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo $customer['customer_id']; ?>" 
                                       class="px-3 py-1 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-bold hover:bg-indigo-200 transition-all">
                                        Detay
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo $customer['customer_id']; ?>/login-as" 
                                       class="px-3 py-1 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold hover:bg-purple-200 transition-all"
                                       onclick="event.preventDefault(); handleLoginAs(this);">
                                        Giriş Yap
                                    </a>
                                    <?php $isActiveCurrent = isset($customer['is_active']) ? (int)$customer['is_active'] : 1; ?>
                                    <button onclick="toggleBusinessStatus('<?php echo $customer['customer_id']; ?>', <?php echo $isActiveCurrent; ?>, '<?php echo addslashes($customer['company_name'] ?? $customer['email']); ?>')"
                                            class="px-3 py-1 rounded-lg <?php echo $isActiveCurrent ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> text-xs font-bold transition-all">
                                        <?php echo $isActiveCurrent ? 'Pasife Al' : 'Aktife Al'; ?>
                                    </button>
                                    <button onclick="openDeleteModal('<?php echo $customer['customer_id']; ?>', '<?php echo addslashes($customer['company_name'] ?? $customer['email']); ?>')"
                                            class="px-3 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold hover:bg-red-200 transition-all">
                                        Sil
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 text-center text-sm text-slate-600">
                Toplam <span class="font-bold"><?php echo count($customers); ?></span> müşteri görüntüleniyor
            </div>
        <?php endif; ?>
    </div>
    
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteBusinessModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-black text-red-600 mb-4">⚠️ İşletme Silme</h3>
        <p class="text-slate-700 mb-4">
            <strong id="deleteBusinessName"></strong> işletmesini <span class="text-red-600 font-bold">KALICI OLARAK</span> silmek üzeresiniz.
        </p>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="text-sm text-red-800 font-bold mb-2">Bu işlem geri alınamaz!</p>
            <ul class="text-xs text-red-700 space-y-1">
                <li>✗ Tüm menü öğeleri</li>
                <li>✗ Tüm siparişler</li>
                <li>✗ Tüm masalar</li>
                <li>✗ Tüm personel kayıtları</li>
                <li>✗ Tüm finansal veriler</li>
                <li>✗ Subdomain (Plesk'ten)</li>
                <li>✗ Dosyalar</li>
            </ul>
        </div>
        <p class="text-sm text-slate-600 mb-4">
            Onaylamak için işletme adını yazın: <strong id="confirmBusinessName"></strong>
        </p>
        <input type="text" id="deleteConfirmInput" 
               placeholder="İşletme adını buraya yazın"
               class="w-full px-4 py-2 border-2 border-red-300 rounded-lg mb-4">
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" 
                    class="flex-1 px-4 py-2 bg-slate-200 rounded-lg font-bold hover:bg-slate-300 transition-all">
                İptal
            </button>
            <button id="confirmDeleteBtn" disabled 
                    onclick="confirmDelete()"
                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed hover:bg-red-700 transition-all">
                Sil
            </button>
        </div>
    </div>
</div>

<!-- QR Menu Status Modal -->
<div id="qrMenuStatusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4 shadow-2xl">
        <h3 class="text-xl font-black text-slate-800 mb-2">🔒 İşletmeyi Pasife Al</h3>
        <p class="text-slate-600 mb-1 text-sm">
            <strong id="qrModalBusinessTitle"></strong> işletmesini pasife alıyorsunuz.
        </p>
        <p class="text-slate-500 text-sm mb-5">QR menünün müşterilere nasıl görüneceğini seçin:</p>
        
        <div class="space-y-3 mb-6">
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-all has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                <input type="radio" name="qr_menu_status" value="menu_only" class="mt-1 accent-amber-500" checked>
                <div>
                    <div class="font-black text-slate-800">📋 Sadece Menü Görüntüleme</div>
                    <p class="text-xs text-slate-500 mt-1">Müşteriler QR menüyü açabilir ve ürünleri görebilir. Ancak <strong>sipariş veremez</strong>, <strong>garson çağıramaz</strong> ve <strong>hesap isteyemez</strong>. Tüm etkileşim butonları gizlenir.</p>
                </div>
            </label>
            
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-red-400 transition-all has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                <input type="radio" name="qr_menu_status" value="passive" class="mt-1 accent-red-500">
                <div>
                    <div class="font-black text-slate-800">🚫 Tamamen Kapalı</div>
                    <p class="text-xs text-slate-500 mt-1">Müşteriler QR kodu okuttuğunda sadece <strong>"Hoşgeldiniz [İşletme Adı], QR menümüz geçici olarak servis dışıdır"</strong> mesajı görür. Menü dahil hiçbir şey gösterilmez.</p>
                </div>
            </label>
        </div>
        
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5">
            <p class="text-xs text-amber-800 font-bold">⚠️ Not: Pasife alınan işletmeye ait tüm kullanıcılar (personeller dahil) giriş yapamayacaktır.</p>
        </div>
        
        <div class="flex gap-3">
            <button onclick="closeQrMenuStatusModal()" class="flex-1 px-4 py-2.5 bg-slate-200 rounded-xl font-bold hover:bg-slate-300 transition-all">
                İptal
            </button>
            <button id="confirmQrStatusBtn" onclick="confirmDeactivateWithQrStatus()" class="flex-1 px-4 py-2.5 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all">
                Pasife Al
            </button>
        </div>
    </div>

  </div>
</div>
<script>
// Delete Modal Functions
let deleteBusinessId = null;
let deleteBusinessName = null;

function openDeleteModal(customerId, companyName) {
    deleteBusinessId = customerId;
    deleteBusinessName = companyName;
    
    document.getElementById('deleteBusinessName').textContent = companyName;
    document.getElementById('confirmBusinessName').textContent = companyName;
    document.getElementById('deleteBusinessModal').classList.remove('hidden');
    document.getElementById('deleteConfirmInput').value = '';
    document.getElementById('confirmDeleteBtn').disabled = true;
    document.getElementById('confirmDeleteBtn').textContent = 'Sil';
}

function closeDeleteModal() {
    document.getElementById('deleteBusinessModal').classList.add('hidden');
    deleteBusinessId = null;
    deleteBusinessName = null;
}

// Real-time validation for delete confirmation input
document.addEventListener('DOMContentLoaded', function() {
    const deleteInput = document.getElementById('deleteConfirmInput');
    if (deleteInput) {
        deleteInput.addEventListener('input', function(e) {
            const input = e.target.value.trim();
            const matches = input === deleteBusinessName;
            document.getElementById('confirmDeleteBtn').disabled = !matches;
        });
    }
});

function confirmDelete() {
    if (!deleteBusinessId) return;
    
    // Disable button during request
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Siliniyor...';
    
    fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + deleteBusinessId + '/delete', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.NotificationManager.success('İşletme başarıyla silindi!');
            window.location.reload();
        } else {
            window.NotificationManager.error('Hata: ' + (data.message || 'İşletme silinemedi'));
            btn.disabled = false;
            btn.textContent = 'Sil';
        }
    })
    .catch(err => {
        window.NotificationManager.error('Bağlantı hatası: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Sil';
    });
}

async function toggleBusinessStatus(customerId, currentStatus, companyName) {
    if (currentStatus === 1) {
        openQrMenuStatusModal(customerId, companyName);
    } else {
        let confirmed = false;
        if (window.NotificationManager && window.NotificationManager.confirm) {
            confirmed = await window.NotificationManager.confirm(
                `"${companyName}" işletmesini aktife almak istediğinizden emin misiniz?\n\nQR menü de tekrar aktif olacaktır.`,
                'İşletme Durumu Değiştir'
            );
        } else {
            confirmed = confirm(`"${companyName}" işletmesini aktife almak istediğinizden emin misiniz?`);
        }
        if (!confirmed) return;
        
        try {
            const res = await fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + customerId + '/toggle-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qr_menu_status: 'active' })
            });
            const data = await res.json();
            if (data.success) {
                window.NotificationManager.success(data.message || 'İşletme durumu güncellendi!');
                window.location.reload();
            } else {
                window.NotificationManager.error('Hata: ' + (data.message || 'İşletme durumu değiştirilemedi'));
            }
        } catch (err) {
            window.NotificationManager.error('Bağlantı hatası: ' + err.message);
        }
    }
}

let qrModalBusinessId = null;
let qrModalBusinessName = null;

function openQrMenuStatusModal(customerId, companyName) {
    qrModalBusinessId = customerId;
    qrModalBusinessName = companyName;
    document.getElementById('qrModalBusinessTitle').textContent = companyName;
    document.getElementById('qrMenuStatusModal').classList.remove('hidden');
}

function closeQrMenuStatusModal() {
    document.getElementById('qrMenuStatusModal').classList.add('hidden');
    qrModalBusinessId = null;
    qrModalBusinessName = null;
}

async function confirmDeactivateWithQrStatus() {
    if (!qrModalBusinessId) return;
    
    const selected = document.querySelector('input[name="qr_menu_status"]:checked');
    if (!selected) {
        window.NotificationManager.error('Lütfen bir QR menü seçeneği seçin');
        return;
    }
    
    const btn = document.getElementById('confirmQrStatusBtn');
    btn.disabled = true;
    btn.textContent = 'İşleniyor...';
    
    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + qrModalBusinessId + '/toggle-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_menu_status: selected.value })
        });
        const data = await res.json();
        
        if (data.success) {
            window.NotificationManager.success(data.message || 'İşletme pasife alındı!');
            closeQrMenuStatusModal();
            window.location.reload();
        } else {
            window.NotificationManager.error('Hata: ' + (data.message || 'İşlem başarısız'));
            btn.disabled = false;
            btn.textContent = 'Pasife Al';
        }
    } catch (err) {
        window.NotificationManager.error('Bağlantı hatası: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Pasife Al';
    }
}

async function handleLoginAs(link) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu müşteri hesabına giriş yapmak istediğinizden emin misiniz?', 'Hesaba Giriş');
    } else {
        confirmed = confirm('Bu müşteri hesabına giriş yapmak istediğinizden emin misiniz?');
    }
    if (confirmed) {
        window.location.href = link.href;
    }
}

// Handle form submission with clean URLs
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('businesses-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(filterForm);
            const params = {};
            
            // Collect form values
            for (const [key, value] of formData.entries()) {
                if (value && value !== '') {
                    params[key] = value;
                }
            }
            
            // Build clean URL and navigate
            const cleanUrl = '<?php echo BASE_URL; ?>/qodmin/businesses' + 
                (Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '');
            
            window.location.href = cleanUrl;
        });
    }

    // Meta WhatsApp column — single-click aç/kapa for each business.
    // Uses the same endpoint as the business detail page.
    document.querySelectorAll('[data-meta-toggle]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const customerId = btn.dataset.customerId;
            const currentEnabled = btn.dataset.enabled === '1';
            const newVal = currentEnabled ? 0 : 1;
            const label = btn.querySelector('[data-meta-label]');
            const dot = btn.querySelector('span.w-2');
            btn.disabled = true;
            try {
                const res = await fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + encodeURIComponent(customerId) + '/meta-whatsapp-permission', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: !!newVal }),
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.message || 'İzin güncellenemedi');
                }
                btn.dataset.enabled = newVal ? '1' : '0';
                if (label) label.textContent = newVal ? 'Açık' : 'Kapalı';
                btn.classList.toggle('bg-emerald-100', !!newVal);
                btn.classList.toggle('text-emerald-700', !!newVal);
                btn.classList.toggle('hover:bg-emerald-200', !!newVal);
                btn.classList.toggle('bg-slate-100', !newVal);
                btn.classList.toggle('text-slate-500', !newVal);
                btn.classList.toggle('hover:bg-slate-200', !newVal);
                if (dot) {
                    dot.classList.toggle('bg-emerald-500', !!newVal);
                    dot.classList.toggle('bg-slate-400', !newVal);
                }
                if (window.NotificationManager) {
                    window.NotificationManager.success(data.message || 'Meta WhatsApp izni güncellendi');
                }
            } catch (e) {
                if (window.NotificationManager) {
                    window.NotificationManager.error(e.message || 'Hata');
                } else {
                    alert(e.message || 'Hata');
                }
            } finally {
                btn.disabled = false;
            }
        });
    });
});
</script>
