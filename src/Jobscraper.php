<?php
// Improved JobScraper.php with better CSS selector support
class JobScraper {
    private $pdo;
    private $company;

    public function __construct(Database $db, Company $company) {
        $this->pdo = $db->getConnection();
        $this->company = $company;
    }

    public function scrapeCompany($companyData) {
        $url = $companyData['careers_url'];
        $selector = $companyData['selector'] ?: 'a';

        try {
            $html = $this->fetchPage($url);
            if (!$html) {
                throw new Exception("Failed to fetch page");
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $jobs = $this->extractJobs($xpath, $selector, $url);

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
                'user_agent' => 'Mozilla/5.0 (compatible; JobMonitor/1.0)',
                'follow_location' => true,
                'header' => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: gzip, deflate',
                    'DNT: 1',
                    'Connection: keep-alive'
                ]
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function extractJobs($xpath, $selector, $baseUrl) {
        $jobs = [];
        $xpathQuery = $this->cssToXpath($selector);

        try {
            $elements = $xpath->query($xpathQuery);
        } catch (Exception $e) {
            // If XPath fails, try simpler fallback
            error_log("XPath query failed: " . $e->getMessage());
            $elements = $xpath->query("//a[contains(@href, 'job') or contains(@href, 'career')]");
        }

        if ($elements) {
            foreach ($elements as $element) {
                $title = trim($element->textContent);
                $href = $element->getAttribute('href');

                if (empty($title) || strlen($title) < 3) continue;
                if (preg_match('/\b(home|about|contact|login|search|filter|sort|page|next|previous)\b/i', $title)) continue;

                // Skip common navigation or non-job links
                if (preg_match('/^(home|about|contact|login|logout|search|filter|apply now|learn more|view all)$/i', trim($title))) continue;

                $url = $this->makeAbsoluteUrl($href, $baseUrl);
                $contentHash = hash('sha256', $title . $url);

                $jobs[] = [
                    'title' => $title,
                    'url' => $url,
                    'content_hash' => $contentHash
                ];
            }
        }

        return $jobs;
    }

    private function cssToXpath($selector) {
        $selector = trim($selector);

        // Default case for 'a' selector
        if ($selector === 'a') {
            return "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'position')]";
        }

        // Class selector (e.g., .jobTitle-link)
        if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $className = $matches[1];
            return "//a[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
        }

        // Element with class (e.g., a.jobTitle-link)
        if (preg_match('/^([a-zA-Z0-9]+)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $element = $matches[1];
            $className = $matches[2];
            return "//$element" . "[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
        }

        // ID selector (e.g., #job-list)
        if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $id = $matches[1];
            return "//*[@id='$id']//a";
        }

        // Attribute selector (e.g., a[href*="job"])
        if (preg_match('/^([a-zA-Z0-9]*)\[([^=]+)([*^$|~]?)=(["\'])([^"\']*)\4\]$/', $selector, $matches)) {
            $element = $matches[1] ?: '*';
            $attribute = $matches[2];
            $operator = $matches[3];
            $value = $matches[5];

            switch ($operator) {
                case '*': // contains
                    return "//$element" . "[contains(@$attribute, '$value')]";
                case '^': // starts with
                    return "//$element" . "[starts-with(@$attribute, '$value')]";
                case '$': // ends with
                    return "//$element" . "[substring(@$attribute, string-length(@$attribute) - string-length('$value') + 1) = '$value']";
                case '~': // word match
                    return "//$element" . "[contains(concat(' ', normalize-space(@$attribute), ' '), ' $value ')]";
                default: // exact match
                    return "//$element" . "[@$attribute='$value']";
            }
        }

        // Data attribute (e.g., [data-job-id])
        if (preg_match('/^\[([a-zA-Z0-9_-]+)\]$/', $selector, $matches)) {
            $attribute = $matches[1];
            return "//a[@$attribute]";
        }

        // Descendant selector (e.g., .job-listing a)
        if (preg_match('/^\.([a-zA-Z0-9_-]+)\s+([a-zA-Z0-9]+)$/', $selector, $matches)) {
            $className = $matches[1];
            $element = $matches[2];
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]//$element";
        }

        // Complex selectors - try to handle some common patterns
        if (strpos($selector, ' ') !== false) {
            // Simple descendant selector handling
            $parts = explode(' ', $selector);
            $xpath = '';
            foreach ($parts as $part) {
                if (strpos($part, '.') === 0) {
                    $className = substr($part, 1);
                    $xpath .= "//*[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
                } else {
                    $xpath .= "//$part";
                }
            }
            return $xpath;
        }

        // Fallback: treat as element selector
        return "//$selector";
    }

    private function makeAbsoluteUrl($href, $baseUrl) {
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
            INSERT INTO jobs (company_id, title, url, content_hash, status)
            VALUES (?, ?, ?, ?, 'new')
            ON DUPLICATE KEY UPDATE
                last_seen = NOW(),
                status = 'existing'
        ");
        return $stmt->execute([
            $companyId,
            $job['title'],
            $job['url'],
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
