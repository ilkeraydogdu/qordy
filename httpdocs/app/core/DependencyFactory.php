<?php
namespace App\Core;

use App\Repositories\UserRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PackageRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SavedPaymentMethodRepository;
use App\Repositories\AdminRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PreparationScreenRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\FinanceCategoryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\MenuItemRepository;
use App\Repositories\ProductVariantRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Repositories\ShiftRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\WasteRecordRepository;
use App\Repositories\ArchivedSessionRepository;
use App\Repositories\IntegrationPlatformRepository;
use App\Repositories\RoleRepository;
use App\Repositories\ConstantsRepository;
use App\Repositories\MenuItemTranslationRepository;
use App\Repositories\ZoneRepository;
use App\Repositories\ReceiptRepository;
use App\Repositories\ReceiptTemplateRepository;
use App\Repositories\ReceiptTemplateLayoutRepository;
use App\Repositories\ReceiptPrintQueueRepository;
use App\Repositories\PrinterRepository;
use App\Repositories\BlogPostRepository;
use App\Repositories\BlogCategoryRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockLocationRepository;
use App\Repositories\ReportsRepository;
use App\Repositories\LeaveTypeRepository;
use App\Repositories\LeaveRepository;
use App\Repositories\MedicalReportRepository;
use App\Repositories\User2FARepository;
use App\Repositories\User2FACodeRepository;
use App\Repositories\PrinterBridgeRepository;
use App\Repositories\TableSessionRepository;
use App\Repositories\CustomerSessionRepository;
use App\Repositories\FeatureRepository;
use App\Repositories\PaymentGatewayRepository;
use App\Repositories\POSDeviceRepository;
use App\Repositories\OrderItemCustomizationRepository;
use App\Repositories\StaffScheduleRepository;
use App\Repositories\ShiftScheduleRepository;
use App\Repositories\GuestStaffRepository;
use App\Repositories\JavaScriptErrorLogRepository;
use App\Repositories\PhpErrorLogRepository;
use App\Repositories\MediaFileRepository;
use App\Services\IngredientCustomizationService;
use App\Services\ImageService;
use App\Services\UserService;
use App\Services\CustomerService;
use App\Services\PackageService;
use App\Services\SubscriptionService;
use App\Services\AdminService;
use App\Services\OrderService;
use App\Services\TableService;
use App\Services\CategoryService;
use App\Services\PreparationScreenService;
use App\Services\DynamicPermissionService;
use App\Services\TableSessionService;
use App\Services\QRCodeSecurityService;
use App\Services\CustomerSessionService;
use App\Services\FeatureService;
use App\Services\ReservationService;
use App\Services\FinanceService;
use App\Services\FinanceCategoryService;
use App\Services\FinanceAnalyticsService;
use App\Services\SystemSettingsService;
use App\Services\NotificationService;
use App\Services\ToastNotificationService;
use App\Services\MenuItemService;
use App\Services\ProductVariantService;
use App\Services\OrderItemService;
use App\Services\OrderEditApprovalService;
use App\Services\PaymentTransactionService;
use App\Services\ShiftService;
use App\Services\IngredientService;
use App\Services\WasteRecordService;
use App\Services\ArchivedSessionService;
use App\Services\IntegrationPlatformService;
use App\Services\GeminiService;
use App\Services\SEOContentService;
use App\Services\AIService;
use App\Services\AuthenticationService;
use App\Services\RoleService;
use App\Services\ConstantsService;
use App\Services\FilterService;
use App\Services\MenuItemTranslationService;
use App\Services\ZoneService;
use App\Services\EmailService;
use App\Services\ReceiptService;
use App\Services\ReceiptTemplateService;
use App\Services\ReceiptTemplateDesignService;
use App\Services\BusinessSettingsService;
use App\Repositories\BusinessSettingsRepository;
use App\Services\BusinessService;
use App\Services\PrinterService;
use App\Services\StockMovementService;
use App\Services\StockLocationService;
use App\Services\SessionService;
use App\Services\ReportsService;
use App\Services\ExportService;
use App\Services\OrderPrintService;
use App\Services\ZReportService;
use App\Services\LeaveTypeService;
use App\Services\LeaveService;
use App\Services\MedicalReportService;
use App\Services\PersonnelService;
use App\Services\TwoFactorAuthService;
use App\Services\Email2FAService;
use App\Services\SMS2FAService;
use App\Services\SMSService;
use App\Services\StaffScheduleService;
use App\Services\FreeTranslationService;
use App\Services\QueueService;
use App\Services\QueueNotificationService;
use App\Services\ImageGenerationService;
use App\Services\ShiftScheduleService;
use App\Services\GuestStaffService;
use App\Services\JavaScriptErrorLogService;
use App\Services\PhpErrorLogService;
use App\Services\UnifiedErrorLogService;
use App\Services\ValidationService;
use App\Services\CacheService;
use App\Services\WebSocketService;
use App\Services\PaymentService;
use App\Services\PaymentGatewayService;
use App\Services\POSDeviceService;
use App\Services\NavigationService;
use App\Services\SubdomainService;
use App\Services\TableActivityLogService;
use App\Repositories\TableActivityLogRepository;
use App\Repositories\WhatsAppMessageLogRepository;
use App\Services\WhatsAppMessageLogService;
use App\Config\Database;

class DependencyFactory {
    private static $instances = [];
    private static $container = null;

    public static function getDatabase() {
        if (!isset(self::$instances['database'])) {
            $database = new Database();
            self::$instances['database'] = $database->connect();
        }
        return self::$instances['database'];
    }

    public static function getUserRepository() {
        if (!isset(self::$instances['userRepository'])) {
            self::$instances['userRepository'] = new UserRepository(self::getDatabase());
        }
        return self::$instances['userRepository'];
    }

    public static function getUserService() {
        if (!isset(self::$instances['userService'])) {
            self::$instances['userService'] = new UserService(self::getUserRepository());
        }
        return self::$instances['userService'];
    }

    public static function getCustomerRepository() {
        if (!isset(self::$instances['customerRepository'])) {
            self::$instances['customerRepository'] = new CustomerRepository(self::getDatabase());
        }
        return self::$instances['customerRepository'];
    }

    public static function getDemoAccessLogRepository() {
        if (!isset(self::$instances['demoAccessLogRepository'])) {
            require_once __DIR__ . '/../repositories/DemoAccessLogRepository.php';
            self::$instances['demoAccessLogRepository'] = new \App\Repositories\DemoAccessLogRepository(self::getDatabase());
        }
        return self::$instances['demoAccessLogRepository'];
    }

    public static function getCustomerService() {
        if (!isset(self::$instances['customerService'])) {
            self::$instances['customerService'] = new CustomerService(self::getCustomerRepository());
        }
        return self::$instances['customerService'];
    }


    public static function getPackageRepository() {
        if (!isset(self::$instances['packageRepository'])) {
            require_once __DIR__ . '/../repositories/PackageRepository.php';
            self::$instances['packageRepository'] = new PackageRepository(self::getDatabase());
        }
        return self::$instances['packageRepository'];
    }

    public static function getPackageService() {
        if (!isset(self::$instances['packageService'])) {
            require_once __DIR__ . '/../services/PackageService.php';
            self::$instances['packageService'] = new \App\Services\PackageService(self::getPackageRepository());
        }
        return self::$instances['packageService'];
    }

    public static function getSubscriptionRepository() {
        if (!isset(self::$instances['subscriptionRepository'])) {
            require_once __DIR__ . '/../repositories/SubscriptionRepository.php';
            self::$instances['subscriptionRepository'] = new SubscriptionRepository(self::getDatabase());
        }
        return self::$instances['subscriptionRepository'];
    }

    public static function getSubscriptionService() {
        if (!isset(self::$instances['subscriptionService'])) {
            require_once __DIR__ . '/../services/SubscriptionService.php';
            self::$instances['subscriptionService'] = new \App\Services\SubscriptionService(
                self::getSubscriptionRepository(),
                self::getPackageRepository()
            );
        }
        return self::$instances['subscriptionService'];
    }

    public static function getActivityLogService() {
        if (!isset(self::$instances['activityLogService'])) {
            require_once __DIR__ . '/../services/ActivityLogService.php';
            self::$instances['activityLogService'] = new \App\Services\ActivityLogService(self::getDatabase());
        }
        return self::$instances['activityLogService'];
    }

    public static function getTrialService() {
        if (!isset(self::$instances['trialService'])) {
            require_once __DIR__ . '/../services/TrialService.php';
            self::$instances['trialService'] = new \App\Services\TrialService();
        }
        return self::$instances['trialService'];
    }

    public static function getCustomPaymentLinkRepository() {
        if (!isset(self::$instances['customPaymentLinkRepository'])) {
            require_once __DIR__ . '/../repositories/CustomPaymentLinkRepository.php';
            self::$instances['customPaymentLinkRepository'] = new \App\Repositories\CustomPaymentLinkRepository(self::getDatabase());
        }
        return self::$instances['customPaymentLinkRepository'];
    }

    public static function getCustomPaymentLinkService() {
        if (!isset(self::$instances['customPaymentLinkService'])) {
            require_once __DIR__ . '/../services/CustomPaymentLinkService.php';
            self::$instances['customPaymentLinkService'] = new \App\Services\CustomPaymentLinkService(
                self::getCustomPaymentLinkRepository()
            );
        }
        return self::$instances['customPaymentLinkService'];
    }

    public static function getCustomPaymentLinkIntentRepository() {
        if (!isset(self::$instances['customPaymentLinkIntentRepository'])) {
            require_once __DIR__ . '/../repositories/CustomPaymentLinkIntentRepository.php';
            self::$instances['customPaymentLinkIntentRepository'] = new \App\Repositories\CustomPaymentLinkIntentRepository(self::getDatabase());
        }
        return self::$instances['customPaymentLinkIntentRepository'];
    }

    public static function getCustomPaymentLinkDismissalRepository() {
        if (!isset(self::$instances['customPaymentLinkDismissalRepository'])) {
            require_once __DIR__ . '/../repositories/CustomPaymentLinkDismissalRepository.php';
            self::$instances['customPaymentLinkDismissalRepository'] = new \App\Repositories\CustomPaymentLinkDismissalRepository(self::getDatabase());
        }
        return self::$instances['customPaymentLinkDismissalRepository'];
    }

    public static function getLegalPageService() {
        if (!isset(self::$instances['legalPageService'])) {
            require_once __DIR__ . '/../services/LegalPageService.php';
            self::$instances['legalPageService'] = new \App\Services\LegalPageService();
        }
        return self::$instances['legalPageService'];
    }

    public static function getBankAccountRepository() {
        if (!isset(self::$instances['bankAccountRepository'])) {
            require_once __DIR__ . '/../repositories/BankAccountRepository.php';
            self::$instances['bankAccountRepository'] = new \App\Repositories\BankAccountRepository(self::getDatabase());
        }
        return self::$instances['bankAccountRepository'];
    }

    public static function getBankTransferPaymentRepository() {
        if (!isset(self::$instances['bankTransferPaymentRepository'])) {
            require_once __DIR__ . '/../repositories/BankTransferPaymentRepository.php';
            self::$instances['bankTransferPaymentRepository'] = new \App\Repositories\BankTransferPaymentRepository(self::getDatabase());
        }
        return self::$instances['bankTransferPaymentRepository'];
    }

    public static function getBankTransferService() {
        if (!isset(self::$instances['bankTransferService'])) {
            require_once __DIR__ . '/../services/BankTransferService.php';
            self::$instances['bankTransferService'] = new \App\Services\BankTransferService(
                self::getBankTransferPaymentRepository(),
                self::getBankAccountRepository()
            );
        }
        return self::$instances['bankTransferService'];
    }

    public static function getSubscriptionPaymentRepository() {
        if (!isset(self::$instances['subscriptionPaymentRepository'])) {
            require_once __DIR__ . '/../repositories/SubscriptionPaymentRepository.php';
            self::$instances['subscriptionPaymentRepository'] = new SubscriptionPaymentRepository(self::getDatabase());
        }
        return self::$instances['subscriptionPaymentRepository'];
    }
    
    public static function getSavedPaymentMethodRepository() {
        if (!isset(self::$instances['savedPaymentMethodRepository'])) {
            require_once __DIR__ . '/../repositories/SavedPaymentMethodRepository.php';
            self::$instances['savedPaymentMethodRepository'] = new SavedPaymentMethodRepository(self::getDatabase());
        }
        return self::$instances['savedPaymentMethodRepository'];
    }
    
    public static function getPaymentService() {
        if (!isset(self::$instances['paymentService'])) {
            require_once __DIR__ . '/../services/PaymentService.php';
            self::$instances['paymentService'] = new \App\Services\PaymentService();
        }
        return self::$instances['paymentService'];
    }

    public static function getAdminRepository() {
        if (!isset(self::$instances['adminRepository'])) {
            require_once __DIR__ . '/../repositories/AdminRepository.php';
            self::$instances['adminRepository'] = new AdminRepository(self::getDatabase());
        }
        return self::$instances['adminRepository'];
    }

    public static function getAdminService() {
        if (!isset(self::$instances['adminService'])) {
            require_once __DIR__ . '/../services/AdminService.php';
            self::$instances['adminService'] = new AdminService(self::getAdminRepository());
        }
        return self::$instances['adminService'];
    }

    public static function getOrderRepository() {
        if (!isset(self::$instances['orderRepository'])) {
            self::$instances['orderRepository'] = new OrderRepository(self::getDatabase());
        }
        return self::$instances['orderRepository'];
    }

    public static function getOrderService() {
        if (!isset(self::$instances['orderService'])) {
            self::$instances['orderService'] = new OrderService(self::getOrderRepository());
        }
        return self::$instances['orderService'];
    }

    public static function getTableRepository() {
        if (!isset(self::$instances['tableRepository'])) {
            self::$instances['tableRepository'] = new TableRepository(self::getDatabase());
        }
        return self::$instances['tableRepository'];
    }

    public static function getTableService() {
        if (!isset(self::$instances['tableService'])) {
            self::$instances['tableService'] = new TableService(self::getTableRepository());
        }
        return self::$instances['tableService'];
    }

    public static function getTableSessionRepository() {
        if (!isset(self::$instances['tableSessionRepository'])) {
            self::$instances['tableSessionRepository'] = new TableSessionRepository(self::getDatabase());
        }
        return self::$instances['tableSessionRepository'];
    }

    public static function getTableSessionService() {
        if (!isset(self::$instances['tableSessionService'])) {
            self::$instances['tableSessionService'] = new TableSessionService(self::getTableSessionRepository());
        }
        return self::$instances['tableSessionService'];
    }

    public static function getCustomerSessionRepository() {
        if (!isset(self::$instances['customerSessionRepository'])) {
            self::$instances['customerSessionRepository'] = new CustomerSessionRepository(self::getDatabase());
        }
        return self::$instances['customerSessionRepository'];
    }

    public static function getCustomerSessionService() {
        if (!isset(self::$instances['customerSessionService'])) {
            self::$instances['customerSessionService'] = new CustomerSessionService(self::getCustomerSessionRepository());
        }
        return self::$instances['customerSessionService'];
    }

    public static function getQRCodeSecurityService() {
        if (!isset(self::$instances['qrCodeSecurityService'])) {
            self::$instances['qrCodeSecurityService'] = new QRCodeSecurityService(
                self::getTableSessionRepository(),
                self::getCustomerSessionRepository()
            );
        }
        return self::$instances['qrCodeSecurityService'];
    }

    public static function getFeatureRepository() {
        if (!isset(self::$instances['featureRepository'])) {
            self::$instances['featureRepository'] = new FeatureRepository(self::getDatabase());
        }
        return self::$instances['featureRepository'];
    }

    public static function getFeatureService() {
        if (!isset(self::$instances['featureService'])) {
            self::$instances['featureService'] = new FeatureService(self::getFeatureRepository());
        }
        return self::$instances['featureService'];
    }

    public static function getPaymentGatewayRepository() {
        if (!isset(self::$instances['paymentGatewayRepository'])) {
            self::$instances['paymentGatewayRepository'] = new PaymentGatewayRepository(self::getDatabase());
        }
        return self::$instances['paymentGatewayRepository'];
    }

    public static function getPaymentGatewayService() {
        if (!isset(self::$instances['paymentGatewayService'])) {
            self::$instances['paymentGatewayService'] = new PaymentGatewayService(self::getPaymentGatewayRepository());
        }
        return self::$instances['paymentGatewayService'];
    }

    public static function getPOSDeviceRepository() {
        if (!isset(self::$instances['posDeviceRepository'])) {
            self::$instances['posDeviceRepository'] = new POSDeviceRepository(self::getDatabase());
        }
        return self::$instances['posDeviceRepository'];
    }

    public static function getPOSDeviceService() {
        if (!isset(self::$instances['posDeviceService'])) {
            self::$instances['posDeviceService'] = new POSDeviceService(self::getPOSDeviceRepository());
        }
        return self::$instances['posDeviceService'];
    }

    public static function getOrderItemCustomizationRepository() {
        if (!isset(self::$instances['orderItemCustomizationRepository'])) {
            self::$instances['orderItemCustomizationRepository'] = new OrderItemCustomizationRepository(self::getDatabase());
        }
        return self::$instances['orderItemCustomizationRepository'];
    }

    public static function getIngredientCustomizationService() {
        if (!isset(self::$instances['ingredientCustomizationService'])) {
            self::$instances['ingredientCustomizationService'] = new IngredientCustomizationService(self::getOrderItemCustomizationRepository());
        }
        return self::$instances['ingredientCustomizationService'];
    }

    public static function getZoneRepository() {
        if (!isset(self::$instances['zoneRepository'])) {
            self::$instances['zoneRepository'] = new ZoneRepository(self::getDatabase());
        }
        return self::$instances['zoneRepository'];
    }

    public static function getZoneService() {
        if (!isset(self::$instances['zoneService'])) {
            self::$instances['zoneService'] = new ZoneService(self::getZoneRepository());
        }
        return self::$instances['zoneService'];
    }

    public static function getCategoryRepository() {
        if (!isset(self::$instances['categoryRepository'])) {
            self::$instances['categoryRepository'] = new CategoryRepository(self::getDatabase());
        }
        return self::$instances['categoryRepository'];
    }

    public static function getCategoryService() {
        if (!isset(self::$instances['categoryService'])) {
            self::$instances['categoryService'] = new CategoryService(self::getCategoryRepository());
        }
        return self::$instances['categoryService'];
    }


    public static function getPreparationScreenRepository() {
        if (!isset(self::$instances['preparationScreenRepository'])) {
            self::$instances['preparationScreenRepository'] = new PreparationScreenRepository(self::getDatabase());
        }
        return self::$instances['preparationScreenRepository'];
    }

    public static function getPreparationScreenService() {
        if (!isset(self::$instances['preparationScreenService'])) {
            self::$instances['preparationScreenService'] = new PreparationScreenService(self::getPreparationScreenRepository());
        }
        return self::$instances['preparationScreenService'];
    }

    public static function getDynamicPermissionService() {
        if (!isset(self::$instances['dynamicPermissionService'])) {
            self::$instances['dynamicPermissionService'] = new DynamicPermissionService();
        }
        return self::$instances['dynamicPermissionService'];
    }

    public static function getReservationRepository() {
        if (!isset(self::$instances['reservationRepository'])) {
            self::$instances['reservationRepository'] = new ReservationRepository(self::getDatabase());
        }
        return self::$instances['reservationRepository'];
    }

    public static function getReservationService() {
        if (!isset(self::$instances['reservationService'])) {
            self::$instances['reservationService'] = new ReservationService(self::getReservationRepository());
        }
        return self::$instances['reservationService'];
    }

    public static function getExpenseRepository() {
        if (!isset(self::$instances['expenseRepository'])) {
            self::$instances['expenseRepository'] = new ExpenseRepository(self::getDatabase());
        }
        return self::$instances['expenseRepository'];
    }

    public static function getInvoiceRepository() {
        if (!isset(self::$instances['invoiceRepository'])) {
            self::$instances['invoiceRepository'] = new InvoiceRepository(self::getDatabase());
        }
        return self::$instances['invoiceRepository'];
    }

    public static function getSupplierRepository() {
        if (!isset(self::$instances['supplierRepository'])) {
            self::$instances['supplierRepository'] = new SupplierRepository(self::getDatabase());
        }
        return self::$instances['supplierRepository'];
    }

    public static function getFinanceCategoryRepository() {
        if (!isset(self::$instances['financeCategoryRepository'])) {
            self::$instances['financeCategoryRepository'] = new FinanceCategoryRepository(self::getDatabase());
        }
        return self::$instances['financeCategoryRepository'];
    }

    public static function getFinanceCategoryService() {
        if (!isset(self::$instances['financeCategoryService'])) {
            self::$instances['financeCategoryService'] = new FinanceCategoryService(self::getFinanceCategoryRepository());
        }
        return self::$instances['financeCategoryService'];
    }

    public static function getFinanceService() {
        if (!isset(self::$instances['financeService'])) {
            self::$instances['financeService'] = new FinanceService(
                self::getExpenseRepository(),
                self::getInvoiceRepository(),
                self::getSupplierRepository()
            );
        }
        return self::$instances['financeService'];
    }

    public static function getFinanceAnalyticsService() {
        if (!isset(self::$instances['financeAnalyticsService'])) {
            self::$instances['financeAnalyticsService'] = new FinanceAnalyticsService(self::getDatabase());
        }
        return self::$instances['financeAnalyticsService'];
    }

    public static function getSystemSettingsRepository() {
        if (!isset(self::$instances['systemSettingsRepository'])) {
            self::$instances['systemSettingsRepository'] = new SystemSettingsRepository(self::getDatabase());
        }
        return self::$instances['systemSettingsRepository'];
    }

    public static function getSystemSettingsService() {
        if (!isset(self::$instances['systemSettingsService'])) {
            self::$instances['systemSettingsService'] = new SystemSettingsService(self::getSystemSettingsRepository());
        }
        return self::$instances['systemSettingsService'];
    }

    public static function getNotificationRepository() {
        if (!isset(self::$instances['notificationRepository'])) {
            self::$instances['notificationRepository'] = new NotificationRepository(self::getDatabase());
        }
        return self::$instances['notificationRepository'];
    }

    public static function getNotificationService() {
        if (!isset(self::$instances['notificationService'])) {
            self::$instances['notificationService'] = new NotificationService(self::getNotificationRepository());
        }
        return self::$instances['notificationService'];
    }

    public static function getToastNotificationService() {
        if (!isset(self::$instances['toastNotificationService'])) {
            self::$instances['toastNotificationService'] = ToastNotificationService::getInstance();
        }
        return self::$instances['toastNotificationService'];
    }

    public static function getMenuItemRepository() {
        if (!isset(self::$instances['menuItemRepository'])) {
            self::$instances['menuItemRepository'] = new MenuItemRepository(self::getDatabase());
        }
        return self::$instances['menuItemRepository'];
    }

    public static function getMenuItemService() {
        if (!isset(self::$instances['menuItemService'])) {
            self::$instances['menuItemService'] = new MenuItemService(self::getMenuItemRepository());
        }
        return self::$instances['menuItemService'];
    }

    public static function getProductVariantRepository() {
        if (!isset(self::$instances['productVariantRepository'])) {
            self::$instances['productVariantRepository'] = new ProductVariantRepository(self::getDatabase());
        }
        return self::$instances['productVariantRepository'];
    }

    public static function getProductVariantService() {
        if (!isset(self::$instances['productVariantService'])) {
            self::$instances['productVariantService'] = new ProductVariantService(self::getProductVariantRepository());
        }
        return self::$instances['productVariantService'];
    }

    public static function getMenuItemTranslationRepository() {
        if (!isset(self::$instances['menuItemTranslationRepository'])) {
            self::$instances['menuItemTranslationRepository'] = new MenuItemTranslationRepository(self::getDatabase());
        }
        return self::$instances['menuItemTranslationRepository'];
    }

    public static function getMenuItemTranslationService() {
        if (!isset(self::$instances['menuItemTranslationService'])) {
            self::$instances['menuItemTranslationService'] = new MenuItemTranslationService(self::getMenuItemTranslationRepository());
        }
        return self::$instances['menuItemTranslationService'];
    }

    public static function getOrderItemRepository() {
        if (!isset(self::$instances['orderItemRepository'])) {
            self::$instances['orderItemRepository'] = new OrderItemRepository(self::getDatabase());
        }
        return self::$instances['orderItemRepository'];
    }

    public static function getOrderItemService() {
        if (!isset(self::$instances['orderItemService'])) {
            self::$instances['orderItemService'] = new OrderItemService(self::getOrderItemRepository());
        }
        return self::$instances['orderItemService'];
    }

    public static function getOrderEditApprovalService() {
        if (!isset(self::$instances['orderEditApprovalService'])) {
            self::$instances['orderEditApprovalService'] = new OrderEditApprovalService();
        }
        return self::$instances['orderEditApprovalService'];
    }

    public static function getTableActivityLogRepository() {
        if (!isset(self::$instances['tableActivityLogRepository'])) {
            self::$instances['tableActivityLogRepository'] = new TableActivityLogRepository(self::getDatabase());
        }
        return self::$instances['tableActivityLogRepository'];
    }

    public static function getTableActivityLogService() {
        if (!isset(self::$instances['tableActivityLogService'])) {
            self::$instances['tableActivityLogService'] = new TableActivityLogService(self::getTableActivityLogRepository());
        }
        return self::$instances['tableActivityLogService'];
    }

    public static function getWhatsAppMessageLogRepository() {
        if (!isset(self::$instances['whatsAppMessageLogRepository'])) {
            require_once __DIR__ . '/../repositories/WhatsAppMessageLogRepository.php';
            self::$instances['whatsAppMessageLogRepository'] = new WhatsAppMessageLogRepository(self::getDatabase());
        }
        return self::$instances['whatsAppMessageLogRepository'];
    }

    public static function getWhatsAppMessageLogService() {
        if (!isset(self::$instances['whatsAppMessageLogService'])) {
            require_once __DIR__ . '/../services/WhatsAppMessageLogService.php';
            self::$instances['whatsAppMessageLogService'] = new WhatsAppMessageLogService(self::getWhatsAppMessageLogRepository());
        }
        return self::$instances['whatsAppMessageLogService'];
    }

    public static function getPaymentTransactionRepository() {
        if (!isset(self::$instances['paymentTransactionRepository'])) {
            self::$instances['paymentTransactionRepository'] = new PaymentTransactionRepository(self::getDatabase());
        }
        return self::$instances['paymentTransactionRepository'];
    }

    public static function getPaymentTransactionService() {
        if (!isset(self::$instances['paymentTransactionService'])) {
            self::$instances['paymentTransactionService'] = new PaymentTransactionService(self::getPaymentTransactionRepository());
        }
        return self::$instances['paymentTransactionService'];
    }

    public static function getShiftRepository() {
        if (!isset(self::$instances['shiftRepository'])) {
            self::$instances['shiftRepository'] = new ShiftRepository(self::getDatabase());
        }
        return self::$instances['shiftRepository'];
    }

    public static function getShiftService() {
        if (!isset(self::$instances['shiftService'])) {
            self::$instances['shiftService'] = new ShiftService(self::getShiftRepository());
        }
        return self::$instances['shiftService'];
    }

    public static function getStaffScheduleRepository() {
        if (!isset(self::$instances['staffScheduleRepository'])) {
            self::$instances['staffScheduleRepository'] = new StaffScheduleRepository(self::getDatabase());
        }
        return self::$instances['staffScheduleRepository'];
    }

    public static function getStaffScheduleService() {
        if (!isset(self::$instances['staffScheduleService'])) {
            self::$instances['staffScheduleService'] = new StaffScheduleService(self::getStaffScheduleRepository());
        }
        return self::$instances['staffScheduleService'];
    }

    public static function getShiftScheduleRepository() {
        if (!isset(self::$instances['shiftScheduleRepository'])) {
            self::$instances['shiftScheduleRepository'] = new ShiftScheduleRepository(self::getDatabase());
        }
        return self::$instances['shiftScheduleRepository'];
    }

    public static function getShiftScheduleService() {
        if (!isset(self::$instances['shiftScheduleService'])) {
            self::$instances['shiftScheduleService'] = new ShiftScheduleService(
                self::getShiftScheduleRepository(),
                self::getStaffScheduleRepository()
            );
        }
        return self::$instances['shiftScheduleService'];
    }

    public static function getGuestStaffRepository() {
        if (!isset(self::$instances['guestStaffRepository'])) {
            self::$instances['guestStaffRepository'] = new GuestStaffRepository(self::getDatabase());
        }
        return self::$instances['guestStaffRepository'];
    }

    public static function getGuestStaffService() {
        if (!isset(self::$instances['guestStaffService'])) {
            self::$instances['guestStaffService'] = new GuestStaffService(self::getGuestStaffRepository());
        }
        return self::$instances['guestStaffService'];
    }

    public static function getJavaScriptErrorLogRepository() {
        if (!isset(self::$instances['javascriptErrorLogRepository'])) {
            self::$instances['javascriptErrorLogRepository'] = new JavaScriptErrorLogRepository(self::getDatabase());
        }
        return self::$instances['javascriptErrorLogRepository'];
    }

    public static function getJavaScriptErrorLogService() {
        if (!isset(self::$instances['javascriptErrorLogService'])) {
            self::$instances['javascriptErrorLogService'] = new JavaScriptErrorLogService(self::getJavaScriptErrorLogRepository());
        }
        return self::$instances['javascriptErrorLogService'];
    }

    public static function getPhpErrorLogRepository() {
        if (!isset(self::$instances['phpErrorLogRepository'])) {
            self::$instances['phpErrorLogRepository'] = new PhpErrorLogRepository(self::getDatabase());
        }
        return self::$instances['phpErrorLogRepository'];
    }

    public static function getPhpErrorLogService() {
        if (!isset(self::$instances['phpErrorLogService'])) {
            self::$instances['phpErrorLogService'] = new PhpErrorLogService(self::getPhpErrorLogRepository());
        }
        return self::$instances['phpErrorLogService'];
    }

    public static function getUnifiedErrorLogService() {
        if (!isset(self::$instances['unifiedErrorLogService'])) {
            self::$instances['unifiedErrorLogService'] = new UnifiedErrorLogService(
                self::getPhpErrorLogService(),
                self::getJavaScriptErrorLogService()
            );
        }
        return self::$instances['unifiedErrorLogService'];
    }

    public static function getIngredientRepository() {
        if (!isset(self::$instances['ingredientRepository'])) {
            self::$instances['ingredientRepository'] = new IngredientRepository(self::getDatabase());
        }
        return self::$instances['ingredientRepository'];
    }

    public static function getIngredientService() {
        if (!isset(self::$instances['ingredientService'])) {
            self::$instances['ingredientService'] = new IngredientService(self::getIngredientRepository());
        }
        return self::$instances['ingredientService'];
    }

    public static function getWasteRecordRepository() {
        if (!isset(self::$instances['wasteRecordRepository'])) {
            self::$instances['wasteRecordRepository'] = new WasteRecordRepository(self::getDatabase());
        }
        return self::$instances['wasteRecordRepository'];
    }

    public static function getWasteRecordService() {
        if (!isset(self::$instances['wasteRecordService'])) {
            self::$instances['wasteRecordService'] = new WasteRecordService(self::getWasteRecordRepository());
        }
        return self::$instances['wasteRecordService'];
    }

    public static function getArchivedSessionRepository() {
        if (!isset(self::$instances['archivedSessionRepository'])) {
            self::$instances['archivedSessionRepository'] = new ArchivedSessionRepository(self::getDatabase());
        }
        return self::$instances['archivedSessionRepository'];
    }

    public static function getArchivedSessionService() {
        if (!isset(self::$instances['archivedSessionService'])) {
            self::$instances['archivedSessionService'] = new ArchivedSessionService(self::getArchivedSessionRepository());
        }
        return self::$instances['archivedSessionService'];
    }

    public static function getIntegrationPlatformRepository() {
        if (!isset(self::$instances['integrationPlatformRepository'])) {
            self::$instances['integrationPlatformRepository'] = new IntegrationPlatformRepository(self::getDatabase());
        }
        return self::$instances['integrationPlatformRepository'];
    }

    public static function getIntegrationPlatformService() {
        if (!isset(self::$instances['integrationPlatformService'])) {
            self::$instances['integrationPlatformService'] = new IntegrationPlatformService(self::getIntegrationPlatformRepository());
        }
        return self::$instances['integrationPlatformService'];
    }


    public static function getGeminiService() {
        if (!isset(self::$instances['geminiService'])) {
            try {
                self::$instances['geminiService'] = new GeminiService();
            } catch (\Exception $e) {
                // Gemini service is optional, return null if unavailable
                error_log('GeminiService initialization failed: ' . $e->getMessage());
                return null;
            }
        }
        return self::$instances['geminiService'];
    }
    
    /**
     * Get SEO Content Service
     * @return SEOContentService
     */
    public static function getSEOContentService() {
        if (!isset(self::$instances['seoContentService'])) {
            try {
                $geminiService = self::getGeminiService();
                self::$instances['seoContentService'] = new SEOContentService($geminiService);
            } catch (\Exception $e) {
                error_log('SEOContentService initialization failed: ' . $e->getMessage());
                return null;
            }
        }
        return self::$instances['seoContentService'];
    }
    
    /**
     * Get Blog Content Generator Service
     * @return \App\Services\BlogContentGeneratorService
     */
    public static function getBlogContentGeneratorService() {
        if (!isset(self::$instances['blogContentGeneratorService'])) {
            try {
                self::$instances['blogContentGeneratorService'] = new \App\Services\BlogContentGeneratorService();
            } catch (\Exception $e) {
                error_log('BlogContentGeneratorService initialization failed: ' . $e->getMessage());
                return null;
            }
        }
        return self::$instances['blogContentGeneratorService'];
    }
    
    /**
     * Get Image Generation Service
     * @return ImageGenerationService
     */
    public static function getImageGenerationService() {
        if (!isset(self::$instances['imageGenerationService'])) {
            try {
                self::$instances['imageGenerationService'] = new ImageGenerationService();
            } catch (\Exception $e) {
                // Image generation service is optional, return null if unavailable
                error_log('ImageGenerationService initialization failed: ' . $e->getMessage());
                return null;
            }
        }
        return self::$instances['imageGenerationService'];
    }
    
    /**
     * Get AI Service (merkezi AI yönetimi)
     * @return string AIService class name (static methods kullanılıyor)
     */
    public static function getAIService() {
        // AIService static metodlar kullanıyor, instance döndürmeye gerek yok
        // Ama interface uyumluluğu için class döndürüyoruz
        return AIService::class;
    }

    // Generic repository getter (fallback)
    public static function getRoleRepository() {
        if (!isset(self::$instances['roleRepository'])) {
            self::$instances['roleRepository'] = new RoleRepository(self::getDatabase());
        }
        return self::$instances['roleRepository'];
    }

    public static function getRoleService() {
        if (!isset(self::$instances['roleService'])) {
            self::$instances['roleService'] = new RoleService(self::getRoleRepository());
        }
        return self::$instances['roleService'];
    }
    
    public static function getPermissionModel() {
        if (!isset(self::$instances['permissionModel'])) {
            require_once __DIR__ . '/../models/SystemPermission.php';
            self::$instances['permissionModel'] = new \App\Models\SystemPermission(self::getDatabase());
        }
        return self::$instances['permissionModel'];
    }

    public static function getPermissionService() {
        if (!isset(self::$instances['permissionService'])) {
            require_once __DIR__ . '/../services/PermissionService.php';
            require_once __DIR__ . '/../repositories/SystemPermissionRepository.php';
            self::$instances['permissionService'] = new \App\Services\PermissionService(new \App\Repositories\SystemPermissionRepository(self::getDatabase()));
        }
        return self::$instances['permissionService'];
    }
    
    public static function getConstantsRepository() {
        if (!isset(self::$instances['constantsRepository'])) {
            self::$instances['constantsRepository'] = new ConstantsRepository(self::getDatabase());
        }
        return self::$instances['constantsRepository'];
    }

    public static function getConstantsService() {
        if (!isset(self::$instances['constantsService'])) {
            self::$instances['constantsService'] = new ConstantsService(self::getConstantsRepository());
        }
        return self::$instances['constantsService'];
    }

    public static function getAuthenticationService() {
        if (!isset(self::$instances['authenticationService'])) {
            self::$instances['authenticationService'] = new AuthenticationService();
        }
        return self::$instances['authenticationService'];
    }

    public static function getFilterService() {
        if (!isset(self::$instances['filterService'])) {
            self::$instances['filterService'] = new FilterService();
        }
        return self::$instances['filterService'];
    }

    public static function getEmailService() {
        if (!isset(self::$instances['emailService'])) {
            self::$instances['emailService'] = new EmailService();
        }
        return self::$instances['emailService'];
    }

    public static function getQueueService() {
        if (!isset(self::$instances['queueService'])) {
            require_once __DIR__ . '/../services/QueueService.php';
            self::$instances['queueService'] = new QueueService();
        }
        return self::$instances['queueService'];
    }

    public static function getQueueNotificationService() {
        if (!isset(self::$instances['queueNotificationService'])) {
            require_once __DIR__ . '/../services/QueueNotificationService.php';
            self::$instances['queueNotificationService'] = new QueueNotificationService();
        }
        return self::$instances['queueNotificationService'];
    }

    public static function getStockMovementRepository() {
        if (!isset(self::$instances['stockMovementRepository'])) {
            self::$instances['stockMovementRepository'] = new StockMovementRepository(self::getDatabase());
        }
        return self::$instances['stockMovementRepository'];
    }

    public static function getStockMovementService() {
        if (!isset(self::$instances['stockMovementService'])) {
            self::$instances['stockMovementService'] = new StockMovementService(
                self::getStockMovementRepository(),
                self::getIngredientRepository(),
                self::getConstantsRepository()
            );
        }
        return self::$instances['stockMovementService'];
    }

    public static function getStockLocationRepository() {
        if (!isset(self::$instances['stockLocationRepository'])) {
            self::$instances['stockLocationRepository'] = new StockLocationRepository(self::getDatabase());
        }
        return self::$instances['stockLocationRepository'];
    }

    public static function getStockLocationService() {
        if (!isset(self::$instances['stockLocationService'])) {
            self::$instances['stockLocationService'] = new StockLocationService(self::getStockLocationRepository());
        }
        return self::$instances['stockLocationService'];
    }

    public static function getReceiptRepository() {
        if (!isset(self::$instances['receiptRepository'])) {
            self::$instances['receiptRepository'] = new ReceiptRepository(self::getDatabase());
        }
        return self::$instances['receiptRepository'];
    }

    public static function getReceiptService() {
        if (!isset(self::$instances['receiptService'])) {
            self::$instances['receiptService'] = new ReceiptService(self::getReceiptRepository());
        }
        return self::$instances['receiptService'];
    }

    public static function getReceiptTemplateRepository() {
        if (!isset(self::$instances['receiptTemplateRepository'])) {
            self::$instances['receiptTemplateRepository'] = new ReceiptTemplateRepository(self::getDatabase());
        }
        return self::$instances['receiptTemplateRepository'];
    }

    public static function getReceiptTemplateService() {
        if (!isset(self::$instances['receiptTemplateService'])) {
            self::$instances['receiptTemplateService'] = new ReceiptTemplateService(self::getReceiptTemplateRepository());
        }
        return self::$instances['receiptTemplateService'];
    }

    public static function getBusinessSettingsRepository() {
        if (!isset(self::$instances['businessSettingsRepository'])) {
            self::$instances['businessSettingsRepository'] = new BusinessSettingsRepository(self::getDatabase());
        }
        return self::$instances['businessSettingsRepository'];
    }

    public static function getBusinessSettingsService() {
        if (!isset(self::$instances['businessSettingsService'])) {
            self::$instances['businessSettingsService'] = new BusinessSettingsService(self::getBusinessSettingsRepository());
        }
        return self::$instances['businessSettingsService'];
    }
    
    public static function getBusinessService() {
        if (!isset(self::$instances['businessService'])) {
            require_once __DIR__ . '/../services/BusinessService.php';
            self::$instances['businessService'] = new BusinessService(self::getCustomerRepository());
        }
        return self::$instances['businessService'];
    }

    public static function getReceiptTemplateLayoutRepository() {
        if (!isset(self::$instances['receiptTemplateLayoutRepository'])) {
            self::$instances['receiptTemplateLayoutRepository'] = new ReceiptTemplateLayoutRepository(self::getDatabase());
        }
        return self::$instances['receiptTemplateLayoutRepository'];
    }

    public static function getReceiptTemplateDesignService() {
        if (!isset(self::$instances['receiptTemplateDesignService'])) {
            self::$instances['receiptTemplateDesignService'] = new ReceiptTemplateDesignService(self::getReceiptTemplateLayoutRepository());
        }
        return self::$instances['receiptTemplateDesignService'];
    }

    public static function getPrinterRepository() {
        if (!isset(self::$instances['printerRepository'])) {
            self::$instances['printerRepository'] = new PrinterRepository(self::getDatabase());
        }
        return self::$instances['printerRepository'];
    }

    public static function getPrinterService() {
        if (!isset(self::$instances['printerService'])) {
            self::$instances['printerService'] = new PrinterService(self::getPrinterRepository());
        }
        return self::$instances['printerService'];
    }

    public static function getReceiptPrintQueueRepository() {
        if (!isset(self::$instances['receiptPrintQueueRepository'])) {
            self::$instances['receiptPrintQueueRepository'] = new ReceiptPrintQueueRepository(self::getDatabase());
        }
        return self::$instances['receiptPrintQueueRepository'];
    }

    public static function getPrinterBridgeRepository() {
        if (!isset(self::$instances['printerBridgeRepository'])) {
            self::$instances['printerBridgeRepository'] = new \App\Repositories\PrinterBridgeRepository(self::getDatabase());
        }
        return self::$instances['printerBridgeRepository'];
    }

    public static function getPrinterBridgeService() {
        if (!isset(self::$instances['printerBridgeService'])) {
            self::$instances['printerBridgeService'] = new \App\Services\PrinterBridgeService(self::getPrinterBridgeRepository());
        }
        return self::$instances['printerBridgeService'];
    }

    public static function getZonePrinterMappingRepository() {
        if (!isset(self::$instances['zonePrinterMappingRepository'])) {
            self::$instances['zonePrinterMappingRepository'] = new \App\Repositories\ZonePrinterMappingRepository(self::getDatabase());
        }
        return self::$instances['zonePrinterMappingRepository'];
    }

    public static function getZonePrinterMappingService() {
        if (!isset(self::$instances['zonePrinterMappingService'])) {
            self::$instances['zonePrinterMappingService'] = new \App\Services\ZonePrinterMappingService(self::getZonePrinterMappingRepository());
        }
        return self::$instances['zonePrinterMappingService'];
    }

    public static function getPreparationScreenPrinterRepository() {
        if (!isset(self::$instances['preparationScreenPrinterRepository'])) {
            require_once __DIR__ . '/../repositories/PreparationScreenPrinterRepository.php';
            self::$instances['preparationScreenPrinterRepository'] = new \App\Repositories\PreparationScreenPrinterRepository(self::getDatabase());
        }
        return self::$instances['preparationScreenPrinterRepository'];
    }

    public static function getPreparationScreenPrinterService() {
        if (!isset(self::$instances['preparationScreenPrinterService'])) {
            require_once __DIR__ . '/../services/PreparationScreenPrinterService.php';
            self::$instances['preparationScreenPrinterService'] = new \App\Services\PreparationScreenPrinterService(self::getPreparationScreenPrinterRepository());
        }
        return self::$instances['preparationScreenPrinterService'];
    }

    public static function getSessionService() {
        if (!isset(self::$instances['sessionService'])) {
            self::$instances['sessionService'] = SessionService::getInstance();
        }
        return self::$instances['sessionService'];
    }

    public static function getReportsRepository() {
        if (!isset(self::$instances['reportsRepository'])) {
            self::$instances['reportsRepository'] = new ReportsRepository(self::getDatabase());
        }
        return self::$instances['reportsRepository'];
    }

    public static function getReportsService() {
        if (!isset(self::$instances['reportsService'])) {
            self::$instances['reportsService'] = new ReportsService(self::getReportsRepository());
        }
        return self::$instances['reportsService'];
    }
    
    public static function getExportService() {
        if (!isset(self::$instances['exportService'])) {
            self::$instances['exportService'] = new ExportService();
        }
        return self::$instances['exportService'];
    }

    public static function getZReportService() {
        if (!isset(self::$instances['zReportService'])) {
            self::$instances['zReportService'] = new ZReportService();
        }
        return self::$instances['zReportService'];
    }
    
    public static function getOrderPrintService() {
        if (!isset(self::$instances['orderPrintService'])) {
            self::$instances['orderPrintService'] = new OrderPrintService();
        }
        return self::$instances['orderPrintService'];
    }

    public static function getLeaveTypeRepository() {
        if (!isset(self::$instances['leaveTypeRepository'])) {
            self::$instances['leaveTypeRepository'] = new LeaveTypeRepository(self::getDatabase());
        }
        return self::$instances['leaveTypeRepository'];
    }

    public static function getLeaveTypeService() {
        if (!isset(self::$instances['leaveTypeService'])) {
            self::$instances['leaveTypeService'] = new LeaveTypeService(self::getLeaveTypeRepository());
        }
        return self::$instances['leaveTypeService'];
    }

    public static function getLeaveRepository() {
        if (!isset(self::$instances['leaveRepository'])) {
            self::$instances['leaveRepository'] = new LeaveRepository(self::getDatabase());
        }
        return self::$instances['leaveRepository'];
    }

    public static function getLeaveService() {
        if (!isset(self::$instances['leaveService'])) {
            self::$instances['leaveService'] = new LeaveService(self::getLeaveRepository());
        }
        return self::$instances['leaveService'];
    }

    public static function getMedicalReportRepository() {
        if (!isset(self::$instances['medicalReportRepository'])) {
            self::$instances['medicalReportRepository'] = new MedicalReportRepository(self::getDatabase());
        }
        return self::$instances['medicalReportRepository'];
    }

    public static function getMedicalReportService() {
        if (!isset(self::$instances['medicalReportService'])) {
            self::$instances['medicalReportService'] = new MedicalReportService(self::getMedicalReportRepository());
        }
        return self::$instances['medicalReportService'];
    }

    public static function getPersonnelService() {
        if (!isset(self::$instances['personnelService'])) {
            self::$instances['personnelService'] = new PersonnelService(
                self::getUserRepository(),
                self::getLeaveRepository(),
                self::getMedicalReportRepository()
            );
        }
        return self::$instances['personnelService'];
    }

    public static function getUser2FARepository() {
        if (!isset(self::$instances['user2FARepository'])) {
            self::$instances['user2FARepository'] = new User2FARepository(self::getDatabase());
        }
        return self::$instances['user2FARepository'];
    }

    public static function getUser2FACodeRepository() {
        if (!isset(self::$instances['user2FACodeRepository'])) {
            self::$instances['user2FACodeRepository'] = new User2FACodeRepository(self::getDatabase());
        }
        return self::$instances['user2FACodeRepository'];
    }

    public static function getSMSService() {
        if (!isset(self::$instances['smsService'])) {
            self::$instances['smsService'] = new SMSService();
        }
        return self::$instances['smsService'];
    }

    public static function getFreeTranslationService() {
        if (!isset(self::$instances['freeTranslationService'])) {
            self::$instances['freeTranslationService'] = new FreeTranslationService();
        }
        return self::$instances['freeTranslationService'];
    }

    public static function getEmail2FAService() {
        if (!isset(self::$instances['email2FAService'])) {
            self::$instances['email2FAService'] = new Email2FAService();
        }
        return self::$instances['email2FAService'];
    }

    public static function getSMS2FAService() {
        if (!isset(self::$instances['sms2FAService'])) {
            self::$instances['sms2FAService'] = new SMS2FAService();
        }
        return self::$instances['sms2FAService'];
    }

    public static function getTwoFactorAuthService() {
        if (!isset(self::$instances['twoFactorAuthService'])) {
            self::$instances['twoFactorAuthService'] = new TwoFactorAuthService(
                self::getUser2FARepository(),
                self::getUser2FACodeRepository(),
                self::getUserRepository()
            );
        }
        return self::$instances['twoFactorAuthService'];
    }

    // NEW SERVICES ADDED
    public static function getValidationService() {
        if (!isset(self::$instances['validationService'])) {
            self::$instances['validationService'] = new ValidationService();
        }
        return self::$instances['validationService'];
    }

    public static function getNavigationService() {
        if (!isset(self::$instances['navigationService'])) {
            self::$instances['navigationService'] = new NavigationService();
        }
        return self::$instances['navigationService'];
    }

    public static function getCacheService() {
        if (!isset(self::$instances['cacheService'])) {
            // Load cache configuration
            $cacheConfig = require __DIR__ . '/../config/cache.php';
            $driver = $cacheConfig['driver'] ?? 'file';
            
            // Instantiate appropriate cache driver based on configuration
            if ($driver === 'redis' && extension_loaded('redis')) {
                try {
                    require_once __DIR__ . '/../services/RedisCache.php';
                    self::$instances['cacheService'] = new \App\Services\RedisCache($cacheConfig['redis']);
                } catch (\Exception $e) {
                    // Fallback to file cache if Redis connection fails
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("Redis cache failed, falling back to file cache: " . $e->getMessage());
                    }
                    self::$instances['cacheService'] = new CacheService(
                        $cacheConfig['file']['path'] ?? null,
                        $cacheConfig['file']['ttl'] ?? 3600
                    );
                }
            } else {
                // Use file cache
                self::$instances['cacheService'] = new CacheService(
                    $cacheConfig['file']['path'] ?? null,
                    $cacheConfig['file']['ttl'] ?? 3600
                );
            }
        }
        return self::$instances['cacheService'];
    }

    public static function getWebSocketService() {
        if (!isset(self::$instances['webSocketService'])) {
            self::$instances['webSocketService'] = new WebSocketService();
        }
        return self::$instances['webSocketService'];
    }

    public static function getLoggerService() {
        if (!isset(self::$instances['loggerService'])) {
            self::$instances['loggerService'] = new \App\Services\LoggerService();
        }
        return self::$instances['loggerService'];
    }
    
    /**
     * Get Redis Session Handler instance
     * @return \App\Core\Session\RedisSessionHandler|null
     */
    public static function getRedisSessionHandler() {
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            return null;
        }
        
        // Check if Redis session is enabled
        $useRedisSession = $_ENV['SESSION_DRIVER'] ?? 'php';
        if ($useRedisSession !== 'redis') {
            return null;
        }
        
        if (!isset(self::$instances['redisSessionHandler'])) {
            try {
                $cacheConfig = require __DIR__ . '/../config/cache.php';
                
                if ($cacheConfig['driver'] !== 'redis') {
                    return null;
                }
                
                $sessionConfig = [
                    'host' => $cacheConfig['redis']['host'],
                    'port' => $cacheConfig['redis']['port'],
                    'password' => $cacheConfig['redis']['password'],
                    'database' => $_ENV['REDIS_SESSION_DATABASE'] ?? 1,
                    'timeout' => $cacheConfig['redis']['timeout'],
                    'prefix' => $_ENV['SESSION_PREFIX'] ?? 'session:',
                    'ttl' => $cacheConfig['session']['ttl'] ?? 28800,
                ];
                
                require_once __DIR__ . '/Session/RedisSessionHandler.php';
                self::$instances['redisSessionHandler'] = new \App\Core\Session\RedisSessionHandler($sessionConfig);
            } catch (\Exception $e) {
                // Log error but return null (fallback to PHP session)
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Failed to create Redis session handler: " . $e->getMessage());
                }
                return null;
            }
        }
        
        return self::$instances['redisSessionHandler'];
    }

    public static function getSEOService() {
        if (!isset(self::$instances['seoService'])) {
            self::$instances['seoService'] = new \App\Services\SEOService();
        }
        return self::$instances['seoService'];
    }

    public static function getSearchService() {
        if (!isset(self::$instances['searchService'])) {
            self::$instances['searchService'] = new \App\Services\SearchService();
        }
        return self::$instances['searchService'];
    }

    public static function getAuthHelperService() {
        if (!isset(self::$instances['authHelperService'])) {
            self::$instances['authHelperService'] = new \App\Services\AuthHelperService();
        }
        return self::$instances['authHelperService'];
    }

    public static function getUrlService() {
        if (!isset(self::$instances['urlService'])) {
            self::$instances['urlService'] = new \App\Services\UrlService();
        }
        return self::$instances['urlService'];
    }

    public static function getWhatsAppService() {
        if (!isset(self::$instances['whatsAppService'])) {
            self::$instances['whatsAppService'] = new \App\Services\WhatsAppService();
        }
        return self::$instances['whatsAppService'];
    }

    public static function getLabelService() {
        if (!isset(self::$instances['labelService'])) {
            self::$instances['labelService'] = new \App\Services\LabelService();
        }
        return self::$instances['labelService'];
    }

    public static function getFormattingService() {
        if (!isset(self::$instances['formattingService'])) {
            self::$instances['formattingService'] = new \App\Services\FormattingService();
        }
        return self::$instances['formattingService'];
    }
    
    public static function getSubdomainService() {
        if (!isset(self::$instances['subdomainService'])) {
            self::$instances['subdomainService'] = new SubdomainService();
        }
        return self::$instances['subdomainService'];
    }

    public static function getDesignSystem() {
        if (!isset(self::$instances['designSystem'])) {
            self::$instances['designSystem'] = new \App\Services\DesignSystem();
        }
        return self::$instances['designSystem'];
    }

    public static function getAssetManager() {
        if (!isset(self::$instances['assetManager'])) {
            self::$instances['assetManager'] = \App\Services\AssetManager::getInstance();
        }
        return self::$instances['assetManager'];
    }

    public static function getAppConfig() {
        if (!isset(self::$instances['appConfig'])) {
            self::$instances['appConfig'] = \App\Services\AppConfig::getInstance();
        }
        return self::$instances['appConfig'];
    }

    public static function getThemeService() {
        if (!isset(self::$instances['themeService'])) {
            self::$instances['themeService'] = \App\Services\ThemeService::getInstance();
        }
        return self::$instances['themeService'];
    }

    public static function getTranslationService() {
        if (!isset(self::$instances['translationService'])) {
            self::$instances['translationService'] = \App\Services\TranslationService::getInstance();
        }
        return self::$instances['translationService'];
    }

    public static function getMediaFileRepository() {
        if (!isset(self::$instances['mediaFileRepository'])) {
            self::$instances['mediaFileRepository'] = new MediaFileRepository(self::getDatabase());
        }
        return self::$instances['mediaFileRepository'];
    }

    public static function getImageService() {
        if (!isset(self::$instances['imageService'])) {
            self::$instances['imageService'] = new ImageService(self::getMediaFileRepository());
        }
        return self::$instances['imageService'];
    }
    
    /**
     * Get Blog Post Repository
     * @return BlogPostRepository
     */
    public static function getBlogPostRepository() {
        if (!isset(self::$instances['blogPostRepository'])) {
            self::$instances['blogPostRepository'] = new BlogPostRepository(self::getDatabase());
        }
        return self::$instances['blogPostRepository'];
    }
    
    /**
     * Get Blog Category Repository
     * @return BlogCategoryRepository
     */
    public static function getBlogCategoryRepository() {
        if (!isset(self::$instances['blogCategoryRepository'])) {
            self::$instances['blogCategoryRepository'] = new BlogCategoryRepository(self::getDatabase());
        }
        return self::$instances['blogCategoryRepository'];
    }
    
    /**
     * Get Blog Service
     * @return \App\Services\BlogService
     */
    public static function getBlogService() {
        if (!isset(self::$instances['blogService'])) {
            $postRepo = self::getBlogPostRepository();
            $catRepo = self::getBlogCategoryRepository();
            self::$instances['blogService'] = new \App\Services\BlogService($postRepo, $catRepo);
        }
        return self::$instances['blogService'];
    }

    // =========================================================================
    // Phase 2 — Stock / HR / Fire infrastructure
    // =========================================================================

    /** @return \App\Services\PushService */
    public static function getPushService() {
        if (!isset(self::$instances['pushService'])) {
            self::$instances['pushService'] = new \App\Services\PushService();
        }
        return self::$instances['pushService'];
    }

    /** @return \App\Services\NotificationDispatcher */
    public static function getNotificationDispatcher() {
        if (!isset(self::$instances['notificationDispatcher'])) {
            self::$instances['notificationDispatcher'] = new \App\Services\NotificationDispatcher(
                self::getNotificationService(),
                self::getEmailService(),
                self::getWhatsAppService(),
                self::getPushService()
            );
        }
        return self::$instances['notificationDispatcher'];
    }

    /** @return \App\Repositories\StockCategoryRepository */
    public static function getStockCategoryRepository() {
        if (!isset(self::$instances['stockCategoryRepository'])) {
            self::$instances['stockCategoryRepository'] = new \App\Repositories\StockCategoryRepository(self::getDatabase());
        }
        return self::$instances['stockCategoryRepository'];
    }

    /** @return \App\Services\StockCategoryService */
    public static function getStockCategoryService() {
        if (!isset(self::$instances['stockCategoryService'])) {
            self::$instances['stockCategoryService'] = new \App\Services\StockCategoryService(
                self::getStockCategoryRepository()
            );
        }
        return self::$instances['stockCategoryService'];
    }

    /** @return \App\Repositories\StockUnitRepository */
    public static function getStockUnitRepository() {
        if (!isset(self::$instances['stockUnitRepository'])) {
            self::$instances['stockUnitRepository'] = new \App\Repositories\StockUnitRepository(self::getDatabase());
        }
        return self::$instances['stockUnitRepository'];
    }

    /** @return \App\Services\StockUnitService */
    public static function getStockUnitService() {
        if (!isset(self::$instances['stockUnitService'])) {
            self::$instances['stockUnitService'] = new \App\Services\StockUnitService(
                self::getStockUnitRepository()
            );
        }
        return self::$instances['stockUnitService'];
    }

    /** @return \App\Repositories\PurchaseReceiptRepository */
    public static function getPurchaseReceiptRepository() {
        if (!isset(self::$instances['purchaseReceiptRepository'])) {
            self::$instances['purchaseReceiptRepository'] = new \App\Repositories\PurchaseReceiptRepository(self::getDatabase());
        }
        return self::$instances['purchaseReceiptRepository'];
    }

    /** @return \App\Repositories\PurchaseReceiptItemRepository */
    public static function getPurchaseReceiptItemRepository() {
        if (!isset(self::$instances['purchaseReceiptItemRepository'])) {
            self::$instances['purchaseReceiptItemRepository'] = new \App\Repositories\PurchaseReceiptItemRepository(self::getDatabase());
        }
        return self::$instances['purchaseReceiptItemRepository'];
    }

    /** @return \App\Services\PurchaseReceiptService */
    public static function getPurchaseReceiptService() {
        if (!isset(self::$instances['purchaseReceiptService'])) {
            self::$instances['purchaseReceiptService'] = new \App\Services\PurchaseReceiptService(
                self::getPurchaseReceiptRepository(),
                self::getPurchaseReceiptItemRepository(),
                self::getIngredientRepository(),
                self::getStockMovementService()
            );
        }
        return self::$instances['purchaseReceiptService'];
    }

    /** @return \App\Services\SupplierAnalyticsService */
    public static function getSupplierAnalyticsService() {
        if (!isset(self::$instances['supplierAnalyticsService'])) {
            self::$instances['supplierAnalyticsService'] = new \App\Services\SupplierAnalyticsService(
                self::getDatabase()
            );
        }
        return self::$instances['supplierAnalyticsService'];
    }

    /** @return \App\Services\LowStockDispatcher */
    public static function getLowStockDispatcher() {
        if (!isset(self::$instances['lowStockDispatcher'])) {
            self::$instances['lowStockDispatcher'] = new \App\Services\LowStockDispatcher(
                self::getDatabase(),
                self::getNotificationDispatcher(),
                self::getIngredientRepository()
            );
        }
        return self::$instances['lowStockDispatcher'];
    }

    /** @return \App\Services\WeeklyScheduleNotifier */
    public static function getWeeklyScheduleNotifier() {
        if (!isset(self::$instances['weeklyScheduleNotifier'])) {
            self::$instances['weeklyScheduleNotifier'] = new \App\Services\WeeklyScheduleNotifier(
                self::getShiftScheduleService(),
                self::getNotificationDispatcher()
            );
        }
        return self::$instances['weeklyScheduleNotifier'];
    }

    public static function getRepository($repositoryName) {
        $className = "App\\Repositories\\{$repositoryName}Repository";
        $key = strtolower($repositoryName) . 'Repository';

        if (!isset(self::$instances[$key])) {
            $db = self::getDatabase();
            if (class_exists($className)) {
                self::$instances[$key] = new $className($db);
            } else {
                throw new \Exception("Repository {$className} not found");
            }
        }
        return self::$instances[$key];
    }

    // Generic service getter (fallback)
    public static function getService($serviceName) {
        $key = strtolower($serviceName) . 'Service';

        // Use specific getters if available
        $methodName = 'get' . ucfirst($serviceName) . 'Service';
        if (method_exists(self::class, $methodName)) {
            return self::$methodName();
        }

        // Special case for AuthenticationService since it doesn't follow the standard pattern
        if ($serviceName === 'Authentication') {
            return self::getAuthenticationService();
        }

        // Fallback to generic creation
        if (!isset(self::$instances[$key])) {
            $className = "App\\Services\\{$serviceName}Service";
            $repoName = $serviceName;

            if (class_exists($className)) {
                $repository = self::getRepository($repoName);
                self::$instances[$key] = new $className($repository);
            } else {
                throw new \Exception("Service {$className} not found");
            }
        }
        return self::$instances[$key];
    }
}