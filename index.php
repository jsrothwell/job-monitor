<?php
// test.php - Web-based testing tool
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Company.php';
require_once __DIR__ . '/src/JobScraper.php';
require_once __DIR__ . '/src/Emailer.php';
require_once __DIR__ . '/src/JobMonitor.php';

$testResults = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_url'])) {
        // Test a URL directly
        $url = $_POST['test_url'];
        $selector = $_POST['test_selector'] ?: 'a';

        try {
            require_once __DIR__ . '/src/JobScraper.php';

            $db = new Database();
            $company = new Company($db);
            $scraper = new JobScraper($db, $company);

            // Create a temporary company data array
            $tempCompany = [
                'id' => 0,
                'name' => 'Test Company',
                'careers_url' => $url,
                'selector' => $selector
            ];

            $startTime = microtime(true);
            $jobs = $scraper->scrapeCompany($tempCompany);
            $endTime = microtime(true);

            $testResults = [
                'url' => $url,
                'selector' => $selector,
                'jobs' => $jobs ?: [],
                'job_count' => $jobs === false ? 0 : count($jobs),
                'success' => $jobs !== false,
                'duration' => round($endTime - $startTime, 2)
            ];

        } catch (Exception $e) {
            $testError = $e->getMessage();
        }
    } elseif (isset($_POST['test_company_id'])) {
        // Test an existing company
        try {
            $monitor = new JobMonitor();
            $testResults = $monitor->testCompany($_POST['test_company_id']);
        } catch (Exception $e) {
            $testError = $e->getMessage();
        }
    }
}

// Get existing companies for dropdown
$db = new Database();
$company = new Company($db);
$companies = $company->getAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor - Test Tool</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .code-output {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Dashboard
            </a>
            <span class="navbar-text">
                <i class="bi bi-tools me-1"></i>
                Test Tool
            </span>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-4">
        <div class="container text-center">
            <h1 class="h3 fw-bold mb-2">
                <i class="bi bi-bug me-2"></i>
                Job Monitor Test Tool
            </h1>
            <p class="mb-0">Test job scraping for any URL or existing company</p>
        </div>
    </div>

    <div class="container py-4">
        <!-- Test Forms -->
        <div class="row">
            <!-- Test URL -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-link-45deg me-2"></i>
                            Test Any URL
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="testUrl" class="form-label">Careers Page URL</label>
                                <input type="url" class="form-control" id="testUrl" name="test_url" required
                                       placeholder="https://company.com/careers"
                                       value="<?= htmlspecialchars($_POST['test_url'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="testSelector" class="form-label">CSS Selector (Optional)</label>
                                <input type="text" class="form-control" id="testSelector" name="test_selector"
                                       placeholder="a[href*='job'], .job-listing a, etc."
                                       value="<?= htmlspecialchars($_POST['test_selector'] ?? '') ?>">
                                <div class="form-text">Leave empty for auto-detection</div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-circle me-1"></i>
                                Test URL
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Test Existing Company -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-building me-2"></i>
                            Test Existing Company
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($companies)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-6 text-muted mb-3"></i>
                                <p class="text-muted">No companies added yet</p>
                                <a href="index.php" class="btn btn-outline-primary">
                                    Add Companies
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label for="testCompany" class="form-label">Select Company</label>
                                    <select class="form-select" id="testCompany" name="test_company_id" required>
                                        <option value="">Choose a company...</option>
                                        <?php foreach ($companies as $comp): ?>
                                            <option value="<?= $comp['id'] ?>"
                                                    <?= (isset($_POST['test_company_id']) && $_POST['test_company_id'] == $comp['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($comp['name']) ?>
                                                (<?= $comp['status'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-play-circle me-1"></i>
                                    Test Company
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <?php if ($testResults || $testError): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header <?= $testError ? 'bg-danger' : 'bg-success' ?> text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-<?= $testError ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                                Test Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($testError): ?>
                                <div class="alert alert-danger">
                                    <strong>Error:</strong> <?= htmlspecialchars($testError) ?>
                                </div>
                            <?php else: ?>
                                <!-- Summary Stats -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-<?= $testResults['success'] ? 'success' : 'danger' ?> mb-1">
                                                <?= $testResults['success'] ? '✓' : '✗' ?>
                                            </div>
                                            <small class="text-muted">Status</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-primary mb-1"><?= $testResults['job_count'] ?? count($testResults['jobs'] ?? []) ?></div>
                                            <small class="text-muted">Jobs Found</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-info mb-1"><?= $testResults['duration'] ?>s</div>
                                            <small class="text-muted">Duration</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-secondary mb-1">
                                                <?= isset($testResults['url']) ? parse_url($testResults['url'], PHP_URL_HOST) : ($testResults['company'] ?? 'N/A') ?>
                                            </div>
                                            <small class="text-muted">Source</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Job Listings -->
                                <?php if (!empty($testResults['jobs'])): ?>
                                    <h6>Job Listings Found:</h6>
                                    <div class="code-output p-3">
                                        <?php foreach ($testResults['jobs'] as $i => $job): ?>
                                            <div class="mb-2 p-2 bg-white rounded border">
                                                <strong><?= $i + 1 ?>. <?= htmlspecialchars($job['title']) ?></strong>
                                                <?php if (!empty($job['url'])): ?>
                                                    <br><small class="text-muted">
                                                        <i class="bi bi-link-45deg me-1"></i>
                                                        <a href="<?= htmlspecialchars($job['url']) ?>" target="_blank">
                                                            <?= htmlspecialchars($job['url']) ?>
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-info-circle me-2"></i>
                                        No job listings found. Try adjusting your CSS selector or check if the page structure has changed.
                                    </div>
                                <?php endif; ?>

                                <!-- Debug Info -->
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>URL:</strong> <?= htmlspecialchars($testResults['url'] ?? 'N/A') ?><br>
                                        <strong>Selector:</strong> <?= htmlspecialchars($testResults['selector'] ?? 'auto-detect') ?><br>
                                        <strong>Message:</strong> <?= htmlspecialchars($testResults['message'] ?? 'Test completed') ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Common Selectors Help -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightbulb me-2"></i>
                            Common CSS Selectors
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Generic Selectors:</h6>
                                <ul class="list-unstyled">
                                    <li><code>a[href*="job"]</code> - Links containing "job"</li>
                                    <li><code>a[href*="career"]</code> - Links containing "career"</li>
                                    <li><code>.job-listing a</code> - Links inside job containers</li>
                                    <li><code>[data-job-id]</code> - Elements with job IDs</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Site-Specific Examples:</h6>
                                <ul class="list-unstyled">
                                    <li><code>.posting-title a</code> - Many job boards</li>
                                    <li><code>.job-title-link</code> - Common class name</li>
                                    <li><code>h3 a</code> - Job titles in headers</li>
                                    <li><code>[role="link"]</code> - Accessible links</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Add loading states to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Testing...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>
