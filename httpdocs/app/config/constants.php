<?php
/**
 * Application Constants
 * Centralized constants configuration
 * 
 * Note: Some constants are loaded dynamically from database via ConstantsService
 * This file provides fallback values and static constants
 */

return [
    // Production Points
    'production_points' => [
        'KITCHEN' => 'KITCHEN',
        'BAR' => 'BAR',
        'SERVICE' => 'SERVICE',
        'NONE' => 'NONE'
    ],
    
    // Order Statuses (fallback - loaded from database via ConstantsService)
    'order_statuses' => [
        'PENDING' => 'PENDING',
        'PREPARING' => 'PREPARING',
        'READY' => 'READY',
        'SERVED' => 'SERVED',
        'CANCELLED' => 'CANCELLED',
        'ISSUE' => 'ISSUE',
        'ON_DELIVERY' => 'ON_DELIVERY',
        'DELIVERED' => 'DELIVERED'
    ],
    
    // Table Statuses (fallback - loaded from database via ConstantsService)
    'table_statuses' => [
        'FREE' => 'FREE',
        'OCCUPIED' => 'OCCUPIED',
        'PAYMENT_PENDING' => 'PAYMENT_PENDING',
        'DIRTY' => 'DIRTY',
        'RESERVED' => 'RESERVED'
    ],
    
    // User Roles (fallback - loaded from database via ConstantsService)
    'user_roles' => [
        'MANAGER' => 'MANAGER',
        'WAITER' => 'WAITER',
        'KITCHEN' => 'KITCHEN',
        'CASHIER' => 'CASHIER',
        'CUSTOMER' => 'CUSTOMER'
    ],
    
    // Payment Methods (fallback - loaded from database via ConstantsService)
    'payment_methods' => [
        'CASH' => 'CASH',
        'CREDIT_CARD' => 'CREDIT_CARD',
        'ONLINE_PAYMENT' => 'ONLINE_PAYMENT',
        'OTHER' => 'OTHER'
    ],
    
    // File Upload Limits
    'file_upload' => [
        'max_image_size' => 5242880, // 5MB
        'max_favicon_size' => 1048576, // 1MB
        'max_document_size' => 10485760, // 10MB
        'allowed_image_types' => [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif',
            'image/svg+xml',
            'image/webp'
        ],
        'allowed_favicon_types' => [
            'image/x-icon',
            'image/vnd.microsoft.icon',
            'image/png',
            'image/svg+xml'
        ],
        'allowed_document_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ]
    ],
    
    // Pagination
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100
    ],
    
    // Export Formats
    'export_formats' => [
        'csv' => 'csv',
        'excel' => 'excel',
        'pdf' => 'pdf'
    ],
    
    // API Response Codes
    'api_codes' => [
        'SUCCESS' => 'SUCCESS',
        'ERROR' => 'ERROR',
        'VALIDATION_ERROR' => 'VALIDATION_ERROR',
        'UNAUTHORIZED' => 'UNAUTHORIZED',
        'NOT_AUTHENTICATED' => 'NOT_AUTHENTICATED',
        'NOT_FOUND' => 'NOT_FOUND',
        'SERVER_ERROR' => 'SERVER_ERROR',
        'METHOD_NOT_ALLOWED' => 'METHOD_NOT_ALLOWED'
    ],
    
    // Order Sources
    'order_sources' => [
        'QR' => 'QR',
        'POS' => 'POS',
        'PHONE' => 'PHONE'
    ],
    
    // Notification Types
    'notification_types' => [
        'CALL_WAITER' => 'CALL_WAITER',
        'REQUEST_BILL' => 'REQUEST_BILL',
        'ORDER_READY' => 'ORDER_READY',
        'ORDER_CANCELLED' => 'ORDER_CANCELLED'
    ],
    
    // Leave Types (fallback - loaded from database)
    'leave_types' => [
        'ANNUAL' => 'ANNUAL',
        'SICK' => 'SICK',
        'PERSONAL' => 'PERSONAL',
        'UNPAID' => 'UNPAID'
    ],
    
    // Medical Report Types
    'medical_report_types' => [
        'SICK_LEAVE' => 'SICK_LEAVE',
        'MEDICAL_CERTIFICATE' => 'MEDICAL_CERTIFICATE',
        'HEALTH_REPORT' => 'HEALTH_REPORT'
    ]
];

/**
 * Get constant value
 * @param string $key Constant key (e.g., 'production_points.KITCHEN')
 * @param mixed $default Default value if not found
 * @return mixed Constant value
 */
function getConstant(string $key, $default = null) {
    static $constants = null;
    
    if ($constants === null) {
        $constants = require __DIR__ . '/constants.php';
    }
    
    $keys = explode('.', $key);
    $value = $constants;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Get production points array
 * @return array Production points
 */
function getProductionPoints(): array {
    return getConstant('production_points', []);
}

/**
 * Get valid production point
 * @param string $point Production point to validate
 * @return string|null Valid production point or null
 */
function getValidProductionPoint(?string $point): ?string {
    $points = getProductionPoints();
    return isset($points[$point]) ? $points[$point] : null;
}

/**
 * Get order statuses array
 * @return array Order statuses
 */
function getOrderStatuses(): array {
    // Try to load from ConstantsService first
    try {
        $constantsService = \App\Core\DependencyFactory::getConstantsService();
        $statuses = $constantsService->getOrderStatusCodes();
        if (!empty($statuses)) {
            return array_flip($statuses); // Convert to associative array
        }
    } catch (\Exception $e) {
        // Fallback to static constants
    }
    
    return getConstant('order_statuses', []);
}

/**
 * Get table statuses array
 * @return array Table statuses
 */
function getTableStatuses(): array {
    // Try to load from ConstantsService first
    try {
        $constantsService = \App\Core\DependencyFactory::getConstantsService();
        $statuses = $constantsService->getTableStatusCodes();
        if (!empty($statuses)) {
            return array_flip($statuses);
        }
    } catch (\Exception $e) {
        // Fallback to static constants
    }
    
    return getConstant('table_statuses', []);
}

/**
 * Get user roles array
 * @return array User roles
 */
function getUserRoles(): array {
    // Try to load from ConstantsService first
    try {
        $constantsService = \App\Core\DependencyFactory::getConstantsService();
        $roles = $constantsService->getRoleCodes();
        if (!empty($roles)) {
            return array_flip($roles);
        }
    } catch (\Exception $e) {
        // Fallback to static constants
    }
    
    return getConstant('user_roles', []);
}

