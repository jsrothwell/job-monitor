<?php
class Database {
    private $pdo;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';

        try {
            $dsn = "mysql:host={$this->config['database']['host']};dbname={$this->config['database']['name']}";
            $this->pdo = new PDO(
                $dsn,
                $this->config['database']['user'],
                $this->config['database']['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function createTables() {
        $companies = "
            CREATE TABLE IF NOT EXISTS companies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                careers_url VARCHAR(500) NOT NULL,
                selector VARCHAR(500) DEFAULT NULL,
                last_checked DATETIME DEFAULT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $jobs = "
            CREATE TABLE IF NOT EXISTS jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT,
                title VARCHAR(500) NOT NULL,
                url VARCHAR(500) DEFAULT NULL,
                content_hash VARCHAR(64),
                first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('new', 'existing', 'removed') DEFAULT 'new',
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                UNIQUE KEY unique_job (company_id, content_hash)
            )
        ";

        $this->pdo->exec($companies);
        $this->pdo->exec($jobs);
    }

    public function getConnection() {
        return $this->pdo;
    }
}
