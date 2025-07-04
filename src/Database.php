<?php
class Database {
    private $pdo;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';

        try {
            $dsn = "mysql:host={$this->config['database']['host']};dbname={$this->config['database']['name']};charset=utf8mb4";
            $this->pdo = new PDO(
                $dsn,
                $this->config['database']['user'],
                $this->config['database']['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function createTables() {
        // Check MySQL version and set SQL mode for compatibility
        try {
            $this->pdo->exec("SET SESSION sql_mode = 'ALLOW_INVALID_DATES'");
        } catch (PDOException $e) {
            // Ignore if this fails - not critical
        }

        // Enhanced companies table
        $companies = "
            CREATE TABLE IF NOT EXISTS companies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                careers_url VARCHAR(500) NOT NULL,
                selector VARCHAR(500) DEFAULT NULL,
                location_selector VARCHAR(500) DEFAULT NULL,
                description_selector VARCHAR(500) DEFAULT NULL,
                last_checked DATETIME DEFAULT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                logo_url VARCHAR(500) DEFAULT NULL,
                website_url VARCHAR(500) DEFAULT NULL,
                industry VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_status (status),
                INDEX idx_last_checked (last_checked),
                INDEX idx_industry (industry)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Enhanced jobs table
        $jobs = "
            CREATE TABLE IF NOT EXISTS jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                title VARCHAR(500) NOT NULL,
                url VARCHAR(500) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                location VARCHAR(255) DEFAULT NULL,
                job_type ENUM('full-time', 'part-time', 'contract', 'internship', 'remote', 'unknown') DEFAULT 'unknown',
                department VARCHAR(100) DEFAULT NULL,
                experience_level ENUM('entry', 'mid', 'senior', 'executive', 'unknown') DEFAULT 'unknown',
                salary_range VARCHAR(100) DEFAULT NULL,
                is_remote BOOLEAN DEFAULT FALSE,
                content_hash VARCHAR(64) NOT NULL,
                first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('new', 'existing', 'removed') DEFAULT 'new',
                is_featured BOOLEAN DEFAULT FALSE,

                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                UNIQUE KEY unique_job (company_id, content_hash),

                -- Indexes for filtering and searching
                INDEX idx_title (title),
                INDEX idx_location (location),
                INDEX idx_job_type (job_type),
                INDEX idx_is_remote (is_remote),
                INDEX idx_department (department),
                INDEX idx_experience_level (experience_level),
                INDEX idx_status (status),
                INDEX idx_first_seen (first_seen),
                INDEX idx_company_status (company_id, status),
                INDEX idx_featured (is_featured),

                -- Full text search indexes
                FULLTEXT KEY ft_title (title),
                FULLTEXT KEY ft_description (description),
                FULLTEXT KEY ft_title_desc (title, description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Job tags table for flexible categorization
        $jobTags = "
            CREATE TABLE IF NOT EXISTS job_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                tag VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_job_tag (job_id, tag),
                INDEX idx_tag (tag)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Common tags/skills table
        $tags = "
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                category ENUM('skill', 'technology', 'department', 'benefit', 'location') DEFAULT 'skill',
                usage_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_category (category),
                INDEX idx_usage_count (usage_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // User saved jobs
        $savedJobs = "
            CREATE TABLE IF NOT EXISTS saved_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                user_identifier VARCHAR(100) NOT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT DEFAULT NULL,

                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_job (user_identifier, job_id),
                INDEX idx_user (user_identifier),
                INDEX idx_saved_at (saved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Job alerts/notifications
        $jobAlerts = "
            CREATE TABLE IF NOT EXISTS job_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                keywords TEXT DEFAULT NULL,
                location_filter VARCHAR(255) DEFAULT NULL,
                remote_only BOOLEAN DEFAULT FALSE,
                company_ids TEXT DEFAULT NULL,
                min_salary INT DEFAULT NULL,
                max_salary INT DEFAULT NULL,
                job_types TEXT DEFAULT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_sent TIMESTAMP NULL DEFAULT NULL,

                INDEX idx_email (email),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Search analytics
        $searchAnalytics = "
            CREATE TABLE IF NOT EXISTS search_analytics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                search_term VARCHAR(255) DEFAULT NULL,
                location_filter VARCHAR(255) DEFAULT NULL,
                remote_filter BOOLEAN DEFAULT NULL,
                results_count INT DEFAULT 0,
                user_identifier VARCHAR(100) DEFAULT NULL,
                searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_search_term (search_term),
                INDEX idx_searched_at (searched_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Execute all table creation queries with error handling
        $tables = [
            'companies' => $companies,
            'jobs' => $jobs,
            'job_tags' => $jobTags,
            'tags' => $tags,
            'saved_jobs' => $savedJobs,
            'job_alerts' => $jobAlerts,
            'search_analytics' => $searchAnalytics
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                // Log the error but don't stop execution
                error_log("Warning: Table creation for $tableName: " . $e->getMessage());

                // Try alternative syntax for job_alerts if it fails
                if ($tableName === 'job_alerts' && strpos($e->getMessage(), 'last_sent') !== false) {
                    $alternativeJobAlerts = str_replace(
                        'last_sent TIMESTAMP NULL DEFAULT NULL',
                        'last_sent DATETIME DEFAULT NULL',
                        $jobAlerts
                    );
                    try {
                        $this->pdo->exec($alternativeJobAlerts);
                    } catch (PDOException $e2) {
                        error_log("Failed to create job_alerts table with alternative syntax: " . $e2->getMessage());
                    }
                }
            }
        }

        // Create views
        $this->createViews();

        // Insert default data if tables are empty
        $this->insertDefaultData();
    }

    private function createViews() {
        // Active jobs view
        $activeJobsView = "
            CREATE OR REPLACE VIEW active_jobs AS
            SELECT
                j.*,
                c.name as company_name,
                c.logo_url,
                c.website_url,
                c.industry
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE j.status IN ('new', 'existing')
            AND c.status = 'active'
        ";

        // Remote jobs view
        $remoteJobsView = "
            CREATE OR REPLACE VIEW remote_jobs AS
            SELECT * FROM active_jobs
            WHERE is_remote = TRUE OR location LIKE '%remote%' OR location LIKE '%anywhere%'
        ";

        // Recent jobs view
        $recentJobsView = "
            CREATE OR REPLACE VIEW recent_jobs AS
            SELECT * FROM active_jobs
            WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY first_seen DESC
        ";

        $this->pdo->exec($activeJobsView);
        $this->pdo->exec($remoteJobsView);
        $this->pdo->exec($recentJobsView);
    }

    private function insertDefaultData() {
        // Check if we have any companies
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM companies");
        $companyCount = $stmt->fetchColumn();

        if ($companyCount == 0) {
            // Insert some default companies for testing
            $defaultCompanies = [
                [
                    'name' => 'Netflix',
                    'careers_url' => 'https://jobs.netflix.com/',
                    'selector' => 'a[href*="job"]',
                    'website_url' => 'https://netflix.com',
                    'industry' => 'Entertainment'
                ],
                [
                    'name' => 'GitHub',
                    'careers_url' => 'https://github.com/about/careers',
                    'selector' => 'a[href*="job"]',
                    'website_url' => 'https://github.com',
                    'industry' => 'Technology'
                ],
                [
                    'name' => 'Shopify',
                    'careers_url' => 'https://www.shopify.com/careers',
                    'selector' => '.job-listing a',
                    'website_url' => 'https://shopify.com',
                    'industry' => 'E-commerce'
                ]
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO companies (name, careers_url, selector, website_url, industry, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");

            foreach ($defaultCompanies as $company) {
                $stmt->execute([
                    $company['name'],
                    $company['careers_url'],
                    $company['selector'],
                    $company['website_url'],
                    $company['industry']
                ]);
            }
        }

        // Insert default tags if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tags");
        $tagCount = $stmt->fetchColumn();

        if ($tagCount == 0) {
            $defaultTags = [
                // Skills
                ['JavaScript', 'skill'],
                ['Python', 'skill'],
                ['React', 'technology'],
                ['Node.js', 'technology'],
                ['AWS', 'technology'],
                ['Docker', 'technology'],

                // Departments
                ['Engineering', 'department'],
                ['Design', 'department'],
                ['Product', 'department'],
                ['Marketing', 'department'],
                ['Sales', 'department'],

                // Benefits
                ['Remote Work', 'benefit'],
                ['Health Insurance', 'benefit'],
                ['401k', 'benefit'],
                ['Flexible Hours', 'benefit']
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO tags (name, category) VALUES (?, ?)
            ");

            foreach ($defaultTags as $tag) {
                $stmt->execute($tag);
            }
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Utility methods for common operations
    public function getJobStats() {
        $stats = [];

        // Total active jobs
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM active_jobs
        ");
        $stats['total_jobs'] = $stmt->fetchColumn();

        // Remote jobs
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM remote_jobs
        ");
        $stats['remote_jobs'] = $stmt->fetchColumn();

        // New jobs this week
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM recent_jobs
        ");
        $stats['new_jobs'] = $stmt->fetchColumn();

        // Active companies
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM companies WHERE status = 'active'
        ");
        $stats['active_companies'] = $stmt->fetchColumn();

        return $stats;
    }

    public function searchJobs($filters = []) {
        $sql = "SELECT * FROM active_jobs WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['location'])) {
            $sql .= " AND location LIKE ?";
            $params[] = "%{$filters['location']}%";
        }

        if (isset($filters['remote_only']) && $filters['remote_only']) {
            $sql .= " AND is_remote = 1";
        }

        if (!empty($filters['job_type'])) {
            $sql .= " AND job_type = ?";
            $params[] = $filters['job_type'];
        }

        if (!empty($filters['department'])) {
            $sql .= " AND department = ?";
            $params[] = $filters['department'];
        }

        $sql .= " ORDER BY first_seen DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getPopularLocations($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT location, COUNT(*) as job_count
            FROM active_jobs
            WHERE location IS NOT NULL AND location != ''
            GROUP BY location
            ORDER BY job_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getPopularCompanies($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT company_name, COUNT(*) as job_count
            FROM active_jobs
            GROUP BY company_name
            ORDER BY job_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function cleanOldJobs($daysOld = 30) {
        // Mark jobs as removed if they haven't been seen in X days
        $stmt = $this->pdo->prepare("
            UPDATE jobs
            SET status = 'removed'
            WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status != 'removed'
        ");
        return $stmt->execute([$daysOld]);
    }

    public function getCompanyJobCount($companyId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM jobs
            WHERE company_id = ? AND status IN ('new', 'existing')
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchColumn();
    }
}
