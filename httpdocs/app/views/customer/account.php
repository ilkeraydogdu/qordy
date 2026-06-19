<?php
/**
 * Account Settings Page for BUSINESS_MANAGER
 * Hesap bilgileri yönetimi
 */

require_once __DIR__ . '/../../helpers/translations.php';

$user = $user ?? [];
$customer = $customer ?? null;

$firstName = $customer['first_name'] ?? '';
$lastName = $customer['last_name'] ?? '';
$email = $customer['email'] ?? ($user['name'] ?? '');
$phone = $customer['phone'] ?? '';

// Generate CSRF token
require_once __DIR__ . '/../../core/Security/CSRFManager.php';
$csrfToken = \App\Core\Security\CSRFManager::generateToken();
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-4 sm:space-y-5 md:space-y-6 no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-800 mb-2">Hesap Bilgilerim</h1>
        <p class="text-slate-600">Hesap bilgilerinizi buradan güncelleyebilirsiniz.</p>
    </div>
    
    <!-- Account Form -->
    <form method="POST" action="<?php echo BASE_URL; ?>/business/account/update" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        
        <!-- Personal Information Card -->
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <h2 class="text-xl font-black text-slate-800 mb-6">Kişisel Bilgiler</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-sm font-bold text-slate-700 mb-2">Ad *</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           value="<?php echo htmlspecialchars($firstName); ?>" 
                           required
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-sm font-bold text-slate-700 mb-2">Soyad *</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           value="<?php echo htmlspecialchars($lastName); ?>" 
                           required
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-bold text-slate-700 mb-2">Email *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           required
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-bold text-slate-700 mb-2">Telefon</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           value="<?php echo htmlspecialchars($phone); ?>" 
                           placeholder="05XX XXX XX XX"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
            </div>
        </div>
        
        <!-- Password Change Card -->
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <h2 class="text-xl font-black text-slate-800 mb-2">Şifre Değiştir</h2>
            <p class="text-sm text-slate-600 mb-6">Şifrenizi değiştirmek isterseniz aşağıdaki alanları doldurun.</p>
            
            <div class="space-y-4">
                <!-- Current Password -->
                <div>
                    <label for="current_password" class="block text-sm font-bold text-slate-700 mb-2">Mevcut Şifre</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
                
                <!-- New Password -->
                <div>
                    <label for="new_password" class="block text-sm font-bold text-slate-700 mb-2">Yeni Şifre</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           minlength="6"
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                    <p class="text-xs text-slate-500 mt-2">En az 6 karakter olmalıdır</p>
                </div>
                
                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-bold text-slate-700 mb-2">Yeni Şifre (Tekrar)</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all font-medium">
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white p-6 rounded-2xl shadow-soft border border-slate-100">
            <button type="button" 
                    onclick="handleDeleteAccount()"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl border-2 border-red-500 text-red-600 font-bold hover:bg-red-50 transition-all">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Hesabı Sil
            </button>
            
            <div class="flex gap-3 w-full sm:w-auto">
                <a href="<?php echo BASE_URL; ?>/business/dashboard" 
                   class="flex-1 sm:flex-none px-6 py-3 rounded-xl border-2 border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-all text-center">
                    İptal
                </a>
                <button type="submit" 
                        class="flex-1 sm:flex-none px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all">
                    Kaydet
                </button>
            </div>
        </div>
    </form>
    
</div>

<script>
// Password match validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    }
    
    newPassword.addEventListener('input', validatePasswordMatch);
    confirmPassword.addEventListener('input', validatePasswordMatch);
});

async function handleDeleteAccount() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Hesabınızı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!', 'Hesap Sil');
    } else {
        confirmed = confirm('Hesabınızı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');
    }
    if (confirmed) {
        if (window.NotificationManager) { window.NotificationManager.info('Hesap silme özelliği yakında eklenecektir.'); } else { alert('Hesap silme özelliği yakında eklenecektir.'); }
    }
}
</script>
