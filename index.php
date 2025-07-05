<?php
// diagnostic.php - Find where RobustJobMonitor is referenced
echo "<h1>Job Monitor Diagnostic Tool</h1>";
echo "<p>This tool will help find where 'RobustJobMonitor' is incorrectly referenced.</p>";

$searchTerm = 'RobustJobMonitor';
$correctTerm = 'JobMonitor';
$rootDir = __DIR__;

function scanDirectory($dir, $searchTerm) {
    $results = [];

    if (!is_dir($dir)) {
        return $results;
    }

    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($fullPath) && !in_array($file, ['vendor', 'node_modules', '.git', 'cache', 'logs'])) {
            $results = array_merge($results, scanDirectory($fullPath, $searchTerm));
        } elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($fullPath);
            if (stripos($content, $searchTerm) !== false) {
                $lines = explode("\n", $content);
                $matches = [];

                foreach ($lines as $lineNum => $line) {
                    if (stripos($line, $searchTerm) !== false) {
                        $matches[] = [
                            'line' => $lineNum + 1,
                            'content' => trim($line)
                        ];
                    }
                }

                if (!empty($matches)) {
                    $results[] = [
                        'file' => $fullPath,
                        'matches' => $matches
                    ];
                }
            }
        }
    }

    return $results;
}

echo "<h2>Scanning for '$searchTerm'...</h2>";

$results = scanDirectory($rootDir, $searchTerm);

if (empty($results)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3 style='color: #155724;'>✅ Good News!</h3>";
    echo "<p>No references to '$searchTerm' found in your PHP files.</p>";
    echo "<p>The error might be coming from:</p>";
    echo "<ul>";
    echo "<li>Cached files (try clearing browser cache)</li>";
    echo "<li>A different version of a file</li>";
    echo "<li>Another script calling your code</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3 style='color: #721c24;'>❌ Found " . count($results) . " file(s) with '$searchTerm'</h3>";

    foreach ($results as $result) {
        echo "<div style='margin: 15px 0; padding: 10px; background: white; border-left: 4px solid #dc3545;'>";
        echo "<h4>File: " . htmlspecialchars($result['file']) . "</h4>";

        foreach ($result['matches'] as $match) {
            echo "<p><strong>Line {$match['line']}:</strong></p>";
            echo "<pre style='background: #f8f9fa; padding: 8px; border-radius: 3px; overflow-x: auto;'>" . htmlspecialchars($match['content']) . "</pre>";
        }

        echo "<p style='color: #28a745;'><strong>Fix:</strong> Replace '$searchTerm' with '$correctTerm' in this file.</p>";
        echo "</div>";
    }
    echo "</div>";
}

echo "<h2>File Structure Check</h2>";

$requiredFiles = [
    'src/Database.php',
    'src/Company.php',
    'src/JobScraper.php',
    'src/Emailer.php',
    'src/JobMonitor.php',
    'config/config.php'
];

echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>File</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Status</th>";
echo "</tr>";

foreach ($requiredFiles as $file) {
    $exists = file_exists($rootDir . '/' . $file);
    $status = $exists ? "✅ Exists" : "❌ Missing";
    $color = $exists ? "#d4edda" : "#f8d7da";

    echo "<tr style='background: $color;'>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($file) . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>$status</td>";
    echo "</tr>";
}

echo "</table>";

// Check if config file exists
if (!file_exists($rootDir . '/config/config.php')) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3 style='color: #856404;'>⚠️ Config File Missing</h3>";
    echo "<p>Create <code>config/config.php</code> from <code>config/config.example.php</code></p>";
    echo "</div>";
}

echo "<h2>Quick Class Test</h2>";

try {
    // Test if we can load the JobMonitor class
    if (file_exists($rootDir . '/src/JobMonitor.php')) {
        require_once $rootDir . '/src/JobMonitor.php';
        echo "<p style='color: green;'>✅ JobMonitor class file found and loadable</p>";

        // Try to create an instance (this might fail due to dependencies)
        echo "<p><em>Note: Creating an instance might fail due to missing config or database, but the class file itself is OK.</em></p>";
    } else {
        echo "<p style='color: red;'>❌ JobMonitor.php file is missing</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠️ JobMonitor class has issues: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If files with '$searchTerm' were found above, edit those files and replace with '$correctTerm'</li>";
echo "<li>Make sure you have the correct <code>index.php</code> file (not the debug version)</li>";
echo "<li>Ensure <code>config/config.php</code> exists with your database settings</li>";
echo "<li>Clear your browser cache and try again</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Diagnostic completed at " . date('Y-m-d H:i:s') . "</small></p>";
?>
