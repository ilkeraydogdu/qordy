<?php
/**
 * Customer Orders History Page
 * Müşteriler için sipariş geçmişi sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';

$orders = $orders ?? [];
$stats = $stats ?? [];
$current_status = $current_status ?? 'all';
$start_date = $start_date ?? null;
$end_date = $end_date ?? null;
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$total_orders = $total_orders ?? 0;
$per_page = $per_page ?? 20;
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6 animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Header -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-800 mb-2 sm:mb-3">Sipariş Geçmişi</h1>
        <p class="text-sm sm:text-base text-slate-600">Tüm siparişlerinizi buradan görüntüleyebilirsiniz.</p>
    </div>
    
    <!-- Statistics Cards -->
    <?php if (!empty($stats)): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Toplam</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-slate-800"><?php echo number_format($stats['total'] ?? 0); ?></div>
        </div>
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Beklemede</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-yellow-600"><?php echo number_format($stats['pending'] ?? 0); ?></div>
        </div>
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Hazırlanıyor</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-blue-600"><?php echo number_format($stats['preparing'] ?? 0); ?></div>
        </div>
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Hazır</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-indigo-600"><?php echo number_format($stats['ready'] ?? 0); ?></div>
        </div>
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Servis Edildi</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-green-600"><?php echo number_format($stats['served'] ?? 0); ?></div>
        </div>
        <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl shadow-soft border border-slate-100">
            <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">İptal</div>
            <div class="text-lg sm:text-xl md:text-2xl font-black text-red-600"><?php echo number_format($stats['cancelled'] ?? 0); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <form method="GET" action="<?php echo BASE_URL; ?>/customer/orders" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">Durum</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all" <?php echo $current_status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="PENDING" <?php echo $current_status === 'PENDING' ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="PREPARING" <?php echo $current_status === 'PREPARING' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                    <option value="READY" <?php echo $current_status === 'READY' ? 'selected' : ''; ?>>Hazır</option>
                    <option value="SERVED" <?php echo $current_status === 'SERVED' ? 'selected' : ''; ?>>Servis Edildi</option>
                    <option value="CANCELLED" <?php echo $current_status === 'CANCELLED' ? 'selected' : ''; ?>>İptal</option>
                </select>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">Başlangıç Tarihi</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">Bitiş Tarihi</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    Filtrele
                </button>
            </div>
        </form>
    </div>
    
    <!-- Orders List -->
    <?php if (empty($orders)): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100 text-center py-8 sm:py-10 md:py-12">
        <p class="text-slate-500 text-sm sm:text-base">Henüz sipariş kaydınız bulunmamaktadır.</p>
    </div>
    <?php else: ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm sm:text-base">
                <thead>
                    <tr class="border-b-2 border-slate-200">
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Sipariş No</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Tarih</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Masa</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Ürün Sayısı</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Tutar</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">Durum</th>
                        <th class="text-left py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-700">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="py-3 sm:py-4 px-3 sm:px-4 font-mono text-xs sm:text-sm text-slate-600">
                            <?php echo htmlspecialchars(substr($order['order_id'] ?? '', 0, 12)); ?>...
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php 
                            if ($order['created_at']) {
                                echo date('d.m.Y H:i', strtotime($order['created_at']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php echo htmlspecialchars($order['table_name'] ?? 'Masa Yok'); ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 text-slate-600">
                            <?php echo number_format($order['items_count'] ?? 0); ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4 font-black text-slate-800">
                            ₺<?php echo number_format($order['total_amount'] ?? 0, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4">
                            <?php 
                            $status = strtoupper((string)($order['status'] ?? ''));
                            $statusColors = [
                                'PENDING' => 'bg-yellow-100 text-yellow-800',
                                'PREPARING' => 'bg-blue-100 text-blue-800',
                                'READY' => 'bg-indigo-100 text-indigo-800',
                                'SERVED' => 'bg-green-100 text-green-800',
                                'CANCELLED' => 'bg-red-100 text-red-800',
                                'REFUNDED' => 'bg-slate-100 text-slate-800'
                            ];
                            $statusLabels = [
                                'PENDING' => 'Beklemede',
                                'PREPARING' => 'Hazırlanıyor',
                                'READY' => 'Hazır',
                                'SERVED' => 'Servis Edildi',
                                'CANCELLED' => 'İptal',
                                'REFUNDED' => 'İade Edildi'
                            ];
                            $statusColor = $statusColors[$status] ?? 'bg-slate-100 text-slate-800';
                            $statusLabel = $statusLabels[$status] ?? $status;
                            ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black <?php echo $statusColor; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td class="py-3 sm:py-4 px-3 sm:px-4">
                            <a href="<?php echo BASE_URL; ?>/customer/orders/<?php echo htmlspecialchars($order['order_id']); ?>" class="text-indigo-600 hover:text-indigo-700 font-bold text-xs sm:text-sm">
                                Detay
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 sm:mt-6 flex items-center justify-between">
            <div class="text-sm text-slate-600">
                Toplam <?php echo number_format($total_orders); ?> sipariş, Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?>
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="<?php echo BASE_URL; ?>/customer/orders?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($current_status); ?><?php echo $start_date ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo $end_date ? '&end_date=' . urlencode($end_date) : ''; ?>" class="px-3 py-2 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 text-sm font-bold text-slate-700">
                    Önceki
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo BASE_URL; ?>/customer/orders?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($current_status); ?><?php echo $start_date ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo $end_date ? '&end_date=' . urlencode($end_date) : ''; ?>" class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-bold">
                    Sonraki
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
