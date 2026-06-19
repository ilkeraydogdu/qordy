<?php
namespace App\Core\Helpers;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;

/**
 * QR Code Helper.
 *
 * Tüm QR üretimi endroid/qr-code üzerinden yerel olarak yapılır; dış servis
 * (api.qrserver.com vb.) kullanılmaz. Controller tarafı PNG byte'ını
 * doğrudan sayfa içinde data URI olarak gömmeyi veya `/qr?data=...`
 * route'una yönlendirmeyi tercih edebilir.
 */
class QRCodeHelper {

    /** Genel QR PNG üretici. İşlem sonucunda binary PNG string döner. */
    public static function generatePng(string $data, int $size = 500, int $margin = 10): string {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: max(64, min(2000, $size)),
            margin: max(0, min(64, $margin)),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        return $builder->build()->getString();
    }

    /** Data URI (base64) — view'da <img src=".."> için kullanışlı. */
    public static function generateDataUri(string $data, int $size = 500, int $margin = 10): string {
        return 'data:image/png;base64,' . base64_encode(self::generatePng($data, $size, $margin));
    }

    /**
     * Masa QR URL'i. Artık üçüncü parti servis URL'i değil, yerel
     * `/qr?data=<url>&size=<n>` endpoint'idir (QRCodeController::render).
     */
    public static function generateQRCodeUrl(string $tableId, int $size = 500): string {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $tableUrl = rtrim(BASE_URL, '/') . '/t/' . rawurlencode($tableId);
        return rtrim(BASE_URL, '/') . '/qr?size=' . (int)$size . '&data=' . urlencode($tableUrl);
    }

    public static function generateTableUrl(string $tableId): string {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        return rtrim(BASE_URL, '/') . '/t/' . rawurlencode($tableId);
    }
}
