<?php
// api/jobs.php - Jobs API endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../src/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get query parameters
    $search = $_GET['search'] ?? '';
    $location = $_GET['location'] ?? '';
    $remote_only = isset($_GET['remote_only']) ? (bool)$_GET['remote_only'] : false;
    $job_type = $_GET['job_type'] ?? '';
    $experience = $_GET['experience'] ?? '';
    $department = $_GET['department'] ?? '';
    $company_ids = isset($_GET['company_ids']) ? explode(',', $_GET['company_ids']) : [];
    $sort_by = $_GET['sort_by'] ?? 'newest';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    // Build the base query
    $sql = "
        SELECT
            j.*,
            c.name as company_name,
            c.logo_url,
            c.website_url,
            c.industry
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status IN ('new', 'existing')
        AND c.status = 'active'
    ";

    $params = [];

    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Add location filter
    if (!empty($location)) {
        $sql .= " AND j.location LIKE ?";
        $params[] = "%$location%";
    }

    // Add remote filter
    if ($remote_only) {
        $sql .= " AND j.is_remote = 1";
    }

    // Add job type filter
    if (!empty($job_type)) {
        $sql .= " AND j.job_type = ?";
        $params[] = $job_type;
    }

    // Add experience filter
    if (!empty($experience)) {
        $sql .= " AND j.experience_level = ?";
        $params[] = $experience;
    }

    // Add department filter
    if (!empty($department)) {
        $sql .= " AND j.department = ?";
        $params[] = $department;
    }

    // Add company filter
    if (!empty($company_ids)) {
        $placeholders = str_repeat('?,', count($company_ids) - 1) . '?';
        $sql .= " AND c.id IN ($placeholders)";
        $params = array_merge($params, $company_ids);
    }

    // Add sorting
    switch ($sort_by) {
        case 'oldest':
            $sql .= " ORDER BY j.first_seen ASC";
            break;
        case 'company':
            $sql .= " ORDER BY c.name ASC, j.first_seen DESC";
            break;
        case 'title':
            $sql .= " ORDER BY j.title ASC";
            break;
        default: // newest
            $sql .= " ORDER BY j.first_seen DESC";
    }

    // Add pagination
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert boolean fields
    foreach ($jobs as &$job) {
        $job['is_remote'] = (bool)$job['is_remote'];
        $job['is_featured'] = (bool)$job['is_featured'];
        $job['company_id'] = (int)$job['company_id'];
    }

    // Get total count for pagination
    $countSql = str_replace(
        "SELECT j.*, c.name as company_name, c.logo_url, c.website_url, c.industry FROM",
        "SELECT COUNT(*) FROM",
        $sql
    );
    $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
    $countSql = preg_replace('/LIMIT.*$/', '', $countSql);

    $countParams = array_slice($params, 0, -2); // Remove limit and offset params
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
