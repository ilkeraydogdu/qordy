<?php
/**
 * Receipt Helper Functions
 * 
 * Helper functions for receipt/adisyon operations
 */

if (!function_exists('generateReceiptNumber')) {
    /**
     * Generate unique receipt number
     * Format: YYYYMMDD-XXXXX (e.g., 20240115-00001)
     * 
     * @return string Receipt number
     */
    function generateReceiptNumber(): string {
        $date = date('Ymd');
        
        // Get receipt service
        $receiptService = \App\Core\DependencyFactory::getReceiptService();
        $todayReceipts = $receiptService->getDailyReceipts(date('Y-m-d'));
        
        if (!empty($todayReceipts)) {
            // Find highest number
            $maxNumber = 0;
            foreach ($todayReceipts as $receipt) {
                $receiptNum = $receipt['receipt_number'] ?? '';
                if (strpos($receiptNum, $date . '-') === 0) {
                    $number = intval(substr($receiptNum, -5));
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }
            $newNumber = $maxNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $date . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('formatReceiptForPrint')) {
    /**
     * Format receipt data for thermal printer
     * QORDY STANDART FİŞ FORMATI - Tüm sistemde kullanılır
     * 
     * @param array $receiptData Receipt data
     * @return string Formatted receipt text
     */
    function formatReceiptForPrint(array $receiptData, bool $forPrinter = true): string {
        $receipt = $receiptData['receipt'] ?? [];
        $order = $receiptData['order'] ?? [];
        $items = $receiptData['items'] ?? [];
        $settings = $receiptData['settings'] ?? [];
        $W = intval($settings['paper_width'] ?? 32);
        
        $output = "";
        
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $translationService = \App\Core\DependencyFactory::getTranslationService();
        
        // İşletme adı: sadece gerçek veriden (ayarlar veya müşteri kaydı). site_name (Qordy) kullanılmaz.
        $restaurantName = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
        if ($restaurantName === '') {
            try {
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId) {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($tenantId);
                    $restaurantName = trim($customer['company_name'] ?? $customer['business_name'] ?? '') ?: '';
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $restaurantName = $restaurantName !== '' ? $restaurantName : 'İşletme';
        $output .= str_pad($restaurantName, $W, ' ', STR_PAD_BOTH) . "\n";
        $output .= str_repeat('=', $W) . "\n";
        $output .= str_pad('ADISYON', $W, ' ', STR_PAD_BOTH) . "\n";
        $output .= str_repeat('=', $W) . "\n\n";
        
        $receiptNumber = $receipt['receipt_number'] ?? '';
        $receiptDate = date('d.m.Y', strtotime($receipt['created_at'] ?? 'now'));
        $receiptTime = date('H:i', strtotime($receipt['created_at'] ?? 'now'));
        
        $output .= str_pad("No: " . $receiptNumber, $W, ' ', STR_PAD_BOTH) . "\n";
        $output .= str_pad("Tarih: " . $receiptDate . " Saat: " . $receiptTime, $W, ' ', STR_PAD_BOTH) . "\n";
        
        $orderId = $receipt['order_id'] ?? $order['order_id'] ?? '';
        if ($orderId !== '') {
            $output .= str_pad("Siparis No: #" . $orderId, $W, ' ', STR_PAD_BOTH) . "\n";
        }
        
        if (!empty($order['table_name'])) {
            $output .= str_pad("Masa: " . $order['table_name'], $W, ' ', STR_PAD_BOTH) . "\n";
        }
        
        $createdByName = trim($receipt['created_by_name'] ?? '');
        $createdByRole = strtoupper(trim($receipt['created_by_role'] ?? ''));
        if ($createdByName === '' && !empty($order['staff_name'])) {
            $createdByName = trim($order['staff_name']);
        }
        if ($createdByName === '' && !empty($order['waiter_name'])) {
            $createdByName = trim($order['waiter_name']);
        }
        $label = ($createdByRole === 'WAITER') ? 'Garson' : 'Kasiyer';
        $output .= str_pad($label . ": " . ($createdByName !== '' ? $createdByName : '-'), $W, ' ', STR_PAD_BOTH) . "\n";
        
        $output .= str_repeat('-', $W) . "\n\n";
        
        // Items - gruplandırılmış (aynı ürün + aynı özelleştirme = tek satır)
        if (!function_exists('groupOrderItemsForDisplay')) {
            require_once __DIR__ . '/functions.php';
        }
        $items = function_exists('groupOrderItemsForDisplay') ? groupOrderItemsForDisplay($items) : $items;
        foreach ($items as $item) {
            $name = $item['item_name'] ?? $item['name'] ?? 'Ürün';
            $quantity = $item['quantity'] ?? 1;
            $price = floatval($item['price'] ?? 0);
            $total = $price * $quantity;
            
            $qtyText = $quantity . "x";
            $priceText = number_format($total, 2, ',', '.') . ' TL';
            $minNameWidth = $W - mb_strlen($qtyText, 'UTF-8') - mb_strlen($priceText, 'UTF-8') - 4;
            $nameLen = mb_strlen($name, 'UTF-8');
            $maxNameLength = max($minNameWidth, $nameLen);
            
            $output .= sprintf("%-3s %-{$maxNameLength}s %9s\n", $qtyText, $name, $priceText);
            
            // Variant
            $variantName = $item['variant_name'] ?? '';
            if (!empty($variantName)) {
                $output .= "    Varyant: " . $variantName . "\n";
            }
            
            // Excluded ingredients (çıkarılan malzemeler)
            $excluded = $item['excluded_ingredients'] ?? [];
            if (is_string($excluded)) {
                $excluded = json_decode($excluded, true) ?: [];
            }
            if (!empty($excluded)) {
                $excludedNames = [];
                foreach ($excluded as $ing) {
                    $ingName = is_array($ing) ? ($ing['name'] ?? $ing['ingredient_name'] ?? '') : $ing;
                    if (!empty($ingName)) $excludedNames[] = $ingName;
                }
                if (!empty($excludedNames)) {
                    $output .= "    CIKAR: " . implode(', ', $excludedNames) . "\n";
                }
            }
            
            // Selected extras
            $extras = $item['selected_extras'] ?? [];
            if (is_string($extras)) {
                $extras = json_decode($extras, true) ?: [];
            }
            if (!empty($extras)) {
                $extraNames = [];
                foreach ($extras as $ext) {
                    $extName = is_array($ext) ? ($ext['name'] ?? $ext['extra_name'] ?? '') : $ext;
                    $extPrice = is_array($ext) ? floatval($ext['price'] ?? 0) : 0;
                    if (!empty($extName)) {
                        $extraNames[] = $extName . ($extPrice > 0 ? ' (+' . number_format($extPrice, 2, ',', '.') . ')' : '');
                    }
                }
                if (!empty($extraNames)) {
                    $output .= "    EKSTRA: " . implode(', ', $extraNames) . "\n";
                }
            }
            
            // Options array (fallback: may contain "Çıkar:", "Ekstra:", "Varyant:")
            if (empty($excluded) && empty($extras) && empty($variantName)) {
                $options = $item['options'] ?? [];
                if (is_string($options)) {
                    $options = json_decode($options, true) ?: [];
                }
                foreach ($options as $opt) {
                    $optText = is_array($opt) ? ($opt['text'] ?? ($opt['label'] ?? '')) : $opt;
                    if (!empty($optText)) {
                        $output .= "    " . $optText . "\n";
                    }
                }
            }
            
            // Item note
            $itemNote = $item['notes'] ?? $item['note'] ?? $item['item_note'] ?? '';
            if (!empty($itemNote)) {
                $output .= "    NOT: " . $itemNote . "\n";
            }
        }
        
        // Customer/order note
        $customerNote = $order['customer_note'] ?? $order['note'] ?? '';
        if (!empty($customerNote)) {
            $output .= "\n" . str_repeat('-', $W) . "\n";
            $output .= "SIPARIS NOTU:\n";
            $output .= wordwrap($customerNote, $W, "\n", true) . "\n";
        }
        
        $output .= "\n" . str_repeat('-', $W) . "\n";
        
        // Totals: total = subtotal + tax + service - discount
        $serviceCharge = floatval($receipt['service_charge'] ?? 0);
        $taxAmount = floatval($receipt['tax_amount'] ?? 0);
        $discount = floatval($receipt['discount_amount'] ?? 0);
        $total = floatval($receipt['total_amount'] ?? 0);
        $subtotal = $total - $taxAmount - $serviceCharge + $discount;
        
        $labelW = intval($W * 0.6);
        $valW = $W - $labelW;
        
        $output .= str_pad($translationService->translate('receipt.subtotal', null, []) . ":", $labelW) . str_pad(number_format($subtotal, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        
        if ($serviceCharge > 0) {
            $output .= str_pad($translationService->translate('receipt.service', null, []) . ":", $labelW) . str_pad(number_format($serviceCharge, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        }
        
        if ($taxAmount > 0) {
            $output .= str_pad($translationService->translate('receipt.tax', null, []) . ":", $labelW) . str_pad(number_format($taxAmount, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        }
        
        if ($discount > 0) {
            $output .= str_pad($translationService->translate('receipt.discount', null, []) . ":", $labelW) . str_pad('-' . number_format($discount, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        }
        
        $output .= str_repeat('=', $W) . "\n";
        $output .= str_pad($translationService->translate('receipt.total', null, []) . ":", $labelW) . str_pad(number_format($total, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        $output .= str_repeat('=', $W) . "\n";
        
        // Ödeme yöntemi – Türkçe; MIXED ise sadece dağılım (ne kadar kart, ne kadar nakit)
        $paymentMethod = strtoupper(trim($receipt['payment_method'] ?? 'CASH'));
        $paymentBreakdown = $receiptData['payment_breakdown'] ?? null;
        if (is_array($paymentBreakdown)) {
            $paymentBreakdown = [
                'cash' => floatval($paymentBreakdown['cash'] ?? 0),
                'card' => floatval($paymentBreakdown['card'] ?? 0)
            ];
        } else {
            $paymentBreakdown = null;
        }
        $paymentLabelsTr = ['CASH' => 'Nakit', 'CARD' => 'Kart', 'QR' => 'QR', 'MIXED' => 'Karışık', 'PENDING' => 'Beklemede'];
        $paymentMethodLabel = $paymentLabelsTr[$paymentMethod] ?? $paymentMethod;

        if ($paymentMethod === 'MIXED' && $paymentBreakdown && (($paymentBreakdown['cash'] ?? 0) + ($paymentBreakdown['card'] ?? 0)) > 0) {
            $cash = $paymentBreakdown['cash'] ?? 0;
            $card = $paymentBreakdown['card'] ?? 0;
            $output .= $translationService->translate('receipt.payment', null, []) . ": Karışık\n";
            $output .= str_pad("Kart:", $labelW) . str_pad(number_format($card, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
            $output .= str_pad("Nakit:", $labelW) . str_pad(number_format($cash, 2, ',', '.') . ' TL', $valW, ' ', STR_PAD_LEFT) . "\n";
        } elseif ($paymentMethod === 'MIXED') {
            $output .= $translationService->translate('receipt.payment', null, []) . ": Karışık (dağılım bilgisi yok)\n";
        } else {
            $output .= $translationService->translate('receipt.payment', null, []) . ": " . $paymentMethodLabel . " - " . number_format($total, 2, ',', '.') . " TL\n";
        }
        
        // Footer
        $output .= str_repeat('-', $W) . "\n\n";
        $output .= str_pad($translationService->translate('receipt.thank_you', null, []), $W, ' ', STR_PAD_BOTH) . "\n\n";
        
        $output .= str_repeat('=', $W) . "\n";
        $output .= str_pad($restaurantName, $W, ' ', STR_PAD_BOTH) . "\n";
        $output .= str_pad('Yonetim Sistemi', $W, ' ', STR_PAD_BOTH) . "\n";
        $footerUrl = trim($settings['website'] ?? $settings['site_url'] ?? '') ?: 'www.qordy.com';
        $output .= str_pad($footerUrl, $W, ' ', STR_PAD_BOTH) . "\n";
        $output .= str_repeat('=', $W) . "\n";
        
        if ($forPrinter) {
            $output .= "\n\n\n";
            $output .= "\x1D\x56\x00"; // GS V 0 - paper cut (sadece yazıcı için)
        }
        
        return $output;
    }
}

if (!function_exists('generateReceiptQR')) {
    /**
     * Generate QR code data for receipt
     * 
     * @param string $receiptId Receipt ID
     * @return string QR code data (URL)
     */
    function generateReceiptQR(string $receiptId): string {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        return $baseUrl . '/receipt/' . $receiptId;
    }
}

if (!function_exists('calculateReceiptTotals')) {
    /**
     * Calculate receipt totals (tax, service charge, etc.)
     * 
     * @param float $subtotal Subtotal amount
     * @param array $settings Settings array with tax_rate and service_charge_rate
     * @param float $discountAmount Discount amount
     * @return array Calculated totals
     */
    function calculateReceiptTotals(float $subtotal, array $settings = [], float $discountAmount = 0): array {
        $taxRate = floatval($settings['tax_rate'] ?? 0);
        $serviceChargeRate = floatval($settings['service_charge_rate'] ?? 0);
        
        $serviceCharge = $subtotal * ($serviceChargeRate / 100);
        $taxableAmount = $subtotal + $serviceCharge - $discountAmount;
        $taxAmount = $taxableAmount * ($taxRate / 100);
        $totalAmount = $taxableAmount + $taxAmount;
        
        return [
            'subtotal' => $subtotal,
            'service_charge' => $serviceCharge,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount
        ];
    }
}

