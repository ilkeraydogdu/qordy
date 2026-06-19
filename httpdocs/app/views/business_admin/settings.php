<?php
require_once __DIR__ . '/../../helpers/translations.php';

$serviceChargeRate = $service_charge_rate ?? '0';
$coverCharge = $cover_charge ?? '0';
$currency = $currency ?? 'TRY';
$orderIdPrefix = $order_id_prefix ?? 'cd';
$wifiName = $wifi_name ?? '';
$wifiPassword = $wifi_password ?? '';
$wifiShowToCustomer = $wifi_show_to_customer ?? false;
$workingHoursEnabled = $working_hours_enabled ?? false;
$workingHoursDays = $working_hours_days ?? [];
$orderEditRequiresApproval = isset($settings['order_edit_requires_approval']) && ($settings['order_edit_requires_approval'] === '1' || $settings['order_edit_requires_approval'] === 1 || $settings['order_edit_requires_approval'] === true);
$staffShowDeleteReduceButtons = !isset($settings['staff_show_delete_reduce_buttons']) || $settings['staff_show_delete_reduce_buttons'] === '' || $settings['staff_show_delete_reduce_buttons'] === '1' || $settings['staff_show_delete_reduce_buttons'] === 1 || $settings['staff_show_delete_reduce_buttons'] === true;
$managerShowDeleteReduceButtons = !isset($settings['manager_show_delete_reduce_buttons']) || $settings['manager_show_delete_reduce_buttons'] === '' || $settings['manager_show_delete_reduce_buttons'] === '1' || $settings['manager_show_delete_reduce_buttons'] === 1 || $settings['manager_show_delete_reduce_buttons'] === true;
$businessLatitude = $settings['business_latitude'] ?? '';
$businessLongitude = $settings['business_longitude'] ?? '';
$businessRadius = isset($settings['business_radius']) ? (int)$settings['business_radius'] : 500;
$businessAddress = $settings['business_address'] ?? '';

$dayLabels = [
    'mon' => 'Pazartesi',
    'tue' => 'Sali',
    'wed' => 'Carsamba',
    'thu' => 'Persembe',
    'fri' => 'Cuma',
    'sat' => 'Cumartesi',
    'sun' => 'Pazar',
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Ayarlar</p>
            <h1 class="q-page-header__title">İşletme Ayarları</h1>
            <p class="q-page-header__subtitle">Finansal ayarlar, WiFi bilgileri ve çalışma saatleri</p>
        </div>
    </header>

    <div class="q-tab-row q-tab-row--card" role="tablist">
        <button type="button" onclick="switchSettingsTab('financial')" id="stab-financial" role="tab" aria-selected="true" class="q-tab selected whitespace-nowrap flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Finansal
        </button>
        <button type="button" onclick="switchSettingsTab('wifi')" id="stab-wifi" role="tab" aria-selected="false" class="q-tab whitespace-nowrap flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"></path></svg>
            WiFi
        </button>
        <button type="button" onclick="switchSettingsTab('hours')" id="stab-hours" role="tab" aria-selected="false" class="q-tab whitespace-nowrap flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Çalışma Saatleri
        </button>
        <button type="button" onclick="switchSettingsTab('location')" id="stab-location" role="tab" aria-selected="false" class="q-tab whitespace-nowrap flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            İşletme Konumu
        </button>
        <button type="button" onclick="switchSettingsTab('order-approval')" id="stab-order-approval" role="tab" aria-selected="false" class="q-tab whitespace-nowrap flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Sipariş Onayı
        </button>
    </div>

    <form id="businessSettingsForm" method="POST">
        <!-- Finansal Ayarlar -->
        <div id="scontent-financial" class="settings-tab-content">
            <div class="q-card q-card--pad q-stack">
                <div class="q-toolbar">
                    <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h2 class="q-page-header__title" style="font-size:var(--font-size-xl);margin:0;">Finansal Ayarlar</h2>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Servis Ucreti Orani (%)</label>
                        <input type="number" name="service_charge_rate" value="<?php echo htmlspecialchars($serviceChargeRate); ?>" step="0.01" min="0" max="100"
                               class="w-full p-4 bg-slate-50 rounded-xl font-bold text-lg outline-none border-2 border-transparent focus:border-indigo-200 transition-all"/>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Kuver Ucreti (TL)</label>
                        <input type="number" name="cover_charge" value="<?php echo htmlspecialchars($coverCharge); ?>" step="0.01" min="0"
                               class="w-full p-4 bg-slate-50 rounded-xl font-bold text-lg outline-none border-2 border-transparent focus:border-indigo-200 transition-all"/>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Para Birimi</label>
                        <select name="currency" class="w-full p-4 bg-slate-50 rounded-xl font-bold text-lg outline-none border-2 border-transparent focus:border-indigo-200 transition-all appearance-none">
                            <option value="TRY" <?php echo $currency === 'TRY' ? 'selected' : ''; ?>>TRY - Turk Lirasi</option>
                            <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                            <option value="EUR" <?php echo $currency === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                        </select>
                    </div>
                </div>
                
                <div class="border-t border-slate-100 pt-6">
                    <h3 class="text-lg font-black tracking-tighter mb-4">Siparis ID Formati</h3>
                    <p class="q-hint">Ornek: <span class="font-mono bg-slate-100 px-2 py-1 rounded"><?php echo htmlspecialchars($orderIdPrefix); ?>1</span>, <span class="font-mono bg-slate-100 px-2 py-1 rounded"><?php echo htmlspecialchars($orderIdPrefix); ?>999</span>, <span class="font-mono bg-slate-100 px-2 py-1 rounded"><?php echo htmlspecialchars($orderIdPrefix); ?>1000</span></p>
                    <p class="q-hint">Siparis numaralari otomatik olarak artar. Uzunluk siniri yoktur (cd999'dan sonra cd1000, cd1001... seklinde devam eder).</p>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Siparis ID Oneki</label>
                        <input type="text" name="order_id_prefix" value="<?php echo htmlspecialchars($orderIdPrefix); ?>" maxlength="10"
                               class="w-full p-4 bg-slate-50 rounded-xl font-bold text-lg outline-none border-2 border-transparent focus:border-indigo-200 transition-all" placeholder="cd"/>
                    </div>
                </div>
            </div>
        </div>

        <!-- WiFi Ayarlar -->
        <div id="scontent-wifi" class="settings-tab-content hidden">
            <div class="q-card q-card--pad q-stack q-stack--md">
                <div class="q-toolbar">
                    <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"></path></svg>
                    </div>
                    <div>
                        <h2 class="q-card__title" style="margin:0;">WiFi Bilgileri</h2>
                        <p class="q-hint">Müşteri ekranında görünecek WiFi bilgileri</p>
                    </div>
                </div>
                
                <div class="q-stack q-stack--md">
                    <div class="q-field">
                        <label class="q-label" for="wifi_name">WiFi Ağ Adı (SSID)</label>
                        <input type="text" id="wifi_name" name="wifi_name" value="<?php echo htmlspecialchars($wifiName); ?>" class="q-input" placeholder="WiFi Ağ Adı"/>
                    </div>
                    <div class="q-field">
                        <label class="q-label" for="wifi-pw">WiFi Şifresi</label>
                        <div class="q-toolbar" style="gap:0;">
                            <input type="password" id="wifi-pw" name="wifi_password" value="<?php echo htmlspecialchars($wifiPassword); ?>" class="q-input" style="flex:1;" placeholder="WiFi Şifresi"/>
                            <button type="button" onclick="document.getElementById('wifi-pw').type = document.getElementById('wifi-pw').type === 'password' ? 'text' : 'password'" class="q-icon-btn" aria-label="Şifreyi göster">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                        <div>
                            <label class="q-label" style="margin:0;">Müşteriye Göster</label>
                            <p class="q-hint">Aktif olunca QR menüde WiFi butonu görünür</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4">
                            <input type="checkbox" name="wifi_show_to_customer" value="1" <?php echo $wifiShowToCustomer ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calisma Saatleri -->
        <div id="scontent-hours" class="settings-tab-content hidden">
            <div class="q-card q-card--pad q-stack q-stack--md">
                <div class="q-toolbar">
                    <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="q-card__title" style="margin:0;">Çalışma Saatleri</h2>
                        <p class="q-hint">Bu saatler dışında QR sipariş kapalı, ciro bu saatlere göre hesaplanır</p>
                    </div>
                </div>
                
                <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                    <div>
                        <label class="q-label" style="margin:0;">Calisma Saatlerini Aktif Et</label>
                        <p class="q-hint">Kapali oldugunda 7/24 siparis alinabilir</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" name="working_hours_enabled" value="1" <?php echo $workingHoursEnabled ? 'checked' : ''; ?> class="sr-only peer" id="wh-enabled">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                    </label>
                </div>
                
                <!-- Hafta Gunleri -->
                <div>
                    <div class="mb-4">
                        <h3 class="q-card__title" style="margin:0 0 var(--space-1);">Hafta Gunleri</h3>
                        <p class="q-hint">Her gunun kapanis saatinde otomatik Z raporu yazdirilir ve ciro sifirlanir</p>
                    </div>
                    <div class="q-stack q-stack--sm">
                        <?php foreach ($dayLabels as $dayKey => $dayName): 
                            $dayData = $workingHoursDays[$dayKey] ?? ['enabled' => true, 'start' => '09:00', 'end' => '02:00'];
                            $dayEnabled = $dayData['enabled'] ?? true;
                            $dayStart = $dayData['start'] ?? '09:00';
                            $dayEnd = $dayData['end'] ?? '02:00';
                        ?>
                        <div class="q-toolbar q-card q-card--pad day-row <?php echo !$dayEnabled ? 'opacity-60' : ''; ?>" style="gap:var(--space-3);background:var(--color-surface-muted);" id="day-row-<?php echo $dayKey; ?>">
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="day_enabled_<?php echo $dayKey; ?>" value="1" <?php echo $dayEnabled ? 'checked' : ''; ?> class="sr-only peer day-toggle" data-day="<?php echo $dayKey; ?>">
                                <div class="w-9 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                            </label>
                            <span class="font-black text-sm w-24 shrink-0" style="color:var(--color-text-primary);"><?php echo $dayName; ?></span>
                            <input type="time" name="day_start_<?php echo $dayKey; ?>" value="<?php echo htmlspecialchars($dayStart); ?>" class="q-input day-time" style="flex:1;min-width:0;" data-day="<?php echo $dayKey; ?>"/>
                            <span class="q-hint shrink-0">-</span>
                            <input type="time" name="day_end_<?php echo $dayKey; ?>" value="<?php echo htmlspecialchars($dayEnd); ?>" class="q-input day-time" style="flex:1;min-width:0;" data-day="<?php echo $dayKey; ?>"/>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <p class="text-sm text-amber-800 font-bold">Kapali gune tiklanan gun QR ile siparis verilemez. Tatil gunlerini kapali isaretleyebilirsiniz.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Isletme Konumu -->
        <div id="scontent-location" class="settings-tab-content hidden">
            <div class="q-card q-card--pad q-stack q-stack--md">
                <div class="q-toolbar">
                    <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="q-card__title" style="margin:0;">İşletme Konumu</h2>
                        <p class="q-hint">Müşterilerin sadece işletme yakınından erişebilmesi için konum belirleyin</p>
                    </div>
                </div>
                
                <div class="q-card q-card--pad" style="background:var(--color-amber-soft);border-color:var(--color-brand-accent);">
                    <p class="text-sm" style="color:var(--color-brand-accent-hover);">Konumu belirledikten sonra <strong>Özellik Yönetimi</strong> sayfasından <strong>Müşteri Konum Takibi</strong> özelliğini aktif edin. Aktif olduğunda, belirlenen yarıçap dışındaki kişiler menüye erişemez.</p>
                </div>
                
                <div class="q-field">
                    <label class="q-label" for="business_address">İşletme Adresi</label>
                    <input type="text" id="business_address" name="business_address" value="<?php echo htmlspecialchars($businessAddress); ?>" class="q-input" placeholder="Örn: Bağdat Caddesi No:123, Kadıköy/İstanbul"/>
                </div>

                <div class="q-grid q-grid--2">
                    <div class="q-field">
                        <label class="q-label" for="biz-latitude">Enlem (Latitude)</label>
                        <input type="text" name="business_latitude" id="biz-latitude" value="<?php echo htmlspecialchars($businessLatitude); ?>" class="q-input" placeholder="Örn: 41.0082"/>
                    </div>
                    <div class="q-field">
                        <label class="q-label" for="biz-longitude">Boylam (Longitude)</label>
                        <input type="text" name="business_longitude" id="biz-longitude" value="<?php echo htmlspecialchars($businessLongitude); ?>" class="q-input" placeholder="Örn: 28.9784"/>
                    </div>
                </div>
                
                <button type="button" onclick="detectBizLocation()" class="q-btn q-btn--primary q-btn--block">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Mevcut Konumumu Kullan
                </button>
                <p class="q-hint text-center">Tarayıcınızdan konum izni isteyerek otomatik doldurur</p>
                
                <div class="q-field">
                    <label class="q-label" for="biz-radius">Erişim Yarıçapı (metre)</label>
                    <input type="number" name="business_radius" id="biz-radius" value="<?php echo $businessRadius; ?>" min="50" max="5000" step="50" class="q-input"/>
                    <p class="q-hint">İşletmeden bu mesafe (metre) dışındaki müşteriler menüye erişemez. Önerilen: 100-500 metre.</p>
                    
                    <div class="q-toolbar" style="flex-wrap:wrap;margin-top:var(--space-2);">
                        <button type="button" onclick="document.getElementById('biz-radius').value=100;scheduleSave(false)" class="q-btn q-btn--ghost q-btn--sm">100m</button>
                        <button type="button" onclick="document.getElementById('biz-radius').value=200;scheduleSave(false)" class="q-btn q-btn--ghost q-btn--sm">200m</button>
                        <button type="button" onclick="document.getElementById('biz-radius').value=500;scheduleSave(false)" class="q-btn q-btn--ghost q-btn--sm">500m</button>
                        <button type="button" onclick="document.getElementById('biz-radius').value=1000;scheduleSave(false)" class="q-btn q-btn--ghost q-btn--sm">1km</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Siparis Silme/Azaltma Onayi -->
        <div id="scontent-order-approval" class="settings-tab-content hidden">
            <div class="q-card q-card--pad q-stack q-stack--md">
                <div class="q-toolbar">
                    <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="q-card__title" style="margin:0;">Siparis Silme/Azaltma Onayi</h2>
                        <p class="q-hint">Garson ve kasiyer silme/azaltma islemleri onay kuyruguna gider</p>
                    </div>
                </div>
                <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                    <div>
                        <label class="q-label" style="margin:0;">Siparis Silme ve Azaltma Onayi Gerekli</label>
                        <p class="q-hint">Aktif: POS ve garson ekraninda silme/azaltma butonlari gorunur; personel islemleri onay kuyruguna gider. Pasif: Bu butonlar tum ekranlarda (yonetici ve personel) gizlenir.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" name="order_edit_requires_approval" value="1" <?php echo $orderEditRequiresApproval ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                    </label>
                </div>
                <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                    <div>
                        <label class="q-label" style="margin:0;">Garson ve Kasiyerde Silme/Azaltma Butonlari</label>
                        <p class="q-hint">Aktif olunca garson ve kasiyer POS ve garson ekraninda silme/azaltma butonlarini gorur. Pasifte sadece isletme yoneticisi gorur.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" name="staff_show_delete_reduce_buttons" value="1" <?php echo $staffShowDeleteReduceButtons ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                    </label>
                </div>
                <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                    <div>
                        <label class="q-label" style="margin:0;">İşletme Yöneticisinde Silme/Azaltma Butonları</label>
                        <p class="q-hint">Aktif olunca işletme yöneticisi POS ve garson ekranında silme/azaltma butonlarını görür. Pasifte sadece garson/kasiyer (yukarıdaki ayar açıksa) görür.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" name="manager_show_delete_reduce_buttons" value="1" <?php echo $managerShowDeleteReduceButtons ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Otomatik kaydetme - Kaydet butonu yok -->
        <div id="saveStatus" class="flex justify-end mt-6 items-center gap-2 min-h-[44px]">
            <span id="saveStatusText" class="q-hint"></span>
        </div>
    </form>
  </div>
</div>

<script>
function switchSettingsTab(tab) {
    document.querySelectorAll('.settings-tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('[id^="stab-"]').forEach(btn => {
        btn.classList.remove('selected');
        btn.setAttribute('aria-selected', 'false');
    });
    document.getElementById('scontent-' + tab)?.classList.remove('hidden');
    const tabBtn = document.getElementById('stab-' + tab);
    if (tabBtn) {
        tabBtn.classList.add('selected');
        tabBtn.setAttribute('aria-selected', 'true');
    }
    window.location.hash = tab;
}

// Day toggle opacity
document.querySelectorAll('.day-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const row = document.getElementById('day-row-' + this.dataset.day);
        if (row) row.classList.toggle('opacity-60', !this.checked);
    });
});

// Load tab from hash
const hash = window.location.hash.replace('#', '');
if (hash && ['financial', 'wifi', 'hours', 'location', 'order-approval'].includes(hash)) {
    switchSettingsTab(hash);
}

// Detect current location for business
function detectBizLocation() {
    if (!navigator.geolocation) {
        window.NotificationManager.warning('Tarayiciniz konum ozelligini desteklemiyor.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('biz-latitude').value = pos.coords.latitude.toFixed(8);
            document.getElementById('biz-longitude').value = pos.coords.longitude.toFixed(8);
            scheduleSave(false);
            if (window.NotificationManager) {
                window.NotificationManager.success('Konum basariyla algilandi!');
            }
        },
        function(err) {
            window.NotificationManager.error('Konum alinamadi: ' + (err.message || 'Bilinmeyen hata'));
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

// --- Otomatik kaydetme ---
let saveTimeout = null;
let isSaving = false;

function collectFormData() {
    const form = document.getElementById('businessSettingsForm');
    const formData = new FormData(form);
    const data = {};
    formData.forEach((val, key) => data[key] = val);
    
    if (!formData.has('wifi_show_to_customer')) data['wifi_show_to_customer'] = '0';
    if (!formData.has('working_hours_enabled')) data['working_hours_enabled'] = '0';
    if (!formData.has('order_edit_requires_approval')) data['order_edit_requires_approval'] = '0';
    if (!formData.has('staff_show_delete_reduce_buttons')) data['staff_show_delete_reduce_buttons'] = '0';
    if (!formData.has('manager_show_delete_reduce_buttons')) data['manager_show_delete_reduce_buttons'] = '0';
    
    delete data['working_hours_start'];
    delete data['working_hours_end'];
    
    const days = ['mon','tue','wed','thu','fri','sat','sun'];
    const daysData = {};
    days.forEach(d => {
        const startInput = document.querySelector('input[name="day_start_' + d + '"]');
        const endInput = document.querySelector('input[name="day_end_' + d + '"]');
        const enabledInput = document.querySelector('input[name="day_enabled_' + d + '"]');
        
        daysData[d] = {
            enabled: enabledInput ? enabledInput.checked : formData.has('day_enabled_' + d),
            start: startInput ? startInput.value : (formData.get('day_start_' + d) || '09:00'),
            end: endInput ? endInput.value : (formData.get('day_end_' + d) || '02:00')
        };
        delete data['day_enabled_' + d];
        delete data['day_start_' + d];
        delete data['day_end_' + d];
    });
    data['working_hours_days'] = JSON.stringify(daysData);
    delete data['order_number_length'];
    
    return data;
}

function saveSettings() {
    if (isSaving) return;
    isSaving = true;
    
    const statusEl = document.getElementById('saveStatusText');
    if (statusEl) statusEl.textContent = 'Kaydediliyor...';
    
    const data = collectFormData();
    const baseUrl = (typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '') || '';
    const apiUrl = baseUrl + '/api/business/settings/update';
    const csrfToken = (typeof window.CSRF_TOKEN !== 'undefined' ? window.CSRF_TOKEN : '') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    
    fetch(apiUrl, { method: 'POST', headers: headers, body: JSON.stringify(data) })
    .then(async r => {
        const text = await r.text();
        let result;
        try { result = JSON.parse(text); } catch (e) { return { success: false, message: 'Sunucu gecersiz yanit verdi', debug: text ? text.substring(0, 200) : '' }; }
        if (!r.ok) return Object.assign(result || {}, { success: false, message: (result && result.message) || result.error || 'HTTP ' + r.status });
        return result;
    })
    .then(result => {
        if (result && result.success) {
            if (statusEl) {
                statusEl.textContent = 'Kaydedildi';
                statusEl.className = 'text-sm text-emerald-600 font-bold';
                setTimeout(() => { statusEl.textContent = ''; statusEl.className = 'text-sm text-slate-400'; }, 2000);
            }
        } else {
            window.NotificationManager.error('Hata: ' + (result?.message || result?.error || 'Bilinmeyen hata') + (result?.debug ? ' (' + result.debug + ')' : ''));
            if (statusEl) statusEl.textContent = '';
        }
    })
    .catch(err => {
        window.NotificationManager.error('Baglanti hatasi: ' + (err.message || 'Yanit gecersiz'));
        if (statusEl) statusEl.textContent = '';
    })
    .finally(() => { isSaving = false; });
}

function scheduleSave(immediate) {
    if (saveTimeout) clearTimeout(saveTimeout);
    if (immediate) {
        saveSettings();
    } else {
        saveTimeout = setTimeout(saveSettings, 400);
    }
}

// Calisma saatleri: aninda kaydet (checkbox ve time degisince)
document.getElementById('wh-enabled')?.addEventListener('change', () => scheduleSave(true));
document.querySelectorAll('.day-toggle').forEach(el => el.addEventListener('change', () => scheduleSave(true)));
document.querySelectorAll('.day-time').forEach(el => el.addEventListener('change', () => scheduleSave(true)));

// WiFi: checkbox degisince aninda
document.querySelector('input[name="wifi_show_to_customer"]')?.addEventListener('change', () => scheduleSave(true));
// Siparis Silme/Azaltma Onayi: checkbox degisince aninda
document.querySelector('input[name="order_edit_requires_approval"]')?.addEventListener('change', () => scheduleSave(true));
document.querySelector('input[name="staff_show_delete_reduce_buttons"]')?.addEventListener('change', () => scheduleSave(true));
document.querySelector('input[name="manager_show_delete_reduce_buttons"]')?.addEventListener('change', () => scheduleSave(true));

// Finansal, WiFi ve konum text/select: blur veya change sonrasi kisa gecikmeyle
['service_charge_rate','cover_charge','currency','order_id_prefix','wifi_name','wifi_password','business_address','business_latitude','business_longitude','business_radius'].forEach(name => {
    const el = document.querySelector('input[name="' + name + '"], select[name="' + name + '"]');
    if (el) {
        el.addEventListener('change', () => scheduleSave(false));
        el.addEventListener('blur', () => scheduleSave(false));
    }
});

// Form submit engelle (Enter ile sayfa yenilenmesin)
document.getElementById('businessSettingsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    scheduleSave(true);
});
</script>
