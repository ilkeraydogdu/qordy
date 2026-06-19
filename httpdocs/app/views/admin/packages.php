<?php
require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/ui.php';

$packages = $packages ?? [];
$baseUrl = BASE_URL;
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';

// İstatistikler
$totalPackages = count($packages);
$activePackages = count(array_filter($packages, function($pkg) {
    return !empty($pkg['is_active']);
}));
$inactivePackages = $totalPackages - $activePackages;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tighter flex items-center gap-3">
            📦 Paket Yönetimi
        </h1>
        <a href="<?php echo getAdminUrl('packages/create'); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl transition-all shadow-lg hover:shadow-xl">
            + Yeni Paket
        </a>
    </div>
    
    <!-- İstatistik Kartları -->
    <?php if ($totalPackages > 0): ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Toplam Paket</div>
            <div class="text-3xl font-black text-slate-900"><?php echo $totalPackages; ?></div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Aktif</div>
            <div class="text-3xl font-black text-green-600"><?php echo $activePackages; ?></div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Pasif</div>
            <div class="text-3xl font-black text-red-600"><?php echo $inactivePackages; ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Paket Kartları -->
    <?php if (empty($packages)): ?>
    <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-slate-100">
        <div class="text-6xl mb-4">📦</div>
        <p class="text-slate-500 text-lg mb-6">Henüz paket bulunmamaktadır.</p>
        <a href="<?php echo getAdminUrl('packages/create'); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl transition-colors shadow-lg inline-block">
            İlk Paketi Oluştur
        </a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <?php foreach ($packages as $pkg): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-lg transition-all">
            <!-- Kart Header -->
            <div class="p-5 border-b border-slate-100">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-lg font-black text-slate-900 flex-1"><?php echo htmlspecialchars($pkg['name']); ?></h3>
                    <button onclick="togglePackageStatus('<?php echo htmlspecialchars($pkg['package_id']); ?>', <?php echo $pkg['is_active'] ? 'true' : 'false'; ?>)" 
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold transition-colors <?php echo $pkg['is_active'] ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'; ?>">
                        <?php echo $pkg['is_active'] ? 'Aktif' : 'Pasif'; ?>
                    </button>
                </div>
                <?php if (!empty($pkg['description'])): ?>
                <p class="text-sm text-slate-600 line-clamp-2"><?php echo htmlspecialchars(mb_substr($pkg['description'], 0, 100, 'UTF-8')); ?><?php echo mb_strlen($pkg['description'], 'UTF-8') > 100 ? '...' : ''; ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Fiyatlar -->
            <div class="p-5 space-y-2">
                <?php if (!empty($pkg['price_one_time']) && $pkg['price_one_time'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-500">Tek Seferlik</span>
                    <span class="text-base font-black text-indigo-600"><?php echo number_format($pkg['price_one_time'], 2, ',', '.'); ?> ₺</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pkg['price_monthly']) && $pkg['price_monthly'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-500">Aylık</span>
                    <span class="text-base font-black text-indigo-600"><?php echo number_format($pkg['price_monthly'], 2, ',', '.'); ?> ₺</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pkg['price_yearly']) && $pkg['price_yearly'] > 0): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-500">Yıllık</span>
                    <span class="text-base font-black text-indigo-600"><?php echo number_format($pkg['price_yearly'], 2, ',', '.'); ?> ₺</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pkg['discount_percentage']) && $pkg['discount_percentage'] > 0): ?>
                <div class="pt-2 mt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">İndirim</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                            %<?php echo number_format($pkg['discount_percentage'], 0); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- İşlemler -->
            <div class="p-5 border-t border-slate-100 flex gap-2">
                <a href="<?php echo getAdminUrl('packages/' . htmlspecialchars($pkg['package_id']) . '/edit'); ?>" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition-colors text-sm text-center">
                    Düzenle
                </a>
                <button onclick="applyDiscount('<?php echo htmlspecialchars($pkg['package_id']); ?>')" 
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors text-sm font-bold" 
                        title="İndirim Uygula">
                    🎁
                </button>
                <button onclick="deletePackage('<?php echo htmlspecialchars($pkg['package_id']); ?>', '<?php echo htmlspecialchars($pkg['name']); ?>')" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-bold">
                    Sil
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.hidden {
    display: none !important;
}

#discount-modal.flex {
    display: flex !important;
}
</style>

<!-- Discount Modal -->
<div id="discount-modal" class="hidden fixed inset-0 z-[201] items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeDiscountModal()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black tracking-tighter">İndirim Uygula</h2>
            <button onclick="closeDiscountModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="discount-form" class="space-y-5">
            <input type="hidden" id="discount-package-id">
            
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">İndirim Yüzdesi (%)</label>
                <input type="number" id="discount-percentage" step="0.01" min="0" max="100" required
                       class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-base shadow-2xl hover:scale-105 active:scale-95 transition-all">
                    Uygula
                </button>
                <button type="button" onclick="closeDiscountModal()" class="px-8 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-black text-base transition-colors">
                    İptal
                </button>
            </div>
        </form>
    </div>

  </div>
</div>
<script>

async function togglePackageStatus(packageId, currentStatus) {
    const msg = currentStatus ? 'Paketi pasif yapmak istediğinize emin misiniz?' : 'Paketi aktif yapmak istediğinize emin misiniz?';
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm(msg, 'Onay');
    } else {
        confirmed = confirm(msg);
    }
    if (!confirmed) return;
    
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    fetch('<?php echo $baseUrl; ?>' + adminPrefix + '/packages/' + packageId + '/toggle-active', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            window.NotificationManager.error(data.message || 'Bir hata oluştu');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Bir hata oluştu');
    });
}

function applyDiscount(packageId) {
    const modal = document.getElementById('discount-modal');
    if (!modal) return;
    document.getElementById('discount-package-id').value = packageId;
    document.getElementById('discount-percentage').value = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeDiscountModal() {
    const modal = document.getElementById('discount-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.getElementById('discount-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const packageId = document.getElementById('discount-package-id').value;
    const percentage = document.getElementById('discount-percentage').value;
    
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    fetch('<?php echo $baseUrl; ?>' + adminPrefix + '/packages/' + packageId + '/apply-discount', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        },
        body: JSON.stringify({
            discount_percentage: percentage
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDiscountModal();
            location.reload();
        } else {
            window.NotificationManager.error(data.message || 'Bir hata oluştu');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Bir hata oluştu');
    });
});

async function deletePackage(packageId, packageName) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('"' + packageName + '" paketini silmek istediğinize emin misiniz? Bu işlem geri alınamaz.', 'Paket Silme');
    } else {
        confirmed = confirm('"' + packageName + '" paketini silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');
    }
    if (!confirmed) return;
    
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    fetch('<?php echo $baseUrl; ?>' + adminPrefix + '/packages/' + packageId + '/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Paket başarıyla silindi');
            }
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            window.NotificationManager.error(data.message || 'Paket silinirken bir hata oluştu');
        }
    })
    .catch(error => {
        console.error('Error deleting package:', error);
        window.NotificationManager.error('Paket silinirken bir hata oluştu');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const discountModal = document.getElementById('discount-modal');
    
    if (discountModal) {
        discountModal.classList.add('hidden');
        discountModal.classList.remove('flex');
        discountModal.setAttribute('aria-hidden', 'true');
    }
});
</script>
