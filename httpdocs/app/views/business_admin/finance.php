<?php
require_once __DIR__ . '/../../helpers/translations.php';

$financeUri = $_SERVER['REQUEST_URI'] ?? '';
$financeNavActive = static function (string $segment) use ($financeUri): bool {
    if ($segment === 'overview') {
        return strpos($financeUri, '/business/finance') !== false
            && strpos($financeUri, '/business/finance/') === false;
    }
    return strpos($financeUri, $segment) !== false;
};
// Prefer the service-computed net profit; fall back to deriving it locally
// so the figure stays correct even if the key is ever omitted.
$netProfit = $financial_data['net_profit']
    ?? (($financial_data['total_revenue'] ?? 0) - ($financial_data['total_expenses'] ?? 0));
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Finans</p>
        <h1 class="q-page-header__title">Genel Bakış</h1>
        <p class="q-page-header__subtitle">İşletme finansal durumunuzu takip edin</p>
      </div>
    </header>

    <nav class="q-tab-row q-tab-row--card" role="tablist" aria-label="Finans menüsü">
      <a href="<?php echo BASE_URL; ?>/business/finance" role="tab" aria-selected="<?php echo $financeNavActive('overview') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('overview') ? 'selected' : ''; ?>">Genel Bakış</a>
      <a href="<?php echo BASE_URL; ?>/business/finance/expenses" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/expenses') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/expenses') ? 'selected' : ''; ?>">Giderler</a>
      <a href="<?php echo BASE_URL; ?>/business/finance/invoices" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/invoices') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/invoices') ? 'selected' : ''; ?>">Faturalar</a>
      <a href="<?php echo BASE_URL; ?>/business/finance/suppliers" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/suppliers') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/suppliers') ? 'selected' : ''; ?>">Tedarikçiler</a>
      <a href="<?php echo BASE_URL; ?>/business/finance/waste" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/waste') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/waste') ? 'selected' : ''; ?>">İsraf</a>
      <a href="<?php echo BASE_URL; ?>/business/inventory" role="tab" aria-selected="<?php echo $financeNavActive('/business/inventory') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/inventory') ? 'selected' : ''; ?>">Stok Takibi</a>
    </nav>

    <div class="q-grid q-grid--4">
      <div class="q-stat">
        <div class="q-stat__top">
          <span class="q-stat__label">Toplam Gelir</span>
          <span class="q-stat__icon" style="background:var(--biz-indigo-soft);color:var(--biz-indigo-hover);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path></svg>
          </span>
        </div>
        <div class="q-stat__value">₺<?php echo number_format($financial_data['total_revenue'] ?? 0, 2); ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top">
          <span class="q-stat__label">Toplam Gider</span>
          <span class="q-stat__icon" style="background:var(--color-status-danger-bg);color:var(--color-status-danger);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
          </span>
        </div>
        <div class="q-stat__value">₺<?php echo number_format($financial_data['total_expenses'] ?? 0, 2); ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top">
          <span class="q-stat__label">Net Kar</span>
          <span class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
          </span>
        </div>
        <div class="q-stat__value">₺<?php echo number_format($netProfit, 2); ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top">
          <span class="q-stat__label">Ödeme Yöntemleri</span>
          <span class="q-stat__icon" style="background:var(--color-surface-3);color:var(--color-text-secondary);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
          </span>
        </div>
        <div class="q-stat__value"><?php echo count($payment_methods ?? []); ?></div>
      </div>
    </div>

    <div class="q-card q-card--pad q-stack">
      <div class="q-toolbar">
        <div class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        </div>
        <h2 class="q-page-header__title" style="font-size:var(--font-size-xl);margin:0;">Gelir ve Gider Dağılımı</h2>
      </div>
      <div class="q-chart-wrap q-chart-wrap--lg">
        <canvas id="incomeExpensesChart" aria-label="Gelir ve gider grafiği"></canvas>
      </div>
    </div>

    <div class="q-card q-card--pad q-stack">
      <div class="q-tab-row" role="tablist" aria-label="Finans detayları">
        <button type="button" class="q-tab selected finance-tab-btn" role="tab" aria-selected="true" data-tab="income">Gelirler</button>
        <button type="button" class="q-tab finance-tab-btn" role="tab" aria-selected="false" data-tab="expenses">Giderler</button>
        <button type="button" class="q-tab finance-tab-btn" role="tab" aria-selected="false" data-tab="payments">Ödemeler</button>
      </div>

      <div id="income-tab" class="finance-tab-content">
        <?php if (!empty($income_expenses['income'] ?? [])): ?>
        <div class="overflow-x-auto">
          <table class="q-table q-table-row-hover-amber">
            <thead>
              <tr>
                <th>Tarih</th>
                <th>Açıklama</th>
                <th>Tutar</th>
                <th>Tür</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($income_expenses['income'] ?? [] as $income): ?>
              <tr>
                <td><?php echo date('d.m.Y', strtotime($income['date'] ?? date('Y-m-d'))); ?></td>
                <td><?php echo htmlspecialchars($income['description'] ?? 'Satış'); ?></td>
                <td><span class="q-stat__delta q-stat__delta--up">+ ₺<?php echo number_format($income['amount'] ?? 0, 2); ?></span></td>
                <td><span class="q-badge q-badge--live"><?php echo htmlspecialchars($income['type'] ?? 'Satış'); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="q-empty q-empty--inline">
          <svg class="q-empty__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path></svg>
          <p class="q-empty__title">Gelir kaydı yok</p>
          <p class="text-sm text-[var(--color-text-muted)] mt-1">Gelir kaydetmek için sipariş alın</p>
        </div>
        <?php endif; ?>
      </div>

      <div id="expenses-tab" class="finance-tab-content hidden">
        <?php if (!empty($income_expenses['expenses'] ?? [])): ?>
        <div class="overflow-x-auto">
          <table class="q-table q-table-row-hover-amber">
            <thead>
              <tr>
                <th>Tarih</th>
                <th>Açıklama</th>
                <th>Tutar</th>
                <th>Tür</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($income_expenses['expenses'] ?? [] as $expense): ?>
              <tr>
                <td><?php echo date('d.m.Y', strtotime($expense['date'] ?? date('Y-m-d'))); ?></td>
                <td><?php echo htmlspecialchars($expense['title'] ?? $expense['description'] ?? 'Gider'); ?></td>
                <td><span class="q-stat__delta q-stat__delta--down">- ₺<?php echo number_format($expense['amount'] ?? 0, 2); ?></span></td>
                <td><span class="q-badge q-badge--danger"><?php echo htmlspecialchars($expense['type'] ?? 'Gider'); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="q-empty q-empty--inline">
          <svg class="q-empty__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4"></path></svg>
          <p class="q-empty__title">Gider kaydı yok</p>
          <p class="text-sm text-[var(--color-text-muted)] mt-1">Gideriniz bulunmuyor</p>
        </div>
        <?php endif; ?>
      </div>

      <div id="payments-tab" class="finance-tab-content hidden">
        <?php if (!empty($payment_methods)): ?>
        <div class="overflow-x-auto">
          <table class="q-table q-table-row-hover-amber">
            <thead>
              <tr>
                <th>Yöntem</th>
                <th>Durum</th>
                <th>Kullanım</th>
                <th>İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payment_methods as $method): ?>
              <tr>
                <td class="font-semibold"><?php echo htmlspecialchars($method['name'] ?? 'Ödeme Yöntemi'); ?></td>
                <td>
                  <span class="q-badge <?php echo ($method['is_active'] ?? true) ? 'q-badge--live' : 'q-badge--neutral'; ?>">
                    <?php echo ($method['is_active'] ?? true) ? 'Aktif' : 'Pasif'; ?>
                  </span>
                </td>
                <td><?php echo $method['usage_count'] ?? 0; ?> kez kullanıldı</td>
                <td><a href="#" class="q-btn q-btn--ghost q-btn--sm">Düzenle</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="q-empty q-empty--inline">
          <svg class="q-empty__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
          <p class="q-empty__title">Ödeme yöntemi yok</p>
          <p class="text-sm text-[var(--color-text-muted)] mt-1">Ödeme yöntemi ekleyin</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('incomeExpensesChart').getContext('2d');
  const income = <?php echo json_encode(array_sum(array_column($income_expenses['income'] ?? [], 'amount'))); ?>;
  const expenses = <?php echo json_encode(array_sum(array_column($income_expenses['expenses'] ?? [], 'amount'))); ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Gelir', 'Gider'],
      datasets: [{
        label: 'Tutar (₺)',
        data: [income || 0, expenses || 0],
        backgroundColor: ['rgba(99, 102, 241, 0.85)', 'rgba(239, 68, 68, 0.75)'],
        borderColor: ['rgb(79, 70, 229)', 'rgb(220, 38, 38)'],
        borderWidth: 2,
        borderRadius: 8
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
              return '₺' + value.toLocaleString('tr-TR');
            }
          }
        }
      },
      plugins: { legend: { display: false } }
    }
  });

  const tabButtons = document.querySelectorAll('.finance-tab-btn');
  const tabContents = document.querySelectorAll('.finance-tab-content');

  tabButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      tabButtons.forEach(function(btn) {
        btn.classList.remove('selected');
        btn.setAttribute('aria-selected', 'false');
      });
      tabContents.forEach(function(content) {
        content.classList.add('hidden');
      });
      button.classList.add('selected');
      button.setAttribute('aria-selected', 'true');
      document.getElementById(button.getAttribute('data-tab') + '-tab').classList.remove('hidden');
    });
  });
});
</script>
