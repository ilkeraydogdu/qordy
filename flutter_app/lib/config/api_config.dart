class ApiConfig {
  ApiConfig._();

  /// Base host for API calls.
  ///
  /// Overridable per build via:
  ///   flutter run --dart-define=API_BASE_URL=https://staging.qordy.com
  ///
  /// Production defaults to the live host. Typical flavors:
  ///   dev   → http://10.0.2.2:8080   (Android emulator, local PHP)
  ///   stage → https://staging.qordy.com
  ///   prod  → https://qordy.com   (default)
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://qordy.com',
  );

  /// Endpoint prefix. Can be overridden when mounting the mobile API
  /// at a non-standard path.
  static const String apiPrefix = String.fromEnvironment(
    'API_PREFIX',
    defaultValue: '/api/mobile',
  );

  static const Duration timeout = Duration(seconds: 15);

  // Auth
  static const String validateSubdomain = '$apiPrefix/validate-subdomain';
  /// Personel girişi için 4-6 haneli benzersiz işletme numarası
  /// doğrulama endpoint'i. Subdomain ile girişe ek olarak sunulur.
  static const String validateBusinessNumber = '$apiPrefix/validate-business-number';
  static const String staffLogin = '$apiPrefix/staff/login';
  static const String managerValidateEmail = '$apiPrefix/manager/validate-email';
  static const String managerLogin = '$apiPrefix/manager/login';
  static const String verifyToken = '$apiPrefix/verify-token';
  static const String refreshToken = '$apiPrefix/refresh-token';
  static const String logout = '$apiPrefix/logout';
  static const String register = '$apiPrefix/register';
  static const String registerSendEmailCode = '$apiPrefix/register/send-email-code';
  static const String registerVerifyEmail = '$apiPrefix/register/verify-email';
  static const String registerSendPhoneCode = '$apiPrefix/register/send-phone-code';
  static const String registerVerifyPhone = '$apiPrefix/register/verify-phone';

  // 2FA TOTP (Google Authenticator, Authy…)
  static const String totpStatus = '$apiPrefix/security/totp/status';
  static const String totpSetup = '$apiPrefix/security/totp/setup';
  static const String totpConfirm = '$apiPrefix/security/totp/confirm';
  static const String totpDisable = '$apiPrefix/security/totp/disable';
  static const String twoFactorVerify = '$apiPrefix/auth/2fa/verify';
  static const String twoFactorSend = '$apiPrefix/auth/2fa/send';

  // 2FA WhatsApp (Meta Cloud API)
  static const String whatsapp2faStatus = '$apiPrefix/security/whatsapp/status';
  static const String whatsapp2faSetup = '$apiPrefix/security/whatsapp/setup';
  static const String whatsapp2faConfirm = '$apiPrefix/security/whatsapp/confirm';
  static const String whatsapp2faDisable = '$apiPrefix/security/whatsapp/disable';
  static const String authMethodsStatus = '$apiPrefix/security/auth-methods';

  // Dashboard
  static const String dashboard = '$apiPrefix/staff/dashboard';

  // Orders
  static const String orders = '$apiPrefix/orders';
  static const String orderStatus = '$apiPrefix/orders/status';
  static const String orderHasKitchenItems = '$apiPrefix/orders/has-kitchen-items';

  // Tables
  static const String tables = '$apiPrefix/tables';

  // Notifications
  static const String notifications = '$apiPrefix/notifications';
  static const String notificationsRead = '$apiPrefix/notifications/read';
  static const String notificationsReadAll = '$apiPrefix/notifications/read-all';
  static const String notificationCount = '$apiPrefix/notifications/unread-count';
  static const String notificationsRegisterToken = '$apiPrefix/notifications/register-token';

  // Manager - Analytics
  static const String analytics = '$apiPrefix/manager/analytics';
  static const String analyticsCategories = '$apiPrefix/manager/analytics/categories';

  // Manager - Staff
  static const String staff = '$apiPrefix/manager/staff';
  static const String staffCreate = '$apiPrefix/manager/staff/create';
  static const String staffUpdate = '$apiPrefix/manager/staff/update';
  static const String staffDelete = '$apiPrefix/manager/staff/delete';
  static const String roles = '$apiPrefix/manager/roles';

  // Manager - Menu
  static const String menu = '$apiPrefix/menu';
  static const String menuAvailability = '$apiPrefix/manager/menu/availability';
  static const String menuAddItem = '$apiPrefix/manager/menu/add-item';
  static const String menuUpdateItem = '$apiPrefix/manager/menu/update-item';
  static const String menuDeleteItem = '$apiPrefix/manager/menu/delete-item';

  // Manager - Categories
  static const String categories = '$apiPrefix/manager/categories';
  static const String categoryCreate = '$apiPrefix/manager/categories/create';
  static const String categoryUpdate = '$apiPrefix/manager/categories/update';
  static const String categoryDelete = '$apiPrefix/manager/categories/delete';

  // Manager - Zones
  static const String zones = '$apiPrefix/manager/zones';
  static const String zoneCreate = '$apiPrefix/manager/zones/create';
  static const String zoneUpdate = '$apiPrefix/manager/zones/update';
  static const String zoneDelete = '$apiPrefix/manager/zones/delete';

  // Manager - Tables
  static const String tableCreate = '$apiPrefix/manager/tables/create';
  static const String tableUpdate = '$apiPrefix/manager/tables/update';
  static const String tableDelete = '$apiPrefix/manager/tables/delete';

  // Manager - Expenses
  static const String expenses = '$apiPrefix/manager/expenses';
  static const String expenseCreate = '$apiPrefix/manager/expenses/create';
  static const String expenseUpdate = '$apiPrefix/manager/expenses/update';
  static const String expenseDelete = '$apiPrefix/manager/expenses/delete';

  // Manager - Reservations
  static const String reservations = '$apiPrefix/manager/reservations';
  static const String reservationCreate = '$apiPrefix/manager/reservations/create';
  static const String reservationUpdate = '$apiPrefix/manager/reservations/update';
  static const String reservationDelete = '$apiPrefix/manager/reservations/delete';

  // Manager - Settings
  static const String settings = '$apiPrefix/manager/settings';

  // Manager - Reports
  static const String productSales = '$apiPrefix/manager/product-sales';
  static const String zReport = '$apiPrefix/manager/z-report';
  static const String zReportPrint = '$apiPrefix/manager/z-report-print';
  static const String stock = '$apiPrefix/manager/stock';
  static const String receipts = '$apiPrefix/manager/receipts';

  // Manager - Order Approvals
  static const String orderApprovals = '$apiPrefix/manager/order-approvals';
  static const String orderApprovalsApprove = '$apiPrefix/manager/order-approvals/approve';
  static const String orderApprovalsReject = '$apiPrefix/manager/order-approvals/reject';

  // POS
  static const String posCreateOrder = '$apiPrefix/pos/create-order';
  static const String posAddItem = '$apiPrefix/pos/add-item';
  static const String posRemoveItem = '$apiPrefix/pos/remove-item';
  static const String posUpdateQuantity = '$apiPrefix/pos/update-quantity';
  static const String posProcessPayment = '$apiPrefix/pos/process-payment';
  static const String posPrintAdisyon = '$apiPrefix/pos/print-adisyon';
  static const String posActiveOrders = '$apiPrefix/pos/active-orders';
  static const String posTableOrders = '$apiPrefix/pos/table-orders';
  static const String posClearTable = '$apiPrefix/pos/clear-table';

  // Kitchen
  static const String kitchenOrders = '$apiPrefix/kitchen/orders';
  static const String kitchenUpdateStatus = '$apiPrefix/kitchen/update-status';

  // Preparation
  static const String preparationOrders = '$apiPrefix/preparation/orders';
  static const String preparationUpdateStatus = '$apiPrefix/preparation/update-status';

  // Waiter
  static const String waiterTableDetails = '$apiPrefix/waiter/table-details';
  static const String waiterReadyOrders = '$apiPrefix/waiter/ready-orders';
  static const String waiterDeliverOrder = '$apiPrefix/waiter/deliver-order';
  static const String waiterTransferCashier = '$apiPrefix/waiter/transfer-cashier';
  static const String waiterDeleteOrderItem = '$apiPrefix/waiter/delete-order-item';
  static const String waiterAcceptOrder = '$apiPrefix/waiter/accept-order';
  static const String waiterMoveTable = '$apiPrefix/waiter/move-table';

  // Packages
  static const String packages = '$apiPrefix/packages/list';
  static const String packageSubscribe = '$apiPrefix/packages/purchase';
  static const String packageUploadReceipt = '$apiPrefix/packages/upload-receipt';
  static const String packagePendingPayments = '$apiPrefix/packages/pending-payments';
  static const String subscriptionStatus = '$apiPrefix/subscription/status';
  static const String paymentIyzicoInitiate = '$apiPrefix/payment/iyzico/initiate';
  static const String paymentIyzicoStatus = '$apiPrefix/payment/iyzico/status';
  static const String packagesAssignedOffer = '$apiPrefix/packages/assigned-offer';
  static const String packagesCustomOffers = '$apiPrefix/packages/custom-offers';
  static String packagesCustomOfferDismiss(String linkId) =>
      '$apiPrefix/packages/custom-offers/$linkId/dismiss';
  static const String subscriptionHistory = '$apiPrefix/subscription/history';

  // Zones with tables
  static const String zoneTables = '$apiPrefix/manager/zones'; // + /{zoneId}/tables

  // Stock CRUD
  static const String stockAdd = '$apiPrefix/manager/stock/add';
  static const String stockRemove = '$apiPrefix/manager/stock/remove';
  static const String stockAdjust = '$apiPrefix/manager/stock/adjust';
  static const String stockDelete = '$apiPrefix/manager/stock/delete';

  // Device / FCM
  static const String registerDevice = '$apiPrefix/device/register';
  static const String unregisterDevice = '$apiPrefix/device/unregister';

  // Printers + bridges
  static const String printerBridges = '$apiPrefix/printers/bridges';
  static const String printerBridgeRevealKey =
      '$apiPrefix/printers/bridges/reveal-key';
  static const String printerBridgeCreate =
      '$apiPrefix/printers/bridges/create';
  static const String printerBridgeUpdate =
      '$apiPrefix/printers/bridges/update';
  static const String printerBridgeDelete =
      '$apiPrefix/printers/bridges/delete';
  static const String printersForBridge =
      '$apiPrefix/printers/bridge-printers';
  static const String printerUpdate = '$apiPrefix/printers/update';
  static const String printerDelete = '$apiPrefix/printers/delete';
  static const String printerTest = '$apiPrefix/printers/test';
  static const String printerPrepScreens = '$apiPrefix/printers/prep-screens';

  // Queue
  static const String queueList = '$apiPrefix/queue';
  static const String queueSettings = '$apiPrefix/queue/settings';
  static const String queueCallNext = '$apiPrefix/queue/call-next';
  static const String queueUpdateStatus = '$apiPrefix/queue/update-status';

  // Receipt templates
  static const String receiptTemplates = '$apiPrefix/receipt-templates';
  static const String receiptTemplateCreate =
      '$apiPrefix/receipt-templates/create';
  static const String receiptTemplateUpdate =
      '$apiPrefix/receipt-templates/update';
  static const String receiptTemplateDelete =
      '$apiPrefix/receipt-templates/delete';

  // Roles & permissions
  static const String rolesPermissions = '$apiPrefix/roles-permissions';
  static const String rolesPermissionsUpdate =
      '$apiPrefix/roles-permissions/update';

  // Order approval history
  static const String orderApprovalsHistory =
      '$apiPrefix/order-approvals/history';

  // Table history
  static const String tableHistory = '$apiPrefix/tables/history';

  // Finance — invoices/suppliers/waste
  static const String invoices = '$apiPrefix/finance/invoices';
  static const String invoiceCreate = '$apiPrefix/finance/invoices/create';
  static const String invoiceDelete = '$apiPrefix/finance/invoices/delete';
  static const String suppliers = '$apiPrefix/finance/suppliers';
  static const String supplierCreate = '$apiPrefix/finance/suppliers/create';
  static const String supplierUpdate = '$apiPrefix/finance/suppliers/update';
  static const String supplierDelete = '$apiPrefix/finance/suppliers/delete';
  static const String wasteList = '$apiPrefix/finance/waste';
  static const String wasteCreate = '$apiPrefix/finance/waste/create';
  static const String wasteDelete = '$apiPrefix/finance/waste/delete';

  // Payment gateways / POS devices / Feature flags / Error logs / Reports
  static const String paymentGateways = '$apiPrefix/payment-gateways';
  static const String paymentGatewayToggle =
      '$apiPrefix/payment-gateways/toggle';
  static const String posDevices = '$apiPrefix/pos-devices';
  static const String posDeviceDelete = '$apiPrefix/pos-devices/delete';
  static const String featureFlags = '$apiPrefix/features';
  static const String featureFlagToggle = '$apiPrefix/features/toggle';
  static const String errorLogs = '$apiPrefix/error-logs';
  static const String reports = '$apiPrefix/reports';
}
