<?php
class Database {
    private $pdo;
    private $config;

    public function __construct() {
        // Check if config file exists
        $configPath = __DIR__ . '/../config/config.php';
        if (!file_exists($configPath)) {
            throw new Exception("Configuration file not found. Please copy config.example.php to config.php and configure it.");
        }

        $this->config = require $configPath;

        // Validate config
        if (!isset($this->config['database']) ||
            !isset($this->config['database']['host']) ||
            !isset($this->config['database']['name']) ||
            !isset($this->config['database']['user']) ||
            !isset($this->config['database']['pass'])) {
            throw new Exception("Invalid database configuration. Please check config/config.php");
        }

        try {
            $dsn = "mysql:host={$this->config['database']['host']};dbname={$this->config['database']['name']};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
            ];

            $this->pdo = new PDO(
                $dsn,
                $this->config['database']['user'],
                $this->config['database']['pass'],
                $options
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage() . ". Please check your database credentials in config/config.php");
        }
    }

    public function createTables() {
        try {
            // Create companies table
            $companies = "
                CREATE TABLE IF NOT EXISTS companies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    careers_url VARCHAR(500) NOT NULL,
                    selector VARCHAR(500) DEFAULT NULL,
                    last_checked DATETIME DEFAULT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_last_checked (last_checked)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";

            // Create jobs table
            $jobs = "
                CREATE TABLE IF NOT EXISTS jobs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_id INT NOT NULL,
                    title VARCHAR(500) NOT NULL,
                    url VARCHAR(500) DEFAULT NULL,
                    content_hash VARCHAR(64) NOT NULL,
                    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    status ENUM('new', 'existing', 'removed') DEFAULT 'new',
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_job (company_id, content_hash),
                    INDEX idx_status (status),
                    INDEX idx_first_seen (first_seen),
                    INDEX idx_company_status (company_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";

            $this->pdo->exec($companies);
            $this->pdo->exec($jobs);

            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to create database tables: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getVersion() {
        try {
            $stmt = $this->pdo->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            return $result['version'] ?? 'Unknown';
        } catch (PDOException $e) {
            return 'Unknown';
        }
    }
}
