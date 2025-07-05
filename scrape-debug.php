<?php
// scrape-debug.php - Advanced web scraping debugger
error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = null;
$url = '';
$selector = '';

if ($_POST) {
    $url = trim($_POST['url']);
    $selector = trim($_POST['selector']);

    $results = debugScraping($url, $selector);
}

function debugScraping($url, $customSelector = '') {
    $debug = array(
        'url' => $url,
        'timestamp' => date('Y-m-d H:i:s'),
        'steps' => array()
    );

    // Step 1: Validate URL
    $debug['steps']['url_validation'] = array(
        'step' => 'URL Validation',
        'success' => filter_var($url, FILTER_VALIDATE_URL) !== false,
        'details' => filter_var($url, FILTER_VALIDATE_URL) ? 'Valid URL format' : 'Invalid URL format'
    );

    if (!$debug['steps']['url_validation']['success']) {
        return $debug;
    }

    // Step 2: Fetch the page
    $debug['steps']['page_fetch'] = fetchPageDebug($url);

    if (!$debug['steps']['page_fetch']['success']) {
        return $debug;
    }

    $html = $debug['steps']['page_fetch']['content'];

    // Step 3: Parse HTML
    $debug['steps']['html_parse'] = parseHtmlDebug($html);

    if (!$debug['steps']['html_parse']['success']) {
        return $debug;
    }

    $dom = $debug['steps']['html_parse']['dom'];
    $xpath = new DOMXPath($dom);

    // Step 4: Test various selectors
    $debug['steps']['selector_tests'] = testSelectors($xpath, $customSelector, $url);

    // Step 5: Content analysis
    $debug['steps']['content_analysis'] = analyzeContent($html, $url);

    return $debug;
}

function fetchPageDebug($url) {
    $result = array('step' => 'Page Fetch', 'success' => false, 'details' => '');

    try {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'follow_location' => true,
                'max_redirects' => 5,
                'header' => array(
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1'
                )
            )
        ));

        $html = @file_get_contents($url, false, $context);

        if ($html === false) {
            $result['details'] = 'Failed to fetch page - server might be blocking requests or URL is inaccessible';
            $result['suggestions'] = array(
                'Check if URL is accessible in browser',
                'Website might be blocking automated requests',
                'Try a different URL from the same site'
            );
        } else {
            $result['success'] = true;
            $result['content'] = $html;
            $result['size'] = strlen($html);
            $result['details'] = 'Successfully fetched ' . number_format(strlen($html)) . ' bytes';

            // Check for common blocking indicators
            if (stripos($html, 'cloudflare') !== false) {
                $result['warnings'][] = 'Cloudflare detected - might be blocking requests';
            }
            if (stripos($html, 'captcha') !== false) {
                $result['warnings'][] = 'CAPTCHA detected - automated requests likely blocked';
            }
            if (stripos($html, 'bot') !== false && stripos($html, 'blocked') !== false) {
                $result['warnings'][] = 'Bot blocking detected';
            }
        }
    } catch (Exception $e) {
        $result['details'] = 'Exception: ' . $e->getMessage();
    }

    return $result;
}

function parseHtmlDebug($html) {
    $result = array('step' => 'HTML Parse', 'success' => false, 'details' => '');

    try {
        $dom = new DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $success = $dom->loadHTML($html);
        libxml_clear_errors();

        if ($success) {
            $result['success'] = true;
            $result['dom'] = $dom;
            $result['details'] = 'HTML parsed successfully';

            // Get some basic stats
            $links = $dom->getElementsByTagName('a');
            $result['link_count'] = $links->length;

        } else {
            $result['details'] = 'Failed to parse HTML - malformed document';
        }
    } catch (Exception $e) {
        $result['details'] = 'Exception: ' . $e->getMessage();
    }

    return $result;
}

function testSelectors($xpath, $customSelector, $url) {
    $result = array('step' => 'Selector Tests', 'tests' => array());

    // Build list of selectors to test
    $selectors = array();

    // Add custom selector first if provided
    if (!empty($customSelector)) {
        $selectors['Custom: ' . $customSelector] = $customSelector;
    }

    // Common job-related selectors
    $commonSelectors = array(
        'Generic job links' => "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job')]",
        'Links with job in href' => "//a[contains(@href, 'job')]",
        'Links with career in href' => "//a[contains(@href, 'career')]",
        'Links with position in href' => "//a[contains(@href, 'position')]",
        'Job title classes' => "//a[contains(@class, 'job') or contains(@class, 'position') or contains(@class, 'posting')]",
        'All links' => "//a[@href]"
    );

    $selectors = array_merge($selectors, $commonSelectors);

    foreach ($selectors as $name => $selector) {
        $test = array('name' => $name, 'selector' => $selector, 'success' => false);

        try {
            $elements = $xpath->query($selector);
            $test['count'] = $elements->length;
            $test['success'] = true;

            $test['samples'] = array();
            $sampleCount = min(5, $elements->length);

            for ($i = 0; $i < $sampleCount; $i++) {
                $element = $elements->item($i);
                $href = $element->getAttribute('href');
                $text = trim($element->textContent);

                // Make absolute URL
                if (!empty($href) && !parse_url($href, PHP_URL_SCHEME)) {
                    $base = parse_url($url);
                    if (strpos($href, '//') === 0) {
                        $href = $base['scheme'] . ':' . $href;
                    } elseif (strpos($href, '/') === 0) {
                        $href = $base['scheme'] . '://' . $base['host'] . $href;
                    } else {
                        $href = $base['scheme'] . '://' . $base['host'] . '/' . ltrim($href, '/');
                    }
                }

                $test['samples'][] = array(
                    'text' => substr($text, 0, 100),
                    'href' => $href
                );
            }

        } catch (Exception $e) {
            $test['error'] = $e->getMessage();
        }

        $result['tests'][] = $test;
    }

    return $result;
}

function analyzeContent($html, $url) {
    $result = array('step' => 'Content Analysis', 'analysis' => array());

    // Check for JavaScript-heavy sites
    $jsCount = substr_count(strtolower($html), '<script');
    $result['analysis']['javascript_count'] = $jsCount;
    $result['analysis']['likely_js_heavy'] = $jsCount > 10;

    // Check for common job board patterns
    $jobKeywords = array('job', 'career', 'position', 'opening', 'vacancy', 'employment', 'hiring');
    $keywordCount = 0;
    foreach ($jobKeywords as $keyword) {
        $keywordCount += substr_count(strtolower($html), $keyword);
    }
    $result['analysis']['job_keyword_count'] = $keywordCount;

    // Check for common job board technologies
    $technologies = array(
        'React' => stripos($html, 'react') !== false,
        'Angular' => stripos($html, 'angular') !== false,
        'Vue' => stripos($html, 'vue') !== false,
        'Workday' => stripos($html, 'workday') !== false,
        'Greenhouse' => stripos($html, 'greenhouse') !== false,
        'Lever' => stripos($html, 'lever') !== false,
        'BambooHR' => stripos($html, 'bamboo') !== false
    );

    $result['analysis']['technologies'] = array_filter($technologies);

    // Analyze domain
    $domain = parse_url($url, PHP_URL_HOST);
    $result['analysis']['domain'] = $domain;
    $result['analysis']['is_subdomain'] = substr_count($domain, '.') > 1;

    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraping Debugger - Job Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-success { background-color: #d1e7dd; }
        .test-warning { background-color: #fff3cd; }
        .test-danger { background-color: #f8d7da; }
        .code-block { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
        .step-card { margin-bottom: 1rem; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">
                <i class="bi bi-bug-fill me-2"></i>
                Scraping Debugger
            </span>
            <a href="index.php" class="btn btn-outline-light btn-sm">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-4">
                    <i class="bi bi-search me-2"></i>
                    Advanced Scraping Debugger
                </h1>
                <p class="text-muted">Test and debug job scraping for any website</p>
            </div>
        </div>

        <!-- Input Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Test URL</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Careers Page URL</label>
                                    <input type="url" class="form-control" name="url" required
                                           placeholder="https://jobs.netflix.com/"
                                           value="<?php echo htmlspecialchars($url); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Custom CSS Selector (Optional)</label>
                                    <input type="text" class="form-control" name="selector"
                                           placeholder="a[href*='job']"
                                           value="<?php echo htmlspecialchars($selector); ?>">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-play-circle me-1"></i>
                                    Debug Scraping
                                </button>
                            </div>
                        </form>

                        <!-- Quick Test URLs -->
                        <div class="mt-3">
                            <h6>Quick Test URLs:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-secondary btn-sm" onclick="fillUrl('https://jobs.netflix.com/')">Netflix</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="fillUrl('https://www.shopify.com/careers')">Shopify</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="fillUrl('https://stripe.com/jobs')">Stripe</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="fillUrl('https://slack.com/careers')">Slack</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="fillUrl('https://github.com/about/careers')">GitHub</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <?php if ($results): ?>
            <div class="row">
                <div class="col-12">
                    <h2 class="h4 mb-3">Debug Results for: <?php echo htmlspecialchars($results['url']); ?></h2>

                    <?php foreach ($results['steps'] as $stepKey => $step): ?>
                        <div class="card step-card">
                            <div class="card-header <?php echo isset($step['success']) ? ($step['success'] ? 'bg-success text-white' : 'bg-danger text-white') : 'bg-secondary text-white'; ?>">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-<?php echo isset($step['success']) ? ($step['success'] ? 'check-circle' : 'x-circle') : 'info-circle'; ?> me-2"></i>
                                    <?php echo $step['step']; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (isset($step['details'])): ?>
                                    <p><?php echo htmlspecialchars($step['details']); ?></p>
                                <?php endif; ?>

                                <?php if (isset($step['warnings'])): ?>
                                    <?php foreach ($step['warnings'] as $warning): ?>
                                        <div class="alert alert-warning alert-sm">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <?php echo htmlspecialchars($warning); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (isset($step['suggestions'])): ?>
                                    <h6>Suggestions:</h6>
                                    <ul>
                                        <?php foreach ($step['suggestions'] as $suggestion): ?>
                                            <li><?php echo htmlspecialchars($suggestion); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if (isset($step['size'])): ?>
                                    <p><strong>Page size:</strong> <?php echo number_format($step['size']); ?> bytes</p>
                                <?php endif; ?>

                                <?php if (isset($step['link_count'])): ?>
                                    <p><strong>Total links found:</strong> <?php echo $step['link_count']; ?></p>
                                <?php endif; ?>

                                <!-- Selector Tests Results -->
                                <?php if (isset($step['tests'])): ?>
                                    <h6>Selector Test Results:</h6>
                                    <?php foreach ($step['tests'] as $test): ?>
                                        <div class="mb-3 p-3 border rounded <?php echo $test['success'] && $test['count'] > 0 ? 'test-success' : ($test['success'] ? 'test-warning' : 'test-danger'); ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($test['name']); ?></strong>
                                                    <div class="code-block mt-1"><?php echo htmlspecialchars($test['selector']); ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($test['success']): ?>
                                                        <span class="badge bg-primary"><?php echo $test['count']; ?> found</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Error</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (isset($test['error'])): ?>
                                                <div class="text-danger mt-2">Error: <?php echo htmlspecialchars($test['error']); ?></div>
                                            <?php endif; ?>

                                            <?php if (isset($test['samples']) && !empty($test['samples'])): ?>
                                                <h6 class="mt-3">Sample Results:</h6>
                                                <?php foreach ($test['samples'] as $sample): ?>
                                                    <div class="mb-2">
                                                        <div><strong>Text:</strong> <?php echo htmlspecialchars($sample['text']); ?></div>
                                                        <div><small><strong>URL:</strong> <a href="<?php echo htmlspecialchars($sample['href']); ?>" target="_blank"><?php echo htmlspecialchars($sample['href']); ?></a></small></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Content Analysis -->
                                <?php if (isset($step['analysis'])): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Content Analysis:</h6>
                                            <ul>
                                                <li><strong>JavaScript Scripts:</strong> <?php echo $step['analysis']['javascript_count']; ?></li>
                                                <li><strong>Job Keywords:</strong> <?php echo $step['analysis']['job_keyword_count']; ?></li>
                                                <li><strong>Likely JS-Heavy:</strong> <?php echo $step['analysis']['likely_js_heavy'] ? 'Yes ⚠️' : 'No ✅'; ?></li>
                                                <li><strong>Domain:</strong> <?php echo htmlspecialchars($step['analysis']['domain']); ?></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if (!empty($step['analysis']['technologies'])): ?>
                                                <h6>Technologies Detected:</h6>
                                                <ul>
                                                    <?php foreach ($step['analysis']['technologies'] as $tech => $detected): ?>
                                                        <li><?php echo htmlspecialchars($tech); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightbulb me-2"></i>
                                Recommendations
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recommendations = array();

                            // Check if any selectors found jobs
                            $foundJobs = false;
                            if (isset($results['steps']['selector_tests']['tests'])) {
                                foreach ($results['steps']['selector_tests']['tests'] as $test) {
                                    if ($test['success'] && $test['count'] > 0) {
                                        $foundJobs = true;
                                        $recommendations[] = "✅ Use selector: <code>" . htmlspecialchars($test['selector']) . "</code> (found " . $test['count'] . " matches)";
                                    }
                                }
                            }

                            if (!$foundJobs) {
                                $recommendations[] = "❌ No job listings found with any selector";
                                $recommendations[] = "🔍 Try inspecting the page source manually to find job links";
                                $recommendations[] = "⚠️ This site might use JavaScript to load jobs (our scraper can't handle JS)";
                            }

                            // Check for JS-heavy site
                            if (isset($results['steps']['content_analysis']['analysis']['likely_js_heavy']) &&
                                $results['steps']['content_analysis']['analysis']['likely_js_heavy']) {
                                $recommendations[] = "⚠️ This appears to be a JavaScript-heavy site - jobs might load dynamically";
                                $recommendations[] = "💡 Consider using a different careers page or look for a simpler job listing page";
                            }

                            // Check for blocking
                            if (isset($results['steps']['page_fetch']['warnings'])) {
                                $recommendations[] = "🚫 Website appears to be blocking automated requests";
                                $recommendations[] = "💡 Try testing with a different company's careers page";
                            }

                            foreach ($recommendations as $rec) {
                                echo "<p>$rec</p>";
                            }
                            ?>

                            <div class="mt-3">
                                <h6>Alternative Sites to Test:</h6>
                                <ul>
                                    <li><strong>Simple job boards:</strong> jobs.netflix.com, stripe.com/jobs, slack.com/careers</li>
                                    <li><strong>GitHub jobs:</strong> github.com/about/careers (very simple structure)</li>
                                    <li><strong>Shopify:</strong> shopify.com/careers (good for testing)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillUrl(url) {
            document.querySelector('input[name="url"]').value = url;
        }
    </script>
</body>
</html>
