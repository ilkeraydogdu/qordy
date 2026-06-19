<?php
/**
 * push_trial_reminders.php
 *
 * Gün sonu cron — Trial süresinin son 3 / 1 / 0 günlerinde işletme sahibine FCM
 * push ve e-posta hatırlatıcısı gönderir.
 *
 * Önerilen crontab:
 *   0 10 * * * php /var/www/vhosts/qordy.com/httpdocs/app/scripts/push_trial_reminders.php >> storage/logs/push_trial.log 2>&1
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Core\DependencyFactory;

$db = DependencyFactory::getDatabase();
$push = null;
try {
    $push = new \App\Services\PushService();
} catch (\Exception $e) {
    echo "PushService init failed: {$e->getMessage()}\n";
}

$now = new DateTimeImmutable();

try {
    $stmt = $db->prepare("
        SELECT s.subscription_id, s.tenant_id AS customer_id, s.trial_ends_at, c.company_name, c.email
        FROM subscriptions s
        JOIN customers c ON c.customer_id = s.tenant_id
        WHERE s.status = 'active'
          AND s.is_trial = 1
          AND s.trial_ends_at IS NOT NULL
          AND s.trial_ends_at >= NOW()
          AND s.trial_ends_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    echo "Query failed: {$e->getMessage()}\n";
    exit(1);
}

$sent = 0; $skipped = 0;
foreach ($rows as $row) {
    $ends = new DateTimeImmutable($row['trial_ends_at']);
    $daysLeft = (int)$now->diff($ends)->days;
    if (!in_array($daysLeft, [0, 1, 3], true)) { $skipped++; continue; }

    $title = $daysLeft === 0
        ? 'Deneme süreniz bugün sona eriyor!'
        : "Deneme sürenizin bitmesine {$daysLeft} gün kaldı";
    $body  = 'Qordy\'den kesintisiz yararlanmak için paketinizi şimdi satın alın.';

    if ($push && $push->isConfigured()) {
        $res = $push->sendToUser((string)$row['customer_id'], $title, $body, [
            'route' => 'upsell',
            'subscription_id' => $row['subscription_id'],
            'days_left' => $daysLeft,
        ]);
        if (($res['sent'] ?? 0) > 0) {
            $sent++;
            echo "[push] {$row['company_name']} -> {$res['sent']} device(s)\n";
        } else {
            $skipped++;
        }
    } else {
        echo "[skip] FCM not configured — {$row['company_name']}\n";
        $skipped++;
    }
}

echo "Done. sent={$sent} skipped={$skipped} candidates=" . count($rows) . "\n";
