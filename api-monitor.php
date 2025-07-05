<?php
// api-monitor.php - Simple monitoring API
error_reporting(0); // Suppress all PHP errors to prevent HTML output
ini_set('display_errors', 0);

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
        // Run monitoring in simplified mode
        $monitor = new JobMonitor();
        $results = $monitor->runManual();

        jsonResponse(array(
            'success' => true,
            'results' => $results,
            'message' => "Found {$results['total_new_jobs']} new jobs across {$results['companies_checked']} companies"
        ));

    } elseif ($action === 'test') {
        // Simple test endpoint
        jsonResponse(array(
            'success' => true,
            'message' => 'API is working',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } else {
        jsonError('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    jsonError('Exception: ' . $e->getMessage());
} catch (Error $e) {
    jsonError('Fatal error: ' . $e->getMessage());
}
