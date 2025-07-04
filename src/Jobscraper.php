<?php
class JobScraper {
    private $pdo;
    private $company;

    // Common location keywords for remote detection
    private $remoteKeywords = [
        'remote', 'anywhere', 'work from home', 'wfh', 'distributed',
        'virtual', 'telecommute', 'home office', 'location independent'
    ];

    // Common job type keywords
    private $jobTypeKeywords = [
        'full-time' => ['full-time', 'full time', 'permanent', 'fte'],
        'part-time' => ['part-time', 'part time', 'hourly'],
        'contract' => ['contract', 'contractor', 'freelance', 'consulting'],
        'internship' => ['intern', 'internship', 'trainee', 'co-op']
    ];

    // Experience level keywords
    private $experienceKeywords = [
        'entry' => ['entry', 'junior', 'graduate', 'associate', 'new grad'],
        'mid' => ['mid', 'intermediate', 'experienced', 'specialist'],
        'senior' => ['senior', 'lead', 'principal', 'staff'],
        'executive' => ['director', 'manager', 'head of', 'chief', 'vp', 'vice president']
    ];

    public function __construct(Database $db, Company $company) {
        $this->pdo = $db->getConnection();
        $this->company = $company;
    }

    public function scrapeCompany($companyData) {
        $url = $companyData['careers_url'];
        // Use the null coalescing operator (??) to prevent undefined key warnings
        $selector = $companyData['selector'] ?? 'a';
        $locationSelector = $companyData['location_selector'] ?? null;
        $descriptionSelector = $companyData['description_selector'] ?? null;

        try {
            $html = $this->fetchPage($url);
            if (!$html) {
                throw new Exception("Failed to fetch page");
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $jobs = $this->extractJobs($xpath, $selector, $locationSelector, $descriptionSelector, $url);

            $newJobs = [];
            foreach ($jobs as $job) {
                if ($this->isNewJob($companyData['id'], $job)) {
                    $this->saveJob($companyData['id'], $job);
                    $newJobs[] = $job;
                }
            }

            $this->markRemovedJobs($companyData['id'], $jobs);
            $this->company->updateLastChecked($companyData['id']);

            return $newJobs;

        } catch (Exception $e) {
            error_log("Scraping error for {$companyData['name']}: " . $e->getMessage());
            return false;
        }
    }

    private function fetchPage($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; JobFeedAggregator/1.0)',
                'follow_location' => true,
                'header' => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive'
                ]
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function extractJobs($xpath, $selector, $locationSelector, $descriptionSelector, $baseUrl) {
        $jobs = [];
        $xpathQuery = $this->cssToXpath($selector);
        $elements = $xpath->query($xpathQuery);

        foreach ($elements as $element) {
            $title = trim($element->textContent);
            $href = $element->getAttribute('href');

            if (empty($title) || strlen($title) < 3) continue;
            if (preg_match('/\b(home|about|contact|login|search|privacy|terms)\b/i', $title)) continue;

            $url = $this->makeAbsoluteUrl($href, $baseUrl);
            $contentHash = hash('sha256', $title . $url);

            // Extract additional information
            $location = $this->extractLocation($element, $locationSelector, $xpath);
            $description = $this->extractDescription($element, $descriptionSelector, $xpath, $url);

            // Analyze the job for additional metadata
            $jobType = $this->detectJobType($title, $description);
            $isRemote = $this->detectRemote($title, $location, $description);
            $experienceLevel = $this->detectExperienceLevel($title, $description);
            $department = $this->detectDepartment($title, $description);
            $salaryRange = $this->extractSalary($title, $description);

            $jobs[] = [
                'title' => $title,
                'url' => $url,
                'location' => $location,
                'description' => $description,
                'job_type' => $jobType,
                'is_remote' => $isRemote,
                'experience_level' => $experienceLevel,
                'department' => $department,
                'salary_range' => $salaryRange,
                'content_hash' => $contentHash
            ];
        }

        return $jobs;
    }

    private function extractLocation($element, $locationSelector, $xpath) {
        if (empty($locationSelector)) {
            // Try common location patterns
            $parent = $element->parentNode;

            // Look for siblings or children with location info
            $locationPatterns = [
                './/text()[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "location")]',
                './/span[contains(@class, "location")]',
                './/div[contains(@class, "location")]',
                './/span[contains(@class, "city")]',
                './/text()[contains(., ",")]' // Often locations have commas
            ];

            foreach ($locationPatterns as $pattern) {
                $locationNodes = $xpath->query($pattern, $parent);
                foreach ($locationNodes as $node) {
                    $text = trim($node->textContent);
                    if ($this->looksLikeLocation($text)) {
                        return $this->cleanLocation($text);
                    }
                }
            }
        } else {
            // Use custom selector
            $locationNodes = $xpath->query($this->cssToXpath($locationSelector), $element);
            if ($locationNodes->length > 0) {
                return $this->cleanLocation($locationNodes->item(0)->textContent);
            }
        }

        return null;
    }

    private function extractDescription($element, $descriptionSelector, $xpath, $jobUrl) {
        if (!empty($descriptionSelector)) {
            $descNodes = $xpath->query($this->cssToXpath($descriptionSelector), $element);
            if ($descNodes->length > 0) {
                return trim($descNodes->item(0)->textContent);
            }
        }

        // Try to get description from the job page directly
        if (!empty($jobUrl) && filter_var($jobUrl, FILTER_VALIDATE_URL)) {
            return $this->fetchJobDescription($jobUrl);
        }

        return null;
    }

    private function fetchJobDescription($url) {
        try {
            $html = $this->fetchPage($url);
            if (!$html) return null;

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Common description selectors
            $descriptionSelectors = [
                './/div[contains(@class, "description")]',
                './/div[contains(@class, "job-description")]',
                './/section[contains(@class, "description")]',
                './/div[contains(@id, "description")]',
                './/div[@role="main"]',
                './/main',
                './/article'
            ];

            foreach ($descriptionSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $text = trim($nodes->item(0)->textContent);
                    if (strlen($text) > 100) { // Reasonable description length
                        return substr($text, 0, 2000); // Limit to 2000 chars
                    }
                }
            }
        } catch (Exception $e) {
            // Fail silently for description fetching
            error_log("Failed to fetch description from {$url}: " . $e->getMessage());
        }

        return null;
    }

    private function looksLikeLocation($text) {
        $text = strtolower(trim($text));

        // Skip if too short or too long
        if (strlen($text) < 2 || strlen($text) > 100) return false;

        // Skip if contains job-related words
        $jobWords = ['job', 'position', 'role', 'apply', 'salary', 'experience'];
        foreach ($jobWords as $word) {
            if (str_contains($text, $word)) return false; // Using PHP 8 str_contains
        }

        // Look for location patterns
        return (
            preg_match('/\b[A-Z][a-z]+,\s*[A-Z]{2}\b/', $text) || // City, State
            preg_match('/\b[A-Z][a-z]+,\s*[A-Z][a-z]+\b/', $text) || // City, Country
            in_array($text, $this->remoteKeywords) ||
            preg_match('/\b(remote|anywhere|usa|canada|uk|europe|asia)\b/i', $text)
        );
    }

    private function cleanLocation($location) {
        $location = trim($location);
        $location = preg_replace('/^(location:|where:)\s*/i', '', $location);
        $location = preg_replace('/\s+/', ' ', $location);
        return substr($location, 0, 255); // Database limit
    }

    private function detectJobType($title, $description) {
        $text = strtolower($title . ' ' . ($description ?? ''));

        foreach ($this->jobTypeKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) { // Using PHP 8 str_contains
                    return $type;
                }
            }
        }

        return 'unknown';
    }

    private function detectRemote($title, $location, $description) {
        $text = strtolower($title . ' ' . ($location ?? '') . ' ' . ($description ?? ''));

        foreach ($this->remoteKeywords as $keyword) {
            if (str_contains($text, $keyword)) { // Using PHP 8 str_contains
                return true;
            }
        }

        return false;
    }

    private function detectExperienceLevel($title, $description) {
        $text = strtolower($title . ' ' . ($description ?? ''));

        foreach ($this->experienceKeywords as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) { // Using PHP 8 str_contains
                    return $level;
                }
            }
        }

        return 'unknown';
    }

    private function detectDepartment($title, $description) {
        $text = strtolower($title . ' ' . ($description ?? ''));

        $departments = [
            'engineering' => ['engineer', 'developer', 'software', 'technical', 'backend', 'frontend', 'devops'],
            'design' => ['designer', 'ux', 'ui', 'design', 'creative', 'visual'],
            'product' => ['product', 'pm', 'product manager'],
            'marketing' => ['marketing', 'growth', 'social media', 'content', 'seo'],
            'sales' => ['sales', 'account', 'business development', 'customer success'],
            'hr' => ['hr', 'human resources', 'people', 'talent', 'recruiting'],
            'finance' => ['finance', 'accounting', 'financial', 'analyst'],
            'operations' => ['operations', 'ops', 'logistics', 'supply chain'],
            'support' => ['support', 'customer service', 'help desk'],
            'data' => ['data', 'analytics', 'scientist', 'analyst', 'bi']
        ];

        foreach ($departments as $dept => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) { // Using PHP 8 str_contains
                    return $dept;
                }
            }
        }

        return null;
    }

    private function extractSalary($title, $description) {
        $text = $title . ' ' . ($description ?? '');

        // Look for salary patterns
        $patterns = [
            '/\$[\d,]+\s*-\s*\$[\d,]+/', // $50,000 - $70,000
            '/\$[\d,]+k?\s*-\s*[\d,]+k?/', // $50k - 70k
            '/[\d,]+\s*-\s*[\d,]+\s*USD/', // 50000 - 70000 USD
            '/salary:?\s*\$?[\d,]+/i' // Salary: $50000
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }

        return null;
    }

    private function cssToXpath($selector) {
        $selector = trim($selector);

        // Handle special cases
        if ($selector === 'a') {
            return "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'position')]";
        }

        // Basic CSS to XPath conversion
        $xpath = $selector;

        // Convert class selectors
        $xpath = preg_replace('/\.([a-zA-Z0-9_-]+)/', '[contains(@class, "$1")]', $xpath);

        // Convert ID selectors
        $xpath = preg_replace('/#([a-zA-Z0-9_-]+)/', '[@id="$1"]', $xpath);

        // Convert attribute selectors
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)\*="([^"]+)"\]/', '[contains(@$1, "$2")]', $xpath);
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)="([^"]+)"\]/', '[@$1="$2"]', $xpath);

        return "//" . $xpath;
    }

    private function makeAbsoluteUrl($href, $baseUrl) {
        if (empty($href)) return '';

        if (parse_url($href, PHP_URL_SCHEME)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $baseScheme = $base['scheme'] ?? 'https';
        $baseHost = $base['host'] ?? '';

        if (str_starts_with($href, '//')) { // Using PHP 8 str_starts_with
            return $baseScheme . ':' . $href;
        }

        if (str_starts_with($href, '/')) { // Using PHP 8 str_starts_with
            return $baseScheme . '://' . $baseHost . $href;
        }

        return $baseScheme . '://' . $baseHost . '/' . ltrim($href, '/');
    }

    private function isNewJob($companyId, $job) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM jobs
            WHERE company_id = ? AND content_hash = ?
        ");
        $stmt->execute([$companyId, $job['content_hash']]);
        return !$stmt->fetch();
    }

    private function saveJob($companyId, $job) {
        $stmt = $this->pdo->prepare("
            INSERT INTO jobs (
                company_id, title, url, description, location,
                job_type, is_remote, experience_level, department,
                salary_range, content_hash, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
            ON DUPLICATE KEY UPDATE
                last_seen = NOW(),
                status = 'existing',
                description = COALESCE(VALUES(description), description),
                location = COALESCE(VALUES(location), location),
                job_type = COALESCE(VALUES(job_type), job_type),
                is_remote = COALESCE(VALUES(is_remote), is_remote),
                experience_level = COALESCE(VALUES(experience_level), experience_level),
                department = COALESCE(VALUES(department), department),
                salary_range = COALESCE(VALUES(salary_range), salary_range)
        ");

        return $stmt->execute([
            $companyId,
            $job['title'],
            $job['url'],
            $job['description'],
            $job['location'],
            $job['job_type'],
            $job['is_remote'] ? 1 : 0,
            $job['experience_level'],
            $job['department'],
            $job['salary_range'],
            $job['content_hash']
        ]);
    }

    private function markRemovedJobs($companyId, $currentJobs) {
        $currentHashes = array_column($currentJobs, 'content_hash');

        if (!empty($currentHashes)) {
            $placeholders = str_repeat('?,', count($currentHashes) - 1) . '?';
            $stmt = $this->pdo->prepare("
                UPDATE jobs
                SET status = 'removed'
                WHERE company_id = ?
                AND content_hash NOT IN ($placeholders)
                AND status != 'removed'
            ");
            $params = array_merge([$companyId], $currentHashes);
            $stmt->execute($params);
        }
    }
}
