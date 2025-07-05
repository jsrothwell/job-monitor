<?php
// debug_selector.php - Tool to debug CSS selectors for job scraping
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Company.php';
require_once __DIR__ . '/src/JobScraper.php';

$url = 'https://jobs.sap.com/go/SAP-Jobs-in-Vancouver/';
$testSelectors = [
    '.jobTitle-link',           // Class selector (note the dot)
    'a.jobTitle-link',          // Link with class
    '[data-job-title]',         // Data attribute
    '.job-title a',             // Link inside job title container
    'a[href*="job"]',           // Links containing "job"
    '.posting-title',           // Common class name
    '.job-listing a',           // Links in job listings
    'h3 a',                     // Links in h3 headers
    'a[title*="Apply"]',        // Apply links
    '.search-results a'         // Links in search results
];

echo "<h1>CSS Selector Debug Tool</h1>";
echo "<h2>Testing URL: " . htmlspecialchars($url) . "</h2>";

// Function to fetch page content
function fetchPageContent($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; JobMonitor/1.0)',
            'follow_location' => true
        ]
    ]);

    return @file_get_contents($url, false, $context);
}

// Function to convert CSS to XPath (simplified version from JobScraper)
function cssToXpath($selector) {
    $selector = trim($selector);

    if ($selector === 'a') {
        return "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'position')]";
    }

    // Simple CSS to XPath conversion
    if (strpos($selector, '.') === 0) {
        // Class selector like .jobTitle-link
        $className = substr($selector, 1);
        return "//a[contains(@class, '$className')]";
    } elseif (strpos($selector, '#') === 0) {
        // ID selector like #job-list
        $id = substr($selector, 1);
        return "//*[@id='$id']//a";
    } elseif (strpos($selector, '[') !== false) {
        // Attribute selector - keep as is for XPath
        return "//" . $selector;
    } else {
        // Element selector or complex selector
        return "//" . $selector;
    }
}

// Function to test a selector
function testSelector($html, $selector, $baseUrl) {
    if (!$html) {
        return ['error' => 'Could not fetch page content'];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $xpathQuery = cssToXpath($selector);
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
    echo "<strong>Testing:</strong> <code>" . htmlspecialchars($selector) . "</code><br>";
    echo "<strong>XPath:</strong> <code>" . htmlspecialchars($xpathQuery) . "</code><br>";

    try {
        $elements = $xpath->query($xpathQuery);
        $jobCount = 0;
        $jobs = [];

        if ($elements) {
            foreach ($elements as $element) {
                $title = trim($element->textContent);
                $href = $element->getAttribute('href');

                // Skip empty or very short titles
                if (empty($title) || strlen($title) < 3) continue;

                // Skip common navigation links
                if (preg_match('/\b(home|about|contact|login|search|filter|sort)\b/i', $title)) continue;

                $url = makeAbsoluteUrl($href, $baseUrl);
                $jobs[] = [
                    'title' => $title,
                    'url' => $url
                ];
                $jobCount++;

                // Limit output for readability
                if ($jobCount >= 10) break;
            }
        }

        if ($jobCount > 0) {
            echo "<span style='color: green;'><strong>✓ Found $jobCount job(s)</strong></span><br>";
            echo "<details><summary>Show jobs found</summary><ul>";
            foreach ($jobs as $job) {
                echo "<li><strong>" . htmlspecialchars($job['title']) . "</strong>";
                if ($job['url']) {
                    echo "<br><small><a href='" . htmlspecialchars($job['url']) . "' target='_blank'>" . htmlspecialchars($job['url']) . "</a></small>";
                }
                echo "</li>";
            }
            echo "</ul></details>";
        } else {
            echo "<span style='color: red;'><strong>✗ No jobs found</strong></span>";
        }

    } catch (Exception $e) {
        echo "<span style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</span>";
    }

    echo "</div>";

    return $jobs;
}

// Helper function to make absolute URLs
function makeAbsoluteUrl($href, $baseUrl) {
    if (empty($href)) return '';

    if (parse_url($href, PHP_URL_SCHEME)) {
        return $href;
    }

    $base = parse_url($baseUrl);
    $baseScheme = $base['scheme'] ?? 'https';
    $baseHost = $base['host'] ?? '';

    if (strpos($href, '//') === 0) {
        return $baseScheme . ':' . $href;
    }

    if (strpos($href, '/') === 0) {
        return $baseScheme . '://' . $baseHost . $href;
    }

    return $baseScheme . '://' . $baseHost . '/' . ltrim($href, '/');
}

// Fetch the page
echo "<h3>Fetching page content...</h3>";
$html = fetchPageContent($url);

if (!$html) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<strong>Error:</strong> Could not fetch the page. This might be because:";
    echo "<ul>";
    echo "<li>The website blocks automated requests</li>";
    echo "<li>The URL is incorrect or inaccessible</li>";
    echo "<li>The website requires JavaScript to load content</li>";
    echo "<li>Network connectivity issues</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
    echo "✓ Page fetched successfully (" . strlen($html) . " characters)";
    echo "</div>";

    echo "<h3>Testing Different CSS Selectors:</h3>";

    $bestSelector = null;
    $maxJobs = 0;

    foreach ($testSelectors as $selector) {
        $jobs = testSelector($html, $selector, $url);
        if (is_array($jobs) && count($jobs) > $maxJobs) {
            $maxJobs = count($jobs);
            $bestSelector = $selector;
        }
    }

    if ($bestSelector) {
        echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>🎯 Recommended Selector:</h4>";
        echo "<p><strong>Use this selector:</strong> <code>" . htmlspecialchars($bestSelector) . "</code></p>";
        echo "<p>Found $maxJobs job(s) with this selector.</p>";
        echo "</div>";
    }
}

echo "<h3>How to Find the Right CSS Selector:</h3>";
echo "<ol>";
echo "<li><strong>Open the careers page</strong> in your browser</li>";
echo "<li><strong>Right-click on a job title</strong> and select 'Inspect Element'</li>";
echo "<li><strong>Look at the HTML structure</strong> around the job link</li>";
echo "<li><strong>Common patterns:</strong>";
echo "<ul>";
echo "<li><code>.job-title</code> - for class names</li>";
echo "<li><code>a[href*='job']</code> - for links containing 'job'</li>";
echo "<li><code>.posting-title a</code> - for links inside posting containers</li>";
echo "<li><code>[data-job-id]</code> - for data attributes</li>";
echo "</ul></li>";
echo "</ol>";

echo "<h3>Common Issues:</h3>";
echo "<ul>";
echo "<li><strong>Missing dot (.) for classes:</strong> Use <code>.jobTitle-link</code> not <code>jobTitle-link</code></li>";
echo "<li><strong>JavaScript-loaded content:</strong> Some sites load jobs dynamically and won't work with basic scraping</li>";
echo "<li><strong>Anti-bot protection:</strong> Some sites block automated requests</li>";
echo "<li><strong>Complex selectors:</strong> Our scraper has limited CSS selector support</li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
details { margin: 10px 0; }
summary { cursor: pointer; font-weight: bold; }
</style>
