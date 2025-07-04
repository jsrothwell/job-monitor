<?php
// create-api-files.php - Manual API files creation script
echo "<h2>Creating API Files...</h2>";

$apiDir = __DIR__ . '/api';

// Create API directory
if (!is_dir($apiDir)) {
    if (mkdir($apiDir, 0755, true)) {
        echo "✅ Created API directory<br>";
    } else {
        echo "❌ Failed to create API directory<br>";
        exit;
    }
} else {
    echo "✅ API directory already exists<br>";
}

// Create jobs.php
$jobsContent = '<?php
// api/jobs.php - Jobs API endpoint
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../src/Database.php";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get query parameters
    $search = $_GET["search"] ?? "";
    $limit = min((int)($_GET["limit"] ?? 50), 100);

    $sql = "
        SELECT j.*, c.name as company_name, c.logo_url, c.website_url, c.industry
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN (\"new\", \"existing\")
        AND c.status = \"active\"
    ";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY j.first_seen DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert boolean fields
    foreach ($jobs as &$job) {
        $job["is_remote"] = (bool)$job["is_remote"];
        $job["company_id"] = (int)$job["company_id"];
    }

    echo json_encode([
        "success" => true,
        "jobs" => $jobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}';

if (file_put_contents($apiDir . '/jobs.php', $jobsContent)) {
    echo "✅ Created jobs.php<br>";
} else {
    echo "❌ Failed to create jobs.php<br>";
}

// Create stats.php
$statsContent = '<?php
// api/stats.php - Statistics API endpoint
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../src/Database.php";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get total active jobs
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN (\"new\", \"existing\") AND c.status = \"active\"
    ");
    $totalJobs = $stmt->fetchColumn();

    // Get remote jobs count
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN (\"new\", \"existing\")
        AND c.status = \"active\"
        AND j.is_remote = 1
    ");
    $remoteJobs = $stmt->fetchColumn();

    // Get new jobs this week
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN (\"new\", \"existing\")
        AND c.status = \"active\"
        AND j.first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $newJobs = $stmt->fetchColumn();

    // Get active companies
    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = \"active\"");
    $activeCompanies = $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "stats" => [
            "total_jobs" => (int)$totalJobs,
            "remote_jobs" => (int)$remoteJobs,
            "new_jobs" => (int)$newJobs,
            "active_companies" => (int)$activeCompanies
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}';

if (file_put_contents($apiDir . '/stats.php', $statsContent)) {
    echo "✅ Created stats.php<br>";
} else {
    echo "❌ Failed to create stats.php<br>";
}

// Create companies.php
$companiesContent = '<?php
// api/companies.php - Companies API endpoint
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../src/Database.php";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get companies with job counts
    $stmt = $pdo->query("
        SELECT
            c.*,
            COUNT(j.id) as job_count
        FROM companies c
        LEFT JOIN jobs j ON c.id = j.company_id
            AND j.status IN (\"new\", \"existing\")
        WHERE c.status = \"active\"
        GROUP BY c.id
        ORDER BY job_count DESC, c.name ASC
    ");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert types
    foreach ($companies as &$company) {
        $company["id"] = (int)$company["id"];
        $company["job_count"] = (int)$company["job_count"];
    }

    echo json_encode([
        "success" => true,
        "companies" => $companies
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}';

if (file_put_contents($apiDir . '/companies.php', $companiesContent)) {
    echo "✅ Created companies.php<br>";
} else {
    echo "❌ Failed to create companies.php<br>";
}

echo "<br><h3>✅ API Files Creation Complete!</h3>";
echo "<p><a href='setup.php'>Return to Setup</a> | <a href='index.php'>Go to Dashboard</a></p>";
?>
