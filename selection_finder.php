<?php
// selector_finder.php - Automatic CSS Selector Finder for Careers Pages
$results = null;
$pageSource = null;
$error = null;
$url = $_GET['url'] ?? $_POST['url'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = trim($_POST['url']);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Invalid URL format";
    } else {
        $results = analyzeCareerPage($url);
    }
}

function analyzeCareerPage($url) {
    $analysis = [
        'url' => $url,
        'success' => false,
        'page_info' => [],
        'tested_selectors' => [],
        'best_selectors' => [],
        'html_structure' => [],
        'raw_html' => ''
    ];

    // Fetch page content
    $html = fetchPageContent($url);

    if (!$html) {
        $analysis['error'] = 'Could not fetch page content. The site may block automated requests or require JavaScript.';
        return $analysis;
    }

    $analysis['success'] = true;
    $analysis['raw_html'] = $html;
    $analysis['page_info'] = analyzePageInfo($html);

    // Test various selectors
    $testSelectors = [
        // Class-based selectors
        '.job-title',
        '.job-title a',
        '.jobTitle',
        '.jobTitle-link',
        '.job-link',
        '.position-title',
        '.posting-title',
        '.listing-title',
        '.career-title',
        '.opening-title',
        '.vacancy-title',
        '.role-title',

        // Element + class combinations
        'a.job-title',
        'a.jobTitle',
        'a.job-link',
        'a.position-link',
        'a.posting-link',

        // Container-based selectors
        '.job-listing a',
        '.job-item a',
        '.position-item a',
        '.posting-item a',
        '.career-listing a',
        '.search-results a',
        '.results-list a',
        '.jobs-list a',
        '.openings-list a',

        // Attribute-based selectors
        'a[href*="job"]',
        'a[href*="career"]',
        'a[href*="position"]',
        'a[href*="opening"]',
        'a[href*="vacancy"]',
        'a[href*="apply"]',
        'a[title*="job"]',
        'a[title*="Apply"]',

        // Data attribute selectors
        '[data-job-id]',
        '[data-job-title]',
        '[data-position]',
        'a[data-job]',
        'a[data-position]',

        // Header-based selectors
        'h1 a',
        'h2 a',
        'h3 a',
        'h4 a',
        'h5 a',

        // Table-based selectors
        'tr a',
        'td a',
        '.table a',

        // List-based selectors
        'li a',
        'ul a',

        // Generic patterns
        '.title a',
        '.name a',
        '.link',
        'a[role="link"]'
    ];

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach ($testSelectors as $selector) {
        $result = testSelector($xpath, $selector, $url);
        if ($result) {
            $analysis['tested_selectors'][] = $result;
        }
    }

    // Sort by job count and quality
    usort($analysis['tested_selectors'], function($a, $b) {
        if ($a['job_count'] !== $b['job_count']) {
            return $b['job_count'] - $a['job_count'];
        }
        return $b['quality_score'] - $a['quality_score'];
    });

    // Get best selectors (top 5)
    $analysis['best_selectors'] = array_slice($analysis['tested_selectors'], 0, 5);

    // Analyze HTML structure around job links
    $analysis['html_structure'] = analyzeHtmlStructure($xpath);

    return $analysis;
}

function fetchPageContent($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'follow_location' => true,
            'header' => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]
    ]);

    $content = @file_get_contents($url, false, $context);

    // Handle gzip compression
    if ($content && function_exists('gzdecode') && strpos($content, "\x1f\x8b") === 0) {
        $content = gzdecode($content);
    }

    return $content;
}

function analyzePageInfo($html) {
    $info = [
        'size' => strlen($html),
        'title' => '',
        'has_jobs_text' => false,
        'has_career_text' => false,
        'framework_hints' => []
    ];

    // Extract title
    if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $matches)) {
        $info['title'] = trim(strip_tags($matches[1]));
    }

    // Check for job-related text
    $info['has_jobs_text'] = (stripos($html, 'job') !== false ||
                             stripos($html, 'career') !== false ||
                             stripos($html, 'position') !== false ||
                             stripos($html, 'opening') !== false);

    // Detect frameworks/platforms
    if (stripos($html, 'workday') !== false) $info['framework_hints'][] = 'Workday';
    if (stripos($html, 'greenhouse') !== false) $info['framework_hints'][] = 'Greenhouse';
    if (stripos($html, 'lever.co') !== false) $info['framework_hints'][] = 'Lever';
    if (stripos($html, 'bamboohr') !== false) $info['framework_hints'][] = 'BambooHR';
    if (stripos($html, 'smartrecruiters') !== false) $info['framework_hints'][] = 'SmartRecruiters';
    if (stripos($html, 'successfactors') !== false) $info['framework_hints'][] = 'SuccessFactors';

    return $info;
}

function testSelector($xpath, $selector, $baseUrl) {
    $xpathQuery = cssToXpath($selector);

    try {
        $elements = $xpath->query($xpathQuery);
        if (!$elements) return null;

        $jobs = [];
        $totalElements = $elements->length;

        foreach ($elements as $element) {
            $title = trim($element->textContent);
            $href = $element->getAttribute('href');

            if (empty($title) || strlen($title) < 3) continue;

            // Skip obvious navigation links
            if (preg_match('/^(home|about|contact|login|logout|search|filter|sort|page|next|previous|back|more|less|toggle|menu|close)$/i', trim($title))) {
                continue;
            }

            // Skip very generic text
            if (preg_match('/^(click here|read more|learn more|view all|see all|show more|apply now)$/i', trim($title))) {
                continue;
            }

            $url = makeAbsoluteUrl($href, $baseUrl);

            $jobs[] = [
                'title' => $title,
                'url' => $url,
                'element_tag' => $element->tagName,
                'element_class' => $element->getAttribute('class'),
                'element_id' => $element->getAttribute('id')
            ];

            // Limit to prevent memory issues
            if (count($jobs) >= 50) break;
        }

        // Calculate quality score
        $qualityScore = calculateQualityScore($jobs, $totalElements);

        return [
            'selector' => $selector,
            'xpath' => $xpathQuery,
            'job_count' => count($jobs),
            'total_elements' => $totalElements,
            'quality_score' => $qualityScore,
            'jobs' => array_slice($jobs, 0, 10), // Limit for display
            'sample_job' => !empty($jobs) ? $jobs[0] : null
        ];

    } catch (Exception $e) {
        return null;
    }
}

function calculateQualityScore($jobs, $totalElements) {
    if (empty($jobs)) return 0;

    $score = 0;

    // More jobs = higher score
    $score += min(count($jobs), 20) * 2;

    // Higher ratio of job links to total elements = better selector
    if ($totalElements > 0) {
        $ratio = count($jobs) / $totalElements;
        $score += $ratio * 20;
    }

    // Check if job titles look legitimate
    foreach ($jobs as $job) {
        $title = strtolower($job['title']);

        // Positive indicators
        if (preg_match('/\b(engineer|developer|manager|analyst|specialist|coordinator|assistant|director|lead|senior|junior|intern)\b/', $title)) {
            $score += 3;
        }

        if (preg_match('/\b(software|data|marketing|sales|hr|finance|operations|design|product|research)\b/', $title)) {
            $score += 2;
        }

        // Length indicates detailed job title
        if (strlen($title) > 15 && strlen($title) < 100) {
            $score += 1;
        }

        // Negative indicators
        if (preg_match('/\b(home|about|contact|login|search|filter|apply now|learn more)\b/', $title)) {
            $score -= 5;
        }
    }

    return max(0, $score);
}

function analyzeHtmlStructure($xpath) {
    $structure = [];

    // Find common job-related patterns
    $patterns = [
        'job-related-links' => "//a[contains(translate(@href, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(@href, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'career')]",
        'class-with-job' => "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job')]",
        'id-with-job' => "//*[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job')]",
        'data-attributes' => "//*[@data-job or @data-position or @data-opening]"
    ];

    foreach ($patterns as $name => $query) {
        try {
            $elements = $xpath->query($query);
            if ($elements && $elements->length > 0) {
                $structure[$name] = [];
                for ($i = 0; $i < min(5, $elements->length); $i++) {
                    $element = $elements->item($i);
                    $structure[$name][] = [
                        'tag' => $element->tagName,
                        'class' => $element->getAttribute('class'),
                        'id' => $element->getAttribute('id'),
                        'text' => trim(substr($element->textContent, 0, 100)),
                        'parent' => $element->parentNode ? $element->parentNode->tagName : ''
                    ];
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }
    }

    return $structure;
}

function cssToXpath($selector) {
    $selector = trim($selector);

    // Class selector
    if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        $className = $matches[1];
        return "//a[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
    }

    // Element with class
    if (preg_match('/^([a-zA-Z0-9]+)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        $element = $matches[1];
        $className = $matches[2];
        return "//$element" . "[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
    }

    // Descendant selector
    if (preg_match('/^\.([a-zA-Z0-9_-]+)\s+([a-zA-Z0-9]+)$/', $selector, $matches)) {
        $className = $matches[1];
        $element = $matches[2];
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]//$element";
    }

    // Attribute contains
    if (preg_match('/^([a-zA-Z0-9]*)\[([^=]+)\*=(["\'])([^"\']*)\3\]$/', $selector, $matches)) {
        $element = $matches[1] ?: '*';
        $attribute = $matches[2];
        $value = $matches[4];
        return "//$element" . "[contains(@$attribute, '$value')]";
    }

    // Data attribute exists
    if (preg_match('/^\[([a-zA-Z0-9_-]+)\]$/', $selector, $matches)) {
        $attribute = $matches[1];
        return "//a[@$attribute]";
    }

    // Header element with descendant
    if (preg_match('/^(h[1-6])\s+([a-zA-Z0-9]+)$/', $selector, $matches)) {
        $header = $matches[1];
        $element = $matches[2];
        return "//$header//$element";
    }

    // Simple element
    return "//$selector";
}

function makeAbsoluteUrl($href, $baseUrl) {
    if (empty($href)) return '';
    if (parse_url($href, PHP_URL_SCHEME)) return $href;

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Selector Finder - Job Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .selector-result {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .selector-result.low-score {
            border-left-color: #ffc107;
        }
        .selector-result.no-results {
            border-left-color: #dc3545;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .quality-badge {
            font-size: 0.8em;
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
                <i class="bi bi-search me-1"></i>
                CSS Selector Finder
            </span>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-4">
        <div class="container text-center">
            <h1 class="h3 fw-bold mb-2">
                <i class="bi bi-code-square me-2"></i>
                CSS Selector Finder
            </h1>
            <p class="mb-0">Automatically find the best CSS selector for any careers page</p>
        </div>
    </div>

    <div class="container py-4">
        <!-- URL Input Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-link-45deg me-2"></i>
                            Analyze Careers Page
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="analyzeForm">
                            <div class="row">
                                <div class="col-md-10">
                                    <label for="url" class="form-label">Careers Page URL</label>
                                    <input type="url" class="form-control" id="url" name="url" required
                                           placeholder="https://company.com/careers"
                                           value="<?= htmlspecialchars($url) ?>">
                                    <div class="form-text">Enter the direct URL to the company's job listings page</div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search me-1"></i>
                                        Analyze
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($results): ?>
            <!-- Page Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Page Analysis
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$results['success']): ?>
                                <div class="alert alert-danger">
                                    <strong>Failed to analyze page:</strong> <?= htmlspecialchars($results['error']) ?>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Page Information:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Title:</strong> <?= htmlspecialchars($results['page_info']['title']) ?></li>
                                            <li><strong>Size:</strong> <?= number_format($results['page_info']['size']) ?> characters</li>
                                            <li><strong>Contains job-related text:</strong>
                                                <?= $results['page_info']['has_jobs_text'] ? '✅ Yes' : '❌ No' ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($results['page_info']['framework_hints'])): ?>
                                            <h6>Detected Platforms:</h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($results['page_info']['framework_hints'] as $framework): ?>
                                                    <li><span class="badge bg-secondary"><?= htmlspecialchars($framework) ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($results['success'] && !empty($results['best_selectors'])): ?>
                <!-- Best Selectors -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-trophy me-2"></i>
                                    Recommended CSS Selectors
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($results['best_selectors'] as $index => $result): ?>
                                    <?php if ($result['job_count'] > 0): ?>
                                        <div class="selector-result p-3 mb-3 rounded <?= $result['quality_score'] < 10 ? 'low-score' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">
                                                    #<?= $index + 1 ?>
                                                    <code><?= htmlspecialchars($result['selector']) ?></code>
                                                </h6>
                                                <div>
                                                    <span class="badge bg-primary quality-badge">Score: <?= $result['quality_score'] ?></span>
                                                    <span class="badge bg-success quality-badge"><?= $result['job_count'] ?> jobs</span>
                                                </div>
                                            </div>

                                            <?php if ($result['sample_job']): ?>
                                                <div class="mt-2">
                                                    <strong>Sample job found:</strong>
                                                    <div class="code-block mt-1">
                                                        <strong>Title:</strong> <?= htmlspecialchars($result['sample_job']['title']) ?><br>
                                                        <?php if ($result['sample_job']['url']): ?>
                                                            <strong>URL:</strong> <a href="<?= htmlspecialchars($result['sample_job']['url']) ?>" target="_blank"><?= htmlspecialchars($result['sample_job']['url']) ?></a><br>
                                                        <?php endif; ?>
                                                        <strong>Element:</strong> &lt;<?= $result['sample_job']['element_tag'] ?><?php if ($result['sample_job']['element_class']): ?> class="<?= htmlspecialchars($result['sample_job']['element_class']) ?>"<?php endif; ?>&gt;
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-outline-primary me-2" onclick="copySelector('<?= htmlspecialchars($result['selector']) ?>')">
                                                    <i class="bi bi-clipboard me-1"></i>Copy Selector
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="showAllJobs(<?= $index ?>)">
                                                    <i class="bi bi-list me-1"></i>View All Jobs
                                                </button>
                                            </div>

                                            <div id="jobs-<?= $index ?>" class="mt-3" style="display: none;">
                                                <h6>All jobs found with this selector:</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Job Title</th>
                                                                <th>URL</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($result['jobs'] as $job): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                                                    <td>
                                                                        <?php if ($job['url']): ?>
                                                                            <a href="<?= htmlspecialchars($job['url']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 300px;">
                                                                                <?= htmlspecialchars($job['url']) ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <em>No URL</em>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if (empty(array_filter($results['best_selectors'], function($r) { return $r['job_count'] > 0; }))): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>No job links found!</strong> This could mean:
                                        <ul class="mt-2 mb-0">
                                            <li>The page loads jobs dynamically with JavaScript</li>
                                            <li>The page requires login or special access</li>
                                            <li>The page structure is very unique</li>
                                            <li>There are no jobs currently posted</li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How to Use -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-lightbulb me-2"></i>
                                    How to Use These Results
                                </h5>
                            </div>
                            <div class="card-body">
                                <ol>
                                    <li><strong>Choose the best selector</strong> from the recommendations above (highest score with reasonable job count)</li>
                                    <li><strong>Copy the selector</strong> by clicking the "Copy Selector" button</li>
                                    <li><strong>Add the company</strong> in your Job Monitor dashboard</li>
                                    <li><strong>Paste the selector</strong> in the "CSS Selector" field</li>
                                    <li><strong>Test it</strong> using the Test Tool to verify it works</li>
                                </ol>

                                <div class="alert alert-info mt-3">
                                    <strong>💡 Pro Tips:</strong>
                                    <ul class="mb-0">
                                        <li>Higher score generally means better quality</li>
                                        <li>Look for selectors that find 5-50 jobs (not too few, not too many)</li>
                                        <li>Check the sample jobs to ensure they look legitimate</li>
                                        <li>If no good selectors are found, the site might use JavaScript to load jobs</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Tested Selectors (Collapsible) -->
            <?php if ($results['success'] && !empty($results['tested_selectors'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-check me-2"></i>
                                    All Tested Selectors (<?= count($results['tested_selectors']) ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-outline-secondary btn-sm mb-3" onclick="toggleAllResults()">
                                    <i class="bi bi-eye me-1"></i>
                                    Show/Hide All Results
                                </button>

                                <div id="allResults" style="display: none;">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Selector</th>
                                                    <th>Jobs Found</th>
                                                    <th>Quality Score</th>
                                                    <th>Sample Title</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($results['tested_selectors'] as $result): ?>
                                                    <tr class="<?= $result['job_count'] == 0 ? 'table-danger' : ($result['quality_score'] > 15 ? 'table-success' : '') ?>">
                                                        <td><code><?= htmlspecialchars($result['selector']) ?></code></td>
                                                        <td><?= $result['job_count'] ?></td>
                                                        <td><?= $result['quality_score'] ?></td>
                                                        <td><?= $result['sample_job'] ? htmlspecialchars(substr($result['sample_job']['title'], 0, 50)) . (strlen($result['sample_job']['title']) > 50 ? '...' : '') : '-' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copySelector(selector) {
            navigator.clipboard.writeText(selector).then(function() {
                // Show a temporary success message
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
                button.classList.add('btn-success');
                button.classList.remove('btn-outline-primary');

                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                }, 2000);
            });
        }

        function showAllJobs(index) {
            const jobsDiv = document.getElementById('jobs-' + index);
            const button = event.target.closest('button');

            if (jobsDiv.style.display === 'none') {
                jobsDiv.style.display = 'block';
                button.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Hide Jobs';
            } else {
                jobsDiv.style.display = 'none';
                button.innerHTML = '<i class="bi bi-list me-1"></i>View All Jobs';
            }
        }

        function toggleAllResults() {
            const resultsDiv = document.getElementById('allResults');
            const button = event.target.closest('button');

            if (resultsDiv.style.display === 'none') {
                resultsDiv.style.display = 'block';
                button.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Hide All Results';
            } else {
                resultsDiv.style.display = 'none';
                button.innerHTML = '<i class="bi bi-eye me-1"></i>Show All Results';
            }
        }

        // Add loading state to form
        document.getElementById('analyzeForm').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Analyzing...';
            button.disabled = true;
        });
    </script>
</body>
</html>
