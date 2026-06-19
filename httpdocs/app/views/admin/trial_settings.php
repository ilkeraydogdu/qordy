<?php
require_once __DIR__ . '/../../helpers/translations.php';

if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$trialSettings = $trialSettings ?? [];
$stats = $stats ?? [];
$packages = $packages ?? [];

$features = $trialSettings['trial_features'] ?? [];
if (is_string($features)) $features = json_decode($features, true) ?: [];

$trialEnabled = (int)($trialSettings['trial_enabled'] ?? 1);
$trialDuration = intval($trialSettings['trial_duration_days'] ?? 14);
$trialPackageId = $trialSettings['trial_package_id'] ?? '';
$maxProducts = intval($trialSettings['trial_max_products'] ?? 10);
$maxTables = intval($trialSettings['trial_max_tables'] ?? 5);
$maxStaff = intval($trialSettings['trial_max_staff'] ?? 2);
$maxCategories = intval($trialSettings['trial_max_categories'] ?? 3);
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 sm:mb-6 lg:mb-8 gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">Trial Yönetimi</h1>
            <p class="text-slate-400 font-bold uppercase text-[8px] sm:text-[9px] lg:text-[10px] tracking-widest mt-1">Ücretsiz Deneme Süresi Ayarları</p>
        </div>
        <a href="<?php echo getAdminUrl('trial-users'); ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm transition-all shadow-lg hover:shadow-xl hover:bg-slate-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Trial Kullanıcıları
        </a>
    </header>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Toplam Trial</div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900"><?php echo $stats['total_trials'] ?? 0; ?></div>
        </div>
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Aktif</div>
            <div class="text-2xl sm:text-3xl font-black text-green-600"><?php echo $stats['active_trials'] ?? 0; ?></div>
        </div>
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Süresi Dolan</div>
            <div class="text-2xl sm:text-3xl font-black text-red-600"><?php echo $stats['expired_trials'] ?? 0; ?></div>
        </div>
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-slate-100">
            <div class="text-sm text-slate-500 font-bold mb-1">Satın Almış</div>
            <div class="text-2xl sm:text-3xl font-black text-indigo-600"><?php echo $stats['converted_trials'] ?? 0; ?></div>
        </div>
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-slate-100 col-span-2 sm:col-span-1">
            <div class="text-sm text-slate-500 font-bold mb-1">Dönüşüm</div>
            <div class="text-2xl sm:text-3xl font-black text-purple-600">%<?php echo $stats['conversion_rate'] ?? 0; ?></div>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="POST" action="<?php echo getAdminUrl('trial-settings'); ?>" class="space-y-4 sm:space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

        <!-- Genel Ayarlar -->
        <div class="bg-white rounded-xl sm:rounded-2xl p-5 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
            <h2 class="text-base sm:text-lg font-black text-slate-900 mb-5 sm:mb-6">Genel Ayarlar</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                <div class="space-y-5">
                    <!-- Trial Toggle -->
                    <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-xl">
                        <input type="checkbox" name="trial_enabled" id="trial-enabled" value="1"
                               class="w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                               <?php echo $trialEnabled ? 'checked' : ''; ?>>
                        <label for="trial-enabled" class="text-base font-bold text-slate-700">Trial Sistemi Aktif</label>
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Trial Süresi (Gün)</label>
                        <input type="number" name="trial_duration_days" min="1" max="90"
                               value="<?php echo $trialDuration; ?>"
                               class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>

                    <!-- Package -->
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Trial Paketi</label>
                        <select name="trial_package_id"
                                class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all">
                            <option value="">Otomatik (en düşük fiyatlı aktif paket)</option>
                            <?php foreach ($packages as $pkg): ?>
                            <option value="<?php echo htmlspecialchars($pkg['package_id'] ?? ''); ?>"
                                    <?php echo $trialPackageId === ($pkg['package_id'] ?? '') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pkg['name'] ?? 'Paket'); ?>
                                <?php if (!empty($pkg['price_monthly'])): ?> — <?php echo number_format($pkg['price_monthly'], 2, ',', '.'); ?> ₺/ay<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 font-bold mt-2">Seçili paketin tüm menü yetkileri ve izinleri trial kullanıcılara uygulanır.</p>
                    </div>
                </div>

                <!-- Resource Limits -->
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-4 tracking-widest">Kaynak Limitleri</label>
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2">Maks. Ürün</label>
                            <input type="number" name="trial_max_products" min="1" max="1000"
                                   value="<?php echo $maxProducts; ?>"
                                   class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2">Maks. Masa</label>
                            <input type="number" name="trial_max_tables" min="1" max="500"
                                   value="<?php echo $maxTables; ?>"
                                   class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2">Maks. Personel</label>
                            <input type="number" name="trial_max_staff" min="1" max="100"
                                   value="<?php echo $maxStaff; ?>"
                                   class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2">Maks. Kategori</label>
                            <input type="number" name="trial_max_categories" min="1" max="100"
                                   value="<?php echo $maxCategories; ?>"
                                   class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 font-bold mt-3">Trial kullanıcılarının oluşturabileceği kaynak sınırları.</p>
                </div>
            </div>
        </div>

        <?php
        $selectedPkg = null;
        if (!empty($trialPackageId)) {
            foreach ($packages as $pkg) {
                if (($pkg['package_id'] ?? '') === $trialPackageId) {
                    $selectedPkg = $pkg;
                    break;
                }
            }
        }
        ?>

        <?php if ($selectedPkg): ?>
        <!-- Seçili paket bilgisi -->
        <div class="bg-white rounded-xl sm:rounded-2xl p-5 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-black text-slate-900">Seçili Trial Paketi</h2>
                <a href="<?php echo getAdminUrl('packages/' . htmlspecialchars($selectedPkg['package_id']) . '/edit'); ?>"
                   class="text-xs font-bold text-indigo-600 hover:text-indigo-800 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 transition-colors">
                    Paketi Düzenle →
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3 bg-slate-50 rounded-xl">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Paket</div>
                    <div class="text-sm font-black text-slate-900 mt-1"><?php echo htmlspecialchars($selectedPkg['name']); ?></div>
                </div>
                <?php if (!empty($selectedPkg['price_yearly'])): ?>
                <?php
                    $yearlyPrice = (float)$selectedPkg['price_yearly'];
                    $monthlyEq   = $yearlyPrice > 0 ? ($yearlyPrice / 12) : 0;
                ?>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Yıllık</div>
                    <div class="text-sm font-black text-indigo-600 mt-1"><?php echo number_format($yearlyPrice, 2, ',', '.'); ?> ₺</div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Aylık Eşdeğer</div>
                    <div class="text-sm font-black text-indigo-600 mt-1"><?php echo number_format($monthlyEq, 2, ',', '.'); ?> ₺</div>
                </div>
                <?php endif; ?>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Durum</div>
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo !empty($selectedPkg['is_active']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo !empty($selectedPkg['is_active']) ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Submit -->
        <div class="flex gap-4">
            <button type="submit"
                    class="flex-1 sm:flex-none py-4 px-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-base shadow-lg hover:shadow-xl transition-all">
                Ayarları Kaydet
            </button>
        </div>
    </form>

  </div>
</div>
