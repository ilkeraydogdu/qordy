<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class PaymentController extends Controller {
    
    protected $paymentService;
    protected $subscriptionService;
    
    public function __construct() {
        parent::__construct();
        $this->paymentService = \App\Core\DependencyFactory::getPaymentService();
        $this->subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
    }
    
    /**
     * Payment success page
     *
     * Normally this is hit via a top-level redirect from the bridge page,
     * where the parent window (with valid session) does the navigation.
     * In a degenerate case — 3DS broke out of the iframe and the cross-
     * site POST stripped our SameSite=Lax cookie — we can still land
     * here without a session. Dumping the user back on /login in that
     * moment is a bad UX for a payment they just made, so we render a
     * lightweight public confirmation that also offers a login CTA.
     */
    public function successPage() {
        $transactionId = $_GET['transaction_id'] ?? '';
        $loggedIn = !empty($_SESSION['logged_in']);

        if (!$loggedIn) {
            $this->renderPublicPaymentResult('success', [
                'transaction_id' => $transactionId,
                'headline' => 'Ödemeniz tamamlandı',
                'message'  => 'Ödemeniz alındı ve aboneliğiniz aktifleştirildi. Devam etmek için lütfen tekrar giriş yapın.',
            ]);
            return;
        }

        $this->view('customer/payment_success', [
            'transaction_id' => $transactionId,
            'message' => 'Ödemeniz başarıyla tamamlandı! Aboneliğiniz aktif edildi.'
        ]);
    }

    /**
     * Payment fail page — same graceful-fallback rationale as successPage().
     */
    public function failPage() {
        $error = $_GET['error'] ?? 'Ödeme işlemi başarısız oldu.';
        $loggedIn = !empty($_SESSION['logged_in']);

        if (!$loggedIn) {
            $this->renderPublicPaymentResult('fail', [
                'headline' => 'Ödeme tamamlanamadı',
                'message'  => $error,
            ]);
            return;
        }

        $this->view('customer/payment_fail', [
            'error' => $error,
            'message' => 'Ödeme işlemi tamamlanamadı. Lütfen tekrar deneyin.'
        ]);
    }

    /**
     * Minimal, public (no-session-required) confirmation page rendered
     * when the user lands on /success or /fail without an active
     * session. Keeps sensitive details out while still giving closure.
     */
    private function renderPublicPaymentResult(string $status, array $data): void {
        // Re-use the premium success/fail views so a user whose session
        // got stripped by the 3DS bounce sees the exact same page as
        // someone who landed with a live session — just with a "Giriş
        // Yap" CTA instead of dashboard links. Views read an optional
        // $public flag to swap the primary action.
        $isSuccess = $status === 'success';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $viewData = [
            'public'         => true,
            'message'        => (string)($data['message']  ?? ($isSuccess
                                    ? 'Ödemeniz başarıyla alındı.'
                                    : 'Ödeme işlemi tamamlanamadı.')),
            'transaction_id' => (string)($data['transaction_id'] ?? ''),
            'error'          => (string)($data['message'] ?? ''),
        ];

        $viewPath = __DIR__ . '/../../views/customer/'
            . ($isSuccess ? 'payment_success' : 'payment_fail')
            . '.php';

        if (file_exists($viewPath)) {
            extract($viewData);
            require $viewPath;
            return;
        }

        // Defensive fallback only — should never actually fire.
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"></head>'
            . '<body style="font-family:system-ui;padding:2rem;text-align:center">'
            . '<h1>' . ($isSuccess ? 'Ödeme Tamamlandı' : 'Ödeme Tamamlanamadı') . '</h1>'
            . '<p>' . htmlspecialchars($viewData['message']) . '</p>'
            . '<p><a href="' . htmlspecialchars(BASE_URL . '/login') . '">Giriş Yap</a></p>'
            . '</body></html>';
    }
    
    /**
     * Initiate iyzico payment - API endpoint
     */
    public function initiateIyzico() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $subscriptionId = $requestData['subscription_id'] ?? $requestData['order_id'] ?? '';

        $prepared = $this->prepareIyzicoCheckout($subscriptionId);

        if (!$prepared['success']) {
            http_response_code($prepared['status'] ?? 400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $prepared['error']]);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'checkout_form_content' => $prepared['checkout_form_content'] ?? '',
            'token' => $prepared['token'] ?? null,
            'conversation_id' => $prepared['conversation_id'] ?? null,
        ]);
        exit;
    }

    /**
     * Render the iyzico checkout form as a FULL HTML page that lives inside
     * an <iframe> embedded by payment.php.
     *
     * Why an iframe:
     *   - iyzico's CheckoutForm bundle navigates `window.top` on 3D-Secure
     *     redirection, and then the issuing bank POSTs the SMS result back
     *     to our callback URL. If we host the form directly on payment.php,
     *     the whole merchant page is navigated away and the session cookie
     *     is treated as cross-site on the final POST (SameSite=Lax strips
     *     it) — which is exactly why the user was dumped on /login after
     *     entering the OTP.
     *   - By wrapping it in a same-origin iframe, all those navigations
     *     happen inside the frame. The callback page posts a message to
     *     the parent window which still holds the real session cookies and
     *     performs the final top-level redirect to /success or /fail.
     */
    public function iyzicoFrame() {
        $this->requireLogin();

        $subscriptionId = (string)($_GET['subscription_id'] ?? '');
        $prepared = $this->prepareIyzicoCheckout($subscriptionId);

        header('Content-Type: text/html; charset=utf-8');
        // Only we should ever embed this frame. The full CSP (with
        // script/frame/etc. directives) is already set by
        // SecurityHeadersMiddleware and includes `frame-ancestors 'self'
        // https://*.qordy.com`, so we intentionally do NOT override it
        // here — overriding would drop all the whitelists iyzico needs
        // (bundle.js, fonts, API endpoints).
        header('X-Frame-Options: SAMEORIGIN');

        if (!$prepared['success']) {
            $err = htmlspecialchars((string)($prepared['error'] ?? 'Ödeme başlatılamadı'), ENT_QUOTES, 'UTF-8');
            echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
                . 'body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f8fafc;color:#0f172a;padding:24px}'
                . '.box{max-width:520px;margin:20px auto;padding:20px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b;font-size:14px}'
                . '</style></head><body><div class="box">' . $err . '</div>'
                . '<script>try{if(window.parent&&window.parent!==window){window.parent.postMessage({type:"iyzico:init-fail",error:' . json_encode($err) . '},window.location.origin);}}catch(e){}</script>'
                . '</body></html>';
            exit;
        }

        $content = $prepared['checkout_form_content'] ?? '';
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Güvenli Ödeme</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
            . '<style>'
            . '  html,body{margin:0;padding:0;background:transparent;font-family:Inter,system-ui,-apple-system,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;overflow:hidden;}'
            . '  body{padding:0;}'
            . '  #iyzipay-checkout-form{min-height:560px}'
            . '  #iyzipay-checkout-form iframe{width:100%!important;border:0!important;min-height:560px;background:transparent;display:block;}'
            . '</style>'
            . '</head><body>'
            // Tell iyzico to render inline inside this frame (not as popup).
            . '<div id="iyzipay-checkout-form" class="responsive"></div>'
            . $content
            // Height broadcaster — parent (payment.php) listens for
            // `iyzico:resize` and grows its outer <iframe> to match so we
            // never show a scrollbar on the payment panel. iyzico's
            // hosted form lives in a nested cross-origin iframe inside
            // this div; we can't measure that iframe directly, but we
            // can observe the container's bounding box which iyzico's
            // loader keeps in sync with its inner contents.
            . '<script>(function(){'
            . '  var lastH=0;'
            . '  function reportHeight(){'
            . '    try{'
            . '      var c=document.getElementById("iyzipay-checkout-form");'
            . '      var h=Math.max(document.body.scrollHeight,document.documentElement.scrollHeight,c?c.getBoundingClientRect().height:0,c?c.offsetHeight:0);'
            . '      h=Math.round(h);'
            . '      if(h && h!==lastH){'
            . '        lastH=h;'
            . '        try{window.parent.postMessage({type:"iyzico:resize",height:h},window.location.origin);}catch(e){}'
            . '      }'
            . '    }catch(e){}'
            . '  }'
            . '  var tick=setInterval(reportHeight,250);'
            . '  window.addEventListener("load",reportHeight);'
            . '  window.addEventListener("resize",reportHeight);'
            . '  if(window.ResizeObserver){'
            . '    try{var ro=new ResizeObserver(reportHeight);ro.observe(document.body);'
            . '      var c=document.getElementById("iyzipay-checkout-form");if(c){ro.observe(c);}'
            . '    }catch(e){}'
            . '  }'
            . '  if(window.MutationObserver){'
            . '    try{new MutationObserver(reportHeight).observe(document.body,{childList:true,subtree:true,attributes:true});}catch(e){}'
            . '  }'
            . '})();</script>'
            . '</body></html>';
        exit;
    }

    /**
     * Shared iyzico init logic — runs ownership + amount checks and returns
     * either the checkout_form_content or a structured error.
     *
     * Fiyat, ödeme sayfası (PackageController::processPayment) ile aynı:
     * full package satırından getDiscountedPrice; gerekirse ham price_* yedek.
     * (Eski: subscription.amount katalog fiyatıydı, iyzico butonu indirimli
     * özetiyle çakışıyordu.)
     *
     * @return array{success:bool,status?:int,error?:string,token?:string,conversation_id?:string,checkout_form_content?:string}
     */
    private function prepareIyzicoCheckout(string $subscriptionId): array {
        if ($subscriptionId === '') {
            return ['success' => false, 'status' => 400, 'error' => 'Subscription ID required'];
        }

        $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
        $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
        if (!$subscription) {
            return ['success' => false, 'status' => 404, 'error' => 'Subscription not found'];
        }

        $userEmail = $_SESSION['username'] ?? '';
        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = $customerRepo->findByEmail($userEmail);
        if (!$customer) {
            $customerId = \App\Core\TenantResolver::resolve();
            if ($customerId) {
                $customer = $customerRepo->findById($customerId);
            }
        }
        if (!$customer) {
            return ['success' => false, 'status' => 404, 'error' => 'Customer not found'];
        }

        // Ownership: subscription must belong to the session customer.
        $subBusinessId = $subscription['business_id'] ?? $subscription['customer_id'] ?? $subscription['tenant_id'] ?? null;
        if (!$subBusinessId || $subBusinessId !== ($customer['customer_id'] ?? null)) {
            \App\Core\Logger::warning('prepareIyzicoCheckout: subscription does not belong to session customer', [
                'subscription_id' => $subscriptionId,
                'session_customer' => $customer['customer_id'] ?? null,
                'subscription_customer' => $subBusinessId,
            ]);
            return ['success' => false, 'status' => 403, 'error' => 'Bu abonelik için ödeme başlatma yetkiniz yok.'];
        }

        $billingCycle = $subscription['billing_cycle'] ?? 'yearly';
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $packageForPrice = $packageService->getPackageById($subscription['package_id'] ?? '');
        if (!$packageForPrice) {
            return ['success' => false, 'status' => 404, 'error' => 'Paket bulunamadı.'];
        }
        $amount = (float) $packageService->getDiscountedPrice($packageForPrice, $billingCycle);
        if ($amount <= 0) {
            $priceField = 'price_' . $billingCycle;
            $amount = (float)($packageForPrice[$priceField] ?? 0);
        }
        if ($amount <= 0) {
            return ['success' => false, 'status' => 400, 'error' => 'Geçerli tutar bulunamadı.'];
        }

        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $iyzicoGateway = $paymentGatewayService->getGateway('iyzico');
        if (!$iyzicoGateway || !$iyzicoGateway->isEnabled()) {
            return ['success' => false, 'status' => 400, 'error' => 'iyzico gateway is not enabled'];
        }

        $conversationId = 'SUBS_' . $subscriptionId . '_' . time();
        $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if (empty($customerName)) {
            $customerName = $customer['company_name'] ?? 'Müşteri';
        }
        $nameParts = explode(' ', $customerName, 2);

        $paymentData = [
            'order_id' => $conversationId,
            'amount' => $amount,
            'customer_id' => $customer['customer_id'] ?? '',
            'customer_name' => $nameParts[0] ?? 'Müşteri',
            'customer_surname' => $nameParts[1] ?? '',
            'customer_email' => $customer['email'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'customer_address' => 'Türkiye',
            'customer_city' => 'Istanbul',
            'customer_country' => 'Turkey',
            'customer_zip' => '34000',
            'customer_identity' => '00000000000',
            'success_url' => BASE_URL . '/customer/payment/iyzico/callback',
            'fail_url' => BASE_URL . '/customer/payment/iyzico/callback',
            'basket' => [
                [
                    'name' => $subscription['package_name'] ?? 'Paket Abonelik',
                    'price' => $amount,
                    'category' => 'Abonelik',
                ]
            ]
        ];

        $result = $iyzicoGateway->processPayment($paymentData);
        if (empty($result['success'])) {
            return ['success' => false, 'status' => 400, 'error' => $result['error'] ?? 'iyzico payment initialization failed'];
        }

        $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
        require_once __DIR__ . '/../../helpers/functions.php';
        $paymentRepo->create([
            'payment_id' => generateId('pay'),
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'currency' => 'TRY',
            'payment_method' => 'iyzico',
            'payment_status' => 'pending',
            'merchant_oid' => $conversationId,
            'gateway_transaction_id' => $result['token'] ?? null,
            'payment_date' => null,
        ]);

        return [
            'success' => true,
            'checkout_form_content' => $result['checkout_form_content'] ?? '',
            'token' => $result['token'] ?? null,
            'conversation_id' => $conversationId,
        ];
    }
    
    /**
     * iyzico callback handler
     */
    public function iyzicoCallback() {
        // Mobil akışta `initiateIyzicoPayment` callback URL'ine
        // `?mobile=1&return=qordy://...` enjekte ediyor; callback
        // sonucunda tarayıcıyı HTTP yerine uygulamanın custom scheme'ine
        // geri almamız gerekiyor. Bu bayrağı her ihtimale karşı iki
        // kaynaktan da okuyoruz (POST formu bazı iyzico modlarında query
        // string'i korumuyor).
        $isMobile = (($_GET['mobile'] ?? '') === '1') || (($_POST['mobile'] ?? '') === '1');
        $mobileReturnRaw = $_GET['return'] ?? $_POST['return'] ?? '';
        $mobileReturn = is_string($mobileReturnRaw) ? trim($mobileReturnRaw) : '';

        // Open-redirect defence: client-supplied `return` URL must be
        // either our own `qordy://` custom scheme (mobile app deeplink)
        // or an https URL on *.qordy.com. Anything else is dropped so
        // attackers can't turn a successful payment into a post-auth
        // redirect to a phishing host.
        if ($mobileReturn !== '') {
            $allowed = false;
            if (stripos($mobileReturn, 'qordy://') === 0) {
                $allowed = true;
            } else {
                $parsed = @parse_url($mobileReturn);
                if (
                    is_array($parsed)
                    && ($parsed['scheme'] ?? '') === 'https'
                    && !empty($parsed['host'])
                    && (
                        strcasecmp($parsed['host'], 'qordy.com') === 0
                        || preg_match('/\.qordy\.com$/i', $parsed['host']) === 1
                    )
                ) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                \App\Core\Logger::warning('iyzico callback: blocked disallowed return url', [
                    'return' => substr($mobileReturn, 0, 200),
                ]);
                $mobileReturn = '';
                $isMobile = false;
            }
        }

        $redirectMobileOrHttp = function (string $status, array $params = []) use ($isMobile, $mobileReturn): void {
            if ($isMobile && $mobileReturn !== '') {
                $qs = http_build_query(array_merge(['status' => $status], $params));
                $joiner = str_contains($mobileReturn, '?') ? '&' : '?';
                $this->renderMobileDeeplinkBridge($mobileReturn . $joiner . $qs, $status, $params);
                exit;
            }

            // Browser flow — we're almost always inside the iyzico iframe
            // (rendered by /customer/payment/iyzico/frame). The parent
            // window still has a live, same-site session; the iframe does
            // not (SameSite=Lax strips cookies on the cross-site POST from
            // iyzico back to us). So we render a tiny bridge page that
            // posts a message to the parent and lets it do the final
            // top-level redirect, where session auth works correctly.
            $tx = isset($params['transaction_id']) ? (string)$params['transaction_id'] : '';
            $err = isset($params['error']) ? (string)$params['error'] : '';
            $subId = (string)($params['subscription_id'] ?? '');

            // 3D Secure bazen iframe dışında window.top'a döner; özet+aynı URL için
            // sonucu /customer/payment?pay_result=... üzerinde göster (yeni sekmesiz, checkout bağlamı).
            if ($subId !== '') {
                if ($status === 'success') {
                    $q = ['subscription_id' => $subId, 'pay_result' => 'success'];
                    if ($tx !== '') {
                        $q['transaction_id'] = $tx;
                    }
                    $fallbackUrl = BASE_URL . '/customer/payment?' . http_build_query($q);
                } else {
                    $fallbackUrl = BASE_URL . '/customer/payment?' . http_build_query([
                        'subscription_id' => $subId,
                        'pay_result' => 'fail',
                        'error' => $err !== '' ? $err : 'Ödeme tamamlanamadı',
                    ]);
                }
            } else {
                $fallbackUrl = $status === 'success'
                    ? (BASE_URL . '/customer/payment/success' . ($tx !== '' ? '?transaction_id=' . rawurlencode($tx) : ''))
                    : (BASE_URL . '/customer/payment/fail?error=' . rawurlencode($err !== '' ? $err : 'Ödeme tamamlanamadı'));
            }
            $this->renderIyzicoBridge($status, $fallbackUrl, [
                'transaction_id' => $tx,
                'error' => $err,
            ]);
            exit;
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['token'] ?? '';
            
            if (empty($token)) {
                \App\Core\Logger::error('iyzico callback: empty token');
                $redirectMobileOrHttp('fail', ['error' => 'Geçersiz ödeme verisi']);
                return;
            }
            
            $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
            $iyzicoGateway = $paymentGatewayService->getGateway('iyzico');
            
            if (!$iyzicoGateway) {
                \App\Core\Logger::error('iyzico callback: gateway not found');
                $redirectMobileOrHttp('fail', ['error' => 'Gateway bulunamadı']);
                return;
            }
            
            $result = $iyzicoGateway->handleCallback(['token' => $token]);
            
            $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
            $payment = $paymentRepo->getByGatewayTransactionId($token);
            
            if (!$payment) {
                $conversationId = $result['conversation_id'] ?? '';
                if ($conversationId) {
                    $payment = $paymentRepo->getByMerchantOid($conversationId);
                }
            }
            
            if ($result['success'] && $result['verified']) {
                // A verified iyzico success without a matching local
                // payment row is a reconciliation problem we shouldn't
                // paper over with a cheerful success page. Fail loud.
                if (!$payment) {
                    \App\Core\Logger::error('iyzico callback: verified but no local payment row', [
                        'token_prefix'    => substr((string)$token, 0, 12),
                        'conversation_id' => $result['conversation_id'] ?? null,
                    ]);
                    $redirectMobileOrHttp('fail', [
                        'error' => 'Ödeme kaydı bulunamadı. Lütfen destek ekibimizle iletişime geçin.',
                        'token' => $token,
                    ]);
                    return;
                }

                // Idempotency: do not reactivate / double-mark a payment that
                // has already been completed (iyzico is known to retry).
                if (($payment['payment_status'] ?? '') === 'completed') {
                    \App\Core\Logger::info('iyzico callback: duplicate success ignored', [
                        'payment_id' => $payment['payment_id']
                    ]);
                    $redirectMobileOrHttp('success', [
                        'transaction_id' => (string)($result['payment_id'] ?? ''),
                        'token' => $token,
                        'subscription_id' => (string)($payment['subscription_id'] ?? ''),
                    ]);
                    return;
                }

                // Amount sanity check — the gateway must confirm the exact
                // amount we charged. Anything less is a tamper / mis-match.
                // NOTE: we treat a missing/zero paid amount as suspicious
                // rather than benign — refuse to complete in that case.
                $paidAmount = isset($result['amount']) ? (float)$result['amount'] : 0.0;
                $expectedAmount = (float)($payment['amount'] ?? 0);
                if ($expectedAmount > 0 && ($paidAmount <= 0 || abs($paidAmount - $expectedAmount) > 0.01)) {
                    \App\Core\Logger::error('iyzico callback: amount mismatch', [
                        'payment_id' => $payment['payment_id'],
                        'expected' => $expectedAmount,
                        'paid' => $paidAmount,
                    ]);
                    $paymentRepo->update($payment['payment_id'], [
                        'payment_status' => 'failed',
                        'payment_date' => date('Y-m-d H:i:s')
                    ]);
                    $redirectMobileOrHttp('fail', [
                        'error' => 'Ödeme tutarı eşleşmiyor.',
                        'subscription_id' => (string)($payment['subscription_id'] ?? ''),
                    ]);
                    return;
                }

                // Atomic "claim" of the pending row: only one concurrent
                // callback for the same token should flip pending→completed
                // and fire activation. If another worker beat us to it
                // (rowCount === 0 and status is already completed), we
                // short-circuit to the idempotent success path.
                $claimed = false;
                try {
                    $db = \App\Core\DependencyFactory::getDatabase();
                    $upd = $db->prepare(
                        "UPDATE subscription_payments SET payment_status = 'completed',
                                gateway_transaction_id = :gw,
                                payment_date = NOW()
                         WHERE payment_id = :pid AND payment_status = 'pending'"
                    );
                    $upd->execute([
                        ':gw'  => $result['payment_id'] ?? $result['transaction_id'] ?? $token,
                        ':pid' => $payment['payment_id'],
                    ]);
                    $claimed = ($upd->rowCount() > 0);
                } catch (\Throwable $e) {
                    // Fall back to the generic update helper if the direct
                    // query path isn't available — we'd rather over-update
                    // than miss the activation entirely.
                    \App\Core\Logger::warning('iyzico callback: atomic claim fallback', [
                        'error' => $e->getMessage(),
                    ]);
                    $paymentRepo->update($payment['payment_id'], [
                        'payment_status' => 'completed',
                        'gateway_transaction_id' => $result['payment_id'] ?? $result['transaction_id'] ?? $token,
                        'payment_date' => date('Y-m-d H:i:s')
                    ]);
                    $claimed = true;
                }

                if (!$claimed) {
                    \App\Core\Logger::info('iyzico callback: lost race, another worker completed it', [
                        'payment_id' => $payment['payment_id']
                    ]);
                    $redirectMobileOrHttp('success', [
                        'transaction_id' => (string)($result['payment_id'] ?? ''),
                        'token' => $token,
                        'subscription_id' => (string)($payment['subscription_id'] ?? ''),
                    ]);
                    return;
                }

                try {
                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $subscriptionService->activateSubscription($payment['subscription_id']);
                    \App\Core\Logger::info('iyzico payment successful - subscription activated', [
                        'subscription_id' => $payment['subscription_id'],
                        'payment_id' => $payment['payment_id'],
                        'amount' => $result['amount'] ?? 0
                    ]);
                } catch (\Exception $e) {
                    // Activation failing after we've captured the money
                    // is a support-case-worthy incident. We log loudly
                    // and STILL redirect to success — walking the user
                    // back to "fail" at this point is confusing because
                    // they WERE charged. The support team will reconcile
                    // via the payment record (now marked completed).
                    \App\Core\Logger::critical('iyzico callback: subscription activation failed after capture', [
                        'error' => $e->getMessage(),
                        'subscription_id' => $payment['subscription_id'],
                        'payment_id' => $payment['payment_id'],
                    ]);
                }

                $redirectMobileOrHttp('success', [
                    'transaction_id' => (string)($result['payment_id'] ?? ''),
                    'token' => $token,
                    'subscription_id' => (string)($payment['subscription_id'] ?? ''),
                ]);
                return;
            } else {
                if ($payment) {
                    $paymentRepo->update($payment['payment_id'], [
                        'payment_status' => 'failed',
                        'payment_date' => date('Y-m-d H:i:s')
                    ]);
                }

                $error = $result['error'] ?? 'Ödeme başarısız oldu';
                $failParams = ['error' => $error, 'token' => $token];
                if ($payment && !empty($payment['subscription_id'])) {
                    $failParams['subscription_id'] = (string) $payment['subscription_id'];
                }
                $redirectMobileOrHttp('fail', $failParams);
                return;
            }
        }

        // Non-POST hit on the callback endpoint. This should basically
        // never happen in the real flow — iyzico always POSTs. Some
        // clients (or the occasional browser refresh of the callback
        // URL) land here though. We REFUSE to trust `?status=success`
        // from the query string; the user must go through the real
        // callback. Show a neutral "tamamlanamadı" page.
        \App\Core\Logger::info('iyzico callback: non-POST request, refusing to trust query status', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'query'  => $_SERVER['QUERY_STRING'] ?? '',
        ]);
        $redirectMobileOrHttp('fail', ['error' => 'Ödeme sonucu doğrulanamadı. Lütfen tekrar deneyin.']);
    }

    /**
     * iyzico hosted form bize tam gövdeli bir sayfa iade ediyor; mobil
     * WebView `qordy://payment/return?...` gibi custom scheme'leri
     * navigation delegate'i ile yakalayıp kapatıyor. Bazı Android
     * WebView sürümleri `<meta refresh>` veya `Location:` header'ı
     * tetiklemeden önce sayfayı parse ediyor — bu yüzden hem anlık
     * JS redirect hem fallback link birlikte veriliyor.
     */
    private function renderMobileDeeplinkBridge(string $deepLink, string $status, array $params = []): void {
        $safeDeep = htmlspecialchars($deepLink, ENT_QUOTES, 'UTF-8');
        $jsDeep = json_encode($deepLink, JSON_UNESCAPED_SLASHES);
        $title = $status === 'success' ? 'Ödeme Başarılı' : 'Ödeme Sonucu';
        $headline = $status === 'success' ? 'Ödemeniz tamamlandı' : 'Uygulamaya dönülüyor';
        $sub = $status === 'success'
            ? 'Aboneliğiniz aktifleştiriliyor, uygulamaya geri yönlendiriliyorsunuz.'
            : ($params['error'] ?? 'Uygulamaya dönmek için lütfen bekleyin.');
        $safeSub = htmlspecialchars($sub, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>\n<html lang=\"tr\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            . "<title>{$title}</title>"
            . "<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f7f7fb;color:#111;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px} .card{background:#fff;border-radius:16px;padding:28px 24px;max-width:420px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.06);text-align:center} h1{font-size:20px;margin:0 0 10px} p{font-size:15px;color:#555;line-height:1.5} .btn{display:inline-block;margin-top:18px;background:#7c3aed;color:#fff;padding:12px 20px;border-radius:12px;text-decoration:none;font-weight:600} .ic{width:56px;height:56px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff} .ok{background:#10b981}.bad{background:#ef4444}</style>"
            . "</head><body><div class=\"card\"><div class=\"ic " . ($status === 'success' ? 'ok' : 'bad') . "\">" . ($status === 'success' ? '&#10003;' : '&#33;') . "</div>"
            . "<h1>{$headline}</h1><p>{$safeSub}</p>"
            . "<a class=\"btn\" href=\"{$safeDeep}\">Uygulamaya Dön</a></div>"
            . "<script>setTimeout(function(){try{window.location.replace({$jsDeep});}catch(e){window.location.href={$jsDeep};}},150);</script>"
            . "</body></html>";
    }

    /**
     * Render the post-3DS bridge page that iyzico POSTs into. This page
     * is almost always loaded inside the iyzico iframe on payment.php and
     * is responsible for:
     *
     *   1) Telling the parent window what happened via postMessage so it
     *      can navigate itself — the parent still has session cookies,
     *      the iframe usually does not.
     *   2) Working as a fallback hard redirect when the bridge is opened
     *      directly (no parent — some 3DS flows break out of the iframe).
     *
     * Security: we intentionally only post to `window.location.origin`
     * (set by the browser for the parent) rather than "*". Combined with
     * the `X-Frame-Options: SAMEORIGIN` on the frame, this prevents a
     * malicious site from embedding the iframe and spoofing results.
     */
    private function renderIyzicoBridge(string $status, string $fallbackUrl, array $params = []): void {
        $payload = [
            'type' => 'iyzico:result',
            'status' => $status === 'success' ? 'success' : 'fail',
            'transactionId' => (string)($params['transaction_id'] ?? ''),
            'error' => (string)($params['error'] ?? ''),
        ];
        $jsPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsFallback = json_encode($fallbackUrl, JSON_UNESCAPED_SLASHES);
        $title = $status === 'success' ? 'Ödeme Tamamlandı' : 'Ödeme Sonucu';
        $headline = $status === 'success' ? 'Ödemeniz onaylandı' : 'Ödeme tamamlanamadı';
        $safeSub = htmlspecialchars(
            $status === 'success'
                ? 'Aboneliğiniz aktifleştiriliyor, yönlendiriliyorsunuz…'
                : ($params['error'] !== '' ? $params['error'] : 'Bir sorun oluştu, lütfen tekrar deneyin.'),
            ENT_QUOTES,
            'UTF-8'
        );
        $iconHtml = $status === 'success' ? '&#10003;' : '&#33;';
        $iconClass = $status === 'success' ? 'ok' : 'bad';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Frame-Options: SAMEORIGIN');
        }

        echo "<!DOCTYPE html>\n<html lang=\"tr\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
            . "<title>{$title}</title>"
            . "<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f7f7fb;color:#111;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.card{background:#fff;border-radius:16px;padding:28px 24px;max-width:420px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.06);text-align:center}h1{font-size:20px;margin:0 0 10px;font-weight:800}p{font-size:15px;color:#555;line-height:1.5;margin:0}.ic{width:56px;height:56px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff}.ok{background:#10b981}.bad{background:#ef4444}</style>"
            . "</head><body><div class=\"card\"><div class=\"ic {$iconClass}\">{$iconHtml}</div>"
            . "<h1>{$headline}</h1><p>{$safeSub}</p></div>"
            . "<script>(function(){var payload={$jsPayload};var fallback={$jsFallback};var posted=false;"
            . "try{if(window.parent&&window.parent!==window){window.parent.postMessage(payload,window.location.origin);posted=true;}"
            . "else if(window.top&&window.top!==window){window.top.postMessage(payload,window.location.origin);posted=true;}}catch(e){}"
            // If we're inside the iframe, we stop here — the parent page
            // will drive the navigation. If the bridge page is actually
            // the top window (3DS broke out of the frame / direct link),
            // perform the fallback redirect. We wait briefly so the
            // user sees the confirmation screen either way.
            . "setTimeout(function(){try{if(window===window.top){window.location.replace(fallback);}else if(!posted){window.location.replace(fallback);}}catch(_e){window.location.href=fallback;}},900);"
            . "})();</script>"
            . "</body></html>";
    }

    /**
     * Save card token
     */
    public function saveCard() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->setFlash('payment_error', 'Geçersiz istek.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $userEmail = $_SESSION['username'] ?? '';
        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = $customerRepo->findByEmail($userEmail);
        
        if (!$customer) {
            $this->toastNotificationService->setFlash('payment_error', 'Müşteri bilgileri bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $cardData = [
            'token' => $requestData['token'] ?? '',
            'last4' => $requestData['last4'] ?? '',
            'brand' => $requestData['brand'] ?? '',
            'expiry_month' => $requestData['expiry_month'] ?? null,
            'expiry_year' => $requestData['expiry_year'] ?? null,
            'gateway' => 'iyzico',
            'is_default' => isset($requestData['is_default']) ? 1 : 0
        ];
        
        $result = $this->paymentService->saveCardToken($customer['customer_id'], $cardData);
        
        if ($result['success']) {
            $this->toastNotificationService->setFlash('payment_success', 'Kart bilgileriniz kaydedildi.');
        } else {
            $this->toastNotificationService->setFlash('payment_error', $result['error'] ?? 'Kart kaydedilemedi.');
        }
        
        header('Location: ' . BASE_URL . '/customer/saved-cards');
        exit;
    }
    
    /**
     * Delete saved card
     */
    public function deleteCard($savedCardId) {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->setFlash('payment_error', 'Geçersiz istek.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $userEmail = $_SESSION['username'] ?? '';
        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = $customerRepo->findByEmail($userEmail);
        
        if (!$customer) {
            $this->toastNotificationService->setFlash('payment_error', 'Müşteri bilgileri bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $savedCardRepo = \App\Core\DependencyFactory::getSavedPaymentMethodRepository();
        $savedCard = $savedCardRepo->findById($savedCardId);
        
        if (!$savedCard || $savedCard['customer_id'] !== $customer['customer_id']) {
            $this->toastNotificationService->setFlash('payment_error', 'Kart bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $result = $savedCardRepo->deactivate($savedCardId);
        
        if ($result) {
            $this->toastNotificationService->setFlash('payment_success', 'Kart bilgileriniz silindi.');
        } else {
            $this->toastNotificationService->setFlash('payment_error', 'Kart silinemedi.');
        }
        
        header('Location: ' . BASE_URL . '/customer/saved-cards');
        exit;
    }
    
    /**
     * Set default card
     */
    public function setDefaultCard($savedCardId) {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->setFlash('payment_error', 'Geçersiz istek.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $userEmail = $_SESSION['username'] ?? '';
        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = $customerRepo->findByEmail($userEmail);
        
        if (!$customer) {
            $this->toastNotificationService->setFlash('payment_error', 'Müşteri bilgileri bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $savedCardRepo = \App\Core\DependencyFactory::getSavedPaymentMethodRepository();
        $savedCard = $savedCardRepo->findById($savedCardId);
        
        if (!$savedCard || $savedCard['customer_id'] !== $customer['customer_id']) {
            $this->toastNotificationService->setFlash('payment_error', 'Kart bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/saved-cards');
            exit;
        }
        
        $result = $savedCardRepo->setDefault($savedCardId, $customer['customer_id']);
        
        if ($result) {
            $this->toastNotificationService->setFlash('payment_success', 'Varsayılan kart olarak ayarlandı.');
        } else {
            $this->toastNotificationService->setFlash('payment_error', 'Kart ayarlanamadı.');
        }
        
        header('Location: ' . BASE_URL . '/customer/saved-cards');
        exit;
    }
}
