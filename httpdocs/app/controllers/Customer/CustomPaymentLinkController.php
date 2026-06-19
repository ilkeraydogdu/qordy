<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\Logger;

/**
 * Public-facing flow for personalized super-admin payment links.
 *
 * Routes:
 *   GET  /pay/{token}                        -> show()
 *   POST /pay/{token}/start                  -> start()          (iyzico init)
 *   POST /api/payment/iyzico/custom-link-cb  -> iyzicoCallback() (gateway return)
 *
 * The gateway callback uses a token-less, bypass-safe URL. The link is
 * recovered by looking up the `custom_payment_link_intents` row keyed by
 * the iyzico checkout token that iyzico POSTs back to us. This avoids
 * relying on $_SESSION, which browsers strip on cross-origin POSTs when
 * our session cookie is SameSite=Lax (the default).
 */
class CustomPaymentLinkController extends Controller {
    /** @var \App\Services\CustomPaymentLinkService */
    protected $linkService;
    /** @var \App\Repositories\CustomPaymentLinkIntentRepository */
    protected $intentRepo;

    public function __construct() {
        parent::__construct();
        $this->linkService = \App\Core\DependencyFactory::getCustomPaymentLinkService();
        $this->intentRepo  = \App\Core\DependencyFactory::getCustomPaymentLinkIntentRepository();
    }

    /**
     * Public landing for a tokenized payment link. Does not require
     * login — for existing customers we prompt to authenticate before
     * they can start the payment.
     */
    public function show($token = '') {
        $link = $this->linkService->getByToken($token);

        if (!$link) {
            $this->render('customer/custom_payment_link_invalid', [
                'title'   => 'Bağlantı geçersiz',
                'message' => 'Bu ödeme bağlantısı bulunamadı.',
            ]);
            return;
        }

        $check = $this->linkService->canConsume($link);
        if (!$check['ok']) {
            $this->render('customer/custom_payment_link_invalid', [
                'title'   => 'Bağlantı kullanılamıyor',
                'message' => $check['reason'],
            ]);
            return;
        }

        $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
        $package = $packageRepo->findById($link['package_id']);

        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        $sessionCustomerId = \App\Core\TenantResolver::resolve();

        $mismatch = false;
        if ($link['mode'] === 'existing_customer' && $isLoggedIn && $sessionCustomerId
            && $sessionCustomerId !== $link['customer_id']) {
            $mismatch = true;
        }

        $this->render('customer/custom_payment_link_show', [
            'title'             => 'Özel Ödeme Bağlantısı',
            'link'              => $link,
            'package'           => $package,
            'public_url'        => $this->linkService->buildUrl($link['token']),
            'is_logged_in'      => $isLoggedIn,
            'session_customer'  => $sessionCustomerId,
            'customer_mismatch' => $mismatch,
        ]);
    }

    /**
     * Initiate the iyzico checkout for this link. For existing
     * customer mode we require an authenticated session. For new
     * customer mode we create a minimal customer + user account
     * first so the callback has a customer to attach the
     * subscription to.
     */
    public function start($token = '') {
        $link = $this->linkService->getByToken($token);
        $check = $link ? $this->linkService->canConsume($link) : ['ok' => false, 'reason' => 'Bağlantı yok'];
        if (!$link || !$check['ok']) {
            $this->flashAndRedirect('error', $check['reason'] ?? 'Bu bağlantı geçersiz.', '/pay/' . $token);
            return;
        }

        $customerId = null;
        $email = null;
        $name  = null;
        $phone = null;

        if ($link['mode'] === 'existing_customer') {
            if (empty($_SESSION['logged_in'])) {
                $this->flashAndRedirect('warning', 'Ödemeye devam etmek için giriş yapmanız gerekir.', '/login?redirect=' . urlencode('/pay/' . $token));
                return;
            }
            $sessionCustomerId = \App\Core\TenantResolver::resolve();
            if (!$sessionCustomerId || $sessionCustomerId !== $link['customer_id']) {
                $this->flashAndRedirect('error', 'Bu bağlantı farklı bir müşteriye ait. Lütfen bağlantı sahibinin hesabıyla giriş yapın.', '/pay/' . $token);
                return;
            }
            $customerId = $sessionCustomerId;
            $customer = \App\Core\DependencyFactory::getCustomerRepository()->findById($customerId);
            $email = $customer['email'] ?? '';
            $name  = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))
                    ?: ($customer['company_name'] ?? 'Müşteri');
            $phone = $customer['phone'] ?? '';
        } else {
            // new_customer mode — we create the customer record *now*
            // so the callback can attach the subscription. The
            // account is inactive until payment completes.
            $email = strtolower(trim((string)$link['target_email']));
            $name  = trim((string)($link['target_name'] ?? '')) ?: $email;

            $existingUser = \App\Core\DependencyFactory::getUserService()->findByEmail($email);
            $existingCustomer = \App\Core\DependencyFactory::getCustomerRepository()->findByEmail($email);

            if ($existingCustomer) {
                $customerId = $existingCustomer['customer_id'];
            } else {
                $firstName = $name;
                $lastName  = '';
                if (strpos($name, ' ') !== false) {
                    [$firstName, $lastName] = explode(' ', $name, 2);
                }

                $tempPassword = bin2hex(random_bytes(8));
                $registerResult = \App\Core\DependencyFactory::getCustomerService()->register([
                    'email'        => $email,
                    'password'     => $tempPassword,
                    'first_name'   => $firstName,
                    'last_name'    => $lastName,
                    'phone'        => '',
                    'company_name' => $name,
                    'subdomain'    => null,
                ]);
                if (empty($registerResult['success'])) {
                    Logger::error('CustomPaymentLinkController: register failed', [
                        'email' => $email,
                        'error' => $registerResult['error'] ?? 'unknown',
                    ]);
                    $this->flashAndRedirect('error', 'Hesap oluşturulamadı: ' . ($registerResult['error'] ?? ''), '/pay/' . $token);
                    return;
                }
                $customerId = $registerResult['customer_id'] ?? null;
                // No need to keep the random temp password around — the
                // user will set their own password on the success page.
                // We only persist enough activation context so that:
                //  a) only this browser session can claim the account
                //     on the success page (activation key check), and
                //  b) the iyzico callback can find the customer.
                unset($tempPassword);
            }
            unset($existingUser);

            // Activation context — bound to this browser's PHP session.
            // Used by success()/activate() to prove that the party
            // landing on /pay/{token}/success is the same browser that
            // initiated the purchase, so only they can set the initial
            // password. Lost sessions fall back to email reset below.
            if ($customerId) {
                $_SESSION['cpl_activation_' . $token] = [
                    'customer_id' => $customerId,
                    'started_at'  => time(),
                ];
            }
        }

        if (!$customerId) {
            $this->flashAndRedirect('error', 'Müşteri kaydı bulunamadı.', '/pay/' . $token);
            return;
        }

        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $iyzicoGateway = $paymentGatewayService->getGateway('iyzico');

        if (!$iyzicoGateway || !$iyzicoGateway->isEnabled()) {
            $this->flashAndRedirect('error', 'iyzico ödeme yöntemi şu anda aktif değil.', '/pay/' . $token);
            return;
        }

        $amount = (float)$link['custom_price'];
        $conversationId = 'CPL_' . $link['link_id'] . '_' . time();
        $nameParts = explode(' ', trim((string)$name), 2);

        $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
        $package = $packageRepo->findById($link['package_id']);
        $packageName = $package['name'] ?? 'Abonelik';

        $paymentData = [
            'order_id'          => $conversationId,
            'amount'            => $amount,
            'customer_id'       => $customerId,
            'customer_name'     => $nameParts[0] ?? 'Müşteri',
            'customer_surname'  => $nameParts[1] ?? 'Musteri',
            'customer_email'    => $email,
            'customer_phone'    => $phone ?? '05555555555',
            'customer_address'  => 'Türkiye',
            'customer_city'     => 'Istanbul',
            'customer_country'  => 'Turkey',
            'customer_zip'      => '34000',
            'customer_identity' => '00000000000',
            'success_url'       => BASE_URL . '/api/payment/iyzico/custom-link-callback',
            'fail_url'          => BASE_URL . '/api/payment/iyzico/custom-link-callback',
            'basket'            => [[
                'name'     => $packageName . ' — ' . (int)$link['duration_months'] . ' ay',
                'price'    => $amount,
                'category' => 'Abonelik',
            ]],
        ];

        $result = $iyzicoGateway->processPayment($paymentData);

        if (empty($result['success'])) {
            Logger::error('CustomPaymentLinkController: iyzico init failed', [
                'link_id' => $link['link_id'],
                'error'   => $result['error'] ?? 'unknown',
            ]);
            $this->flashAndRedirect('error', 'Ödeme başlatılamadı: ' . ($result['error'] ?? ''), '/pay/' . $token);
            return;
        }

        // Persist the pending intent in the DB — the iyzico callback is a
        // cross-origin POST so the SameSite=Lax session cookie is stripped
        // and $_SESSION is NOT available there. Keyed by the iyzico token.
        require_once __DIR__ . '/../../helpers/functions.php';
        try {
            $this->intentRepo->insert([
                'intent_id'       => generateId('cpi'),
                'gateway_token'   => (string)$result['token'],
                'link_id'         => (string)$link['link_id'],
                'customer_id'     => (string)$customerId,
                'conversation_id' => (string)$conversationId,
                'amount'          => $amount,
                'currency'        => (string)($link['currency'] ?? 'TRY'),
                'status'          => 'pending',
            ]);
        } catch (\Throwable $e) {
            Logger::error('CustomPaymentLinkController: could not persist intent', [
                'error' => $e->getMessage(),
                'link_id' => $link['link_id'],
            ]);
            $this->flashAndRedirect('error', 'Ödeme başlatılamadı (intent).', '/pay/' . $token);
            return;
        }

        $html = $result['checkout_form_content'] ?? '';
        $this->render('customer/custom_payment_link_checkout', [
            'title'   => 'Ödemeye Yönlendiriliyor',
            'form'    => $html,
            'token'   => $result['token'] ?? '',
            'package' => $package,
            'link'    => $link,
        ]);
    }

    /**
     * iyzico callback for custom payment links. Verifies the payment
     * with the gateway, creates the subscription, marks the link
     * consumed, and redirects to a success page.
     *
     * Called as `POST /api/payment/iyzico/custom-link-callback` with the
     * iyzico checkout token in the POST body. We look up the pending
     * intent (persisted at `start()`) to recover the link_id + customer_id.
     */
    public function iyzicoCallback($_unused = '') {
        // iyzico usually POSTs on success but may GET-redirect on some
        // 3D-Secure failure paths; accept either.
        $iyzicoToken = $_POST['token'] ?? $_GET['token'] ?? '';
        if (empty($iyzicoToken)) {
            Logger::error('CustomPaymentLinkController callback: empty iyzico token');
            $this->renderCustomLinkBridge('fail', BASE_URL . '/customer/payment/fail?error=' . rawurlencode('Geçersiz ödeme verisi.'), 'Geçersiz ödeme verisi.');
            return;
        }

        $intent = $this->intentRepo->findByGatewayToken($iyzicoToken);
        if (!$intent) {
            Logger::error('CustomPaymentLinkController callback: no intent for token', [
                'iyzico_token_prefix' => substr($iyzicoToken, 0, 12)
            ]);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/customer/payment/fail?error=' . rawurlencode('Ödeme oturumu bulunamadı.'), 'Ödeme oturumu bulunamadı.');
            return;
        }

        $link = \App\Core\DependencyFactory::getCustomPaymentLinkRepository()
            ->findById((string)$intent['link_id']);
        if (!$link) {
            Logger::error('CustomPaymentLinkController callback: link gone', ['link_id' => $intent['link_id']]);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/customer/payment/fail?error=' . rawurlencode('Bağlantı bulunamadı.'), 'Bağlantı bulunamadı.');
            return;
        }

        $linkPublicToken = (string)$link['token'];

        // Re-verify the link is still consumable at callback time. An
        // admin may have revoked / deactivated / expired it while the
        // user was on the 3DS screen, or a multi-use link may have
        // just hit its cap via a parallel checkout. An already-
        // completed intent is treated as idempotent (see the duplicate
        // branch below), so canConsume failures here mean the link was
        // actively invalidated after checkout started.
        if (($intent['status'] ?? '') === 'pending') {
            $check = $this->linkService->canConsume($link);
            if (empty($check['ok'])) {
                Logger::warning('CustomPaymentLinkController callback: link no longer consumable', [
                    'link_id' => $link['link_id'],
                    'reason'  => $check['reason'] ?? 'unknown',
                ]);
                $this->intentRepo->markFailed((string)$intent['intent_id']);
                $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1',
                    (string)($check['reason'] ?? 'Bu ödeme bağlantısı artık kullanılamıyor.'));
                return;
            }
        }

        // Idempotency — ignore duplicate callbacks for the same intent.
        if (($intent['status'] ?? '') !== 'pending') {
            Logger::info('CustomPaymentLinkController callback: duplicate ignored', [
                'intent_id' => $intent['intent_id'],
                'status'    => $intent['status'],
            ]);
            $this->renderCustomLinkBridge('success', BASE_URL . '/pay/' . $linkPublicToken . '/success', '');
            return;
        }

        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $iyzicoGateway = $paymentGatewayService->getGateway('iyzico');
        if (!$iyzicoGateway) {
            $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1', 'Ödeme ağı kullanılamıyor.');
            return;
        }

        $result = $iyzicoGateway->handleCallback(['token' => $iyzicoToken]);

        if (empty($result['success']) || empty($result['verified'])) {
            Logger::warning('CustomPaymentLinkController: iyzico verification failed', [
                'link_id' => $link['link_id'],
                'error'   => $result['error'] ?? 'unknown',
            ]);
            $this->intentRepo->markFailed((string)$intent['intent_id']);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1', (string)($result['error'] ?? 'Doğrulama başarısız.'));
            return;
        }

        // Amount guard — reject anything that isn't the exact price we
        // charged. Even though iyzico is authoritative, a tamper window
        // (DOM overrides, MITM) shouldn't be able to activate a sub for
        // a different price than the one the link was created with.
        // Zero/missing paid amounts are also refused here (no silent
        // skip) — a verified response with no amount is suspicious.
        $paidAmount = (float)($result['amount'] ?? 0);
        $expected   = (float)$intent['amount'];
        if ($expected > 0 && ($paidAmount <= 0 || abs($paidAmount - $expected) > 0.01)) {
            Logger::error('CustomPaymentLinkController: amount mismatch', [
                'intent_id' => $intent['intent_id'],
                'expected'  => $expected,
                'paid'      => $paidAmount,
            ]);
            $this->intentRepo->markFailed((string)$intent['intent_id']);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1', 'Ödeme tutarı eşleşmiyor.');
            return;
        }

        $customerId = $intent['customer_id'] ?? $link['customer_id'] ?? null;
        if (!$customerId) {
            Logger::error('CustomPaymentLinkController: no customer to attach subscription', [
                'link_id' => $link['link_id'],
            ]);
            $this->intentRepo->markFailed((string)$intent['intent_id']);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1', 'Müşteri bulunamadı.');
            return;
        }

        // ---- Atomic intent claim ----
        // Flip the intent pending→completed FIRST and only proceed with
        // side effects (create subscription, mark link consumed) if we
        // were the ones who did the flip. This collapses the race where
        // two callbacks for the same token arrive in quick succession
        // and could each kick off subscription creation and link
        // consumption. markCompleted() is already a conditional
        // `WHERE status = 'pending'` update — we just respect its
        // return value now.
        if (!$this->intentRepo->markCompleted((string)$intent['intent_id'])) {
            Logger::info('CustomPaymentLinkController callback: lost race, intent already finalized', [
                'intent_id' => $intent['intent_id'],
            ]);
            $this->renderCustomLinkBridge('success', BASE_URL . '/pay/' . $linkPublicToken . '/success', '');
            return;
        }

        $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
        $sub = $subscriptionService->createCustomSubscription(
            (string)$customerId,
            (string)$link['package_id'],
            (float)$link['custom_price'],
            (int)$link['duration_months'],
            (string)($link['currency'] ?? 'TRY')
        );

        if (empty($sub['success'])) {
            // Intent is already marked completed, but the subscription
            // failed — roll the intent BACK to failed and loud-log. The
            // user has been charged; support must reconcile manually.
            Logger::critical('CustomPaymentLinkController: subscription creation failed after payment captured', [
                'link_id'     => $link['link_id'],
                'customer_id' => $customerId,
                'intent_id'   => $intent['intent_id'],
                'error'       => $sub['error'] ?? 'unknown',
            ]);
            $this->intentRepo->markFailed((string)$intent['intent_id']);
            $this->renderCustomLinkBridge('fail', BASE_URL . '/pay/' . $linkPublicToken . '?fail=1',
                'Ödeme alındı fakat abonelik oluşturulamadı. Lütfen destek ile iletişime geçin.');
            return;
        }

        // Audit trail for finance / support.
        try {
            $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
            require_once __DIR__ . '/../../helpers/functions.php';
            $paymentRepo->create([
                'payment_id'             => generateId('pay'),
                'subscription_id'        => $sub['subscription_id'],
                'amount'                 => (float)$link['custom_price'],
                'currency'               => (string)($link['currency'] ?? 'TRY'),
                'payment_method'         => 'iyzico',
                'payment_status'         => 'completed',
                'merchant_oid'           => $intent['conversation_id'] ?? ('CPL_' . $link['link_id']),
                'gateway_transaction_id' => $result['payment_id'] ?? $iyzicoToken,
                'payment_date'           => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('CustomPaymentLinkController: payment record insert failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Link accounting AFTER the subscription is in place so we never
        // mark a link "consumed" for a charge that failed to attach.
        $this->linkService->markConsumed($link['link_id']);

        Logger::info('CustomPaymentLinkController: payment success', [
            'link_id'        => $link['link_id'],
            'customer_id'    => $customerId,
            'subscription_id'=> $sub['subscription_id'],
            'amount'         => $link['custom_price'],
        ]);

        $this->renderCustomLinkBridge('success', BASE_URL . '/pay/' . $linkPublicToken . '/success', '');
        return;
    }

    /**
     * Post-3DS bridge for custom-link flow. Same intent as the one in
     * PaymentController: when the callback runs inside an iframe, tell
     * the parent window where to go via postMessage. When the callback
     * is top-level (user navigated away from our wrapper), fall back to
     * a hard redirect. Keeping both code paths means we can later iframe
     * the custom-link checkout without changing the callback.
     *
     * The payload origin is locked to our own origin on the parent side
     * so a 3rd-party that embeds us cannot spoof results.
     */
    private function renderCustomLinkBridge(string $status, string $fallbackUrl, string $error = ''): void {
        $payload = [
            'type' => 'iyzico:custom-link-result',
            'status' => $status === 'success' ? 'success' : 'fail',
            'error' => $error,
            'redirect' => $fallbackUrl,
        ];
        $jsPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsFallback = json_encode($fallbackUrl, JSON_UNESCAPED_SLASHES);
        $title = $status === 'success' ? 'Ödeme Tamamlandı' : 'Ödeme Sonucu';
        $headline = $status === 'success' ? 'Ödemeniz onaylandı' : 'Ödeme tamamlanamadı';
        $safeSub = htmlspecialchars(
            $status === 'success'
                ? 'Aboneliğiniz aktifleştiriliyor, yönlendiriliyorsunuz…'
                : ($error !== '' ? $error : 'Bir sorun oluştu, lütfen tekrar deneyin.'),
            ENT_QUOTES,
            'UTF-8'
        );
        $iconHtml = $status === 'success' ? '&#10003;' : '&#33;';
        $iconClass = $status === 'success' ? 'ok' : 'bad';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Frame-Options: SAMEORIGIN');
            // CSP is already applied by SecurityHeadersMiddleware — we
            // don't override it here, otherwise we'd drop the iyzico
            // whitelists needed for nested iframes/scripts.
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
            . "setTimeout(function(){try{if(window===window.top){window.location.replace(fallback);}else if(!posted){window.location.replace(fallback);}}catch(_e){window.location.href=fallback;}},900);"
            . "})();</script>"
            . "</body></html>";
    }

    /**
     * Post-payment confirmation screen. Branches on link mode:
     *
     *  - existing_customer, user already logged in: "dashboard'a git"
     *    CTA with a 4-second auto-redirect so the user lands on their
     *    panel without extra clicks.
     *
     *  - existing_customer, session lost:           "giriş yap" CTA.
     *
     *  - new_customer:                              render a
     *    "hesabınızı aktifleştirin — şifre belirleyin" form. Proven by
     *    the activation context set in start(). If the session was lost
     *    (user closed the tab, different browser, etc.) we offer to
     *    e-mail a password-reset link instead.
     *
     * All branches require that the latest intent for this link is in
     * 'completed' state — we never show a success screen for unpaid or
     * failed attempts even if the public URL is visited directly.
     */
    public function success($token = '') {
        $link = $this->linkService->getByToken($token);
        if (!$link) {
            $this->render('customer/custom_payment_link_invalid', [
                'title'   => 'Bağlantı geçersiz',
                'message' => 'Bu ödeme bağlantısı bulunamadı.',
            ]);
            return;
        }

        $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
        $package = $packageRepo->findById($link['package_id']);

        // Use the most recent *completed* intent here. On a multi-use
        // link a fresh pending row (e.g. a half-finished retry) must
        // not shadow an earlier successful payment and flip `paid` to
        // false — that would make the page wrongly claim "ödeme
        // tamamlanmadı" to someone who already paid.
        $latestIntent = $this->intentRepo->findLatestCompletedByLinkId((string)$link['link_id']);
        $paid = $latestIntent !== null;

        // Activation context set by start() for new_customer flows.
        $activation = $_SESSION['cpl_activation_' . $token] ?? null;
        $activationCustomerId = is_array($activation) ? ($activation['customer_id'] ?? null) : null;

        // For new_customer mode, allow activation only if:
        //   1) payment succeeded AND
        //   2) the activation context in session matches the intent's
        //      customer, AND
        //   3) the payment was recent (window: 60 minutes from when the
        //      activation context was set). This caps the TOCTOU surface
        //      if a session is stolen long after checkout.
        $canSetPassword = false;
        if (
            $link['mode'] === 'new_customer'
            && $paid
            && $activationCustomerId
            && $latestIntent['customer_id'] === $activationCustomerId
            && isset($activation['started_at'])
            && (time() - (int)$activation['started_at']) < 3600
        ) {
            $canSetPassword = true;
        }

        $isLoggedIn = !empty($_SESSION['logged_in']);

        $this->render('customer/custom_payment_link_success', [
            'title'            => 'Ödeme Tamamlandı',
            'link'             => $link,
            'package'          => $package,
            'paid'             => $paid,
            'mode'             => $link['mode'],
            'can_set_password' => $canSetPassword,
            'is_logged_in'     => $isLoggedIn,
            'csrf_token'       => \App\Core\Security\CSRFManager::generateToken(),
        ]);
    }

    /**
     * Claim a new_customer-mode account after a successful payment.
     *
     * Security chain (must all hold):
     *   - link exists and is in new_customer mode
     *   - most-recent intent for this link is `completed`
     *   - session activation context matches the intent's customer
     *   - activation window (< 60 minutes since start()) not expired
     *   - password meets the minimum policy (>= 8 chars)
     *   - user record for the customer exists and is flagged
     *     `requires_password_change` OR has the random bootstrap pin
     *     we set during register() (we never overwrite a user who's
     *     already claimed their account in another way)
     */
    public function activate($token = '') {
        $link = $this->linkService->getByToken($token);
        if (!$link || $link['mode'] !== 'new_customer') {
            $this->flashAndRedirect('error', 'Bu bağlantı için hesap oluşturma adımı uygun değil.', '/pay/' . $token);
            return;
        }

        $intent = $this->intentRepo->findLatestCompletedByLinkId((string)$link['link_id']);
        if (!$intent) {
            $this->flashAndRedirect('error', 'Ödeme tamamlanmadan hesap aktifleştirilemez.', '/pay/' . $token);
            return;
        }

        $activation = $_SESSION['cpl_activation_' . $token] ?? null;
        if (
            !is_array($activation)
            || empty($activation['customer_id'])
            || $activation['customer_id'] !== $intent['customer_id']
            || empty($activation['started_at'])
            || (time() - (int)$activation['started_at']) >= 3600
        ) {
            $this->flashAndRedirect('error', 'Aktivasyon oturumu bulunamadı veya süresi doldu. Lütfen "Şifre sıfırlama bağlantısı gönder" seçeneğini kullanın.', '/pay/' . $token . '/success');
            return;
        }

        // One-shot guard: a single activation session can set a password
        // exactly once. Re-submitting the form (e.g. browser back +
        // re-post) or replaying a captured request should not be able
        // to re-overwrite the password.
        if (!empty($activation['consumed_at'])) {
            $this->flashAndRedirect('error', 'Bu aktivasyon bağlantısı zaten kullanıldı.', '/login');
            return;
        }

        $data = \App\Core\RequestParser::getRequestData();
        $password  = (string)($data['password']  ?? '');
        $password2 = (string)($data['password2'] ?? '');
        if (strlen($password) < 8) {
            $this->flashAndRedirect('error', 'Şifre en az 8 karakter olmalı.', '/pay/' . $token . '/success');
            return;
        }
        if ($password !== $password2) {
            $this->flashAndRedirect('error', 'Şifreler uyuşmuyor.', '/pay/' . $token . '/success');
            return;
        }

        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = $customerRepo->findById((string)$activation['customer_id']);
        if (!$customer) {
            $this->flashAndRedirect('error', 'Müşteri kaydı bulunamadı.', '/pay/' . $token . '/success');
            return;
        }

        $userService = \App\Core\DependencyFactory::getUserService();
        $user = $userService->findByEmail((string)$customer['email']);
        if (!$user) {
            $this->flashAndRedirect('error', 'Kullanıcı kaydı bulunamadı.', '/pay/' . $token . '/success');
            return;
        }

        // Refuse to overwrite a password on an account that predates
        // this link. In `new_customer` mode we're supposed to be
        // initializing a *fresh* account — if the customer record was
        // attached to an existing user (same email), we'd be taking
        // over their login. We detect "new account" by checking the
        // bootstrap marker flag if the users table has one, otherwise
        // by comparing user_created_at ≥ link_created_at. If in doubt,
        // refuse and direct to support.
        $userCreatedAt = $user['created_at'] ?? null;
        $linkCreatedAt = $link['created_at'] ?? null;
        $bootstrapOk = false;
        if (array_key_exists('requires_password_change', $user)) {
            $bootstrapOk = (bool)$user['requires_password_change'];
        } elseif ($userCreatedAt && $linkCreatedAt) {
            $bootstrapOk = (strtotime((string)$userCreatedAt) >= strtotime((string)$linkCreatedAt) - 5);
        } else {
            // Schema doesn't expose either — allow only if the user row
            // has no prior sessions/logins we can detect. Fail closed.
            $bootstrapOk = empty($user['last_login_at']) && empty($user['last_login']);
        }

        if (!$bootstrapOk) {
            Logger::warning('CustomPaymentLinkController::activate — refused to overwrite existing account', [
                'link_id'  => $link['link_id'],
                'user_id'  => $user['user_id'],
            ]);
            $this->flashAndRedirect('error',
                'Bu e-posta adresine ait mevcut bir hesap bulunuyor. Güvenlik gerekçesiyle şifreyi buradan değiştiremiyoruz. Lütfen mevcut şifrenizle giriş yapın veya destek ile iletişime geçin.',
                '/login');
            return;
        }

        try {
            $userService->updatePassword((string)$user['user_id'], $password);
            // Burn the activation session so it can't be replayed.
            $activation['consumed_at'] = time();
            $_SESSION['cpl_activation_' . $token] = $activation;
        } catch (\Throwable $e) {
            Logger::error('CustomPaymentLinkController::activate — password update failed', [
                'error'     => $e->getMessage(),
                'user_id'   => $user['user_id'],
                'link_id'   => $link['link_id'],
            ]);
            $this->flashAndRedirect('error', 'Şifre kaydedilemedi.', '/pay/' . $token . '/success');
            return;
        }

        // One-shot — invalidate activation context now that the account
        // has been claimed. Any subsequent tab arriving on /success will
        // fall back to the "forgot password" email route.
        unset($_SESSION['cpl_activation_' . $token]);

        // Log the user in so they land directly on the dashboard, no
        // extra login prompt. Mirrors AuthController's session setup.
        \App\Core\SessionManager::regenerateId();
        $_SESSION['user_id']           = $user['user_id'];
        $_SESSION['username']          = $user['name'] ?? $user['email'] ?? $customer['email'];
        $_SESSION['email']             = $customer['email'] ?? '';
        $_SESSION['role']              = $user['role'] ?? 'BUSINESS_MANAGER';
        $_SESSION['role_id']           = $user['role_id'] ?? null;
        $_SESSION['logged_in']         = true;
        $_SESSION['login_time']        = time();
        $_SESSION['customer_id']       = $customer['customer_id'] ?? null;
        $_SESSION['business_id']       = $customer['customer_id'] ?? null;
        try {
            $tenantId = $user['tenant_id'] ?? $customer['customer_id'] ?? null;
            if ($tenantId) {
                \App\Core\SessionManager::setTenantSession($tenantId);
            }
        } catch (\Throwable $e) {}

        header('Location: ' . BASE_URL . '/business/dashboard?welcome=1');
        exit;
    }


    // ----------------------------------------------------------------

    private function flashAndRedirect(string $type, string $message, string $path): void {
        if (isset($this->toastNotificationService)) {
            $this->toastNotificationService->setFlash($type, $message);
        }
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}
