<?php
/**
 * Business Admin Dashboard - Modern Q-System Edition
 *
 * Refactored to use Qordy design tokens (q-stat, q-card, q-table, q-badge).
 * Hardcoded Tailwind utilities replaced with reusable q-* classes.
 * Modern: glassmorphism, hero glow, stagger animations, hover-lift.
 */
require_once __DIR__ . '/../layouts/components/StatCard.php';
require_once __DIR__ . '/../layouts/components/QuickAction.php';
require_once __DIR__ . '/../layouts/components/Badge.php';
require_once __DIR__ . '/partials/Header.php';
require_once __DIR__ . '/partials/StatsCards.php';
require_once __DIR__ . '/partials/RecentOrdersTable.php';

$userName = $_SESSION['first_name'] ?? $_SESSION['name'] ?? 'İşletme Sahibi';
$businessName = $business['company_name'] ?? $business['email'] ?? 'İşletmeniz';
$logo = $business['logo_path'] ?? null;
?>
<div class="q-page q-biz-theme q-hero-glow" data-page="business-dashboard">
 <div class="q-container q-stack q-stack--lg">

 <!-- ===== HERO HEADER (glass + glow) ===== -->
 <header class="q-card q-card--glass q-card--pad q-hero-glow stagger-item" role="banner">
 <div class="q-page-header">
 <div>
 <span class="q-page-header__eyebrow--accent">
 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
 HOŞ GELDİNİZ
 </span>
 <h1 class="q-page-header__title q-page-header__title--gradient" style="margin-top:8px;">
 <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
 </h1>
 <p class="q-page-header__subtitle">
 <?php echo htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8'); ?> — Yönetim Paneli
 </p>
 </div>
 <?php if ($logo): ?>
 <div class="flex-shrink-0">
 <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="İşletme Logosu" class="q-card__logo">
 </div>
 <?php endif; ?>
 </div>
 </header>

 <!-- ===== STATS GRID (gradient + hover-lift) ===== -->
 <?php
 $statsData = [
 'orders' => $recent_orders ?? [],
 'revenue' => $financial_summary['total_revenue'] ?? 0,
 'staff' => $staff_count ?? 0,
 'active_orders' => $active_orders ?? 0
 ];
 echo \App\Views\BusinessAdmin\StatsCardsPartial::render($statsData);
 ?>

 <!-- ===== RECENT ORDERS (glass + amber hover table) ===== -->
 <?php
 $ordersData = [
 'orders' => $recent_orders ?? [],
 'limit' => 5
 ];
 echo \App\Views\BusinessAdmin\RecentOrdersTablePartial::render($ordersData);
 ?>

 <!-- ===== QUICK ACTIONS (gradient icon, hover-lift) ===== -->
 <section class="q-card q-card--glass q-card--pad q-card--elevated stagger-item" aria-label="Hızlı erişim menüsü">
 <div class="q-card__header">
 <h2 class="q-card__title">
 <svg class="w-5 h-5" style="color:var(--color-brand-accent);margin-right:8px;vertical-align:middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
 Hızlı Erişim
 </h2>
 </div>
 <?php
 echo \App\Views\Components\QuickAction::renderMultiple(
 \App\Views\Components\QuickAction::businessMenu()
 );
 ?>
 </section>

 </div>
</div>
