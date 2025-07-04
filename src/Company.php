<?php
class Company {
    private $pdo;

    public function __construct(Database $db) {
        $this->pdo = $db->getConnection();
    }

    /**
     * Add a new company
     */
    public function add($name, $careers_url, $selector = null, $location_selector = null,
                       $description_selector = null, $website_url = null, $logo_url = null, $industry = null) {

        // Validate required fields
        if (empty($name) || empty($careers_url)) {
            throw new Exception('Company name and careers URL are required');
        }

        // Validate URL format
        if (!filter_var($careers_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid careers URL format');
        }

        // Check for duplicate careers URL
        if ($this->urlExists($careers_url)) {
            throw new Exception('A company with this careers URL already exists');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO companies (
                name, careers_url, selector, location_selector,
                description_selector, website_url, logo_url, industry, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        return $stmt->execute([
            $name, $careers_url, $selector, $location_selector,
            $description_selector, $website_url, $logo_url, $industry
        ]);
    }

    /**
     * Update an existing company
     */
    public function update($id, $name, $careers_url, $selector = null, $location_selector = null,
                          $description_selector = null, $website_url = null, $logo_url = null, $industry = null) {

        if (empty($name) || empty($careers_url)) {
            throw new Exception('Company name and careers URL are required');
        }

        if (!filter_var($careers_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid careers URL format');
        }

        // Check for duplicate URL (excluding current company)
        if ($this->urlExists($careers_url, $id)) {
            throw new Exception('A company with this careers URL already exists');
        }

        $stmt = $this->pdo->prepare("
            UPDATE companies
            SET name = ?, careers_url = ?, selector = ?, location_selector = ?,
                description_selector = ?, website_url = ?, logo_url = ?, industry = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([
            $name, $careers_url, $selector, $location_selector,
            $description_selector, $website_url, $logo_url, $industry, $id
        ]);
    }

    /**
     * Get all companies with job counts
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT
                c.*,
                COUNT(j.id) as job_count,
                COUNT(CASE WHEN j.status = 'new' THEN 1 END) as new_jobs_count,
                COUNT(CASE WHEN j.is_remote = 1 THEN 1 END) as remote_jobs_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id
                AND j.status IN ('new', 'existing')
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active companies ordered by last checked (for monitoring)
     */
    public function getActive() {
        $stmt = $this->pdo->query("
            SELECT
                c.*,
                COUNT(j.id) as job_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id
                AND j.status IN ('new', 'existing')
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.last_checked ASC NULLS FIRST, c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single company by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT
                c.*,
                COUNT(j.id) as job_count,
                COUNT(CASE WHEN j.status = 'new' THEN 1 END) as new_jobs_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id
                AND j.status IN ('new', 'existing')
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update last checked timestamp
     */
    public function updateLastChecked($id) {
        $stmt = $this->pdo->prepare("
            UPDATE companies
            SET last_checked = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Update company status
     */
    public function updateStatus($id, $status) {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Invalid status. Must be active or inactive');
        }

        $stmt = $this->pdo->prepare("
            UPDATE companies
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Delete a company and all its jobs
     */
    public function delete($id) {
        // Jobs will be deleted automatically due to foreign key constraint
        $stmt = $this->pdo->prepare("DELETE FROM companies WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Search companies
     */
    public function search($query, $status = null, $industry = null) {
        $sql = "
            SELECT
                c.*,
                COUNT(j.id) as job_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id
                AND j.status IN ('new', 'existing')
            WHERE 1=1
        ";

        $params = [];

        if (!empty($query)) {
            $sql .= " AND (c.name LIKE ? OR c.industry LIKE ?)";
            $searchTerm = "%$query%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        if ($industry) {
            $sql .= " AND c.industry = ?";
            $params[] = $industry;
        }

        $sql .= " GROUP BY c.id ORDER BY c.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get companies by industry
     */
    public function getByIndustry($industry) {
        $stmt = $this->pdo->prepare("
            SELECT
                c.*,
                COUNT(j.id) as job_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id
                AND j.status IN ('new', 'existing')
            WHERE c.industry = ? AND c.status = 'active'
            GROUP BY c.id
            ORDER BY job_count DESC, c.name ASC
        ");
        $stmt->execute([$industry]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get companies that need checking (haven't been checked recently)
     */
    public function getNeedingUpdate($hours = 24) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM companies
            WHERE status = 'active'
            AND (last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL ? HOUR))
            ORDER BY last_checked ASC NULLS FIRST
            LIMIT 50
        ");
        $stmt->execute([$hours]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get company statistics
     */
    public function getStats() {
        $stats = [];

        // Total companies
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM companies");
        $stats['total'] = $stmt->fetchColumn();

        // Active companies
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
        $stats['active'] = $stmt->fetchColumn();

        // Companies by industry
        $stmt = $this->pdo->query("
            SELECT industry, COUNT(*) as count
            FROM companies
            WHERE industry IS NOT NULL AND status = 'active'
            GROUP BY industry
            ORDER BY count DESC
        ");
        $stats['by_industry'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Companies with most jobs
        $stmt = $this->pdo->query("
            SELECT
                c.name,
                c.industry,
                COUNT(j.id) as job_count
            FROM companies c
            LEFT JOIN jobs j ON c.id = j.company_id AND j.status IN ('new', 'existing')
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY job_count DESC
            LIMIT 10
        ");
        $stats['top_companies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recently checked
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM companies
            WHERE status = 'active'
            AND last_checked >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['checked_today'] = $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Bulk update company statuses
     */
    public function bulkUpdateStatus($company_ids, $status) {
        if (empty($company_ids) || !in_array($status, ['active', 'inactive'])) {
            return false;
        }

        $placeholders = str_repeat('?,', count($company_ids) - 1) . '?';
        $params = array_merge([$status], $company_ids);

        $stmt = $this->pdo->prepare("
            UPDATE companies
            SET status = ?, updated_at = NOW()
            WHERE id IN ($placeholders)
        ");

        return $stmt->execute($params);
    }

    /**
     * Import companies from CSV
     */
    public function importFromCsv($csvData) {
        $imported = 0;
        $errors = [];

        foreach ($csvData as $index => $row) {
            try {
                if (empty($row['name']) || empty($row['careers_url'])) {
                    throw new Exception('Name and careers URL required');
                }

                $this->add(
                    $row['name'],
                    $row['careers_url'],
                    $row['selector'] ?? null,
                    $row['location_selector'] ?? null,
                    $row['description_selector'] ?? null,
                    $row['website_url'] ?? null,
                    $row['logo_url'] ?? null,
                    $row['industry'] ?? null
                );

                $imported++;

            } catch (Exception $e) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Export companies to array
     */
    public function exportToArray($includeStats = true) {
        if ($includeStats) {
            return $this->getAll();
        } else {
            $stmt = $this->pdo->query("SELECT * FROM companies ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Check if URL already exists
     */
    private function urlExists($careers_url, $excludeId = null) {
        $sql = "SELECT id FROM companies WHERE careers_url = ?";
        $params = [$careers_url];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Get companies with failed scraping attempts
     */
    public function getFailedScrapingCompanies($days = 7) {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   DATEDIFF(NOW(), c.last_checked) as days_since_check
            FROM companies c
            WHERE c.status = 'active'
            AND c.last_checked IS NOT NULL
            AND c.last_checked < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND NOT EXISTS (
                SELECT 1 FROM jobs j
                WHERE j.company_id = c.id
                AND j.first_seen >= DATE_SUB(NOW(), INTERVAL ? DAY)
            )
            ORDER BY c.last_checked ASC
        ");
        $stmt->execute([$days, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update company logo URL automatically
     */
    public function updateLogoFromWebsite($id) {
        $company = $this->getById($id);
        if (!$company || !$company['website_url']) {
            return false;
        }

        // Try to find logo from website
        try {
            $html = @file_get_contents($company['website_url']);
            if ($html) {
                $dom = new DOMDocument();
                @$dom->loadHTML($html);

                // Look for common logo patterns
                $xpath = new DOMXPath($dom);
                $logoSelectors = [
                    '//img[contains(@class, "logo")]/@src',
                    '//img[contains(@alt, "logo")]/@src',
                    '//img[contains(@id, "logo")]/@src',
                    '//link[@rel="icon"]/@href',
                    '//link[@rel="shortcut icon"]/@href'
                ];

                foreach ($logoSelectors as $selector) {
                    $nodes = $xpath->query($selector);
                    if ($nodes->length > 0) {
                        $logoUrl = $nodes->item(0)->nodeValue;

                        // Make absolute URL
                        if (!parse_url($logoUrl, PHP_URL_SCHEME)) {
                            $logoUrl = rtrim($company['website_url'], '/') . '/' . ltrim($logoUrl, '/');
                        }

                        // Update company logo
                        $stmt = $this->pdo->prepare("
                            UPDATE companies
                            SET logo_url = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        return $stmt->execute([$logoUrl, $id]);
                    }
                }
            }
        } catch (Exception $e) {
            // Fail silently for logo updates
        }

        return false;
    }

    /**
     * Get industry list
     */
    public function getIndustries() {
        $stmt = $this->pdo->query("
            SELECT industry, COUNT(*) as company_count
            FROM companies
            WHERE industry IS NOT NULL AND industry != ''
            GROUP BY industry
            ORDER BY company_count DESC, industry ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
