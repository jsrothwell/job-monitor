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
        $selector = $companyData['selector'];

        try {
            echo "Fetching: " . $url . "\n";
            $html = $this->fetchPage($url);
            if (!$html) {
                throw new Exception("Failed to fetch page");
            }
            echo "Page fetched: " . number_format(strlen($html)) . " bytes\n";

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $jobs = $this->extractJobs($xpath, $selector, $url);
            echo "Raw jobs extracted: " . count($jobs) . "\n";

            // Filter and process jobs
            $jobs = $this->filterJobs($jobs);
            echo "Jobs after filtering: " . count($jobs) . "\n";

            $newJobs = array();
            foreach ($jobs as $job) {
                if ($this->isNewJob($companyData['id'], $job)) {
                    $this->saveJob($companyData['id'], $job);
                    $newJobs[] = $job;
                }
            }

            $this->markRemovedJobs($companyData['id'], $jobs);
            $this->company->updateLastChecked($companyData['id']);

            echo "New jobs found: " . count($newJobs) . "\n";
            return $newJobs;

        } catch (Exception $e) {
            error_log("Scraping error for {$companyData['name']}: " . $e->getMessage());
            echo "Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function fetchPage($url) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'follow_location' => true,
                'max_redirects' => 5,
                'header' => array(
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none'
                )
            )
        ));

        return @file_get_contents($url, false, $context);
    }

    private function extractJobs($xpath, $customSelector, $baseUrl) {
        $jobs = array();

        // Determine which selectors to use
        $selectors = array();

        if (!empty($customSelector)) {
            $selectors[] = array('name' => 'custom', 'xpath' => $this->cssToXpath($customSelector));
        } else {
            $selectors = $this->getDefaultSelectors($baseUrl);
        }

        foreach ($selectors as $selectorInfo) {
            echo "Trying selector: " . $selectorInfo['name'] . "\n";

            try {
                $elements = $xpath->query($selectorInfo['xpath']);
                echo "  Found " . $elements->length . " elements\n";

                if ($elements->length > 0) {
                    $selectorJobs = $this->processElements($elements, $baseUrl);
                    echo "  Processed to " . count($selectorJobs) . " jobs\n";

                    if (count($selectorJobs) > 0) {
                        $jobs = array_merge($jobs, $selectorJobs);

                        // If custom selector or we found a good amount, stop trying others
                        if (!empty($customSelector) || count($selectorJobs) >= 5) {
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
                echo "  Error with selector: " . $e->getMessage() . "\n";
                continue;
            }
        }

        return $jobs;
    }

    private function getDefaultSelectors($url) {
        $domain = parse_url($url, PHP_URL_HOST);

        // Site-specific selectors
        $siteSpecific = array(
            'greenhouse.io' => array(
                array('name' => 'greenhouse', 'xpath' => "//a[contains(@class, 'posting-title')]"),
            ),
            'lever.co' => array(
                array('name' => 'lever', 'xpath' => "//a[@data-qa='posting-name' or contains(@class, 'posting-title')]"),
            ),
            'workday.com' => array(
                array('name' => 'workday', 'xpath' => "//a[@data-automation-id='jobTitle' or contains(@aria-label, 'job')]"),
            ),
            'bamboohr.com' => array(
                array('name' => 'bamboo', 'xpath' => "//a[contains(@class, 'BambooHR-ATS-Jobs-Item')]"),
            ),
            'jobs.netflix.com' => array(
                array('name' => 'netflix', 'xpath' => "//a[contains(@href, '/jobs/')]"),
            ),
            'careers.shopify.com' => array(
                array('name' => 'shopify', 'xpath' => "//a[contains(@class, 'job-listing') or contains(@href, '/careers/')]"),
            )
        );

        foreach ($siteSpecific as $site => $selectors) {
            if (strpos($domain, $site) !== false) {
                return $selectors;
            }
        }

        // Generic selectors in order of specificity
        return array(
            array('name' => 'job-specific-links', 'xpath' => "//a[contains(@href, 'job') and (contains(@href, '/') or contains(@href, '=')) and string-length(normalize-space(text())) > 3]"),
            array('name' => 'career-links', 'xpath' => "//a[contains(@href, 'career') and contains(@href, '/') and string-length(normalize-space(text())) > 3]"),
            array('name' => 'position-links', 'xpath' => "//a[contains(@href, 'position') and string-length(normalize-space(text())) > 3]"),
            array('name' => 'job-classes', 'xpath' => "//a[contains(@class, 'job') or contains(@class, 'position') or contains(@class, 'posting') or contains(@class, 'career')]"),
            array('name' => 'job-text-content', 'xpath' => "//a[contains(translate(normalize-space(text()), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'engineer') or contains(translate(normalize-space(text()), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'developer') or contains(translate(normalize-space(text()), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'manager') or contains(translate(normalize-space(text()), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'analyst')]"),
            array('name' => 'general-job-indicators', 'xpath' => "//a[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'position') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'opening')]")
        );
    }

    private function processElements($elements, $baseUrl) {
        $jobs = array();

        foreach ($elements as $element) {
            $title = trim($element->textContent);
            $href = $element->getAttribute('href');

            // Skip if title is too short or contains common non-job words
            if (strlen($title) < 3) continue;
            if (preg_match('/\b(home|about|contact|login|search|privacy|terms|cookie|support)\b/i', $title)) continue;

            // Clean up title
            $title = $this->cleanTitle($title);
            if (empty($title)) continue;

            $url = $this->makeAbsoluteUrl($href, $baseUrl);
            $contentHash = hash('sha256', $title . $url);

            $jobs[] = array(
                'title' => $title,
                'url' => $url,
                'content_hash' => $contentHash
            );
        }

        return $jobs;
    }

    private function filterJobs($jobs) {
        $filtered = array();
        $seen = array();

        foreach ($jobs as $job) {
            // Remove duplicates based on title similarity
            $normalizedTitle = strtolower(trim($job['title']));
            $isDuplicate = false;

            foreach ($seen as $seenTitle) {
                $similarity = 0;
                similar_text($normalizedTitle, $seenTitle, $similarity);
                if ($similarity > 90) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $filtered[] = $job;
                $seen[] = $normalizedTitle;
            }
        }

        return $filtered;
    }

    private function cleanTitle($title) {
        // Remove excessive whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        // Remove common prefixes/suffixes
        $title = preg_replace('/^(job|position|career|opening):\s*/i', '', $title);
        $title = preg_replace('/\s*-\s*(apply now|view job|learn more)$/i', '', $title);

        // Remove location indicators that are too long
        $title = preg_replace('/\s*\([^)]{30,}\)$/', '', $title);

        return trim($title);
    }

    private function cssToXpath($selector) {
        $selector = trim($selector);

        // Handle some basic CSS selectors
        if (strpos($selector, '[') !== false && strpos($selector, ']') !== false) {
            // Attribute selector - return as is for XPath
            if (strpos($selector, '=') !== false) {
                return "//" . $selector;
            }
        }

        if (strpos($selector, '.') === 0) {
            // Class selector
            $class = substr($selector, 1);
            return "//a[contains(@class, '$class')]";
        }

        if ($selector === 'a') {
            return "//a[contains(@href, 'job') or contains(@href, 'career') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'job')]";
        }

        // Default: treat as XPath if it starts with //
        if (strpos($selector, '//') === 0) {
            return $selector;
        }

        // Otherwise, treat as tag name
        return "//" . $selector;
    }

    private function makeAbsoluteUrl($href, $baseUrl) {
        if (empty($href)) return '';

        if (parse_url($href, PHP_URL_SCHEME)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $baseScheme = isset($base['scheme']) ? $base['scheme'] : 'https';
        $baseHost = isset($base['host']) ? $base['host'] : '';

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
        $stmt->execute(array($companyId, $job['content_hash']));
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
        return $stmt->execute(array(
            $companyId,
            $job['title'],
            $job['url'],
            $job['content_hash']
        ));
    }

    private function markRemovedJobs($companyId, $currentJobs) {
        if (empty($currentJobs)) return;

        $currentHashes = array();
        foreach ($currentJobs as $job) {
            $currentHashes[] = $job['content_hash'];
        }

        $placeholders = str_repeat('?,', count($currentHashes) - 1) . '?';
        $stmt = $this->pdo->prepare("
            UPDATE jobs
            SET status = 'removed'
            WHERE company_id = ?
            AND content_hash NOT IN ($placeholders)
            AND status != 'removed'
        ");
        $params = array_merge(array($companyId), $currentHashes);
        $stmt->execute($params);
    }
}
