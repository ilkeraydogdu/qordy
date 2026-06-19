<?php
/**
 * Centralized Validation Rules
 * Defines validation rules for all input fields across the application
 * 
 * Note: Role, status, and payment method lists are loaded dynamically from database
 */

// Load dynamic constants from database
// Fallback values will be loaded from ConstantsService if database fails
$roleList = 'MANAGER,WAITER,KITCHEN,CASHIER'; // Fallback - will be overridden by ConstantsService
$orderStatusList = 'PENDING,PREPARING,READY,SERVED,CANCELLED,ISSUE,ON_DELIVERY,DELIVERED'; // Fallback - will be overridden by ConstantsService
$tableStatusList = 'FREE,OCCUPIED,PAYMENT_PENDING,DIRTY,RESERVED'; // Fallback - will be overridden by ConstantsService
$paymentMethodList = 'CASH,CREDIT_CARD,ONLINE_PAYMENT,OTHER'; // Fallback - will be overridden by ConstantsService

try {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $constantsService = \App\Core\DependencyFactory::getConstantsService();
    
    // Load role codes
    $roleCodes = $constantsService->getRoleCodes();
    if (!empty($roleCodes)) {
        $roleList = implode(',', $roleCodes);
    }
    
    // Load order status codes
    $orderStatusCodes = $constantsService->getOrderStatusCodes();
    if (!empty($orderStatusCodes)) {
        $orderStatusList = implode(',', $orderStatusCodes);
    }
    
    // Load table status codes
    $tableStatusCodes = $constantsService->getTableStatusCodes();
    if (!empty($tableStatusCodes)) {
        $tableStatusList = implode(',', $tableStatusCodes);
    }
    
    // Load payment method codes
    $paymentMethodCodes = $constantsService->getPaymentMethodCodes();
    if (!empty($paymentMethodCodes)) {
        $paymentMethodList = implode(',', $paymentMethodCodes);
    }
} catch (\Exception $e) {
    // Use fallback values if database fails
    // Use Logger if available, otherwise fallback to error_log
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error("Error loading constants for validation rules: " . $e->getMessage());
    } else {
        error_log("Error loading constants for validation rules: " . $e->getMessage());
    }
}

return [
    // User/Staff validation rules
    'user' => [
        'name' => 'required|string|max:255',
        'pin' => 'required|string|min:4|max:20',
        'role' => 'required|string|in:' . $roleList,
    ],
    
    // Menu item validation rules
    'menu_item' => [
        'name' => 'required|string|max:255',
        'description' => 'string|max:1000',
        'price' => 'required|numeric|min:0', // Allow 0 for free items
        'category_id' => 'required|string|max:50',
        'image_url' => 'string|max:5000',
        'stock' => 'integer|min:0',
        'is_available' => 'boolean',
    ],
    
    // Menu item edit validation rules (category_id is optional for edit)
    'menu_item_edit' => [
        'name' => 'required|string|max:255',
        'description' => 'string|max:1000',
        'price' => 'required|numeric|min:0', // Allow 0 for free items
        'category_id' => 'string|max:50', // Optional for edit (can be null to remove category)
        'image_url' => 'string|max:5000',
        'stock' => 'integer|min:0',
        'is_available' => 'boolean',
    ],
    
    // Category validation rules - sadece name (Türkçe kategori adı) zorunlu
    'category' => [
        'name' => 'required|string|max:255',
        // description, name_en, image_url, default_production_point opsiyonel
    ],
    
    // Order validation rules
    'order' => [
        'table_id' => 'required|string|max:50',
        'items' => 'required|array',
        'items.*.menu_item_id' => 'required|string|max:50',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.price' => 'required|numeric|min:0',
        'customer_note' => 'string|max:1000',
        'order_source' => 'string|in:QR,POS,PHONE',
    ],
    
    // Order item validation rules
    'order_item' => [
        'order_id' => 'required|string|max:50',
        'menu_item_id' => 'required|string|max:50',
        'quantity' => 'required|integer|min:1',
        'price' => 'required|numeric|min:0',
        'note' => 'string|max:500',
    ],
    
    // Table validation rules
    'table' => [
        'name' => 'required|string|max:255',
        'zone' => 'string|max:100',
        'zone_id' => 'string|max:50',
        'capacity' => 'integer|min:1|max:50',
        'status' => 'string|in:' . $tableStatusList,
    ],
    
    // Reservation validation rules
    'reservation' => [
        'customer_name' => 'required|string|max:255',
        'customer_phone' => 'string|max:50',
        'date' => 'required|date',
        'time' => 'required|string|regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
        'guest_count' => 'integer|min:1|max:50',
        'table_id' => 'string|max:50',
        'notes' => 'string|max:1000',
    ],
    
    // Payment transaction validation rules
    'payment_transaction' => [
        'table_id' => 'required|string|max:50',
        'amount' => 'required|numeric|min:0',
        'payment_method' => 'required|string|in:' . $paymentMethodList,
        'tip' => 'numeric|min:0',
        'order_id' => 'string|max:50',
    ],
    
    // Expense validation rules
    'expense' => [
        'category' => 'required|string|in:RENT,BILLS,SUPPLY,SALARY,OTHER',
        'amount' => 'required|numeric|min:0',
        'description' => 'string|max:1000',
        'date' => 'required|date',
        'supplier_id' => 'string|max:50',
    ],
    
    // Shift validation rules
    'shift' => [
        'staff_id' => 'required|string|max:50',
        'opening_cash' => 'numeric|min:0',
        'closing_cash' => 'numeric|min:0',
    ],
    
    // Waste record validation rules
    'waste_record' => [
        'ingredient_id' => 'string|max:50',
        'amount' => 'required|numeric|min:0',
        'reason' => 'required|string|in:SPOILAGE,SPILL,MISTAKE,OTHER',
        'date' => 'required|date',
    ],
    
    // Supplier validation rules
    'supplier' => [
        'name' => 'required|string|max:255',
        'contact' => 'string|max:255',
        'category' => 'string|in:FOOD,BEVERAGE,SUPPLY,OTHER',
    ],
    
    // Invoice validation rules
    'invoice' => [
        'supplier_id' => 'string|max:50',
        'invoice_number' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'date' => 'required|date',
        'due_date' => 'date',
    ],
    
    // Settings validation rules
    'settings' => [
        'site_name' => 'string|max:255',
        'restaurant_phone' => 'string|max:50',
        'restaurant_email' => 'email|max:255',
        'restaurant_address' => 'string|max:500',
        'restaurant_working_hours' => 'string|max:255',
        'service_charge_rate' => 'numeric|min:0|max:100',
        'cover_charge' => 'numeric|min:0',
        'currency' => 'string|max:10',
        'app_env' => 'string|in:development,production,testing',
        'app_debug' => 'string|in:true,false,1,0',
        'timezone' => 'string|max:50',
        'default_language' => 'string|max:10',
        'session_timeout' => 'integer|min:1|max:1440',
        'max_upload_size' => 'integer|min:1',
        'supported_languages' => 'array',
        'language_switcher_enabled' => 'string|in:1,0',
        'auto_detect_language' => 'string|in:1,0',
        'smtp_host' => 'string|max:255',
        'smtp_port' => 'integer|min:1|max:65535',
        'smtp_encryption' => 'string|in:tls,ssl,none',
        'smtp_username' => 'string|max:255',
        'smtp_password' => 'string|max:255',
        'smtp_from_name' => 'string|max:255',
        'iyzico_api_key' => 'string|max:255',
        'iyzico_secret_key' => 'string|max:255',
        'webhook_url' => 'url|max:500',
        'netgsm_username' => 'string|max:255',
        'netgsm_password' => 'string|max:255',
        'netgsm_msgheader' => 'string|max:50',
    ],
    
    // Contact form validation rules
    'contact_form' => [
        'full_name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'string|max:20',
        'company_name' => 'string|max:255',
        'message' => 'string|max:5000',
    ],
];

