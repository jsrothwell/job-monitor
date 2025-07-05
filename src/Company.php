<?php
class Company {
    private $pdo;

    public function __construct(Database $db) {
        $this->pdo = $db->getConnection();
    }

    public function add($name, $careers_url, $selector = null) {
        try {
            // Validate inputs
            if (empty($name) || empty($careers_url)) {
                throw new Exception("Company name and careers URL are required");
            }

            // Validate URL format
            if (!filter_var($careers_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid URL format");
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO companies (name, careers_url, selector, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");

            $result = $stmt->execute(array($name, $careers_url, $selector));

            if (!$result) {
                throw new Exception("Failed to insert company into database");
            }

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            // Check for duplicate entry
            if ($e->getCode() == 23000) {
                throw new Exception("A company with this name or URL already exists");
            }
            throw new Exception("Database error: " . $e->getMessage());
        }
    }

    public function getAll() {
        try {
            $stmt = $this->pdo->query("
                SELECT companies.*,
                       (SELECT COUNT(*) FROM jobs WHERE company_id = companies.id) as job_count
                FROM companies
                ORDER BY name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Failed to retrieve companies: " . $e->getMessage());
        }
    }

    public function getActive() {
        try {
            // Simplified query without COALESCE for better compatibility
            $stmt = $this->pdo->query("
                SELECT * FROM companies
                WHERE status = 'active'
                ORDER BY
                    CASE WHEN last_checked IS NULL THEN 0 ELSE 1 END,
                    last_checked ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Failed to retrieve active companies: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmt->execute(array($id));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Failed to retrieve company: " . $e->getMessage());
        }
    }

    public function updateLastChecked($id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE companies
                SET last_checked = NOW()
                WHERE id = ?
            ");
            return $stmt->execute(array($id));
        } catch (PDOException $e) {
            throw new Exception("Failed to update last checked time: " . $e->getMessage());
        }
    }

    public function updateStatus($id, $status) {
        try {
            if (!in_array($status, array('active', 'inactive'))) {
                throw new Exception("Invalid status. Must be 'active' or 'inactive'");
            }

            $stmt = $this->pdo->prepare("
                UPDATE companies
                SET status = ?
                WHERE id = ?
            ");
            return $stmt->execute(array($status, $id));
        } catch (PDOException $e) {
            throw new Exception("Failed to update company status: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            // Check if company exists
            $company = $this->getById($id);
            if (!$company) {
                throw new Exception("Company not found");
            }

            $stmt = $this->pdo->prepare("DELETE FROM companies WHERE id = ?");
            $result = $stmt->execute(array($id));

            if (!$result) {
                throw new Exception("Failed to delete company");
            }

            return true;
        } catch (PDOException $e) {
            throw new Exception("Database error while deleting company: " . $e->getMessage());
        }
    }

    public function update($id, $name, $careers_url, $selector = null) {
        try {
            // Validate inputs
            if (empty($name) || empty($careers_url)) {
                throw new Exception("Company name and careers URL are required");
            }

            // Validate URL format
            if (!filter_var($careers_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid URL format");
            }

            $stmt = $this->pdo->prepare("
                UPDATE companies
                SET name = ?, careers_url = ?, selector = ?
                WHERE id = ?
            ");

            $result = $stmt->execute(array($name, $careers_url, $selector, $id));

            if (!$result) {
                throw new Exception("Failed to update company");
            }

            return true;

        } catch (PDOException $e) {
            throw new Exception("Database error: " . $e->getMessage());
        }
    }

    public function getStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN last_checked IS NOT NULL THEN 1 ELSE 0 END) as checked
                FROM companies
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Failed to get company statistics: " . $e->getMessage());
        }
    }
}
