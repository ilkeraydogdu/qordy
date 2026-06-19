<?php
namespace App\Services;

/**
 * WebSocketBroadcaster
 *
 * TASARIM NOTU (Nisan 2026 revizyonu):
 * Eski sürüm WS sunucusuna `fsockopen` + `fwrite` ile ham satır yazmaya
 * çalışıyordu. Bu HİÇBİR ZAMAN çalışmadı çünkü Ratchet WS sunucusu HTTP
 * upgrade + binary frame bekliyor; ham TCP satırı sessizce çöpe gidiyordu.
 * Ayrıca `init()` hiçbir yerde çağrılmıyordu; yani `broadcast()` doğrudan
 * fallback log yoluna düşüyor, "çalışıyor gibi" görünüyordu.
 *
 * GERÇEK REALTIME YOLU:
 *   - Ratchet sunucusu (app/websocket/server.php) her 5 saniyede bir DB'yi
 *     yokluyor (pollOrderChanges / pollTableChanges / pollNotificationChanges)
 *     ve hash değişimi gördüğünde tenant kanalına yayınlıyor.
 *   - Bu yüzden uygulamanın herhangi bir yerinden yayın için YAPMASI
 *     GEREKEN TEK ŞEY DB'yi doğru güncellemektir (orders / tables /
 *     notifications). Ekstra bir push'a gerek yok.
 *
 * Bu sınıf artık:
 *   - Tüketiciler (OrderService, TableService) kırılmasın diye aynı
 *     public API'yı koruyor,
 *   - Her çağrıyı yapısal log'a düşürüyor (gözlemlenebilirlik),
 *   - Gelecekte Redis pub/sub veya internal HTTP-notify devreye alınırsa
 *     tek noktadan upgrade edilebilecek biçimde soyutlanmış.
 */
class WebSocketBroadcaster {

    /**
     * Geriye dönük uyumluluk için korunur. Artık sessizce no-op.
     * Eskiden fsockopen yapıyordu; bu çağrı Ratchet üzerinde işe yaramıyordu.
     */
    public static function init(string $host = '127.0.0.1', int $port = 8080): void {
        // no-op: DB polling üzerinden yayın yapılıyor.
    }

    /**
     * Broadcast message. Gerçek yayın Ratchet DB-polling tarafından yapılır;
     * burada sadece gözlemlenebilirlik için log tutulur.
     */
    public static function broadcast(string $event, array $data, ?string $channel = null): void {
        if (class_exists('\\App\\Core\\Logger')) {
            try {
                \App\Core\Logger::info('ws.broadcast.hint', [
                    'event' => $event,
                    'channel' => $channel,
                    'keys' => array_slice(array_keys($data), 0, 8),
                ]);
            } catch (\Throwable $e) { /* log altyapısı hazır değilse sessiz düş */ }
        }
    }

    public static function broadcastOrder(string $event, array $orderData): void {
        self::broadcast("order.{$event}", $orderData, 'orders');
    }

    public static function broadcastTable(string $event, array $tableData): void {
        self::broadcast("table.{$event}", $tableData, 'tables');
    }

    public static function broadcastNotification(array $notificationData): void {
        self::broadcast('notification.new', $notificationData, 'notifications');
    }

    public static function close(): void {
        // no-op
    }
}
