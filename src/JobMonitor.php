<?php
class JobMonitor {
    private $db;
    private $company;
    private $scraper;
    private $emailer;

    public function __construct() {
        $this->db = new Database();
        $this->company = new Company($this->db);
        $this->scraper = new JobScraper($this->db, $this->company);
        $this->emailer = new Emailer();
    }

    public function run() {
        echo "Starting job monitoring...\n";

        $companies = $this->company->getActive();

        foreach ($companies as $companyData) {
            echo "Checking {$companyData['name']}...\n";

            $newJobs = $this->scraper->scrapeCompany($companyData);

            if ($newJobs === false) {
                echo "Failed to scrape {$companyData['name']}\n";
                continue;
            }

            if (!empty($newJobs)) {
                echo "Found " . count($newJobs) . " new jobs at {$companyData['name']}\n";
                $this->emailer->sendJobAlert($companyData['name'], $newJobs);
            } else {
                echo "No new jobs at {$companyData['name']}\n";
            }

            sleep(2);
        }

        echo "Monitoring complete.\n";
    }
}
