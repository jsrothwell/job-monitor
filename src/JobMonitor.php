<?php
// src/JobMonitor.php - Enhanced version for job feed aggregation

class JobMonitor {
    private $db;
    private $company;
    private $scraper;
    private $emailer;
    private $config;

    public function __construct() {
        $this->db = new Database();
        $this->company = new Company($this->db);
        $this->scraper = new JobScraper($this->db, $this->company);
        $this->emailer = new Emailer();
        $this->config = require __DIR__ . '/../config/config.php';
    }

    /**
     * Original cron job method - for automated monitoring
     */
    public function run() {
        echo "Starting job feed monitoring...\n";

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

                // Send email alerts
                $this->sendEmailAlerts($companyData['name'], $newJobs);

                // Process job alerts
                $this->processJobAlerts($newJobs, $companyData);
            } else {
                echo "No new jobs at {$companyData['name']}\n";
            }

            // Small delay between requests to be respectful
            sleep(2);
        }

        // Clean up old jobs
        $this->cleanupOldJobs();

        echo "Monitoring complete.\n";
    }

    /**
     * Enhanced manual run method with detailed results
     */
    public function runManual() {
        $companies = $this->company->getActive();

        $results = [
            'companies_checked' => 0,
            'total_new_jobs' => 0,
            'emails_sent' => 0,
            'alerts_sent' => 0,
            'errors' => 0,
            'details' => [],
            'start_time' => microtime(true),
            'summary' => []
        ];

        foreach ($companies as $companyData) {
            $results['companies_checked']++;
            $companyName = $companyData['name'];

            try {
                $startTime = microtime(true);
                $newJobs = $this->scraper->scrapeCompany($companyData);
                $endTime = microtime(true);

                if ($newJobs === false) {
                    $results['errors']++;
                    $results['details'][$companyName] = [
                        'success' => false,
                        'error' => 'Failed to scrape website',
                        'new_jobs' => 0,
                        'duration' => round($endTime - $startTime, 2)
                    ];
                    continue;
                }

                $jobCount = count($newJobs);
                $results['total_new_jobs'] += $jobCount;

                if (!empty($newJobs)) {
                    // Send traditional email alert
                    $this->emailer->sendJobAlert($companyName, $newJobs);
                    $results['emails_sent']++;

                    // Process targeted job alerts
                    $alertsSent = $this->processJobAlerts($newJobs, $companyData);
                    $results['alerts_sent'] += $alertsSent;
                }

                $results['details'][$companyName] = [
                    'success' => true,
                    'new_jobs' => $jobCount,
                    'duration' => round($endTime - $startTime, 2),
                    'jobs' => $this->formatJobsForResults($newJobs),
                    'alerts_sent' => $alertsSent ?? 0
                ];

            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][$companyName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'new_jobs' => 0,
                    'duration' => 0
                ];
            }

            // Small delay between requests
            sleep(1);
        }

        $results['end_time'] = microtime(true);
        $results['duration'] = round($results['end_time'] - $results['start_time'], 2);

        // Generate summary
        $results['summary'] = $this->generateRunSummary($results);

        return $results;
    }

    /**
     * Get comprehensive monitoring statistics
     */
    public function getStats() {
        $pdo = $this->db->getConnection();
        $stats = [];

        // Basic counts
        $stmt = $pdo->query("SELECT COUNT(*) FROM companies");
        $stats['total_companies'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
        $stats['active_companies'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status IN ('new', 'existing')");
        $stats['total_jobs'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_remote = 1 AND status IN ('new', 'existing')");
        $stats['remote_jobs'] = $stmt->fetchColumn();

        // Time-based stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['new_jobs_today'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['new_jobs_week'] = $stmt->fetchColumn();

        // Last monitoring run
        $stmt = $pdo->query("SELECT MAX(last_checked) FROM companies WHERE last_checked IS NOT NULL");
        $stats['last_run'] = $stmt->fetchColumn();

        // Department breakdown
        $stmt = $pdo->query("
            SELECT department, COUNT(*) as count
            FROM jobs
            WHERE department IS NOT NULL
            AND status IN ('new', 'existing')
            GROUP BY department
            ORDER BY count DESC
            LIMIT 10
        ");
        $stats['departments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Location breakdown
        $stmt = $pdo->query("
            SELECT location, COUNT(*) as count
            FROM jobs
            WHERE location IS NOT NULL
            AND location != ''
            AND status IN ('new', 'existing')
            GROUP BY location
            ORDER BY count DESC
            LIMIT 10
        ");
        $stats['locations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Job type breakdown
        $stmt = $pdo->query("
            SELECT job_type, COUNT(*) as count
            FROM jobs
            WHERE job_type != 'unknown'
            AND status IN ('new', 'existing')
            GROUP BY job_type
            ORDER BY count DESC
        ");
        $stats['job_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Experience level breakdown
        $stmt = $pdo->query("
            SELECT experience_level, COUNT(*) as count
            FROM jobs
            WHERE experience_level != 'unknown'
            AND status IN ('new', 'existing')
            GROUP BY experience_level
            ORDER BY count DESC
        ");
        $stats['experience_levels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Industry breakdown
        $stmt = $pdo->query("
            SELECT c.industry, COUNT(j.id) as count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id AND j.status IN ('new', 'existing')
            WHERE c.industry IS NOT NULL
            GROUP BY c.industry
            ORDER BY count DESC
        ");
        $stats['industries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * Get recent job discoveries with enhanced details
     */
    public function getRecentJobs($limit = 10, $filters = []) {
        $pdo = $this->db->getConnection();

        $sql = "
            SELECT j.*, c.name as company_name, c.industry, c.logo_url, c.website_url
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE j.status IN ('new', 'existing')
        ";

        $params = [];

        // Apply filters
        if (!empty($filters['department'])) {
            $sql .= " AND j.department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['location'])) {
            $sql .= " AND j.location LIKE ?";
            $params[] = "%{$filters['location']}%";
        }

        if (isset($filters['remote']) && $filters['remote']) {
            $sql .= " AND j.is_remote = 1";
        }

        if (!empty($filters['job_type'])) {
            $sql .= " AND j.job_type = ?";
            $params[] = $filters['job_type'];
        }

        $sql .= " ORDER BY j.first_seen DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Test a single company manually with enhanced feedback
     */
    public function testCompany($companyId) {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new Exception("Company not found");
        }

        $startTime = microtime(true);

        try {
            $newJobs = $this->scraper->scrapeCompany($company);
            $endTime = microtime(true);

            // Get additional metrics
            $totalJobs = $newJobs === false ? 0 : count($newJobs);
            $remoteJobs = 0;
            $departmentBreakdown = [];
            $locationBreakdown = [];

            if ($newJobs !== false && !empty($newJobs)) {
                foreach ($newJobs as $job) {
                    if ($job['is_remote']) $remoteJobs++;

                    if ($job['department']) {
                        $departmentBreakdown[$job['department']] =
                            ($departmentBreakdown[$job['department']] ?? 0) + 1;
                    }

                    if ($job['location']) {
                        $locationBreakdown[$job['location']] =
                            ($locationBreakdown[$job['location']] ?? 0) + 1;
                    }
                }
            }

            return [
                'success' => $newJobs !== false,
                'company' => $company['name'],
                'new_jobs' => $totalJobs,
                'remote_jobs' => $remoteJobs,
                'jobs' => $this->formatJobsForResults($newJobs ?: []),
                'duration' => round($endTime - $startTime, 2),
                'department_breakdown' => $departmentBreakdown,
                'location_breakdown' => $locationBreakdown,
                'message' => $newJobs === false ? 'Scraping failed' : 'Scraping successful',
                'selectors_used' => [
                    'job_selector' => $company['selector'] ?: 'auto-detect',
                    'location_selector' => $company['location_selector'] ?: 'auto-detect',
                    'description_selector' => $company['description_selector'] ?: 'auto-detect'
                ]
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

    /**
     * Process job alerts - send targeted notifications
     */
    private function processJobAlerts($newJobs, $companyData) {
        if (empty($newJobs)) return 0;

        $pdo = $this->db->getConnection();
        $alertsSent = 0;

        // Get active job alerts
        $stmt = $pdo->query("
            SELECT * FROM job_alerts
            WHERE is_active = 1
            ORDER BY created_at ASC
        ");
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($alerts as $alert) {
            $matchingJobs = $this->filterJobsForAlert($newJobs, $alert, $companyData);

            if (!empty($matchingJobs)) {
                $this->sendJobAlert($alert, $matchingJobs, $companyData);
                $alertsSent++;

                // Update last sent timestamp
                $stmt = $pdo->prepare("
                    UPDATE job_alerts
                    SET last_sent = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$alert['id']]);
            }
        }

        return $alertsSent;
    }

    /**
     * Filter jobs based on alert criteria
     */
    private function filterJobsForAlert($jobs, $alert, $companyData) {
        $matchingJobs = [];

        $keywords = json_decode($alert['keywords'] ?? '[]', true) ?: [];
        $companyIds = json_decode($alert['company_ids'] ?? '[]', true) ?: [];
        $jobTypes = json_decode($alert['job_types'] ?? '[]', true) ?: [];

        foreach ($jobs as $job) {
            $matches = true;

            // Check keywords
            if (!empty($keywords)) {
                $jobText = strtolower($job['title'] . ' ' . ($job['description'] ?? ''));
                $keywordMatch = false;

                foreach ($keywords as $keyword) {
                    if (strpos($jobText, strtolower($keyword)) !== false) {
                        $keywordMatch = true;
                        break;
                    }
                }

                if (!$keywordMatch) $matches = false;
            }

            // Check location
            if ($matches && !empty($alert['location_filter'])) {
                $jobLocation = strtolower($job['location'] ?? '');
                $filterLocation = strtolower($alert['location_filter']);

                if (strpos($jobLocation, $filterLocation) === false) {
                    $matches = false;
                }
            }

            // Check remote requirement
            if ($matches && $alert['remote_only'] && !$job['is_remote']) {
                $matches = false;
            }

            // Check company filter
            if ($matches && !empty($companyIds)) {
                if (!in_array($companyData['id'], $companyIds)) {
                    $matches = false;
                }
            }

            // Check job type
            if ($matches && !empty($jobTypes)) {
                if (!in_array($job['job_type'], $jobTypes)) {
                    $matches = false;
                }
            }

            // Check salary range
            if ($matches && ($alert['min_salary'] || $alert['max_salary'])) {
                $jobSalary = $this->extractSalaryNumber($job['salary_range'] ?? '');

                if ($alert['min_salary'] && $jobSalary && $jobSalary < $alert['min_salary']) {
                    $matches = false;
                }

                if ($alert['max_salary'] && $jobSalary && $jobSalary > $alert['max_salary']) {
                    $matches = false;
                }
            }

            if ($matches) {
                $matchingJobs[] = $job;
            }
        }

        return $matchingJobs;
    }

    /**
     * Send targeted job alert
     */
    private function sendJobAlert($alert, $jobs, $companyData) {
        $subject = "Job Alert: " . count($jobs) . " matching position" . (count($jobs) > 1 ? 's' : '') . " found";

        $body = "Your job alert found " . count($jobs) . " matching position(s):\n\n";

        foreach ($jobs as $job) {
            $body .= "• {$job['title']} at {$companyData['name']}\n";
            if ($job['location']) $body .= "  Location: {$job['location']}\n";
            if ($job['is_remote']) $body .= "  🏠 Remote Work Available\n";
            if ($job['salary_range']) $body .= "  💰 {$job['salary_range']}\n";
            if ($job['url']) $body .= "  🔗 {$job['url']}\n";
            $body .= "\n";
        }

        $body .= "---\n";
        $body .= "This alert was triggered by your job search criteria.\n";
        $body .= "To manage your alerts, visit your job feed dashboard.\n";

        // Use the emailer to send
        $this->emailer->sendCustomAlert($alert['email'], $subject, $body);
    }

    /**
     * Send traditional email alerts
     */
    private function sendEmailAlerts($companyName, $newJobs) {
        if (!empty($this->config['email']['alerts_enabled'])) {
            $this->emailer->sendJobAlert($companyName, $newJobs);
        }
    }

    /**
     * Clean up old jobs
     */
    private function cleanupOldJobs($daysOld = 30) {
        return $this->db->cleanOldJobs($daysOld);
    }

    /**
     * Format jobs for API results
     */
    private function formatJobsForResults($jobs) {
        return array_map(function($job) {
            return [
                'title' => $job['title'],
                'location' => $job['location'] ?? null,
                'is_remote' => $job['is_remote'] ?? false,
                'job_type' => $job['job_type'] ?? 'unknown',
                'department' => $job['department'] ?? null,
                'salary_range' => $job['salary_range'] ?? null,
                'url' => $job['url'] ?? null
            ];
        }, $jobs);
    }

    /**
     * Generate run summary
     */
    private function generateRunSummary($results) {
        $summary = [
            'success_rate' => $results['companies_checked'] > 0 ?
                round((($results['companies_checked'] - $results['errors']) / $results['companies_checked']) * 100, 1) : 0,
            'avg_jobs_per_company' => $results['companies_checked'] > 0 ?
                round($results['total_new_jobs'] / $results['companies_checked'], 1) : 0,
            'most_productive_company' => null,
            'companies_with_jobs' => 0,
            'companies_with_errors' => $results['errors']
        ];

        $maxJobs = 0;
        foreach ($results['details'] as $company => $details) {
            if ($details['success'] && $details['new_jobs'] > 0) {
                $summary['companies_with_jobs']++;

                if ($details['new_jobs'] > $maxJobs) {
                    $maxJobs = $details['new_jobs'];
                    $summary['most_productive_company'] = [
                        'name' => $company,
                        'jobs' => $details['new_jobs']
                    ];
                }
            }
        }

        return $summary;
    }

    /**
     * Extract salary number from salary string
     */
    private function extractSalaryNumber($salaryString) {
        if (empty($salaryString)) return null;

        // Remove common currency symbols and text
        $cleaned = preg_replace('/[^\d,.-]/', '', $salaryString);
        $cleaned = str_replace(',', '', $cleaned);

        // Extract first number found
        if (preg_match('/(\d+(?:\.\d+)?)/', $cleaned, $matches)) {
            return (float)$matches[1];
        }

        return null;
    }

    /**
     * Get trending job titles
     */
    public function getTrendingJobTitles($days = 7, $limit = 10) {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT
                title,
                COUNT(*) as frequency,
                COUNT(DISTINCT company_id) as companies
            FROM jobs
            WHERE first_seen >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status IN ('new', 'existing')
            GROUP BY title
            HAVING frequency > 1
            ORDER BY frequency DESC, companies DESC
            LIMIT ?
        ");

        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get job growth trends
     */
    public function getJobGrowthTrends($days = 30) {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT
                DATE(first_seen) as date,
                COUNT(*) as jobs_posted,
                COUNT(CASE WHEN is_remote = 1 THEN 1 END) as remote_jobs
            FROM jobs
            WHERE first_seen >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(first_seen)
            ORDER BY date ASC
        ");

        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get company performance metrics
     */
    public function getCompanyPerformance($companyId = null) {
        $pdo = $this->db->getConnection();

        $sql = "
            SELECT
                c.name,
                c.industry,
                COUNT(j.id) as total_jobs,
                COUNT(CASE WHEN j.first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as jobs_this_week,
                COUNT(CASE WHEN j.first_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as jobs_this_month,
                COUNT(CASE WHEN j.is_remote = 1 THEN 1 END) as remote_jobs,
                c.last_checked,
                DATEDIFF(NOW(), c.last_checked) as days_since_check
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id AND j.status IN ('new', 'existing')
            WHERE c.status = 'active'
        ";

        $params = [];

        if ($companyId) {
            $sql .= " AND c.id = ?";
            $params[] = $companyId;
        }

        $sql .= " GROUP BY c.id ORDER BY total_jobs DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $companyId ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
