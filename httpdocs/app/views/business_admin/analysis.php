<?php
require_once __DIR__ . '/../../helpers/translations.php';
?>
<div class="q-page q-biz-theme">
    <div class="q-container q-stack q-stack--lg">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">İŞLETME</p>
                <h1 class="q-page-header__title">Analiz</h1>
                <p class="q-page-header__subtitle">İşletme performans analizlerinizi görüntüleyin</p>
            </div>
        </header>

        <section class="q-grid q-grid--4">
            <div class="q-stat q-stat--compact">
                <div class="q-stat__top"><span class="q-stat__label">Toplam Sipariş</span></div>
                <div class="q-stat__value"><?php echo (int) ($business_analytics['total_orders'] ?? 0); ?></div>
            </div>
            <div class="q-stat q-stat--compact">
                <div class="q-stat__top"><span class="q-stat__label">Toplam Gelir</span></div>
                <div class="q-stat__value">₺<?php echo number_format($business_analytics['total_revenue'] ?? 0, 2); ?></div>
            </div>
            <div class="q-stat q-stat--compact">
                <div class="q-stat__top"><span class="q-stat__label">Ortalama Sipariş</span></div>
                <div class="q-stat__value">₺<?php echo number_format($business_analytics['avg_order_value'] ?? 0, 2); ?></div>
            </div>
            <div class="q-stat q-stat--compact">
                <div class="q-stat__top"><span class="q-stat__label">Müşteri Sayısı</span></div>
                <div class="q-stat__value"><?php echo (int) ($business_analytics['customer_count'] ?? 0); ?></div>
            </div>
        </section>

        <section class="q-grid q-grid--2">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">Gelir Trendi</h2>
                <div class="q-chart-wrap">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">Sipariş Sayısı</h2>
                <div class="q-chart-wrap">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
        </section>

        <section class="q-card q-card--pad q-stack">
            <div class="q-card__header">
                <h2 class="q-card__title">Raporlar</h2>
                <div class="q-toolbar q-toolbar--between">
                    <select class="q-input">
                        <option>Geçen Ay</option>
                        <option>Bu Ay</option>
                        <option>Bu Yıl</option>
                        <option>Özel Tarih</option>
                    </select>
                    <button type="button" class="q-btn q-btn--primary">Rapor Al</button>
                </div>
            </div>

            <?php if (!empty($reports)): ?>
                <div class="overflow-x-auto">
                    <table class="q-table">
                        <thead>
                            <tr>
                                <th>Rapor Adı</th>
                                <th>Tarih</th>
                                <th>Tip</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['name'] ?? 'Rapor'); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($report['date'] ?? date('Y-m-d'))); ?></td>
                                    <td><span class="q-badge q-badge--info"><?php echo htmlspecialchars($report['type'] ?? 'Genel'); ?></span></td>
                                    <td>
                                        <div class="flex gap-2">
                                            <a href="#" class="q-btn q-btn--ghost q-btn--sm">Görüntüle</a>
                                            <a href="#" class="q-btn q-btn--soft q-btn--sm">İndir</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="q-empty">
                    <p class="q-empty__title">Rapor bulunamadı</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
            datasets: [{
                label: 'Gelir (₺)',
                data: [12000, 19000, 15000, 18000, 22000, 17000, 25000, 21000, 24000, 28000, 26000, 30000],
                borderColor: 'rgb(245, 158, 11)',
                backgroundColor: 'rgba(245, 158, 11, 0.12)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₺' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'bar',
        data: {
            labels: ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'],
            datasets: [{
                label: 'Sipariş Sayısı',
                data: [12, 19, 15, 18, 22, 30, 25],
                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                borderColor: 'rgb(245, 158, 11)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
