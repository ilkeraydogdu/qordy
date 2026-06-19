<?php
/**
 * Qordy Mobile API Routes Updater
 * Mevcut MobileAPIController route'larını yeni wrapper controller'lara yönlendirir
 */

declare(strict_types=1);

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$routeFile = "{$appPath}/app/config/routes.php";
$content = file_get_contents($routeFile);

// Controller method eşleştirmeleri
$mapping = [
 'API/MobileAPIController@' => [
 'login' => 'API/Mobile/MobileAuthController@login',
 'refreshToken' => 'API/Mobile/AuthController@refreshToken',
 'logout' => 'API/Mobile/AuthController@logout',
 'getOrders' => 'API/Mobile/OrderController@getOrders',
 'createMobileOrder' => 'API/Mobile/OrderController@createMobileOrder',
 'updateOrderStatus' => 'API/Mobile/OrderController@updateOrderStatus',
 'getNotifications' => 'API/Mobile/NotificationController@getNotifications',
 'markNotificationRead' => 'API/Mobile/NotificationController@markNotificationRead',
 'staffLogin' => 'API/Mobile/AuthController@staffLogin',
 'staffDashboard' => 'API/Mobile/StaffController@staffDashboard',
 'getTables' => 'API/Mobile/TableController@getTables',
 'getMenu' => 'API/OrdersController@menu', // V2 -> yeni controller
 'addMenuItem' => 'API/OrdersController@createItem', // V2
 'updateMenuItem' => 'API/OrdersController@updateItem', // V2
 'deleteMenuItem' => 'API/OrdersController@deleteItem', // V2
 'addCategory' => 'API/OrdersController@createCategory', // V2
 'updateCategory' => 'API/OrdersController@updateCategory', // V2
 'deleteCategory' => 'API/OrdersController@deleteCategory', // V2
 'totpSetup' => 'API/Mobile/AuthController@totpSetup',
 'totpConfirm' => 'API/Mobile/AuthController@totpConfirm',
 'totpDisable' => 'API/Mobile/AuthController@totpDisable',
 'whatsappSetup' => 'API/Mobile/AuthController@whatsappSetup',
 'whatsappConfirm' => 'API/Mobile/AuthController@whatsappConfirm',
 'whatsappDisable' => 'API/Mobile/AuthController@whatsappDisable',
 ]
];

// Tüm route'ları kontrol et ve güncelle
foreach ($mapping as $oldPrefix => $newTargets) {
 foreach ($newTargets as $oldMethod => $newRoute) {
 // Eski patterni bul ve yeniyle değiştir
 $pattern = "/'{$oldPrefix}{$oldMethod}' => '.*'/";
 $replacement = "'{$oldPrefix}{$oldMethod}' => '{$newRoute}'";
 $content = preg_replace($pattern, $replacement, $content);
 }
}

// Değişiklikleri kaydet
file_put_contents($routeFile, $content);

echo "📱 Mobile API routes updated!\n";
echo "✅ " . count($mapping['API/MobileAPIController@']) . " routes redirected to new controllers\n";
echo "⚠️  Note: New controllers are currently delegate wrappers - full migration pending Q1 2027\n";
