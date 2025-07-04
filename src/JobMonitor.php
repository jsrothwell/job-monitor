<?php
// src/JobMonitor.php - Enhanced version with manual run capabilities

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
            echo "Checking {$companyData['name']}...\n";

            $newJobs = $this->scraper->scrapeCompany($companyData);

            if ($newJobs === false) {
                echo "Failed to scrape {$companyData['name']}\n";
                continue;
            }

            if (!empty($newJobs)) {
                echo "Found " . count($newJobs) . " new jobs at {$companyData['name']}\n";
                $this->emailer->sendJobAlert($companyData['name'], $newJobs);
            } else {
                echo "No new jobs at {$companyData['name']}\n";
            }

            sleep(2);
        }

        echo "Monitoring complete.\n";
    }

    // Enhanced manual run method with detailed results
    public function runManual() {
        $companies = $this->company->getActive();

        $results = [
            'companies_checked' => 0,
            'total_new_jobs' => 0,
            'emails_sent' => 0,
            'errors' => 0,
            'details' => [],
            'start_time' => microtime(true)
        ];

        foreach ($companies as $companyData) {
            $results['companies_checked']++;
            $companyName = $companyData['name'];

            try {
                $newJobs = $this->scraper->scrapeCompany($companyData);

                if ($newJobs === false) {
                    $results['errors']++;
                    $results['details'][$companyName] = [
                        'success' => false,
                        'error' => 'Failed to scrape website',
                        'new_jobs' => 0
                    ];
                    continue;
                }

                $jobCount = count($newJobs);
                $results['total_new_jobs'] += $jobCount;

                if (!empty($newJobs)) {
                    $this->emailer->sendJobAlert($companyName, $newJobs);
                    $results['emails_sent']++;
                }

                $results['details'][$companyName] = [
                    'success' => true,
                    'new_jobs' => $jobCount,
                    'jobs' => $newJobs
                ];

            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][$companyName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'new_jobs' => 0
                ];
            }

            // Small delay between requests
            sleep(1);
        }

        $results['end_time'] = microtime(true);
        $results['duration'] = round($results['end_time'] - $results['start_time'], 2);

        return $results;
    }

    // Get recent monitoring statistics
    public function getStats() {
        $pdo = $this->db->getConnection();

        // Get total companies
        $stmt = $pdo->query("SELECT COUNT(*) FROM companies");
        $totalCompanies = $stmt->fetchColumn();

        // Get active companies
        $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
        $activeCompanies = $stmt->fetchColumn();

        // Get total jobs tracked
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs");
        $totalJobs = $stmt->fetchColumn();

        // Get new jobs in last 24 hours
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $newJobsToday = $stmt->fetchColumn();

        // Get last monitoring run
        $stmt = $pdo->query("SELECT MAX(last_checked) FROM companies WHERE last_checked IS NOT NULL");
        $lastRun = $stmt->fetchColumn();

        return [
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'total_jobs' => $totalJobs,
            'new_jobs_today' => $newJobsToday,
            'last_run' => $lastRun
        ];
    }

    // Get recent job discoveries
    public function getRecentJobs($limit = 10) {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT j.*, c.name as company_name
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            ORDER BY j.first_seen DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Test a single company manually
    public function testCompany($companyId) {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND status = 'active'");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new Exception("Company not found or inactive");
        }

        $startTime = microtime(true);

        try {
            $newJobs = $this->scraper->scrapeCompany($company);
            $endTime = microtime(true);

            return [
                'success' => true,
                'company' => $company['name'],
                'new_jobs' => $newJobs === false ? 0 : count($newJobs),
                'jobs' => $newJobs ?: [],
                'duration' => round($endTime - $startTime, 2),
                'message' => $newJobs === false ? 'Scraping failed' : 'Scraping successful'
            ];

        } catch (Exception $e) {
            $endTime = microtime(true);

            return [
                'success' => false,
                'company' => $company['name'],
                'error' => $e->getMessage(),
                'duration' => round($endTime - $startTime, 2),
                'message' => 'Error occurred during scraping'
            ];
        }
    }
}
