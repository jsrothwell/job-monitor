<?php
class Emailer {
    private $config;
    private $db;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';

        // Initialize database for alert management
        try {
            require_once __DIR__ . '/Database.php';
            $this->db = new Database();
        } catch (Exception $e) {
            // Database not available, alerts won't work but basic emails will
            $this->db = null;
        }
    }

    /**
     * Send traditional job alert for new jobs at a company
     */
    public function sendJobAlert($companyName, $newJobs) {
        if (empty($newJobs)) return false;

        $subject = "🎯 New Jobs at $companyName - " . count($newJobs) . " position" . (count($newJobs) > 1 ? 's' : '');

        $body = $this->buildJobAlertEmail($companyName, $newJobs);

        return $this->sendEmail($this->config['email']['to'], $subject, $body);
    }

    /**
     * Send custom job alert (for targeted alerts)
     */
    public function sendCustomAlert($to, $subject, $body) {
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Send targeted job alert based on user preferences
     */
    public function sendTargetedAlert($alertConfig, $jobs, $companyData) {
        if (empty($jobs)) return false;

        $subject = $this->buildAlertSubject($alertConfig, $jobs);
        $body = $this->buildTargetedAlertEmail($alertConfig, $jobs, $companyData);

        return $this->sendEmail($alertConfig['email'], $subject, $body, true);
    }

    /**
     * Send digest email with weekly job summary
     */
    public function sendWeeklyDigest($email, $stats, $topJobs, $companies) {
        $subject = "📊 Weekly Job Digest - " . $stats['total_new_jobs'] . " new positions";

        $body = $this->buildDigestEmail($stats, $topJobs, $companies);

        return $this->sendEmail($email, $subject, $body, true);
    }

    /**
     * Send system notification (errors, updates, etc.)
     */
    public function sendSystemNotification($type, $message, $details = []) {
        if (!isset($this->config['email']['admin_notifications']) ||
            !$this->config['email']['admin_notifications']) {
            return false;
        }

        $adminEmail = $this->config['email']['admin_email'] ?? $this->config['email']['to'];

        $subject = "🔧 Job Feed System: " . ucfirst($type);
        $body = $this->buildSystemNotificationEmail($type, $message, $details);

        return $this->sendEmail($adminEmail, $subject, $body);
    }

    /**
     * Build traditional job alert email
     */
    private function buildJobAlertEmail($companyName, $newJobs) {
        $body = "New job postings found at $companyName:\n\n";

        foreach ($newJobs as $job) {
            $body .= "🔹 {$job['title']}\n";

            if (!empty($job['location'])) {
                $body .= "   📍 Location: {$job['location']}\n";
            }

            if ($job['is_remote']) {
                $body .= "   🏠 Remote Work Available\n";
            }

            if (!empty($job['job_type']) && $job['job_type'] !== 'unknown') {
                $body .= "   💼 Type: " . ucfirst($job['job_type']) . "\n";
            }

            if (!empty($job['department'])) {
                $body .= "   🏢 Department: " . ucfirst($job['department']) . "\n";
            }

            if (!empty($job['salary_range'])) {
                $body .= "   💰 Salary: {$job['salary_range']}\n";
            }

            if (!empty($job['url'])) {
                $body .= "   🔗 Apply: {$job['url']}\n";
            }

            $body .= "\n";
        }

        $body .= "---\n";
        $body .= "🤖 Job Feed Aggregator Alert System\n";
        $body .= "Found " . count($newJobs) . " new position" . (count($newJobs) > 1 ? 's' : '') . " at $companyName\n\n";

        if (count($newJobs) > 1) {
            $remoteCount = count(array_filter($newJobs, function($job) { return $job['is_remote']; }));
            if ($remoteCount > 0) {
                $body .= "🏠 $remoteCount remote-friendly position" . ($remoteCount > 1 ? 's' : '') . "\n";
            }

            $departments = array_filter(array_unique(array_column($newJobs, 'department')));
            if (!empty($departments)) {
                $body .= "🏢 Departments: " . implode(', ', array_map('ucfirst', $departments)) . "\n";
            }
        }

        return $body;
    }

    /**
     * Build targeted alert email
     */
    private function buildTargetedAlertEmail($alertConfig, $jobs, $companyData) {
        $keywords = json_decode($alertConfig['keywords'] ?? '[]', true) ?: [];

        $body = "🎯 Your job alert found " . count($jobs) . " matching position" . (count($jobs) > 1 ? 's' : '') . "!\n\n";

        if (!empty($keywords)) {
            $body .= "Keywords: " . implode(', ', $keywords) . "\n";
        }

        if (!empty($alertConfig['location_filter'])) {
            $body .= "Location: {$alertConfig['location_filter']}\n";
        }

        if ($alertConfig['remote_only']) {
            $body .= "Remote only: Yes\n";
        }

        $body .= "\n" . str_repeat("=", 50) . "\n\n";

        foreach ($jobs as $job) {
            $body .= "📌 {$job['title']}\n";
            $body .= "🏢 Company: {$companyData['name']}\n";

            if (!empty($job['location'])) {
                $body .= "📍 Location: {$job['location']}\n";
            }

            if ($job['is_remote']) {
                $body .= "🏠 Remote Work: Available\n";
            }

            if (!empty($job['job_type']) && $job['job_type'] !== 'unknown') {
                $body .= "💼 Type: " . ucfirst($job['job_type']) . "\n";
            }

            if (!empty($job['experience_level']) && $job['experience_level'] !== 'unknown') {
                $body .= "📈 Level: " . ucfirst($job['experience_level']) . "\n";
            }

            if (!empty($job['salary_range'])) {
                $body .= "💰 Salary: {$job['salary_range']}\n";
            }

            if (!empty($job['description'])) {
                $description = substr($job['description'], 0, 200);
                if (strlen($job['description']) > 200) {
                    $description .= "...";
                }
                $body .= "📝 Description: $description\n";
            }

            if (!empty($job['url'])) {
                $body .= "🔗 Apply Now: {$job['url']}\n";
            }

            $body .= "\n" . str_repeat("-", 30) . "\n\n";
        }

        $body .= "💡 This alert was triggered by your saved job search criteria.\n";
        $body .= "To manage your alerts, visit your job feed dashboard.\n\n";
        $body .= "Happy job hunting! 🚀";

        return $body;
    }

    /**
     * Build weekly digest email
     */
    private function buildDigestEmail($stats, $topJobs, $companies) {
        $body = "📊 Your Weekly Job Market Digest\n\n";

        // Stats summary
        $body .= "📈 WEEKLY STATS\n";
        $body .= str_repeat("=", 30) . "\n";
        $body .= "• Total new jobs: {$stats['total_new_jobs']}\n";
        $body .= "• Remote positions: {$stats['remote_jobs']}\n";
        $body .= "• Active companies: {$stats['active_companies']}\n";
        $body .= "• Most active day: {$stats['most_active_day'] ?? 'N/A'}\n\n";

        // Top companies
        if (!empty($companies)) {
            $body .= "🏢 MOST ACTIVE COMPANIES\n";
            $body .= str_repeat("=", 30) . "\n";

            foreach (array_slice($companies, 0, 5) as $company) {
                $body .= "• {$company['name']}: {$company['new_jobs_count']} new positions\n";
            }
            $body .= "\n";
        }

        // Top job titles
        if (!empty($topJobs)) {
            $body .= "🔥 TRENDING POSITIONS\n";
            $body .= str_repeat("=", 30) . "\n";

            foreach (array_slice($topJobs, 0, 10) as $job) {
                $body .= "• {$job['title']} at {$job['company_name']}\n";
                if ($job['location']) {
                    $body .= "  📍 {$job['location']}\n";
                }
                if ($job['is_remote']) {
                    $body .= "  🏠 Remote available\n";
                }
                $body .= "\n";
            }
        }

        $body .= "---\n";
        $body .= "🤖 Generated by Job Feed Aggregator\n";
        $body .= "Stay ahead of the job market with real-time updates!\n";

        return $body;
    }

    /**
     * Build system notification email
     */
    private function buildSystemNotificationEmail($type, $message, $details) {
        $icons = [
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'success' => '✅'
        ];

        $icon = $icons[$type] ?? '🔧';

        $body = "$icon SYSTEM NOTIFICATION\n\n";
        $body .= "Type: " . ucfirst($type) . "\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Message: $message\n\n";

        if (!empty($details)) {
            $body .= "Details:\n";
            foreach ($details as $key => $value) {
                $body .= "• $key: $value\n";
            }
            $body .= "\n";
        }

        $body .= "---\n";
        $body .= "Job Feed Aggregator System\n";

        return $body;
    }

    /**
     * Build alert subject line
     */
    private function buildAlertSubject($alertConfig, $jobs) {
        $keywords = json_decode($alertConfig['keywords'] ?? '[]', true) ?: [];
        $keywordText = !empty($keywords) ? ' for "' . implode(', ', array_slice($keywords, 0, 2)) . '"' : '';

        $count = count($jobs);
        $plural = $count > 1 ? 's' : '';

        return "🎯 $count Job Alert$plural$keywordText";
    }

    /**
     * Process and send all pending job alerts
     */
    public function processPendingAlerts() {
        if (!$this->db) return false;

        $pdo = $this->db->getConnection();

        // Get active alerts that haven't been sent recently
        $stmt = $pdo->query("
            SELECT * FROM job_alerts
            WHERE is_active = 1
            AND (last_sent IS NULL OR last_sent < DATE_SUB(NOW(), INTERVAL 1 HOUR))
            ORDER BY created_at ASC
        ");

        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $processed = 0;

        foreach ($alerts as $alert) {
            try {
                $matchingJobs = $this->findMatchingJobs($alert);

                if (!empty($matchingJobs)) {
                    $this->sendJobMatchAlert($alert, $matchingJobs);

                    // Update last sent timestamp
                    $updateStmt = $pdo->prepare("
                        UPDATE job_alerts
                        SET last_sent = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$alert['id']]);

                    $processed++;
                }
            } catch (Exception $e) {
                error_log("Error processing alert {$alert['id']}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Find jobs matching alert criteria
     */
    private function findMatchingJobs($alert) {
        if (!$this->db) return [];

        $pdo = $this->db->getConnection();

        // Get recent jobs (last 24 hours)
        $sql = "
            SELECT j.*, c.name as company_name, c.logo_url, c.industry
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE j.status IN ('new', 'existing')
            AND c.status = 'active'
            AND j.first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";

        $params = [];

        // Apply alert filters
        if (!empty($alert['location_filter'])) {
            $sql .= " AND j.location LIKE ?";
            $params[] = "%{$alert['location_filter']}%";
        }

        if ($alert['remote_only']) {
            $sql .= " AND j.is_remote = 1";
        }

        if (!empty($alert['company_ids'])) {
            $companyIds = json_decode($alert['company_ids'], true);
            if (!empty($companyIds)) {
                $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
                $sql .= " AND c.id IN ($placeholders)";
                $params = array_merge($params, $companyIds);
            }
        }

        if (!empty($alert['job_types'])) {
            $jobTypes = json_decode($alert['job_types'], true);
            if (!empty($jobTypes)) {
                $placeholders = str_repeat('?,', count($jobTypes) - 1) . '?';
                $sql .= " AND j.job_type IN ($placeholders)";
                $params = array_merge($params, $jobTypes);
            }
        }

        $sql .= " ORDER BY j.first_seen DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter by keywords
        if (!empty($alert['keywords'])) {
            $keywords = json_decode($alert['keywords'], true);
            if (!empty($keywords)) {
                $jobs = array_filter($jobs, function($job) use ($keywords) {
                    $jobText = strtolower($job['title'] . ' ' . ($job['description'] ?? ''));

                    foreach ($keywords as $keyword) {
                        if (strpos($jobText, strtolower($keyword)) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
            }
        }

        return array_values($jobs);
    }

    /**
     * Send job match alert
     */
    private function sendJobMatchAlert($alert, $jobs) {
        $subject = $this->buildAlertSubject($alert, $jobs);
        $body = $this->buildMatchAlertEmail($alert, $jobs);

        return $this->sendEmail($alert['email'], $subject, $body, true);
    }

    /**
     * Build match alert email
     */
    private function buildMatchAlertEmail($alert, $jobs) {
        $keywords = json_decode($alert['keywords'] ?? '[]', true) ?: [];

        $body = "🎯 Your job alert found " . count($jobs) . " new matching position" . (count($jobs) > 1 ? 's' : '') . "!\n\n";

        // Alert criteria summary
        $body .= "📋 ALERT CRITERIA\n";
        $body .= str_repeat("=", 20) . "\n";

        if (!empty($keywords)) {
            $body .= "Keywords: " . implode(', ', $keywords) . "\n";
        }

        if (!empty($alert['location_filter'])) {
            $body .= "Location: {$alert['location_filter']}\n";
        }

        if ($alert['remote_only']) {
            $body .= "Remote only: Yes\n";
        }

        $body .= "\n📌 MATCHING JOBS\n";
        $body .= str_repeat("=", 20) . "\n\n";

        foreach ($jobs as $job) {
            $body .= "• {$job['title']}\n";
            $body .= "  🏢 {$job['company_name']}\n";

            if ($job['location']) {
                $body .= "  📍 {$job['location']}\n";
            }

            if ($job['is_remote']) {
                $body .= "  🏠 Remote work available\n";
            }

            if ($job['salary_range']) {
                $body .= "  💰 {$job['salary_range']}\n";
            }

            if ($job['url']) {
                $body .= "  🔗 {$job['url']}\n";
            }

            $body .= "\n";
        }

        $body .= "---\n";
        $body .= "💡 Manage your alerts: [Dashboard URL]\n";
        $body .= "🤖 Job Feed Aggregator - Stay ahead of opportunities!\n";

        return $body;
    }

    /**
     * Core email sending function
     */
    private function sendEmail($to, $subject, $body, $isHtml = false) {
        try {
            $headers = [
                'From: ' . $this->config['email']['user'],
                'Reply-To: ' . $this->config['email']['user'],
                'X-Mailer: Job Feed Aggregator v2.0'
            ];

            if ($isHtml) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $body = nl2br(htmlspecialchars($body));
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            }

            $success = mail($to, $subject, $body, implode("\r\n", $headers));

            if ($success) {
                error_log("Email sent successfully: $subject to $to");
                return true;
            } else {
                error_log("Failed to send email: $subject to $to");
                return false;
            }

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfig() {
        $testSubject = "🧪 Job Feed Test Email";
        $testBody = "This is a test email from your Job Feed Aggregator.\n\n";
        $testBody .= "If you received this email, your email configuration is working correctly!\n\n";
        $testBody .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $testBody .= "System: Job Feed Aggregator v2.0";

        return $this->sendEmail($this->config['email']['to'], $testSubject, $testBody);
    }

    /**
     * Create a new job alert
     */
    public function createJobAlert($email, $criteria) {
        if (!$this->db) return false;

        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO job_alerts (
                email, keywords, location_filter, remote_only,
                company_ids, min_salary, max_salary, job_types
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $email,
            json_encode($criteria['keywords'] ?? []),
            $criteria['location_filter'] ?? null,
            $criteria['remote_only'] ?? false,
            json_encode($criteria['company_ids'] ?? []),
            $criteria['min_salary'] ?? null,
            $criteria['max_salary'] ?? null,
            json_encode($criteria['job_types'] ?? [])
        ]);
    }

    /**
     * Get email statistics
     */
    public function getEmailStats() {
        if (!$this->db) return [];

        $pdo = $this->db->getConnection();

        $stats = [];

        // Total active alerts
        $stmt = $pdo->query("SELECT COUNT(*) FROM job_alerts WHERE is_active = 1");
        $stats['active_alerts'] = $stmt->fetchColumn();

        // Alerts sent today
        $stmt = $pdo->query("SELECT COUNT(*) FROM job_alerts WHERE last_sent >= CURDATE()");
        $stats['alerts_sent_today'] = $stmt->fetchColumn();

        // Most popular keywords
        $stmt = $pdo->query("
            SELECT keywords, COUNT(*) as count
            FROM job_alerts
            WHERE is_active = 1 AND keywords IS NOT NULL
            GROUP BY keywords
            ORDER BY count DESC
            LIMIT 5
        ");
        $stats['popular_keywords'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}
