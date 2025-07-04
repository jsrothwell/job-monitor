<?php
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
                'follow_location' => true
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function extractJobs($xpath, $selector, $baseUrl) {
        $jobs = [];
        $xpathQuery = $this->cssToXpath($selector);
        $elements = $xpath->query($xpathQuery);

        foreach ($elements as $element) {
            $title = trim($element->textContent);
            $href = $element->getAttribute('href');

            if (empty($title) || strlen($title) < 3) continue;
            if (preg_match('/\b(home|about|contact|login|search)\b/i', $title)) continue;

            $url = $this->makeAbsoluteUrl($href, $baseUrl);
            $contentHash = hash('sha256', $title . $url);

            $jobs[] = [
                'title' => $title,
                'url' => $url,
                'content_hash' => $contentHash
            ];
        }

        return $jobs;
    }

    private function cssToXpath($selector) {
        $selector = trim($selector);

        if ($selector === 'a') {
            return "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'position')]";
        }

        return "//" . $selector;
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
