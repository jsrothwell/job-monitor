<?php
class Emailer {
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function sendJobAlert($companyName, $newJobs) {
        if (empty($newJobs)) return;

        $subject = "New Jobs at $companyName - " . count($newJobs) . " positions";

        $body = "New job postings found at $companyName:\n\n";
        foreach ($newJobs as $job) {
            $body .= "• {$job['title']}\n";
            if (!empty($job['url'])) {
                $body .= "  Link: {$job['url']}\n";
            }
            $body .= "\n";
        }

        $body .= "\n--\nJob Monitor Alert System";

        $this->sendEmail($this->config['email']['to'], $subject, $body);
    }

    private function sendEmail($to, $subject, $body) {
        $headers = [
            'From: ' . $this->config['email']['user'],
            'Reply-To: ' . $this->config['email']['user'],
            'Content-Type: text/plain; charset=UTF-8'
        ];

        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
            echo "Email sent: $subject\n";
        } else {
            echo "Failed to send email: $subject\n";
        }
    }
}
