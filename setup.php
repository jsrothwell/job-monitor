<?php
// setup.php - Initial setup script for Job Feed Aggregator

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'test_database':
            $result = testDatabaseConnection($_POST);
            if ($result['success']) {
                $success[] = $result['message'];
                $_SESSION['db_config'] = $_POST;
                $step = 2; // Advance to next step
            } else {
                $errors[] = $result['message'];
            }
            break;

        case 'create_config':
            $result = createConfigFile($_SESSION['db_config'] ?? [], $_POST);
            if ($result['success']) {
                $success[] = $result['message'];
                $step = 3;
            } else {
                $errors[] = $result['message'];
            }
            break;

        case 'setup_database':
            $result = setupDatabase();
            if ($result['success']) {
                $success[] = $result['message'];
                $step = 4;
            } else {
                $errors[] = $result['message'];
            }
            break;

        case 'add_sample_data':
            $result = addSampleData();
            if ($result['success']) {
                $success[] = $result['message'];
                $step = 5;
            } else {
                $errors[] = $result['message'];
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Feed Aggregator Setup</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .setup-step {
            transition: all 0.3s ease;
        }

        .setup-step.active {
            border-left: 4px solid var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .step-number.completed {
            background: #28a745;
            color: white;
        }

        .step-number.active {
            background: var(--primary-color);
            color: white;
        }

        .feature-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .config-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="hero-gradient text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-briefcase-fill me-2"></i>
                        Job Feed Aggregator Setup
                    </h1>
                    <p class="mb-0 opacity-75">Configure your intelligent job monitoring system</p>
                </div>
                <div class="col-auto">
                    <div class="badge bg-light text-primary fs-6">Step <?= $step ?> of 5</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Progress Steps -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <?php
                    $steps = [
                        1 => ['icon' => 'database', 'title' => 'Database'],
                        2 => ['icon' => 'gear', 'title' => 'Configuration'],
                        3 => ['icon' => 'table', 'title' => 'Setup Tables'],
                        4 => ['icon' => 'plus-circle', 'title' => 'Sample Data'],
                        5 => ['icon' => 'check-circle', 'title' => 'Complete']
                    ];

                    foreach ($steps as $num => $stepInfo):
                        $isCompleted = $step > $num;
                        $isActive = $step == $num;
                        $statusClass = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
                    ?>
                    <div class="text-center">
                        <div class="step-number <?= $statusClass ?> mx-auto mb-2">
                            <?php if ($isCompleted): ?>
                                <i class="bi bi-check"></i>
                            <?php else: ?>
                                <?= $num ?>
                            <?php endif; ?>
                        </div>
                        <small class="d-block fw-semibold"><?= $stepInfo['title'] ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Setup Error</h6>
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <h6 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Success!</h6>
                <?php foreach ($success as $message): ?>
                    <div><?= htmlspecialchars($message) ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Debug Info (remove in production) -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info">
            <strong>Debug Info:</strong><br>
            Current Step: <?= $step ?><br>
            POST Action: <?= $_POST['action'] ?? 'none' ?><br>
            Session DB Config: <?= !empty($_SESSION['db_config']) ? 'Set' : 'Not set' ?><br>
            Success Messages: <?= count($success) ?><br>
            Error Messages: <?= count($errors) ?>
        </div>
        <?php endif; ?>

        <!-- Step Content -->
        <div class="row">
            <div class="col-lg-8 mx-auto">

                <?php if ($step == 1): ?>
                <!-- Step 1: Database Configuration -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-database me-2"></i>
                            Database Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">First, let's configure your database connection. Make sure you have created a MySQL database and have the connection details ready.</p>

                        <!-- Pre-flight checks -->
                        <div class="mb-3">
                            <h6>System Checks:</h6>
                            <ul class="list-unstyled small">
                                <li>
                                    <i class="bi bi-<?= is_writable(__DIR__) ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                    Directory writable: <?= is_writable(__DIR__) ? 'Yes' : 'No' ?>
                                </li>
                                <li>
                                    <i class="bi bi-<?= extension_loaded('pdo_mysql') ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                    PDO MySQL: <?= extension_loaded('pdo_mysql') ? 'Available' : 'Missing' ?>
                                </li>
                                <li>
                                    <i class="bi bi-<?= function_exists('mail') ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                    Mail function: <?= function_exists('mail') ? 'Available' : 'Missing' ?>
                                </li>
                            </ul>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="test_database">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" class="form-control" name="db_name" value="job_feed" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="db_user" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="db_pass">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-database-check me-2"></i>Test Database Connection
                                </button>
                            </div>
                        </form>

                        <!-- Show continue button if database test was successful -->
                        <?php if (!empty($success) && !empty($_SESSION['db_config'])): ?>
                        <div class="mt-3 text-center">
                            <a href="?step=2" class="btn btn-success">
                                <i class="bi bi-arrow-right me-2"></i>Continue to Email Setup
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="mt-3 text-center">
                            <a href="?step=2&skip_test=1" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-skip-forward me-2"></i>Skip Test (Manual Config)
                            </a>
                        </div>
                        <?php endif;
                    </div>
                </div>

                <?php elseif ($step == 2): ?>
                <!-- Step 2: Email Configuration -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-envelope me-2"></i>
                            Email Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Configure email settings for job alerts and notifications.</p>

                        <?php if (isset($_GET['skip_test']) && $_GET['skip_test'] == 1): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Database test skipped.</strong> Make sure to manually create your config/config.php file with correct database settings.
                        </div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="action" value="create_config">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your Email Address</label>
                                    <input type="email" class="form-control" name="email_user" required>
                                    <div class="form-text">This will be used as the "from" address</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alert Recipient</label>
                                    <input type="email" class="form-control" name="email_to" required>
                                    <div class="form-text">Where job alerts will be sent</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableAlerts" name="enable_alerts" checked>
                                    <label class="form-check-label" for="enableAlerts">
                                        Enable Email Alerts
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableNotifications" name="admin_notifications">
                                    <label class="form-check-label" for="enableNotifications">
                                        Enable System Notifications
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-gear me-2"></i>Create Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($step == 3): ?>
                <!-- Step 3: Database Setup -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Database Tables Setup
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Now let's create the database tables needed for the job feed aggregator.</p>

                        <div class="mb-3">
                            <h6>Tables to be created:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-table text-primary me-2"></i>companies - Store company and feed information</li>
                                <li><i class="bi bi-table text-primary me-2"></i>jobs - Store job listings with enhanced metadata</li>
                                <li><i class="bi bi-table text-primary me-2"></i>job_tags - Flexible job categorization</li>
                                <li><i class="bi bi-table text-primary me-2"></i>saved_jobs - User saved jobs</li>
                                <li><i class="bi bi-table text-primary me-2"></i>job_alerts - Email alert preferences</li>
                                <li><i class="bi bi-table text-primary me-2"></i>tags - Common tags and skills</li>
                            </ul>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="setup_database">

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-database-add me-2"></i>Create Database Tables
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($step == 4): ?>
                <!-- Step 4: Sample Data -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            Sample Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Add some sample companies to get you started. You can always add more or remove these later.</p>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="feature-card text-center">
                                    <i class="bi bi-github display-6 text-primary mb-2"></i>
                                    <h6>GitHub</h6>
                                    <small class="text-muted">Technology company careers</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="feature-card text-center">
                                    <i class="bi bi-play-circle display-6 text-danger mb-2"></i>
                                    <h6>Netflix</h6>
                                    <small class="text-muted">Entertainment industry jobs</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="feature-card text-center">
                                    <i class="bi bi-shop display-6 text-success mb-2"></i>
                                    <h6>Shopify</h6>
                                    <small class="text-muted">E-commerce platform roles</small>
                                </div>
                            </div>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="add_sample_data">

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Add Sample Companies
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="?step=5" class="btn btn-outline-secondary">
                                Skip Sample Data
                            </a>
                        </div>
                    </div>
                </div>

                <?php elseif ($step == 5): ?>
                <!-- Step 5: Complete -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            Setup Complete!
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-check-circle-fill display-1 text-success mb-3"></i>
                            <h4>🎉 Congratulations!</h4>
                            <p class="text-muted">Your Job Feed Aggregator is now ready to use.</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="feature-card">
                                    <h6><i class="bi bi-speedometer me-2"></i>What's New</h6>
                                    <ul class="list-unstyled small">
                                        <li>✅ Location-based filtering</li>
                                        <li>✅ Remote job detection</li>
                                        <li>✅ Job type categorization</li>
                                        <li>✅ Department sorting</li>
                                        <li>✅ Enhanced search capabilities</li>
                                        <li>✅ Smart job alerts</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="feature-card">
                                    <h6><i class="bi bi-list-check me-2"></i>Next Steps</h6>
                                    <ul class="list-unstyled small">
                                        <li>1. Add your company feeds</li>
                                        <li>2. Configure cron job automation</li>
                                        <li>3. Set up job alerts</li>
                                        <li>4. Test the scraping system</li>
                                        <li>5. Start monitoring!</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Cron Job Setup</h6>
                            <p class="mb-2">To automate job monitoring, add this to your cron tab:</p>
                            <code>*/30 * * * * php <?= __DIR__ ?>/scripts/monitor.php</code>
                            <p class="mb-0 mt-2 small">This will check for new jobs every 30 minutes.</p>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="index.php" class="btn btn-success btn-lg">
                                <i class="bi bi-house me-2"></i>Go to Dashboard
                            </a>
                            <a href="manage.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-lg me-2"></i>Add Job Feeds
                            </a>
                            <a href="test.php" class="btn btn-outline-info btn-lg">
                                <i class="bi bi-tools me-2"></i>Test System
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function testDatabaseConnection($config) {
    // Validate required fields
    if (empty($config['db_host']) || empty($config['db_name']) || empty($config['db_user'])) {
        return [
            'success' => false,
            'message' => 'Please fill in all required database fields (host, name, user)'
        ];
    }

    try {
        $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";

        // First test basic connection to MySQL server
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        // Check if database exists, if not try to create it
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['db_name']}'");
        if (!$stmt->fetch()) {
            // Try to create the database
            $pdo->exec("CREATE DATABASE `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Now connect to the specific database
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Test if we can create tables (check permissions)
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_permissions (id INT) ENGINE=InnoDB");
        $pdo->exec("DROP TABLE IF EXISTS test_permissions");

        return [
            'success' => true,
            'message' => 'Database connection successful! Database "' . $config['db_name'] . '" is ready.'
        ];

    } catch (PDOException $e) {
        $errorMessage = 'Database connection failed: ';

        if (strpos($e->getMessage(), 'Access denied') !== false) {
            $errorMessage .= 'Invalid username or password';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $errorMessage .= 'Database "' . $config['db_name'] . '" does not exist and could not be created';
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            $errorMessage .= 'Cannot connect to MySQL server at ' . $config['db_host'];
        } else {
            $errorMessage .= $e->getMessage();
        }

        return [
            'success' => false,
            'message' => $errorMessage
        ];
    }
}

function createConfigFile($dbConfig, $emailConfig) {
    $configContent = "<?php\nreturn [\n";
    $configContent .= "    'database' => [\n";
    $configContent .= "        'host' => '{$dbConfig['db_host']}',\n";
    $configContent .= "        'name' => '{$dbConfig['db_name']}',\n";
    $configContent .= "        'user' => '{$dbConfig['db_user']}',\n";
    $configContent .= "        'pass' => '{$dbConfig['db_pass']}'\n";
    $configContent .= "    ],\n";
    $configContent .= "    'email' => [\n";
    $configContent .= "        'host' => 'smtp.gmail.com',\n";
    $configContent .= "        'port' => 587,\n";
    $configContent .= "        'user' => '{$emailConfig['email_user']}',\n";
    $configContent .= "        'pass' => 'your_email_password',\n";
    $configContent .= "        'to' => '{$emailConfig['email_to']}',\n";
    $configContent .= "        'alerts_enabled' => " . (isset($emailConfig['enable_alerts']) ? 'true' : 'false') . ",\n";
    $configContent .= "        'admin_notifications' => " . (isset($emailConfig['admin_notifications']) ? 'true' : 'false') . ",\n";
    $configContent .= "        'admin_email' => '{$emailConfig['email_to']}'\n";
    $configContent .= "    ]\n";
    $configContent .= "];\n";

    $configDir = __DIR__ . '/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $configFile = $configDir . '/config.php';

    if (file_put_contents($configFile, $configContent)) {
        return [
            'success' => true,
            'message' => 'Configuration file created successfully!'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to create configuration file. Check file permissions.'
        ];
    }
}

function setupDatabase() {
    try {
        require_once __DIR__ . '/src/Database.php';
        $db = new Database();
        $db->createTables();

        return [
            'success' => true,
            'message' => 'Database tables created successfully!'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to create database tables: ' . $e->getMessage()
        ];
    }
}

function addSampleData() {
    try {
        require_once __DIR__ . '/src/Database.php';
        require_once __DIR__ . '/src/Company.php';

        $db = new Database();
        $company = new Company($db);

        $sampleCompanies = [
            [
                'name' => 'GitHub',
                'careers_url' => 'https://github.com/about/careers',
                'selector' => 'a[href*="job"]',
                'website_url' => 'https://github.com',
                'industry' => 'Technology',
                'logo_url' => 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png'
            ],
            [
                'name' => 'Netflix',
                'careers_url' => 'https://jobs.netflix.com/',
                'selector' => 'a[href*="job"]',
                'website_url' => 'https://netflix.com',
                'industry' => 'Entertainment'
            ],
            [
                'name' => 'Shopify',
                'careers_url' => 'https://www.shopify.com/careers',
                'selector' => '.job-listing a',
                'website_url' => 'https://shopify.com',
                'industry' => 'E-commerce'
            ]
        ];

        $added = 0;
        foreach ($sampleCompanies as $companyData) {
            try {
                $company->add(
                    $companyData['name'],
                    $companyData['careers_url'],
                    $companyData['selector'],
                    null, // location_selector
                    null, // description_selector
                    $companyData['website_url'],
                    $companyData['logo_url'] ?? null,
                    $companyData['industry']
                );
                $added++;
            } catch (Exception $e) {
                // Company might already exist, continue
            }
        }

        return [
            'success' => true,
            'message' => "Added $added sample companies successfully!"
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to add sample data: ' . $e->getMessage()
        ];
    }
}
?>
