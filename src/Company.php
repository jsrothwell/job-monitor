<?php
class Company {
    private $pdo;

    public function __construct(Database $db) {
        $this->pdo = $db->getConnection();
    }

    public function add($name, $careers_url, $selector = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO companies (name, careers_url, selector)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$name, $careers_url, $selector]);
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM companies ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive() {
        $stmt = $this->pdo->query("
            SELECT * FROM companies
            WHERE status = 'active'
            ORDER BY last_checked ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLastChecked($id) {
        $stmt = $this->pdo->prepare("
            UPDATE companies
            SET last_checked = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM companies WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
