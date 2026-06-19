<?php
require_once __DIR__ . '/../../helpers/translations.php';

// Get packages from controller (fallback to empty array if not provided)
$packages = $packages ?? [];
?>

<div class="q-page animate-slide-up">
  <div class="q-container">
    <!-- Back Button -->
    <div class="mb-4 sm:mb-6">
        <a href="<?php echo BASE_URL; ?>/qodmin/businesses" class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 font-bold text-sm transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            İşletmelere Dön
        </a>
    </div>

    <!-- Header -->
    <header class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100 mb-4 sm:mb-6">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mb-2">Yeni İşletme Ekle</h1>
        <p class="text-slate-600 font-bold">Yeni bir işletme hesabı oluşturun ve subdomain atayın</p>
    </header>

    <!-- Form -->
    <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
        <form id="createBusinessForm" class="space-y-4 sm:space-y-6" enctype="multipart/form-data">
            <!-- Company Name -->
            <div>
                <label for="company_name" class="block text-slate-700 font-black text-sm mb-2">
                    İşletme Adı <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="company_name"
                       name="company_name"
                       required
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                       placeholder="Örn: Lezzet Restoran">
                <p class="mt-1 text-xs text-slate-500">İşletme adından otomatik subdomain oluşturulacaktır</p>
            </div>

            <!-- Subdomain Preview -->
            <div id="subdomainPreview" class="hidden">
                <label class="block text-slate-700 font-black text-sm mb-2">Subdomain Önizleme</label>
                <div class="px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 font-mono text-sm text-slate-700">
                    <span id="subdomainText" class="font-black"></span>.<span class="text-slate-500">qordy.com</span>
                </div>
            </div>

            <!-- Custom Subdomain (Optional) -->
            <div>
                <label for="subdomain" class="block text-slate-700 font-black text-sm mb-2">
                    Özel Subdomain (Opsiyonel)
                </label>
                <input type="text"
                       id="subdomain"
                       name="subdomain"
                       pattern="[a-z0-9]+"
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900 font-mono text-sm"
                       placeholder="Örn: caddecafe">
                <p class="mt-1 text-xs text-slate-500">Sadece küçük harf ve rakam kullanabilirsiniz (tire karakteri kullanılamaz)</p>
            </div>

            <!-- Business Logo -->
            <div>
                <label for="logo" class="block text-slate-700 font-black text-sm mb-2">
                    İşletme Logosu
                </label>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <input type="file"
                               id="logo"
                               name="logo"
                               accept="image/*"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                               placeholder="Logo seçin">
                    </div>
                    <div id="logoPreview" class="w-16 h-16 rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden hidden">
                        <img id="logoPreviewImg" src="" alt="Logo Preview" class="w-full h-full object-contain">
                    </div>
                </div>
                <p class="mt-1 text-xs text-slate-500">İşletme logosu (PNG, JPG, GIF, maksimum 2MB)</p>
            </div>

            <!-- Business Owner Section -->
            <div>
                <label for="owner_section" class="block text-slate-700 font-black text-sm mb-2">
                    İşletme Sahibi Bilgileri <span class="text-red-500">*</span>
                </label>

                <!-- Option to select existing user or create new -->
                <div class="mb-3">
                    <label class="flex items-center gap-2 text-slate-700 font-bold text-sm">
                        <input type="radio" name="owner_type" value="existing" checked class="owner-type-radio">
                        Sistemde kayıtlı kullanıcıyı işletme sahibi yap
                    </label>
                    <label class="flex items-center gap-2 text-slate-700 font-bold text-sm mt-2">
                        <input type="radio" name="owner_type" value="new" class="owner-type-radio">
                        Yeni kullanıcı oluştur (e-posta ile davet et)
                    </label>
                </div>

                <!-- Existing user selection -->
                <div id="existing-owner-section">
                    <select id="owner_user_id" name="owner_user_id"
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900">
                        <option value="">Bir kullanıcı seçin (işletme yöneticileri)</option>
                        <!-- Options will be populated via AJAX -->
                    </select>
                </div>

                <!-- New user creation form -->
                <div id="new-owner-section" class="hidden space-y-3">
                    <div>
                        <label for="new_owner_email" class="block text-slate-700 font-bold text-sm mb-1">
                            E-posta <span class="text-red-500">*</span>
                        </label>
                        <input type="email"
                               id="new_owner_email"
                               name="new_owner_email"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                               placeholder="ornek@email.com">
                    </div>
                    <div>
                        <label for="new_owner_first_name" class="block text-slate-700 font-bold text-sm mb-1">
                            Ad <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="new_owner_first_name"
                               name="new_owner_first_name"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                               placeholder="Ad">
                    </div>
                    <div>
                        <label for="new_owner_last_name" class="block text-slate-700 font-bold text-sm mb-1">
                            Soyad <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="new_owner_last_name"
                               name="new_owner_last_name"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                               placeholder="Soyad">
                    </div>
                    <div>
                        <label for="new_owner_phone" class="block text-slate-700 font-bold text-sm mb-1">
                            Telefon (Opsiyonel)
                        </label>
                        <input type="tel"
                               id="new_owner_phone"
                               name="new_owner_phone"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900"
                               placeholder="+90 555 123 45 67">
                    </div>
                </div>
            </div>

            <!-- Package Selection (after owner selection) -->
            <div>
                <label for="package_id" class="block text-slate-700 font-black text-sm mb-2">
                    Paket Seçimi (Opsiyonel)
                </label>
                <select id="package_id" name="package_id"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition-all font-bold text-slate-900">
                    <option value="">Bir paket seçin (opsiyonel)</option>
                    <?php if (!empty($packages) && is_array($packages)): ?>
                        <?php foreach ($packages as $package): ?>
                            <option value="<?php echo htmlspecialchars($package['package_id'] ?? ''); ?>">
                                <?php echo htmlspecialchars($package['name'] ?? 'Unnamed Package'); ?>
                                <?php if (!empty($package['price_monthly'])): ?>
                                    (<?php echo number_format($package['price_monthly'], 2); ?> TL/Ay)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (empty($packages) || !is_array($packages)): ?>
                    <p class="mt-2 text-xs text-slate-500 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <span class="font-bold text-yellow-700">⚠️ Bilgi:</span> Aktif paket bulunamadı. Paket eklemek için <a href="<?php echo BASE_URL; ?>/qodmin/packages" class="text-orange-600 hover:text-orange-700 underline font-bold">Paketler</a> sayfasına gidin.
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-xs text-slate-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <span class="font-bold text-blue-700">ℹ️ Önemli:</span> Seçilen paketin içeriğine göre işletme sahibine otomatik olarak erişim izinleri ve menü görünürlüğü atanacaktır. Paket içeriğinde tanımlı olan menülere ve özelliklere erişim sağlanır.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Active Status -->
            <div class="flex items-center gap-3">
                <input type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       checked
                       class="w-5 h-5 rounded border-slate-300 text-orange-500 focus:ring-orange-500">
                <label for="is_active" class="text-slate-700 font-bold text-sm">
                    İşletmeyi aktif olarak oluştur
                </label>
            </div>

            <!-- Submit Button -->
            <div class="flex gap-3 pt-4">
                <button type="submit"
                        class="px-6 py-3 bg-orange-500 text-white rounded-xl font-black hover:bg-orange-600 transition-colors">
                    İşletme Oluştur
                </button>
                <a href="<?php echo BASE_URL; ?>/qodmin/businesses"
                   class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-colors">
                    İptal
                </a>
            </div>
        </form>
    </div>

  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createBusinessForm');
    const companyNameInput = document.getElementById('company_name');
    const subdomainInput = document.getElementById('subdomain');
    const subdomainPreview = document.getElementById('subdomainPreview');
    const subdomainText = document.getElementById('subdomainText');
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    const logoPreviewImg = document.getElementById('logoPreviewImg');
    const ownerTypeRadios = document.querySelectorAll('.owner-type-radio');
    const existingOwnerSection = document.getElementById('existing-owner-section');
    const newOwnerSection = document.getElementById('new-owner-section');

    // Toggle between existing/new owner sections
    ownerTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'existing') {
                existingOwnerSection.classList.remove('hidden');
                newOwnerSection.classList.add('hidden');

                // Load business owners via AJAX
                loadBusinessOwners();
            } else {
                newOwnerSection.classList.remove('hidden');
                existingOwnerSection.classList.add('hidden');
            }
        });
    });

    // Load business owners via AJAX
    function loadBusinessOwners() {
        const select = document.getElementById('owner_user_id');
        select.disabled = true;
        select.innerHTML = '<option value="">Yükleniyor...</option>';
        
        fetch('<?php echo BASE_URL; ?>/api/qodmin/business-owners')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                select.innerHTML = '<option value="">Bir kullanıcı seçin (işletme yöneticileri)</option>';

                if (data.success && data.data && Array.isArray(data.data)) {
                    if (data.data.length === 0) {
                        select.innerHTML = '<option value="">Henüz işletme yöneticisi bulunmuyor</option>';
                    } else {
                        data.data.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.user_id;
                            const displayText = user.email ? `${user.name} (${user.email})` : user.name;
                            option.textContent = displayText;
                            select.appendChild(option);
                        });
                    }
                    select.disabled = false;
                } else {
                    console.error('Error loading business owners: Invalid response format', data);
                    select.innerHTML = '<option value="">Yükleme hatası</option>';
                    select.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error loading business owners:', error);
                select.innerHTML = '<option value="">Yükleme hatası - Lütfen sayfayı yenileyin</option>';
                select.disabled = false;
            });
    }

    // Generate subdomain from company name
    // Rules: Only lowercase letters and numbers, no hyphens
    // Turkish characters converted to English equivalents
    // Spaces and special characters removed
    function generateSubdomain(name) {
        if (!name) return '';

        let subdomain = name.toLowerCase()
            .replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i')
            .replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u')
            .replace(/[^a-z0-9]/g, '') // Remove all non-alphanumeric characters (including spaces and hyphens)
            .substring(0, 63);

        return subdomain || 'business' + Date.now();
    }

    // Update subdomain preview
    function updateSubdomainPreview() {
        const customSubdomain = subdomainInput.value.trim();
        const generatedSubdomain = generateSubdomain(companyNameInput.value);
        const finalSubdomain = customSubdomain || generatedSubdomain;

        if (finalSubdomain) {
            subdomainText.textContent = finalSubdomain;
            subdomainPreview.classList.remove('hidden');
        } else {
            subdomainPreview.classList.add('hidden');
        }
    }

    companyNameInput.addEventListener('input', updateSubdomainPreview);
    subdomainInput.addEventListener('input', updateSubdomainPreview);

    // Handle logo preview
    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreviewImg.src = e.target.result;
                logoPreview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        } else {
            logoPreview.classList.add('hidden');
        }
    });

    // Validation function
    function validateForm() {
        const errors = [];
        
        // Validate company name
        const companyName = companyNameInput.value.trim();
        if (!companyName) {
            errors.push('İşletme adı zorunludur');
            companyNameInput.classList.add('border-red-500');
        } else {
            companyNameInput.classList.remove('border-red-500');
        }
        
        // Validate owner type
        const ownerType = document.querySelector('input[name="owner_type"]:checked')?.value;
        if (!ownerType) {
            errors.push('İşletme sahibi türü seçilmelidir');
        }
        
        // Validate based on owner type
        if (ownerType === 'existing') {
            const ownerUserId = document.getElementById('owner_user_id').value;
            if (!ownerUserId) {
                errors.push('Bir işletme sahibi seçilmelidir');
                document.getElementById('owner_user_id').classList.add('border-red-500');
            } else {
                document.getElementById('owner_user_id').classList.remove('border-red-500');
            }
        } else if (ownerType === 'new') {
            const email = document.getElementById('new_owner_email').value.trim();
            const firstName = document.getElementById('new_owner_first_name').value.trim();
            const lastName = document.getElementById('new_owner_last_name').value.trim();
            
            if (!email) {
                errors.push('E-posta adresi zorunludur');
                document.getElementById('new_owner_email').classList.add('border-red-500');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Geçerli bir e-posta adresi girilmelidir');
                document.getElementById('new_owner_email').classList.add('border-red-500');
            } else {
                document.getElementById('new_owner_email').classList.remove('border-red-500');
            }
            
            if (!firstName) {
                errors.push('Ad zorunludur');
                document.getElementById('new_owner_first_name').classList.add('border-red-500');
            } else {
                document.getElementById('new_owner_first_name').classList.remove('border-red-500');
            }
            
            if (!lastName) {
                errors.push('Soyad zorunludur');
                document.getElementById('new_owner_last_name').classList.add('border-red-500');
            } else {
                document.getElementById('new_owner_last_name').classList.remove('border-red-500');
            }
        }
        
        // Validate subdomain if provided
        const subdomain = subdomainInput.value.trim();
        if (subdomain) {
            if (!/^[a-z0-9]+$/.test(subdomain)) {
                errors.push('Subdomain sadece küçük harf ve rakam içerebilir (tire karakteri kullanılamaz)');
                subdomainInput.classList.add('border-red-500');
            } else if (subdomain.length < 3) {
                errors.push('Subdomain en az 3 karakter olmalıdır');
                subdomainInput.classList.add('border-red-500');
            } else if (subdomain.length > 63) {
                errors.push('Subdomain en fazla 63 karakter olabilir');
                subdomainInput.classList.add('border-red-500');
            } else {
                subdomainInput.classList.remove('border-red-500');
            }
        }
        
        // Validate logo file if provided
        const logoFile = logoInput.files[0];
        if (logoFile) {
            const maxSize = 2 * 1024 * 1024; // 2MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!allowedTypes.includes(logoFile.type)) {
                errors.push('Logo dosyası sadece PNG, JPG veya GIF formatında olabilir');
            }
            
            if (logoFile.size > maxSize) {
                errors.push('Logo dosyası maksimum 2MB olabilir');
            }
        }
        
        return errors;
    }
    
    // Real-time validation
    companyNameInput.addEventListener('blur', function() {
        if (!this.value.trim()) {
            this.classList.add('border-red-500');
        } else {
            this.classList.remove('border-red-500');
        }
    });
    
    subdomainInput.addEventListener('blur', function() {
        const value = this.value.trim();
        if (value && !/^[a-z0-9]+$/.test(value)) {
            this.classList.add('border-red-500');
        } else {
            this.classList.remove('border-red-500');
        }
    });
    
    document.getElementById('new_owner_email')?.addEventListener('blur', function() {
        const value = this.value.trim();
        if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            this.classList.add('border-red-500');
        } else {
            this.classList.remove('border-red-500');
        }
    });

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear previous error styles
        form.querySelectorAll('.border-red-500').forEach(el => {
            el.classList.remove('border-red-500');
        });

        // Validate form
        const validationErrors = validateForm();
        if (validationErrors.length > 0) {
            window.NotificationManager.warning('Lütfen aşağıdaki hataları düzeltin:\n\n- ' + validationErrors.join('\n- '));
            return;
        }

        // Create FormData object to handle file upload
        const formData = new FormData(form);

        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Oluşturuluyor...';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/qodmin/businesses', {
                method: 'POST',
                body: formData
            });

            // Check if response is OK before parsing JSON
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Check content type before parsing JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, try to read response as text to see what we got
                const responseText = await response.text();

                // Log the unexpected response for debugging
                console.error('Unexpected response (not JSON):', responseText);

                // Try to parse as JSON in case it's valid JSON with wrong content-type
                try {
                    const result = JSON.parse(responseText);
                    if (result.success) {
                        let message = 'İşletme başarıyla oluşturuldu!';
                        if (result.warnings && result.warnings.length > 0) {
                            message += '\n\nUyarılar:\n- ' + result.warnings.join('\n- ');
                        }
                        window.NotificationManager.success(message);

                        if (result.customer_id) {
                            window.location.href = '<?php echo BASE_URL; ?>/qodmin/businesses/' + result.customer_id;
                        } else {
                            window.location.href = '<?php echo BASE_URL; ?>/qodmin/businesses';
                        }
                    } else {
                        window.NotificationManager.error('Hata: ' + (result.message || 'İşletme oluşturulamadı'));
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                } catch (jsonError) {
                    // If it's not valid JSON at all, show error
                    console.error('Response is not valid JSON:', jsonError);
                    window.NotificationManager.error('Sunucudan beklenmedik bir yanıt alındı. Lütfen daha sonra tekrar deneyin.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return; // Exit early since we handled the non-JSON response
            }

            // If we get here, response should be JSON
            const result = await response.json();

            if (result.success) {
                // Check for warnings
                let message = 'İşletme başarıyla oluşturuldu!';
                if (result.warnings && result.warnings.length > 0) {
                    message += '\n\nUyarılar:\n- ' + result.warnings.join('\n- ');
                }

                window.NotificationManager.success(message);

                if (result.customer_id) {
                    window.location.href = '<?php echo BASE_URL; ?>/qodmin/businesses/' + result.customer_id;
                } else {
                    window.location.href = '<?php echo BASE_URL; ?>/qodmin/businesses';
                }
            } else {
                // Show error message with details if available
                let errorMessage = result.message || 'İşletme oluşturulamadı';
                
                // If there are validation errors, show them
                if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                    errorMessage += '\n\nHatalar:\n- ' + result.errors.join('\n- ');
                }
                
                window.NotificationManager.error('Hata: ' + errorMessage);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (error) {
            console.error('Error:', error);

            // Check if it's a network error or JSON parsing error
            if (error instanceof SyntaxError) {
                window.NotificationManager.error('Sunucudan beklenmedik bir yanıt alındı. Lütfen daha sonra tekrar deneyin.');
            } else {
                window.NotificationManager.error('Bir ağ hatası oluştu. Lütfen tekrar deneyin.');
            }

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Load business owners initially
    loadBusinessOwners();
});
</script>
