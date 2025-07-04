<?php
// debug.php - Comprehensive debugging and troubleshooting tool
error_reporting(E_ALL);
ini_set('display_errors', 1);

$debug = [];

// Check PHP version
$debug['php_version'] = [
    'version' => PHP_VERSION,
    'required' => '7.4.0',
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
];

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'dom', 'libxml'];
$debug['extensions'] = [];
foreach ($requiredExtensions as $ext) {
    $debug['extensions'][$ext] = extension_loaded($ext);
}

// Check file permissions
$files = [
    'config/config.php',
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php'
];

$debug['files'] = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    $debug['files'][$file] = [
        'exists' => file_exists($path),
        'readable' => file_exists($path) && is_readable($path),
        'size' => file_exists($path) ? filesize($path) : 0
    ];
}

// Test configuration
$debug['config'] = ['status' => false, 'error' => null];
if (file_exists(__DIR__ . '/config/config.php')) {
    try {
        $config = require __DIR__ . '/config/config.php';

        $requiredKeys = ['database', 'email'];
        $hasRequired = true;
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                $hasRequired = false;
                break;
            }
        }

        if ($hasRequired && isset($config['database']['host'], $config['database']['name'], $config['database']['user'], $config['database']['pass'])) {
            $debug['config']['status'] = true;
            $debug['config']['database_host'] = $config['database']['host'];
            $debug['config']['database_name'] = $config['database']['name'];
        } else {
            $debug['config']['error'] = 'Missing required configuration keys';
        }
    } catch (Exception $e) {
        $debug['config']['error'] = $e->getMessage();
    }
} else {
    $debug['config']['error'] = 'Configuration file not found';
}

// Test database connection
$debug['database'] = ['status' => false, 'error' => null];
if ($debug['config']['status']) {
    try {
        require_once __DIR__ . '/src/Database.php';
        $db = new Database();

        if ($db->testConnection()) {
            $debug['database']['status'] = true;
            $debug['database']['version'] = $db->getVersion();

            // Test table creation
            try {
                $db->createTables();
                $debug['database']['tables_created'] = true;
            } catch (Exception $e) {
                $debug['database']['tables_error'] = $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $debug['database']['error'] = $e->getMessage();
    }
}

// Test class loading
$debug['classes'] = [];
$classes = [
    'Database' => 'src/Database.php',
    'Company' => 'src/Company.php',
    'JobScraper' => 'src/JobScraper.php',
    'Emailer' => 'src/Emailer.php',
    'JobMonitor' => 'src/JobMonitor.php'
];

foreach ($classes as $className => $file) {
    try {
        if (file_exists(__DIR__ . '/' . $file)) {
            require_once __DIR__ . '/' . $file;
            $debug['classes'][$className] = class_exists($className);
        } else {
            $debug['classes'][$className] = false;
        }
    } catch (Exception $e) {
        $debug['classes'][$className] = 'Error: ' . $e->getMessage();
    }
}

// Test JobMonitor functionality
$debug['jobmonitor'] = ['status' => false, 'error' => null];
if ($debug['database']['status'] && isset($debug['classes']['JobMonitor']) && $debug['classes']['JobMonitor'] === true) {
    try {
        $monitor = new JobMonitor();
        $debug['jobmonitor']['status'] = true;

        // Test stats method
        try {
            $stats = $monitor->getStats();
            $debug['jobmonitor']['stats'] = $stats;
        } catch (Exception $e) {
            $debug['jobmonitor']['stats_error'] = $e->getMessage();
        }

        // Test configuration check
        try {
            $configCheck = $monitor->checkConfiguration();
            $debug['jobmonitor']['config_check'] = $configCheck;
        } catch (Exception $e) {
            $debug['jobmonitor']['config_check_error'] = $e->getMessage();
        }
    } catch (Exception $e) {
        $debug['jobmonitor']['error'] = $e->getMessage();
    }
}

// Test write permissions
$debug['permissions'] = [];
$testDirs = [__DIR__, __DIR__ . '/config'];
foreach ($testDirs as $dir) {
    $testFile = $dir . '/test_' . uniqid() . '.tmp';
    $canWrite = @file_put_contents($testFile, 'test');
    if ($canWrite) {
        @unlink($testFile);
        $debug['permissions'][$dir] = true;
    } else {
        $debug['permissions'][$dir] = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Monitor - Debug Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-ok { color: #198754; }
        .status-error { color: #dc3545; }
        .status-warning { color: #fd7e14; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; overflow-x: auto; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">
                <i class="bi bi-bug me-2"></i>
                Job Monitor Debug
            </span>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house me-1"></i>
                Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-4">
                    <i class="bi bi-tools me-2"></i>
                    Debug Information
                </h1>
            </div>
        </div>

        <!-- PHP Environment -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-server me-2"></i>
                            PHP Environment
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>PHP Version</td>
                                <td>
                                    <span class="<?= $debug['php_version']['status'] ? 'status-ok' : 'status-error' ?>">
                                        <?= $debug['php_version']['version'] ?>
                                        <?= $debug['php_version']['status'] ? '✓' : '✗' ?>
                                    </span>
                                    <small class="text-muted">(Required: <?= $debug['php_version']['required'] ?>+)</small>
                                </td>
                            </tr>
                            <tr>
                                <td>Server</td>
                                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                            </tr>
                            <tr>
                                <td>Memory Limit</td>
                                <td><?= ini_get('memory_limit') ?></td>
                            </tr>
                            <tr>
                                <td>Max Execution Time</td>
                                <td><?= ini_get('max_execution_time') ?> seconds</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-puzzle me-2"></i>
                            PHP Extensions
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($debug['extensions'] as $ext => $loaded): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ext) ?></td>
                                    <td>
                                        <span class="<?= $loaded ? 'status-ok' : 'status-error' ?>">
                                            <?= $loaded ? '✓ Loaded' : '✗ Missing' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- File System -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Required Files
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($debug['files'] as $file => $info): ?>
                                <tr>
                                    <td><?= htmlspecialchars($file) ?></td>
                                    <td>
                                        <?php if ($info['exists']): ?>
                                            <span class="status-ok">✓ <?= number_format($info['size']) ?> bytes</span>
                                        <?php else: ?>
                                            <span class="status-error">✗ Missing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-lock me-2"></i>
                            Directory Permissions
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($debug['permissions'] as $dir => $writable): ?>
                                <tr>
                                    <td><?= str_replace(__DIR__, '.', $dir) ?></td>
                                    <td>
                                        <span class="<?= $writable ? 'status-ok' : 'status-error' ?>">
                                            <?= $writable ? '✓ Writable' : '✗ Not writable' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>
                            Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($debug['config']['status']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Configuration loaded successfully
                            </div>
                            <table class="table table-sm">
                                <tr>
                                    <td>Database Host</td>
                                    <td><?= htmlspecialchars($debug['config']['database_host']) ?></td>
                                </tr>
                                <tr>
                                    <td>Database Name</td>
                                    <td><?= htmlspecialchars($debug['config']['database_name']) ?></td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Configuration Error: <?= htmlspecialchars($debug['config']['error']) ?>
                            </div>
                            <p>Please ensure <code>config/config.php</code> exists and contains valid configuration.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-database me-2"></i>
                            Database Connection
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($debug['database']['status']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Database connected successfully
                            </div>
                            <table class="table table-sm">
                                <tr>
                                    <td>MySQL Version</td>
                                    <td><?= htmlspecialchars($debug['database']['version']) ?></td>
                                </tr>
                                <tr>
                                    <td>Tables Created</td>
                                    <td>
                                        <?php if (isset($debug['database']['tables_created'])): ?>
                                            <span class="status-ok">✓ Success</span>
                                        <?php elseif (isset($debug['database']['tables_error'])): ?>
                                            <span class="status-error">✗ <?= htmlspecialchars($debug['database']['tables_error']) ?></span>
                                        <?php else: ?>
                                            <span class="status-warning">Not tested</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Database Error: <?= htmlspecialchars($debug['database']['error'] ?? 'Unknown error') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes and JobMonitor -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-code-square me-2"></i>
                            Class Loading
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($debug['classes'] as $class => $status): ?>
                                <tr>
                                    <td><?= htmlspecialchars($class) ?></td>
                                    <td>
                                        <?php if ($status === true): ?>
                                            <span class="status-ok">✓ Loaded</span>
                                        <?php elseif ($status === false): ?>
                                            <span class="status-error">✗ Failed</span>
                                        <?php else: ?>
                                            <span class="status-error">✗ <?= htmlspecialchars($status) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity me-2"></i>
                            JobMonitor Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($debug['jobmonitor']['status']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                JobMonitor initialized successfully
                            </div>

                            <?php if (isset($debug['jobmonitor']['stats'])): ?>
                                <h6>Current Stats:</h6>
                                <table class="table table-sm">
                                    <tr><td>Total Companies</td><td><?= $debug['jobmonitor']['stats']['total_companies'] ?></td></tr>
                                    <tr><td>Active Companies</td><td><?= $debug['jobmonitor']['stats']['active_companies'] ?></td></tr>
                                    <tr><td>Total Jobs</td><td><?= $debug['jobmonitor']['stats']['total_jobs'] ?></td></tr>
                                    <tr><td>New Jobs Today</td><td><?= $debug['jobmonitor']['stats']['new_jobs_today'] ?></td></tr>
                                </table>
                            <?php endif; ?>

                            <?php if (isset($debug['jobmonitor']['config_check'])): ?>
                                <h6>Configuration Check:</h6>
                                <?php if ($debug['jobmonitor']['config_check']['configured']): ?>
                                    <span class="status-ok">✓ All checks passed</span>
                                <?php else: ?>
                                    <div class="alert alert-warning alert-sm">
                                        Issues found:
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($debug['jobmonitor']['config_check']['issues'] as $issue): ?>
                                                <li><?= htmlspecialchars($issue) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                JobMonitor Error: <?= htmlspecialchars($debug['jobmonitor']['error'] ?? 'Unknown error') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Status -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Overall Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $overallOk = $debug['php_version']['status'] &&
                                   array_sum($debug['extensions']) === count($debug['extensions']) &&
                                   $debug['config']['status'] &&
                                   $debug['database']['status'] &&
                                   $debug['jobmonitor']['status'];
                        ?>

                        <?php if ($overallOk): ?>
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle me-2"></i>System Status: HEALTHY</h5>
                                <p class="mb-0">All systems are working correctly. The application should function normally.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h5><i class="bi bi-exclamation-triangle me-2"></i>System Status: ISSUES DETECTED</h5>
                                <p>Please fix the errors shown above before using the application.</p>

                                <h6>Common Solutions:</h6>
                                <ul>
                                    <li>Copy <code>config/config.example.php</code> to <code>config/config.php</code> and configure it</li>
                                    <li>Check database credentials and ensure the database exists</li>
                                    <li>Verify all required files are uploaded</li>
                                    <li>Check file permissions on the server</li>
                                    <li>Contact your hosting provider about missing PHP extensions</li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <h6>Quick Actions:</h6>
                            <div class="btn-group">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="bi bi-house me-1"></i>
                                    Main Dashboard
                                </a>
                                <a href="test.php" class="btn btn-outline-primary">
                                    <i class="bi bi-tools me-1"></i>
                                    Test Tool
                                </a>
                                <button onclick="location.reload()" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Refresh Debug
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Raw Debug Data -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-code me-2"></i>
                            Raw Debug Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <pre><code><?= htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT)) ?></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
