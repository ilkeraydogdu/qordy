/// API endpoint sabitleri ve ortam konfigürasyonu.
///
/// Tüm URL'ler burada toplanır; magic string YOK.
/// Subdomain tabanlı routing (qordy.com/{subdomain}/api/mobile/...).
library;

/// Uygulama ortamı.
enum AppEnv { dev, staging, production }

class ApiConstants {
 ApiConstants._();

 /// Aktif ortam — release build'de [AppEnv.production] kullanılır.
 static const AppEnv environment = AppEnv.production;

 /// Production base URL.
 static const String productionBaseUrl = 'https://qordy.com';

 /// Dev/staging — gerçek cihazda LAN IP gerekir.
 static const String devBaseUrl = 'http://10.0.2.2:8080';

 /// WSS endpoint (WebSocket).
 static const String wsBaseUrl = 'wss://qordy.com:8080';

 /// API kökü.
 static const String apiPrefix = '/api/mobile';

 /// Auth endpoints.
 static const String login = '$apiPrefix/manager/login';
 static const String refreshToken = '$apiPrefix/refresh-token';
 static const String verifyToken = '$apiPrefix/verify-token';
 static const String logout = '$apiPrefix/logout';
 static const String validateSubdomain = '$apiPrefix/validate-subdomain';

 /// 2FA endpoints.
 static const String twoFactorVerify = '$apiPrefix/auth/2fa/verify';
 static const String twoFactorSend = '$apiPrefix/auth/2fa/send';

 /// Dashboard.
 static const String staffDashboard = '$apiPrefix/staff/dashboard';

 /// Orders.
 static const String orders = '$apiPrefix/orders';
 static const String orderStatus = '$apiPrefix/orders/status';
 static const String kitchenOrders = '$apiPrefix/kitchen/orders';

 /// Tables.
 static const String tables = '$apiPrefix/tables';

 /// Menu.
 static const String menu = '$apiPrefix/menu';
 static const String menuItemIngredients = '$apiPrefix/menu/item-ingredients';

 /// Notifications.
 static const String notifications = '$apiPrefix/notifications';
 static const String notificationsRead = '$apiPrefix/notifications/read';
 static const String notificationsReadAll = '$apiPrefix/notifications/read-all';
 static const String notificationsRegisterToken = '$apiPrefix/notifications/register-token';

 /// Network timeouts (saniye).
 static const int connectTimeoutSec = 15;
 static const int receiveTimeoutSec = 30;
 static const int sendTimeoutSec = 30;

 /// Rate limit (client-side token bucket).
 static const int loginMaxPerMinute = 5;
 static const int defaultMaxPerMinute = 60;

 /// Base URL [subdomain] placeholder'lı: https://qordy.com/{sub}/api/mobile
 static String tenantBaseUrl(String subdomain) =>
 '$productionBaseUrl/$subdomain';

 /// WSS URL [subdomain] placeholder'lı.
 static String tenantWsUrl(String subdomain) =>
 '$wsBaseUrl/$subdomain';
}
