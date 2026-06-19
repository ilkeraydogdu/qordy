<?php
/**
 * Admin Table History View
 * Masa geçmişi detay sayfası - analiz ve raporlar
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$tableId = $table_id ?? '';
$tableName = $table_name ?? 'Masa';
$sessions = $sessions ?? [];
$startDate = $start_date ?? date('Y-m-01');
$endDate = $end_date ?? date('Y-m-d');

$totalRevenue = 0;
$totalSessions = count($sessions);
$totalReceipts = 0;

foreach ($sessions as $session) {
    $totalRevenue += floatval($session['total_revenue'] ?? 0);
    if (!empty($session['receipt_ids'])) {
        $totalReceipts += count(explode(',', $session['receipt_ids']));
    }
}
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Masa Analizi</p>
            <h1 class="q-page-header__title"><?php echo htmlspecialchars($tableName); ?> — Geçmiş</h1>
            <p class="q-page-header__subtitle">Masa geçmişi ve oturum analizi</p>
        </div>
        <div class="q-page-header__actions q-filter-bar">
            <input type="date" id="start-date" value="<?php echo htmlspecialchars($startDate); ?>" class="q-input">
            <input type="date" id="end-date" value="<?php echo htmlspecialchars($endDate); ?>" class="q-input">
            <button type="button" onclick="filterHistory()" class="q-btn q-btn--primary">Filtrele</button>
        </div>
    </header>

    <div class="q-grid q-grid--3 gap-4">
        <div class="q-card q-card--pad">
            <p class="q-hint text-xs uppercase tracking-wide mb-2">Toplam Ciro</p>
            <p class="text-2xl font-semibold" style="color:var(--color-accent-primary);"><?php echo formatCurrency($totalRevenue); ?></p>
        </div>
        <div class="q-card q-card--pad">
            <p class="q-hint text-xs uppercase tracking-wide mb-2">Oturum Sayısı</p>
            <p class="text-2xl font-semibold"><?php echo $totalSessions; ?></p>
        </div>
        <div class="q-card q-card--pad">
            <p class="q-hint text-xs uppercase tracking-wide mb-2">Adisyon Sayısı</p>
            <p class="text-2xl font-semibold"><?php echo $totalReceipts; ?></p>
        </div>
    </div>

    <div class="q-card q-card--pad">
        <h2 class="text-lg font-semibold mb-4">Oturum Geçmişi</h2>

        <?php if (empty($sessions)): ?>
            <div class="text-center py-12 q-hint">
                Bu tarih aralığında oturum bulunamadı
            </div>
        <?php else: ?>
            <div class="q-stack q-stack--sm">
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $startTime = date('d.m.Y H:i', strtotime($session['start_time'] ?? $session['created_at'] ?? 'now'));
                    $endTime = $session['end_time'] ? date('d.m.Y H:i', strtotime($session['end_time'])) : 'Devam ediyor';
                    $revenue = floatval($session['total_revenue'] ?? 0);
                    $tip = floatval($session['total_tip'] ?? 0);
                    $receiptIds = $session['receipt_ids'] ?? '';
                    $receiptCount = $receiptIds ? count(explode(',', $receiptIds)) : 0;
                    ?>
                    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                        <div class="flex flex-col sm:flex-row justify-between gap-3 sm:gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-base mb-2">
                                    <?php echo $startTime; ?> — <?php echo $endTime; ?>
                                </div>
                                <div class="q-hint text-sm q-stack q-stack--xs">
                                    <div>Oturum ID: <?php echo htmlspecialchars($session['session_id'] ?? ''); ?></div>
                                    <?php if ($receiptCount > 0): ?>
                                        <div>Adisyonlar: <?php echo $receiptCount; ?> adet</div>
                                    <?php endif; ?>
                                    <?php if ($tip > 0): ?>
                                        <div>Bahşiş: <?php echo formatCurrency($tip); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-semibold text-xl" style="color:var(--color-accent-primary);">
                                    <?php echo formatCurrency($revenue); ?>
                                </div>
                                <div class="q-hint text-xs">Ciro</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || window.BASE_URL || '';
const tableId = <?php echo json_encode($tableId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function filterHistory() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    window.location.href = `${baseUrl}/qodmin/table-history/${tableId}?start_date=${startDate}&end_date=${endDate}`;
}
</script>
