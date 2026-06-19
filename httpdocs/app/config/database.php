<?php
namespace App\Config;

class Database {
    private static $instance = null;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct() {
        // Load from environment variables only (must be set in .env file)
        if (!isset($_ENV['DB_HOST']) || empty($_ENV['DB_HOST'])) {
            throw new \Exception('DB_HOST environment variable is required. Please set it in .env file.');
        }
        if (!isset($_ENV['DB_NAME']) || empty($_ENV['DB_NAME'])) {
            throw new \Exception('DB_NAME environment variable is required. Please set it in .env file.');
        }
        if (!isset($_ENV['DB_USER']) || empty($_ENV['DB_USER'])) {
            throw new \Exception('DB_USER environment variable is required. Please set it in .env file.');
        }
        if (!isset($_ENV['DB_PASS'])) {
            throw new \Exception('DB_PASS environment variable is required. Please set it in .env file.');
        }

        $this->host = $_ENV['DB_HOST'];
        $this->dbname = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
    }

    public function connect() {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                // Persistent connections removed to prevent connection pool exhaustion
                // In production, use connection pooler (ProxySQL/PgBouncer) at infrastructure level
                // \PDO::ATTR_PERSISTENT => false,
            ];

            $this->pdo = new \PDO($dsn, $this->username, $this->password, $options);
            
            // Set SQL modes for security and consistency
            $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
            return $this->pdo;
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            // For production, don't expose database details
            $appEnv = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
            if ($appEnv === 'production') {
                throw new \Exception("Database connection failed");
            } else {
                throw $e;
            }
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get database connection with connection pooling
     */
    public function getConnection() {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Test database connection
     */
    public function testConnection(): bool {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare("SELECT 1");
            $stmt->execute();
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Exception $e) {
            error_log("Database test connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database configuration details (for debugging purposes)
     */
    public function getConfig(): array {
        return [
            'host' => $this->host,
            'dbname' => $this->dbname,
            'username' => $this->username,
            // Don't return password for security
            'connected' => $this->pdo !== null
        ];
    }
}