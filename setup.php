<?php
// setup.php - Easy installation and setup script
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'create_config':
            try {
                $config = [
                    'database' => [
                        'host' => $_POST['db_host'],
                        'name' => $_POST['db_name'],
                        'user' => $_POST['db_user'],
                        'pass' => $_POST['db_pass']
                    ],
                    'email' => [
                        'host' => $_POST['email_host'],
                        'port' => (int)$_POST['email_port'],
                        'user' => $_POST['email_user'],
                        'pass' => $_POST['email_pass'],
                        'to' => $_POST['email_to']
                    ]
                ];

                $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";

                if (file_put_contents(__DIR__ . '/config/config.php', $configContent)) {
                    $message = "Configuration file created successfully!";
                    $messageType = 'success';
                    $step = 2;
                } else {
                    $message = "Failed to create configuration file. Check file permissions.";
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;

        case 'test_database':
            try {
                require_once __DIR__ . '/src/Database.php';
                $db = new Database();

                if ($db->testConnection()) {
                    $db->createTables();
                    $message = "Database connection successful and tables created!";
                    $messageType = 'success';
                    $step = 3;
                } else {
                    $message = "Database connection failed. Please check your settings.";
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;

        case 'test_complete':
            try {
                require_once __DIR__ . '/src/Database.php';
                require_once __DIR__ . '/src/Company.php';
                require_once __DIR__ . '/src/JobScraper.php';
                require_once __DIR__ . '/src/Emailer.php';
                require_once __DIR__ . '/src/JobMonitor.php';

                $monitor = new JobMonitor();
                $configCheck = $monitor->checkConfiguration();

                if ($configCheck['configured']) {
                    $message = "Setup completed successfully! You can now use the Job Monitor.";
                    $messageType = 'success';
                    $step = 4;
                } else {
                    $message = "Configuration issues found: " . implode(', ', $configCheck['issues']);
                    $messageType = 'warning';
                }
            } catch (Exception $e) {
                $message = "Error during final test: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;
    }
}

// Check current status
$checks = [
    'config_exists' => file_exists(__DIR__ . '/config/config.php'),
    'config_dir_writable' => is_writable(__DIR__ . '/config'),
    'required_files' => true
];

$requiredFiles = [
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $checks['required_files'] = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .setup-step {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .step-complete {
            border-left-color: #198754;
            background: #d1e7dd;
        }
        .step-number {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">
                <i class="bi bi-gear me-2"></i>
                Job Monitor Setup
            </span>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <h1 class="h3">Welcome to Job Monitor Setup</h1>
                    <p class="text-muted">Let's get your job monitoring system configured</p>
                </div>

                <!-- Progress Steps -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <?php
                            $steps = [
                                1 => 'Configuration',
                                2 => 'Database',
                                3 => 'Verification',
                                4 => 'Complete'
                            ];

                            foreach ($steps as $num => $title):
                                $isActive = $num == $step;
                                $isComplete = $num < $step;
                                $class = $isComplete ? 'bg-success text-white' : ($isActive ? 'bg-primary text-white' : 'bg-light text-muted');
                            ?>
                                <div class="text-center">
                                    <div class="step-number <?= $class ?> mb-2">
                                        <?= $isComplete ? '✓' : $num ?>
                                    </div>
                                    <small><?= $title ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Step Content -->
                <?php if ($step == 1): ?>
                    <!-- Step 1: Configuration -->
                    <div class="card setup-step">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-gear me-2"></i>
                                Step 1: Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$checks['config_dir_writable']): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    The config directory is not writable. Please set permissions to 755 or 777.
                                </div>
                            <?php elseif (!$checks['required_files']): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Some required files are missing. Please ensure all files are uploaded.
                                </div>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="create_config">

                                    <h6>Database Configuration</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Database Host</label>
                                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Database Name</label>
                                            <input type="text" class="form-control" name="db_name" required>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Database Username</label>
                                            <input type="text" class="form-control" name="db_user" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Database Password</label>
                                            <input type="password" class="form-control" name="db_pass">
                                        </div>
                                    </div>

                                    <h6>Email Configuration</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" name="email_host" value="smtp.gmail.com" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" name="email_port" value="587" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="email_user" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email Password</label>
                                            <input type="password" class="form-control" name="email_pass" required>
                                            <div class="form-text">Use App Password for Gmail</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Send Alerts To</label>
                                        <input type="email" class="form-control" name="email_to" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check me-1"></i>
                                        Create Configuration
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Database -->
                    <div class="card setup-step">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-database me-2"></i>
                                Step 2: Database Setup
                            </h5>
                        </div>
                        <div class="card-body">
                            <p>Now let's test the database connection and create the required tables.</p>

                            <form method="post">
                                <input type="hidden" name="action" value="test_database">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-play-circle me-1"></i>
                                    Test Database Connection
                                </button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Verification -->
                    <div class="card setup-step">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-check-circle me-2"></i>
                                Step 3: Verification
                            </h5>
                        </div>
                        <div class="card-body">
                            <p>Let's verify that all components are working correctly.</p>

                            <form method="post">
                                <input type="hidden" name="action" value="test_complete">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-all me-1"></i>
                                    Run Final Tests
                                </button>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Step 4: Complete -->
                    <div class="card step-complete">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-check-circle me-2"></i>
                                Setup Complete!
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">Job Monitor is Ready!</h4>
                                <p class="text-muted">Your job monitoring system has been successfully configured.</p>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                    <a href="index.php" class="btn btn-primary">
                                        <i class="bi bi-house me-1"></i>
                                        Go to Dashboard
                                    </a>
                                    <a href="test.php" class="btn btn-outline-primary">
                                        <i class="bi bi-tools me-1"></i>
                                        Test Tool
                                    </a>
                                    <a href="debug.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-bug me-1"></i>
                                        Debug Info
                                    </a>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6>Next Steps:</h6>
                                <ol>
                                    <li>Add companies to monitor using the dashboard</li>
                                    <li>Test job scraping with the test tool</li>
                                    <li>Set up a cron job to run <code>scripts/monitor.php</code> automatically</li>
                                </ol>

                                <h6>Cron Job Example:</h6>
                                <div class="bg-dark text-light p-2 rounded">
                                    <code># Run every 30 minutes<br>
                                    */30 * * * * php /path/to/your/job-monitor/scripts/monitor.php</code>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">System Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="<?= $checks['required_files'] ? 'text-success' : 'text-danger' ?>">
                                    <i class="bi bi-<?= $checks['required_files'] ? 'check-circle' : 'x-circle' ?> fs-4"></i>
                                    <div>Required Files</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="<?= $checks['config_exists'] ? 'text-success' : 'text-muted' ?>">
                                    <i class="bi bi-<?= $checks['config_exists'] ? 'check-circle' : 'circle' ?> fs-4"></i>
                                    <div>Configuration</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="<?= $checks['config_dir_writable'] ? 'text-success' : 'text-danger' ?>">
                                    <i class="bi bi-<?= $checks['config_dir_writable'] ? 'check-circle' : 'x-circle' ?> fs-4"></i>
                                    <div>Permissions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
