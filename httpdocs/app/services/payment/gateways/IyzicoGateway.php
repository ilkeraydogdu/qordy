<?php
namespace App\Services\Payment\Gateways;

require_once __DIR__ . '/PaymentGatewayInterface.php';

// Load Iyzico SDK via Composer autoload or bootstrap
if (!class_exists('Iyzipay\Options')) {
    $vendorBase = __DIR__ . '/../../../../vendor';
    $iyzicoBootstrap = $vendorBase . '/iyzico/iyzipay-php/IyzipayBootstrap.php';
    if (file_exists($iyzicoBootstrap)) {
        require_once $iyzicoBootstrap;
        if (class_exists('IyzipayBootstrap')) {
            \IyzipayBootstrap::init($vendorBase . '/iyzico/iyzipay-php/src');
        }
    }
}

/**
 * İyzico Payment Gateway
 * Full implementation with iyzipay PHP SDK
 */
class IyzicoGateway implements PaymentGatewayInterface {
    private $apiKey;
    private $secretKey;
    private $testMode;
    private $enabled;
    private $baseUrl;

    public function __construct(array $config) {
        $this->apiKey = $config['api_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
        $this->testMode = $config['test_mode'] ?? true;
        $this->enabled = $config['is_enabled'] ?? false;
        $this->baseUrl = $this->testMode 
            ? 'https://sandbox-api.iyzipay.com' 
            : 'https://api.iyzipay.com';
    }

    /**
     * Process payment - Creates payment request
     * @param array $paymentData Payment data
     * @return array Result
     */
    public function processPayment(array $paymentData): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'İyzico gateway is not enabled', 'code' => 'GATEWAY_DISABLED'];
        }
        
        if (empty($this->apiKey) || empty($this->secretKey)) {
            return ['success' => false, 'error' => 'İyzico credentials not configured', 'code' => 'CONFIG_ERROR'];
        }
        
        $amount = floatval($paymentData['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Invalid payment amount', 'code' => 'INVALID_AMOUNT'];
        }
        
        // Check if Iyzico SDK is available
        if (!class_exists('\Iyzipay\Options')) {
            if ($this->testMode) {
                // In test mode, return simulated response
                return [
                    'success' => true,
                    'transaction_id' => 'iyzico_' . uniqid(),
                    'external_transaction_id' => 'IYZICO_' . uniqid(),
                    'amount' => $amount,
                    'gateway' => 'iyzico',
                    'checkout_form_content' => '<div>Test mode: Iyzico SDK not installed</div>'
                ];
            }
            throw new \Exception('Iyzico SDK is not installed. Please install iyzico/iyzipay-php package.');
        }
        
        try {
            $options = new \Iyzipay\Options();
            $options->setApiKey($this->apiKey);
            $options->setSecretKey($this->secretKey);
            $options->setBaseUrl($this->baseUrl);
            
            $request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId($paymentData['order_id'] ?? 'ORDER_' . time() . '_' . uniqid());
            $request->setPrice(number_format($amount, 2, '.', ''));
            $request->setPaidPrice(number_format($amount, 2, '.', ''));
            $request->setCurrency(\Iyzipay\Model\Currency::TL);
            $request->setBasketId('BASKET_' . time());
            $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::SUBSCRIPTION);

            // ─────────────────────────────────────────────────────────────
            // UX + policy tuning (everything we can actually control via
            // the SDK — anything visual beyond this has to be flipped in
            // the iyzico merchant panel since the hosted form UI runs on
            // static.iyzipay.com and we cannot CSS it from outside).
            // ─────────────────────────────────────────────────────────────

            // Single-payment only. Kills the installment picker, the
            // "Tüm Taksit Seçeneklerini Göster" link, and the per-bank
            // installment breakdown at the bottom of the form.
            $request->setEnabledInstallments([1]);

            // Accept debit cards as well as credit cards.
            $request->setDebitCardAllowed(true);

            // Subscriptions never have shipping; make it explicit so
            // iyzico doesn't reserve an escrow-like "shipping included"
            // band in the paidPrice calculation.
            $request->setShippingAmountExcluded(true);

            // Tag the traffic so it's distinguishable in iyzico reports.
            $request->setPaymentSource('qordy_subscription');

            // Make 3D Secure / OTP mandatory regardless of bank/BIN or
            // iyzico merchant defaults. This is the strongest stance we
            // can take from the SDK against fraud for a subscription
            // purchase — we'd rather drop a non-3DS-capable card than
            // authorise it silently.
            $request->setForceThreeDS(1);

            // Buyer information
            $buyer = new \Iyzipay\Model\Buyer();
            $buyer->setId($paymentData['customer_id'] ?? 'BY' . time());
            $buyer->setName($paymentData['customer_name'] ?? 'Müşteri');
            $buyer->setSurname($paymentData['customer_surname'] ?? '');
            $buyer->setGsmNumber($paymentData['customer_phone'] ?? '');
            $buyer->setEmail($paymentData['customer_email'] ?? '');
            $buyer->setIdentityNumber($paymentData['customer_identity'] ?? '00000000000');
            $buyer->setLastLoginDate(date('Y-m-d H:i:s'));
            $buyer->setRegistrationDate(date('Y-m-d H:i:s'));
            $buyer->setRegistrationAddress($paymentData['customer_address'] ?? '');
            $buyer->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $buyer->setCity($paymentData['customer_city'] ?? 'Istanbul');
            $buyer->setCountry($paymentData['customer_country'] ?? 'Turkey');
            $buyer->setZipCode($paymentData['customer_zip'] ?? '34000');
            $request->setBuyer($buyer);
            
            // Shipping address
            $shippingAddress = new \Iyzipay\Model\Address();
            $shippingAddress->setContactName($paymentData['customer_name'] ?? 'Müşteri');
            $shippingAddress->setCity($paymentData['customer_city'] ?? 'Istanbul');
            $shippingAddress->setCountry($paymentData['customer_country'] ?? 'Turkey');
            $shippingAddress->setAddress($paymentData['customer_address'] ?? '');
            $shippingAddress->setZipCode($paymentData['customer_zip'] ?? '34000');
            $request->setShippingAddress($shippingAddress);
            
            $billingAddress = new \Iyzipay\Model\Address();
            $billingAddress->setContactName($paymentData['customer_name'] ?? '');
            $billingAddress->setCity($paymentData['customer_city'] ?? 'Istanbul');
            $billingAddress->setCountry($paymentData['customer_country'] ?? 'Turkey');
            $billingAddress->setAddress($paymentData['customer_address'] ?? '');
            $billingAddress->setZipCode($paymentData['customer_zip'] ?? '34000');
            $request->setBillingAddress($billingAddress);
            
            // Basket items
            $basketItems = [];
            $items = $paymentData['basket'] ?? [];
            if (empty($items)) {
                // Default item if basket is empty
                $basketItem = new \Iyzipay\Model\BasketItem();
                $basketItem->setId('BI' . time());
                $basketItem->setName('Ürün');
                $basketItem->setCategory1('Genel');
                $basketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
                $basketItem->setPrice(number_format($amount, 2, '.', ''));
                $basketItems[] = $basketItem;
            } else {
                foreach ($items as $index => $item) {
                    $basketItem = new \Iyzipay\Model\BasketItem();
                    $basketItem->setId('BI' . ($index + 1));
                    $basketItem->setName($item['name'] ?? 'Ürün ' . ($index + 1));
                    $basketItem->setCategory1($item['category'] ?? 'Genel');
                    $basketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
                    $basketItem->setPrice(number_format(floatval($item['price'] ?? 0), 2, '.', ''));
                    $basketItems[] = $basketItem;
                }
            }
            $request->setBasketItems($basketItems);
            
            // Callback URLs
            $successUrl = $paymentData['success_url'] ?? BASE_URL . '/payment/iyzico/callback?status=success';
            $failUrl = $paymentData['fail_url'] ?? BASE_URL . '/payment/iyzico/callback?status=fail';
            $callbackUrl = $paymentData['success_url'] ?? BASE_URL . '/payment/iyzico/callback';
            $request->setCallbackUrl($callbackUrl);
            
            // Initialize checkout form
            $checkoutFormInitialize = \Iyzipay\Model\CheckoutFormInitialize::create($request, $options);
            
            if ($checkoutFormInitialize->getStatus() === 'success') {
                return [
                    'success' => true,
                    'checkout_form_content' => $checkoutFormInitialize->getCheckoutFormContent(),
                    'payment_page_url' => $checkoutFormInitialize->getPaymentPageUrl(),
                    'token' => $checkoutFormInitialize->getToken(),
                    'conversation_id' => $checkoutFormInitialize->getConversationId(),
                    'amount' => $amount,
                    'gateway' => 'iyzico'
                ];
            }
            
            return [
                'success' => false,
                'error' => $checkoutFormInitialize->getErrorMessage() ?? 'Payment initialization failed',
                'code' => $checkoutFormInitialize->getErrorCode() ?? 'PAYMENT_FAILED'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Iyzico API error: ' . $e->getMessage(),
                'code' => 'API_ERROR'
            ];
        }
    }

    /**
     * Handle Iyzico callback
     * @param array $callbackData Callback POST data
     * @return array Verification result
     */
    public function handleCallback(array $callbackData): array {
        $token = $callbackData['token'] ?? '';
        
        if (empty($token)) {
            return ['success' => false, 'error' => 'Invalid callback data', 'code' => 'INVALID_CALLBACK'];
        }
        
        if (!class_exists('\Iyzipay\Options')) {
            return ['success' => false, 'error' => 'Iyzico SDK is not installed', 'code' => 'SDK_NOT_INSTALLED'];
        }
        
        try {
            $options = new \Iyzipay\Options();
            $options->setApiKey($this->apiKey);
            $options->setSecretKey($this->secretKey);
            $options->setBaseUrl($this->baseUrl);
            
            $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
            $request->setToken($token);
            
            $checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);
            
            if ($checkoutForm->getStatus() === 'success' && $checkoutForm->getPaymentStatus() === 'SUCCESS') {
                return [
                    'success' => true,
                    'verified' => true,
                    'status' => 'completed',
                    'payment_id' => $checkoutForm->getPaymentId(),
                    'conversation_id' => $checkoutForm->getConversationId(),
                    'amount' => floatval($checkoutForm->getPaidPrice()),
                    'transaction_id' => $checkoutForm->getPaymentId()
                ];
            }
            
            return [
                'success' => false,
                'verified' => false,
                'status' => 'failed',
                'error' => $checkoutForm->getErrorMessage() ?? 'Payment failed',
                'code' => $checkoutForm->getErrorCode() ?? 'PAYMENT_FAILED'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Callback verification error: ' . $e->getMessage(),
                'code' => 'VERIFICATION_ERROR'
            ];
        }
    }

    /**
     * Refund payment
     * @param array $refundData Refund data
     * @return array Result
     */
    public function refundPayment(array $refundData): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'İyzico gateway is not enabled', 'code' => 'GATEWAY_DISABLED'];
        }
        
        $paymentId = $refundData['transaction_id'] ?? $refundData['payment_id'] ?? '';
        $amount = floatval($refundData['amount'] ?? 0);
        
        if (empty($paymentId) || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid refund data', 'code' => 'INVALID_DATA'];
        }
        
        if (!class_exists('\Iyzipay\Options')) {
            if ($this->testMode) {
                return ['success' => true, 'refund_id' => 'iyzico_ref_' . uniqid()];
            }
            throw new \Exception('Iyzico SDK is not installed. Please install iyzico/iyzipay-php package.');
        }
        
        try {
            $options = new \Iyzipay\Options();
            $options->setApiKey($this->apiKey);
            $options->setSecretKey($this->secretKey);
            $options->setBaseUrl($this->baseUrl);
            
            $request = new \Iyzipay\Request\CreateRefundRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId('REFUND_' . time() . '_' . uniqid());
            $request->setPaymentTransactionId($paymentId);
            $request->setPrice(number_format($amount, 2, '.', ''));
            
            $refund = \Iyzipay\Model\Refund::create($request, $options);
            
            if ($refund->getStatus() === 'success') {
                return [
                    'success' => true,
                    'refund_id' => $refund->getPaymentId(),
                    'amount' => $amount
                ];
            }
            
            return [
                'success' => false,
                'error' => $refund->getErrorMessage() ?? 'Refund failed',
                'code' => $refund->getErrorCode() ?? 'REFUND_FAILED'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Refund error: ' . $e->getMessage(),
                'code' => 'API_ERROR'
            ];
        }
    }

    /**
     * Verify payment status
     * @param string $transactionId Transaction ID (payment_id)
     * @return array Payment status
     */
    public function verifyPayment(string $transactionId): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'İyzico gateway is not enabled', 'code' => 'GATEWAY_DISABLED'];
        }
        
        if (!class_exists('\Iyzipay\Options')) {
            if ($this->testMode) {
                return ['success' => true, 'verified' => true, 'status' => 'completed'];
            }
            return ['success' => false, 'error' => 'Iyzico SDK is not installed', 'code' => 'SDK_NOT_INSTALLED'];
        }
        
        try {
            $options = new \Iyzipay\Options();
            $options->setApiKey($this->apiKey);
            $options->setSecretKey($this->secretKey);
            $options->setBaseUrl($this->baseUrl);
            
            $request = new \Iyzipay\Request\RetrievePaymentRequest();
            $request->setPaymentId($transactionId);
            
            $payment = \Iyzipay\Model\Payment::retrieve($request, $options);
            
            if ($payment->getStatus() === 'success') {
                return [
                    'success' => true,
                    'verified' => true,
                    'status' => $payment->getPaymentStatus() === 'SUCCESS' ? 'completed' : 'pending',
                    'amount' => floatval($payment->getPaidPrice()),
                    'transaction_id' => $payment->getPaymentId()
                ];
            }
            
            return [
                'success' => false,
                'error' => $payment->getErrorMessage() ?? 'Verification failed',
                'code' => $payment->getErrorCode() ?? 'VERIFICATION_FAILED'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Verification error: ' . $e->getMessage(),
                'code' => 'API_ERROR'
            ];
        }
    }

    public function getName(): string { 
        return 'İyzico'; 
    }
    
    public function getCode(): string { 
        return 'iyzico'; 
    }
    
    public function isEnabled(): bool { 
        return $this->enabled && !empty($this->apiKey) && !empty($this->secretKey); 
    }
    
    public function isTestMode(): bool { 
        return $this->testMode; 
    }
}

