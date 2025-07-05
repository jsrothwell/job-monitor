<?php
// Job Monitor Dashboard - Replace your entire index.php with this file
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Company.php';
require_once __DIR__ . '/src/JobScraper.php';
require_once __DIR__ . '/src/Emailer.php';
require_once __DIR__ . '/src/JobMonitor.php';

$message = '';
$messageType = '';
$manualResults = null;

try {
    $db = new Database();
    $db->createTables();
    $company = new Company($db);
    $monitor = new JobMonitor(); // CORRECT CLASS NAME
} catch (Exception $e) {
    die("Setup error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company':
                try {
                    $name = trim($_POST['company_name']);
                    $url = trim($_POST['careers_url']);
                    $selector = trim($_POST['css_selector']) ?: null;

                    if (empty($name) || empty($url)) {
                        throw new Exception("Company name and URL are required");
                    }

                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid URL format");
                    }

                    if ($company->add($name, $url, $selector)) {
                        $message = "Company '$name' added successfully!";
                        $messageType = 'success';
                    } else {
                        throw new Exception("Failed to add company");
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_company':
                try {
                    $id = (int)$_POST['company_id'];
                    if ($company->delete($id)) {
                        $message = "Company deleted successfully!";
                        $messageType = 'success';
                    } else {
                        throw new Exception("Failed to delete company");
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'run_manual':
                try {
                    $manualResults = $monitor->runManual(); // CORRECT METHOD CALL
                    $message = "Manual run completed! Found {$manualResults['total_new_jobs']} new jobs across {$manualResults['companies_checked']} companies.";
                    $messageType = 'info';
                } catch (Exception $e) {
                    $message = "Error during manual run: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

$companies = $company->getAll();
$stats = $monitor->getStats();
$recentJobs = $monitor->getRecentJobs(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .job-item { border-left: 4px solid #007bff; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-briefcase me-2"></i>Job Monitor</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="test.php"><i class="bi bi-tools me-1"></i>Test Tool</a>
            </div>
        </div>
    </nav>

    <div class="hero-section py-5">
        <div class="container text-center">
            <h1 class="display-5 fw-bold mb-3"><i class="bi bi-radar me-3"></i>Job Monitor Dashboard</h1>
            <p class="lead mb-4">Automatically track new job postings from your target companies</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="run_manual">
                    <button type="submit" class="btn btn-light btn-lg">
                        <i class="bi bi-play-circle me-2"></i>Run Check Now
                    </button>
                </form>
                <a href="#add-company" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-plus-circle me-2"></i>Add Company
                </a>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-building display-6 text-primary mb-2"></i>
                        <h3 class="fw-bold"><?= $stats['active_companies'] ?></h3>
                        <p class="text-muted mb-0">Active Companies</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-briefcase display-6 text-success mb-2"></i>
                        <h3 class="fw-bold"><?= $stats['total_jobs'] ?></h3>
                        <p class="text-muted mb-0">Total Jobs Tracked</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-star display-6 text-warning mb-2"></i>
                        <h3 class="fw-bold"><?= $stats['new_jobs_today'] ?></h3>
                        <p class="text-muted mb-0">New Jobs Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-clock display-6 text-info mb-2"></i>
                        <h6 class="fw-bold"><?= $stats['last_run'] ? date('M j, g:i A', strtotime($stats['last_run'])) : 'Never' ?></h6>
                        <p class="text-muted mb-0">Last Check</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($manualResults): ?>
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-activity me-2"></i>Manual Run Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3 text-center">
                                    <div class="h4 text-primary"><?= $manualResults['companies_checked'] ?></div>
                                    <small class="text-muted">Companies Checked</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="h4 text-success"><?= $manualResults['total_new_jobs'] ?></div>
                                    <small class="text-muted">New Jobs Found</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="h4 text-info"><?= $manualResults['emails_sent'] ?></div>
                                    <small class="text-muted">Emails Sent</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="h4 text-warning"><?= $manualResults['duration'] ?>s</div>
                                    <small class="text-muted">Duration</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100" id="add-company">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Add Company</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_company">
                            <div class="mb-3">
                                <label for="companyName" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="companyName" name="company_name" required placeholder="e.g., Google, Microsoft, etc.">
                            </div>
                            <div class="mb-3">
                                <label for="careersUrl" class="form-label">Careers Page URL</label>
                                <input type="url" class="form-control" id="careersUrl" name="careers_url" required placeholder="https://company.com/careers">
                                <div class="form-text">Direct link to the company's job listings page</div>
                            </div>
                            <div class="mb-3">
                                <label for="cssSelector" class="form-label">CSS Selector (Optional)</label>
                                <input type="text" class="form-control" id="cssSelector" name="css_selector" placeholder="a[href*='job'], .job-listing a, etc.">
                                <div class="form-text">Leave empty for auto-detection</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Add Company</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Recent Jobs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentJobs)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-6 text-muted mb-3"></i>
                                <p class="text-muted">No jobs tracked yet</p>
                                <p class="small text-muted">Add companies and run the monitor to see jobs here</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentJobs as $job): ?>
                                    <div class="list-group-item job-item px-0 border-0 border-start border-3">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($job['title']) ?></h6>
                                            <small class="text-muted"><?= date('M j', strtotime($job['first_seen'])) ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <small class="text-muted">
                                                <i class="bi bi-building me-1"></i><?= htmlspecialchars($job['company_name']) ?>
                                            </small>
                                        </p>
                                        <?php if (!empty($job['url'])): ?>
                                            <small>
                                                <a href="<?= htmlspecialchars($job['url']) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-arrow-up-right-square me-1"></i>View Job
                                                </a>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Monitored Companies (<?= count($companies) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($companies)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-building display-6 text-muted mb-3"></i>
                                <p class="text-muted">No companies added yet</p>
                                <p class="small text-muted">Add your first company using the form above</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company</th><th>URL</th><th>Selector</th><th>Last Checked</th><th>Status</th><th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $comp): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($comp['name']) ?></strong></td>
                                                <td>
                                                    <a href="<?= htmlspecialchars($comp['careers_url']) ?>" target="_blank" class="text-decoration-none">
                                                        <i class="bi bi-arrow-up-right-square me-1"></i><?= parse_url($comp['careers_url'], PHP_URL_HOST) ?>
                                                    </a>
                                                </td>
                                                <td><code><?= htmlspecialchars($comp['selector'] ?: 'auto') ?></code></td>
                                                <td><?= $comp['last_checked'] ? date('M j, Y g:i A', strtotime($comp['last_checked'])) : 'Never' ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $comp['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($comp['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this company?')">
                                                        <input type="hidden" name="action" value="delete_company">
                                                        <input type="hidden" name="company_id" value="<?= $comp['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
