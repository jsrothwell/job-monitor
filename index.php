<?php
// index.php - Standalone version (no API required)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check PHP version first
if (version_compare(PHP_VERSION, '5.6.0', '<')) {
    die('This application requires PHP 5.6 or higher. You are running PHP ' . PHP_VERSION);
}

// Initialize variables
$message = '';
$messageType = '';
$runResults = null;

// Check if required files exist
$requiredFiles = array(
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php',
    'config/config.php'
);

foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        die("Missing required file: $file");
    }
}

try {
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Company.php';
    require_once __DIR__ . '/src/JobScraper.php';
    require_once __DIR__ . '/src/Emailer.php';
    require_once __DIR__ . '/src/JobMonitor.php';

    // Initialize database and create tables
    $db = new Database();
    $db->createTables();

    $company = new Company($db);
    $monitor = new JobMonitor();

} catch (Exception $e) {
    die("Initialization error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_company':
                    if (!empty($_POST['company_name']) && !empty($_POST['careers_url'])) {
                        $name = trim($_POST['company_name']);
                        $url = trim($_POST['careers_url']);
                        $selector = !empty($_POST['selector']) ? trim($_POST['selector']) : null;

                        if ($company->add($name, $url, $selector)) {
                            $message = "Company '$name' added successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to add company. Please try again.";
                            $messageType = 'danger';
                        }
                    } else {
                        $message = "Please fill in all required fields.";
                        $messageType = 'warning';
                    }
                    break;

                case 'run_monitor':
                    // Use the simple manual run with timeout protection
                    set_time_limit(120); // 2 minute limit

                    try {
                        $runResults = $monitor->runManual();
                        $message = "Monitoring completed! Checked {$runResults['companies_checked']} companies, found {$runResults['total_new_jobs']} new jobs in {$runResults['duration']} seconds.";
                        $messageType = $runResults['total_new_jobs'] > 0 ? 'success' : 'info';
                    } catch (Exception $e) {
                        $message = "Monitoring error: " . $e->getMessage();
                        $messageType = 'danger';
                    }
                    break;

                case 'delete_company':
                    if (!empty($_POST['company_id'])) {
                        if ($company->delete($_POST['company_id'])) {
                            $message = "Company deleted successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete company.";
                            $messageType = 'danger';
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get data for display
try {
    $companies = $company->getAll();
    $stats = $monitor->getStats();
    $recentJobs = $monitor->getRecentJobs(5);
} catch (Exception $e) {
    $companies = array();
    $stats = array();
    $recentJobs = array();
    if (empty($message)) {
        $message = "Warning: " . $e->getMessage();
        $messageType = 'warning';
    }
}

// Safely get values with fallbacks for older PHP
function getValue($array, $key, $default = 0) {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor Dashboard</title>

    <!-- Google Fonts - Libre Franklin -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Libre Franklin', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .job-item {
            border-left: 4px solid #007bff;
        }
        .navbar-brand, .card-title, .display-5 {
            font-weight: 600;
        }
        .h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 {
            font-family: 'Libre Franklin', sans-serif;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-search me-2"></i>
                Job Monitor
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="test.php">
                    <i class="bi bi-tools me-1"></i>
                    Test Tool
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-5">
        <div class="container text-center">
            <h1 class="display-5 fw-bold mb-3">
                <i class="bi bi-briefcase me-3"></i>
                Job Monitor Dashboard
            </h1>
            <p class="lead mb-4">Automatically track job postings from your favorite companies</p>

            <!-- Quick Stats -->
            <div class="row justify-content-center">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card bg-dark bg-opacity-75 rounded p-3 text-center border border-light border-opacity-25">
                        <div class="h3 mb-1 text-primary fw-bold"><?php echo getValue($stats, 'active_companies'); ?></div>
                        <small class="text-light">Active Companies</small>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card bg-dark bg-opacity-75 rounded p-3 text-center border border-light border-opacity-25">
                        <div class="h3 mb-1 text-success fw-bold"><?php echo getValue($stats, 'total_jobs'); ?></div>
                        <small class="text-light">Total Jobs Tracked</small>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card bg-dark bg-opacity-75 rounded p-3 text-center border border-light border-opacity-25">
                        <div class="h3 mb-1 text-warning fw-bold"><?php echo getValue($stats, 'new_jobs_today'); ?></div>
                        <small class="text-light">New Today</small>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card bg-dark bg-opacity-75 rounded p-3 text-center border border-light border-opacity-25">
                        <div class="h3 mb-1 text-info fw-bold">
                            <?php
                            $lastRun = getValue($stats, 'last_run', null);
                            echo $lastRun ? date('H:i', strtotime($lastRun)) : 'Never';
                            ?>
                        </div>
                        <small class="text-light">Last Check</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Company Form -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add Company
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="addCompanyForm">
                            <input type="hidden" name="action" value="add_company">

                            <div class="mb-3">
                                <label for="companyName" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="companyName" name="company_name" required
                                       placeholder="e.g., Netflix, Google, etc.">
                            </div>

                            <div class="mb-3">
                                <label for="careersUrl" class="form-label">Careers Page URL</label>
                                <input type="url" class="form-control" id="careersUrl" name="careers_url" required
                                       placeholder="https://company.com/careers">
                            </div>

                            <div class="mb-3">
                                <label for="selector" class="form-label">CSS Selector (Optional)</label>
                                <input type="text" class="form-control" id="selector" name="selector"
                                       placeholder="a[href*='job'], .job-listing, etc.">
                                <div class="form-text">Leave empty for auto-detection</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus me-1"></i>
                                Add Company
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Manual Run & Recent Jobs -->
            <div class="col-lg-8 mb-4">
                <!-- Manual Run Section -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-play-circle me-2"></i>
                            Manual Run
                        </h5>
                        <form method="post" class="d-inline" id="manualRunForm">
                            <input type="hidden" name="action" value="run_monitor">
                            <button type="submit" class="btn btn-light btn-sm" id="runBtn">
                                <i class="bi bi-play me-1"></i>
                                Run Now
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <!-- Loading State -->
                        <div id="loadingState" style="display: none;">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <h6 class="text-primary">Monitoring in Progress...</h6>
                                <p class="text-muted mb-0">
                                    Checking companies for new job postings. This may take 1-3 minutes depending on the number of companies.
                                </p>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Please wait while we scrape job listings from your companies...
                                    </small>
                                </div>
                            </div>
                        </div>

                        <?php if ($runResults): ?>
                            <div class="row text-center mb-3">
                                <div class="col-3">
                                    <div class="h5 text-primary"><?php echo $runResults['companies_checked']; ?></div>
                                    <small class="text-muted">Companies</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-success"><?php echo $runResults['total_new_jobs']; ?></div>
                                    <small class="text-muted">New Jobs</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-info"><?php echo $runResults['emails_sent']; ?></div>
                                    <small class="text-muted">Emails Sent</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-warning"><?php echo $runResults['duration']; ?>s</div>
                                    <small class="text-muted">Duration</small>
                                </div>
                            </div>

                            <?php if (!empty($runResults['details'])): ?>
                                <div class="accordion" id="runResultsAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#runDetails">
                                                View Detailed Results
                                            </button>
                                        </h2>
                                        <div id="runDetails" class="accordion-collapse collapse" data-bs-parent="#runResultsAccordion">
                                            <div class="accordion-body">
                                                <?php foreach ($runResults['details'] as $companyName => $result): ?>
                                                    <div class="mb-2">
                                                        <strong><?php echo htmlspecialchars($companyName); ?>:</strong>
                                                        <span class="badge bg-<?php echo $result['success'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $result['success'] ? $result['new_jobs'] . ' new jobs' : 'Failed'; ?>
                                                        </span>
                                                        <?php if (!$result['success']): ?>
                                                            <small class="text-muted d-block"><?php echo htmlspecialchars($result['error']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div id="defaultMessage">
                                <p class="text-muted mb-0">Click "Run Now" to check all companies for new job postings immediately.</p>
                                <div class="mt-2">
                                    <small class="text-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Monitoring typically takes 1-3 minutes depending on the number of companies you've added.
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Jobs -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>
                            Recent Job Discoveries
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentJobs)): ?>
                            <?php foreach ($recentJobs as $job): ?>
                                <div class="job-item bg-light p-3 mb-2 rounded">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-building me-1"></i>
                                                <?php echo htmlspecialchars($job['company_name']); ?>
                                                <i class="bi bi-calendar ms-3 me-1"></i>
                                                <?php echo date('M j, Y', strtotime($job['first_seen'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($job['url'])): ?>
                                            <a href="<?php echo htmlspecialchars($job['url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-arrow-up-right-square"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-inbox display-6 text-muted mb-3"></i>
                                <p class="text-muted">No jobs discovered yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Companies List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-buildings me-2"></i>
                            Monitored Companies (<?php echo count($companies); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($companies)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>URL</th>
                                            <th>Status</th>
                                            <th>Last Checked</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $comp): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($comp['name']); ?></strong>
                                                    <?php if (!empty($comp['selector'])): ?>
                                                        <br><small class="text-muted">Selector: <?php echo htmlspecialchars($comp['selector']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($comp['careers_url']); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo parse_url($comp['careers_url'], PHP_URL_HOST); ?>
                                                        <i class="bi bi-arrow-up-right-square ms-1"></i>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $comp['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($comp['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $comp['last_checked'] ? date('M j, H:i', strtotime($comp['last_checked'])) : 'Never'; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="test.php?company_id=<?php echo $comp['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="bi bi-play"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" onclick="deleteCompany(<?php echo $comp['id']; ?>, '<?php echo htmlspecialchars($comp['name']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                                <h5>No companies added yet</h5>
                                <p class="text-muted">Add your first company using the form above to get started!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteCompanyName"></strong>?</p>
                    <p class="text-muted small">This will also delete all associated job records.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" id="deleteForm">
                        <input type="hidden" name="action" value="delete_company">
                        <input type="hidden" name="company_id" id="deleteCompanyId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function deleteCompany(id, name) {
            document.getElementById('deleteCompanyId').value = id;
            document.getElementById('deleteCompanyName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Add loading states for forms
        document.getElementById('addCompanyForm').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Adding...';
            btn.disabled = true;
        });

        document.getElementById('manualRunForm').addEventListener('submit', function() {
            // Hide default message and show loading state
            const defaultMessage = document.getElementById('defaultMessage');
            const loadingState = document.getElementById('loadingState');
            const runBtn = document.getElementById('runBtn');

            if (defaultMessage) defaultMessage.style.display = 'none';
            loadingState.style.display = 'block';

            // Update button
            runBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Running...';
            runBtn.disabled = true;

            // Show user what's happening
            console.log('Manual monitoring started...');
        });
    </script>
</body>
</html>
