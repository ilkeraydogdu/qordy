<?php
/**
 * AI saved insights table + navigation item "AI Önerileri".
 *
 * Usage: /opt/plesk/php/8.3/bin/php app/migrations/20260616_business_ai_saved_insights.php
 */

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"')
            || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'qordy';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$port = 3306;
if (strpos($host, ':') !== false) {
    [$host, $port] = explode(':', $host, 2);
    $port = (int) $port ?: 3306;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS business_ai_saved_insights (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id VARCHAR(64) NOT NULL,
            user_id VARCHAR(64) NOT NULL,
            insight_id CHAR(64) NOT NULL,
            category_key VARCHAR(32) NOT NULL DEFAULT '',
            category_label VARCHAR(64) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            metric VARCHAR(128) NOT NULL DEFAULT '',
            body_text TEXT NOT NULL,
            action_hint VARCHAR(512) NOT NULL DEFAULT '',
            impact VARCHAR(16) NOT NULL DEFAULT 'orta',
            tone VARCHAR(16) NOT NULL DEFAULT 'info',
            icon VARCHAR(32) NOT NULL DEFAULT 'info',
            source VARCHAR(16) NOT NULL DEFAULT 'rule',
            payload_json JSON NULL,
            saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_business_user_insight (business_id, user_id, insight_id),
            KEY idx_business_user_saved (business_id, user_id, saved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "OK: business_ai_saved_insights table ready.\n";

    $analyticsId = $pdo->query(
        "SELECT nav_id FROM navigation_items WHERE nav_key = 'ANALYTICS' AND parent_id IS NULL LIMIT 1"
    )->fetchColumn();

    if (!$analyticsId) {
        echo "SKIP: ANALYTICS parent nav not found (nav item not inserted).\n";
        exit(0);
    }

    $exists = $pdo->prepare(
        "SELECT nav_id FROM navigation_items WHERE nav_key = 'AI_SUGGESTIONS' LIMIT 1"
    );
    $exists->execute();
    if ($exists->fetchColumn()) {
        echo "OK: AI_SUGGESTIONS nav item already exists.\n";
        exit(0);
    }

    $ins = $pdo->prepare("
        INSERT INTO navigation_items
            (nav_id, nav_key, parent_id, icon, label_tr, label_en, url, permission_key, display_order, is_active)
        VALUES
            ('AI_SUGGESTIONS', 'AI_SUGGESTIONS', :parent, 'Sparkles', 'AI Önerileri', 'AI Suggestions',
             '/business/ai-onerileri', 'dashboard.analytics', 12, 1)
    ");
    $ins->execute(['parent' => $analyticsId]);
    echo "OK: AI_SUGGESTIONS navigation item created.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
