<?php
// basic-debug.php - Simple file and system checker
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Job Monitor - Basic Debug</h1>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

// Check files
echo "<h2>File Check</h2>";
$files = array(
    'index.php',
    'test.php',
    'config/config.php',
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Jobscraper.php',  // Check both cases
    'src/Emailer.php',
    'src/JobMonitor.php'
);

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;

    echo "<p>";
    echo $exists ? "✅" : "❌";
    echo " <strong>" . $file . "</strong>";
    if ($exists) {
        echo " (" . number_format($size) . " bytes)";
    } else {
        echo " (missing)";
    }
    echo "</p>";
}

// Check what's in Emailer.php
echo "<h2>Emailer.php Content Check</h2>";
$emailerPath = __DIR__ . '/src/Emailer.php';
if (file_exists($emailerPath)) {
    $content = file_get_contents($emailerPath);
    $lines = explode("\n", $content);
    $totalLines = count($lines);

    echo "<p><strong>Total lines:</strong> " . $totalLines . "</p>";

    // Check for problematic syntax
    $hasQuestionQuestion = strpos($content, '??') !== false;
    $hasArraySyntax = strpos($content, '[]') !== false;

    echo "<p><strong>Contains ?? operator:</strong> " . ($hasQuestionQuestion ? "Yes ⚠️" : "No ✅") . "</p>";
    echo "<p><strong>Contains [] syntax:</strong> " . ($hasArraySyntax ? "Yes" : "No") . "</p>";

    // Show lines around 214 if file is that long
    if ($totalLines >= 214) {
        echo "<h3>Lines around 214:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
        for ($i = max(210, 0); $i <= min(218, $totalLines - 1); $i++) {
            $lineNum = $i + 1;
            $highlight = ($lineNum == 214) ? "style='background: yellow;'" : "";
            echo "<span $highlight>$lineNum: " . htmlspecialchars($lines[$i]) . "</span>\n";
        }
        echo "</pre>";
    }

    // Show first 10 lines
    echo "<h3>First 10 lines of Emailer.php:</h3>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    for ($i = 0; $i < min(10, $totalLines); $i++) {
        $lineNum = $i + 1;
        echo "$lineNum: " . htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p>❌ Emailer.php not found!</p>";
}

// Test basic PHP syntax
echo "<h2>PHP Extensions</h2>";
$extensions = array('pdo', 'pdo_mysql', 'curl', 'dom');
foreach ($extensions as $ext) {
    echo "<p>";
    echo extension_loaded($ext) ? "✅" : "❌";
    echo " " . $ext;
    echo "</p>";
}

// Check config
echo "<h2>Config File</h2>";
$configPath = __DIR__ . '/config/config.php';
if (file_exists($configPath)) {
    echo "<p>✅ Config file exists</p>";
    try {
        $config = require $configPath;
        echo "<p>✅ Config file loads without errors</p>";
        echo "<p><strong>Has database section:</strong> " . (isset($config['database']) ? "Yes" : "No") . "</p>";
        echo "<p><strong>Has email section:</strong> " . (isset($config['email']) ? "Yes" : "No") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Config file error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>❌ Config file missing</p>";
    echo "<p><strong>Expected location:</strong> " . $configPath . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Replace src/Emailer.php</strong> with the 'Simple Working Emailer.php' version</li>";
echo "<li><strong>Create config/config.php</strong> if missing</li>";
echo "<li><strong>Check file permissions</strong> (644 for files, 755 for directories)</li>";
echo "<li><strong>Test again</strong> with index.php</li>";
echo "</ol>";

echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

<style>
body {
    font-family: 'Libre Franklin', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h1 {
    color: #007bff;
    font-weight: 600;
}
h2 {
    color: #495057;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
    font-weight: 600;
}
h3 {
    font-weight: 600;
}
pre {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
}
</style>

<!-- Google Fonts - Libre Franklin -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
