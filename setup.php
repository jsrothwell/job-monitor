<?php
// setup.php - Simplified setup script for Job Feed Aggregator
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'test_database') {
        $result = testDatabaseConnection($_POST);
        if ($result['success']) {
            $success[] = $result['message'];
            $_SESSION['db_config'] = $_POST;
            $_SESSION['db_tested'] = true;
        } else {
            $errors[] = $result['message'];
        }
    }

    if ($action === 'create_config') {
        $dbConfig = isset($_SESSION['db_config']) ? $_SESSION['db_config'] : [];
        $result = createConfigFile($dbConfig, $_POST);
        if ($result['success']) {
            $success[] = $result['message'];
            $step = 3;
        } else {
            $errors[] = $result['message'];
        }
    }

    if ($action === 'setup_database') {
        $result = setupDatabase();
        if ($result['success']) {
            $success[] = $result['message'];
            $step = 4;
        } else {
            $errors[] = $result['message'];
        }
    }

    if ($action === 'add_sample_data') {
        $result = addSampleData();
        if ($result['success']) {
            $success[] = $result['message'];
            $step = 5;
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Check if we can advance to step 2
$canAdvanceToStep2 = isset($_SESSION['db_tested']) && $_SESSION['db_tested'] === true;
if (isset($_GET['advance']) && $_GET['advance'] == '2' && $canAdvanceToStep2) {
    $step = 2;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Feed Aggregator Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .step-number { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .step-number.completed { background: #28a745; color: white; }
        .step-number.active { background: #667eea; color: white; }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="hero-gradient text-white py-4">
        <div class="container">
            <h1 class="h3 mb-0">
                <i class="bi bi-briefcase-fill me-2"></i>
                Job Feed Aggregator Setup
            </h1>
            <p class="mb-0 opacity-75">Step <?php echo $step; ?> of 5</p>
        </div>
    </div>

    <div class="container py-4">

        <!-- Progress Steps -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <?php
                    $steps = [
                        1 => 'Database',
                        2 => 'Configuration',
                        3 => 'Setup Tables',
                        4 => 'Sample Data',
                        5 => 'Complete'
                    ];

                    foreach ($steps as $num => $title) {
                        $isCompleted = $step > $num;
                        $isActive = $step == $num;
                        $statusClass = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
                        echo '<div class="text-center">';
                        echo '<div class="step-number ' . $statusClass . ' mx-auto mb-2">';
                        echo $isCompleted ? '<i class="bi bi-check"></i>' : $num;
                        echo '</div>';
                        echo '<small class="d-block fw-semibold">' . $title . '</small>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Setup Error</h6>
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <h6 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Success!</h6>
                <?php foreach ($success as $message): ?>
                    <div><?php echo htmlspecialchars($message); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                        <p class="text-muted">Configure your database connection.</p>

                        <!-- System checks -->
                        <div class="mb-3">
                            <h6>System Status:</h6>
                            <ul class="list-unstyled small">
                                <li>
                                    <i class="bi bi-<?php echo extension_loaded('pdo_mysql') ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                    PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? 'Available' : 'Missing'; ?>
                                </li>
                                <li>
                                    <i class="bi bi-<?php echo is_writable(__DIR__) ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                    Directory writable: <?php echo is_writable(__DIR__) ? 'Yes' : 'No'; ?>
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

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-database-check me-2"></i>Test Database Connection
                            </button>
                        </form>

                        <?php if ($canAdvanceToStep2): ?>
                        <div class="mt-3 text-center">
                            <a href="?advance=2" class="btn btn-success">
                                <i class="bi bi-arrow-right me-2"></i>Continue to Email Setup
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="mt-3 text-center">
                            <a href="?step=2" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-skip-forward me-2"></i>Skip Database Test
                            </a>
                        </div>
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
                        <p class="text-muted">Configure email settings for job alerts.</p>

                        <form method="post">
                            <input type="hidden" name="action" value="create_config">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your Email Address</label>
                                    <input type="email" class="form-control" name="email_user" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alert Recipient</label>
                                    <input type="email" class="form-control" name="email_to" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableAlerts" name="enable_alerts" checked>
                                    <label class="form-check-label" for="enableAlerts">
                                        Enable Email Alerts
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-gear me-2"></i>Create Configuration
                            </button>
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
                        <p class="text-muted">Create the database tables.</p>

                        <form method="post">
                            <input type="hidden" name="action" value="setup_database">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-database-add me-2"></i>Create Database Tables
                            </button>
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
                        <p class="text-muted">Add sample companies to get started.</p>

                        <form method="post">
                            <input type="hidden" name="action" value="add_sample_data">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add Sample Companies
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="?step=5" class="btn btn-outline-secondary">
                                Skip Sample Data
                            </a>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Step 5: Complete -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            Setup Complete!
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill display-1 text-success mb-3"></i>
                        <h4>🎉 Congratulations!</h4>
                        <p class="text-muted mb-4">Your Job Feed Aggregator is ready!</p>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="index.php" class="btn btn-success btn-lg">
                                <i class="bi bi-house me-2"></i>Go to Dashboard
                            </a>
                            <a href="manage.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-lg me-2"></i>Add Job Feeds
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function testDatabaseConnection($config) {
    if (empty($config['db_host']) || empty($config['db_name']) || empty($config['db_user'])) {
        return [
            'success' => false,
            'message' => 'Please fill in all required fields'
        ];
    }

    try {
        $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);

        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['db_name']}'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE DATABASE `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Test connection to the database
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        return [
            'success' => true,
            'message' => 'Database connection successful!'
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function createConfigFile($dbConfig, $emailConfig) {
    $configContent = "<?php\nreturn [\n";
    $configContent .= "    'database' => [\n";
    $configContent .= "        'host' => '" . ($dbConfig['db_host'] ?? 'localhost') . "',\n";
    $configContent .= "        'name' => '" . ($dbConfig['db_name'] ?? 'job_feed') . "',\n";
    $configContent .= "        'user' => '" . ($dbConfig['db_user'] ?? '') . "',\n";
    $configContent .= "        'pass' => '" . ($dbConfig['db_pass'] ?? '') . "'\n";
    $configContent .= "    ],\n";
    $configContent .= "    'email' => [\n";
    $configContent .= "        'host' => 'smtp.gmail.com',\n";
    $configContent .= "        'port' => 587,\n";
    $configContent .= "        'user' => '{$emailConfig['email_user']}',\n";
    $configContent .= "        'pass' => 'your_email_password',\n";
    $configContent .= "        'to' => '{$emailConfig['email_to']}'\n";
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
            'message' => 'Configuration file created!'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Could not create config file'
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
            'message' => 'Database tables created!'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database setup failed: ' . $e->getMessage()
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
            ['GitHub', 'https://github.com/about/careers', 'a[href*="job"]', 'https://github.com', 'Technology'],
            ['Netflix', 'https://jobs.netflix.com/', 'a[href*="job"]', 'https://netflix.com', 'Entertainment'],
            ['Shopify', 'https://www.shopify.com/careers', '.job-listing a', 'https://shopify.com', 'E-commerce']
        ];

        $added = 0;
        foreach ($sampleCompanies as $data) {
            try {
                $company->add($data[0], $data[1], $data[2], null, null, $data[3], null, $data[4]);
                $added++;
            } catch (Exception $e) {
                // Company might exist already
            }
        }

        return [
            'success' => true,
            'message' => "Added $added sample companies!"
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Sample data failed: ' . $e->getMessage()
        ];
    }
}
?>
