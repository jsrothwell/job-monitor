<?php
// api-monitor-robust.php - Robust monitoring API with timeouts
error_reporting(0); // Suppress all PHP errors to prevent HTML output
ini_set('display_errors', 0);
set_time_limit(120); // 2 minute maximum execution time

// Ensure we always output JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache');

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function jsonError($message) {
    jsonResponse(array('error' => $message));
}

// Basic file existence check
$requiredFiles = array(
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php'
);

foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        jsonError("Missing required file: $file");
    }
}

// Check for action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';
if (empty($action)) {
    jsonError('No action specified');
}

try {
    // Load required classes
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Company.php';
    require_once __DIR__ . '/src/JobScraper.php';
    require_once __DIR__ . '/src/Emailer.php';
    require_once __DIR__ . '/src/JobMonitor.php';

    if ($action === 'run') {
        // Run monitoring in robust mode
        $monitor = new RobustJobMonitor();
        $results = $monitor->runWithTimeouts();

        jsonResponse(array(
            'success' => true,
            'results' => $results,
            'message' => "Completed monitoring: {$results['companies_checked']} companies checked, {$results['total_new_jobs']} new jobs found"
        ));

    } elseif ($action === 'test') {
        // Simple test endpoint
        jsonResponse(array(
            'success' => true,
            'message' => 'API is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ));

    } elseif ($action === 'quick_test') {
        // Quick test of first company only
        $monitor = new RobustJobMonitor();
        $result = $monitor->testFirstCompany();

        jsonResponse(array(
            'success' => true,
            'test_result' => $result
        ));

    } else {
        jsonError('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    jsonError('Exception: ' . $e->getMessage());
} catch (Error $e) {
    jsonError('Fatal error: ' . $e->getMessage());
}

class RobustJobMonitor extends JobMonitor {

    public function runWithTimeouts() {
        $companies = $this->company->getActive();
        $startTime = microtime(true);

        $results = array(
            'companies_checked' => 0,
            'total_new_jobs' => 0,
            'emails_sent' => 0,
            'errors' => 0,
            'timeouts' => 0,
            'details' => array(),
            'start_time' => $startTime
        );

        if (empty($companies)) {
            $results['message'] = 'No active companies found';
            $results['end_time'] = microtime(true);
            $results['duration'] = 0;
            return $results;
        }

        foreach ($companies as $companyData) {
            $companyName = $companyData['name'];
            $results['companies_checked']++;

            try {
                // Set a timeout for each company
                $companyStartTime = microtime(true);

                // Skip if we've been running too long
                if ((microtime(true) - $startTime) > 100) { // 100 second total limit
                    $results['details'][$companyName] = array(
                        'success' => false,
                        'error' => 'Skipped due to time limit',
                        'new_jobs' => 0
                    );
                    continue;
                }

                $newJobs = $this->scrapeWithTimeout($companyData, 15); // 15 second per company limit

                if ($newJobs === false) {
                    $results['errors']++;
                    $results['details'][$companyName] = array(
                        'success' => false,
                        'error' => 'Failed to scrape website (timeout or blocking)',
                        'new_jobs' => 0,
                        'duration' => round(microtime(true) - $companyStartTime, 2)
                    );
                } elseif ($newJobs === 'timeout') {
                    $results['timeouts']++;
                    $results['details'][$companyName] = array(
                        'success' => false,
                        'error' => 'Request timed out after 15 seconds',
                        'new_jobs' => 0,
                        'duration' => 15
                    );
                } else {
                    $jobCount = count($newJobs);
                    $results['total_new_jobs'] += $jobCount;

                    if (!empty($newJobs)) {
                        try {
                            $this->emailer->sendJobAlert($companyName, $newJobs);
                            $results['emails_sent']++;
                        } catch (Exception $e) {
                            // Email failed but don't stop monitoring
                        }
                    }

                    $results['details'][$companyName] = array(
                        'success' => true,
                        'new_jobs' => $jobCount,
                        'jobs' => $newJobs,
                        'duration' => round(microtime(true) - $companyStartTime, 2)
                    );
                }

            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][$companyName] = array(
                    'success' => false,
                    'error' => $e->getMessage(),
                    'new_jobs' => 0,
                    'duration' => round(microtime(true) - $companyStartTime, 2)
                );
            }

            // Small delay between companies to be nice to servers
            if ($results['companies_checked'] < count($companies)) {
                sleep(1);
            }
        }

        $results['end_time'] = microtime(true);
        $results['duration'] = round($results['end_time'] - $results['start_time'], 2);

        return $results;
    }

    private function scrapeWithTimeout($companyData, $timeoutSeconds) {
        // Create a simple timeout mechanism
        $startTime = time();

        try {
            // Use the original scraper but check time periodically
            $url = $companyData['careers_url'];
            $html = $this->fetchPageWithTimeout($url, $timeoutSeconds);

            if (!$html) {
                return false;
            }

            // Quick timeout check
            if ((time() - $startTime) >= $timeoutSeconds) {
                return 'timeout';
            }

            // Parse and extract jobs (simplified)
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Use a simple job detection
            $elements = $xpath->query("//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job')]");

            $jobs = array();
            $count = 0;
            foreach ($elements as $element) {
                if ($count >= 20) break; // Limit processing time

                $title = trim($element->textContent);
                if (strlen($title) > 3 && strlen($title) < 200) {
                    $href = $element->getAttribute('href');
                    $url = $this->makeAbsoluteUrl($href, $companyData['careers_url']);

                    $job = array(
                        'title' => $title,
                        'url' => $url,
                        'content_hash' => hash('sha256', $title . $url)
                    );

                    // Check if it's actually new
                    if ($this->isNewJob($companyData['id'], $job)) {
                        $this->saveJob($companyData['id'], $job);
                        $jobs[] = $job;
                    }
                }
                $count++;
            }

            // Update last checked
            $this->company->updateLastChecked($companyData['id']);

            return $jobs;

        } catch (Exception $e) {
            return false;
        }
    }

    private function fetchPageWithTimeout($url, $timeout) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => $timeout,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'follow_location' => true,
                'max_redirects' => 3
            )
        ));

        return @file_get_contents($url, false, $context);
    }

    private function makeAbsoluteUrl($href, $baseUrl) {
        if (empty($href)) return '';
        if (parse_url($href, PHP_URL_SCHEME)) return $href;

        $base = parse_url($baseUrl);
        $scheme = isset($base['scheme']) ? $base['scheme'] : 'https';
        $host = isset($base['host']) ? $base['host'] : '';

        if (strpos($href, '//') === 0) {
            return $scheme . ':' . $href;
        }
        if (strpos($href, '/') === 0) {
            return $scheme . '://' . $host . $href;
        }
        return $scheme . '://' . $host . '/' . ltrim($href, '/');
    }

    private function isNewJob($companyId, $job) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT id FROM jobs WHERE company_id = ? AND content_hash = ?");
            $stmt->execute(array($companyId, $job['content_hash']));
            return !$stmt->fetch();
        } catch (Exception $e) {
            return true; // Assume new if we can't check
        }
    }

    private function saveJob($companyId, $job) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO jobs (company_id, title, url, content_hash, status, first_seen, last_seen)
                VALUES (?, ?, ?, ?, 'new', NOW(), NOW())
            ");
            $stmt->execute(array($companyId, $job['title'], $job['url'], $job['content_hash']));
        } catch (Exception $e) {
            // Ignore save errors for now
        }
    }

    public function testFirstCompany() {
        $companies = $this->company->getActive();

        if (empty($companies)) {
            return array('error' => 'No active companies to test');
        }

        $company = $companies[0];

        try {
            $startTime = microtime(true);
            $result = $this->scrapeWithTimeout($company, 10);
            $duration = round(microtime(true) - $startTime, 2);

            return array(
                'company' => $company['name'],
                'url' => $company['careers_url'],
                'success' => $result !== false && $result !== 'timeout',
                'result_type' => is_array($result) ? 'jobs_found' : $result,
                'job_count' => is_array($result) ? count($result) : 0,
                'duration' => $duration
            );
        } catch (Exception $e) {
            return array(
                'company' => $company['name'],
                'error' => $e->getMessage(),
                'success' => false
            );
        }
    }
}
