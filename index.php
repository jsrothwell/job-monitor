<?php
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Company.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $company = new Company($db);

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $company->add($_POST['name'], $_POST['careers_url'], $_POST['selector']);
                header('Location: index.php');
                exit;

            case 'delete':
                $company->delete($_POST['id']);
                header('Location: index.php');
                exit;
        }
    }
}

$db = new Database();
$db->createTables();
$company = new Company($db);
$companies = $company->getAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: box-shadow 0.15s ease-in-out;
        }
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .navbar-brand {
            font-weight: 700;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        .btn-sm {
            font-size: 0.8rem;
        }
        .code-snippet {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .feature-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-search me-2"></i>
                Job Monitor
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-building me-1"></i>
                    <?= count($companies) ?> Companies Monitored
                </span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-5 mb-4">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">
                <i class="bi bi-radar me-3"></i>
                Job Monitor Dashboard
            </h1>
            <p class="lead">Automatically track job openings from your favorite companies</p>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Add Company Form -->
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add New Company
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add">

                            <div class="mb-3">
                                <label for="companyName" class="form-label fw-semibold">
                                    <i class="bi bi-building me-1"></i>
                                    Company Name
                                </label>
                                <input type="text" class="form-control" id="companyName" name="name" required
                                       placeholder="Enter company name">
                            </div>

                            <div class="mb-3">
                                <label for="careersUrl" class="form-label fw-semibold">
                                    <i class="bi bi-link-45deg me-1"></i>
                                    Careers Page URL
                                </label>
                                <input type="url" class="form-control" id="careersUrl" name="careers_url" required
                                       placeholder="https://company.com/careers">
                            </div>

                            <div class="mb-3">
                                <label for="cssSelector" class="form-label fw-semibold">
                                    <i class="bi bi-code-slash me-1"></i>
                                    CSS Selector (Optional)
                                </label>
                                <input type="text" class="form-control" id="cssSelector" name="selector"
                                       placeholder="a[href*='job'], .job-listing a, etc.">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Leave empty to auto-detect job links
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-lg me-2"></i>
                                    Add Company
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Companies Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-buildings me-2"></i>
                                Monitored Companies
                            </h5>
                            <?php if (!empty($companies)): ?>
                                <span class="badge bg-secondary">
                                    <?= count($companies) ?> total
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($companies)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No companies added yet</h5>
                                <p class="text-muted">Start by adding your first company above</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">
                                                <i class="bi bi-building me-1"></i>
                                                Company
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-link-45deg me-1"></i>
                                                Careers URL
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-code-slash me-1"></i>
                                                Selector
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-clock me-1"></i>
                                                Last Checked
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-activity me-1"></i>
                                                Status
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-gear me-1"></i>
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $comp): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($comp['name']) ?>
                                            </td>
                                            <td>
                                                <a href="<?= htmlspecialchars($comp['careers_url']) ?>"
                                                   target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i>
                                                    View
                                                </a>
                                            </td>
                                            <td>
                                                <span class="code-snippet">
                                                    <?= htmlspecialchars($comp['selector'] ?: 'auto-detect') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($comp['last_checked']): ?>
                                                    <span class="text-success">
                                                        <i class="bi bi-check-circle me-1"></i>
                                                        <?= date('M j, Y g:i A', strtotime($comp['last_checked'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="bi bi-dash-circle me-1"></i>
                                                        Never
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($comp['status'] === 'active'): ?>
                                                    <span class="badge bg-success status-badge">
                                                        <i class="bi bi-play-fill me-1"></i>
                                                        Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger status-badge">
                                                        <i class="bi bi-pause-fill me-1"></i>
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;"
                                                      onsubmit="return confirm('Are you sure you want to delete this company?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $comp['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
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

        <!-- Setup Instructions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear-fill me-2"></i>
                            Setup Instructions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary">
                                    <i class="bi bi-list-check me-1"></i>
                                    Initial Setup
                                </h6>
                                <ol class="list-group list-group-numbered list-group-flush">
                                    <li class="list-group-item border-0 px-0">
                                        Copy <code>config/config.example.php</code> to <code>config/config.php</code>
                                    </li>
                                    <li class="list-group-item border-0 px-0">
                                        Update database and email credentials in the config file
                                    </li>
                                    <li class="list-group-item border-0 px-0">
                                        Add companies using the form above
                                    </li>
                                    <li class="list-group-item border-0 px-0">
                                        Set up a cron job for automated monitoring
                                    </li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success">
                                    <i class="bi bi-terminal me-1"></i>
                                    Quick Commands
                                </h6>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Cron job setup:</small>
                                    <div class="code-snippet">
                                        */30 * * * * php /path/to/scripts/monitor.php
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Test scraping:</small>
                                    <div class="code-snippet">
                                        php scripts/quick-test.php https://company.com/careers
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-4">Application Features</h6>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-building fs-4"></i>
                                </div>
                                <h6 class="fw-semibold">Company Management</h6>
                                <small class="text-muted">Add and monitor multiple companies</small>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-search fs-4"></i>
                                </div>
                                <h6 class="fw-semibold">Smart Scraping</h6>
                                <small class="text-muted">Auto-detect or custom CSS selectors</small>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-envelope fs-4"></i>
                                </div>
                                <h6 class="fw-semibold">Email Alerts</h6>
                                <small class="text-muted">Get notified of new job postings</small>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-clock fs-4"></i>
                                </div>
                                <h6 class="fw-semibold">Automated</h6>
                                <small class="text-muted">Runs automatically via cron jobs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-1">&copy; 2024 Job Monitor. Built with Bootstrap & PHP.</p>
            <small class="text-muted">
                <i class="bi bi-github me-1"></i>
                Open source job monitoring application
            </small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS for enhanced UX -->
    <script>
        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Adding...';
            submitBtn.disabled = true;
        });

        // Add tooltips to action buttons
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto-focus on company name field
        document.getElementById('companyName').focus();
    </script>
</body>
</html>
