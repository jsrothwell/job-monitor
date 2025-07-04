<?php
// api/companies.php - Companies API endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../src/Database.php';

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
            AND j.status IN ('new', 'existing')
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY job_count DESC, c.name ASC
    ");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert types
    foreach ($companies as &$company) {
        $company['id'] = (int)$company['id'];
        $company['job_count'] = (int)$company['job_count'];
    }

    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
