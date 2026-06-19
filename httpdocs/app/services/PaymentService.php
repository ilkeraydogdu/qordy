<?php
namespace App\Services;

class PaymentService {
    
    /**
     * Process in-venue payment (POS cash/card/mixed)
     */
    public function processPayment(array $paymentData): array {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $orderId = $paymentData['order_id'] ?? '';
            $method = strtoupper($paymentData['payment_method'] ?? 'CASH');
            $amount = floatval($paymentData['amount'] ?? 0);
            $tenantId = \App\Core\TenantContext::getId();
            $tableId = $paymentData['table_id'] ?? '';
            $tip = floatval($paymentData['tip'] ?? 0);
            $serviceCharge = floatval($paymentData['service_charge'] ?? 0);
            
            if ($method === 'MIXED' || $method === 'OTHER') {
                $cashAmount = floatval($paymentData['cash_amount'] ?? 0);
                $cardAmount = floatval($paymentData['card_amount'] ?? 0);
                
                if ($cashAmount <= 0 && $cardAmount <= 0) {
                    $cashAmount = $amount;
                }
                
                if ($cashAmount > 0) {
                    $txId = 'tx_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
                    $stmt = $db->prepare("INSERT INTO payment_transactions (transaction_id, tenant_id, table_id, amount, method, tip, service_charge, created_at) VALUES (?, ?, ?, ?, 'CASH', ?, ?, NOW())");
                    $stmt->execute([$txId, $tenantId, $tableId, $cashAmount, 0, 0]);
                }
                
                if ($cardAmount > 0) {
                    $txId = 'tx_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
                    $stmt = $db->prepare("INSERT INTO payment_transactions (transaction_id, tenant_id, table_id, amount, method, tip, service_charge, created_at) VALUES (?, ?, ?, ?, 'CREDIT_CARD', ?, ?, NOW())");
                    $stmt->execute([$txId, $tenantId, $tableId, $cardAmount, $tip, $serviceCharge]);
                }
                
                $stmt = $db->prepare("UPDATE orders SET is_paid = 1, payment_method = 'CASH' WHERE order_id = ?");
                $stmt->execute([$orderId]);
                
                return ['success' => true, 'method' => 'MIXED'];
            }
            
            $txId = 'tx_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            $stmt = $db->prepare("INSERT INTO payment_transactions (transaction_id, tenant_id, table_id, amount, method, tip, service_charge, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$txId, $tenantId, $tableId, $amount, $method, $tip, $serviceCharge]);
            
            $stmt = $db->prepare("UPDATE orders SET is_paid = 1, payment_method = ? WHERE order_id = ?");
            $stmt->execute([$method, $orderId]);
            
            return ['success' => true, 'transaction_id' => $txId, 'method' => $method];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Payment processing error', ['error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process subscription/package payment via iyzico
     */
    public function processPackagePayment(string $subscriptionId, float $amount, string $paymentMethod, array $gatewayData = []): array {
        try {
            if ($paymentMethod === 'iyzico' || $paymentMethod === 'gateway') {
                $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
                $gateway = $paymentGatewayService->getGateway('iyzico');
                
                if (!$gateway || !$gateway->isEnabled()) {
                    return ['success' => false, 'error' => 'iyzico ödeme altyapısı aktif değil.'];
                }

                $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
                $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
                if (!$subscription) {
                    return ['success' => false, 'error' => 'Abonelik bulunamadı.'];
                }

                $conversationId = 'SUBS_' . $subscriptionId . '_' . time();
                $paymentData = [
                    'order_id' => $conversationId,
                    'amount' => $amount,
                    'customer_email' => $gatewayData['customer_email'] ?? '',
                    'customer_name' => $gatewayData['customer_name'] ?? 'Müşteri',
                    'customer_surname' => $gatewayData['customer_surname'] ?? '',
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

                $result = $gateway->processPayment($paymentData);

                if ($result['success']) {
                    $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
                    require_once __DIR__ . '/../helpers/functions.php';

                    $paymentRecord = [
                        'payment_id' => generateId('pay'),
                        'subscription_id' => $subscriptionId,
                        'amount' => $amount,
                        'currency' => 'TRY',
                        'payment_method' => 'iyzico',
                        'payment_status' => 'pending',
                        'merchant_oid' => $conversationId,
                        'gateway_transaction_id' => $result['token'] ?? null,
                        'payment_date' => null
                    ];
                    $paymentRepo->create($paymentRecord);

                    return [
                        'success' => true,
                        'checkout_form_content' => $result['checkout_form_content'] ?? '',
                        'token' => $result['token'] ?? null,
                        'conversation_id' => $conversationId
                    ];
                }

                return ['success' => false, 'error' => $result['error'] ?? 'Ödeme başlatılamadı.'];
            }

            return ['success' => false, 'error' => 'Desteklenmeyen ödeme yöntemi. Lütfen iyzico ile ödeme yapın.'];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Package payment error', ['error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle iyzico callback for subscription payments
     */
    public function handleIyzicoCallback(array $callbackData): array {
        try {
            $token = $callbackData['token'] ?? '';
            if (empty($token)) {
                return ['success' => false, 'error' => 'Geçersiz callback verisi.'];
            }

            $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
            $gateway = $paymentGatewayService->getGateway('iyzico');

            if (!$gateway) {
                return ['success' => false, 'error' => 'iyzico gateway bulunamadı.'];
            }

            $result = $gateway->handleCallback(['token' => $token]);

            $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
            $payment = $paymentRepo->getByGatewayTransactionId($token);

            if (!$payment && !empty($result['conversation_id'])) {
                $payment = $paymentRepo->getByMerchantOid($result['conversation_id']);
            }

            if ($result['success'] && $result['verified']) {
                if ($payment) {
                    $paymentRepo->update($payment['payment_id'], [
                        'payment_status' => 'completed',
                        'gateway_transaction_id' => $result['payment_id'] ?? $result['transaction_id'] ?? $token,
                        'payment_date' => date('Y-m-d H:i:s')
                    ]);

                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $subscriptionService->activateSubscription($payment['subscription_id']);
                }

                return [
                    'success' => true,
                    'payment_id' => $result['payment_id'] ?? $result['transaction_id'] ?? '',
                    'subscription_id' => $payment['subscription_id'] ?? ''
                ];
            }

            if ($payment) {
                $paymentRepo->update($payment['payment_id'], [
                    'payment_status' => 'failed',
                    'payment_date' => date('Y-m-d H:i:s')
                ]);
            }

            return ['success' => false, 'error' => $result['error'] ?? 'Ödeme doğrulanamadı.'];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('iyzico callback error', ['error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Save card token (stub - iyzico card storage requires separate agreement)
     */
    public function saveCardToken(string $customerId, array $cardData): array {
        try {
            $savedCardRepo = \App\Core\DependencyFactory::getSavedPaymentMethodRepository();
            require_once __DIR__ . '/../helpers/functions.php';

            $record = [
                'id' => generateId('sc'),
                'customer_id' => $customerId,
                'gateway' => 'iyzico',
                'token' => $cardData['token'] ?? '',
                'last4' => $cardData['last4'] ?? '',
                'brand' => $cardData['brand'] ?? '',
                'expiry_month' => $cardData['expiry_month'] ?? null,
                'expiry_year' => $cardData['expiry_year'] ?? null,
                'is_default' => $cardData['is_default'] ?? 0,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $savedCardRepo->create($record);
            return ['success' => true];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Save card token error', ['error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => 'Kart kaydedilemedi: ' . $e->getMessage()];
        }
    }

    /**
     * Get saved cards for a customer
     */
    public function getSavedCards(string $customerId): array {
        try {
            $savedCardRepo = \App\Core\DependencyFactory::getSavedPaymentMethodRepository();
            return $savedCardRepo->getByCustomerId($customerId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Get saved cards error', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
}
