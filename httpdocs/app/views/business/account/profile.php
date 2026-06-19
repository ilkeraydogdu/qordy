<?php
$customer = $customer ?? [];
$firstName = $customer['first_name'] ?? '';
$lastName = $customer['last_name'] ?? '';
$email = $customer['email'] ?? '';
$phone = $customer['phone'] ?? '';
$companyName = $customer['company_name'] ?? '';
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="p-6 h-full overflow-y-auto bg-[#f4f5fa]">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-black text-slate-900">Profil Bilgilerim</h1>
            <p class="text-slate-600 mt-2">İşletme ve kişisel bilgilerinizi görüntüleyin</p>
        </div>

        <!-- Profile Card -->
        <div class="bg-white rounded-2xl shadow-soft p-6 border border-slate-100">
            <!-- Company Info -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    İşletme Bilgileri
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600">İşletme Adı</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo htmlspecialchars($companyName ?: 'Belirtilmemiş'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div>
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Kişisel Bilgiler
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600">Ad Soyad</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName) ?: 'Belirtilmemiş'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600">E-posta</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo htmlspecialchars($email ?: 'Belirtilmemiş'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600">Telefon</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo htmlspecialchars($phone ?: 'Belirtilmemiş'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
