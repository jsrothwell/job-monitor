<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Company.php';
require_once __DIR__ . '/../src/JobScraper.php';
require_once __DIR__ . '/../src/Emailer.php';
require_once __DIR__ . '/../src/JobMonitor.php';

if (php_sapi_name() === 'cli') {
    $monitor = new JobMonitor();
    $monitor->run();
} else {
    echo "This script should be run from command line.\n";
}
