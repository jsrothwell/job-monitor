<?php
// Debug version - shows exact errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "Starting debug...<br>";

// Test each file one by one
echo "1. Testing Database.php...<br>";
try {
    require_once __DIR__ . '/src/Database.php';
    echo "✓ Database.php loaded successfully<br>";
} catch (Exception $e) {
    die("❌ Error loading Database.php: " . $e->getMessage());
}

echo "2. Testing Company.php...<br>";
try {
    require_once __DIR__ . '/src/Company.php';
    echo "✓ Company.php loaded successfully<br>";
} catch (Exception $e) {
    die("❌ Error loading Company.php: " . $e->getMessage());
}

echo "3. Testing config file...<br>";
if (!file_exists(__DIR__ . '/config/config.php')) {
    die("❌ Config file missing! Create config/config.php from config.example.php");
}
echo "✓ Config file exists<br>";

echo "4. Testing database connection...<br>";
try {
    $db = new Database();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

echo "5. Testing table creation...<br>";
try {
    $db->createTables();
    echo "✓ Tables created/verified<br>";
} catch (Exception $e) {
    die("❌ Table creation failed: " . $e->getMessage());
}

echo "6. Testing Company class...<br>";
try {
    $company = new Company($db);
    $companies = $company->getAll();
    echo "✓ Company class working, found " . count($companies) . " companies<br>";
} catch (Exception $e) {
    die("❌ Company class failed: " . $e->getMessage());
}

echo "7. Testing other required files...<br>";
$requiredFiles = [
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ $file exists<br>";
        try {
            require_once __DIR__ . '/' . $file;
            echo "✓ $file loaded successfully<br>";
        } catch (Exception $e) {
            echo "❌ Error loading $file: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Missing file: $file<br>";
    }
}

echo "8. Testing JobMonitor class...<br>";
try {
    $monitor = new JobMonitor();
    echo "✓ JobMonitor class created successfully<br>";

    // Test if runManual method exists
    if (method_exists($monitor, 'runManual')) {
        echo "✓ runManual method exists<br>";
    } else {
        echo "❌ runManual method missing - JobMonitor.php not updated properly<br>";
    }
} catch (Exception $e) {
    echo "❌ JobMonitor class failed: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Debug complete!</strong><br>";
echo "If you see this message, the basic functionality is working.<br>";
echo "The 500 error might be caused by a specific action or form submission.<br>";

// Simple form test
if ($_POST) {
    echo "<br><strong>POST data received:</strong><br>";
    print_r($_POST);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Test</title>
</head>
<body>
    <h2>Debug Test Form</h2>
    <form method="post">
        <input type="hidden" name="action" value="test">
        <button type="submit">Test POST</button>
    </form>

    <h3>File Check:</h3>
    <ul>
        <li>Config file: <?= file_exists(__DIR__ . '/config/config.php') ? '✓ Exists' : '❌ Missing' ?></li>
        <li>Database.php: <?= file_exists(__DIR__ . '/src/Database.php') ? '✓ Exists' : '❌ Missing' ?></li>
        <li>Company.php: <?= file_exists(__DIR__ . '/src/Company.php') ? '✓ Exists' : '❌ Missing' ?></li>
        <li>JobScraper.php: <?= file_exists(__DIR__ . '/src/JobScraper.php') ? '✓ Exists' : '❌ Missing' ?></li>
        <li>Emailer.php: <?= file_exists(__DIR__ . '/src/Emailer.php') ? '✓ Exists' : '❌ Missing' ?></li>
        <li>JobMonitor.php: <?= file_exists(__DIR__ . '/src/JobMonitor.php') ? '✓ Exists' : '❌ Missing' ?></li>
    </ul>
</body>
</html>
