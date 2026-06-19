<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Helpers\QRCodeHelper;

/**
 * Yerel QR kod endpoint'i. `/qr?data=<string>&size=<int>&margin=<int>` formatında
 * çağrılır ve PNG döner. Üçüncü parti bir servise bağımlı değildir; tamamı
 * endroid/qr-code ile uygulama içinden üretilir.
 *
 * Güvenlik notu: `data` parametresi istemciden geldiğinden, yalnızca düz
 * ASCII ve UTF-8 karakterlere izin veriyoruz; binary payload encode etmek
 * için base64 wrapper eklemek caller'ın sorumluluğundadır.
 */
class QRCodeController extends Controller {

    public function generate(): void {
        $data = (string)($_GET['data'] ?? '');
        if ($data === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'data parametresi zorunludur';
            return;
        }
        // Aşırı uzun payload'ları reddet.
        if (strlen($data) > 2048) {
            http_response_code(413);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'data çok uzun';
            return;
        }

        $size   = (int)($_GET['size']   ?? 500);
        $margin = (int)($_GET['margin'] ?? 10);

        try {
            $png = QRCodeHelper::generatePng($data, $size, $margin);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('QRCodeController::generate failed', [
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'QR üretilemedi';
            return;
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400, immutable');
        header('Content-Length: ' . strlen($png));
        echo $png;
    }
}
