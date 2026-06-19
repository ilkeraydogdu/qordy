<?php
/**
 * Company Settings Page for BUSINESS_MANAGER
 * Şirket/İşletme bilgileri yönetimi
 */

require_once __DIR__ . '/../../helpers/translations.php';

$customer = $customer ?? null;

$companyName = $customer['company_name'] ?? '';
$taxNumber = $customer['tax_number'] ?? '';
$address = $customer['address'] ?? '';
$city = $customer['city'] ?? '';
$country = $customer['country'] ?? 'Türkiye';
$postalCode = $customer['postal_code'] ?? '';
$website = $customer['website'] ?? '';
$businessHours = $customer['business_hours'] ?? '';
$logoUrl = $customer['logo_url'] ?? '';

// Generate CSRF token
require_once __DIR__ . '/../../core/Security/CSRFManager.php';
$csrfToken = \App\Core\Security\CSRFManager::generateToken();
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-4 sm:space-y-5 md:space-y-6 no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-800 mb-2">Şirket Bilgileri</h1>
        <p class="text-slate-600">İşletmenizin bilgilerini buradan yönetebilirsiniz.</p>
    </div>
    
    <!-- Company Form -->
    <form method="POST" action="<?php echo BASE_URL; ?>/business/company/update" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        
        <!-- Basic Company Information -->
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <h2 class="text-xl font-black text-slate-800 mb-6">Temel Bilgiler</h2>
            
            <div class="space-y-6">
                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-bold text-slate-700 mb-2">Şirket/İşletme Adı *</label>
                    <input type="text" 
                           id="company_name" 
                           name="company_name" 
                           value="<?php echo htmlspecialchars($companyName); ?>" 
                           required
                           placeholder="Örn: Lezzet Restaurant"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Tax Number -->
                <div>
                    <label for="tax_number" class="block text-sm font-bold text-slate-700 mb-2">Vergi No / TC Kimlik No</label>
                    <input type="text" 
                           id="tax_number" 
                           name="tax_number" 
                           value="<?php echo htmlspecialchars($taxNumber); ?>" 
                           placeholder="XXXXXXXXXX"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Logo Upload -->
                <div>
                    <label for="logo" class="block text-sm font-bold text-slate-700 mb-2">Logo</label>
                    <?php if (!empty($logoUrl)): ?>
                    <div class="mb-3">
                        <img src="<?php echo BASE_URL . htmlspecialchars($logoUrl); ?>" alt="Logo" class="h-20 w-auto rounded-lg border-2 border-slate-200">
                    </div>
                    <?php endif; ?>
                    <input type="file" 
                           id="logo" 
                           name="logo" 
                           accept="image/*"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                    <p class="text-xs text-slate-500 mt-2">PNG, JPG veya GIF formatında yükleyebilirsiniz (Maks. 2MB)</p>
                </div>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <h2 class="text-xl font-black text-slate-800 mb-6">Adres Bilgileri</h2>
            
            <div class="space-y-6">
                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-bold text-slate-700 mb-2">Adres *</label>
                    <textarea id="address" 
                              name="address" 
                              required
                              rows="3"
                              placeholder="Sokak, Mahalle, No"
                              class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium"><?php echo htmlspecialchars($address); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- City -->
                    <div>
                        <label for="city" class="block text-sm font-bold text-slate-700 mb-2">Şehir *</label>
                        <input type="text" 
                               id="city" 
                               name="city" 
                               value="<?php echo htmlspecialchars($city); ?>" 
                               required
                               placeholder="İstanbul"
                               class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                    </div>
                    
                    <!-- Postal Code -->
                    <div>
                        <label for="postal_code" class="block text-sm font-bold text-slate-700 mb-2">Posta Kodu</label>
                        <input type="text" 
                               id="postal_code" 
                               name="postal_code" 
                               value="<?php echo htmlspecialchars($postalCode); ?>" 
                               placeholder="34000"
                               class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                    </div>
                    
                    <!-- Country -->
                    <div>
                        <label for="country" class="block text-sm font-bold text-slate-700 mb-2">Ülke *</label>
                        <input type="text" 
                               id="country" 
                               name="country" 
                               value="<?php echo htmlspecialchars($country); ?>" 
                               required
                               class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact & Business Hours -->
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <h2 class="text-xl font-black text-slate-800 mb-6">İletişim ve Çalışma Saatleri</h2>
            
            <div class="space-y-6">
                <!-- Website -->
                <div>
                    <label for="website" class="block text-sm font-bold text-slate-700 mb-2">Website</label>
                    <input type="url" 
                           id="website" 
                           name="website" 
                           value="<?php echo htmlspecialchars($website); ?>" 
                           placeholder="https://www.ornekrestoran.com"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Business Hours -->
                <div>
                    <label for="business_hours" class="block text-sm font-bold text-slate-700 mb-2">Çalışma Saatleri</label>
                    <textarea id="business_hours" 
                              name="business_hours" 
                              rows="4"
                              placeholder="Pazartesi-Cuma: 09:00 - 22:00&#10;Cumartesi-Pazar: 10:00 - 23:00"
                              class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium"><?php echo htmlspecialchars($businessHours); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex gap-3 justify-end bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <a href="<?php echo BASE_URL; ?>/business/dashboard" 
               class="px-6 py-3 rounded-xl border-2 border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-all">
                İptal
            </a>
            <button type="submit" 
                    class="px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all">
                Kaydet
            </button>
        </div>
    </form>
    
</div>
