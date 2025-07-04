<?php
// api/stats.php - Statistics API endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../src/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get total active jobs
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing') AND c.status = 'active'
    ");
    $totalJobs = $stmt->fetchColumn();

    // Get remote jobs count
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
        AND j.is_remote = 1
    ");
    $remoteJobs = $stmt->fetchColumn();

    // Get new jobs this week
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
        AND j.first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $newJobs = $stmt->fetchColumn();

    // Get active companies
    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
    $activeCompanies = $stmt->fetchColumn();

    // Get department breakdown
    $stmt = $pdo->query("
        SELECT j.department, COUNT(*) as count
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
        AND j.department IS NOT NULL
        GROUP BY j.department
        ORDER BY count DESC
        LIMIT 10
    ");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top locations
    $stmt = $pdo->query("
        SELECT j.location, COUNT(*) as count
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
        AND j.location IS NOT NULL
        AND j.location != ''
        GROUP BY j.location
        ORDER BY count DESC
        LIMIT 10
    ");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_jobs' => (int)$totalJobs,
            'remote_jobs' => (int)$remoteJobs,
            'new_jobs' => (int)$newJobs,
            'active_companies' => (int)$activeCompanies,
            'departments' => $departments,
            'locations' => $locations
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
