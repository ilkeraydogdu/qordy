<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

use App\Core\Controller;

/**
 * Payment Controller
 * Handles payment gateway operations (iyzico only)
 */
class PaymentController extends Controller {
    
    /**
     * Initiate payment via iyzico
     */
    public function initiatePayment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $amount = floatval($requestData['amount'] ?? 0);
        $tableId = $requestData['table_id'] ?? '';
        $orderId = $requestData['order_id'] ?? null;
        
        if ($amount <= 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_amount', [], 400);
            return;
        }
        
        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $gateway = $paymentGatewayService->getGateway('iyzico');
        
        if (!$gateway || !$gateway->isEnabled()) {
            $this->toastNotificationService->sendApiResponse('error', 'iyzico gateway is not enabled', [], 400);
            return;
        }
        
        $customerEmail = $requestData['customer_email'] ?? '';
        $customerName = $requestData['customer_name'] ?? '';
        $customerPhone = $requestData['customer_phone'] ?? '';
        $customerAddress = $requestData['customer_address'] ?? '';
        $basket = $requestData['basket'] ?? [];
        
        if ($orderId) {
            $orderService = \App\Core\DependencyFactory::getOrderService();
            try {
                $order = $orderService->getOrderById($orderId);
                if ($order) {
                    if (empty($customerEmail) && !empty($order['customer_email'])) {
                        $customerEmail = $order['customer_email'];
                    }
                    if (empty($customerName) && !empty($order['customer_name'])) {
                        $customerName = $order['customer_name'];
                    }
                    if (empty($basket) && !empty($order['items'])) {
                        $basket = array_map(function($item) {
                            return [
                                'name' => $item['menu_item_name'] ?? 'Ürün',
                                'price' => floatval($item['price'] ?? 0),
                                'quantity' => intval($item['quantity'] ?? 1)
                            ];
                        }, $order['items']);
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('Error fetching order: ' . $e->getMessage());
            }
        }
        
        $paymentData = [
            'amount' => $amount,
            'order_id' => $orderId,
            'customer_email' => $customerEmail ?: 'customer@example.com',
            'customer_name' => $customerName ?: 'Müşteri',
            'customer_phone' => $customerPhone ?: '',
            'customer_address' => $customerAddress ?: '',
            'basket' => $basket,
            'success_url' => BASE_URL . '/payment/iyzico/callback?status=success&table_id=' . urlencode($tableId),
            'fail_url' => BASE_URL . '/payment/iyzico/callback?status=fail&table_id=' . urlencode($tableId),
            'currency' => 'TRY'
        ];
        
        $result = $gateway->processPayment($paymentData);
        
        if ($result['success']) {
            $conversationId = $result['conversation_id'] ?? uniqid();
            $_SESSION['iyzico_pending_' . $conversationId] = [
                'token' => $result['token'] ?? '',
                'conversation_id' => $conversationId,
                'amount' => $amount,
                'table_id' => $tableId,
                'order_id' => $orderId,
                'timestamp' => time()
            ];
            
            $this->apiResponse([
                'success' => true,
                'gateway' => 'iyzico',
                'checkout_form_content' => $result['checkout_form_content'],
                'payment_page_url' => $result['payment_page_url'],
                'token' => $result['token']
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', $result['error'] ?? 'Payment initiation failed', [], 400);
        }
    }
    
    /**
     * Handle iyzico payment callback
     */
    public function callback() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $status = $_GET['status'] ?? 'unknown';
            $tableId = $_GET['table_id'] ?? '';
            
            $this->view('payment/callback', [
                'status' => $status,
                'table_id' => $tableId,
                'gateway' => 'iyzico',
                'message' => $status === 'success' ? 'Ödeme başarıyla tamamlandı' : 'Ödeme işlemi başarısız oldu'
            ]);
            return;
        }
        
        $callbackData = $_POST;
        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $gateway = $paymentGatewayService->getGateway('iyzico');
        
        if (!$gateway || !$gateway->isEnabled()) {
            http_response_code(400);
            echo 'FAIL';
            return;
        }
        
        $verification = $gateway->handleCallback($callbackData);
        
        if (!$verification || !$verification['success']) {
            http_response_code(400);
            echo 'FAIL';
            return;
        }
        
        $transactionId = $verification['payment_id'] ?? $verification['transaction_id'] ?? '';
        $conversationId = $verification['conversation_id'] ?? '';
        $amount = $verification['amount'] ?? 0;
        $status = $verification['status'] ?? 'failed';
        
        // Look up pending data by conversation_id (matching how initiatePayment stores it)
        $pendingData = null;
        if (!empty($conversationId)) {
            $pendingKey = 'iyzico_pending_' . $conversationId;
            $pendingData = $_SESSION[$pendingKey] ?? null;
            if ($pendingData) {
                unset($_SESSION[$pendingKey]);
            }
        }
        
        $paymentTransactionService = \App\Core\DependencyFactory::getPaymentTransactionService();
        
        if ($status === 'completed') {
            $transactionData = [
                'transaction_id' => generateId('t'),
                'external_transaction_id' => $transactionId,
                'table_id' => $pendingData['table_id'] ?? '',
                'amount' => $amount,
                'method' => 'ONLINE_PAYMENT',
                'processed_by' => $_SESSION['user_id'] ?? 'SYSTEM',
                'source' => 'IYZICO',
                'gateway_response' => json_encode($callbackData)
            ];
            
            $txId = $paymentTransactionService->createTransaction($transactionData);
            
            if ($txId) {
                if (!empty($pendingData['order_id'])) {
                    try {
                        $orderService = \App\Core\DependencyFactory::getOrderService();
                        $orderService->updateOrderStatus($pendingData['order_id'], 'PAID');
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('Error updating order status: ' . $e->getMessage());
                    }
                }
                
                if (!empty($pendingData['table_id'])) {
                    try {
                        $tableService = \App\Core\DependencyFactory::getTableService();
                        $tableService->updateTableStatus($pendingData['table_id'], 'FREE');

                        $tableSessionService = \App\Core\DependencyFactory::getTableSessionService();
                        $customerSessionService = \App\Core\DependencyFactory::getCustomerSessionService();
                        $tableSessionService->clearSessionsByTable($pendingData['table_id']);
                        $customerSessionService->clearSessionsByTable($pendingData['table_id']);
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('Error updating table status: ' . $e->getMessage());
                    }
                }
            }
        }
        
        http_response_code(200);
        echo json_encode(['success' => true]);
    }
    
    /**
     * Get payment status
     */
    public function getPaymentStatus() {
        $requestData = \App\Core\RequestParser::getRequestData();
        $transactionId = $requestData['transaction_id'] ?? $requestData['payment_id'] ?? '';
        
        if (empty($transactionId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Transaction ID required', [], 400);
            return;
        }
        
        $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
        $gateway = $paymentGatewayService->getGateway('iyzico');
        
        if (!$gateway || !$gateway->isEnabled()) {
            $this->toastNotificationService->sendApiResponse('error', 'iyzico gateway is not enabled', [], 400);
            return;
        }
        
        $result = $gateway->verifyPayment($transactionId);
        
        if ($result['success']) {
            $this->apiResponse([
                'success' => true,
                'status' => $result['status'],
                'amount' => $result['amount'],
                'transaction_id' => $result['transaction_id']
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', $result['error'] ?? 'Status check failed', [], 400);
        }
    }
}
