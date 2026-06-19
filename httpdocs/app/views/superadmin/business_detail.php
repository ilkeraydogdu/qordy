<?php
require_once __DIR__ . '/../../helpers/translations.php';

$customer = $customer ?? null;
$subscription = $subscription ?? null;
$subdomain_url = $subdomain_url ?? null;
$total_orders = $total_orders ?? 0;
$total_revenue = $total_revenue ?? 0;
$daily_revenue = $daily_revenue ?? 0;
$hourly_sales_total = $hourly_sales_total ?? 0;
$trial_data = $trial_data ?? ['is_trial' => false];
$owner_user = $owner_user ?? null;
$all_roles = $all_roles ?? [];
$occupied_tables_count = $occupied_tables_count ?? 0;
$total_tables_count = $total_tables_count ?? 0;
$active_orders_count = $active_orders_count ?? 0;

$staff_count = $staff_count ?? 0;
$staff_list = $staff_list ?? [];
$table_count = $table_count ?? 0;
$occupied_tables = $occupied_tables ?? 0;
$active_tables = $active_tables ?? 0;
$menu_item_count = $menu_item_count ?? 0;
$category_count = $category_count ?? 0;
$revenue_today = $revenue_today ?? 0;
$orders_today = $orders_today ?? 0;
$revenue_month = $revenue_month ?? 0;
$orders_month = $orders_month ?? 0;
$recent_orders = $recent_orders ?? [];

if (!$customer) {
    header('Location: ' . BASE_URL . '/qodmin/businesses');
    exit;
}

$companyName = htmlspecialchars($customer['company_name'] ?? 'İşletme');
$initials = strtoupper(mb_substr($customer['company_name'] ?? '?', 0, 2));
$isActive = !empty($customer['is_active']);
$tableOccupancy = $table_count > 0 ? round(($active_tables / $table_count) * 100) : 0;
$logoPath = trim($customer['logo_path'] ?? '');
$logoUrl = $logoPath ? (str_starts_with($logoPath, 'http') ? $logoPath : (rtrim(BASE_URL, '/') . (str_starts_with($logoPath, '/') ? $logoPath : '/' . ltrim($logoPath, '/')))) : '';
?>

<div class="q-page animate-slide-up">
  <div class="q-container">
    
    <!-- Back + Breadcrumb -->
    <div class="flex items-center gap-2 text-sm">
        <a href="<?php echo BASE_URL; ?>/qodmin/businesses" class="text-slate-400 hover:text-slate-600 transition-colors font-bold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <a href="<?php echo BASE_URL; ?>/qodmin/businesses" class="text-slate-400 hover:text-slate-600 transition-colors font-bold">İşletmeler</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 font-bold"><?php echo $companyName; ?></span>
    </div>

    <!-- Header Card -->
    <div class="bg-white rounded-xl p-5 sm:p-6 shadow-soft border border-slate-100">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center flex-shrink-0 overflow-hidden bg-slate-100">
                <img id="header-logo" src="<?php echo $logoUrl ? htmlspecialchars($logoUrl) : ''; ?>" alt="<?php echo $companyName; ?>" class="w-full h-full object-contain p-1" style="<?php echo $logoUrl ? '' : 'display:none;'; ?>" onerror="this.style.display='none'; document.getElementById('header-logo-fallback').style.display='flex';">
                <div id="header-logo-fallback" class="w-full h-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-black text-xl" style="<?php echo $logoUrl ? 'display:none;' : ''; ?>"><?php echo $initials; ?></div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900"><?php echo $companyName; ?></h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black <?php echo $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>">
                        <?php echo $isActive ? 'Aktif' : 'Pasif'; ?>
                    </span>
                    <?php if ($subscription): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 text-blue-700">
                            <?php echo htmlspecialchars($subscription['package_name'] ?? 'Abonelik'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4 mt-1 text-sm text-slate-500">
                    <span><?php echo htmlspecialchars($customer['email'] ?? ''); ?></span>
                    <?php if (!empty($customer['phone'])): ?>
                        <span><?php echo htmlspecialchars($customer['phone']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer['subdomain'])): ?>
                        <span class="text-orange-600 font-bold"><?php echo htmlspecialchars($customer['subdomain']); ?>.qordy.com</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($subdomain_url): ?>
                    <a href="<?php echo htmlspecialchars($subdomain_url); ?>" target="_blank" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-bold text-sm hover:bg-orange-600 transition-colors">
                        Subdomain
                    </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo htmlspecialchars($customer['customer_id']); ?>/login-as" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm hover:bg-slate-800 transition-colors">
                    Giriş Yap
                </a>
                <button onclick="toggleBusinessStatusDetail('<?php echo htmlspecialchars($customer['customer_id']); ?>', <?php echo $isActive ? 1 : 0; ?>, '<?php echo addslashes($customer['company_name'] ?? ''); ?>')"
                        class="px-4 py-2 <?php echo $isActive ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-500 hover:bg-emerald-600'; ?> text-white rounded-lg font-bold text-sm transition-colors">
                    <?php echo $isActive ? 'Pasife Al' : 'Aktife Al'; ?>
                </button>
                <?php if ($isActive): ?>
                <button onclick="openQrMenuStatusModalDetail()" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg font-bold text-sm transition-colors">
                    QR Menü Ayarı
                </button>
                <?php endif; ?>
                <?php 
                $qrMenuStatus = $customer['qr_menu_status'] ?? 'active';
                if ($qrMenuStatus !== 'active'): ?>
                <span class="px-3 py-2 rounded-lg text-xs font-black <?php echo $qrMenuStatus === 'menu_only' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'; ?>">
                    QR: <?php echo $qrMenuStatus === 'menu_only' ? 'Sadece Menü' : 'Kapalı'; ?>
                </span>
                <?php endif; ?>
                <?php $metaOn = (int)($customer['meta_whatsapp_enabled'] ?? 0) === 1; ?>
                <button type="button" id="metaWhatsAppToggleBtn"
                        onclick="toggleMetaWhatsApp('<?php echo htmlspecialchars($customer['customer_id']); ?>', <?php echo $metaOn ? 1 : 0; ?>)"
                        class="px-4 py-2 <?php echo $metaOn ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-slate-400 hover:bg-slate-500'; ?> text-white rounded-lg font-bold text-sm transition-colors inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 2.1.55 4.15 1.6 5.96L2 22l4.24-1.11a9.9 9.9 0 004.77 1.21c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01C17.18 3.03 14.69 2 12.04 2zm5.83 14.19c-.25.7-1.46 1.34-2.01 1.42-.52.08-1.17.11-1.89-.12-.43-.14-1-.32-1.72-.64-3.02-1.31-4.99-4.36-5.14-4.56-.15-.2-1.22-1.62-1.22-3.09s.77-2.19 1.04-2.49c.27-.3.59-.37.79-.37.2 0 .4 0 .57.01.18 0 .43-.07.67.51.25.6.85 2.07.92 2.22.08.15.13.33.03.53-.1.2-.15.32-.3.5-.15.17-.32.39-.45.52-.15.15-.31.31-.13.61.17.3.77 1.27 1.66 2.06 1.14 1.01 2.1 1.32 2.4 1.47.3.15.47.13.65-.08.17-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.74.82 2.04.97.3.15.5.22.57.34.08.12.08.72-.17 1.42z"/></svg>
                    <span id="metaWhatsAppToggleLabel">Meta WhatsApp: <?php echo $metaOn ? 'AÇIK' : 'KAPALI'; ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-emerald-600" id="revenueToday"><?php echo number_format($revenue_today, 2); ?> ₺</div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Bugünkü Ciro</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-blue-600"><?php echo number_format($revenue_month, 2); ?> ₺</div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Aylık Ciro</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-slate-800"><?php echo number_format($total_revenue, 2); ?> ₺</div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Toplam Gelir</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-purple-600"><?php echo number_format($total_orders); ?></div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Toplam Sipariş</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-orange-600"><?php echo $active_tables; ?> / <?php echo $table_count; ?></div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Aktif Masa</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-soft border border-slate-100 text-center">
            <div class="text-xl font-black text-indigo-600"><?php echo $staff_count; ?></div>
            <div class="text-xs text-slate-500 font-bold mt-0.5">Personel</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-soft border border-slate-100 overflow-hidden">
        <div class="border-b border-slate-100 flex overflow-x-auto no-scrollbar">
            <button class="tab-btn px-5 py-3.5 text-sm font-bold text-orange-600 border-b-2 border-orange-500 whitespace-nowrap" data-tab="overview">Genel Bakış</button>
            <button class="tab-btn px-5 py-3.5 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-slate-600 whitespace-nowrap" data-tab="orders">Siparişler</button>
            <button class="tab-btn px-5 py-3.5 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-slate-600 whitespace-nowrap" data-tab="staff">Personel</button>
            <button class="tab-btn px-5 py-3.5 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-slate-600 whitespace-nowrap" data-tab="info">İşletme Bilgileri</button>
        </div>

        <!-- Tab: Overview -->
        <div class="tab-content p-5" id="tab-overview">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Real-time Stats -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <h3 class="text-base font-black text-slate-800">Anlık Veriler</h3>
                        <span class="flex items-center gap-1 text-xs text-emerald-600 font-bold"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>Canlı</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                            <div class="text-xs text-emerald-700 font-bold mb-1">Günlük Ciro</div>
                            <div class="text-lg font-black text-emerald-900" id="dailyRevenue"><?php echo number_format($daily_revenue ?: $revenue_today, 2); ?> ₺</div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                            <div class="text-xs text-blue-700 font-bold mb-1">Saatlik Satış</div>
                            <div class="text-lg font-black text-blue-900" id="hourlySales"><?php echo number_format($hourly_sales_total, 2); ?> ₺</div>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-4 border border-orange-100">
                            <div class="text-xs text-orange-700 font-bold mb-1">Dolu Masa</div>
                            <div class="text-lg font-black text-orange-900" id="occupiedTables"><?php echo $occupied_tables ?: $occupied_tables_count; ?></div>
                            <div class="text-xs text-orange-600" id="tableStats">/ <?php echo $table_count ?: $total_tables_count; ?> masa</div>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                            <div class="text-xs text-purple-700 font-bold mb-1">Aktif Sipariş</div>
                            <div class="text-lg font-black text-purple-900" id="activeOrders"><?php echo $active_orders_count; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Summary -->
                <div>
                    <h3 class="text-base font-black text-slate-800 mb-4">Özet Bilgiler</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-500">Menü Ürünleri</span>
                            <span class="text-sm font-black text-slate-800"><?php echo $menu_item_count; ?> ürün / <?php echo $category_count; ?> kategori</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-500">Bugünkü Sipariş</span>
                            <span class="text-sm font-black text-slate-800"><?php echo number_format($orders_today); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-500">Aylık Sipariş</span>
                            <span class="text-sm font-black text-slate-800"><?php echo number_format($orders_month); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-500">Masa Doluluk</span>
                            <span class="text-sm font-black text-slate-800">%<?php echo $tableOccupancy; ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-500">Kayıt Tarihi</span>
                            <span class="text-sm font-black text-slate-800"><?php echo isset($customer['created_at']) ? date('d.m.Y H:i', strtotime($customer['created_at'])) : '-'; ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-slate-500">Son Giriş</span>
                            <span class="text-sm font-black text-slate-800"><?php echo isset($customer['last_login_at']) ? date('d.m.Y H:i', strtotime($customer['last_login_at'])) : 'Henüz giriş yapılmamış'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($subscription):
                // The subscriptions schema uses `current_period_start/end` (not
                // `start_date/end_date`), so the old view showed "-" for every
                // date even on active subs. Normalise before rendering and
                // surface trial/billing state in one place instead of two.
                $subStart = $subscription['current_period_start']
                    ?? $subscription['start_date']
                    ?? $subscription['trial_started_at']
                    ?? null;
                $subEnd = $subscription['current_period_end']
                    ?? $subscription['end_date']
                    ?? $subscription['trial_ends_at']
                    ?? null;
                $subStatus = strtolower((string)($subscription['status'] ?? ''));
                $subIsTrial = (int)($subscription['is_trial'] ?? 0) === 1;
                $statusLabel = $subStatus === 'active' ? 'Aktif'
                    : ($subStatus === 'pending' ? 'Ödeme Bekliyor'
                    : ($subStatus === 'cancelled' ? 'İptal'
                    : ($subStatus === 'expired' ? 'Süresi Doldu' : ucfirst($subStatus ?: '-'))));
                $statusColor = $subStatus === 'active' ? 'text-emerald-600'
                    : ($subStatus === 'pending' ? 'text-amber-600'
                    : ($subStatus === 'cancelled' || $subStatus === 'expired' ? 'text-red-600' : 'text-slate-600'));
            ?>
            <div class="mt-5 pt-5 border-t border-slate-100">
                <h3 class="text-base font-black text-slate-800 mb-3">Abonelik Bilgileri</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Paket</div>
                        <div class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($subscription['package_name'] ?? '-'); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Tip</div>
                        <div class="text-sm font-black <?= $subIsTrial ? 'text-amber-600' : 'text-indigo-600' ?>">
                            <?= $subIsTrial ? 'Ücretsiz Deneme' : 'Ücretli Abonelik' ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Durum</div>
                        <div class="text-sm font-black <?= $statusColor ?>"><?php echo htmlspecialchars($statusLabel); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Başlangıç</div>
                        <div class="text-sm font-black text-slate-800"><?php echo $subStart ? date('d.m.Y', strtotime($subStart)) : '-'; ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-0.5">Bitiş</div>
                        <div class="text-sm font-black text-slate-800"><?php echo $subEnd ? date('d.m.Y', strtotime($subEnd)) : '-'; ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== TRIAL & ROLE INFO ===== -->
            <div class="mt-5 pt-5 border-t border-slate-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-black text-slate-800">Deneme Süresi & Rol</h3>
                </div>

                <?php
                $_td = $trial_data;
                $_ou = $owner_user;
                ?>

                <!-- Role change -->
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <div class="text-sm text-slate-500">Sahip Rolü:</div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-black
                        <?= ($_ou['role'] ?? 'BUSINESS_MANAGER') === 'TRIAL'
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-emerald-100 text-emerald-700' ?>">
                        <?= htmlspecialchars($_ou['role_code'] ?? $_ou['role'] ?? 'Yok') ?>
                    </span>
                    <?php if (!empty($all_roles)): ?>
                    <select id="role-select-biz" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                        <?php foreach ($all_roles as $_r): ?>
                        <option value="<?= htmlspecialchars($_r['role_code']) ?>"
                            <?= ($_ou['role'] ?? '') === $_r['role_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($_r['role_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="changeOwnerRole('<?= htmlspecialchars($customer['customer_id'] ?? '') ?>')"
                            class="px-3 py-1.5 bg-slate-900 text-white text-xs font-black rounded-lg hover:bg-slate-700 transition-all">
                        Kaydet
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($_td['is_trial']): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-0.5">Deneme bitiş tarihi</div>
                        <div class="font-black text-slate-800">
                            <?= $_td['trial_ends_at'] ? date('d.m.Y H:i', $_td['trial_end_ts']) : '-' ?>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-0.5">Denemeye kalan</div>
                        <div class="font-black <?= $_td['is_expired'] ? 'text-red-600' : 'text-emerald-600' ?>">
                            <?php if ($_td['is_expired']): ?>
                                Süresi doldu
                            <?php else: ?>
                                <?= (int)$_td['days_left'] ?> gün
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-0.5">Ek süre (7 gün)</div>
                        <div class="font-black <?= $_td['fully_blocked'] ? 'text-red-600' : 'text-orange-600' ?>">
                            <?php if ($_td['fully_blocked']): ?>
                                Doldu — engellendi
                            <?php elseif ($_td['is_expired']): ?>
                                <?= (int)$_td['grace_days_left'] ?> gün kaldı
                            <?php else: ?>
                                Henüz başlamadı
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($_td['is_expired']): ?>
                    <div class="col-span-full">
                        <div class="px-3 py-2 rounded-lg text-xs font-bold <?= $_td['fully_blocked'] ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' ?>">
                            <?= $_td['fully_blocked']
                                ? '⛔ Deneme + ek süre bitti. İşletme tam olarak engellendi, yalnızca paket alımı ile açılabilir.'
                                : '⚠️ Deneme süresi bitti. Grace period içinde: sisteme girilebilir ama düzenleme yapılamaz. ' . (int)$_td['grace_days_left'] . ' gün sonra tamamen kilitlenecek.' ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="text-sm text-slate-400 mb-3">Bu işletmenin aktif bir deneme aboneliği yok.</div>
                <button onclick="startTrialForBusiness('<?= htmlspecialchars($customer['customer_id'] ?? '') ?>')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition-colors">
                    Ücretsiz Deneme Başlat
                </button>
                <?php endif; ?>

                <?php
                // Only show "Abonelik Aktifleştir (Paket Ata)" when there's NO
                // active subscription at all — previously we also showed it
                // alongside an active trial, which made it look like the
                // business somehow had two subscriptions that contradicted
                // each other ("Aktif ... Deneme aboneliği bulunamadı" + two
                // overlapping buttons).
                $_hasActiveSub = $subscription
                    && strtolower((string)($subscription['status'] ?? '')) === 'active';
                if (!$_hasActiveSub):
                ?>
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <button onclick="activateSubscriptionForBusiness('<?= htmlspecialchars($customer['customer_id'] ?? '') ?>')"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition-colors">
                        Abonelik Aktifleştir (Paket Ata)
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <!-- ===== / TRIAL & ROLE INFO ===== -->
  </div>
</div>
<script>
            function startTrialForBusiness(customerId) {
                if (!confirm('Bu işletme için ücretsiz deneme başlatmak istediğinize emin misiniz?')) return;
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch('/api/qodmin/businesses/' + customerId + '/start-trial', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({})
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        if (window.NotificationManager) window.NotificationManager.success('Ücretsiz deneme başlatıldı!');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (d.error || 'Bilinmeyen'));
                        else alert('Hata: ' + (d.error || 'Bilinmeyen'));
                    }
                })
                .catch(err => alert('Hata: ' + err.message));
            }
            function activateSubscriptionForBusiness(customerId) {
                if (!confirm('Bu işletme için abonelik aktifleştirmek istediğinize emin misiniz? İlk aktif paket atanacak.')) return;
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch('/api/qodmin/businesses/' + customerId + '/activate-subscription', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({})
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        if (window.NotificationManager) window.NotificationManager.success('Abonelik aktifleştirildi!');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (d.error || 'Bilinmeyen'));
                        else alert('Hata: ' + (d.error || 'Bilinmeyen'));
                    }
                })
                .catch(err => alert('Hata: ' + err.message));
            }
            function changeOwnerRole(customerId) {
                var roleCode = document.getElementById('role-select-biz').value;
                if (!confirm('Sahibin rolünü "' + roleCode + '" olarak değiştirmek istediğinize emin misiniz?')) return;
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch('/api/qodmin/businesses/' + customerId + '/change-role', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ role_code: roleCode })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        if (window.NotificationManager) window.NotificationManager.success('Rol güncellendi!');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (d.error || 'Bilinmeyen'));
                    }
                })
                .catch(err => alert('Hata: ' + err.message));
            }
            </script>
        </div>

        <!-- Tab: Orders -->
        <div class="tab-content p-5 hidden" id="tab-orders">
            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-slate-500 font-bold text-sm">Henüz sipariş bulunmuyor</p>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-black text-slate-800">Son Siparişler</h3>
                    <span class="text-xs text-slate-400"><?php echo number_format($total_orders); ?> toplam sipariş</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="q-table">
                        <thead>
                            <tr class="border-b-2 border-slate-100">
                                <th class="text-left py-2.5 px-3 text-xs font-black text-slate-500 uppercase">Sipariş ID</th>
                                <th class="text-right py-2.5 px-3 text-xs font-black text-slate-500 uppercase">Tutar</th>
                                <th class="text-center py-2.5 px-3 text-xs font-black text-slate-500 uppercase">Durum</th>
                                <th class="text-center py-2.5 px-3 text-xs font-black text-slate-500 uppercase">Ödeme</th>
                                <th class="text-right py-2.5 px-3 text-xs font-black text-slate-500 uppercase">Tarih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($recent_orders as $order): ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-3 text-sm font-medium text-slate-700">#<?php echo htmlspecialchars(substr($order['order_id'] ?? '', -8)); ?></td>
                                <td class="py-2.5 px-3 text-sm font-bold text-slate-800 text-right"><?php echo number_format($order['total_amount'] ?? 0, 2); ?> ₺</td>
                                <td class="py-2.5 px-3 text-center">
                                    <?php 
                                    $status = $order['status'] ?? '';
                                    $statusColors = [
                                        'SERVED' => 'bg-emerald-100 text-emerald-700',
                                        'PREPARING' => 'bg-amber-100 text-amber-700',
                                        'NEW' => 'bg-blue-100 text-blue-700',
                                        'CANCELLED' => 'bg-red-100 text-red-700',
                                        'READY' => 'bg-purple-100 text-purple-700',
                                    ];
                                    $statusLabels = [
                                        'SERVED' => 'Servis Edildi',
                                        'PREPARING' => 'Hazırlanıyor',
                                        'NEW' => 'Yeni',
                                        'CANCELLED' => 'İptal',
                                        'READY' => 'Hazır',
                                    ];
                                    $colorClass = $statusColors[$status] ?? 'bg-slate-100 text-slate-600';
                                    $label = $statusLabels[$status] ?? $status;
                                    ?>
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold <?php echo $colorClass; ?>"><?php echo $label; ?></span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <?php if (!empty($order['is_paid'])): ?>
                                        <span class="text-emerald-600 text-xs font-bold">Ödendi</span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs font-bold">Ödenmedi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3 text-xs text-slate-500 text-right"><?php echo isset($order['created_at']) ? date('d.m.Y H:i', strtotime($order['created_at'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Staff -->
        <div class="tab-content p-5 hidden" id="tab-staff">
            <?php if (empty($staff_list)): ?>
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <p class="text-slate-500 font-bold text-sm">Henüz personel eklenmemiş</p>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-black text-slate-800">Personel Listesi</h3>
                    <span class="text-xs text-slate-400"><?php echo $staff_count; ?> kişi</span>
                </div>
                <div class="space-y-2">
                    <?php foreach ($staff_list as $staff): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100/80 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-slate-200 flex items-center justify-center text-slate-600 font-bold text-xs">
                                <?php echo strtoupper(mb_substr($staff['name'] ?? '?', 0, 2)); ?>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($staff['name'] ?? '-'); ?></div>
                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($staff['role'] ?? $staff['role_id'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700"><?php echo htmlspecialchars($staff['role'] ?? $staff['role_id'] ?? '-'); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Business Info -->
        <div class="tab-content p-5 hidden" id="tab-info">
            <h3 class="text-base font-black text-slate-800 mb-4">İşletme Bilgileri</h3>
            <!-- Logo -->
            <div class="mb-6 pb-6 border-b border-slate-100">
                <div class="text-xs text-slate-400 font-bold mb-2">İşletme Logosu</div>
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 rounded-xl flex items-center justify-center overflow-hidden bg-slate-50 border border-slate-200 flex-shrink-0">
                        <img id="info-logo" src="<?php echo $logoUrl ? htmlspecialchars($logoUrl) : ''; ?>" alt="Logo" class="w-full h-full object-contain p-1" style="<?php echo $logoUrl ? '' : 'display:none;'; ?>" onerror="this.style.display='none'; document.getElementById('info-logo-fallback').style.display='flex';">
                        <div id="info-logo-fallback" class="w-full h-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-black text-lg" style="<?php echo $logoUrl ? 'display:none;' : ''; ?>"><?php echo $initials; ?></div>
                    </div>
                    <div>
                        <input type="file" id="logo-input" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" class="hidden">
                        <button type="button" onclick="document.getElementById('logo-input').click()" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-bold hover:bg-orange-600 transition-colors">
                            Logo Değiştir
                        </button>
                        <p class="text-xs text-slate-500 mt-1">PNG, JPG, GIF veya WebP (max 5MB)</p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">İşletme Adı</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['company_name'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">E-posta</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Ad</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['first_name'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Soyad</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['last_name'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Telefon</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Durum</div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black <?php echo $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>">
                        <?php echo $isActive ? 'Aktif' : 'Pasif'; ?>
                    </span>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Subdomain</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['subdomain'] ?? 'Yok'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Müşteri ID</div>
                    <div class="text-xs font-mono text-slate-600"><?php echo htmlspecialchars($customer['customer_id'] ?? '-'); ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Oluşturulma</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo isset($customer['created_at']) ? date('d.m.Y H:i', strtotime($customer['created_at'])) : '-'; ?></div>
                </div>
                <div class="py-2 border-b border-slate-50">
                    <div class="text-xs text-slate-400 font-bold mb-0.5">Son Giriş</div>
                    <div class="text-sm font-bold text-slate-800"><?php echo isset($customer['last_login_at']) ? date('d.m.Y H:i', strtotime($customer['last_login_at'])) : 'Henüz giriş yapılmamış'; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Menu Status Modal (Detail Page) -->
<div id="qrMenuStatusModalDetail" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4 shadow-2xl">
        <h3 class="text-xl font-black text-slate-800 mb-2" id="qrModalDetailTitle">QR Menü Durumu</h3>
        <p class="text-slate-500 text-sm mb-5">QR menünün müşterilere nasıl görüneceğini seçin:</p>
        
        <div class="space-y-3 mb-6">
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-green-400 transition-all has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                <input type="radio" name="qr_menu_status_detail" value="active" class="mt-1 accent-green-500" <?php echo ($customer['qr_menu_status'] ?? 'active') === 'active' ? 'checked' : ''; ?>>
                <div>
                    <div class="font-black text-slate-800">✅ Tam Aktif</div>
                    <p class="text-xs text-slate-500 mt-1">QR menü tam işlevsel. Müşteriler sipariş verebilir, garson çağırabilir, hesap isteyebilir.</p>
                </div>
            </label>
            
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-all has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                <input type="radio" name="qr_menu_status_detail" value="menu_only" class="mt-1 accent-amber-500" <?php echo ($customer['qr_menu_status'] ?? 'active') === 'menu_only' ? 'checked' : ''; ?>>
                <div>
                    <div class="font-black text-slate-800">📋 Sadece Menü Görüntüleme</div>
                    <p class="text-xs text-slate-500 mt-1">Müşteriler menüyü görebilir ama sipariş veremez, garson çağıramaz, hesap isteyemez.</p>
                </div>
            </label>
            
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-red-400 transition-all has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                <input type="radio" name="qr_menu_status_detail" value="passive" class="mt-1 accent-red-500" <?php echo ($customer['qr_menu_status'] ?? 'active') === 'passive' ? 'checked' : ''; ?>>
                <div>
                    <div class="font-black text-slate-800">🚫 Tamamen Kapalı</div>
                    <p class="text-xs text-slate-500 mt-1">QR kodu okutulduğunda "QR menümüz geçici olarak servis dışıdır" mesajı gösterilir.</p>
                </div>
            </label>
        </div>
        
        <div class="flex gap-3">
            <button onclick="closeQrMenuStatusModalDetail()" class="flex-1 px-4 py-2.5 bg-slate-200 rounded-xl font-bold hover:bg-slate-300 transition-all">İptal</button>
            <button id="confirmQrStatusDetailBtn" onclick="saveQrMenuStatusDetail()" class="flex-1 px-4 py-2.5 bg-indigo-500 text-white rounded-xl font-bold hover:bg-indigo-600 transition-all">Kaydet</button>
        </div>
    </div>
</div>

<script>
function openQrMenuStatusModalDetail() {
    document.getElementById('qrModalDetailTitle').textContent = '<?php echo addslashes($customer['company_name'] ?? 'İşletme'); ?> - QR Menü Durumu';
    document.getElementById('qrMenuStatusModalDetail').classList.remove('hidden');
}

function closeQrMenuStatusModalDetail() {
    document.getElementById('qrMenuStatusModalDetail').classList.add('hidden');
}

// Süper admin -> işletme için Meta WhatsApp izni aç/kapa toggle'ı.
async function toggleMetaWhatsApp(customerId, currentlyEnabled) {
    const newVal = currentlyEnabled ? 0 : 1;
    const btn = document.getElementById('metaWhatsAppToggleBtn');
    const label = document.getElementById('metaWhatsAppToggleLabel');
    if (btn) btn.disabled = true;
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
        if (window.NotificationManager) window.NotificationManager.success(data.message || 'Meta WhatsApp izni güncellendi');
        if (label) label.textContent = 'Meta WhatsApp: ' + (newVal ? 'AÇIK' : 'KAPALI');
        if (btn) {
            btn.classList.toggle('bg-emerald-500', !!newVal);
            btn.classList.toggle('hover:bg-emerald-600', !!newVal);
            btn.classList.toggle('bg-slate-400', !newVal);
            btn.classList.toggle('hover:bg-slate-500', !newVal);
            btn.setAttribute('onclick', `toggleMetaWhatsApp('${customerId}', ${newVal})`);
        }
    } catch (e) {
        if (window.NotificationManager) window.NotificationManager.error(e.message || 'Hata');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function saveQrMenuStatusDetail() {
    const selected = document.querySelector('input[name="qr_menu_status_detail"]:checked');
    if (!selected) return;
    
    const btn = document.getElementById('confirmQrStatusDetailBtn');
    btn.disabled = true;
    btn.textContent = 'Kaydediliyor...';
    
    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/<?php echo htmlspecialchars($customer['customer_id']); ?>/qr-menu-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_menu_status: selected.value })
        });
        const data = await res.json();
        if (data.success) {
            window.NotificationManager.success(data.message || 'QR menü durumu güncellendi!');
            closeQrMenuStatusModalDetail();
            window.location.reload();
        } else {
            window.NotificationManager.error('Hata: ' + (data.message || 'Güncelleme başarısız'));
            btn.disabled = false;
            btn.textContent = 'Kaydet';
        }
    } catch (err) {
        window.NotificationManager.error('Bağlantı hatası: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Kaydet';
    }
}

async function toggleBusinessStatusDetail(customerId, currentStatus, companyName) {
    if (currentStatus === 1) {
        openQrMenuStatusModalDeactivate(customerId, companyName);
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
                window.NotificationManager.success(data.message || 'İşletme aktif yapıldı!');
                window.location.reload();
            } else {
                window.NotificationManager.error('Hata: ' + (data.message || 'İşlem başarısız'));
            }
        } catch (err) {
            window.NotificationManager.error('Bağlantı hatası: ' + err.message);
        }
    }
}

let deactivateBusinessId = null;

function openQrMenuStatusModalDeactivate(customerId, companyName) {
    deactivateBusinessId = customerId;
    document.getElementById('deactivateModalTitle').textContent = companyName;
    document.getElementById('deactivateQrModal').classList.remove('hidden');
}

function closeDeactivateQrModal() {
    document.getElementById('deactivateQrModal').classList.add('hidden');
    deactivateBusinessId = null;
}

async function confirmDeactivateDetail() {
    if (!deactivateBusinessId) return;
    const selected = document.querySelector('input[name="deactivate_qr_status"]:checked');
    if (!selected) {
        window.NotificationManager.error('Lütfen bir QR menü seçeneği seçin');
        return;
    }
    
    const btn = document.getElementById('confirmDeactivateDetailBtn');
    btn.disabled = true;
    btn.textContent = 'İşleniyor...';
    
    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + deactivateBusinessId + '/toggle-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_menu_status: selected.value })
        });
        const data = await res.json();
        if (data.success) {
            window.NotificationManager.success(data.message || 'İşletme pasife alındı!');
            closeDeactivateQrModal();
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
</script>

<!-- Deactivate with QR Status Modal -->
<div id="deactivateQrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4 shadow-2xl">
        <h3 class="text-xl font-black text-slate-800 mb-2">🔒 İşletmeyi Pasife Al</h3>
        <p class="text-slate-600 mb-1 text-sm"><strong id="deactivateModalTitle"></strong> işletmesini pasife alıyorsunuz.</p>
        <p class="text-slate-500 text-sm mb-5">QR menünün müşterilere nasıl görüneceğini seçin:</p>
        
        <div class="space-y-3 mb-6">
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-all has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                <input type="radio" name="deactivate_qr_status" value="menu_only" class="mt-1 accent-amber-500" checked>
                <div>
                    <div class="font-black text-slate-800">📋 Sadece Menü Görüntüleme</div>
                    <p class="text-xs text-slate-500 mt-1">Müşteriler menüyü görebilir ama sipariş veremez, garson çağıramaz, hesap isteyemez.</p>
                </div>
            </label>
            <label class="flex items-start gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-red-400 transition-all has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                <input type="radio" name="deactivate_qr_status" value="passive" class="mt-1 accent-red-500">
                <div>
                    <div class="font-black text-slate-800">🚫 Tamamen Kapalı</div>
                    <p class="text-xs text-slate-500 mt-1">QR kodu okutulduğunda "QR menümüz geçici olarak servis dışıdır" mesajı gösterilir.</p>
                </div>
            </label>
        </div>
        
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5">
            <p class="text-xs text-amber-800 font-bold">⚠️ Not: Pasife alınan işletmeye ait tüm kullanıcılar giriş yapamayacaktır.</p>
        </div>
        
        <div class="flex gap-3">
            <button onclick="closeDeactivateQrModal()" class="flex-1 px-4 py-2.5 bg-slate-200 rounded-xl font-bold hover:bg-slate-300 transition-all">İptal</button>
            <button id="confirmDeactivateDetailBtn" onclick="confirmDeactivateDetail()" class="flex-1 px-4 py-2.5 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all">Pasife Al</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('text-orange-600', 'border-orange-500');
                b.classList.add('text-slate-400', 'border-transparent');
            });
            this.classList.remove('text-slate-400', 'border-transparent');
            this.classList.add('text-orange-600', 'border-orange-500');
            
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('tab-' + this.dataset.tab)?.classList.remove('hidden');
        });
    });
    
    // Real-time stats update
    const businessId = '<?php echo htmlspecialchars($customer['customer_id'] ?? ''); ?>';
    const apiUrl = '<?php echo BASE_URL; ?>/api/qodmin/businesses/' + businessId + '/stats';
    let updateInterval = null;
    
    function formatNumber(num, decimals = 2) {
        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    }
    
    function updateStats() {
        fetch(apiUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (!data.success || !data.data) return;
            const s = data.data;
            
            const updates = {
                'dailyRevenue': formatNumber(s.daily_revenue) + ' ₺',
                'hourlySales': formatNumber(s.hourly_sales_total) + ' ₺',
                'occupiedTables': s.occupied_tables_count,
                'tableStats': '/ ' + s.total_tables_count + ' masa',
                'activeOrders': s.active_orders_count,
                'revenueToday': formatNumber(s.daily_revenue) + ' ₺'
            };
            
            for (const [id, val] of Object.entries(updates)) {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            }
        })
        .catch(() => {});
    }
    
    function startAutoUpdate() {
        updateStats();
        updateInterval = setInterval(updateStats, 30000);
    }
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(updateInterval);
            updateInterval = null;
        } else if (!updateInterval) {
            startAutoUpdate();
        }
    });
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startAutoUpdate);
    } else {
        startAutoUpdate();
    }
    
    // Logo upload
    const logoInput = document.getElementById('logo-input');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('logo', file);
            fetch('<?php echo BASE_URL; ?>/api/qodmin/businesses/' + businessId + '/upload-logo', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.url) {
                    const infoLogo = document.getElementById('info-logo');
                    const infoFallback = document.getElementById('info-logo-fallback');
                    const headerLogo = document.getElementById('header-logo');
                    const headerFallback = document.getElementById('header-logo-fallback');
                    const newUrl = data.url + (data.url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
                    if (infoLogo) { infoLogo.src = newUrl; infoLogo.style.display = ''; if (infoFallback) infoFallback.style.display = 'none'; }
                    if (headerLogo) { headerLogo.src = newUrl; headerLogo.style.display = ''; if (headerFallback) headerFallback.style.display = 'none'; }
                    if (window.NotificationManager) window.NotificationManager.success('Logo güncellendi');
                    else alert('Logo güncellendi');
                } else {
                    if (window.NotificationManager) window.NotificationManager.error(data.message || 'Logo yüklenemedi');
                    else alert(data.message || 'Logo yüklenemedi');
                }
            })
            .catch(() => {
                if (window.NotificationManager) window.NotificationManager.error('Logo yüklenirken hata oluştu');
                else alert('Logo yüklenirken hata oluştu');
            });
            this.value = '';
        });
    }
})();
</script>
