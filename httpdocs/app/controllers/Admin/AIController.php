<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

/**
 * AI Önerileri Controller v3
 *
 * Her insight artık yapılandırılmış formatta:
 * - category: "Gelir" | "Gider" | "Performans" | "Ürün" | "Fire" | "Müşteri" | "Menü" | "Personel"
 * - title: Kısa, etkili başlık
 * - metric: Ölçülebilir değer (örn: "₺45.000", "%18", "12 saat")
 * - text: Açıklayıcı tek cümle
 * - action: Ne yapılması gerektiği
 * - impact: Etki (yüksek/orta/düşük)
 * - icon, tone: Görsel
 */
class AIController extends Controller {
 protected $orderService;
 protected $financeService;
 protected $customerService;
 protected $wasteService;
 protected $shiftService;
 protected $savedInsightRepo;

 /** Feed batch TTL — dashboard önerileri bu süre sonra yenilenir (saniye). */
 private const FEED_TTL_SECONDS = 900;
 /** Max insight rows returned to dashboard feed. */
 private const FEED_ROW_LIMIT = 4;

 public function __construct() {
 parent::__construct();
 $this->orderService = \App\Core\DependencyFactory::getOrderService();
 $this->financeService = \App\Core\DependencyFactory::getFinanceService();
 $this->customerService = \App\Core\DependencyFactory::getCustomerService();
 $this->wasteService = \App\Core\DependencyFactory::getWasteRecordService();
 $this->shiftService = \App\Core\DependencyFactory::getShiftService();
 $this->savedInsightRepo = new \App\Repositories\BusinessAiSavedInsightRepository(
 \App\Core\DependencyFactory::getDatabase()
 );
 }

 public function getAIInsights() {
 if (!$this->hasPermission('dashboard.analytics')) {
 $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
 return;
 }

 $ctx = $this->resolveSaveContext();
 if (!$ctx) {
 $this->apiResponse(['success' => false, 'message' => 'Tenant context missing'], 403);
 return;
 }

 try {
 $queryParams = \App\Core\RequestParser::getQueryParams();
 $mode = strtolower((string)($queryParams['mode'] ?? 'category'));
 $category = $queryParams['category'] ?? 'revenue';
 $forceRefresh = filter_var($queryParams['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

 $range = strtolower((string)($queryParams['range'] ?? 'month'));
 $startParam = $queryParams['start_date'] ?? null;
 $endParam = $queryParams['end_date'] ?? null;

 [$startDate, $endDate, $queryStart, $queryEnd] = $this->resolveDashboardWindow(
 $range,
 is_string($startParam) ? $startParam : null,
 is_string($endParam) ? $endParam : null,
 $queryParams
 );

 if ($forceRefresh) {
 $rl = \App\Services\RateLimiter::checkEndpoint(
 'ai-insights-refresh',
 $ctx['business_id'] . ':' . $ctx['user_id'],
 8,
 60
 );
 if (!$rl['allowed']) {
 $this->apiResponse([
 'success' => false,
 'message' => 'Too many refresh requests',
 'retry_after' => $rl['retry_after'],
 ], 429);
 return;
 }
 }

 $payload = $this->buildPayload($startDate, $endDate, $queryStart, $queryEnd, $range);

 if ($mode === 'feed') {
 $cacheKey = 'ai_feed:' . $ctx['business_id'] . ':' . $range . ':' . $startDate . ':' . $endDate;
 $feed = null;
 if (!$forceRefresh) {
 $cached = \App\Core\Cache::get($cacheKey);
 if (is_array($cached) && !empty($cached['insights'])) {
 $feed = $cached;
 }
 }
 if ($feed === null) {
 $feed = $this->generateFeedInsights($payload, $startDate, $endDate, $range);
 $feed['insights'] = array_map([$this, 'sanitizeFeedInsight'], $feed['insights']);
 $feed['insights'] = array_slice($feed['insights'], 0, self::FEED_ROW_LIMIT);
 \App\Core\Cache::set($cacheKey, $feed, self::FEED_TTL_SECONDS);
 }
 $savedIds = $this->getSavedInsightIdsForCurrentUser();
 $this->apiResponse([
 'success' => true,
 'mode' => 'feed',
 'insights' => $feed['insights'],
 'batch_id' => $feed['batch_id'],
 'expires_at' => $feed['expires_at'],
 'source_label' => 'Veri tabanlı öneriler',
 'saved_ids' => $savedIds,
 'range' => ['start' => $startDate, 'end' => $endDate, 'preset' => $range],
 ]);
 return;
 }

 // Legacy single-category mode (backward compatible)
 $insights = array_map([$this, 'sanitizeFeedInsight'], $this->generateFallbackInsights($payload, $category));

 $this->apiResponse([
 'success' => true,
 'category' => $category,
 'insights' => $insights,
 'quick_stats' => $this->buildQuickStats($payload, $category),
 'range' => ['start' => $startDate, 'end' => $endDate, 'preset' => $range],
 ]);
 } catch (\Exception $e) {
 \App\Core\Logger::error('AI insights error: ' . $e->getMessage());
 $this->toastNotificationService->sendApiResponse('error', 'notifications.error.ai_analysis_failed', [], 500);
 }
 }

 /**
 * Dashboard ile aynı tarih penceresi — business-day aware.
 *
 * @return array{0:string,1:string,2:string,3:string}
 */
 private function resolveDashboardWindow(
 string $rangeKey,
 ?string $startOverride,
 ?string $endOverride,
 array $queryParams = []
 ): array {
 $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
 $businessRange = $settingsService->getBusinessDateRange();
 $today = $businessRange['date'];

 if ($startOverride && $endOverride && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startOverride) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endOverride)) {
 $startDate = $startOverride;
 $endDate = $endOverride;
 } else {
 [$startDate, $endDate] = $this->resolveDashboardRangeDates($rangeKey, $today, $queryParams);
 }

 if ($rangeKey === 'today') {
 $queryStart = $businessRange['start_datetime'];
 $queryEnd = $businessRange['end_datetime'];
 } else {
 $queryStart = $startDate . ' 00:00:00';
 $queryEnd = $endDate . ' 23:59:59';
 }

 return [$startDate, $endDate, $queryStart, $queryEnd];
 }

 /**
 * @return array{0:string,1:string}
 */
 private function resolveDashboardRangeDates(string $rangeKey, string $today, array $queryParams = []): array {
 switch ($rangeKey) {
 case 'week':
 return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
 case 'month':
 return [date('Y-m-01'), date('Y-m-t')];
 case '3months':
 return [date('Y-m-d', strtotime('-3 months')), $today];
 case '6months':
 return [date('Y-m-d', strtotime('-6 months')), $today];
 case '9months':
 return [date('Y-m-d', strtotime('-9 months')), $today];
 case 'year':
 return [date('Y-01-01'), date('Y-12-31')];
 case 'custom':
 return [
 !empty($queryParams['start_date']) ? (string)$queryParams['start_date'] : $today,
 !empty($queryParams['end_date']) ? (string)$queryParams['end_date'] : $today,
 ];
 case 'today':
 default:
 return [$today, $today];
 }
 }

 private function buildPayload(string $startDate, string $endDate, string $queryStart, string $queryEnd, string $rangeKey): array {
 $startDt = $queryStart;
 $endDt = $queryEnd;

 // Dashboard ile aynı metrik: datetime aralığı + ödeme durumu filtresi
 $revenue = (float)$this->orderService->getDailyRevenueByDatetimeRange($startDt, $endDt);
 $expenses = (float)$this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
 $topItems = $this->orderService->getTopSellingItems(8, $startDt, $endDt);
 $avgOrderValue = (float)$this->orderService->calculateAvgOrderValue($startDt, $endDt);
 $revenueByCat = $this->orderService->getRevenueByCategory($startDate, $endDate);
 $hourlySales = $this->orderService->getHourlySales($startDate, $endDate);

 $periodDays = max(1, (int)floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
 $prevStart = date('Y-m-d', strtotime("-{$periodDays} days", strtotime($startDate)));
 $prevEnd = date('Y-m-d', strtotime("-{$periodDays} days", strtotime($endDate)));
 $prevRevenue = (float)$this->orderService->getDailyRevenueByDatetimeRange($prevStart . ' 00:00:00', $prevEnd . ' 23:59:59');
 $revenueChangePct = $prevRevenue > 0
 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1)
 : ($revenue > 0 ? 100.0 : 0.0);

 $orders = $this->orderService->getOrdersByDatetimeRange($startDt, $endDt);
 $orders = is_array($orders) ? $orders : [];
 $cancelledStatus = \App\Core\Helpers\ConstantsHelper::getOrderStatus('CANCELLED');
 $totalOrders = count(array_filter($orders, fn($o) => ($o['status'] ?? '') !== $cancelledStatus));
 $wasteRecords = [];
 try {
 $wasteTotal = (float)$this->wasteService->getTotalWasteByDateRange($startDate, $endDate);
 $wasteRecords = $this->wasteService->getByDateRange($startDate, $endDate);
 } catch (\Throwable $e) {}

 $unpaidInvoices = [];
 $unpaidTotal = 0.0;
 $expByCategory = [];
 try {
 $unpaidInvoices = $this->financeService->getUnpaidInvoices();
 $unpaidTotal = (float)$this->financeService->getTotalUnpaidInvoices();
 $expensesAll = $this->financeService->getExpensesByDateRange($startDate, $endDate);
 foreach ($expensesAll as $e) {
 $cat = $e['category'] ?? 'Diğer';
 $expByCategory[$cat] = ($expByCategory[$cat] ?? 0) + (float)($e['amount'] ?? 0);
 }
 } catch (\Throwable $e) {}

 $openShifts = [];
 try { $openShifts = $this->shiftService->getOpenShifts(); } catch (\Throwable $e) {}

 $activeCustomers = 0;
 try {
 $perf = $this->customerService->getBusinessPerformanceAnalytics();
 $activeCustomers = (int)($perf['active_customers'] ?? $perf['total_customers'] ?? 0);
 } catch (\Throwable $e) {}

 return [
 'start_date' => $startDate,
 'end_date' => $endDate,
 'range_key' => $rangeKey,
 'period_days' => $periodDays,
 'revenue' => $revenue,
 'prev_revenue' => $prevRevenue,
 'revenue_change_pct' => $revenueChangePct,
 'total_orders' => $totalOrders,
 'expenses' => $expenses,
 'profit' => $revenue - $expenses,
 'profit_margin' => $revenue > 0 ? round((($revenue - $expenses) / $revenue) * 100, 1) : 0,
 'avg_order_value' => $avgOrderValue,
 'top_items' => $topItems,
 'revenue_by_cat' => $revenueByCat,
 'hourly_sales' => $hourlySales,
 'waste_total' => $wasteTotal,
 'waste_records' => $wasteRecords,
 'unpaid_invoices' => $unpaidInvoices,
 'unpaid_total' => $unpaidTotal,
 'exp_by_category' => $expByCategory,
 'open_shifts' => $openShifts,
 'active_customers' => $activeCustomers,
 ];
 }

 /**
 * Helper: yapılandırılmış insight objesi oluştur
 */
 private function card(string $cat, string $title, string $metric, string $text, string $action, string $impact, string $tone, string $icon): array {
 return [
 'category' => $cat,
 'title' => $title,
 'metric' => $metric,
 'text' => $text,
 'action' => $action,
 'impact' => $impact,
 'tone' => $tone,
 'icon' => $icon,
 ];
 }

 private function generateFallbackInsights(array $data, string $category): array {
 switch ($category) {
 case 'revenue': return $this->insightRevenue($data);
 case 'expense': return $this->insightExpense($data);
 case 'performance': return $this->insightPerformance($data);
 case 'product': return $this->insightProduct($data);
 case 'waste': return $this->insightWaste($data);
 case 'customer': return $this->insightCustomer($data);
 case 'menu': return $this->insightMenu($data);
 case 'staff': return $this->insightStaff($data);
 default: return [$this->card('Sistem', 'Kategori seçin', '—', 'Bir analiz kategorisi seçerek başlayın.', '', 'düşük', 'neutral', 'info')];
 }
 }

 /* ========================================================
 GELİR
 ======================================================== */
 private function insightRevenue(array $d): array {
 $r = (float)$d['revenue'];
 $e = (float)$d['expenses'];
 $p = $r - $e;
 $m = (float)$d['profit_margin'];
 $aov = (float)$d['avg_order_value'];
 $byCat = $d['revenue_by_cat'] ?? [];
 $hourly = $d['hourly_sales'] ?? [];
 $cards = [];

 if ($r <= 0) {
 return [$this->card('Gelir', 'Veri Bulunamadı', '₺0', 'Bu tarih aralığında gelir kaydı yok.', 'POS ve sipariş kayıtlarını kontrol edin.', 'yüksek', 'warning', 'alert')];
 }

 // 1. Genel ciro durumu
 $cards[] = $this->card(
 'Gelir',
 $m >= 25 ? 'Kâr Marjı Sağlıklı' : ($m >= 10 ? 'Kâr Marjı Orta' : 'Kâr Marjı Düşük'),
 '%' . $m,
 sprintf('Bu dönem ₺%s ciro, ₺%s gider elde ettiniz. Net kâr ₺%s.', number_format($r, 0, ',', '.'), number_format($e, 0, ',', '.'), number_format($p, 0, ',', '.')),
 $m >= 25 ? 'Mevcut stratejiyi sürdürün.' : 'Menü fiyatlarını %5-8 gözden geçirin veya porsiyon maliyetini düşürün.',
 $m >= 25 ? 'düşük' : 'yüksek',
 $m >= 25 ? 'success' : ($m >= 10 ? 'info' : 'warning'),
 $m >= 25 ? 'trending-up' : 'trending-down'
 );

 // 2. Sepet ortalaması
 if ($aov > 0) {
 $cards[] = $this->card(
 'Gelir',
 'Sepet Ortalaması',
 '₺' . number_format($aov, 0, ',', '.'),
 'Müşteri başına ortalama harcama tutarı.',
 sprintf('"Yanına ekle" ile +₺%s hedefleyin (%%15 artış).', number_format($aov * 0.15, 0, ',', '.')),
 'orta',
 'info',
 'shopping-bag'
 );
 }

 // 3. En güçlü kategori
 if (!empty($byCat)) {
 usort($byCat, fn($a, $b) => ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0));
 $topCat = $byCat[0];
 $topCatName = $topCat['name'] ?? $topCat['category'] ?? '—';
 $topCatRev = (float)($topCat['revenue'] ?? 0);
 $share = $r > 0 ? round(($topCatRev / $r) * 100, 1) : 0;
 $cards[] = $this->card(
 'Gelir',
 $share > 50 ? 'Tek Kategori Riski' : 'Lider Kategori',
 '%' . $share,
 sprintf('"%s" ₺%s ciro üretiyor.', $topCatName, number_format($topCatRev, 0, ',', '.')),
 $share > 50 ? 'Bu kategoriye bağımlılık riskli. 2-3 alt ürün ekleyin.' : 'Bu kategoride 1-2 çeşit daha ekleyin.',
 $share > 50 ? 'yüksek' : 'orta',
 $share > 50 ? 'warning' : 'success',
 'star'
 );
 }

 // 4. Saatlik yoğunluk
 if (!empty($hourly)) {
 $peakHour = 0; $peakAmt = 0;
 foreach ($hourly as $h) {
 $amt = (float)($h['total'] ?? $h['revenue'] ?? 0);
 if ($amt > $peakAmt) { $peakAmt = $amt; $peakHour = (int)($h['hour'] ?? 0); }
 }
 if ($peakHour > 0) {
 $cards[] = $this->card(
 'Gelir',
 'Altın Saat',
 sprintf('%02d:00', $peakHour),
 sprintf('Bu saat ₺%s ciro ile en yoğun dilim.', number_format($peakAmt, 0, ',', '.')),
 'Garson vardiyasını 30 dk önce başlatın, mutfağı buna göre hazırlayın.',
 'orta',
 'info',
 'clock'
 );
 }

 // En zayıf saat
 $minHour = 0; $minAmt = PHP_FLOAT_MAX;
 foreach ($hourly as $h) {
 $amt = (float)($h['total'] ?? $h['revenue'] ?? 0);
 if ($amt < $minAmt && (int)($h['hour'] ?? 0) > 0) { $minAmt = $amt; $minHour = (int)($h['hour'] ?? 0); }
 }
 if ($minHour > 0 && $minAmt < $peakAmt * 0.3) {
 $cards[] = $this->card(
 'Gelir',
 'Sessiz Saat',
 sprintf('%02d:00', $minHour),
 sprintf('Bu saat sadece ₺%s ciro üretiyor.', number_format($minAmt, 0, ',', '.')),
 sprintf('"%02d:00-\'%02d:00 arası %%20 indirim" happy hour kampanyası başlatın.', $minHour, $minHour + 2),
 'yüksek',
 'warning',
 'moon'
 );
 }
 }

 // 5. Gider/ciro oranı
 if ($e > 0 && $r > 0) {
 $ratio = round(($e / $r) * 100, 1);
 $cards[] = $this->card(
 'Gelir',
 $ratio > 50 ? 'Gider/ Ciro Yüksek' : 'Gider/ Ciro Sağlıklı',
 '%' . $ratio,
 sprintf('Giderler cironun %%%s\'ini oluşturuyor. (Sağlıklı eşik: <%%50)', $ratio),
 $ratio > 50 ? 'Tedarikçi pazarlığı ve porsiyon kontrolü acil.' : 'Mevcut dengeselliği koruyun.',
 $ratio > 50 ? 'yüksek' : 'düşük',
 $ratio > 50 ? 'danger' : 'success',
 $ratio > 50 ? 'flame' : 'check'
 );
 }

 // 6. Bugün vs dün — yalnızca "today" aralığında
 $rangeKey = (string)($d['range_key'] ?? 'month');
 if ($rangeKey === 'today') {
 $todayRev = (float)$this->orderService->getDailyRevenue(date('Y-m-d'));
 $yestRev = (float)$this->orderService->getDailyRevenue(date('Y-m-d', strtotime('-1 day')));
 if ($yestRev > 0) {
 $delta = round((($todayRev - $yestRev) / $yestRev) * 100, 1);
 $cards[] = $this->card(
 'Gelir',
 $delta >= 0 ? 'Bugün Dünden İyi' : 'Bugün Geride',
 ($delta >= 0 ? '+' : '') . '%' . $delta,
 sprintf('Bugün ₺%s, dün ₺%s.', number_format($todayRev, 0, ',', '.'), number_format($yestRev, 0, ',', '.')),
 $delta >= 0 ? 'Bu tempo haftalık hedefi geçecek.' : 'Öğle kampanyası ile açığı kapatın.',
 'orta',
 $delta >= 0 ? 'success' : 'warning',
 $delta >= 0 ? 'arrow-up' : 'arrow-down'
 );
 }
 }

 // 7. Dönem projeksiyonu — yalnızca ay+ uzun dönemlerde, günlük ortalamadan
 $days = max(1, (int)($d['period_days'] ?? 1));
 $dailyAvg = $r / $days;
 if (in_array($rangeKey, ['month', '3months', '6months', '9months', 'year'], true) && $dailyAvg > 0) {
 $monthlyEstimate = $dailyAvg * 30;
 $cards[] = $this->card(
 'Gelir',
 'Aylık Projeksiyon',
 '₺' . number_format($monthlyEstimate, 0, ',', '.'),
 sprintf('Seçili dönemde günlük ortalama ₺%s.', number_format($dailyAvg, 0, ',', '.')),
 sprintf('Hedef için günlük ₺%s satış gerekir.', number_format($dailyAvg * 1.1, 0, ',', '.')),
 'orta',
 'info',
 'target'
 );
 }

 return $cards;
 }

 /* ========================================================
 GİDER
 ======================================================== */
 private function insightExpense(array $d): array {
 $e = (float)$d['expenses'];
 $r = (float)$d['revenue'];
 $unpaid = (float)$d['unpaid_total'];
 $unpaidList = $d['unpaid_invoices'] ?? [];
 $byCat = $d['exp_by_category'] ?? [];
 $cards = [];

 if ($e <= 0 && $unpaid <= 0) {
 return [$this->card('Gider', 'Gider Kaydı Yok', '₺0', 'Bu dönemde gider veya açık fatura yok.', 'Tedarikçi kayıtlarını kontrol edin.', 'düşük', 'info', 'info')];
 }

 $ratio = $r > 0 ? round(($e / $r) * 100, 1) : 0;
 $cards[] = $this->card(
 'Gider',
 $ratio > 50 ? 'Giderler Yüksek' : 'Gider Kontrolü',
 '%' . $ratio,
 sprintf('Dönem gideri ₺%s.', number_format($e, 0, ',', '.')),
 $ratio > 50 ? 'Sektör eşiği aşıldı, aksiyon gerekli.' : 'Sağlıklı aralıkta.',
 $ratio > 50 ? 'yüksek' : 'düşük',
 $ratio > 50 ? 'danger' : 'info',
 'wallet'
 );

 if (!empty($byCat)) {
 arsort($byCat);
 $topName = array_key_first($byCat);
 $topVal = (float)$byCat[$topName];
 $share = $e > 0 ? round(($topVal / $e) * 100, 1) : 0;
 $cards[] = $this->card(
 'Gider',
 $share > 40 ? 'Tek Kalemde Yoğunlaşma' : 'Büyük Gider Kalemi',
 '%' . $share,
 sprintf('"%s" kalemi ₺%s.', $topName, number_format($topVal, 0, ',', '.')),
 $share > 40 ? 'Toplu alım veya alternatif tedarikçi görüşmesi yapın.' : 'Dağılım dengeli.',
 $share > 40 ? 'yüksek' : 'orta',
 $share > 40 ? 'warning' : 'info',
 'tag'
 );
 }

 if ($unpaid > 0) {
 $cards[] = $this->card(
 'Gider',
 'Açık Faturalar',
 '₺' . number_format($unpaid, 0, ',', '.'),
 sprintf('%d adet fatura ödenmedi.', count($unpaidList)),
 'Vadesi yaklaşanları önceliklendirin.',
 'yüksek',
 $unpaid > $e * 0.3 ? 'danger' : 'warning',
 'file-text'
 );
 } else {
 $cards[] = $this->card('Gider', 'Faturalar Güncel', '₺0', 'Açık fatura yok.', 'Mevcut ödeme düzenini koruyun.', 'düşük', 'success', 'check');
 }

 if (!empty($unpaidList)) {
 usort($unpaidList, fn($a, $b) => strtotime($a['due_date'] ?? 'now') <=> strtotime($b['due_date'] ?? 'now'));
 $oldest = $unpaidList[0];
 $daysOver = (int)((time() - strtotime($oldest['due_date'] ?? 'now')) / 86400);
 $supplierName = $oldest['supplier_name'] ?? 'Tedarikçi';
 $amount = (float)($oldest['amount'] ?? 0);
 if ($daysOver > 0) {
 $cards[] = $this->card(
 'Gider',
 'Vadesi Geçen Fatura',
 $daysOver . ' gün',
 sprintf('"%s" ₺%s tutarındaki faturası vadesini geçmiş.', $supplierName, number_format($amount, 0, ',', '.')),
 'Bugün tedarikçiyle iletişime geçin.',
 'yüksek',
 'danger',
 'alert'
 );
 } else {
 $cards[] = $this->card(
 'Gider',
 'Yaklaşan Vade',
 abs($daysOver) . ' gün',
 sprintf('"%s" faturası %d gün içinde vadesi dolacak.', $supplierName, abs($daysOver)),
 'Ödeme planını bugün gözden geçirin.',
 'orta',
 'info',
 'clock'
 );
 }
 }

 $expenseTrend = [];
 try { $expenseTrend = $this->financeService->getExpenseTrend(7); } catch (\Throwable $ex) {}
 if (!empty($expenseTrend) && count($expenseTrend) >= 2) {
 $last = (float)(end($expenseTrend)['amount'] ?? 0);
 $first = (float)($expenseTrend[0]['amount'] ?? 0);
 if ($first > 0) {
 $change = round((($last - $first) / $first) * 100, 1);
 $cards[] = $this->card(
 'Gider',
 $change > 10 ? 'Gider Artış Trendi' : 'Gider Stabil',
 ($change >= 0 ? '+' : '') . '%' . $change,
 sprintf('Son 7 günde gider %s.', $change > 0 ? 'yükseldi' : 'düştü'),
 $change > 10 ? 'Beklenmedik artışı kontrol edin.' : 'Stabil seyir.',
 $change > 10 ? 'yüksek' : 'düşük',
 $change > 10 ? 'warning' : 'info',
 $change > 0 ? 'trending-up' : 'trending-down'
 );
 }
 }

 $supplierCount = 0;
 try { $supplierCount = count($this->financeService->getAllSuppliers()); } catch (\Throwable $ex) {}
 $cards[] = $this->card(
 'Gider',
 $supplierCount < 3 ? 'Tedarikçi Riski' : 'Tedarikçi Tabanı',
 $supplierCount . ' tedarikçi',
 sprintf('%d farklı tedarikçiyle çalışıyorsunuz.', $supplierCount),
 $supplierCount < 3 ? 'En az 2 alternatif tedarikçi geliştirin.' : 'Taban yeterli.',
 $supplierCount < 3 ? 'yüksek' : 'düşük',
 $supplierCount < 3 ? 'warning' : 'info',
 'users'
 );

 if (!empty($byCat) && $r > 0) {
 $foodCost = ($byCat['Gıda'] ?? $byCat['Malzeme'] ?? $byCat['food'] ?? 0);
 $foodRatio = $foodCost > 0 ? round(($foodCost / $r) * 100, 1) : 0;
 if ($foodRatio > 0) {
 $monthlySave = $r * 0.01 * 30;
 $cards[] = $this->card(
 'Gider',
 $foodRatio > 35 ? 'Gıda Maliyeti Yüksek' : 'Gıda Maliyeti Hedefte',
 '%' . $foodRatio,
 sprintf('Gıda maliyeti ₺%s (hedef: %%28-32).', number_format($foodCost, 0, ',', '.')),
 $foodRatio > 35 ? sprintf('%%1 düşürmek ≈ ₺%s/ay tasarruf sağlar.', number_format($monthlySave, 0, ',', '.')) : 'Mevcut seviyeyi koruyun.',
 $foodRatio > 35 ? 'yüksek' : 'düşük',
 $foodRatio > 35 ? 'warning' : 'success',
 'leaf'
 );
 }
 }

 return $cards;
 }

 /* ========================================================
 PERFORMANS
 ======================================================== */
 private function insightPerformance(array $d): array {
 $r = (float)$d['revenue'];
 $e = (float)$d['expenses'];
 $aov = (float)$d['avg_order_value'];
 $hourly = $d['hourly_sales'] ?? [];
 $m = (float)$d['profit_margin'];
 $cards = [];

 if ($r <= 0) {
 return [$this->card('Performans', 'Veri Yetersiz', '₺0', 'Tarih aralığını genişletin veya sipariş kaydı girin.', 'En az 1 haftalık veriyle anlamlı analiz çıkar.', 'düşük', 'warning', 'alert')];
 }

 $cards[] = $this->card(
 'Performans',
 $m >= 25 ? 'Üst Segment Verimlilik' : ($m >= 15 ? 'Orta Verimlilik' : 'Düşük Verimlilik'),
 '%' . $m,
 'Kâr marjı sektör karşılaştırmasında.',
 $m < 20 ? 'Menü fiyatlarını %5-8 artırın, porsiyon maliyetini düşürün.' : 'Sürdürülebilir seviyedesiniz.',
 $m < 20 ? 'yüksek' : 'düşük',
 $m >= 25 ? 'success' : ($m >= 15 ? 'info' : 'warning'),
 'activity'
 );

 if ($aov > 0) {
 $cards[] = $this->card(
 'Performans',
 'Sepet Upsell Fırsatı',
 '₺' . number_format($aov * 0.20, 0, ',', '.'),
 sprintf('Mevcut sepet ₺%s. Yan ürün önerisi ile +%%12-15 artış mümkün.', number_format($aov, 0, ',', '.')),
 '"Yanında ₺' . number_format($aov * 0.20, 0, ',', '.') . ' içecek/desert öner" popup\'ı aktif edin.',
 'yüksek',
 'info',
 'trending-up'
 );
 }

 if (!empty($hourly)) {
 $activeHours = count(array_filter($hourly, fn($h) => (float)($h['total'] ?? 0) > 0));
 $avgPerHour = $activeHours > 0 ? $r / $activeHours : 0;
 $cards[] = $this->card(
 'Performans',
 'Saatlik Verimlilik',
 '₺' . number_format($avgPerHour, 0, ',', '.'),
 sprintf('%d aktif saat üzerinden ortalama saat başı ciro.', $activeHours),
 $avgPerHour < 500 ? 'Masa çevrim süresini kısaltın.' : 'Yoğunluk verimli kullanılıyor.',
 $avgPerHour < 500 ? 'yüksek' : 'düşük',
 'info',
 'clock'
 );
 }

 $ratio = $r > 0 ? round(($e / $r) * 100, 1) : 0;
 $cards[] = $this->card(
 'Performans',
 'Operasyonel Verimlilik',
 '%' . $ratio,
 'Gider/ciro oranı.',
 $ratio > 50 ? 'Hedefin altında kalınmalı.' : 'Hedef aralıktasınız.',
 $ratio > 50 ? 'yüksek' : 'düşük',
 $ratio > 50 ? 'danger' : 'success',
 'pie-chart'
 );

 if (!empty($hourly)) {
 usort($hourly, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
 $peak = $hourly[0];
 $cards[] = $this->card(
 'Performans',
 'En Verimli Saat',
 sprintf('%02d:00', (int)($peak['hour'] ?? 0)),
 sprintf('Bu saat ₺%s ciro.', number_format((float)($peak['total'] ?? 0), 0, ',', '.')),
 'Bu saate premium upsell uygulayın, indirim değil.',
 'orta',
 'success',
 'zap'
 );
 }

 $openShifts = $d['open_shifts'] ?? [];
 $cards[] = $this->card(
 'Performans',
 'Saha Durumu',
 count($openShifts) . ' vardiya',
 'Açık vardiya sayısı.',
 count($openShifts) > 0 ? 'Personel sahada, takip aktif.' : 'Hiç açık vardiya yok, satış girişi yapılmamış olabilir.',
 'orta',
 count($openShifts) > 0 ? 'success' : 'warning',
 'user-check'
 );

 $ac = (int)$d['active_customers'];
 if ($ac > 0) {
 $arpu = $r / $ac;
 $cards[] = $this->card(
 'Performans',
 'Müşteri Başı Ciro',
 '₺' . number_format($arpu, 0, ',', '.'),
 sprintf('%d aktif müşteri üzerinden.', $ac),
 sprintf('₺%s üzeri hedefleyin (%%20 artış).', number_format($arpu * 1.2, 0, ',', '.')),
 'orta',
 'info',
 'user'
 );
 }

 return $cards;
 }

 /* ========================================================
 ÜRÜN
 ======================================================== */
 private function insightProduct(array $d): array {
 $top = $d['top_items'] ?? [];
 $r = (float)$d['revenue'];
 $cards = [];

 if (empty($top)) {
 return [$this->card('Ürün', 'Satış Verisi Yok', '0 adet', 'Bu dönemde ürün satışı yok.', 'Menüyü gözden geçirin.', 'düşük', 'warning', 'box')];
 }

 $lead = $top[0];
 $leadRev = (float)($lead['revenue'] ?? 0);
 $leadCnt = (int)($lead['count'] ?? 0);
 $leadName = $lead['name'] ?? '—';
 $unitPrice = $leadCnt > 0 ? $leadRev / $leadCnt : 0;
 $cards[] = $this->card(
 'Ürün',
 'Yıldız Ürün',
 $leadCnt . ' adet',
 sprintf('"%s" ₺%s ciro üretti.', $leadName, number_format($leadRev, 0, ',', '.')),
 sprintf('Yanına +₺%s\'lik yan ürün önerin.', number_format($unitPrice * 0.30, 0, ',', '.')),
 'yüksek',
 'success',
 'star'
 );

 $byUnitRev = $top;
 usort($byUnitRev, function($a, $b) {
 $aU = (int)($a['count'] ?? 1) > 0 ? ((float)($a['revenue'] ?? 0)) / (int)$a['count'] : 0;
 $bU = (int)($b['count'] ?? 1) > 0 ? ((float)($b['revenue'] ?? 0)) / (int)$b['count'] : 0;
 return $bU <=> $aU;
 });
 $topUnit = $byUnitRev[0];
 $unitPrice2 = (int)($topUnit['count'] ?? 1) > 0 ? (float)$topUnit['revenue'] / (int)$topUnit['count'] : 0;
 $cards[] = $this->card(
 'Ürün',
 'En Yüksek Birim Getiri',
 '₺' . number_format($unitPrice2, 0, ',', '.'),
 sprintf('"%s" birim başına en yüksek getiri.', $topUnit['name'] ?? '—'),
 'Premium konumlandırma için fotoğraf yatırımı yapın.',
 'orta',
 'success',
 'gem'
 );

 $worst = end($top);
 $worstRev = (float)($worst['revenue'] ?? 0);
 if (count($top) >= 4) {
 $cards[] = $this->card(
 'Ürün',
 'Düşük Performans',
 (int)($worst['count'] ?? 0) . ' adet',
 sprintf('"%s" sadece ₺%s ciro.', $worst['name'] ?? '—', number_format($worstRev, 0, ',', '.')),
 '30 gün içinde iyileşmezse menüden çıkarın veya combo\'ya ekleyin.',
 'yüksek',
 'warning',
 'trending-down'
 );
 }

 $top3Rev = 0;
 for ($i = 0; $i < min(3, count($top)); $i++) {
 $top3Rev += (float)($top[$i]['revenue'] ?? 0);
 }
 $top3Share = $r > 0 ? round(($top3Rev / $r) * 100, 1) : 0;
 $cards[] = $this->card(
 'Ürün',
 $top3Share > 60 ? 'Top 3 Yoğunlaşması' : 'Menü Dengesi',
 '%' . $top3Share,
 'Top 3 ürünün ciroya katkısı.',
 $top3Share > 60 ? 'Uzun kuyruk ürünleri öne çıkarın.' : 'Dağılım makul.',
 $top3Share > 60 ? 'yüksek' : 'düşük',
 $top3Share > 60 ? 'warning' : 'info',
 'bar-chart'
 );

 $hidden = null;
 foreach ($top as $item) {
 $cnt = (int)($item['count'] ?? 0);
 $rev = (float)($item['revenue'] ?? 0);
 if ($cnt > 0 && $cnt < 30 && $rev / $cnt > $unitPrice2 * 0.7) {
 $hidden = $item; break;
 }
 }
 if ($hidden) {
 $cards[] = $this->card(
 'Ürün',
 'Gizli Şampiyon',
 (int)($hidden['count'] ?? 0) . ' adet',
 sprintf('"%s" az satılıyor ama ₺%s/birim getiri var.', $hidden['name'] ?? '—', number_format((float)$hidden['revenue'] / max(1, (int)$hidden['count']), 0, ',', '.')),
 'Fotoğraf yatırımı + menüde üst konuma taşıyın.',
 'yüksek',
 'info',
 'eye'
 );
 }

 if (count($top) >= 2) {
 $comboTotal = (float)($top[0]['revenue'] ?? 0) + (float)($top[1]['revenue'] ?? 0);
 $cards[] = $this->card(
 'Ürün',
 'Combo Önerisi',
 '%15 indirim',
 sprintf('"%s" + "%s" combo halinde.', $top[0]['name'] ?? '—', $top[1]['name'] ?? '—'),
 sprintf('"2\'si ₺%s yerine ₺%s" kampanyası sepet ortalamasını +%%18 artırır.', number_format($comboTotal, 0, ',', '.'), number_format($comboTotal * 0.85, 0, ',', '.')),
 'yüksek',
 'info',
 'package'
 );
 }

 $totalCount = array_sum(array_map(fn($i) => (int)($i['count'] ?? 0), $top));
 $cards[] = $this->card(
 'Ürün',
 'Stok Tüketim Tahmini',
 $totalCount . ' adet',
 sprintf('Top %d üründe toplam satış.', count($top)),
 'Lider ürünlerin hammaddesini 1.5x sipariş edin.',
 'orta',
 'info',
 'truck'
 );

 $cards[] = $this->card(
 'Ürün',
 'Marj Kontrolü',
 '<%35',
 'Birim başı ₺150 altında ürünler için maliyet/fiyat oranı hedefi.',
 'Oranı %35\'i aşan ürünlerde porsiyon küçültün veya fiyat artırın.',
 'orta',
 'info',
 'percent'
 );

 return $cards;
 }

 /* ========================================================
 FIRE
 ======================================================== */
 private function insightWaste(array $d): array {
 $w = (float)$d['waste_total'];
 $r = (float)$d['revenue'];
 $records = $d['waste_records'] ?? [];
 $cards = [];

 if (empty($records) && $w <= 0) {
 return [$this->card('Fire', 'Fire Kaydı Yok', '₺0', 'Bu dönemde fire kaydı yok, mükemmel!', 'Mevcut kontrolleri sürdürün.', 'düşük', 'success', 'check')];
 }

 $wasteRatio = $r > 0 ? round(($w / $r) * 100, 2) : 0;
 $cards[] = $this->card(
 'Fire',
 $wasteRatio > 3 ? 'Fire Oranı Yüksek' : ($wasteRatio > 1.5 ? 'Fire Oranı Dikkat' : 'Fire Kontrolü'),
 '%' . $wasteRatio,
 sprintf('Toplam fire ₺%s.', number_format($w, 0, ',', '.')),
 $wasteRatio > 3 ? 'Sektör eşiği (%3) aşıldı, aksiyon gerekli.' : 'Sağlıklı aralıkta (<%3).',
 $wasteRatio > 3 ? 'yüksek' : 'düşük',
 $wasteRatio > 3 ? 'danger' : ($wasteRatio > 1.5 ? 'warning' : 'success'),
 $wasteRatio > 3 ? 'alert' : 'check'
 );

 $byItem = [];
 foreach ($records as $rec) {
 $key = $rec['ingredient_name'] ?? $rec['item_name'] ?? 'Bilinmeyen';
 $byItem[$key] = ($byItem[$key] ?? 0) + (float)($rec['cost'] ?? $rec['amount'] ?? 0);
 }
 if (!empty($byItem)) {
 arsort($byItem);
 $topWaste = array_key_first($byItem);
 $topWasteVal = (float)$byItem[$topWaste];
 $share = $w > 0 ? round(($topWasteVal / $w) * 100, 1) : 0;
 $cards[] = $this->card(
 'Fire',
 'En Çok Fire Veren',
 '%' . $share,
 sprintf('"%s" ₺%s fire.', $topWaste, number_format($topWasteVal, 0, ',', '.')),
 'Stok sipariş miktarını azaltın, FIFO kontrolü sıkılaştırın.',
 $share > 30 ? 'yüksek' : 'orta',
 $share > 30 ? 'warning' : 'info',
 'package-x'
 );
 }

 $byReason = [];
 foreach ($records as $rec) {
 $reason = $rec['reason'] ?? 'Diğer';
 $byReason[$reason] = ($byReason[$reason] ?? 0) + (float)($rec['cost'] ?? $rec['amount'] ?? 0);
 }
 if (!empty($byReason)) {
 arsort($byReason);
 $topReason = array_key_first($byReason);
 $topReasonVal = (float)$byReason[$topReason];
 $reasonAction = $topReason === 'Son kullanma' ? 'Sipariş miktarlarını porsiyona göre küçültün.' :
 ($topReason === 'Hazırlama hatası' ? 'Mutfak ekibine eğitim verin.' : 'Sebep bazlı aksiyon planı oluşturun.');
 $cards[] = $this->card(
 'Fire',
 'En Sık Sebep',
 '₺' . number_format($topReasonVal, 0, ',', '.'),
 sprintf('"%s" en sık fire sebebi.', $topReason),
 $reasonAction,
 'yüksek',
 'warning',
 'alert-circle'
 );
 }

 if ($w > 0) {
 $saveTarget = $w * 0.5;
 $cards[] = $this->card(
 'Fire',
 'Tasarruf Potansiyeli',
 '₺' . number_format($saveTarget, 0, ',', '.'),
 'Fireyi yarıya indirme potansiyeli.',
 sprintf('Bu ₺%s ekstra ciro demek.', number_format($saveTarget * 0.4, 0, ',', '.')),
 'yüksek',
 'success',
 'target'
 );
 }

 $recent7 = array_filter($records, function($rec) {
 $dt = strtotime($rec['created_at'] ?? $rec['date'] ?? 'now');
 return $dt >= strtotime('-7 days');
 });
 $recentTotal = array_sum(array_map(fn($r) => (float)($r['cost'] ?? $r['amount'] ?? 0), $recent7));
 $cards[] = $this->card(
 'Fire',
 'Son 7 Gün',
 '₺' . number_format($recentTotal, 0, ',', '.'),
 sprintf('%d fire kaydı.', count($recent7)),
 'Haftalık fire takip rutini başlatın.',
 'orta',
 $recentTotal > $w * 0.4 ? 'warning' : 'info',
 'calendar'
 );

 $byHour = [];
 foreach ($records as $rec) {
 $hr = (int)date('H', strtotime($rec['created_at'] ?? $rec['date'] ?? 'now'));
 $byHour[$hr] = ($byHour[$hr] ?? 0) + 1;
 }
 if (!empty($byHour)) {
 arsort($byHour);
 $peakHr = array_key_first($byHour);
 $cards[] = $this->card(
 'Fire',
 'Saat Yoğunluğu',
 sprintf('%02d:00', $peakHr),
 'Fire kayıtlarının en sık olduğu saat.',
 'Mesai sonu kontrol rutinini güçlendirin.',
 'orta',
 'info',
 'clock'
 );
 }

 $exp = (float)$d['expenses'];
 if ($exp > 0) {
 $ratio = round(($w / $exp) * 100, 2);
 $cards[] = $this->card(
 'Fire',
 'Fire/Gider Oranı',
 '%' . $ratio,
 sprintf('Fire giderin %%%s\'i.', $ratio),
 $ratio > 5 ? 'Hedefin çok üstünde, acil müdahale.' : 'Hedefin altında.',
 $ratio > 5 ? 'yüksek' : 'düşük',
 $ratio > 5 ? 'danger' : 'success',
 'percent'
 );
 }

 return $cards;
 }

 /* ========================================================
 MÜŞTERİ
 ======================================================== */
 private function insightCustomer(array $d): array {
 $r = (float)$d['revenue'];
 $ac = (int)$d['active_customers'];
 $aov = (float)$d['avg_order_value'];
 $cards = [];

 if ($ac === 0) {
 return [$this->card('Müşteri', 'Müşteri Verisi Yok', '0 kişi', 'Aktif müşteri kaydı yetersiz.', 'Müşteri kayıtlarını kontrol edin.', 'düşük', 'info', 'users')];
 }

 $arpu = $r / $ac;
 $cards[] = $this->card(
 'Müşteri',
 'Müşteri Başı Ciro',
 '₺' . number_format($arpu, 0, ',', '.'),
 sprintf('%d aktif müşteri, ortalama gelir.', $ac),
 $arpu < 500 ? 'Sadakat programı ile %20 artırılabilir.' : 'Güçlü segment.',
 $arpu < 500 ? 'yüksek' : 'düşük',
 $arpu >= 500 ? 'success' : 'info',
 'user'
 );

 if ($aov > 0) {
 $visitFreq = $aov > 0 ? $arpu / $aov : 0;
 $cards[] = $this->card(
 'Müşteri',
 'Ziyaret Sıklığı',
 number_format($visitFreq, 1) . ' kez',
 sprintf('Müşteri başına ortalama ziyaret (₺%s sepet üzerinden).', number_format($aov, 0, ',', '.')),
 'Ziyaret sıklığını artırmak için WhatsApp hatırlatması başlatın.',
 'orta',
 'info',
 'shopping-cart'
 );
 }

 $growthRevenue = $arpu * $ac * 0.10;
 $cards[] = $this->card(
 'Müşteri',
 '%10 Büyüme Potansiyeli',
 '₺' . number_format($growthRevenue, 0, ',', '.'),
 'Müşteri tabanını %10 büyütmek.',
 '"Arkadaşını getir, %20 indirim" kampanyası başlatın.',
 'yüksek',
 'info',
 'trending-up'
 );

 $cards[] = $this->card(
 'Müşteri',
 'Geri Kazanım',
 '%8-12',
 'Son 30 günde gelmeyen müşterilere WhatsApp kampanyası.',
 '"10% geri dönüş indirimi" mesajı gönderin.',
 'yüksek',
 'warning',
 'mail'
 );

 $cards[] = $this->card(
 'Müşteri',
 'VIP Segmenti',
 '%20 müşteri',
 'Üst %20 muhtemelen cironun %80\'ini üretiyor.',
 'Bu grubu VIP olarak işaretleyin, özel menü sunun.',
 'orta',
 'success',
 'crown'
 );

 $cards[] = $this->card(
 'Müşteri',
 'Sadakat Programı',
 '+%35 ziyaret',
 'Her 5. ziyarette 1 puan, 10 puanda 1 ücretsiz ürün.',
 'Dijital sadakat kartı aktif edin.',
 'yüksek',
 'info',
 'heart'
 );

 $cards[] = $this->card(
 'Müşteri',
 'İletişim Stratejisi',
 '+%18 öğle',
 'Pzt-Per 11:00-12:00 arası WhatsApp broadcast.',
 'Günün menüsünü bu saatte paylaşın.',
 'orta',
 'info',
 'message-circle'
 );

 return $cards;
 }

 /* ========================================================
 MENÜ
 ======================================================== */
 private function insightMenu(array $d): array {
 $top = $d['top_items'] ?? [];
 $r = (float)$d['revenue'];
 $cards = [];

 if (empty($top)) {
 return [$this->card('Menü', 'Menü Verisi Yok', '0 ürün', 'Aktif ürün bulunamadı.', 'En az bir ürün aktif olmalı.', 'düşük', 'warning', 'book-open')];
 }

 $lead = $top[0];
 $cards[] = $this->card(
 'Menü',
 'Yıldız Ürün',
 (int)($lead['count'] ?? 0) . ' adet',
 sprintf('"%s" ₺%s ciro.', $lead['name'] ?? '—', number_format((float)($lead['revenue'] ?? 0), 0, ',', '.')),
 'Yanında yüksek marjlı içecek combo önerin.',
 'yüksek',
 'success',
 'star'
 );

 $byCat = $d['revenue_by_cat'] ?? [];
 if (!empty($byCat)) {
 usort($byCat, fn($a, $b) => ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0));
 $topCat = $byCat[0];
 $share = $r > 0 ? round(((float)($topCat['revenue'] ?? 0) / $r) * 100, 1) : 0;
 $cards[] = $this->card(
 'Menü',
 $share > 60 ? 'Tek Kategori Riski' : 'Kategori Dengesi',
 '%' . $share,
 sprintf('"%s" cironun %%%s\'i.', $topCat['name'] ?? '—', $share),
 $share > 60 ? 'Diğer kategorilere yatırım yapın.' : 'Dengeli dağılım.',
 $share > 60 ? 'yüksek' : 'düşük',
 $share > 60 ? 'warning' : 'info',
 'layers'
 );
 }

 foreach ($top as $item) {
 $cnt = (int)($item['count'] ?? 0);
 $rev = (float)($item['revenue'] ?? 0);
 if ($cnt > 0 && $cnt < 25 && $rev / $cnt > 200) {
 $cards[] = $this->card(
 'Menü',
 'Gizli Şampiyon',
 $cnt . ' adet',
 sprintf('"%s" az satılıyor ama ₺%s/adet.', $item['name'] ?? '—', number_format($rev / $cnt, 0, ',', '.')),
 'Premium konumlandırma için fotoğraf yatırımı yapın.',
 'yüksek',
 'info',
 'gem'
 );
 break;
 }
 }

 if (count($top) >= 5) {
 $last = $top[count($top) - 1];
 $cards[] = $this->card(
 'Menü',
 'Menü Sonu Kararı',
 (int)($last['count'] ?? 0) . ' adet',
 sprintf('"%s" performansı düşük.', $last['name'] ?? '—'),
 '30 gün içinde iyileşmezse menüden çıkarın.',
 'yüksek',
 'warning',
 'trash'
 );
 }

 if (count($top) >= 2) {
 $comboTotal = (float)($top[0]['revenue'] ?? 0) + (float)($top[1]['revenue'] ?? 0);
 $cards[] = $this->card(
 'Menü',
 'Combo Stratejisi',
 '+%18 sepet',
 sprintf('"%s" + "%s" combo.', $top[0]['name'] ?? '—', $top[1]['name'] ?? '—'),
 sprintf('"2\'si ₺%s yerine ₺%s" kampanyası.', number_format($comboTotal, 0, ',', '.'), number_format($comboTotal * 0.85, 0, ',', '.')),
 'yüksek',
 'info',
 'package'
 );
 }

 $month = (int)date('n');
 $seasonal = $month >= 6 && $month <= 8 ? 'Serinletici içecekler, dondurma, soğuk çorbalar öne çıkmalı.' :
 ($month >= 12 || $month <= 2 ? 'Sıcak çorba, sıcak içecek, ağır tatlılar satışta.' :
 'Hafif yeşillik, salata, smoothieler mevsimlik tercih.');
 $cards[] = $this->card(
 'Menü',
 'Mevsimsellik',
 date('F'),
 'Mevsim bazlı ürün önerisi.',
 $seasonal,
 'orta',
 'info',
 'sun'
 );

 $avgPrice = 0;
 $cnt = 0;
 foreach ($top as $i) {
 $c = (int)($i['count'] ?? 0);
 if ($c > 0) { $avgPrice += (float)$i['revenue'] / $c; $cnt++; }
 }
 $avgPrice = $cnt > 0 ? $avgPrice / $cnt : 0;
 if ($avgPrice > 0) {
 $cards[] = $this->card(
 'Menü',
 'Fiyat Skalası',
 '₺' . number_format($avgPrice, 0, ',', '.'),
 'Ortalama ürün fiyatı (3 katmanlı yapı önerisi).',
 sprintf('Giriş ₺%s, ana ₺%s, premium ₺%s.', number_format($avgPrice * 0.6, 0, ',', '.'), number_format($avgPrice, 0, ',', '.'), number_format($avgPrice * 1.8, 0, ',', '.')),
 'orta',
 'info',
 'dollar-sign'
 );
 }

 $cards[] = $this->card(
 'Menü',
 'Çeşitlilik',
 count($top) . ' ürün',
 'Aktif takip edilen ürün sayısı.',
 count($top) < 6 ? '2-3 yeni ürün ekleyin.' : 'Çeşitlilik iyi seviyede.',
 count($top) < 6 ? 'yüksek' : 'düşük',
 count($top) >= 6 ? 'success' : 'warning',
 'grid'
 );

 return $cards;
 }

 /* ========================================================
 PERSONEL
 ======================================================== */
 private function insightStaff(array $d): array {
 $r = (float)$d['revenue'];
 $openShifts = $d['open_shifts'] ?? [];
 $cards = [];

 $cards[] = $this->card(
 'Personel',
 count($openShifts) > 0 ? 'Saha Aktif' : 'Saha Sessiz',
 count($openShifts) . ' vardiya',
 'Açık vardiya sayısı.',
 count($openShifts) === 0 ? 'Personel girişi yapılmamış olabilir.' : 'Operasyon aktif.',
 count($openShifts) === 0 ? 'yüksek' : 'düşük',
 count($openShifts) > 0 ? 'success' : 'warning',
 'user-check'
 );

 if (count($openShifts) > 0 && $r > 0) {
 $perShift = $r / count($openShifts);
 $cards[] = $this->card(
 'Personel',
 'Vardiya Başına Ciro',
 '₺' . number_format($perShift, 0, ',', '.'),
 sprintf('%d vardiya üzerinden ortalama.', count($openShifts)),
 sprintf('₺%s üstü başarılı.', number_format($perShift * 1.2, 0, ',', '.')),
 'orta',
 'info',
 'users'
 );
 }

 $cards[] = $this->card(
 'Personel',
 'Eğitim Etkisi',
 '+%8-12 ciro',
 'Aylık 1 saat ürün bilgisi + upsell eğitimi.',
 'Bu ay eğitim planı oluşturun.',
 'yüksek',
 'info',
 'book'
 );

 $cards[] = $this->card(
 'Personel',
 'Vardiya Modeli',
 'Çift vardiya',
 '12:00-16:00 ve 18:00-23:00 en verimli dilimler.',
 'Ara saatlerde part-time/stajyer kullanın.',
 'orta',
 'info',
 'clock'
 );

 $cards[] = $this->card(
 'Personel',
 'Performans Primi',
 '+%12 sepet',
 'Aylık "en çok upsell yapan garson" primi ₺500-1000.',
 'Bu ay prim sistemi duyurun.',
 'yüksek',
 'info',
 'award'
 );

 $oldOpen = 0;
 foreach ($openShifts as $s) {
 $opened = strtotime($s['opened_at'] ?? $s['start_time'] ?? 'now');
 if ($opened < strtotime('-12 hours')) { $oldOpen++; }
 }
 if ($oldOpen > 0) {
 $cards[] = $this->card(
 'Personel',
 'Uzun Açık Vardiya',
 $oldOpen . ' adet',
 sprintf('%d vardiya 12 saatten fazla açık.', $oldOpen),
 'Z raporu çekilmemiş olabilir, bugün kapatın.',
 'yüksek',
 'danger',
 'alert'
 );
 }

 return $cards;
 }

 /* ========================================================
 QUICK STATS
 ======================================================== */
 private function buildQuickStats(array $data, string $category): array {
 $r = (float)($data['revenue'] ?? 0);
 $e = (float)($data['expenses'] ?? 0);
 $top = $data['top_items'] ?? [];
 $p = $r - $e;
 $m = (float)($data['profit_margin'] ?? 0);

 switch ($category) {
 case 'revenue':
 return [
 'kpi1' => ['value' => '₺' . number_format($r, 0, ',', '.'), 'label' => 'Toplam Ciro', 'icon' => 'trending-up', 'tone' => 'success'],
 'kpi2' => ['value' => '%' . $m, 'label' => 'Kâr Marjı', 'icon' => 'percent', 'tone' => $m >= 20 ? 'success' : 'warning'],
 'kpi3' => ['value' => '₺' . number_format($data['avg_order_value'] ?? 0, 0, ',', '.'), 'label' => 'Ortalama Sepet', 'icon' => 'shopping-bag', 'tone' => 'info'],
 ];
 case 'expense':
 return [
 'kpi1' => ['value' => '₺' . number_format($e, 0, ',', '.'), 'label' => 'Toplam Gider', 'icon' => 'wallet', 'tone' => 'warning'],
 'kpi2' => ['value' => '₺' . number_format($data['unpaid_total'] ?? 0, 0, ',', '.'), 'label' => 'Açık Fatura', 'icon' => 'file-text', 'tone' => ($data['unpaid_total'] ?? 0) > 0 ? 'danger' : 'success'],
 'kpi3' => ['value' => $r > 0 ? '%' . round(($e / $r) * 100, 1) : '%0', 'label' => 'Gider/ Ciro', 'icon' => 'pie-chart', 'tone' => $e > $r * 0.5 ? 'danger' : 'success'],
 ];
 case 'performance':
 return [
 'kpi1' => ['value' => '₺' . number_format($p, 0, ',', '.'), 'label' => 'Net Kâr', 'icon' => 'dollar-sign', 'tone' => $p > 0 ? 'success' : 'danger'],
 'kpi2' => ['value' => '%' . $m, 'label' => 'Kâr Marjı', 'icon' => 'activity', 'tone' => $m >= 20 ? 'success' : 'warning'],
 'kpi3' => ['value' => count($data['open_shifts'] ?? []) . ' vardiya', 'label' => 'Aktif Saha', 'icon' => 'user-check', 'tone' => 'info'],
 ];
 case 'product':
 $leadName = $top[0]['name'] ?? '—';
 $leadCnt = (int)($top[0]['count'] ?? 0);
 $leadRev = (float)($top[0]['revenue'] ?? 0);
 return [
 'kpi1' => ['value' => mb_substr($leadName, 0, 14), 'label' => 'Yıldız Ürün', 'icon' => 'star', 'tone' => 'success'],
 'kpi2' => ['value' => $leadCnt . ' adet', 'label' => 'Satış Adedi', 'icon' => 'trending-up', 'tone' => 'info'],
 'kpi3' => ['value' => '₺' . number_format($leadRev, 0, ',', '.'), 'label' => 'Lider Ciro', 'icon' => 'gem', 'tone' => 'success'],
 ];
 case 'waste':
 $wr = $r > 0 ? round(((float)($data['waste_total'] ?? 0) / $r) * 100, 2) : 0;
 return [
 'kpi1' => ['value' => '₺' . number_format($data['waste_total'] ?? 0, 0, ',', '.'), 'label' => 'Toplam Fire', 'icon' => 'package-x', 'tone' => $wr > 3 ? 'danger' : 'warning'],
 'kpi2' => ['value' => '%' . $wr, 'label' => 'Fire / Ciro', 'icon' => 'percent', 'tone' => $wr > 3 ? 'danger' : 'success'],
 'kpi3' => ['value' => count($data['waste_records'] ?? []) . ' kayıt', 'label' => 'Fire Adedi', 'icon' => 'alert-circle', 'tone' => 'info'],
 ];
 case 'customer':
 $ac = (int)($data['active_customers'] ?? 0);
 $arpu = $ac > 0 ? $r / $ac : 0;
 return [
 'kpi1' => ['value' => number_format($ac, 0, ',', '.') . ' kişi', 'label' => 'Aktif Müşteri', 'icon' => 'users', 'tone' => 'info'],
 'kpi2' => ['value' => '₺' . number_format($arpu, 0, ',', '.'), 'label' => 'Müşteri Başı', 'icon' => 'user', 'tone' => $arpu >= 500 ? 'success' : 'warning'],
 'kpi3' => ['value' => '₺' . number_format($data['avg_order_value'] ?? 0, 0, ',', '.'), 'label' => 'Ort. Sepet', 'icon' => 'shopping-cart', 'tone' => 'success'],
 ];
 case 'menu':
 $leadName = $top[0]['name'] ?? '—';
 $leadCnt = (int)($top[0]['count'] ?? 0);
 $top3Rev = 0;
 for ($i = 0; $i < min(3, count($top)); $i++) { $top3Rev += (float)($top[$i]['revenue'] ?? 0); }
 $top3Share = $r > 0 ? round(($top3Rev / $r) * 100, 1) : 0;
 return [
 'kpi1' => ['value' => mb_substr($leadName, 0, 14), 'label' => 'En Çok Satan', 'icon' => 'star', 'tone' => 'success'],
 'kpi2' => ['value' => $leadCnt . ' adet', 'label' => 'Satış Adedi', 'icon' => 'trending-up', 'tone' => 'info'],
 'kpi3' => ['value' => '%' . $top3Share, 'label' => 'Top 3 Payı', 'icon' => 'pie-chart', 'tone' => $top3Share > 60 ? 'warning' : 'success'],
 ];
 case 'staff':
 return [
 'kpi1' => ['value' => count($data['open_shifts'] ?? []) . ' vardiya', 'label' => 'Açık Vardiya', 'icon' => 'user-check', 'tone' => count($data['open_shifts'] ?? []) > 0 ? 'success' : 'warning'],
 'kpi2' => ['value' => '₺' . number_format($r, 0, ',', '.'), 'label' => 'Ciro', 'icon' => 'trending-up', 'tone' => 'success'],
 'kpi3' => ['value' => '₺' . number_format($data['avg_order_value'] ?? 0, 0, ',', '.'), 'label' => 'Ort. Sepet', 'icon' => 'shopping-bag', 'tone' => 'info'],
 ];
 default:
 return [
 'kpi1' => ['value' => '—', 'label' => 'Veri Bekleniyor', 'icon' => 'info', 'tone' => 'neutral'],
 'kpi2' => ['value' => '—', 'label' => 'Veri Bekleniyor', 'icon' => 'info', 'tone' => 'neutral'],
 'kpi3' => ['value' => '—', 'label' => 'Veri Bekleniyor', 'icon' => 'info', 'tone' => 'neutral'],
 ];
 }
 }

 /**
 * Kaydedilmiş öneriler sayfası.
 */
 public function savedInsightsPage() {
 if (!$this->hasPermission('dashboard.analytics')) {
 header('Location: ' . BASE_URL . '/unauthorized');
 return;
 }
 $apiPrefix = $this->isSuperAdmin() ? '/api/qodmin' : '/api/business';
 $this->view('admin/ai_suggestions', [
 'page_title' => 'AI Önerileri',
 'api_prefix' => $apiPrefix,
 ]);
 }

 /**
 * GET — kullanıcının kaydettiği öneriler.
 */
 public function listSavedInsights() {
 if (!$this->hasPermission('dashboard.analytics')) {
 $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
 return;
 }
 $ctx = $this->resolveSaveContext();
 if (!$ctx) {
 $this->apiResponse(['success' => false, 'items' => []], 400);
 return;
 }
 $items = $this->savedInsightRepo->listForUser($ctx['business_id'], $ctx['user_id']);
 $this->apiResponse(['success' => true, 'items' => $items]);
 }

 /**
 * POST — öneriyi kaydet.
 */
 public function saveInsight() {
 if (!$this->hasPermission('dashboard.analytics')) {
 $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
 return;
 }
 $ctx = $this->resolveSaveContext();
 if (!$ctx) {
 $this->apiResponse(['success' => false, 'message' => 'Tenant context missing'], 400);
 return;
 }
 $body = \App\Core\RequestParser::getRequestData();
 $insightId = trim((string)($body['insight_id'] ?? ''));
 if ($insightId === '') {
 $this->apiResponse(['success' => false, 'message' => 'insight_id required'], 422);
 return;
 }
 $row = [
 'insight_id' => $insightId,
 'category_key' => $this->sanitizeInsightText((string)($body['category_key'] ?? ''), 64),
 'category_label' => $this->sanitizeInsightText((string)($body['category_label'] ?? $body['category'] ?? ''), 64),
 'title' => $this->sanitizeInsightText((string)($body['title'] ?? ''), 200),
 'metric' => $this->sanitizeInsightText((string)($body['metric'] ?? ''), 64),
 'body_text' => $this->sanitizeInsightText((string)($body['text'] ?? $body['body_text'] ?? ''), 1000),
 'action_hint' => $this->sanitizeInsightText((string)($body['action'] ?? $body['action_hint'] ?? ''), 500),
 'impact' => $this->sanitizeInsightText((string)($body['impact'] ?? 'orta'), 16),
 'tone' => $this->sanitizeInsightText((string)($body['tone'] ?? 'info'), 16),
 'icon' => $this->sanitizeInsightText((string)($body['icon'] ?? 'info'), 32),
 'source' => $this->sanitizeInsightText((string)($body['source'] ?? 'rule'), 16),
 'payload_json' => json_encode($body, JSON_UNESCAPED_UNICODE),
 ];
 $ok = $this->savedInsightRepo->save($ctx['business_id'], $ctx['user_id'], $row);
 $this->apiResponse(['success' => $ok, 'insight_id' => $insightId]);
 }

 /**
 * POST/DELETE — kaydı kaldır.
 */
 public function unsaveInsight() {
 if (!$this->hasPermission('dashboard.analytics')) {
 $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
 return;
 }
 $ctx = $this->resolveSaveContext();
 if (!$ctx) {
 $this->apiResponse(['success' => false], 400);
 return;
 }
 $body = \App\Core\RequestParser::getRequestData();
 $insightId = trim((string)($body['insight_id'] ?? $body['id'] ?? ''));
 if ($insightId === '') {
 $this->apiResponse(['success' => false, 'message' => 'insight_id required'], 422);
 return;
 }
 $ok = $this->savedInsightRepo->unsave($ctx['business_id'], $ctx['user_id'], $insightId);
 $this->apiResponse(['success' => $ok]);
 }

 /**
 * @return array{business_id:string,user_id:string}|null
 */
 private function resolveSaveContext(): ?array {
 $businessId = (string)(\App\Core\TenantContext::getId() ?? '');
 if ($businessId === '') {
 $businessId = (string)(\App\Core\SessionManager::get('business_id') ?? \App\Core\SessionManager::get('customer_id') ?? '');
 }
 $userId = (string)($this->getCurrentUserId() ?? '');
 if ($businessId === '' || $userId === '') {
 return null;
 }
 return ['business_id' => $businessId, 'user_id' => $userId];
 }

 /**
 * @return string[]
 */
 private function getSavedInsightIdsForCurrentUser(): array {
 $ctx = $this->resolveSaveContext();
 if (!$ctx) {
 return [];
 }
 return $this->savedInsightRepo->listInsightIdsForUser($ctx['business_id'], $ctx['user_id']);
 }

 /**
 * Karışık kategori feed — öncelik + çeşitlilik ile 5–8 öneri.
 *
 * @return array{batch_id:string,expires_at:string,insights:array<int,array>}
 */
 private function generateFeedInsights(array $payload, string $startDate, string $endDate, string $range): array {
 $categories = [
 'revenue' => 'Gelir',
 'expense' => 'Gider',
 'performance' => 'Performans',
 'product' => 'Ürün',
 'waste' => 'Fire',
 'customer' => 'Müşteri',
 'menu' => 'Menü',
 'staff' => 'Personel',
 ];
 $pool = [];
 foreach ($categories as $key => $label) {
 $cards = $this->generateFallbackInsights($payload, $key);
 foreach ($cards as $card) {
 if ($this->shouldSkipFeedCard($card, $payload)) {
 continue;
 }
 $pool[] = $this->enrichFeedInsight($card, $key, $label);
 }
 }

 usort($pool, function ($a, $b) {
 return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
 });

 $picked = [];
 $perCat = [];
 $limit = self::FEED_ROW_LIMIT;
 foreach ($pool as $item) {
 $ck = $item['category_key'] ?? '';
 if (($perCat[$ck] ?? 0) >= 2) {
 continue;
 }
 unset($item['_score']);
 $picked[] = $item;
 $perCat[$ck] = ($perCat[$ck] ?? 0) + 1;
 if (count($picked) >= $limit) {
 break;
 }
 }

 if (count($picked) < 3) {
 foreach ($this->buildSupplementAutoInsights($payload) as $extra) {
 if (count($picked) >= $limit) {
 break;
 }
 $picked[] = $extra;
 }
 }

 if (empty($picked)) {
 $picked[] = [
 'id' => $this->insightHash('system', 'Veri Bekleniyor', 'Seçili dönemde yeterli veri yok.'),
 'category_key' => 'system',
 'category' => 'Sistem',
 'title' => 'Veri Bekleniyor',
 'metric' => '',
 'text' => 'Seçili dönemde analiz için yeterli veri bulunamadı. Tarih aralığını genişletmeyi deneyin.',
 'action' => 'Üstteki dönem filtresinden "Bu Ay" veya "Bu Hafta" seçin.',
 'impact' => 'düşük',
 'tone' => 'neutral',
 'icon' => 'info',
 'source' => 'auto',
 ];
 }

 $slot = (int) floor(time() / self::FEED_TTL_SECONDS);
 $batchId = hash('sha256', implode('|', [$startDate, $endDate, $range, (string)$slot]));
 $expiresAt = gmdate('c', ($slot + 1) * self::FEED_TTL_SECONDS);

 return [
 'batch_id' => $batchId,
 'expires_at' => $expiresAt,
 'insights' => $picked,
 ];
 }

 private function shouldSkipFeedCard(array $card, array $payload): bool {
 $title = (string)($card['title'] ?? '');
 $rangeKey = (string)($payload['range_key'] ?? 'month');

 if ($title === 'Veri Bulunamadı' && (float)($payload['revenue'] ?? 0) > 0) {
 return true;
 }
 if ($title === 'Kategori seçin') {
 return true;
 }
 if (in_array($title, ['Bugün Dünden İyi', 'Bugün Geride'], true) && $rangeKey !== 'today') {
 return true;
 }
 if ($title === 'Aylık Projeksiyon' && !in_array($rangeKey, ['month', '3months', '6months', '9months', 'year'], true)) {
 return true;
 }
 $text = trim((string)($card['text'] ?? ''));
 return $text === '';
 }

 private function enrichFeedInsight(array $card, string $categoryKey, string $categoryLabel): array {
 $title = (string)($card['title'] ?? '');
 $text = (string)($card['text'] ?? '');
 $impact = (string)($card['impact'] ?? 'orta');
 $meta = $this->categoryMeta($categoryKey);
 return [
 'id' => $this->insightHash($categoryKey, $title, $text),
 'category_key' => $categoryKey,
 'category' => $meta['label'],
 'title' => $title,
 'metric' => (string)($card['metric'] ?? ''),
 'text' => $text,
 'action' => (string)($card['action'] ?? ''),
 'impact' => $impact,
 'tone' => (string)($card['tone'] ?? 'info'),
 'icon' => (string)($card['icon'] ?? 'info'),
 'emoji' => $meta['emoji'],
 'type_class' => $meta['type_class'],
 'source' => 'rule',
 '_score' => $this->impactScore($impact),
 ];
 }

 /**
 * @return array{emoji:string,label:string,type_class:string}
 */
 private function categoryMeta(string $categoryKey): array {
 return match ($categoryKey) {
 'revenue' => ['emoji' => '💰', 'label' => 'GELİR', 'type_class' => 'gelir'],
 'expense' => ['emoji' => '📉', 'label' => 'GİDER', 'type_class' => 'gider'],
 'performance' => ['emoji' => '⚡', 'label' => 'OPERASYON', 'type_class' => 'operasyon'],
 'product' => ['emoji' => '🍽', 'label' => 'ÜRÜN', 'type_class' => 'urun'],
 'waste' => ['emoji' => '🔥', 'label' => 'FİRE', 'type_class' => 'fire'],
 'customer' => ['emoji' => '👥', 'label' => 'MÜŞTERİ', 'type_class' => 'musteri'],
 'menu' => ['emoji' => '📋', 'label' => 'MENÜ', 'type_class' => 'menu'],
 'staff' => ['emoji' => '👤', 'label' => 'PERSONEL', 'type_class' => 'personel'],
 default => ['emoji' => 'ℹ️', 'label' => 'SİSTEM', 'type_class' => 'sistem'],
 };
 }

 /**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
 private function sanitizeFeedInsight(array $item): array {
 foreach (['category', 'title', 'metric', 'text', 'action', 'impact', 'tone', 'icon', 'emoji'] as $field) {
 if (isset($item[$field])) {
 $max = $field === 'text' ? 1000 : ($field === 'action' ? 500 : 200);
 $item[$field] = $this->sanitizeInsightText((string)$item[$field], $max);
 }
 }
 return $item;
 }

 private function sanitizeInsightText(string $value, int $maxLen): string {
 $value = strip_tags($value);
 $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
 $value = trim($value);
 if (mb_strlen($value, 'UTF-8') > $maxLen) {
 $value = mb_substr($value, 0, $maxLen, 'UTF-8');
 }
 return $value;
 }

 private function insightHash(string $categoryKey, string $title, string $text): string {
 return hash('sha256', $categoryKey . '|' . $title . '|' . $text);
 }

 private function impactScore(string $impact): int {
 return match (mb_strtolower($impact, 'UTF-8')) {
 'yüksek' => 3,
 'orta' => 2,
 default => 1,
 };
 }

 /**
 * Dashboard computeAutoInsights ile uyumlu ek öneriler (source: auto).
 *
 * @return array<int, array<string, mixed>>
 */
 private function buildSupplementAutoInsights(array $payload): array {
 $items = [];
 $r = (float)($payload['revenue'] ?? 0);
 $rangeKey = (string)($payload['range_key'] ?? 'month');
 $change = (float)($payload['revenue_change_pct'] ?? 0);
 if ($r > 0 && abs($change) >= 5) {
 $items[] = [
 'id' => $this->insightHash('auto', 'Dönem Ciro Değişimi', (string)$change),
 'category_key' => 'revenue',
 'category' => 'GELİR',
 'title' => $change >= 0 ? 'Ciro Artışı' : 'Ciro Düşüşü',
 'metric' => ($change >= 0 ? '+' : '') . '%' . $change,
 'text' => 'Önceki eşit döneme göre ciro değişimi.',
 'action' => $change >= 0 ? 'Artışı sürdürmek için yoğun saatleri koruyun.' : 'Kampanya veya menü revizyonu değerlendirin.',
 'impact' => abs($change) >= 15 ? 'yüksek' : 'orta',
 'tone' => $change >= 0 ? 'success' : 'warning',
 'icon' => $change >= 0 ? 'trending-up' : 'trending-down',
 'emoji' => '💰',
 'type_class' => 'gelir',
 'source' => 'auto',
 ];
 } elseif ($r > 0) {
 $items[] = [
 'id' => $this->insightHash('auto', 'Dönem Cirosu', 'Ciro'),
 'category_key' => 'revenue',
 'category' => 'GELİR',
 'title' => 'Dönem Cirosu',
 'metric' => '₺' . number_format($r, 0, ',', '.'),
 'text' => sprintf('%s – %s aralığında toplam ciro.', $payload['start_date'] ?? '', $payload['end_date'] ?? ''),
 'action' => 'Detay için Raporlar sayfasına gidin.',
 'impact' => 'orta',
 'tone' => 'info',
 'icon' => 'trending-up',
 'emoji' => '💰',
 'type_class' => 'gelir',
 'source' => 'auto',
 ];
 }
 $open = count($payload['open_shifts'] ?? []);
 if ($open > 0) {
 $items[] = [
 'id' => $this->insightHash('auto', 'Açık Vardiya', (string)$open),
 'category_key' => 'staff',
 'category' => 'PERSONEL',
 'title' => 'Açık Vardiya',
 'metric' => (string)$open,
 'text' => $open . ' vardiya hâlâ açık — gün sonu kapanışını kontrol edin.',
 'action' => 'Personel > Vardiyalar bölümünü inceleyin.',
 'impact' => 'yüksek',
 'tone' => 'warning',
 'icon' => 'user-check',
 'emoji' => '👤',
 'type_class' => 'personel',
 'source' => 'auto',
 ];
 }
 return $items;
 }
}
