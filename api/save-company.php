<?php
// api/save-company.php - Save/update company
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

try {
    $db = new Database();
    $company = new Company($db);

    $name = $input['name'] ?? '';
    $careers_url = $input['careers_url'] ?? '';
    $selector = $input['selector'] ?? null;
    $location_selector = $input['location_selector'] ?? null;
    $description_selector = $input['description_selector'] ?? null;
    $website_url = $input['website_url'] ?? null;
    $logo_url = $input['logo_url'] ?? null;
    $industry = $input['industry'] ?? null;
    $id = $input['id'] ?? null;

    if (empty($name) || empty($careers_url)) {
        throw new Exception('Company name and careers URL are required');
    }

    if ($id) {
        // Update existing company
        $result = $company->update($id, $name, $careers_url, $selector, $location_selector, $description_selector, $website_url, $logo_url, $industry);
    } else {
        // Add new company
        $result = $company->add($name, $careers_url, $selector, $location_selector, $description_selector, $website_url, $logo_url, $industry);
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Company saved successfully']);
    } else {
        throw new Exception('Failed to save company');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/test-company.php - Test a specific company
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';
require_once __DIR__ . '/../src/JobScraper.php';
require_once __DIR__ . '/../src/JobMonitor.php';

$company_id = $_GET['id'] ?? null;

if (!$company_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}

try {
    $monitor = new JobMonitor();
    $result = $monitor->testCompany($company_id);

    echo json_encode([
        'success' => $result['success'],
        'company' => $result['company'],
        'jobs' => $result['jobs'] ?? [],
        'job_count' => $result['new_jobs'] ?? 0,
        'duration' => $result['duration'] ?? 0,
        'message' => $result['message'] ?? ''
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/test-url.php - Test URL with selectors
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';
require_once __DIR__ . '/../src/JobScraper.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['url'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'URL required']);
    exit;
}

try {
    $db = new Database();
    $company = new Company($db);
    $scraper = new JobScraper($db, $company);

    // Create temporary company data
    $tempCompany = [
        'id' => 0,
        'name' => 'Test Company',
        'careers_url' => $input['url'],
        'selector' => $input['selector'] ?? null,
        'location_selector' => $input['location_selector'] ?? null,
        'description_selector' => $input['description_selector'] ?? null
    ];

    $startTime = microtime(true);
    $jobs = $scraper->scrapeCompany($tempCompany);
    $endTime = microtime(true);

    if ($jobs === false) {
        throw new Exception('Failed to scrape the website. Please check the URL and selectors.');
    }

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'job_count' => count($jobs),
        'duration' => round($endTime - $startTime, 2),
        'url' => $input['url'],
        'selector' => $input['selector'] ?? 'auto-detect'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/update-company-status.php - Update company status
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Company ID and status required']);
    exit;
}

try {
    $db = new Database();
    $company = new Company($db);

    $result = $company->updateStatus($input['id'], $input['status']);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        throw new Exception('Failed to update status');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/delete-company.php - Delete company
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';

$company_id = $_GET['id'] ?? null;

if (!$company_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}

try {
    $db = new Database();
    $company = new Company($db);

    $result = $company->delete($company_id);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Company deleted successfully']);
    } else {
        throw new Exception('Failed to delete company');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/run-all-feeds.php - Run all active feeds
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';
require_once __DIR__ . '/../src/JobScraper.php';
require_once __DIR__ . '/../src/Emailer.php';
require_once __DIR__ . '/../src/JobMonitor.php';

try {
    $monitor = new JobMonitor();
    $results = $monitor->runManual();

    echo json_encode([
        'success' => true,
        'companies_checked' => $results['companies_checked'],
        'total_new_jobs' => $results['total_new_jobs'],
        'emails_sent' => $results['emails_sent'],
        'errors' => $results['errors'],
        'duration' => $results['duration'],
        'details' => $results['details']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/activate-all-feeds.php - Activate all feeds
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../src/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("UPDATE companies SET status = 'active'");
    $result = $stmt->execute();
    $affected = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Activated $affected companies",
        'affected_count' => $affected
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/clean-old-jobs.php - Clean old jobs
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../src/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Mark jobs as removed if not seen in 30 days
    $stmt = $pdo->prepare("
        UPDATE jobs
        SET status = 'removed'
        WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status != 'removed'
    ");
    $stmt->execute();
    $removedCount = $stmt->rowCount();

    // Delete jobs marked as removed for more than 90 days
    $stmt = $pdo->prepare("
        DELETE FROM jobs
        WHERE status = 'removed'
        AND last_seen < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Marked $removedCount jobs as removed, deleted $deletedCount old jobs",
        'removed_count' => $removedCount,
        'deleted_count' => $deletedCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/job-alerts.php - Manage job alerts
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

require_once __DIR__ . '/../src/Database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    switch ($method) {
        case 'GET':
            // Get user's alerts
            $user_id = $_GET['user_id'] ?? session_id();

            $stmt = $pdo->prepare("
                SELECT * FROM job_alerts
                WHERE email = ? OR user_identifier = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id, $user_id]);
            $alerts = $stmt->fetchAll();

            echo json_encode(['success' => true, 'alerts' => $alerts]);
            break;

        case 'POST':
            // Create new alert
            $input = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                INSERT INTO job_alerts (
                    email, keywords, location_filter, remote_only,
                    company_ids, min_salary, max_salary, job_types
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $input['email'],
                json_encode($input['keywords'] ?? []),
                $input['location_filter'] ?? null,
                $input['remote_only'] ?? false,
                json_encode($input['company_ids'] ?? []),
                $input['min_salary'] ?? null,
                $input['max_salary'] ?? null,
                json_encode($input['job_types'] ?? [])
            ]);

            echo json_encode(['success' => $result, 'message' => 'Alert created successfully']);
            break;

        case 'DELETE':
            // Delete alert
            $alert_id = $_GET['id'] ?? null;

            if ($alert_id) {
                $stmt = $pdo->prepare("DELETE FROM job_alerts WHERE id = ?");
                $result = $stmt->execute([$alert_id]);

                echo json_encode(['success' => $result, 'message' => 'Alert deleted successfully']);
            } else {
                throw new Exception('Alert ID required');
            }
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// api/export-jobs.php - Export jobs data
<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="jobs_export_' . date('Y-m-d') . '.csv"');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../src/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $location = $_GET['location'] ?? '';
    $remote_only = isset($_GET['remote_only']) ? (bool)$_GET['remote_only'] : false;
    $job_type = $_GET['job_type'] ?? '';
    $experience = $_GET['experience'] ?? '';
    $department = $_GET['department'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 1000), 5000); // Max 5000 for export

    // Build query (similar to jobs.php but for export)
    $sql = "
        SELECT
            j.title,
            c.name as company_name,
            c.industry,
            j.location,
            j.job_type,
            j.experience_level,
            j.department,
            j.salary_range,
            j.is_remote,
            j.url,
            j.first_seen,
            j.last_seen,
            j.status
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
    ";

    $params = [];

    // Add filters (same logic as jobs.php)
    if (!empty($search)) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($location)) {
        $sql .= " AND j.location LIKE ?";
        $params[] = "%$location%";
    }

    if ($remote_only) {
        $sql .= " AND j.is_remote = 1";
    }

    if (!empty($job_type)) {
        $sql .= " AND j.job_type = ?";
        $params[] = $job_type;
    }

    if (!empty($experience)) {
        $sql .= " AND j.experience_level = ?";
        $params[] = $experience;
    }

    if (!empty($department)) {
        $sql .= " AND j.department = ?";
        $params[] = $department;
    }

    $sql .= " ORDER BY j.first_seen DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Output CSV headers
    $headers = [
        'Job Title', 'Company', 'Industry', 'Location', 'Job Type',
        'Experience Level', 'Department', 'Salary Range', 'Remote',
        'URL', 'First Seen', 'Last Seen', 'Status'
    ];

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    // Output data
    while ($row = $stmt->fetch()) {
        $row['is_remote'] = $row['is_remote'] ? 'Yes' : 'No';
        fputcsv($output, array_values($row));
    }

    fclose($output);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
