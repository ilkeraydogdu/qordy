<?php

return [
    '' => 'LandingController@index',
    
    'manifest.json' => 'LandingController@manifest',
    
    'downloads/{file}' => 'LandingController@download',
    
    'pricing' => 'LandingController@pricing',
    'fiyatlandirma' => 'LandingController@pricing',
    'fiyatlar' => 'LandingController@pricing',
    'features' => 'LandingController@features',
    'ozellikler' => 'LandingController@features',
    'hakkimizda' => 'LandingController@about',
    'iletisim' => 'LandingController@contact',
    'gizlilik' => 'LandingController@privacy',
    'kullanim-sartlari' => 'LandingController@terms',
    'GET:api/packages' => 'LandingController@apiPackages',

    // Yerel QR üretimi (üçüncü parti api.qrserver.com kullanılmaz).
    // Kullanım: /qr?data=<url>&size=500&margin=10
    'GET:qr' => 'QRCodeController@generate',
    
    'login' => 'Auth/RegistrationController@publicLogin',
    'POST:login' => 'Auth/RegistrationController@publicLogin',
    'register' => 'Auth/RegistrationController@register',
    'POST:register' => 'Auth/RegistrationController@register',
    'GET:api/register/check-subdomain' => 'Auth/RegistrationController@checkSubdomainAvailability',
    'POST:api/register/send-email-code' => 'Auth/RegistrationController@sendRegisterEmailCode',
    'POST:api/register/verify-email' => 'Auth/RegistrationController@verifyRegisterEmail',
    'POST:api/register/send-phone-code' => 'Auth/RegistrationController@sendRegisterPhoneCode',
    'POST:api/register/verify-phone' => 'Auth/RegistrationController@verifyRegisterPhone',
    'staff/login' => 'Auth/SessionController@login', 
    'POST:staff/login' => 'Auth/SessionController@login',
    'qodmin/login' => 'Auth/QordyAdminLoginController@qodminLogin',
    'POST:qodmin/login' => 'Auth/QordyAdminLoginController@qodminLogin',
    
    'GET:api/csrf-token' => 'Auth/SessionController@refreshCsrfToken',
    'logout' => 'Auth/SessionController@logout',
    'unauthorized' => 'Auth/SessionController@unauthorized',
    'auth/2fa/verify' => 'Auth/TwoFactorController@show2FAVerify',
    'POST:auth/2fa/verify' => 'Auth/TwoFactorController@verify2FA',
    'POST:auth/2fa/switch' => 'Auth/TwoFactorController@switch2FAMethod',
    'POST:auth/2fa/resend' => 'Auth/TwoFactorController@resend2FACode',
    'business' => 'Admin/DashboardController@dashboard', 
    'dashboard' => 'Admin/DashboardController@dashboard', 
    'business/dashboard/{rangeParam}' => 'Admin/DashboardController@dashboard', // v2.1 path-based
    'business/profile' => 'Admin/ProfileController@index',
    'POST:business/profile/update' => 'Admin/ProfileController@update',
    
    'business/account' => 'Customer/AccountController@index',
    'GET:business/account/update' => 'Customer/AccountController@index', 
    'POST:business/account/update' => 'Customer/AccountController@update',
    
    'business/packages' => 'Admin/PackagesController@index',
    'business/packages/create' => 'Admin/PackagesController@create',
    'POST:business/packages' => 'Admin/PackagesController@store',
    'business/packages/{id}/edit' => 'Admin/PackagesController@edit',
    'POST:business/packages/{id}' => 'Admin/PackagesController@update',
    'POST:business/packages/{id}/delete' => 'Admin/PackagesController@delete',
    'GET:api/business/packages/{id}/permissions' => 'Admin/PackagesController@getPermissions',
    'POST:api/business/packages/{id}/permissions' => 'Admin/PackagesController@assignPermission',
    'DELETE:api/business/packages/{id}/permissions/{permissionId}' => 'Admin/PackagesController@removePermission',
    // Rol-bazlı paket yönetimi (yeni)
    'GET:api/business/packages/{id}/roles' => 'Admin/PackagesController@getRoles',
    'POST:api/business/packages/{id}/roles' => 'Admin/PackagesController@assignRoles',
    'PUT:api/business/packages/{id}/roles' => 'Admin/PackagesController@assignRoles',
    
    'business/subscriptions' => 'Admin/SubscriptionsController@index',
    'business/subscriptions/{id}' => 'Admin/SubscriptionsController@show',
    'POST:business/subscriptions/{id}/cancel' => 'Admin/SubscriptionsController@cancel',
    'POST:business/subscriptions/{id}/upgrade' => 'Admin/SubscriptionsController@upgrade',
    'POST:business/subscriptions/{id}/activate' => 'Admin/SubscriptionsController@activate',
    
    'customer/packages' => 'Customer/PackageController@index', 
    'customer/packages/list' => 'Customer/PackageController@listPackages', 
    'GET:customer/packages/{id}/purchase' => 'Customer/PackageController@purchase',
    'POST:customer/packages/{id}/purchase' => 'Customer/PackageController@purchase',
    'customer/payment' => 'Customer/PackageController@processPayment',
    'POST:customer/payment/process' => 'Customer/PackageController@processPayment',
    'customer/payment/callback' => 'Customer/PackageController@paymentCallback',
    'customer/my-subscription' => 'Customer/PackageController@mySubscription',
    'customer/subscription' => 'Customer/PackageController@subscriptionDetail',
    'POST:customer/subscription/cancel' => 'Customer/PackageController@cancelSubscription',
    'customer/payment-history' => 'Customer/PackageController@paymentHistory',
    'customer/saved-cards' => 'Customer/PackageController@savedCards',

    // Business owner'ın kayıtlı kartlarını yöneten sayfa (/business/payment-methods).
    // Bu kontrolcü index/delete/setDefault eylemlerini gerçek veriyle sunuyor;
    // kart EKLEME ise PCI gerekçesiyle iyzico hosted flow üzerinden
    // /customer/packages akışına yönlendirir.
    // Business owner'ın abonelik makbuzları / faturaları.
    'business/billing'                             => 'Customer/BillingController@index',
    'GET:business/billing/{id}/download'           => 'Customer/BillingController@download',

    'business/payment-methods'                 => 'Customer/PaymentMethodsController@index',
    'POST:business/payment-methods/add'        => 'Customer/PaymentMethodsController@add',
    'POST:business/payment-methods/delete'     => 'Customer/PaymentMethodsController@delete',
    'POST:business/payment-methods/set-default' => 'Customer/PaymentMethodsController@setDefault',
    
    'customer/orders' => 'Customer/OrderController@index',
    'customer/orders/{id}' => 'Customer/OrderController@detail',
    
    'customer/payment/success' => 'Customer/PaymentController@successPage',
    'customer/payment/fail' => 'Customer/PaymentController@failPage',
    'POST:customer/payment/iyzico/initiate' => 'Customer/PaymentController@initiateIyzico',
    'GET:customer/payment/iyzico/frame' => 'Customer/PaymentController@iyzicoFrame',
    'POST:customer/payment/iyzico/callback' => 'Customer/PaymentController@iyzicoCallback',
    'GET:customer/payment/iyzico/callback' => 'Customer/PaymentController@iyzicoCallback',
    'POST:customer/payment/upload-receipt' => 'Customer/PackageController@uploadReceipt',
    'POST:customer/saved-cards/add' => 'Customer/PaymentController@saveCard',
    'POST:customer/saved-cards/{id}/delete' => 'Customer/PaymentController@deleteCard',
    'POST:customer/saved-cards/{id}/set-default' => 'Customer/PaymentController@setDefaultCard',
    
    'business/menu' => 'MenuController@index',
    'POST:business/menu' => 'MenuController@add', 
    'GET:business/menu/add' => function() {
        header('Location: ' . BASE_URL . '/business/menu', true, 302);
        exit;
    },
    'POST:business/menu/add' => 'MenuController@add',
    'GET:business/menu/edit/{id}' => function() {
        header('Location: ' . BASE_URL . '/business/menu', true, 302);
        exit;
    },
    'POST:business/menu/edit/{id}' => 'MenuController@edit',
    'POST:business/menu/delete/{id}' => 'MenuController@delete',
    'POST:business/menu/translate' => 'MenuController@translate',
    'POST:business/menu/translate-product-name' => 'MenuController@translateProductName',
    'POST:business/menu/translate-category-name' => 'MenuController@translateCategoryName',
    'POST:business/menu/bulk-update-prices' => 'MenuController@bulkUpdatePrices',
    'GET:business/menu/fix-preparation-screens' => 'MenuController@fixPreparationScreens',
    'POST:business/menu/fix-preparation-screens' => 'MenuController@fixPreparationScreens',
    'POST:api/business/menu/extract-from-image' => 'MenuController@extractMenuFromImage',
    'POST:api/business/menu/bulk-add-from-extraction' => 'MenuController@bulkAddFromExtraction',
    'business/categories' => 'CategoryController@index',
    'POST:business/categories/add' => 'CategoryController@add',
    'POST:business/categories/edit/{id}' => 'CategoryController@edit',
    'POST:business/categories/delete/{id}' => 'CategoryController@delete',
    'business/orders' => 'OrderController@index',
    'business/orders/{id}' => 'OrderController@detail',
    'business/tables' => 'Admin/TablesController@tables',
    'business/zones' => 'Admin/TablesController@zones',
    'business/table-history/{id}' => 'Admin/TablesController@tableHistory',
    'api/business/zones' => 'ZoneController@getZones',
    'POST:api/business/zones' => 'ZoneController@createZone',
    'GET:api/business/zones/{id}' => 'ZoneController@getZone',
    'PUT:api/business/zones/{id}' => 'ZoneController@updateZone',
    'DELETE:api/business/zones/{id}' => 'ZoneController@deleteZone',
    'api/business/tables/generate-qr-code' => 'TableController@generateQRCode',
    'business/inventory' => 'StockController@index',
    'business/stock' => 'StockController@index',
    'GET:api/business/stock/list' => 'StockController@getStockList',
    'GET:api/business/stock/summary' => 'StockController@getStockSummary',
    'GET:api/business/stock/movements' => 'StockController@movements',
    'POST:api/business/stock/add' => 'StockController@addStock',
    'POST:api/business/stock/remove' => 'StockController@removeStock',
    'POST:api/business/stock/transfer' => 'StockController@transferStock',
    'POST:api/business/stock/adjust' => 'StockController@adjustStock',
    // Phase 2 — stock categories + units
    'business/stock-categories'                 => 'StockCategoryController@index',
    'qodmin/stock-categories'                   => 'StockCategoryController@index',
    'GET:api/business/stock-categories'         => 'StockCategoryController@listCategories',
    'GET:api/business/stock-categories/tree'    => 'StockCategoryController@categoryTree',
    'POST:api/business/stock-categories'        => 'StockCategoryController@createCategory',
    'PUT:api/business/stock-categories/{id}'    => 'StockCategoryController@updateCategory',
    'DELETE:api/business/stock-categories/{id}' => 'StockCategoryController@deleteCategory',
    'GET:api/business/stock-units'              => 'StockCategoryController@listUnits',
    'POST:api/business/stock-units'             => 'StockCategoryController@createUnit',
    'DELETE:api/business/stock-units/{id}'      => 'StockCategoryController@deleteUnit',
    // qodmin mirror (super admin routes)
    'GET:api/qodmin/stock-categories'         => 'StockCategoryController@listCategories',
    'GET:api/qodmin/stock-categories/tree'    => 'StockCategoryController@categoryTree',
    'POST:api/qodmin/stock-categories'        => 'StockCategoryController@createCategory',
    'PUT:api/qodmin/stock-categories/{id}'    => 'StockCategoryController@updateCategory',
    'DELETE:api/qodmin/stock-categories/{id}' => 'StockCategoryController@deleteCategory',
    'GET:api/qodmin/stock-units'              => 'StockCategoryController@listUnits',
    'POST:api/qodmin/stock-units'             => 'StockCategoryController@createUnit',
    'DELETE:api/qodmin/stock-units/{id}'      => 'StockCategoryController@deleteUnit',
    // Phase 2 — purchase receipts
    'business/purchases'                             => 'PurchaseReceiptController@index',
    'qodmin/purchases'                               => 'PurchaseReceiptController@index',
    'GET:api/business/purchases'                     => 'PurchaseReceiptController@listReceipts',
    'GET:api/business/purchases/{id}'                => 'PurchaseReceiptController@getReceipt',
    'POST:api/business/purchases'                    => 'PurchaseReceiptController@createReceipt',
    'DELETE:api/business/purchases/{id}'             => 'PurchaseReceiptController@deleteReceipt',
    'GET:api/business/purchases/active-lots/{ingredientId}' => 'PurchaseReceiptController@activeLots',
    // Phase 2 lookups
    'GET:api/business/suppliers'   => 'PurchaseReceiptController@lookupSuppliers',
    'GET:api/business/ingredients' => 'PurchaseReceiptController@lookupIngredients',
    'GET:api/qodmin/suppliers'     => 'PurchaseReceiptController@lookupSuppliers',
    'GET:api/qodmin/ingredients'   => 'PurchaseReceiptController@lookupIngredients',
    'GET:api/qodmin/purchases/active-lots/{ingredientId}' => 'PurchaseReceiptController@activeLots',

    // Phase 2 — supplier performance analytics.
    'business/supplier-performance' => 'SupplierPerformanceController@index',
    'qodmin/supplier-performance'   => 'SupplierPerformanceController@index',
    'GET:api/business/supplier-performance/leaderboard' => 'SupplierPerformanceController@leaderboard',
    'GET:api/business/supplier-performance/trend'       => 'SupplierPerformanceController@trend',
    'GET:api/business/supplier-performance/{supplierId}' => 'SupplierPerformanceController@detail',
    'GET:api/qodmin/supplier-performance/leaderboard'   => 'SupplierPerformanceController@leaderboard',
    'GET:api/qodmin/supplier-performance/trend'         => 'SupplierPerformanceController@trend',
    'GET:api/qodmin/supplier-performance/{supplierId}'  => 'SupplierPerformanceController@detail',

    // Phase 2 — STOCK_MANAGER dedicated dashboard.
    'business/stock-dashboard' => 'StockDashboardController@index',
    'qodmin/stock-dashboard'   => 'StockDashboardController@index',

    // Phase 2 — Guest staff (yevmiyeci) CRUD.
    'business/guest-staff'                => 'GuestStaffController@index',
    'qodmin/guest-staff'                  => 'GuestStaffController@index',
    'GET:api/business/guest-staff'        => 'GuestStaffController@list',
    'POST:api/business/guest-staff'       => 'GuestStaffController@create',
    'POST:api/business/guest-staff/{id}'  => 'GuestStaffController@update',
    'DELETE:api/business/guest-staff/{id}'=> 'GuestStaffController@delete',
    'GET:api/qodmin/guest-staff'          => 'GuestStaffController@list',
    'POST:api/qodmin/guest-staff'         => 'GuestStaffController@create',
    'POST:api/qodmin/guest-staff/{id}'    => 'GuestStaffController@update',
    'DELETE:api/qodmin/guest-staff/{id}'  => 'GuestStaffController@delete',

    // Phase 2 — per-item low stock configuration.
    'business/low-stock'                        => 'LowStockController@index',
    'qodmin/low-stock'                          => 'LowStockController@index',
    'GET:api/business/low-stock'                => 'LowStockController@list',
    'POST:api/business/low-stock/{id}'          => 'LowStockController@update',
    'POST:api/business/low-stock/trigger'       => 'LowStockController@triggerNow',
    'GET:api/qodmin/low-stock'                  => 'LowStockController@list',
    'POST:api/qodmin/low-stock/{id}'            => 'LowStockController@update',
    'POST:api/qodmin/low-stock/trigger'         => 'LowStockController@triggerNow',
    'GET:api/qodmin/purchases'                       => 'PurchaseReceiptController@listReceipts',
    'GET:api/qodmin/purchases/{id}'                  => 'PurchaseReceiptController@getReceipt',
    'POST:api/qodmin/purchases'                      => 'PurchaseReceiptController@createReceipt',
    'DELETE:api/qodmin/purchases/{id}'               => 'PurchaseReceiptController@deleteReceipt',
    'business/receipts' => 'ReceiptController@history',
    'business/receipt-templates' => 'ReceiptController@templates',
    'POST:api/business/settings/update' => 'BusinessAdminController@updateBusinessSettings',
    'POST:api/business/receipt/generate' => 'ReceiptController@generate',
    'GET:api/business/receipt/{id}' => 'ReceiptController@getReceipt',
    'POST:api/business/receipt-template/save' => 'ReceiptController@saveTemplate',
    'business/printers' => 'PrinterController@index',
    'business/printers/bridge-setup' => 'PrinterController@bridgeSetup',
    'GET:api/business/printer-bridges' => 'API/PrinterBridgeManagementController@index',
    'POST:api/business/printer-bridges/create' => 'API/PrinterBridgeManagementController@create',
    'POST:api/business/printer-bridges/{id}/regenerate' => 'API/PrinterBridgeRegenerateController@regenerate',
    'DELETE:api/business/printer-bridges/{id}' => 'API/PrinterBridgeManagementController@delete',
    'POST:api/business/printer/register' => 'PrinterController@register',
    'POST:api/business/printer/test-connection' => 'PrinterController@testConnection',
    'POST:api/business/printer/test-print' => 'PrinterController@testPrint',
    'POST:api/business/printer/test' => 'PrinterController@testPrint',
    'POST:api/business/printer/update' => 'PrinterController@update',
    'POST:api/business/printer/delete' => 'PrinterController@delete',
    // ESKİ SYSTEM KALDIRILDI - Artık sadece printer_bridges.config_code kullanılıyor
    // Yeni köprü oluşturma: POST /api/business/printer-bridges/create
    'GET:api/business/printer/bridges' => 'PrinterController@getBridges',
    'GET:api/business/printer/bridge/{bridgeId}/printers' => 'PrinterController@getBridgePrinters',
    'GET:api/business/printer/{id}/screens' => 'PrinterController@getPrinterScreens',
    'GET:api/business/printer/{id}' => 'PrinterController@getPrinter',
    'POST:api/business/printer/bridge/create' => 'PrinterController@createBridge',
    'POST:api/business/printer/bridge/update' => 'PrinterController@updateBridge',
    'PUT:api/business/printer/bridge/update' => 'PrinterController@updateBridge',
    'POST:api/business/printer/bridge/delete' => 'PrinterController@deleteBridge',
    'DELETE:api/business/printer/bridge/delete' => 'PrinterController@deleteBridge',
    'GET:api/qodmin/printer/zones' => 'PrinterController@getZones',
    'GET:api/qodmin/printer/{id}' => 'PrinterController@getPrinter',
    'POST:api/qodmin/printer/assign-zone' => 'PrinterController@assignToZone',
    'POST:api/qodmin/printer/remove-zone' => 'PrinterController@removeFromZone',
    'business/users' => 'Admin/UsersController@users',
    'business/users/{id}' => 'Admin/UsersController@staffDetail',
    'POST:business/users/add' => 'Admin/UsersController@addStaff',
    'POST:api/business/users/{id}/update' => 'Admin/UsersController@updateStaff',
    'POST:api/business/users/{id}/update-pin' => 'Admin/UsersController@updateStaffPin',
    'api/business/users/{id}/pin' => 'Admin/UsersController@getStaffPin',
    'business/users/delete/{id}' => 'Admin/UsersController@deleteStaff',
    'POST:api/business/leaves/add' => 'Admin/LeaveController@addLeave',
    // Phase 2 — leave listing + approval + my-leaves.
    'GET:api/business/leave-types'         => 'Admin/LeaveController@listLeaveTypes',
    'GET:api/business/leaves/report'      => 'Admin/LeaveController@report',
    'POST:api/business/leaves/managed'    => 'Admin/LeaveController@createManaged',
    'GET:api/business/leaves'              => 'Admin/LeaveController@listLeaves',
    'GET:api/business/leaves/my'           => 'Admin/LeaveController@myLeaves',
    'POST:api/business/leaves/{id}/approve'=> 'Admin/LeaveController@approveLeave',
    'POST:api/business/leaves/{id}/reject' => 'Admin/LeaveController@rejectLeave',
    'business/leaves'                      => 'Admin/LeaveController@dashboard',
    'business/hr/leaves'                   => 'Admin/LeaveController@dashboard',
    'GET:api/business/leaves/{id}' => 'Admin/LeaveController@getLeave',
    'POST:api/business/leaves/{id}/update' => 'Admin/LeaveController@updateLeave',
    'DELETE:api/business/leaves/{id}' => 'Admin/LeaveController@deleteLeave',
    'POST:api/business/medical-reports/add' => 'Admin/MedicalReportController@addMedicalReport',
    'GET:api/business/medical-reports/{id}' => 'Admin/MedicalReportController@getMedicalReport',
    'POST:api/business/medical-reports/{id}/update' => 'Admin/MedicalReportController@updateMedicalReport',
    'DELETE:api/business/medical-reports/{id}' => 'Admin/MedicalReportController@deleteMedicalReport',
    'business/medical-reports/{id}/download' => 'Admin/MedicalReportController@downloadMedicalReport',
    'business/roles-permissions' => 'Admin/RolesPermissionsController@rolesPermissions',
    'POST:api/business/create-role' => 'Admin/RolesPermissionsController@createRole',
    'POST:api/business/update-role' => 'Admin/RolesPermissionsController@updateRole',
    'api/business/delete-role' => 'Admin/RolesPermissionsController@deleteRole',
    'POST:api/business/assign-permissions' => 'Admin/RolesPermissionsController@assignPermissions',
    'api/business/role-permissions' => 'Admin/RolesPermissionsController@getRolePermissions',
    'POST:api/business/add-printer-permissions' => 'Admin/POSDeviceController@addPrinterPermissions',
    'business/analytics' => 'Admin/AnalyticsController@analytics',
    'api/business/analytics-data' => 'Admin/AnalyticsController@getAnalyticsData',
    'api/business/end-of-day' => 'Admin/AnalyticsController@endOfDay',
    'api/business/z-report-pdf' => 'Admin/AnalyticsController@zReportPdf',
    'POST:api/business/z-report-print' => 'Admin/AnalyticsController@zReportPrint',
    'api/business/auto-z-report' => 'Admin/AnalyticsController@autoZReport',
    'api/business/ai-insights' => 'Admin/AIController@getAIInsights',
    'api/business/ai-insights/saved' => 'Admin/AIController@listSavedInsights',
    'POST:api/business/ai-insights/save' => 'Admin/AIController@saveInsight',
    'POST:api/business/ai-insights/unsave' => 'Admin/AIController@unsaveInsight',
    'business/ai-onerileri' => 'Admin/AIController@savedInsightsPage',
    'api/business/dashboard-data' => 'Admin/DashboardController@getDashboardData',
    // Order approvals (sipariş silme/azaltma onayları)
    'business/order-approvals' => 'Admin/OrderApprovalController@index',
    'business/order-approval-history' => 'Admin/OrderApprovalController@history',
    'api/business/order-approvals/pending' => 'Admin/OrderApprovalController@getPendingApprovals',
    'api/business/order-approvals/pending-count' => 'Admin/OrderApprovalController@getPendingCount',
    'api/business/order-approvals/history' => 'Admin/OrderApprovalController@getApprovalHistory',
    'GET:api/business/order-approvals/detail' => 'Admin/OrderApprovalController@getApprovalDetail',
    'api/business/order-approvals/payments' => 'Admin/OrderApprovalController@getPaymentsForHistory',
    'api/business/order-approvals/other-transactions' => 'Admin/OrderApprovalController@getOtherTransactionsForHistory',
    'GET:api/business/order-approvals/receipt-detail' => 'ReceiptController@getReceiptForOrderApprovalHistory',
    'POST:api/business/order-approvals/approve' => 'Admin/OrderApprovalController@approveRequest',
    'POST:api/business/order-approvals/reject' => 'Admin/OrderApprovalController@rejectRequest',
    'GET:api/business/approval-feedback' => 'Admin/OrderApprovalController@getApprovalFeedback',
    'POST:business/settings' => 'Admin/SettingsController@settings',
    'business/error-logs' => 'Admin/ErrorLogController@errorLogs',
    'business/features' => 'Admin/FeatureController@features',
    'POST:api/business/feature/toggle' => 'Admin/FeatureController@toggleFeature',
    'business/payment-gateways' => 'Admin/PaymentGatewayController@paymentGateways',
    'POST:api/business/payment-gateway/update' => 'Admin/PaymentGatewayController@updatePaymentGateway',
    'POST:api/business/payment-gateway/seed' => 'Admin/PaymentGatewayController@seedGateways',
    'business/pos-devices' => 'Admin/POSDeviceController@posDevices',
    'POST:api/business/pos-device/test' => 'Admin/POSDeviceController@testPOSDevice',
    'POST:api/business/pos-device/update' => 'Admin/POSDeviceController@updatePOSDevice',
    'POST:api/business/pos-device/add' => 'Admin/POSDeviceController@addPOSDevice',
    'POST:business/settings/reset' => 'Admin/SystemController@resetSystem',
    'POST:api/business/settings/upload-logo' => 'Admin/LogoController@uploadLogo',
    'POST:api/business/settings/upload-favicon' => 'Admin/LogoController@uploadFavicon',
    'POST:api/business/email/test' => 'Admin/EmailController@testEmail',
    'api/business/email/status' => 'Admin/EmailController@getEmailStatus',
    'api/business/meta/debug-token' => 'Admin/SettingsController@debugMetaToken',
    'POST:api/business/meta/test-whatsapp' => 'Admin/SettingsController@testWhatsApp',
    'api/business/meta/dashboard-stats' => 'Admin/SettingsController@getMetaDashboardStats',
    'api/business/meta/message-history' => 'Admin/SettingsController@getMetaMessageHistory',
    'api/business/meta/top-recipients' => 'Admin/SettingsController@getMetaTopRecipients',
    'POST:api/business/2fa/enable' => 'Admin/TwoFactorController@enable2FA',
    'POST:api/business/2fa/disable' => 'Admin/TwoFactorController@disable2FA',
    'POST:api/business/2fa/send-code' => 'Admin/TwoFactorController@send2FACode',
    'POST:api/business/2fa/verify' => 'Admin/TwoFactorController@verify2FA',
    'business/reservations' => 'ReservationController@index',
    'business/reservations/add' => 'ReservationController@showAddForm',
    'POST:business/reservations/add' => 'ReservationController@add',
    'business/reservations/edit/{id}' => 'ReservationController@edit',
    'POST:business/reservations/update/{id}' => 'ReservationController@update',
    'POST:business/reservations/delete/{id}' => 'ReservationController@delete',
    'POST:api/business/reservations/send-reminder/{id}' => 'APIController@sendReservationReminder',

    // ---------------------------------------------------------------------
    // Queue (Sıra) - QR based waiting line
    // ---------------------------------------------------------------------
    // Public (subdomain): door display, customer form, status
    // Primary, branded paths (TR: sıra / bilet)
    'sira' => 'PublicQueueController@display',
    'sira/katil' => 'PublicQueueController@form',
    'POST:sira/kayit' => 'PublicQueueController@submit',
    'sira/bilet/{queueId}' => 'PublicQueueController@status',
    'POST:sira/iptal/{queueId}' => 'PublicQueueController@cancel',
    'GET:api/sira/token' => 'PublicQueueController@apiToken',
    'GET:api/sira/bilet/{queueId}' => 'PublicQueueController@apiStatus',
    // Legacy aliases (backwards-compatible, still work)
    'q' => 'PublicQueueController@display',
    'q/form' => 'PublicQueueController@form',
    'POST:q/submit' => 'PublicQueueController@submit',
    'q/status/{queueId}' => 'PublicQueueController@status',
    'POST:q/cancel/{queueId}' => 'PublicQueueController@cancel',
    'GET:api/q/token' => 'PublicQueueController@apiToken',
    'GET:api/q/status/{queueId}' => 'PublicQueueController@apiStatus',

    // Admin (main domain): queue management
    'business/queue' => 'QueueController@index',
    'business/queue/settings' => 'QueueController@settings',
    'POST:business/queue/settings' => 'QueueController@saveSettings',
    'POST:business/queue/settings/patch' => 'QueueController@patchSetting',
    'POST:business/queue/{id}/notify' => 'QueueController@notify',
    'POST:business/queue/{id}/seat' => 'QueueController@seat',
    'POST:business/queue/{id}/cancel' => 'QueueController@cancel',
    'POST:business/queue/{id}/no-show' => 'QueueController@noShow',
    'POST:business/queue/qr/rotate' => 'QueueController@rotateQrToken',
    'POST:business/queue/entries/delete' => 'QueueController@deleteCrmEntries',
    'GET:api/business/queue/list' => 'QueueController@apiList',
    'GET:api/business/queue/available-tables' => 'QueueController@availableTables',

    'business/finance/expenses' => 'FinanceController@expenses',
    'business/finance/invoices' => 'FinanceController@invoices',
    'business/finance/suppliers' => 'FinanceController@suppliers',
    'business/finance/waste' => 'FinanceController@waste',
    
    // Account Management Routes
    'business/account/profile' => 'Business/AccountController@profile',
    'business/account/payments' => 'Business/AccountController@payments',
    'business/account/subscription' => 'Business/AccountController@subscription',
    // Phase 2 — shift scheduling (restored).
    'business/shifts' => 'Admin/ShiftsController@shifts',
    'business/hr/shifts' => 'Admin/ShiftsController@shifts',
    'qodmin/shifts'   => 'Admin/ShiftsController@shifts',
    'POST:business/shifts/create'                  => 'Admin/ShiftsController@createShift',
    'POST:business/shifts/update'                  => 'Admin/ShiftsController@updateShift',
    'POST:business/shifts/delete'                  => 'Admin/ShiftsController@deleteShift',
    'POST:business/shifts/save-schedule'           => 'Admin/ShiftsController@saveStaffSchedule',
    'POST:business/shifts/create-schedule'         => 'Admin/ShiftsController@createShiftSchedule',
    'POST:business/shifts/create-weekly-schedule'  => 'Admin/ShiftsController@createWeeklyShiftSchedule',
    'POST:business/shifts/create-tables'           => 'Admin/ShiftsController@generateShiftsFromSchedule',
    'POST:qodmin/shifts/create'                    => 'Admin/ShiftsController@createShift',
    'POST:qodmin/shifts/update'                    => 'Admin/ShiftsController@updateShift',
    'POST:qodmin/shifts/delete'                    => 'Admin/ShiftsController@deleteShift',
    'POST:qodmin/shifts/save-schedule'             => 'Admin/ShiftsController@saveStaffSchedule',
    'POST:qodmin/shifts/create-schedule'           => 'Admin/ShiftsController@createShiftSchedule',
    'POST:qodmin/shifts/create-weekly-schedule'    => 'Admin/ShiftsController@createWeeklyShiftSchedule',
    'POST:qodmin/shifts/create-tables'             => 'Admin/ShiftsController@generateShiftsFromSchedule',
    // Phase 2 — clock-in/out + my-schedule.
    'POST:api/business/shifts/{id}/clock-in'  => 'Admin/ShiftsController@clockIn',
    'POST:api/business/shifts/{id}/clock-out' => 'Admin/ShiftsController@clockOut',
    'POST:api/qodmin/shifts/{id}/clock-in'    => 'Admin/ShiftsController@clockIn',
    'POST:api/qodmin/shifts/{id}/clock-out'   => 'Admin/ShiftsController@clockOut',
    'GET:api/business/shifts/my'              => 'Admin/ShiftsController@mySchedule',
    'GET:api/qodmin/shifts/my'                => 'Admin/ShiftsController@mySchedule',
    'GET:api/business/shift-schedules/{id}'   => 'Admin/ShiftsController@getShiftSchedule',
    'GET:api/qodmin/shift-schedules/{id}'     => 'Admin/ShiftsController@getShiftSchedule',
    'POST:api/business/shift-schedules/{id}/update' => 'Admin/ShiftsController@updateShiftSchedule',
    'POST:api/qodmin/shift-schedules/{id}/update'   => 'Admin/ShiftsController@updateShiftSchedule',
    'PUT:api/business/shift-schedules/{id}'   => 'Admin/ShiftsController@updateShiftSchedule',
    'PUT:api/qodmin/shift-schedules/{id}'     => 'Admin/ShiftsController@updateShiftSchedule',
    'POST:api/business/shift-schedules/{id}/delete' => 'Admin/ShiftsController@deleteShiftSchedule',
    'POST:api/qodmin/shift-schedules/{id}/delete'   => 'Admin/ShiftsController@deleteShiftSchedule',
    'DELETE:api/business/shift-schedules/{id}' => 'Admin/ShiftsController@deleteShiftSchedule',
    'DELETE:api/qodmin/shift-schedules/{id}'   => 'Admin/ShiftsController@deleteShiftSchedule',
    'business/my-schedule'                    => 'Admin/ShiftsController@mySchedulePage',
    'staff/my-schedule'                       => 'Admin/ShiftsController@mySchedulePage',
    'api/business/finance/data' => 'FinanceController@getFinancialData',
    'GET:api/business/finance/categories'           => 'FinanceController@listCategories',
    'POST:api/business/finance/categories'          => 'FinanceController@createCategory',
    'POST:api/business/finance/categories/rename'   => 'FinanceController@renameCategory',
    'POST:api/business/finance/categories/delete'   => 'FinanceController@deleteCategory',
    'GET:api/qodmin/finance/categories'             => 'FinanceController@listCategories',
    'POST:api/qodmin/finance/categories'            => 'FinanceController@createCategory',
    'POST:api/qodmin/finance/categories/rename'     => 'FinanceController@renameCategory',
    'POST:api/qodmin/finance/categories/delete'     => 'FinanceController@deleteCategory',

    // Finance analytics (fire/stok/tedarikçi özet + drill-down)
    'GET:api/business/finance/analytics'                        => 'FinanceController@analytics',
    'GET:api/qodmin/finance/analytics'                          => 'FinanceController@analytics',
    'GET:api/business/finance/suppliers/{id}/analytics'         => 'FinanceController@supplierAnalytics',
    'GET:api/qodmin/finance/suppliers/{id}/analytics'           => 'FinanceController@supplierAnalytics',

    // Supplier detail HTML pages
    'business/finance/suppliers/{id}'                           => 'FinanceController@supplierDetail',
    'qodmin/finance/suppliers/{id}'                             => 'FinanceController@supplierDetail',
    'business/preparation-screens' => 'Admin/PreparationScreenController@index',
    'business/preparation-screens/create' => 'Admin/PreparationScreenController@create',
    'POST:business/preparation-screens' => 'Admin/PreparationScreenController@store',
    'business/preparation-screens/edit/{id}' => 'Admin/PreparationScreenController@edit',
    'POST:business/preparation-screens/update/{id}' => 'Admin/PreparationScreenController@update',
    'POST:business/preparation-screens/toggle-active/{id}' => 'Admin/PreparationScreenController@toggleActive',
    'POST:business/preparation-screens/delete/{id}' => 'Admin/PreparationScreenController@delete',
    'GET:api/business/preparation-screens' => 'Admin/PreparationScreenController@getAll',
    'GET:api/business/preparation-screens/active' => 'Admin/PreparationScreenController@getActiveScreens',
    'api/business/preparation-screens/categories' => 'Admin/PreparationScreenController@getCategories',
    'GET:api/business/preparation-screens/{id}/printers' => 'Admin/PreparationScreenPrinterController@index',
    'POST:api/business/preparation-screens/{id}/assign-printer' => 'Admin/PreparationScreenPrinterController@assign',
    'POST:api/business/preparation-screens/{id}/remove-printer' => 'Admin/PreparationScreenPrinterController@remove',
    'POST:api/business/preparation-screens/{id}/update-priority' => 'Admin/PreparationScreenPrinterController@updatePriority',
    'business/preparation-screens/{slug}' => 'PreparationScreenController@dashboard',
    'business/preparation-screens/{slug}/orders' => 'PreparationScreenController@getOrders',
    'POST:business/preparation-screens/{slug}/update-status' => 'PreparationScreenController@updateOrderStatus',
    // Product Sales Analytics
    'business/product-sales' => 'Admin/ProductSalesController@index',
    // Legacy / defensive alias: earlier seeds stored the nav URL under
    // /analytics/product-sales. Canonical route is /business/product-sales.
    // Keep the legacy URL working so old bookmarks/nav caches don't 404.
    'business/analytics/product-sales' => 'Admin/ProductSalesController@index',
    'GET:api/business/product-sales/data' => 'Admin/ProductSalesController@getData',
    'GET:api/business/product-sales/receipt' => 'Admin/ProductSalesController@receipt',
    'POST:api/business/product-sales/print' => 'Admin/ProductSalesController@printReceipt',
    
    'business/reports' => 'Admin/ReportsController@reports',
    'business/export-report' => 'Admin/ReportsController@exportReport',
    'GET:api/business/export-orders' => 'Admin/ReportsController@exportOrders',
    'api/business/reports-data' => 'Admin/ReportsController@getReportsData',
    // Shift management routes removed
    'POST:api/business/permissions/sync' => 'Admin/SystemController@syncDynamicPermissions',
    // Shift tables creation removed
    'GET:api/business/receipt-template/design/{business_id}' => 'Admin/ReceiptTemplateDesignController@getLayout',
    'GET:api/business/receipt-template/design' => 'Admin/ReceiptTemplateDesignController@getLayout',
    'POST:api/business/receipt-template/design/save' => 'Admin/ReceiptTemplateDesignController@saveLayout',
    'GET:api/business/receipt-template/design/preview/{layout_id}' => 'Admin/ReceiptTemplateDesignController@preview',
    'GET:api/business/receipt-template/design/list' => 'Admin/ReceiptTemplateDesignController@listLayouts',
    'DELETE:api/business/receipt-template/design/{layout_id}' => 'Admin/ReceiptTemplateDesignController@deleteLayout',
    'POST:api/business/receipt-template/design/{layout_id}/delete' => 'Admin/ReceiptTemplateDesignController@deleteLayout',
    'POST:api/business/receipt-template/design/{layout_id}/set-default' => 'Admin/ReceiptTemplateDesignController@setDefault',
    'POST:api/business/receipt/{id}/void' => 'ReceiptController@void',
    'api/business/receipts' => 'ReceiptController@getReceipts',
    'POST:api/business/receipt/print' => 'ReceiptController@printToPrinter',
    'api/business/receipt/templates' => 'ReceiptController@templates',
    'POST:api/business/receipt/template/save' => 'ReceiptController@saveTemplate',
    // Printer Bridge routes moved to global /api/printer-bridge/* (see line ~864)
    'POST:api/business/images/upload' => 'ImageController@upload',
    'PUT:api/business/images/{id}' => 'ImageController@update',
    'POST:api/business/images/{id}/update' => 'ImageController@update',
    'DELETE:api/business/images/{id}' => 'ImageController@delete',
    'api/business/images/entity/{type}/{id}' => 'ImageController@getByEntity',
    'api/business/images/entity/{type}/{id}/primary' => 'ImageController@getPrimaryImage',
    'POST:api/business/images/{id}/set-primary' => 'ImageController@setPrimary',
    'POST:api/business/images/sort-order' => 'ImageController@updateSortOrder',
    'DELETE:api/business/images/entity/{type}/{id}' => 'ImageController@deleteByEntity',
    'api/business/images/entity/{type}/{id}/responsive' => 'ImageController@getResponsiveUrls',
    'api/business/images/statistics' => 'ImageController@statistics',
    'POST:api/business/images/cleanup-orphaned' => 'ImageController@cleanupOrphaned',
    'api/business/qodmin/getUser' => 'Admin/UsersController@getUser',
    'api/business/qodmin/getTable' => 'TableController@getTable',
    'api/business/qodmin/getOrder' => 'OrderController@getOrder',
    'POST:api/business/qodmin/order/{id}/print' => 'OrderController@printOrder',
    'GET:api/business/qodmin/order/{id}/pdf' => 'OrderController@downloadOrderPDF',
    'POST:api/business/qodmin/order/{id}/send-email' => 'OrderController@sendOrderPDFEmail',
    'POST:api/business/qodmin/update-order-status' => 'APIController@updateOrderStatus',
    'GET:api/business/qodmin/export-orders' => 'Admin/ReportsController@exportOrders',
    'api/business/qodmin/getReservation' => 'ReservationController@getReservation',
    'POST:api/business/qodmin/add-table' => 'APIController@addTable',
    'PUT:api/business/qodmin/update-table' => 'APIController@updateTable',
    'POST:api/business/qodmin/update-table' => 'APIController@updateTable',
    'DELETE:api/business/qodmin/delete-table' => 'APIController@deleteTable',
    // Business table management routes (without qodmin prefix)
    'POST:api/business/add-table' => 'APIController@addTable',
    'PUT:api/business/update-table' => 'APIController@updateTable',
    'POST:api/business/update-table' => 'APIController@updateTable',
    'DELETE:api/business/delete-table' => 'APIController@deleteTable',
    'api/business/download-qr' => 'APIController@downloadQRCode',
    'POST:api/business/reservation/add' => 'APIController@addReservation',
    'POST:api/business/reservation/delete' => 'APIController@deleteReservation',
    'POST:api/business/supplier/add' => 'APIController@addSupplier',
    'POST:api/business/supplier/update' => 'APIController@updateSupplier',
    'POST:api/business/supplier/delete' => 'APIController@deleteSupplier',
    'POST:api/business/invoice/add' => 'APIController@addInvoice',
    'POST:api/business/invoice/pay' => 'APIController@payInvoice',
    // Shift API removed
    'POST:api/business/change-language' => 'APIController@changeLanguage',
    'api/business/notifications' => 'APIController@getNotifications',
    'api/business/get-notifications' => 'APIController@getNotifications',
    'POST:api/business/notifications/mark-read/{id}' => 'APIController@markNotificationRead',
    'POST:api/business/errors/report' => 'APIController@reportError',
    'POST:api/business/errors/resolve' => 'APIController@resolveErrors',
    'POST:api/business/errors/delete-resolved' => 'APIController@deleteResolvedErrors',
    'POST:api/business/errors/delete-all' => 'APIController@deleteAllErrors',
    // business/image-test route removed (view file does not exist)
    
    // === ADMIN ROUTES (Super Admin için - /qodmin/*) ===
    'qodmin/dashboard' => 'SuperAdmin/DashboardController@index',
    'qodmin/profile' => 'Admin/ProfileController@index',
    'POST:qodmin/profile/update' => 'Admin/ProfileController@update',
    
    // Admin paket yönetimi
    'qodmin/packages' => 'Admin/PackagesController@index',
    'qodmin/packages/create' => 'Admin/PackagesController@create',
    'POST:qodmin/packages' => 'Admin/PackagesController@store',
    'qodmin/packages/{id}/edit' => 'Admin/PackagesController@edit',
    'POST:qodmin/packages/{id}' => 'Admin/PackagesController@update',
    'POST:qodmin/packages/generate-description' => 'Admin/PackagesController@generateDescription',
    
    // Admin contact forms
    'qodmin/contact-forms' => 'Admin/ContactFormsController@index',
    'qodmin/contact-forms/view/{id}' => 'Admin/ContactFormsController@viewContact',
    'POST:qodmin/contact-forms/update-status' => 'Admin/ContactFormsController@updateStatus',
    // replaced => 'PrinterBridgeController@getPrinterRoles',
    'POST:qodmin/contact-forms/delete' => 'Admin/ContactFormsController@delete',
    'POST:qodmin/contact-forms/send-reply' => 'Admin/ContactFormsController@sendReply',
    'POST:qodmin/contact-forms/improve-text' => 'Admin/ContactFormsController@improveText',

        'POST:qodmin/packages/{id}/delete' => 'Admin/PackagesController@delete',
    'POST:qodmin/packages/{id}/toggle-active' => 'Admin/PackagesController@toggleActive',
    'POST:qodmin/packages/{id}/apply-discount' => 'Admin/PackagesController@applyDiscount',
    'GET:qodmin/packages/{id}/edit-data' => 'Admin/PackagesController@editData',
    'GET:api/qodmin/packages/{id}/permissions' => 'Admin/PackagesController@getPermissions',
    'POST:api/qodmin/packages/{id}/permissions' => 'Admin/PackagesController@assignPermission',
    'DELETE:api/qodmin/packages/{id}/permissions/{permissionId}' => 'Admin/PackagesController@removePermission',
    // Rol-bazlı paket yönetimi (yeni)
    'GET:api/qodmin/packages/{id}/roles' => 'Admin/PackagesController@getRoles',
    'POST:api/qodmin/packages/{id}/roles' => 'Admin/PackagesController@assignRoles',
    'PUT:api/qodmin/packages/{id}/roles' => 'Admin/PackagesController@assignRoles',
    
    // Bank transfer approvals (Super Admin)
    'qodmin/bank-transfers' => 'Admin/BankTransferController@pendingPayments',
    'POST:qodmin/bank-transfers/{id}/approve' => 'Admin/BankTransferController@approve',
    'POST:qodmin/bank-transfers/{id}/reject' => 'Admin/BankTransferController@reject',
    'POST:qodmin/bank-transfers/{id}/delete' => 'Admin/BankTransferController@deleteTransfer',
    'GET:qodmin/bank-transfers/{id}/receipt' => 'Admin/BankTransferController@viewReceipt',
    'GET:business/bank-transfers/{id}/receipt' => 'Admin/BankTransferController@viewReceipt',

    // Bank account management (Super Admin)
    // IMPORTANT: More specific routes ({id}) must come before generic routes for correct matching
    'qodmin/bank-accounts' => 'Admin/BankTransferController@bankAccounts',
    'POST:qodmin/bank-accounts/{id}/delete' => 'Admin/BankTransferController@deleteBankAccount',
    'POST:qodmin/bank-accounts/{id}' => 'Admin/BankTransferController@updateBankAccount',
    'POST:qodmin/bank-accounts' => 'Admin/BankTransferController@createBankAccount',

    // Admin abonelik yönetimi
    'qodmin/subscriptions' => 'Admin/SubscriptionsController@index',
    'qodmin/subscriptions/{id}' => 'Admin/SubscriptionsController@show',
    'POST:qodmin/subscriptions/{id}/activate' => 'Admin/SubscriptionsController@activate',
    'POST:qodmin/subscriptions/{id}/cancel' => 'Admin/SubscriptionsController@cancel',
    
    'qodmin/menu' => 'MenuController@index',
    'GET:qodmin/menu/add' => function() {
        header('Location: ' . BASE_URL . '/qodmin/menu', true, 302);
        exit;
    }, // 302 Redirect - add is handled via modal/POST
    'POST:qodmin/menu/add' => 'MenuController@add',
    'GET:qodmin/menu/edit/{id}' => function() {
        header('Location: ' . BASE_URL . '/qodmin/menu', true, 302);
        exit;
    }, // 302 Redirect - edit is handled via modal/POST
    'POST:qodmin/menu/edit/{id}' => 'MenuController@edit',
    'POST:qodmin/menu/delete/{id}' => 'MenuController@delete',
    'POST:qodmin/menu/translate' => 'MenuController@translate',
    'POST:qodmin/menu/translate-product-name' => 'MenuController@translateProductName',
    'POST:qodmin/menu/translate-category-name' => 'MenuController@translateCategoryName',
    'POST:qodmin/menu/bulk-update-prices' => 'MenuController@bulkUpdatePrices',
    'GET:qodmin/menu/fix-preparation-screens' => 'MenuController@fixPreparationScreens',
    'POST:qodmin/menu/fix-preparation-screens' => 'MenuController@fixPreparationScreens',
    'POST:api/qodmin/menu/extract-from-image' => 'MenuController@extractMenuFromImage',
    'POST:api/qodmin/menu/bulk-add-from-extraction' => 'MenuController@bulkAddFromExtraction',
    'qodmin/categories' => 'CategoryController@index',
    'POST:qodmin/categories/add' => 'CategoryController@add',
    'POST:qodmin/categories/edit/{id}' => 'CategoryController@edit',
    'POST:qodmin/categories/delete/{id}' => 'CategoryController@delete',
    // API routes for categories
    'GET:api/categories' => 'CategoryController@index',
    'POST:api/categories' => 'CategoryController@add',
    'PUT:api/categories/{id}' => 'CategoryController@edit',
    'DELETE:api/categories/{id}' => 'CategoryController@destroy',
    'qodmin/orders' => 'OrderController@index',
    'qodmin/orders/{id}' => 'OrderController@detail',
    'qodmin/customers' => function() {
        header('Location: ' . BASE_URL . '/qodmin/businesses', true, 302);
        exit;
    }, // Redirect to businesses (customers management integrated there)
    'qodmin/tables' => 'Admin/TablesController@tables',
    'qodmin/zones' => 'Admin/TablesController@zones',
    'qodmin/table-history/{id}' => 'Admin/TablesController@tableHistory',
    'api/qodmin/zones' => 'ZoneController@getZones',
    'POST:api/qodmin/zones' => 'ZoneController@createZone',
    'GET:api/qodmin/zones/{id}' => 'ZoneController@getZone',
    'PUT:api/qodmin/zones/{id}' => 'ZoneController@updateZone',
    'DELETE:api/qodmin/zones/{id}' => 'ZoneController@deleteZone',
    'GET:api/qodmin/zone/printers' => 'ZoneController@getPrinters',
    'api/qodmin/tables/generate-qr-code' => 'TableController@generateQRCode',
    'qodmin/inventory' => 'StockController@index',
    'qodmin/stock' => 'StockController@index', // Alias for admin/inventory
    
    // Stock API routes
    'GET:api/stock/list' => 'StockController@getStockList',
    'GET:api/stock/summary' => 'StockController@getStockSummary',
    'GET:api/stock/movements' => 'StockController@movements',
    'POST:api/stock/add' => 'StockController@addStock',
    'POST:api/stock/remove' => 'StockController@removeStock',
    'POST:api/stock/transfer' => 'StockController@transferStock',
    'POST:api/stock/adjust' => 'StockController@adjustStock',

    // Stock API — super-admin aliases (same controller, honours ?business_id=).
    // The inventory UI picks `/api/qodmin/...` for super admins and `/api/business/...`
    // for business owners; both must resolve to the same backend.
    'GET:api/qodmin/stock/list'     => 'StockController@getStockList',
    'GET:api/qodmin/stock/summary'  => 'StockController@getStockSummary',
    'GET:api/qodmin/stock/movements'=> 'StockController@movements',
    'GET:api/qodmin/stock/low'      => 'StockController@getLowStock',
    'POST:api/qodmin/stock/add'     => 'StockController@addStock',
    'POST:api/qodmin/stock/remove'  => 'StockController@removeStock',
    'POST:api/qodmin/stock/transfer'=> 'StockController@transferStock',
    'POST:api/qodmin/stock/adjust'  => 'StockController@adjustStock',
    'POST:api/qodmin/stock/threshold'=> 'StockController@updateThreshold',
    'GET:api/business/stock/low'    => 'StockController@getLowStock',
    'POST:api/business/stock/threshold' => 'StockController@updateThreshold',

    // Legacy / defensive alias: earlier seeds pointed the Product Sales nav item
    // at /qodmin/analytics/product-sales; the canonical route is /qodmin/product-sales.
    // Keep the legacy URL working so old bookmarks/nav caches don't 404.
    'qodmin/analytics/product-sales' => 'Admin/ProductSalesController@index',
    
    // Receipt routes
    'qodmin/receipts' => 'ReceiptController@history',
    'qodmin/receipt-templates' => 'ReceiptController@templates',
    'POST:api/qodmin/receipt/generate' => 'ReceiptController@generate',
    'POST:api/qodmin/receipt/print' => 'ReceiptController@print',
    'GET:api/qodmin/receipt/{id}' => 'ReceiptController@getReceipt',
    'POST:api/qodmin/receipt-template/save' => 'ReceiptController@saveTemplate',
    
    // Printer routes
    'qodmin/printers' => 'PrinterController@index',
    'qodmin/printers/bridge-setup' => 'PrinterController@bridgeSetup',
    // CRITICAL: Specific routes MUST come before wildcard routes
    'GET:api/qodmin/printer/bridges' => 'PrinterController@getBridges',
    'GET:api/qodmin/printer/all' => 'PrinterController@getAll',
    'POST:api/qodmin/printer/register' => 'PrinterController@register',
    'POST:api/qodmin/printer/test' => 'PrinterController@testConnection',
    'POST:api/qodmin/printer/update' => 'PrinterController@update',
    'POST:api/qodmin/printer/delete' => 'PrinterController@delete',
    'GET:api/qodmin/printer/{id}' => 'PrinterController@getPrinter',
    
    // Printer Bridge Management routes
    'POST:api/qodmin/printer/bridge/create' => 'PrinterController@createBridge',
    'POST:api/qodmin/printer/bridge/update' => 'PrinterController@updateBridge',
    'PUT:api/qodmin/printer/bridge/update' => 'PrinterController@updateBridge',
    'POST:api/qodmin/printer/bridge/delete' => 'PrinterController@deleteBridge',
    'DELETE:api/qodmin/printer/bridge/delete' => 'PrinterController@deleteBridge',
    
    // User management routes
    'qodmin/users' => 'Admin/UsersController@users',
    'qodmin/users/{id}' => 'Admin/UsersController@staffDetail',
    'POST:qodmin/users/add' => 'Admin/UsersController@addStaff',
    'POST:api/qodmin/users/{id}/update' => 'Admin/UsersController@updateStaff',
    'POST:api/qodmin/users/{id}/update-pin' => 'Admin/UsersController@updateStaffPin',
    'api/qodmin/users/{id}/pin' => 'Admin/UsersController@getStaffPin',
    'qodmin/users/delete/{id}' => 'Admin/UsersController@deleteStaff',
    'POST:api/qodmin/leaves/add' => 'Admin/LeaveController@addLeave',
    'GET:api/qodmin/leave-types'         => 'Admin/LeaveController@listLeaveTypes',
    'GET:api/qodmin/leaves/report'      => 'Admin/LeaveController@report',
    'POST:api/qodmin/leaves/managed'    => 'Admin/LeaveController@createManaged',
    'GET:api/qodmin/leaves'              => 'Admin/LeaveController@listLeaves',
    'GET:api/qodmin/leaves/my'           => 'Admin/LeaveController@myLeaves',
    'POST:api/qodmin/leaves/{id}/approve'=> 'Admin/LeaveController@approveLeave',
    'POST:api/qodmin/leaves/{id}/reject' => 'Admin/LeaveController@rejectLeave',
    'qodmin/leaves'                      => 'Admin/LeaveController@dashboard',
    'GET:api/qodmin/leaves/{id}' => 'Admin/LeaveController@getLeave',
    'POST:api/qodmin/leaves/{id}/update' => 'Admin/LeaveController@updateLeave',
    'DELETE:api/qodmin/leaves/{id}' => 'Admin/LeaveController@deleteLeave',
    'POST:api/qodmin/medical-reports/add' => 'Admin/MedicalReportController@addMedicalReport',
    'GET:api/qodmin/medical-reports/{id}' => 'Admin/MedicalReportController@getMedicalReport',
    'POST:api/qodmin/medical-reports/{id}/update' => 'Admin/MedicalReportController@updateMedicalReport',
    'DELETE:api/qodmin/medical-reports/{id}' => 'Admin/MedicalReportController@deleteMedicalReport',
    'qodmin/medical-reports/{id}/download' => 'Admin/MedicalReportController@downloadMedicalReport',
    // Roles & Permissions route
    'qodmin/roles-permissions' => 'Admin/RolesPermissionsController@rolesPermissions',
    'POST:api/qodmin/create-role' => 'Admin/RolesPermissionsController@createRole',
    'POST:api/qodmin/update-role' => 'Admin/RolesPermissionsController@updateRole',
    'api/qodmin/delete-role' => 'Admin/RolesPermissionsController@deleteRole',
    'POST:api/qodmin/assign-permissions' => 'Admin/RolesPermissionsController@assignPermissions',
    'api/qodmin/role-permissions' => 'Admin/RolesPermissionsController@getRolePermissions',
    'POST:api/qodmin/add-printer-permissions' => 'Admin/POSDeviceController@addPrinterPermissions',
    'qodmin/analytics' => 'Admin/AnalyticsController@analytics',
    'api/qodmin/analytics-data' => 'Admin/AnalyticsController@getAnalyticsData',
    'api/qodmin/end-of-day' => 'Admin/AnalyticsController@endOfDay',
    'api/qodmin/z-report-pdf' => 'Admin/AnalyticsController@zReportPdf',
    'POST:api/qodmin/z-report-print' => 'Admin/AnalyticsController@zReportPrint',
    'api/qodmin/ai-insights' => 'Admin/AIController@getAIInsights',
    'api/qodmin/ai-insights/saved' => 'Admin/AIController@listSavedInsights',
    'POST:api/qodmin/ai-insights/save' => 'Admin/AIController@saveInsight',
    'POST:api/qodmin/ai-insights/unsave' => 'Admin/AIController@unsaveInsight',
    'qodmin/ai-onerileri' => 'Admin/AIController@savedInsightsPage',
    'api/qodmin/dashboard-data' => 'Admin/DashboardController@getDashboardData',
    // Order approvals removed
    'qodmin/order-approvals' => 'Admin/OrderApprovalController@index',
    'qodmin/order-approval-history' => 'Admin/OrderApprovalController@history',
    'api/qodmin/order-approvals/pending' => 'Admin/OrderApprovalController@getPendingApprovals',
    'api/qodmin/order-approvals/pending-count' => 'Admin/OrderApprovalController@getPendingCount',
    'api/qodmin/order-approvals/history' => 'Admin/OrderApprovalController@getApprovalHistory',
    'GET:api/qodmin/order-approvals/detail' => 'Admin/OrderApprovalController@getApprovalDetail',
    'api/qodmin/order-approvals/payments' => 'Admin/OrderApprovalController@getPaymentsForHistory',
    'api/qodmin/order-approvals/other-transactions' => 'Admin/OrderApprovalController@getOtherTransactionsForHistory',
    'GET:api/qodmin/order-approvals/receipt-detail' => 'ReceiptController@getReceiptForOrderApprovalHistory',
    'POST:api/qodmin/order-approvals/approve' => 'Admin/OrderApprovalController@approveRequest',
    'POST:api/qodmin/order-approvals/reject' => 'Admin/OrderApprovalController@rejectRequest',
    'GET:api/qodmin/approval-feedback' => 'Admin/OrderApprovalController@getApprovalFeedback',
    'qodmin/settings' => 'Admin/SettingsController@settings',
    'POST:qodmin/settings' => 'Admin/SettingsController@settings',
    
    // === PERFORMANCE MONITORING ROUTES (Admin only) ===
    'qodmin/performance' => 'PerformanceController@dashboard',
    'GET:api/performance/cache-stats' => 'PerformanceController@apiCacheStats',
    'GET:api/performance/query-stats' => 'PerformanceController@apiQueryStats',
    'GET:api/performance/slow-queries' => 'PerformanceController@slowQueries',
    'POST:api/performance/clear-cache' => 'PerformanceController@clearCache',
    'POST:api/performance/clear-active-sessions' => 'PerformanceController@clearActiveSessions',
    'GET:api/performance/test-cache' => 'PerformanceController@testCachePerformance',
    'qodmin/features' => function() {
        header('Location: ' . BASE_URL . '/qodmin/dashboard', true, 302);
        exit;
    }, // Redirect to dashboard (feature flags controller not implemented yet)
    'qodmin/error-logs' => 'SuperAdmin/ErrorLogController@errorLogs',
    'GET:qodmin/error-logs/export-all' => 'SuperAdmin/ErrorLogController@exportAllLogs',
    'qodmin/error-analytics' => function() {
        header('Location: ' . BASE_URL . '/qodmin/error-logs', true, 301);
        exit;
    }, // Redirect to error-logs (same content)
    'qodmin/system-logs' => 'SuperAdmin/SystemLogsController@index', // Redirect to superadmin system logs
    'qodmin/payment-gateways' => 'Admin/PaymentGatewayController@paymentGateways',
    'POST:api/qodmin/payment-gateway/update' => 'Admin/PaymentGatewayController@updatePaymentGateway',
    'POST:api/qodmin/payment-gateways/update' => 'Admin/PaymentGatewayController@updatePaymentGateway',
    'POST:api/qodmin/payment-gateway/seed' => 'Admin/PaymentGatewayController@seedGateways',
    'POST:api/qodmin/payment-gateways/seed' => 'Admin/PaymentGatewayController@seedGateways',
    // DEPRECATED: Havale/EFT aç-kapa artık Settings modülünden yönetiliyor.
    // Route'lar 410 Gone yanıtı vermek için handler'a yönlendirilmiş halde
    // bırakıldı (eski istemciler için). Yeni istemci /qodmin/settings formu
    // ile POST ediyor.
    'POST:api/qodmin/payment-settings/bank-transfer' => 'Admin/PaymentGatewayController@updateBankTransferEnabled',
    'POST:api/business/payment-settings/bank-transfer' => 'Admin/PaymentGatewayController@updateBankTransferEnabled',
    
    // Payment routes (iyzico only)
    'POST:api/payment/initiate' => 'PaymentController@initiatePayment',
    'POST:api/payment/iyzico/initiate' => 'PaymentController@initiatePayment',
    'POST:payment/iyzico/callback' => 'PaymentController@callback',
    'GET:payment/iyzico/callback' => 'PaymentController@callback',
    'GET:api/payment/status/{transactionId}' => 'PaymentController@getPaymentStatus',
    'POST:api/payment/status' => 'PaymentController@getPaymentStatus',
    'qodmin/pos-devices' => 'Admin/POSDeviceController@posDevices',
    'POST:api/qodmin/pos-device/test' => 'Admin/POSDeviceController@testPOSDevice',
    'POST:api/qodmin/pos-device/update' => 'Admin/POSDeviceController@updatePOSDevice',
    'POST:api/qodmin/pos-device/add' => 'Admin/POSDeviceController@addPOSDevice',
    'POST:qodmin/settings/reset' => 'Admin/SystemController@resetSystem',
    'POST:api/qodmin/settings/upload-logo' => 'Admin/LogoController@uploadLogo',
    'POST:api/qodmin/settings/upload-favicon' => 'Admin/LogoController@uploadFavicon',
    'POST:api/qodmin/email/test' => 'Admin/EmailController@testEmail',
    'api/qodmin/email/status' => 'Admin/EmailController@getEmailStatus',
    'api/qodmin/meta/debug-token' => 'Admin/SettingsController@debugMetaToken',
    'api/qodmin/meta/business-info' => 'Admin/SettingsController@getMetaBusinessInfo',
    'POST:api/qodmin/meta/test-whatsapp' => 'Admin/SettingsController@testWhatsApp',
    'api/qodmin/meta/dashboard-stats' => 'Admin/SettingsController@getMetaDashboardStats',
    'api/qodmin/meta/message-history' => 'Admin/SettingsController@getMetaMessageHistory',
    'api/qodmin/meta/top-recipients' => 'Admin/SettingsController@getMetaTopRecipients',
    // /api/business/meta/* — aynı endpoint'ler, super admin'in "business view"
    // kipinde settings.php içindeki $apiPrefix = /api/business olduğunda da
    // çağrılabilmesi için. Controller tarafında yetki kontrolü (isSuperAdmin)
    // zaten yapılıyor.
    'api/business/meta/debug-token' => 'Admin/SettingsController@debugMetaToken',
    'api/business/meta/business-info' => 'Admin/SettingsController@getMetaBusinessInfo',
    'POST:api/business/meta/test-whatsapp' => 'Admin/SettingsController@testWhatsApp',
    'api/business/meta/dashboard-stats' => 'Admin/SettingsController@getMetaDashboardStats',
    'api/business/meta/message-history' => 'Admin/SettingsController@getMetaMessageHistory',
    'api/business/meta/top-recipients' => 'Admin/SettingsController@getMetaTopRecipients',
    'POST:api/qodmin/2fa/enable' => 'Admin/TwoFactorController@enable2FA',
    'POST:api/qodmin/2fa/disable' => 'Admin/TwoFactorController@disable2FA',
    'POST:api/qodmin/2fa/send-code' => 'Admin/TwoFactorController@send2FACode',
    'POST:api/qodmin/2fa/verify' => 'Admin/TwoFactorController@verify2FA',
    'qodmin/reservations' => 'ReservationController@index',
    'qodmin/reservations/add' => 'ReservationController@showAddForm',
    'POST:qodmin/reservations/add' => 'ReservationController@add',
    'qodmin/reservations/edit/{id}' => 'ReservationController@edit',
    'POST:qodmin/reservations/update/{id}' => 'ReservationController@update',
    'POST:qodmin/reservations/update-status/{id}' => 'ReservationController@updateStatus',
    // replaced => 'PrinterBridgeController@getPrinterRoles',
    'POST:qodmin/reservations/delete/{id}' => 'ReservationController@delete',
    'POST:api/reservations/send-reminder/{id}' => 'APIController@sendReservationReminder',
    'qodmin/finance' => 'FinanceController@index',
    'qodmin/finance/expenses' => 'FinanceController@expenses',
    'qodmin/finance/invoices' => 'FinanceController@invoices',
    'qodmin/finance/suppliers' => 'FinanceController@suppliers',
    'qodmin/finance/waste' => 'FinanceController@waste',
    // Shift qodmin routes removed
    'api/qodmin/finance/data' => 'FinanceController@getFinancialData',

    // Waiter routes (redirect to business prefix)
    // CRITICAL: Clean query string to prevent infinite redirect loops
    // Serve waiter directly under clean non-business URLs
    'waiter' => function() {
        // Remove 'url' parameter from query string to prevent redirect loops
        $params = $_GET;
        unset($params['url']); // Remove rewrite parameter
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        $redirectUrl = BASE_URL . '/waiter/dashboard' . $queryString;
        header('Location: ' . $redirectUrl, true, 301);
        exit;
    },
    'waiter/dashboard' => 'WaiterController@dashboard',
    'waiter/pos' => 'WaiterController@pos',
    'GET:api/waiter/tables' => 'WaiterController@getTables',
    'GET:api/waiter/table-notifications' => 'WaiterController@getTableNotifications',
    'POST:api/waiter/notifications/mark-read' => 'WaiterController@markNotificationRead',
    'POST:api/waiter/print-table-receipt' => 'WaiterController@printTableReceipt',
    'GET:api/waiter/table-activity-logs' => 'WaiterController@getTableActivityLogs',
    'GET:api/waiter/table-details/{id}' => 'WaiterController@getTableDetails',
    'GET:api/waiter/ready-orders' => 'WaiterController@getReadyOrders',
    'POST:api/waiter/accept-order' => 'WaiterController@acceptOrder',
    'POST:api/waiter/deliver-order' => 'WaiterController@deliverOrder',
    'POST:api/waiter/move-table' => 'WaiterController@moveTable',
    'POST:api/waiter/delete-order-item' => 'WaiterController@deleteOrderItem',
    'POST:api/waiter/reduce-order-item-quantity' => 'WaiterController@reduceOrderItemQuantity',
    'POST:api/waiter/delete-all-table-orders' => 'WaiterController@deleteAllTableOrders',
    'POST:api/waiter/transfer-to-cashier' => 'WaiterController@transferToCashier',
    'POST:api/waiter/update-order-status' => 'WaiterController@updateOrderStatusWithNotification',
    'POST:api/waiter/notify-customer-waiter-coming' => 'WaiterController@notifyCustomerWaiterComing',

    // Kitchen routes (Serve directly under clean non-business URLs)
    'kitchen' => function() {
        header('Location: ' . BASE_URL . '/kitchen/dashboard', true, 301);
        exit;
    },
    'kitchen/dashboard' => 'KitchenController@dashboard',
    'kitchen/orders' => 'KitchenController@orders',
    'kitchen/getOrders' => 'KitchenController@getOrders', // API route, keep as is
    'POST:kitchen/update-status' => 'KitchenController@updateOrderStatus', // API route, keep as is
    // replaced => 'PrinterBridgeController@getPrinterRoles',

    // Preparation Screens routes (Admin)
    'qodmin/preparation-screens' => 'Admin/PreparationScreenController@index',
    'qodmin/preparation-screens/create' => 'Admin/PreparationScreenController@create',
    'POST:qodmin/preparation-screens' => 'Admin/PreparationScreenController@store',
    'qodmin/preparation-screens/edit/{id}' => 'Admin/PreparationScreenController@edit',
    'POST:qodmin/preparation-screens/update/{id}' => 'Admin/PreparationScreenController@update',
    'POST:qodmin/preparation-screens/toggle-active/{id}' => 'Admin/PreparationScreenController@toggleActive',
    'GET:qodmin/preparation-screens/delete/{id}' => 'Admin/PreparationScreenController@delete',
    'POST:qodmin/preparation-screens/delete/{id}' => 'Admin/PreparationScreenController@delete',
    'api/qodmin/preparation-screens/categories' => 'Admin/PreparationScreenController@getCategories',
    
    // Preparation Screen Printer routes
    'GET:api/qodmin/preparation-screens/{id}/printers' => 'Admin/PreparationScreenPrinterController@index',
    'POST:api/qodmin/preparation-screens/{id}/assign-printer' => 'Admin/PreparationScreenPrinterController@assign',
    'POST:api/qodmin/preparation-screens/{id}/remove-printer' => 'Admin/PreparationScreenPrinterController@remove',
    'POST:api/qodmin/preparation-screens/{id}/update-priority' => 'Admin/PreparationScreenPrinterController@updatePriority',
    'qodmin/preparation-screens/{slug}' => 'PreparationScreenController@dashboard',
    'qodmin/preparation-screens/{slug}/orders' => 'PreparationScreenController@getOrders',
    'POST:qodmin/preparation-screens/{slug}/update-status' => 'PreparationScreenController@updateOrderStatus',
    // replaced => 'PrinterBridgeController@getPrinterRoles',

    // Preparation Screens routes (Dynamic)
    'preparation-screen/{slug}' => 'PreparationScreenController@dashboard',
    'preparation-screen/{slug}/orders' => 'PreparationScreenController@getOrders',
    'POST:preparation-screen/{slug}/update-status' => 'PreparationScreenController@updateOrderStatus',
    // replaced => 'PrinterBridgeController@getPrinterRoles',

    // POS routes (Serve directly under clean non-business URLs)
    'pos' => 'PosController@dashboard',
    'pos/dashboard' => 'PosController@dashboard',
    'cashier' => function() {
        header('Location: ' . BASE_URL . '/pos', true, 301);
        exit;
    },
    'business/pos' => function() {
        header('Location: ' . BASE_URL . '/pos', true, 301);
        exit;
    },
    'business/pos/dashboard' => function() {
        header('Location: ' . BASE_URL . '/pos', true, 301);
        exit;
    },
    
    // POS routes (Super Admin)
    'qodmin/pos' => 'PosController@dashboard',
    'qodmin/pos/dashboard' => 'PosController@dashboard',
    
    // Waiter routes (Redirect business prefix to clean URLs)
    'business/waiter' => function() {
        $params = $_GET;
        unset($params['url']);
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . '/waiter/dashboard' . $queryString, true, 301);
        exit;
    },
    'business/waiter/dashboard' => function() {
        $params = $_GET;
        unset($params['url']);
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . '/waiter/dashboard' . $queryString, true, 301);
        exit;
    },
    'business/waiter/pos' => function() {
        $params = $_GET;
        unset($params['url']);
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . '/waiter/pos' . $queryString, true, 301);
        exit;
    },
    
    // Waiter routes (Super Admin)
    'qodmin/waiter' => 'WaiterController@dashboard',
    'qodmin/waiter/dashboard' => 'WaiterController@dashboard',
    
    // Kitchen routes (Redirect business prefix to clean URLs)
    'business/kitchen' => function() {
        header('Location: ' . BASE_URL . '/kitchen/dashboard', true, 301);
        exit;
    },
    'business/kitchen/dashboard' => function() {
        header('Location: ' . BASE_URL . '/kitchen/dashboard', true, 301);
        exit;
    },
    'business/kitchen/orders' => function() {
        header('Location: ' . BASE_URL . '/kitchen/orders', true, 301);
        exit;
    },
    
    // Kitchen routes (Super Admin)
    'qodmin/kitchen' => 'KitchenController@dashboard',
    'qodmin/kitchen/dashboard' => 'KitchenController@dashboard',
    
    // Preparation screen routes (Business)
    // Note: business/preparation-screens route is already defined at line 239 (Admin/PreparationScreenController@index)
    // Note: business/preparation-screen (singular) is for dynamic screen dashboards via preparation-screen/{slug} route
    
    // POS routes - serve pos/orders directly
    'pos/orders' => 'PosController@orders',
    'POST:pos/process-payment' => 'PosController@processPayment', // API route, keep as is
    'POST:pos/print-adisyon' => 'PosController@printOrderAdisyon', // API route, keep as is
    'POST:pos/request-payment-prep-cancel' => 'PosController@requestPaymentPrepCancel', // Request prep cancel
    'POST:business/pos/print-adisyon' => 'PosController@printOrderAdisyon', // POS sayfasından adisyon yazdır (business/pos ile aynı base)
    'POST:business/pos/request-payment-prep-cancel' => 'PosController@requestPaymentPrepCancel', // Mutfak/hazırlık iptali için yönetici onayı iste
    'POST:pos/create-order' => 'PosController@createOrder', // API route, keep as is
    'POST:pos/add-item' => 'PosController@addItemToOrder', // API route, keep as is
    'POST:pos/remove-item' => 'PosController@removeItemFromOrder', // API route, keep as is
    'POST:pos/remove-item-from-order' => 'PosController@removeItemFromOrder', // JS alias
    'POST:pos/update-quantity' => 'PosController@updateOrderItemQuantity', // API route, keep as is
    'POST:pos/update-order-item-quantity' => 'PosController@updateOrderItemQuantity', // JS alias
    'POST:pos/delete-all-table-orders' => 'PosController@deleteAllTableOrders', // Delete all orders on table
    'POST:api/pos/delete-all-table-orders' => 'PosController@deleteAllTableOrders', // API alias
    
    // Business POS routes
    'business/pos/orders' => function() {
        header('Location: ' . BASE_URL . '/pos/orders', true, 301);
        exit;
    },
    'api/pos/active-orders' => 'PosController@getActiveOrders',
    'api/pos/table-orders' => 'PosController@getTableOrders',
    'api/pos/table-history/{id}' => 'PosController@getTableHistory',
    'api/pos/tables-grouped' => 'PosController@getTablesGrouped',

    // Customer routes (short URLs)
    'menu' => 'CustomerController@menu',
    'cart' => 'CustomerController@cart',
    't' => 'CustomerController@tableMenu', // Table selection page (no tableId)
    't/{tableId}' => 'CustomerController@tableMenu', // Old format for backward compatibility
    'table/{tableId}' => 'CustomerController@tableMenu', // Alternative for backward compatibility
    'masa/{zoneSlug}/{tableSlug}' => 'CustomerController@tableMenuBySlug', // SEO-friendly URL

    // Language-based menu routes
    'tr/menu/{slug}' => 'CustomerController@menuBySlug',
    'en/menu/{slug}' => 'CustomerController@menuBySlug',
    'menu/{slug}' => 'CustomerController@menuBySlug',

    // API routes
    'POST:api/cart/sync' => 'CustomerController@syncCart',
    'api/menu' => 'APIController@getMenu',
    'api/menu/item' => 'MenuController@getMenuItem',
    'GET:api/menu/item/{id}' => 'MenuController@getMenuItem',
    'GET:api/customer/menu' => 'CustomerController@getMenu',
    'POST:api/customer/change-language' => 'CustomerController@changeLanguage',
    'GET:api/menu/item/{id}/translations' => 'MenuController@getMenuItemTranslations',
    'api/menu/category' => 'APIController@getCategory',
    'api/orders' => 'APIController@getOrders',
    'api/orders/table-sessions' => 'APIController@getTableOrderSessions',
    'api/get-notifications' => 'APIController@getNotifications',
    'api/tables' => 'APIController@getTables',
    'POST:api/place-order' => 'APIController@placeOrder',
    'POST:api/session/update-location' => 'APIController@updateSessionLocation',
    'GET:api/session/check' => 'APIController@checkSession',
    'POST:api/session/continue' => 'APIController@continueSession',
    'api/update-order-status' => 'APIController@updateOrderStatus',
    'POST:api/update-order-status' => 'APIController@updateOrderStatus',
    // Shift API start/end removed
    'POST:api/expense/add' => 'APIController@addExpense',
    'POST:api/expense/delete' => 'APIController@deleteExpense',
    'POST:api/waste/add' => 'APIController@addWaste',
    'POST:api/waste/delete' => 'APIController@deleteWaste',
    'api/table/transfer' => 'APIController@transferTable',
    'POST:api/call-waiter' => 'APIController@callWaiter',
    'POST:api/request-bill' => 'APIController@requestBill',
    'POST:api/cancel-order-request' => 'APIController@cancelOrderRequest',
    'POST:api/update-table-status' => 'APIController@updateTableStatus',
    'GET:api/translate' => 'APIController@translate',
    'api/qodmin/getUser' => 'Admin/UsersController@getUser',
    'api/qodmin/getTable' => 'TableController@getTable',
    'api/qodmin/getOrder' => 'OrderController@getOrder',
    'POST:api/qodmin/order/{id}/print' => 'OrderController@printOrder',
    'GET:api/qodmin/order/{id}/pdf' => 'OrderController@downloadOrderPDF',
    'POST:api/qodmin/order/{id}/send-email' => 'OrderController@sendOrderPDFEmail',
    'POST:api/qodmin/update-order-status' => 'APIController@updateOrderStatus',
    'GET:api/qodmin/export-orders' => 'Admin/ReportsController@exportOrders',
    'api/qodmin/getReservation' => 'ReservationController@getReservation',
    'POST:api/qodmin/add-table' => 'APIController@addTable',
    'PUT:api/qodmin/update-table' => 'APIController@updateTable',
    'POST:api/qodmin/update-table' => 'APIController@updateTable',
    'DELETE:api/qodmin/delete-table' => 'APIController@deleteTable',
    'api/qodmin/download-qr' => 'APIController@downloadQRCode',
    'POST:api/reservation/add' => 'APIController@addReservation',
    'POST:api/reservation/delete' => 'APIController@deleteReservation',
    'POST:api/supplier/add' => 'APIController@addSupplier',
    'POST:api/supplier/update' => 'APIController@updateSupplier',
    'POST:api/supplier/delete' => 'APIController@deleteSupplier',
    'POST:api/invoice/add' => 'APIController@addInvoice',
    'POST:api/invoice/pay' => 'APIController@payInvoice',
    // Shift current API removed
    'POST:api/change-language' => 'APIController@changeLanguage',
    'api/notifications' => 'APIController@getNotifications',
    'api/get-notifications' => 'APIController@getNotifications',
    'POST:api/notifications/mark-read/{id}' => 'APIController@markNotificationRead',
    'POST:api/notifications/mark-read' => 'APIController@markNotificationRead',
    'POST:api/errors/report' => 'APIController@reportError',
    'POST:api/errors/resolve' => 'APIController@resolveErrors',
    'POST:api/errors/delete-resolved' => 'APIController@deleteResolvedErrors',
    'POST:api/errors/delete-all' => 'APIController@deleteAllErrors',
    'POST:api/errors/smart-cleanup' => 'APIController@smartCleanup',
    
    // Contact form routes
    'GET:api/contact/captcha' => 'APIController@generateContactCaptcha',
    'POST:api/contact/submit' => 'APIController@submitContactForm',

    // Meta (WhatsApp/Facebook) Webhook - no auth, called by Meta servers
    'GET:api/webhook/meta' => 'API/MetaWebhookController@handle',
    'POST:api/webhook/meta' => 'API/MetaWebhookController@handle',

    // qodmin/image-test route removed (view file does not exist)

    // Image Management API routes
    'POST:api/images/upload' => 'ImageController@upload',
    'PUT:api/images/{id}' => 'ImageController@update',
    'POST:api/images/{id}/update' => 'ImageController@update',
    'DELETE:api/images/{id}' => 'ImageController@delete',
    'api/images/entity/{type}/{id}' => 'ImageController@getByEntity',
    'api/images/entity/{type}/{id}/primary' => 'ImageController@getPrimaryImage',
    'POST:api/images/{id}/set-primary' => 'ImageController@setPrimary',
    'POST:api/images/sort-order' => 'ImageController@updateSortOrder',
    'DELETE:api/images/entity/{type}/{id}' => 'ImageController@deleteByEntity',
    'api/images/entity/{type}/{id}/responsive' => 'ImageController@getResponsiveUrls',
    'api/images/statistics' => 'ImageController@statistics',
    'POST:api/images/cleanup-orphaned' => 'ImageController@cleanupOrphaned',

    // === MOBILE APP API ROUTES ===
    'POST:api/mobile/validate-subdomain' => 'API/MobileAPIController@validateSubdomain',
    'POST:api/mobile/validate-business-number' => 'API/MobileAPIController@validateBusinessNumber',
    'POST:api/mobile/staff/login' => 'API/MobileAPIController@staffLogin',
    'POST:api/mobile/refresh-token' => 'API/MobileAPIController@refreshToken',
    'POST:api/mobile/manager/validate-email' => 'API/MobileAPIController@validateManagerEmail',
    'POST:api/mobile/manager/login' => 'API/MobileAPIController@managerLogin',
    'POST:api/mobile/verify-token' => 'API/MobileAPIController@verifyTokenEndpoint',
    'POST:api/mobile/logout' => 'API/MobileAPIController@logout',
    // E-posta ile tenant çözümleme + şifre sıfırlama (işletme uygulaması)
    'GET:api/mobile/auth/resolve-tenant' => 'API/MobileAPIController@resolveTenant',
    'POST:api/mobile/auth/resolve-tenant' => 'API/MobileAPIController@resolveTenant',
    'POST:api/mobile/auth/forgot-password' => 'API/MobileAPIController@forgotPassword',
    'POST:api/mobile/auth/reset-password' => 'API/MobileAPIController@resetPassword',

    // 2FA TOTP (Google Authenticator, Authy, Microsoft Authenticator…)
    'POST:api/mobile/auth/2fa/verify' => 'API/MobileAPIController@verify2FAChallenge',
    'POST:api/mobile/auth/2fa/send'   => 'API/MobileAPIController@send2FAChallengeCode',
    'GET:api/mobile/security/totp/status' => 'API/MobileAPIController@totpStatus',
    'POST:api/mobile/security/totp/setup' => 'API/MobileAPIController@totpSetup',
    'POST:api/mobile/security/totp/confirm' => 'API/MobileAPIController@totpConfirm',
    'POST:api/mobile/security/totp/disable' => 'API/MobileAPIController@totpDisable',
    // 2FA WhatsApp (Meta Cloud API) — mobile enrolment + status
    'GET:api/mobile/security/whatsapp/status'   => 'API/MobileAPIController@whatsappStatus',
    'POST:api/mobile/security/whatsapp/setup'   => 'API/MobileAPIController@whatsappSetup',
    'POST:api/mobile/security/whatsapp/confirm' => 'API/MobileAPIController@whatsappConfirm',
    'POST:api/mobile/security/whatsapp/disable' => 'API/MobileAPIController@whatsappDisable',
    'GET:api/mobile/security/auth-methods'      => 'API/MobileAPIController@authMethodsStatus',
    'GET:api/mobile/staff/dashboard' => 'API/MobileAPIController@staffDashboard',
    'GET:api/mobile/orders' => 'API/MobileAPIController@getOrders',
    'POST:api/mobile/orders/status' => 'API/MobileAPIController@updateOrderStatus',
    'GET:api/mobile/tables' => 'API/MobileAPIController@getTables',
    'GET:api/mobile/menu' => 'API/MobileAPIController@getMenu',
    'GET:api/mobile/menu/item-ingredients' => 'API/MobileAPIController@getMenuItemIngredients',
    'GET:api/mobile/notifications' => 'API/MobileAPIController@getNotifications',
    'POST:api/mobile/notifications/read' => 'API/MobileAPIController@markNotificationRead',
    'POST:api/mobile/notifications/read-all' => 'API/MobileAPIController@markAllNotificationsRead',
    'POST:api/mobile/notifications/register-token' => 'API/MobileAPIController@registerPushToken',
    
    // Kitchen endpoints
    'GET:api/mobile/kitchen/orders' => 'API/MobileAPIController@getKitchenOrders',
    'GET:api/mobile/orders/has-kitchen-items' => 'API/MobileAPIController@orderHasKitchenItems',
    'POST:api/mobile/kitchen/update-status' => 'API/MobileAPIController@updateKitchenStatus',
    
    // Preparation screen endpoints
    'GET:api/mobile/preparation/orders' => 'API/MobileAPIController@getPreparationOrders',
    'POST:api/mobile/preparation/update-status' => 'API/MobileAPIController@updatePreparationStatus',
    
    // Waiter endpoints
    'GET:api/mobile/waiter/table-details' => 'API/MobileAPIController@getTableDetails',
    'GET:api/mobile/waiter/ready-orders' => 'API/MobileAPIController@getReadyOrders',
    'POST:api/mobile/waiter/deliver-order' => 'API/MobileAPIController@deliverOrder',
    'POST:api/mobile/waiter/transfer-cashier' => 'API/MobileAPIController@transferToCashier',
    'POST:api/mobile/waiter/delete-order-item' => 'API/MobileAPIController@deleteOrderItem',
    
    // POS/Cashier endpoints
    'POST:api/mobile/pos/create-order' => 'API/MobileAPIController@createMobileOrder',
    'POST:api/mobile/pos/add-item' => 'API/MobileAPIController@addItemToOrder',
    'POST:api/mobile/pos/remove-item' => 'API/MobileAPIController@removeItemFromOrder',
    'POST:api/mobile/pos/update-quantity' => 'API/MobileAPIController@updateItemQuantity',
    'POST:api/mobile/pos/process-payment' => 'API/MobileAPIController@processPaymentMobile',
    'POST:api/mobile/pos/print-adisyon' => 'API/MobileAPIController@printAdisyonMobile',
    'GET:api/mobile/pos/active-orders' => 'API/MobileAPIController@getActiveOrdersMobile',
    'GET:api/mobile/pos/table-orders' => 'API/MobileAPIController@getTableOrdersMobile',
    
    // Waiter: accept-order and move-table
    'POST:api/mobile/waiter/accept-order' => 'API/MobileAPIController@acceptOrder',
    'POST:api/mobile/waiter/move-table' => 'API/MobileAPIController@moveTable',
    
    // Manager endpoints
    'GET:api/mobile/manager/analytics' => 'API/MobileAPIController@getManagerAnalytics',
    'GET:api/mobile/manager/staff' => 'API/MobileAPIController@getStaffList',
    'GET:api/mobile/manager/settings' => 'API/MobileAPIController@getBusinessSettings',
    'PUT:api/mobile/manager/settings' => 'API/MobileAPIController@updateBusinessSettings',
    'POST:api/mobile/manager/settings' => 'API/MobileAPIController@updateBusinessSettings',
    'GET:api/mobile/manager/categories' => 'API/MobileAPIController@getCategories',
    
    // Menu management endpoints
    'POST:api/mobile/manager/menu/availability' => 'API/MobileAPIController@updateMenuItemAvailability',
    'POST:api/mobile/manager/menu/add-item' => 'API/MobileAPIController@addMenuItem',
    'POST:api/mobile/manager/menu/update-item' => 'API/MobileAPIController@updateMenuItem',
    'POST:api/mobile/manager/menu/delete-item' => 'API/MobileAPIController@deleteMenuItem',
    
    // Staff CRUD
    'POST:api/mobile/manager/staff/create' => 'API/MobileAPIController@createStaff',
    'POST:api/mobile/manager/staff/update' => 'API/MobileAPIController@updateStaff',
    'POST:api/mobile/manager/staff/delete' => 'API/MobileAPIController@deleteStaffMobile',
    'GET:api/mobile/manager/roles' => 'API/MobileAPIController@getRoles',
    
    // Reservation CRUD
    'GET:api/mobile/manager/reservations' => 'API/MobileAPIController@getReservationsList',
    'POST:api/mobile/manager/reservations/create' => 'API/MobileAPIController@createReservation',
    'POST:api/mobile/manager/reservations/update' => 'API/MobileAPIController@updateReservationMobile',
    'POST:api/mobile/manager/reservations/delete' => 'API/MobileAPIController@deleteReservation',
    
    // Zone & Table CRUD
    'GET:api/mobile/manager/zones' => 'API/MobileAPIController@getZonesList',
    'POST:api/mobile/manager/zones/create' => 'API/MobileAPIController@createZone',
    'POST:api/mobile/manager/zones/update' => 'API/MobileAPIController@updateZone',
    'POST:api/mobile/manager/zones/delete' => 'API/MobileAPIController@deleteZone',
    'POST:api/mobile/manager/tables/create' => 'API/MobileAPIController@createTableMobile',
    'POST:api/mobile/manager/tables/update' => 'API/MobileAPIController@updateTableMobile',
    'POST:api/mobile/manager/tables/delete' => 'API/MobileAPIController@deleteTableMobile',
    'GET:api/mobile/manager/zones/{zoneId}/tables' => 'API/MobileAPIController@getZoneTables',
    
    // Category CRUD
    'POST:api/mobile/manager/categories/create' => 'API/MobileAPIController@createCategory',
    'POST:api/mobile/manager/categories/update' => 'API/MobileAPIController@updateCategory',
    'POST:api/mobile/manager/categories/delete' => 'API/MobileAPIController@deleteCategoryMobile',
    
    // Expense CRUD
    'GET:api/mobile/manager/expenses' => 'API/MobileAPIController@getExpenses',
    'POST:api/mobile/manager/expenses/create' => 'API/MobileAPIController@createExpense',
    'POST:api/mobile/manager/expenses/update' => 'API/MobileAPIController@updateExpense',
    'POST:api/mobile/manager/expenses/delete' => 'API/MobileAPIController@deleteExpense',
    
    // Clear table orders
    'POST:api/mobile/pos/clear-table' => 'API/MobileAPIController@clearTableOrders',
    
    // Registration
    'POST:api/mobile/register' => 'API/MobileAPIController@registerBusiness',
    'POST:api/mobile/register/send-email-code' => 'API/MobileAPIController@sendRegisterEmailCode',
    'POST:api/mobile/register/verify-email' => 'API/MobileAPIController@verifyRegisterEmail',
    'POST:api/mobile/register/send-phone-code' => 'API/MobileAPIController@sendRegisterPhoneCode',
    'POST:api/mobile/register/verify-phone' => 'API/MobileAPIController@verifyRegisterPhone',
    'GET:api/mobile/packages/list' => 'API/MobileAPIController@getPackagesList',
    'POST:api/mobile/packages/purchase' => 'API/MobileAPIController@purchasePackage',
    'POST:api/mobile/packages/upload-receipt' => 'API/MobileAPIController@uploadPaymentReceipt',
    'GET:api/mobile/packages/pending-payments' => 'API/MobileAPIController@getPendingPayments',
    'GET:api/mobile/subscription/status' => 'API/MobileAPIController@getSubscriptionStatus',
    'POST:api/mobile/payment/iyzico/initiate' => 'API/MobileAPIController@initiateIyzicoPayment',
    'GET:api/mobile/payment/iyzico/status' => 'API/MobileAPIController@iyzicoPaymentStatus',
    'GET:api/mobile/packages/assigned-offer' => 'API/MobileAPIController@getAssignedOffer',
    'GET:api/mobile/packages/custom-offers' => 'API/MobileAPIController@listCustomOffers',
    'POST:api/mobile/packages/custom-offers/{link_id}/dismiss' => 'API/MobileAPIController@dismissCustomOffer',
    'GET:api/mobile/subscription/history' => 'API/MobileAPIController@subscriptionHistory',
    
    // Analytics enhanced
    'GET:api/mobile/manager/analytics/categories' => 'API/MobileAPIController@getAnalyticsByCategory',
    'GET:api/mobile/manager/product-sales' => 'API/MobileAPIController@getProductSalesData',
    'GET:api/mobile/manager/z-report' => 'API/MobileAPIController@getZReport',
    'POST:api/mobile/manager/z-report-print' => 'API/MobileAPIController@printZReport',

    // Stock, Receipts, Order Approvals (Manager)
    'GET:api/mobile/manager/stock' => 'API/MobileAPIController@getStockList',
    'POST:api/mobile/manager/stock/add' => 'API/MobileAPIController@addStockMovement',
    'POST:api/mobile/manager/stock/remove' => 'API/MobileAPIController@removeStockMovement',
    'POST:api/mobile/manager/stock/adjust' => 'API/MobileAPIController@adjustStockMovement',
    'POST:api/mobile/manager/stock/delete' => 'API/MobileAPIController@deleteStockMovement',
    'GET:api/mobile/manager/receipts' => 'API/MobileAPIController@getReceiptsList',
    'GET:api/mobile/manager/order-approvals' => 'API/MobileAPIController@getOrderApprovalsPending',
    'POST:api/mobile/manager/order-approvals/approve' => 'API/MobileAPIController@approveOrderRequest',
    'POST:api/mobile/manager/order-approvals/reject' => 'API/MobileAPIController@rejectOrderRequest',

    // Printers + bridges
    'GET:api/mobile/printers/bridges' => 'API/MobileAPIController@getPrinterBridges',
    'POST:api/mobile/printers/bridges/reveal-key' => 'API/MobileAPIController@revealPrinterBridgeKey',
    'POST:api/mobile/printers/bridges/create' => 'API/MobileAPIController@createPrinterBridge',
    'POST:api/mobile/printers/bridges/update' => 'API/MobileAPIController@updatePrinterBridge',
    'POST:api/mobile/printers/bridges/delete' => 'API/MobileAPIController@deletePrinterBridge',
    'GET:api/mobile/printers/bridge-printers' => 'API/MobileAPIController@getPrintersForBridge',
    'POST:api/mobile/printers/update' => 'API/MobileAPIController@updatePrinterMobile',
    'POST:api/mobile/printers/delete' => 'API/MobileAPIController@deletePrinterMobile',
    'POST:api/mobile/printers/test' => 'API/MobileAPIController@testPrinterMobile',
    'GET:api/mobile/printers/prep-screens' => 'API/MobileAPIController@getPrepScreensForPrinterMobile',

    // Queue
    'GET:api/mobile/queue' => 'API/MobileAPIController@getQueueMobile',
    'GET:api/mobile/queue/settings' => 'API/MobileAPIController@getQueueSettingsMobile',
    'POST:api/mobile/queue/settings' => 'API/MobileAPIController@updateQueueSettingsMobile',
    'POST:api/mobile/queue/call-next' => 'API/MobileAPIController@callNextQueueTicketMobile',
    'POST:api/mobile/queue/update-status' => 'API/MobileAPIController@updateQueueTicketStatusMobile',

    // Receipt templates
    'GET:api/mobile/receipt-templates' => 'API/MobileAPIController@getReceiptTemplatesMobile',
    'POST:api/mobile/receipt-templates/create' => 'API/MobileAPIController@createReceiptTemplateMobile',
    'POST:api/mobile/receipt-templates/update' => 'API/MobileAPIController@updateReceiptTemplateMobile',
    'POST:api/mobile/receipt-templates/delete' => 'API/MobileAPIController@deleteReceiptTemplateMobile',

    // Roles & permissions
    'GET:api/mobile/roles-permissions' => 'API/MobileAPIController@getRolesPermissionsMobile',
    'POST:api/mobile/roles-permissions/update' => 'API/MobileAPIController@updateRolePermissionsMobile',

    // Order approval history
    'GET:api/mobile/order-approvals/history' => 'API/MobileAPIController@getOrderApprovalHistoryMobile',

    // Table history
    'GET:api/mobile/tables/history' => 'API/MobileAPIController@getTableHistoryMobile',

    // Finance — invoices / suppliers / waste
    'GET:api/mobile/finance/invoices' => 'API/MobileAPIController@getInvoicesMobile',
    'POST:api/mobile/finance/invoices/create' => 'API/MobileAPIController@createInvoiceMobile',
    'POST:api/mobile/finance/invoices/delete' => 'API/MobileAPIController@deleteInvoiceMobile',
    'GET:api/mobile/finance/suppliers' => 'API/MobileAPIController@getSuppliersMobile',
    'POST:api/mobile/finance/suppliers/create' => 'API/MobileAPIController@createSupplierMobile',
    'POST:api/mobile/finance/suppliers/update' => 'API/MobileAPIController@updateSupplierMobile',
    'POST:api/mobile/finance/suppliers/delete' => 'API/MobileAPIController@deleteSupplierMobile',
    'GET:api/mobile/finance/waste' => 'API/MobileAPIController@getWasteMobile',
    'POST:api/mobile/finance/waste/create' => 'API/MobileAPIController@createWasteMobile',
    'POST:api/mobile/finance/waste/delete' => 'API/MobileAPIController@deleteWasteMobile',

    // System config mirrors
    'GET:api/mobile/payment-gateways' => 'API/MobileAPIController@getPaymentGatewaysMobile',
    'POST:api/mobile/payment-gateways/toggle' => 'API/MobileAPIController@togglePaymentGatewayMobile',
    'GET:api/mobile/pos-devices' => 'API/MobileAPIController@getPosDevicesMobile',
    'POST:api/mobile/pos-devices/delete' => 'API/MobileAPIController@deletePosDeviceMobile',
    'GET:api/mobile/features' => 'API/MobileAPIController@getFeatureFlagsMobile',
    'POST:api/mobile/features/toggle' => 'API/MobileAPIController@toggleFeatureFlagMobile',
    'GET:api/mobile/error-logs' => 'API/MobileAPIController@getErrorLogsMobile',
    'GET:api/mobile/reports' => 'API/MobileAPIController@getReportsMobile',

    // Printer Bridge API routes (No Auth Required)
    'GET:pb/debug' => 'PrinterBridgeController@debug',
    'POST:pb/register' => 'PrinterBridgeController@register',
    'POST:pb/heartbeat' => 'PrinterBridgeController@heartbeat',
    'POST:pb/sync-printers' => 'PrinterBridgeController@syncPrinters',
    'POST:pb/queue' => 'PrinterBridgeController@getQueue',
    'POST:pb/update-status' => 'PrinterBridgeController@updateStatus',
    'POST:pb/printer-roles' => 'PrinterBridgeController@getPrinterRoles',
    'GET:pb/screens' => 'PrinterBridgeController@getScreens',
    'POST:pb/assign-printer' => 'PrinterBridgeController@assignPrinter',
    'GET:pb/printers' => 'PrinterBridgeController@getPrinters',
    'GET:pb/detected-printers' => 'PrinterBridgeController@getDetectedPrinters',

    // Product Sales Analytics (Super Admin)
    'qodmin/product-sales' => 'Admin/ProductSalesController@index',
    'GET:api/qodmin/product-sales/data' => 'Admin/ProductSalesController@getData',
    'GET:api/qodmin/product-sales/receipt' => 'Admin/ProductSalesController@receipt',
    'POST:api/qodmin/product-sales/print' => 'Admin/ProductSalesController@printReceipt',
    
    // Reports routes
    'qodmin/reports' => 'Admin/ReportsController@reports',
    'qodmin/export-report' => 'Admin/ReportsController@exportReport',
    'GET:api/qodmin/export-orders' => 'Admin/ReportsController@exportOrders',
    'api/qodmin/reports-data' => 'Admin/ReportsController@getReportsData',
    // Shift qodmin routes removed
    'POST:api/qodmin/permissions/sync' => 'Admin/SystemController@syncDynamicPermissions',

    // Receipt routes
    'receipt/{id}' => 'ReceiptController@viewReceipt',
    'receipt/{id}/print' => 'ReceiptController@print',
    'receipt/{id}/pdf' => 'ReceiptController@pdf',
    'POST:api/receipt/generate' => 'ReceiptController@generate',
    'POST:receipt/{id}/reprint' => 'ReceiptController@reprint',
    
    // Receipt Template Design routes
    'GET:api/receipt-template/design/{business_id}' => 'Admin/ReceiptTemplateDesignController@getLayout',
    'GET:api/receipt-template/design' => 'Admin/ReceiptTemplateDesignController@getLayout',
    'POST:api/receipt-template/design/save' => 'Admin/ReceiptTemplateDesignController@saveLayout',
    'GET:api/receipt-template/design/preview/{layout_id}' => 'Admin/ReceiptTemplateDesignController@preview',
    'GET:api/receipt-template/design/list' => 'Admin/ReceiptTemplateDesignController@listLayouts',
    'DELETE:api/receipt-template/design/{layout_id}' => 'Admin/ReceiptTemplateDesignController@deleteLayout',
    'POST:api/receipt-template/design/{layout_id}/delete' => 'Admin/ReceiptTemplateDesignController@deleteLayout',
    'POST:api/receipt-template/design/{layout_id}/set-default' => 'Admin/ReceiptTemplateDesignController@setDefault',
    'POST:api/receipt/{id}/void' => 'ReceiptController@void',
    'api/receipts' => 'ReceiptController@getReceipts',
    'POST:api/receipt/print' => 'ReceiptController@printToPrinter',
    'api/receipt/templates' => 'ReceiptController@templates',
    'POST:api/receipt/template/save' => 'ReceiptController@saveTemplate',
    
    // Printer Bridge API routes
    'POST:api/printer-bridge/register' => 'PrinterBridgeController@register',
    'POST:api/printer-bridge/heartbeat' => 'PrinterBridgeController@heartbeat',
    'GET:api/printer-bridge/screens' => 'PrinterBridgeController@getScreens',
    'POST:api/printer-bridge/sync-printers' => 'PrinterBridgeController@syncPrinters',
    'POST:api/printer-bridge/queue' => 'PrinterBridgeController@getQueue',
    'POST:api/printer-bridge/update-status' => 'PrinterBridgeController@updateStatus',
    'POST:api/printer-bridge/save-assignment' => 'PrinterBridgeController@assignPrinter',
    
    // replaced => 'PrinterBridgeController@register',
    // replaced => 'PrinterBridgeController@heartbeat',
    // replaced => 'PrinterBridgeController@getQueue',
    // replaced => 'PrinterBridgeController@updateStatus',
    // replaced => 'PrinterBridgeController@getPrinterRoles',
    'GET:api/printer-bridge/generate-token' => 'PrinterBridgeController@generateToken',
    'GET:api/printer-bridge/config/{token}' => 'PrinterBridgeController@getConfigByToken',
    'GET:api/printer-bridge/list' => 'PrinterBridgeController@list',
    'GET:api/printer-bridge/printers' => 'PrinterBridgeController@getPrinters',
    'POST:api/printer-bridge/assign-printer' => 'PrinterBridgeController@assignPrinter',
    // replaced => 'PrinterBridgeController@syncPrinters',
    'GET:api/printer-bridge/detected-printers' => 'PrinterBridgeController@getDetectedPrinters',
    
    // Preparation Screens API (for desktop app)
    'GET:api/preparation-screens' => 'Admin/PreparationScreenController@getAll',
    
    // === SUPER ADMIN ROUTES (/qodmin/*) ===
    'qodmin/businesses' => 'SuperAdmin/BusinessesController@index',
    'qodmin/businesses/create' => 'SuperAdmin/BusinessesController@create',
    'POST:qodmin/businesses' => 'SuperAdmin/BusinessesController@store',
    'qodmin/businesses/{id}' => 'SuperAdmin/BusinessesController@show',
    'qodmin/businesses/{id}/login-as' => 'SuperAdmin/BusinessesController@loginAs',
    'qodmin/restore-session' => 'SuperAdmin/BusinessesController@restoreSession',
    // Cross-subdomain "Login As" handoff. Hem qordy.com hem de alt alan
    // adlarının public olarak açık tuttuğu tek endpoint; içeride tek
    // kullanımlık token doğrulanır.
    'admin-handoff' => 'SuperAdmin/BusinessesController@adminHandoff',
    'api/qodmin/business-owners' => 'SuperAdmin/BusinessesController@getBusinessOwners',
    'qodmin/business-owners' => 'SuperAdmin/BusinessOwnersController@index',
    'qodmin/queue' => 'SuperAdmin/QueueController@index',
    'qodmin/queue/{id}' => 'SuperAdmin/QueueController@show',
    'qodmin/activity-logs' => 'SuperAdmin/ActivityLogsController@index',
    'qodmin/short-links' => 'SuperAdmin/ShortLinksController@index',
    'POST:api/qodmin/short-links/sync-all' => 'SuperAdmin/ShortLinksController@syncAll',
    'POST:api/qodmin/short-links/{pfdkId}/sync' => 'SuperAdmin/ShortLinksController@sync',
    'GET:api/qodmin/short-links/{pfdkId}/analytics' => 'SuperAdmin/ShortLinksController@analytics',
    'GET:api/qodmin/business-owners/{id}/permissions' => 'SuperAdmin/BusinessOwnersController@getPermissions',
    'GET:api/qodmin/business-owners/{id}' => 'SuperAdmin/BusinessOwnersController@show',
    'POST:api/qodmin/business-owners/update' => 'SuperAdmin/BusinessOwnersController@update',
    'POST:api/qodmin/business-owners/delete' => 'SuperAdmin/BusinessOwnersController@delete',
    
    // qodmin/test-packages route removed (view file does not exist)
    
    // SuperAdmin API endpoints (business hierarchy)
    'GET:api/qodmin/businesses' => 'SuperAdminController@getBusinesses',
    'GET:api/qodmin/businesses/{id}/menu' => 'SuperAdminController@getBusinessMenuItems',
    'GET:api/qodmin/businesses/{id}/categories' => 'SuperAdminController@getBusinessCategories',
    'GET:api/qodmin/businesses/{id}/tables' => 'SuperAdminController@getBusinessTables',
    'GET:api/qodmin/businesses/{id}/staff' => 'SuperAdminController@getBusinessStaff',
    'GET:api/qodmin/businesses/{id}/orders' => 'SuperAdminController@getBusinessOrders',
    'GET:api/qodmin/businesses/{id}/zones' => 'SuperAdminController@getBusinessZones',
    'GET:api/qodmin/businesses/{id}/expenses' => 'SuperAdminController@getBusinessExpenses',
    'GET:api/qodmin/businesses/{id}/invoices' => 'SuperAdminController@getBusinessInvoices',
    'GET:api/qodmin/businesses/{id}/suppliers' => 'SuperAdminController@getBusinessSuppliers',
    'GET:api/qodmin/businesses/{id}/waste' => 'SuperAdminController@getBusinessWaste',
    'GET:api/qodmin/businesses/{id}/printers' => 'SuperAdminController@getBusinessPrinters',
    'GET:api/qodmin/businesses/{id}/stats' => 'SuperAdmin/BusinessesController@getBusinessStats',
    'GET:api/qodmin/businesses/{id}/debug' => 'SuperAdminController@debugBusiness',
    'POST:api/qodmin/businesses/{id}/toggle-status' => 'SuperAdmin/BusinessesController@toggleStatus',
    'POST:api/qodmin/businesses/{id}/qr-menu-status' => 'SuperAdmin/BusinessesController@updateQrMenuStatus',
    'POST:api/qodmin/businesses/{id}/meta-whatsapp-permission' => 'SuperAdmin/BusinessesController@updateMetaWhatsAppPermission',
    'POST:api/qodmin/businesses/{id}/change-role' => 'SuperAdmin/BusinessesController@changeOwnerRole',
    'DELETE:api/qodmin/businesses/{id}/delete' => 'SuperAdmin/BusinessesController@deleteBusiness',
    'POST:api/qodmin/businesses/{id}/upload-logo' => 'SuperAdmin/BusinessesController@uploadLogo',
    'POST:api/qodmin/businesses/{id}/start-trial' => 'SuperAdmin/BusinessesController@startTrial',
    'POST:api/qodmin/businesses/{id}/activate-subscription' => 'SuperAdmin/BusinessesController@activateSubscription',
    'GET:api/qodmin/menu-items/{id}/stock-history' => 'MenuController@getProductStockHistory',
    
    // === TRIAL MANAGEMENT ROUTES (Super Admin) ===
    'qodmin/trial-settings' => 'Admin/TrialController@settings',
    'POST:qodmin/trial-settings' => 'Admin/TrialController@settings',
    'qodmin/trial-users' => 'Admin/TrialController@users',
    'POST:api/qodmin/trial/extend' => 'Admin/TrialController@extendTrial',
    'POST:api/qodmin/trial/cancel' => 'Admin/TrialController@cancelTrial',

    // === PUBLIC TRIAL SETTINGS (JSON) — consumed by external / cached landing pages ===
    'GET:api/trial/public-settings' => 'LandingController@apiTrialSettings',

    // === CUSTOM PAYMENT LINKS (Super Admin) ===
    'qodmin/payment-links' => 'Admin/PaymentLinksController@index',
    'qodmin/payment-links/create' => 'Admin/PaymentLinksController@create',
    'POST:qodmin/payment-links' => 'Admin/PaymentLinksController@store',
    'POST:qodmin/payment-links/{id}/revoke' => 'Admin/PaymentLinksController@revoke',
    'POST:qodmin/payment-links/{id}/toggle-reusable' => 'Admin/PaymentLinksController@toggleReusable',

    // === CUSTOM PAYMENT LINKS (Public / Customer) ===
    'GET:pay/{token}' => 'Customer/CustomPaymentLinkController@show',
    'POST:pay/{token}/start' => 'Customer/CustomPaymentLinkController@start',
    // iyzico posts to a stable, token-less URL. We recover the link
    // via the DB-persisted intent keyed by iyzico's own token.
    'POST:api/payment/iyzico/custom-link-callback' => 'Customer/CustomPaymentLinkController@iyzicoCallback',
    'GET:api/payment/iyzico/custom-link-callback' => 'Customer/CustomPaymentLinkController@iyzicoCallback',
    'GET:pay/{token}/success' => 'Customer/CustomPaymentLinkController@success',
    // Post-payment password setup for new_customer links. Claims the
    // account bootstrapped during start() and logs the user in.
    'POST:pay/{token}/activate' => 'Customer/CustomPaymentLinkController@activate',

    // === IN-APP POPUP / FLUTTER BOTTOM SHEET API ===
    // Müşterinin kendisine tanımlanmış aktif özel ödeme tekliflerini listeler.
    'GET:api/customer/custom-offers' => 'Customer/CustomOfferApiController@list',
    'POST:api/customer/custom-offers/{link_id}/dismiss' => 'Customer/CustomOfferApiController@dismiss',
    'GET:api/customer/purchase-history' => 'Customer/CustomOfferApiController@history',
    
    // === LEGAL PAGES MANAGEMENT (Super Admin) ===
    'qodmin/legal-pages' => 'Admin/LegalPageController@index',
    'qodmin/legal-pages/create' => 'Admin/LegalPageController@create',
    'POST:qodmin/legal-pages/store' => 'Admin/LegalPageController@store',
    'qodmin/legal-pages/{id}/edit' => 'Admin/LegalPageController@edit',
    'POST:qodmin/legal-pages/{id}/update' => 'Admin/LegalPageController@update',
    'POST:api/qodmin/legal-pages/{id}/delete' => 'Admin/LegalPageController@destroy',
    'POST:api/qodmin/legal-pages/{id}/toggle' => 'Admin/LegalPageController@toggle',
    
    // === TRIAL PAGE ROUTES ===
    'trial/expired' => 'TrialPageController@expired',
    
    // === PUBLIC LEGAL PAGES ===
    'sayfa/{slug}' => 'LegalPageController@show',

    // === GOOGLE PLAY HESAP SİLME TALEBİ ===
    // Play Console "Data Safety → Account deletion request URL" alanı
    // zorunlu olduğu için public, indexlenebilir bir talep formu.
    // Gerçekte silme işlemi destek ekibi tarafından manuel yürütülür.
    'hesap-sil' => 'AccountDeletionController@show',
    'POST:hesap-sil' => 'AccountDeletionController@submit',
    'account-deletion' => 'AccountDeletionController@show',
    'POST:account-deletion' => 'AccountDeletionController@submit',

    // Business Admin Routes
    'business/dashboard'                    => 'Admin/DashboardController@dashboard',
    'business/operations'                   => 'BusinessAdminController@operations',
    'business/finance'                      => 'BusinessAdminController@finance',
    'business/settings'                     => 'BusinessAdminController@settings',
    'business/analysis'                     => 'BusinessAdminController@analysis',
    // Business alias routes — mirror qodmin/* equivalents for ROLE_BUSINESS_MANAGER
    'business/staff'                        => 'Admin/UsersController@users',
    'POST:business/staff'                   => 'Admin/UsersController@users',
    'business/business-settings'            => 'BusinessAdminController@settings',
    'POST:business/business-settings'       => 'BusinessAdminController@updateSettings',
    'business/roles'                        => 'Admin/RolesPermissionsController@rolesPermissions',
    'business/receipts'                     => 'BusinessAdminController@receipts',
    'business/order-approvals'              => 'BusinessAdminController@orderApprovals',
    'business/order-approval-history'       => 'BusinessAdminController@orderApprovalHistory',


    // === BLOG ROUTES (Public) ===
    // Blog is powered by the Soro AI widget (https://app.trysoro.com).
    // SoroBlogController renders a server-side SEO shell, gracefully
    // falls back to any legacy internal posts, and cooperates with
    // SoroBlogMirrorService for a self-updating sitemap.
    'blog' => 'SoroBlogController@index',
    'blog/category/{slug}' => 'SoroBlogController@category',
    'blog/{slug}' => 'SoroBlogController@post',
    'GET:api/soro/articles' => 'SoroBlogController@apiArticles',

    // Legacy internal blog (still reachable for admins/debugging):
    'blog-archive' => 'BlogController@index',
    'blog-archive/{slug}' => 'BlogController@post',
    'blog-archive/category/{slug}' => 'BlogController@category',
    
    // === BLOG MANAGEMENT ROUTES (Admin) ===
    'admin/blog-management' => 'Admin/BlogManagementController@index',
    'POST:admin/blog-management/generate-post' => 'Admin/BlogManagementController@generatePost',
    'GET:admin/blog-management/unpublished-topics' => 'Admin/BlogManagementController@getUnpublishedTopics',
    'POST:admin/blog-management/optimize-all' => 'Admin/BlogManagementController@optimizeAll',
    'business/staff-login' => 'BusinessAdminController@staffLogin',

    // Web migration routes REMOVED - güvenlik açığı. Migration'lar CLI ile çalıştırılmalı.

 // === HEALTH & METRICS (Refactor Q4 2026) ===
 'GET:health' => 'HealthController@check',
 'GET:metrics' => 'MetricsController@index',

 // === MOBILE API v2 (Refactored) ===
 'POST:api/mobile/auth/login' => 'API/Mobile/MobileAuthController@login',
 'POST:api/mobile/auth/verify-2fa' => 'API/Mobile/MobileAuthController@verify2FA',
 'POST:api/mobile/auth/refresh' => 'API/Mobile/MobileAuthController@refresh',
 'POST:api/mobile/auth/logout' => 'API/Mobile/MobileAuthController@logout',
 'GET:api/mobile/orders' => 'API/Mobile/MobileOrderController@getOrders',
 'POST:api/mobile/orders' => 'API/Mobile/MobileOrderController@createOrder',
 'PATCH:api/mobile/orders/status' => 'API/Mobile/MobileOrderController@updateStatus',

 // === PUBLIC API v2 (Refactored) ===
 'GET:api/v2/menu' => 'API/OrdersController@menu',
 'GET:api/v2/orders' => 'API/OrdersController@index',
 'POST:api/v2/orders' => 'API/OrdersController@place',
 'PATCH:api/v2/orders/status' => 'API/OrdersController@updateStatus',
 'POST:api/v2/orders/call-waiter' => 'API/OrdersController@callWaiter',
 'GET:api/v2/tables' => 'API/TablesController@index',
 'GET:api/v2/zones' => 'API/TablesController@zones',
 'GET:api/v2/floors' => 'API/TablesController@floors',
 'GET:api/v2/tables/qr' => 'API/TablesController@downloadQR',

 // === MENU API v2 (Refactored) ===
 'GET:api/v2/menu/categories' => 'Menu/CategoryController@index',
 'POST:api/v2/menu/categories' => 'Menu/CategoryController@add',
 'PUT:api/v2/menu/categories' => 'Menu/CategoryController@edit',
 'DELETE:api/v2/menu/categories' => 'Menu/CategoryController@delete',
 'GET:api/v2/menu/items' => 'Menu/MenuItemController@index',
 'POST:api/v2/menu/items' => 'Menu/MenuItemController@add',
 'PUT:api/v2/menu/items' => 'Menu/MenuItemController@edit',
 'DELETE:api/v2/menu/items' => 'Menu/MenuItemController@delete',
 'PATCH:api/v2/menu/items/availability' => 'Menu/MenuItemController@updateAvailability',
 'POST:api/v2/menu/items/extract-from-image' => 'Menu/MenuItemController@extractFromImage',
 'POST:api/v2/menu/items/bulk-add' => 'Menu/MenuItemController@bulkAddFromExtraction',
];
