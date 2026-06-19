<?php
/**
 * Add image_url and icon columns to categories table.
 *
 * Usage: /opt/plesk/php/8.3/bin/php app/migrations/20260617_categories_image_icon.php
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

    $columns = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('image_url', $columns, true)) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN image_url VARCHAR(500) NULL DEFAULT NULL AFTER description");
        echo "Added categories.image_url\n";
    }

    if (!in_array('icon', $columns, true)) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN icon VARCHAR(64) NULL DEFAULT NULL AFTER image_url");
        echo "Added categories.icon\n";
    }

    echo "Migration complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
