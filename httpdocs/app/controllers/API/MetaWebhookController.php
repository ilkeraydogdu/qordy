<?php
namespace App\Controllers\API;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

/**
 * Meta (WhatsApp/Facebook) Webhook Controller
 * 
 * Handles webhook verification and incoming events from Meta platforms:
 * - WhatsApp Business API (messages, status updates)
 * - Messenger (optional)
 * 
 * Meta for Developers webhook setup:
 * - Callback URL: https://yourdomain.com/api/webhook/meta
 * - Verify token: Set in qodmin/settings > Meta API
 */
class MetaWebhookController {
    
    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'GET') {
            $this->verify();
        } elseif ($method === 'POST') {
            $this->receive();
        } else {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method not allowed']);
        }
    }
    
    /**
     * Webhook verification - Meta sends GET with hub.mode, hub.verify_token, hub.challenge
     * We must verify the token and return the challenge to complete setup
     */
    private function verify(): void {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';
        
        if ($mode !== 'subscribe') {
            $this->logMeta('warning', 'MetaWebhook: Invalid hub_mode', ['mode' => $mode]);
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'Invalid hub_mode';
            exit;
        }
        
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $expectedToken = trim($settingsService->getSetting('meta_webhook_verify_token', ''));
        
        if (empty($expectedToken)) {
            $this->logMeta('warning', 'MetaWebhook: Verify token not configured. Visit qodmin/settings > Meta API sekmesine gidin, token otomatik oluşturulacak.');
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Verify token not configured. Visit qodmin/settings > Meta API first.';
            exit;
        }
        
        if (!hash_equals($expectedToken, $token)) {
            $this->logMeta('warning', 'MetaWebhook: Verify token mismatch', [
                'expected_len' => strlen($expectedToken),
                'received_len' => strlen($token)
            ]);
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Verify token mismatch';
            exit;
        }
        
        $this->logMeta('info', 'MetaWebhook: Verification successful');
        header('Content-Type: text/plain');
        header('X-Content-Type-Options: nosniff');
        echo $challenge;
        exit;
    }
    
    /**
     * Receive webhook events - Meta sends POST with JSON body
     * Validates X-Hub-Signature-256 when meta_app_secret is configured
     */
    private function receive(): void {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            http_response_code(400);
            exit;
        }

        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $appSecret = trim($settingsService->getSetting('meta_app_secret', ''));
        if (!empty($appSecret)) {
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
            if (empty($signature)) {
                $this->logMeta('warning', 'MetaWebhook: Missing X-Hub-Signature-256 header');
                http_response_code(403);
                exit;
            }
            $expectedSig = 'sha256=' . hash_hmac('sha256', $input, $appSecret);
            if (!hash_equals($expectedSig, $signature)) {
                $this->logMeta('warning', 'MetaWebhook: Signature verification failed');
                http_response_code(403);
                exit;
            }
        }

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMeta('warning', 'MetaWebhook: Invalid JSON', ['error' => json_last_error_msg()]);
            http_response_code(400);
            exit;
        }

        // Meta webhook: object + entry[] (entry.id = WABA ID for WhatsApp)
        $object = $data['object'] ?? null;
        $entries = $data['entry'] ?? [];

        if ($object === 'whatsapp_business_account' || $object === 'page') {
            $ourWabaId = trim($settingsService->getSetting('meta_whatsapp_business_account_id', ''));
            
            foreach ($entries as $entry) {
                $entryId = (string)($entry['id'] ?? '');
                if ($ourWabaId !== '' && $entryId !== '' && $entryId !== $ourWabaId) {
                    continue; // Farklı WABA - atla
                }
                $this->processEntry($entry, $object);
            }
        }
        
        // Always return 200 to acknowledge receipt (Meta retries on non-2xx)
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
    
    /**
     * Process a single webhook entry
     */
    private function processEntry(array $entry, string $object): void {
        $changes = $entry['changes'] ?? [];
        $id = $entry['id'] ?? '';
        
        foreach ($changes as $change) {
            $field = $change['value'] ?? [];
            
            if ($object === 'whatsapp_business_account') {
                $this->processWhatsAppValue($field);
            } elseif ($object === 'page') {
                $this->processPageValue($field);
            }
        }
        
        $this->logMeta('info', 'MetaWebhook: Processed entry', [
            'object' => $object,
            'entry_id' => $id
        ]);
    }
    
    /**
     * Process WhatsApp Business webhook value
     * Meta Cloud API: value contains messages, statuses, errors, contacts, metadata
     */
    private function processWhatsAppValue(array $value): void {
        $messages = $value['messages'] ?? [];
        $statuses = $value['statuses'] ?? [];
        $errors = $value['errors'] ?? [];
        // `metadata` (display_phone_number + phone_number_id) her mesaj için
        // aynı value içinde geldiği için mesajlara taşıyoruz; böylece
        // handleWhatsAppMessage tenant'ı doğru çözümleyebilir.
        $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
        
        if (!is_array($messages)) {
            $messages = [];
        }
        if (!is_array($statuses)) {
            $statuses = [];
        }
        if (!is_array($errors)) {
            $errors = [];
        }
        
        foreach ($messages as $msg) {
            if (is_array($msg)) {
                if (!isset($msg['metadata'])) {
                    $msg['metadata'] = $metadata;
                }
                $this->handleWhatsAppMessage($msg);
            }
        }
        
        foreach ($statuses as $status) {
            if (is_array($status)) {
                $this->handleWhatsAppStatus($status);
            }
        }
        
        foreach ($errors as $err) {
            $this->logMeta('error', 'MetaWebhook WhatsApp error', is_array($err) ? $err : ['raw' => $err]);
        }
    }
    
    /**
     * Handle incoming WhatsApp message.
     *
     * Eski hâli yalnızca Logger'a bir info kaydı atıyordu (TODO ile bırakılmıştı).
     * Artık:
     *  - Mesajı whatsapp_message_logs tablosuna direction='inbound' olarak kaydediyor
     *    (tablo tarafı whatsapp_inbound_columns.sql migration'ı ile hazırlandı).
     *  - İşletmeye özel "meta_auto_reply_message" tanımlıysa WhatsAppService
     *    üzerinden otomatik cevap gönderiyor.
     *  - Tenant çözümü meta_whatsapp_business_account_id → phone_number_id eşleşmesi
     *    ile yapılır; bulunamazsa mesaj yine loglanır, yalnızca tenant NULL kalır.
     */
    private function handleWhatsAppMessage(array $msg): void {
        $from       = (string)($msg['from'] ?? '');
        $type       = (string)($msg['type'] ?? 'text');
        $metaMsgId  = (string)($msg['id'] ?? '');
        $timestamp  = (string)($msg['timestamp'] ?? '');

        // İçerik özetini tüm mesaj tipleri için güvenli biçimde çıkar.
        $content = '';
        switch ($type) {
            case 'text':
                $content = (string)($msg['text']['body'] ?? '');
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $media = $msg[$type] ?? [];
                $content = '[' . strtoupper($type) . '] '
                    . ($media['caption'] ?? ($media['filename'] ?? ($media['id'] ?? '')));
                break;
            case 'interactive':
                $inter = $msg['interactive'] ?? [];
                $kind = $inter['type'] ?? '';
                if ($kind === 'button_reply') {
                    $content = '[BUTTON] ' . ($inter['button_reply']['title'] ?? '')
                        . ' (' . ($inter['button_reply']['id'] ?? '') . ')';
                } elseif ($kind === 'list_reply') {
                    $content = '[LIST] ' . ($inter['list_reply']['title'] ?? '')
                        . ' (' . ($inter['list_reply']['id'] ?? '') . ')';
                }
                break;
            case 'location':
                $loc = $msg['location'] ?? [];
                $content = '[LOCATION] ' . ($loc['latitude'] ?? '?') . ',' . ($loc['longitude'] ?? '?');
                break;
            case 'contacts':
                $content = '[CONTACT] ' . json_encode($msg['contacts'] ?? [], JSON_UNESCAPED_UNICODE);
                break;
            case 'button':
                $content = '[BUTTON] ' . ($msg['button']['text'] ?? '');
                break;
            case 'reaction':
                $content = '[REACTION] ' . ($msg['reaction']['emoji'] ?? '');
                break;
            default:
                $content = '[' . strtoupper($type) . ']';
        }

        // Tenant çözümü: WABA + phone_number_id (varsa) → business_id
        $tenantId = $this->resolveInboundTenant($msg);

        try {
            $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
            $logService->logMessage([
                'tenant_id'       => $tenantId,
                'message_type'    => in_array($type, ['text','template','marketing','otp','test'], true) ? $type : 'other',
                'direction'       => 'inbound',
                'recipient_phone' => $this->inboundRecipientDisplay($msg),
                'sender_phone'    => $from,
                'message_content' => $content,
                'meta_message_id' => $metaMsgId,
                'status'          => 'received',
                'sent_by'         => 'webhook',
            ]);
        } catch (\Throwable $e) {
            $this->logMeta('error', 'MetaWebhook: Failed to persist inbound message', [
                'error' => $e->getMessage(),
                'from'  => $from,
                'id'    => $metaMsgId,
            ]);
        }

        $this->logMeta('info', 'MetaWebhook: WhatsApp inbound message stored', [
            'from'      => $from,
            'type'      => $type,
            'id'        => $metaMsgId,
            'tenant_id' => $tenantId,
            'length'    => strlen($content),
        ]);

        // Otomatik yanıt: sadece metin mesajlarına ve opt-in varsa gönder.
        if ($type === 'text' && $content !== '' && $tenantId !== null) {
            $this->maybeAutoReply($tenantId, $from, $content);
        }
    }

    /**
     * Gelen mesajın hangi WABA/phone_number_id kombinasyonuna ait olduğunu
     * çözerek tenant_id (business_id) döndürür. Bulunamazsa null.
     */
    private function resolveInboundTenant(array $msg): ?string {
        try {
            $settings = \App\Core\DependencyFactory::getSystemSettingsService();
            $ourWaba  = trim((string)$settings->getSetting('meta_whatsapp_business_account_id', ''));
            $ourPhone = trim((string)$settings->getSetting('meta_phone_number_id', ''));
            // Çok-kiracılı WhatsApp yapılandırması yoksa global business_id'ye düşer.
            $fallback = trim((string)$settings->getSetting('meta_default_business_id', ''));

            // Mesaj seviyesinde `msg['metadata']['phone_number_id']` Meta tarafından
            // processWhatsAppValue içinde sıyrıldığı için, doğrudan yakalayamayız;
            // yine de processWhatsAppValue buraya $msg yerine $msg içinde taşırsa
            // anlamlı kalması için kontrol ediyoruz.
            $msgPhoneId = (string)($msg['metadata']['phone_number_id'] ?? '');
            if ($ourPhone !== '' && $msgPhoneId !== '' && $msgPhoneId !== $ourPhone) {
                return null;
            }

            return $fallback !== '' ? $fallback : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function inboundRecipientDisplay(array $msg): string {
        // Meta'dan gelen mesajda "recipient_phone" olarak bizim aldığımız
        // numara: WABA metadata.display_phone_number olur.
        return (string)($msg['metadata']['display_phone_number'] ?? 'WABA');
    }

    /**
     * Basit otomatik yanıt: settings'te "meta_auto_reply_enabled"=1 ve
     * "meta_auto_reply_message" doluysa gelen kişiye tek satırlık cevap
     * gönderir. İş kuralı servise bağlıdır, burası yalnızca gateway.
     */
    private function maybeAutoReply(string $tenantId, string $toPhone, string $inboundText): void {
        try {
            $settings = \App\Core\DependencyFactory::getSystemSettingsService();
            $enabled  = (string)$settings->getSetting('meta_auto_reply_enabled', '0');
            $message  = trim((string)$settings->getSetting('meta_auto_reply_message', ''));
            if ($enabled !== '1' || $message === '') {
                return;
            }

            if (!method_exists('\App\Core\DependencyFactory', 'getWhatsAppService')) {
                return;
            }
            $wa = \App\Core\DependencyFactory::getWhatsAppService();
            if (!$wa || !method_exists($wa, 'sendText')) {
                return;
            }

            $wa->sendText($toPhone, $message, [
                'tenant_id'  => $tenantId,
                'source'     => 'auto_reply',
                'in_reply_to' => $inboundText,
            ]);
        } catch (\Throwable $e) {
            $this->logMeta('warning', 'MetaWebhook: auto-reply failed', [
                'error' => $e->getMessage(),
                'to'    => $toPhone,
            ]);
        }
    }
    
    /**
     * Handle WhatsApp message status update (sent, delivered, read)
     */
    private function handleWhatsAppStatus(array $status): void {
        $msgId = $status['id'] ?? '';
        $recipient = $status['recipient_id'] ?? '';
        $statusType = $status['status'] ?? '';
        
        $this->logMeta('info', 'MetaWebhook: WhatsApp status', [
            'message_id' => $msgId,
            'status' => $statusType
        ]);
        
        if (!empty($msgId) && in_array($statusType, ['sent', 'delivered', 'read', 'failed'])) {
            try {
                $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
                $logService->updateStatus($msgId, $statusType);
            } catch (\Throwable $e) {
                $this->logMeta('warning', 'MetaWebhook: Failed to update message status', [
                    'message_id' => $msgId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Process Facebook Page webhook value (Messenger, etc.)
     */
    private function processPageValue(array $value): void {
        $messaging = $value['messaging'] ?? [];
        
        foreach ($messaging as $event) {
            $this->logMeta('info', 'MetaWebhook: Page messaging event', [
                'sender' => $event['sender']['id'] ?? null,
                'recipient' => $event['recipient']['id'] ?? null
            ]);
            // TODO: Handle Messenger events
        }
    }

    /**
     * Safe logging - avoids errors if Logger class is unavailable
     */
    private function logMeta(string $level, string $message, array $context = []): void {
        if (!class_exists('\App\Core\Logger')) {
            return;
        }
        try {
            if ($level === 'error') {
                \App\Core\Logger::error($message, $context);
            } elseif ($level === 'warning') {
                \App\Core\Logger::warning($message, $context);
            } else {
                \App\Core\Logger::info($message, $context);
            }
        } catch (\Throwable $e) {
            // Silently ignore logging failures
        }
    }
}
