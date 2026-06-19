<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Business owner'ın abonelik faturaları/makbuzları.
 *
 * Eski hâli `invoices` tablosundan okuyordu; o tablo aslında tedarikçi
 * faturalarına aitti ve `invoice_number`/`status` gibi view'in beklediği
 * alanlar yoktu. Ayrıca `download()` yalnızca "yakında eklenecektir"
 * mesajı veriyordu. Şu an:
 *   - Makbuz kaynakları: `subscription_payments` (+ `subscriptions` ile
 *     join) üzerinden abonelik ödemeleri.
 *   - download() artık gerçek içerikli bir yazdırılabilir HTML makbuz
 *     çıktısı üretiyor (Content-Disposition: inline) — tarayıcının
 *     "PDF olarak kaydet" özelliğiyle indirilebiliyor. True-PDF rendering
 *     için dompdf vb. eklenmeden de kullanıcı işlerini yapabiliyor.
 */
class BillingController extends Controller {

    private function resolveCustomerId(): ?string {
        $id = $_SESSION['customer_id'] ?? null;
        if ($id) return (string)$id;
        $email = $_SESSION['username'] ?? $_SESSION['email'] ?? '';
        if (!$email) return null;
        try {
            $row = DependencyFactory::getCustomerRepository()->findByEmail($email);
            return $row['customer_id'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Müşterinin (business) tüm abonelik makbuzlarını getirir.
     * View'in beklediği alan adlarına da normalize eder.
     */
    private function fetchInvoices(string $customerId): array {
        try {
            $db = DependencyFactory::getDatabase();
            $sql = "
                SELECT
                    sp.payment_id,
                    sp.subscription_id,
                    sp.amount,
                    sp.currency,
                    sp.payment_method,
                    sp.payment_status,
                    sp.gateway_transaction_id,
                    sp.payment_date,
                    sp.created_at,
                    s.tenant_id,
                    s.package_id,
                    s.billing_cycle,
                    p.name AS package_name
                FROM subscription_payments sp
                INNER JOIN subscriptions s ON s.subscription_id = sp.subscription_id
                LEFT JOIN packages p ON p.package_id = s.package_id
                WHERE s.tenant_id = :cid
                ORDER BY COALESCE(sp.payment_date, sp.created_at) DESC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['cid' => $customerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            Logger::warning('BillingController::fetchInvoices query failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            $rows = [];
        }

        $statusMap = [
            'completed' => 'paid',
            'pending'   => 'pending',
            'failed'    => 'failed',
            'refunded'  => 'refunded',
        ];

        $out = [];
        foreach ($rows as $r) {
            $raw = $r['payment_status'] ?? 'pending';
            $out[] = [
                'id'             => $r['payment_id'],
                'payment_id'     => $r['payment_id'],
                'invoice_number' => self::formatInvoiceNumber($r['payment_id'], $r['created_at'] ?? null),
                'date'           => $r['payment_date'] ?: $r['created_at'],
                'amount'         => (float)$r['amount'],
                'currency'       => $r['currency'] ?: 'TRY',
                'status'         => $statusMap[$raw] ?? $raw,
                'raw_status'     => $raw,
                'payment_method' => $r['payment_method'],
                'package_name'   => $r['package_name'] ?? 'Abonelik',
                'billing_cycle'  => $r['billing_cycle'] ?? null,
                'subscription_id'=> $r['subscription_id'],
                'gateway_txn'    => $r['gateway_transaction_id'] ?? null,
            ];
        }
        return $out;
    }

    private static function formatInvoiceNumber(string $paymentId, ?string $createdAt): string {
        $yearPart = $createdAt ? date('Y', strtotime($createdAt)) : date('Y');
        $tail = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $paymentId), -8));
        if (!$tail) {
            $tail = strtoupper(bin2hex(random_bytes(4)));
        }
        return "INV-{$yearPart}-{$tail}";
    }

    public function index() {
        $this->requireLogin();

        $customerId = $this->resolveCustomerId();
        if (!$customerId) {
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }

        $customer = null;
        try {
            $customer = DependencyFactory::getCustomerService()->getCustomerById($customerId);
        } catch (\Throwable $e) {
            Logger::warning('BillingController::index customer lookup failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
        }

        $invoices = $this->fetchInvoices($customerId);

        $this->view('customer/billing', [
            'customer' => $customer,
            'invoices' => $invoices,
            'page'     => 'billing',
        ]);
    }

    public function download($id = null) {
        $this->requireLogin();

        // Route pattern: /business/billing/{id}/download → $id gelir.
        // GET fallback: eski linkler ?id=... kullanmış olabilir.
        $paymentId = $id ?: ($_GET['id'] ?? null);
        if (!$paymentId) {
            $this->toastNotificationService->setFlash('error', 'Fatura bulunamadı');
            header('Location: ' . BASE_URL . '/business/billing');
            exit;
        }

        $customerId = $this->resolveCustomerId();
        if (!$customerId) {
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }

        $invoice = null;
        $customer = null;
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("
                SELECT sp.*, s.tenant_id, s.package_id, s.billing_cycle,
                       p.name AS package_name, p.description AS package_description
                FROM subscription_payments sp
                INNER JOIN subscriptions s ON s.subscription_id = sp.subscription_id
                LEFT JOIN packages p ON p.package_id = s.package_id
                WHERE sp.payment_id = :pid AND s.tenant_id = :cid
                LIMIT 1
            ");
            $stmt->execute(['pid' => $paymentId, 'cid' => $customerId]);
            $invoice = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($invoice) {
                $customer = DependencyFactory::getCustomerService()->getCustomerById($customerId);
            }
        } catch (\Throwable $e) {
            Logger::error('BillingController::download query failed', [
                'payment_id'  => $paymentId,
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
        }

        if (!$invoice) {
            $this->toastNotificationService->setFlash('error', 'Fatura bulunamadı veya size ait değil.');
            header('Location: ' . BASE_URL . '/business/billing');
            exit;
        }

        $invoiceNumber = self::formatInvoiceNumber($invoice['payment_id'], $invoice['created_at'] ?? null);
        $this->renderInvoiceHtml($invoice, $customer, $invoiceNumber);
    }

    /**
     * Yazdırılabilir / "PDF olarak kaydet" ile indirilebilir HTML makbuz.
     * Tek başına HTML dosyası olarak değerlendirilebilmesi için tüm stil
     * inline tutuldu.
     */
    private function renderInvoiceHtml(array $invoice, ?array $customer, string $invoiceNumber): void {
        $h = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

        $amount    = number_format((float)$invoice['amount'], 2, ',', '.');
        $currency  = $invoice['currency'] ?: 'TRY';
        $symbol    = ($currency === 'TRY') ? '₺' : $h($currency . ' ');
        $dateRaw   = $invoice['payment_date'] ?: ($invoice['created_at'] ?? date('Y-m-d H:i:s'));
        $dateNice  = $dateRaw ? date('d.m.Y', strtotime($dateRaw)) : '-';
        $statusTr  = [
            'completed' => 'Ödendi',
            'pending'   => 'Beklemede',
            'failed'    => 'Başarısız',
            'refunded'  => 'İade Edildi',
        ][$invoice['payment_status'] ?? 'pending'] ?? $invoice['payment_status'];

        $customerName  = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: ($customer['business_name'] ?? '-');
        $customerEmail = $customer['email']         ?? '';
        $customerPhone = $customer['phone_number']  ?? ($customer['phone'] ?? '');
        $customerAddr  = $customer['address']       ?? '';

        $filename = "{$invoiceNumber}.html";

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');

        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8">';
        echo '<title>Makbuz ' . $h($invoiceNumber) . '</title>';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>
            * { box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color:#0f172a; background:#f8fafc; margin:0; padding:24px; }
            .sheet { max-width: 820px; margin: 0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:36px 40px; box-shadow: 0 4px 24px rgba(15,23,42,0.06); }
            h1 { font-size: 26px; margin: 0 0 4px; letter-spacing: .5px; }
            .muted { color:#64748b; font-size: 13px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; }
            .box { border:1px solid #e2e8f0; border-radius:12px; padding: 16px 18px; background:#f8fafc; }
            table { width: 100%; border-collapse: collapse; margin-top: 28px; }
            th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
            th { background:#f1f5f9; font-weight: 700; color:#334155; }
            .total-row td { font-weight: 800; font-size: 16px; }
            .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
            .badge.paid { background:#dcfce7; color:#15803d; }
            .badge.pending { background:#fef3c7; color:#a16207; }
            .badge.failed { background:#fee2e2; color:#b91c1c; }
            .badge.refunded { background:#e2e8f0; color:#475569; }
            .actions { margin: 0 auto 24px; max-width: 820px; display:flex; gap:12px; }
            .btn { padding: 10px 18px; border-radius: 10px; border: 1px solid #cbd5e1; background:#fff; font-weight: 700; cursor: pointer; }
            .btn-primary { background:#4f46e5; color:#fff; border-color:#4f46e5; }
            @media print {
                body { background:#fff; padding:0; }
                .actions { display:none !important; }
                .sheet { box-shadow:none; border:none; border-radius:0; margin:0; max-width:none; padding:24px 28px; }
            }
        </style></head><body>';

        echo '<div class="actions">';
        echo '<button class="btn btn-primary" onclick="window.print()">Yazdır / PDF olarak kaydet</button>';
        echo '<a class="btn" href="' . $h(BASE_URL) . '/business/billing">Geri dön</a>';
        echo '</div>';

        echo '<div class="sheet">';
        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;">';
        echo '<div>';
        echo '<div class="muted" style="letter-spacing:.2em;text-transform:uppercase;font-weight:700;">Ödeme Makbuzu</div>';
        echo '<h1>' . $h($invoiceNumber) . '</h1>';
        echo '<div class="muted">Tarih: ' . $h($dateNice) . '</div>';
        echo '</div>';
        $badgeClass = $invoice['payment_status'] ?? 'pending';
        echo '<div class="badge ' . $h($badgeClass) . '">' . $h($statusTr) . '</div>';
        echo '</div>';

        echo '<div class="grid">';
        echo '<div class="box"><div class="muted" style="font-weight:700;margin-bottom:6px;">FATURA EDİLEN</div>';
        echo '<div style="font-weight:700;">' . $h($customerName) . '</div>';
        if ($customerEmail) echo '<div class="muted">' . $h($customerEmail) . '</div>';
        if ($customerPhone) echo '<div class="muted">' . $h($customerPhone) . '</div>';
        if ($customerAddr)  echo '<div class="muted" style="margin-top:4px;">' . nl2br($h($customerAddr)) . '</div>';
        echo '</div>';
        echo '<div class="box"><div class="muted" style="font-weight:700;margin-bottom:6px;">ÖDEME BİLGİLERİ</div>';
        echo '<div>Ödeme yöntemi: <strong>' . $h(strtoupper($invoice['payment_method'] ?? '-')) . '</strong></div>';
        if (!empty($invoice['gateway_transaction_id'])) {
            echo '<div class="muted">Gateway: ' . $h($invoice['gateway_transaction_id']) . '</div>';
        }
        echo '<div class="muted">Abonelik: ' . $h($invoice['subscription_id']) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<table>';
        echo '<thead><tr><th>Açıklama</th><th style="text-align:right">Tutar</th></tr></thead>';
        echo '<tbody>';
        $desc = ($invoice['package_name'] ?? 'Abonelik') .
               (!empty($invoice['billing_cycle']) ? ' · ' . ($invoice['billing_cycle'] === 'monthly' ? 'Aylık' : 'Yıllık') : '');
        echo '<tr><td>' . $h($desc) . '</td><td style="text-align:right">' . $symbol . $h($amount) . '</td></tr>';
        echo '<tr class="total-row"><td>Toplam</td><td style="text-align:right">' . $symbol . $h($amount) . '</td></tr>';
        echo '</tbody></table>';

        echo '<div style="margin-top:32px;" class="muted">Bu makbuz elektronik olarak oluşturulmuştur. Resmî e-arşiv fatura ihtiyacınız varsa destek ekibimizle iletişime geçin.</div>';

        echo '</div></body></html>';
        exit;
    }
}
