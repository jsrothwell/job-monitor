<?php
// sql-test.php - Test MySQL compatibility and troubleshoot SQL issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>MySQL Compatibility Test</h1>";

try {
    require_once __DIR__ . '/src/Database.php';

    $db = new Database();
    $pdo = $db->getConnection();

    echo "<h2>✅ Database Connection: SUCCESS</h2>";

    // Get MySQL version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "<p><strong>MySQL Version:</strong> " . htmlspecialchars($version['version']) . "</p>";

    // Test basic table existence
    echo "<h2>📋 Table Check</h2>";
    $tables = array('companies', 'jobs');
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<p>✅ <strong>$table:</strong> " . $result['count'] . " records</p>";
        } catch (Exception $e) {
            echo "<p>❌ <strong>$table:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // Test potentially problematic SQL functions
    echo "<h2>🧪 SQL Function Tests</h2>";

    $tests = array(
        'NOW()' => "SELECT NOW() as result",
        'CURDATE()' => "SELECT CURDATE() as result",
        'DATE_ADD()' => "SELECT DATE_ADD(CURDATE(), INTERVAL -1 DAY) as result",
        'Basic COUNT' => "SELECT COUNT(*) as result FROM companies",
        'CASE WHEN' => "SELECT CASE WHEN 1=1 THEN 'works' ELSE 'broken' END as result"
    );

    foreach ($tests as $testName => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $result = $stmt->fetch();
            echo "<p>✅ <strong>$testName:</strong> " . htmlspecialchars($result['result']) . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ <strong>$testName:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // Test the specific query that might be failing
    echo "<h2>🎯 JobMonitor Query Tests</h2>";

    $jobMonitorQueries = array(
        'Active Companies' => "SELECT * FROM companies WHERE status = 'active' ORDER BY CASE WHEN last_checked IS NULL THEN 0 ELSE 1 END, last_checked ASC",
        'Company Stats' => "SELECT COUNT(*) as total FROM companies",
        'Jobs Today' => "SELECT COUNT(*) as count FROM jobs WHERE first_seen >= DATE_ADD(CURDATE(), INTERVAL -1 DAY)",
        'Recent Jobs' => "SELECT j.*, c.name as company_name FROM jobs j JOIN companies c ON j.company_id = c.id ORDER BY j.first_seen DESC LIMIT 5"
    );

    foreach ($jobMonitorQueries as $testName => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll();
            echo "<p>✅ <strong>$testName:</strong> " . count($results) . " results</p>";
        } catch (Exception $e) {
            echo "<p>❌ <strong>$testName:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p style='margin-left: 20px; color: #666;'><small>SQL: " . htmlspecialchars($sql) . "</small></p>";
        }
    }

    // Test JobMonitor class if possible
    echo "<h2>🚀 JobMonitor Class Test</h2>";
    try {
        require_once __DIR__ . '/src/Company.php';
        require_once __DIR__ . '/src/JobScraper.php';
        require_once __DIR__ . '/src/Emailer.php';
        require_once __DIR__ . '/src/JobMonitor.php';

        $monitor = new JobMonitor();
        echo "<p>✅ <strong>JobMonitor created successfully</strong></p>";

        // Test getStats method specifically
        try {
            $stats = $monitor->getStats();
            echo "<p>✅ <strong>getStats():</strong> " . json_encode($stats) . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ <strong>getStats() failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        // Test getRecentJobs method
        try {
            $recentJobs = $monitor->getRecentJobs(3);
            echo "<p>✅ <strong>getRecentJobs():</strong> " . count($recentJobs) . " jobs found</p>";
        } catch (Exception $e) {
            echo "<p>❌ <strong>getRecentJobs() failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }

    } catch (Exception $e) {
        echo "<p>❌ <strong>JobMonitor class failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }

} catch (Exception $e) {
    echo "<h2>❌ Database Connection Failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>📝 Recommendations</h2>";
echo "<ul>";
echo "<li>Replace <code>src/JobMonitor.php</code> with the MySQL Compatible version</li>";
echo "<li>Replace <code>src/Company.php</code> with the MySQL Compatible version</li>";
echo "<li>Check the specific error messages above to identify the problematic SQL</li>";
echo "<li>If errors persist, your MySQL version may be very old and need hosting provider assistance</li>";
echo "</ul>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1 { color: #007bff; }
h2 { color: #495057; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
p { margin: 5px 0; }
</style>
