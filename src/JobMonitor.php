<?php
// src/JobMonitor.php - MySQL Compatible Version

class JobMonitor {
    private $db;
    private $company;
    private $scraper;
    private $emailer;

    public function __construct() {
        $this->db = new Database();
        $this->company = new Company($this->db);
        $this->scraper = new JobScraper($this->db, $this->company);
        $this->emailer = new Emailer();
    }

    // Original cron job method
    public function run() {
        echo "Starting job monitoring...\n";

        $companies = $this->company->getActive();

        foreach ($companies as $companyData) {
            echo "Checking " . $companyData['name'] . "...\n";

            $newJobs = $this->scraper->scrapeCompany($companyData);

            if ($newJobs === false) {
                echo "Failed to scrape " . $companyData['name'] . "\n";
                continue;
            }

            if (!empty($newJobs)) {
                echo "Found " . count($newJobs) . " new jobs at " . $companyData['name'] . "\n";
                $this->emailer->sendJobAlert($companyData['name'], $newJobs);
            } else {
                echo "No new jobs at " . $companyData['name'] . "\n";
            }

            sleep(2);
        }

        echo "Monitoring complete.\n";
    }

    // Enhanced manual run method with detailed results
    public function runManual() {
        $companies = $this->company->getActive();

        $results = array(
            'companies_checked' => 0,
            'total_new_jobs' => 0,
            'emails_sent' => 0,
            'errors' => 0,
            'details' => array(),
            'start_time' => microtime(true)
        );

        foreach ($companies as $companyData) {
            $results['companies_checked']++;
            $companyName = $companyData['name'];

            try {
                $newJobs = $this->scraper->scrapeCompany($companyData);

                if ($newJobs === false) {
                    $results['errors']++;
                    $results['details'][$companyName] = array(
                        'success' => false,
                        'error' => 'Failed to scrape website',
                        'new_jobs' => 0
                    );
                    continue;
                }

                $jobCount = count($newJobs);
                $results['total_new_jobs'] += $jobCount;

                if (!empty($newJobs)) {
                    $this->emailer->sendJobAlert($companyName, $newJobs);
                    $results['emails_sent']++;
                }

                $results['details'][$companyName] = array(
                    'success' => true,
                    'new_jobs' => $jobCount,
                    'jobs' => $newJobs
                );

            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][$companyName] = array(
                    'success' => false,
                    'error' => $e->getMessage(),
                    'new_jobs' => 0
                );
            }

            // Small delay between requests
            sleep(1);
        }

        $results['end_time'] = microtime(true);
        $results['duration'] = round($results['end_time'] - $results['start_time'], 2);

        return $results;
    }

    // Get recent monitoring statistics (MySQL compatible)
    public function getStats() {
        try {
            $pdo = $this->db->getConnection();

            // Get total companies
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
            $result = $stmt->fetch();
            $totalCompanies = isset($result['count']) ? $result['count'] : 0;

            // Get active companies
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
            $result = $stmt->fetch();
            $activeCompanies = isset($result['count']) ? $result['count'] : 0;

            // Get total jobs tracked
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM jobs");
            $result = $stmt->fetch();
            $totalJobs = isset($result['count']) ? $result['count'] : 0;

            // Get new jobs in last 24 hours (compatible with older MySQL)
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM jobs
                WHERE first_seen >= DATE_ADD(CURDATE(), INTERVAL -1 DAY)
            ");
            $result = $stmt->fetch();
            $newJobsToday = isset($result['count']) ? $result['count'] : 0;

            // Get last monitoring run (compatible syntax)
            $stmt = $pdo->query("SELECT last_checked FROM companies WHERE last_checked IS NOT NULL ORDER BY last_checked DESC LIMIT 1");
            $result = $stmt->fetch();
            $lastRun = isset($result['last_checked']) ? $result['last_checked'] : null;

            return array(
                'total_companies' => (int)$totalCompanies,
                'active_companies' => (int)$activeCompanies,
                'total_jobs' => (int)$totalJobs,
                'new_jobs_today' => (int)$newJobsToday,
                'last_run' => $lastRun
            );
        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return array(
                'total_companies' => 0,
                'active_companies' => 0,
                'total_jobs' => 0,
                'new_jobs_today' => 0,
                'last_run' => null
            );
        }
    }

    // Get recent job discoveries
    public function getRecentJobs($limit = 10) {
        try {
            $pdo = $this->db->getConnection();

            $stmt = $pdo->prepare("
                SELECT j.*, c.name as company_name
                FROM jobs j
                JOIN companies c ON j.company_id = c.id
                ORDER BY j.first_seen DESC
                LIMIT ?
            ");
            $stmt->execute(array($limit));

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent jobs: " . $e->getMessage());
            return array();
        }
    }

    // Test a single company manually
    public function testCompany($companyId) {
        try {
            $pdo = $this->db->getConnection();

            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND status = 'active'");
            $stmt->execute(array($companyId));
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                throw new Exception("Company not found or inactive");
            }

            $startTime = microtime(true);

            try {
                $newJobs = $this->scraper->scrapeCompany($company);
                $endTime = microtime(true);

                return array(
                    'success' => true,
                    'company' => $company['name'],
                    'new_jobs' => $newJobs === false ? 0 : count($newJobs),
                    'jobs' => $newJobs ? $newJobs : array(),
                    'duration' => round($endTime - $startTime, 2),
                    'message' => $newJobs === false ? 'Scraping failed' : 'Scraping successful'
                );

            } catch (Exception $e) {
                $endTime = microtime(true);

                return array(
                    'success' => false,
                    'company' => $company['name'],
                    'error' => $e->getMessage(),
                    'duration' => round($endTime - $startTime, 2),
                    'message' => 'Error occurred during scraping'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'company' => 'Unknown',
                'error' => $e->getMessage(),
                'duration' => 0,
                'message' => 'Failed to load company data'
            );
        }
    }

    // Check if the application is properly configured
    public function checkConfiguration() {
        $issues = array();

        // Check config file
        if (!file_exists(__DIR__ . '/../config/config.php')) {
            $issues[] = "Configuration file missing";
        }

        // Check database connection
        try {
            if (!$this->db->testConnection()) {
                $issues[] = "Database connection failed";
            }
        } catch (Exception $e) {
            $issues[] = "Database connection failed: " . $e->getMessage();
        }

        // Check required extensions
        $requiredExtensions = array('pdo', 'pdo_mysql', 'curl', 'dom', 'libxml');
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Required PHP extension missing: $ext";
            }
        }

        return array(
            'configured' => empty($issues),
            'issues' => $issues
        );
    }
}
