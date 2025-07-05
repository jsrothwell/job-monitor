<?php
class Emailer {
    private $config;

    public function __construct() {
        $configPath = __DIR__ . '/../config/config.php';
        if (!file_exists($configPath)) {
            throw new Exception("Configuration file not found: " . $configPath);
        }

        $this->config = require $configPath;

        if (!isset($this->config['email'])) {
            throw new Exception("Email configuration section missing");
        }
    }

    public function sendJobAlert($companyName, $newJobs) {
        if (empty($newJobs)) {
            return true;
        }

        try {
            $jobCount = count($newJobs);
            $subject = "New Jobs at " . $companyName . " - " . $jobCount . " position" . ($jobCount > 1 ? 's' : '');

            $body = "New job postings found at " . $companyName . ":\n\n";

            $index = 1;
            foreach ($newJobs as $job) {
                $body .= $index . ". " . $job['title'] . "\n";
                if (!empty($job['url'])) {
                    $body .= "   Link: " . $job['url'] . "\n";
                }
                $body .= "\n";
                $index++;
            }

            $body .= "\n--\n";
            $body .= "Job Monitor Alert System\n";
            $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

            return $this->sendEmail($this->config['email']['to'], $subject, $body);

        } catch (Exception $e) {
            error_log("Email alert error: " . $e->getMessage());
            return false;
        }
    }

    private function sendEmail($to, $subject, $body) {
        try {
            // Validate email addresses
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid recipient email");
            }

            if (!filter_var($this->config['email']['user'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid sender email");
            }

            // Prepare headers
            $headers = array(
                'From: ' . $this->config['email']['user'],
                'Reply-To: ' . $this->config['email']['user'],
                'Content-Type: text/plain; charset=UTF-8'
            );

            // Send email
            $success = mail($to, $subject, $body, implode("\r\n", $headers));

            if ($success) {
                echo "Email sent: " . $subject . "\n";
                return true;
            } else {
                echo "Failed to send email: " . $subject . "\n";
                return false;
            }

        } catch (Exception $e) {
            echo "Email error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function testEmail() {
        try {
            $testSubject = "Job Monitor Test - " . date('Y-m-d H:i:s');
            $testBody = "This is a test email from Job Monitor.\n\n";
            $testBody .= "Configuration:\n";
            $testBody .= "From: " . $this->config['email']['user'] . "\n";
            $testBody .= "To: " . $this->config['email']['to'] . "\n";
            $testBody .= "Time: " . date('Y-m-d H:i:s');

            return $this->sendEmail($this->config['email']['to'], $testSubject, $testBody);

        } catch (Exception $e) {
            echo "Test email failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
