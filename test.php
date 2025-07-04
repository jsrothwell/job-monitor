<?php
// api/system-check.php - System status check endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $checks = [];

    // Check database connection
    try {
        require_once __DIR__ . '/../src/Database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        $stmt = $pdo->query("SELECT 1");
        $checks['database'] = ['success' => true, 'message' => 'Database connected'];
    } catch (Exception $e) {
        $checks['database'] = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }

    // Check core files
    $requiredFiles = [
        '../src/Database.php',
        '../src/Company.php',
        '../src/JobScraper.php',
        '../src/JobMonitor.php',
        '../src/Emailer.php'
    ];

    $missingFiles = [];
    foreach ($requiredFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missingFiles[] = basename($file);
        }
    }

    $checks['files'] = [
        'success' => empty($missingFiles),
        'message' => empty($missingFiles) ? 'All core files present' : 'Missing: ' . implode(', ', $missingFiles)
    ];

    // Check API endpoints
    $apiFiles = ['jobs.php', 'stats.php', 'companies.php'];
    $missingApi = [];
    foreach ($apiFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missingApi[] = $file;
        }
    }

    $checks['api'] = [
        'success' => empty($missingApi),
        'message' => empty($missingApi) ? 'All API endpoints available' : 'Missing: ' . implode(', ', $missingApi)
    ];

    // Check companies count
    try {
        if ($checks['database']['success']) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
            $companyCount = $stmt->fetchColumn();
            $checks['companies'] = [
                'success' => $companyCount > 0,
                'message' => "$companyCount active companies",
                'count' => $companyCount
            ];
        } else {
            $checks['companies'] = ['success' => false, 'message' => 'Cannot check - database unavailable'];
        }
    } catch (Exception $e) {
        $checks['companies'] = ['success' => false, 'message' => 'Error checking companies: ' . $e->getMessage()];
    }

    echo json_encode([
        'success' => true,
        'checks' => $checks,
        'overall_status' => array_reduce($checks, function($carry, $check) {
            return $carry && $check['success'];
        }, true)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'checks' => []
    ]);
}
