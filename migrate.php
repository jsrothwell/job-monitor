<?php
// migrate.php - Migration script to upgrade existing job monitor to feed aggregator

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Database.php';

echo "<html><head><title>Job Feed Migration</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
    .success { border-left-color: #28a745; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto; }
    .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
    .btn:hover { background: #0056b3; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🚀 Job Feed Aggregator Migration</h1>";
echo "<p>This script will upgrade your existing Job Monitor to the new Job Feed Aggregator with enhanced features.</p>";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    echo "<div class='step'><h3>Step 1: Checking Current Database Structure</h3>";

    // Check if we have the old structure
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $hasOldStructure = in_array('companies', $tables) && in_array('jobs', $tables);
    $hasNewStructure = false;

    if ($hasOldStructure) {
        // Check if new columns exist
        $stmt = $pdo->query("DESCRIBE companies");
        $companyColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $companyColumns[] = $row['Field'];
        }

        $hasNewStructure = in_array('industry', $companyColumns) && in_array('logo_url', $companyColumns);
    }

    if (!$hasOldStructure) {
        echo "<p>✨ Fresh installation detected. Creating new database structure...</p>";
        $db->createTables();
        echo "<p class='success'>✅ New database structure created successfully!</p>";
    } else if ($hasNewStructure) {
        echo "<p class='warning'>⚠️ Enhanced structure already exists. Verifying integrity...</p>";
        $db->createTables(); // This will create missing tables/columns if any
        echo "<p class='success'>✅ Database structure is up to date!</p>";
    } else {
        echo "<p>🔄 Existing structure found. Upgrading to enhanced version...</p>";
        performMigration($pdo);
        echo "<p class='success'>✅ Database successfully migrated to new structure!</p>";
    }
    echo "</div>";

    echo "<div class='step'><h3>Step 2: Data Migration and Cleanup</h3>";

    // Migrate existing data if needed
    if ($hasOldStructure && !$hasNewStructure) {
        migrateExistingData($pdo);
    }

    // Add sample data if database is empty
    addSampleDataIfEmpty($pdo);

    echo "<p class='success'>✅ Data migration completed!</p>";
    echo "</div>";

    echo "<div class='step'><h3>Step 3: File Structure Check</h3>";

    $requiredFiles = [
        'src/Database.php' => 'Enhanced Database class',
        'src/Company.php' => 'Enhanced Company class',
        'src/JobScraper.php' => 'Enhanced JobScraper class',
        'src/JobMonitor.php' => 'Enhanced JobMonitor class',
        'src/Emailer.php' => 'Emailer class',
        'api/jobs.php' => 'Jobs API endpoint',
        'api/stats.php' => 'Statistics API endpoint',
        'api/companies.php' => 'Companies API endpoint',
        'config/config.php' => 'Configuration file'
    ];

    $missingFiles = [];
    foreach ($requiredFiles as $file => $description) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "<p>✅ {$description}: <code>{$file}</code></p>";
        } else {
            echo "<p class='error'>❌ Missing: {$description} (<code>{$file}</code>)</p>";
            $missingFiles[] = $file;
        }
    }

    if (empty($missingFiles)) {
        echo "<p class='success'>✅ All required files are present!</p>";
    } else {
        echo "<div class='error'>";
        echo "<p><strong>Missing Files Detected:</strong></p>";
        echo "<p>Please ensure you have uploaded all the enhanced files from the new Job Feed Aggregator.</p>";
        echo "</div>";
    }
    echo "</div>";

    echo "<div class='step'><h3>Step 4: Configuration Check</h3>";

    if (file_exists(__DIR__ . '/config/config.php')) {
        $config = require __DIR__ . '/config/config.php';

        // Check required config sections
        $requiredSections = ['database', 'email'];
        $configOk = true;

        foreach ($requiredSections as $section) {
            if (!isset($config[$section])) {
                echo "<p class='error'>❌ Missing configuration section: {$section}</p>";
                $configOk = false;
            } else {
                echo "<p>✅ Configuration section: {$section}</p>";
            }
        }

        if ($configOk) {
            echo "<p class='success'>✅ Configuration file is properly structured!</p>";
        }
    } else {
        echo "<p class='error'>❌ Configuration file not found. Please copy config.example.php to config.php and configure it.</p>";
    }
    echo "</div>";

    echo "<div class='step'><h3>Step 5: API Directory Setup</h3>";

    if (!is_dir(__DIR__ . '/api')) {
        if (mkdir(__DIR__ . '/api', 0755, true)) {
            echo "<p>✅ Created API directory</p>";
        } else {
            echo "<p class='error'>❌ Failed to create API directory. Please create it manually.</p>";
        }
    } else {
        echo "<p>✅ API directory exists</p>";
    }

    echo "</div>";

    echo "<div class='step success'><h3>🎉 Migration Complete!</h3>";
    echo "<p><strong>Your Job Monitor has been successfully upgraded to Job Feed Aggregator!</strong></p>";
    echo "<h4>New Features Available:</h4>";
    echo "<ul>";
    echo "<li>📍 <strong>Location Filtering</strong> - Filter jobs by city, state, or remote work</li>";
    echo "<li>💼 <strong>Job Type Detection</strong> - Automatic categorization of full-time, part-time, contract positions</li>";
    echo "<li>🏠 <strong>Remote Work Detection</strong> - Automatically identifies remote-friendly positions</li>";
    echo "<li>📊 <strong>Enhanced Analytics</strong> - Department breakdown, salary insights, and trends</li>";
    echo "<li>🎯 <strong>Smart Job Alerts</strong> - Targeted notifications based on keywords and preferences</li>";
    echo "<li>🏢 <strong>Company Insights</strong> - Industry categorization and company performance metrics</li>";
    echo "<li>🔍 <strong>Advanced Search</strong> - Full-text search with multiple filter options</li>";
    echo "<li>📱 <strong>Modern Interface</strong> - Responsive design with improved user experience</li>";
    echo "</ul>";

    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Visit your <a href='index.php' class='btn'>Job Feed Dashboard</a> to see the new interface</li>";
    echo "<li>Use <a href='manage.php' class='btn'>Manage Feeds</a> to add and configure company career pages</li>";
    echo "<li>Test the new features with <a href='test.php' class='btn'>Test Tool</a></li>";
    echo "<li>Set up your cron job to use the enhanced monitoring system</li>";
    echo "</ol>";

    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h4>📋 Cron Job Update:</h4>";
    echo "<p>Update your cron job command to:</p>";
    echo "<pre>*/30 * * * * php " . __DIR__ . "/scripts/monitor.php</pre>";
    echo "<p>This will run the enhanced job monitoring every 30 minutes.</p>";
    echo "</div>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step error'>";
    echo "<h3>❌ Migration Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and permissions, then try again.</p>";
    echo "</div>";
}

echo "</div></body></html>";

function performMigration($pdo) {
    echo "<p>🔄 Adding new columns to companies table...</p>";

    // Add new columns to companies table
    $companyColumns = [
        "ADD COLUMN location_selector VARCHAR(500) DEFAULT NULL",
        "ADD COLUMN description_selector VARCHAR(500) DEFAULT NULL",
        "ADD COLUMN logo_url VARCHAR(500) DEFAULT NULL",
        "ADD COLUMN website_url VARCHAR(500) DEFAULT NULL",
        "ADD COLUMN industry VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];

    foreach ($companyColumns as $column) {
        try {
            $pdo->exec("ALTER TABLE companies $column");
            echo "<p>✅ Added column: " . substr($column, strpos($column, 'ADD COLUMN') + 11) . "</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "<p class='warning'>⚠️ Column addition failed: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<p>🔄 Enhancing jobs table...</p>";

    // Add new columns to jobs table
    $jobColumns = [
        "ADD COLUMN description TEXT DEFAULT NULL",
        "ADD COLUMN location VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN job_type ENUM('full-time', 'part-time', 'contract', 'internship', 'remote', 'unknown') DEFAULT 'unknown'",
        "ADD COLUMN department VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN experience_level ENUM('entry', 'mid', 'senior', 'executive', 'unknown') DEFAULT 'unknown'",
        "ADD COLUMN salary_range VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN is_remote BOOLEAN DEFAULT FALSE",
        "ADD COLUMN is_featured BOOLEAN DEFAULT FALSE"
    ];

    foreach ($jobColumns as $column) {
        try {
            $pdo->exec("ALTER TABLE jobs $column");
            echo "<p>✅ Added column: " . substr($column, strpos($column, 'ADD COLUMN') + 11) . "</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "<p class='warning'>⚠️ Column addition failed: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Add indexes for better performance
    echo "<p>🔄 Adding performance indexes...</p>";

    $indexes = [
        "ALTER TABLE jobs ADD INDEX idx_location (location)",
        "ALTER TABLE jobs ADD INDEX idx_job_type (job_type)",
        "ALTER TABLE jobs ADD INDEX idx_is_remote (is_remote)",
        "ALTER TABLE jobs ADD INDEX idx_department (department)",
        "ALTER TABLE jobs ADD INDEX idx_experience_level (experience_level)",
        "ALTER TABLE companies ADD INDEX idx_industry (industry)"
    ];

    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
            echo "<p>✅ Added index</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "<p class='warning'>⚠️ Index creation note: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Create new tables
    echo "<p>🔄 Creating new enhancement tables...</p>";

    $newTables = [
        "job_tags" => "
            CREATE TABLE IF NOT EXISTS job_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                tag VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_job_tag (job_id, tag),
                INDEX idx_tag (tag)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        "tags" => "
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                category ENUM('skill', 'technology', 'department', 'benefit', 'location') DEFAULT 'skill',
                usage_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_usage_count (usage_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        "saved_jobs" => "
            CREATE TABLE IF NOT EXISTS saved_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                user_identifier VARCHAR(100) NOT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT DEFAULT NULL,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_job (user_identifier, job_id),
                INDEX idx_user (user_identifier),
                INDEX idx_saved_at (saved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        "job_alerts" => "
            CREATE TABLE IF NOT EXISTS job_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                keywords TEXT DEFAULT NULL,
                location_filter VARCHAR(255) DEFAULT NULL,
                remote_only BOOLEAN DEFAULT FALSE,
                company_ids TEXT DEFAULT NULL,
                min_salary INT DEFAULT NULL,
                max_salary INT DEFAULT NULL,
                job_types TEXT DEFAULT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_sent TIMESTAMP DEFAULT NULL,
                INDEX idx_email (email),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    foreach ($newTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Created table: {$tableName}</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>⚠️ Table creation note for {$tableName}: " . $e->getMessage() . "</p>";
        }
    }
}

function migrateExistingData($pdo) {
    echo "<p>🔄 Migrating existing job data...</p>";

    // Update existing jobs with smart defaults based on title analysis
    $stmt = $pdo->query("SELECT id, title FROM jobs WHERE job_type = 'unknown' OR job_type IS NULL");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    foreach ($jobs as $job) {
        $title = strtolower($job['title']);

        // Detect job type
        $jobType = 'unknown';
        if (strpos($title, 'intern') !== false) {
            $jobType = 'internship';
        } elseif (strpos($title, 'contract') !== false || strpos($title, 'freelance') !== false) {
            $jobType = 'contract';
        } elseif (strpos($title, 'part-time') !== false || strpos($title, 'part time') !== false) {
            $jobType = 'part-time';
        } else {
            $jobType = 'full-time';
        }

        // Detect experience level
        $experience = 'unknown';
        if (strpos($title, 'senior') !== false || strpos($title, 'lead') !== false || strpos($title, 'principal') !== false) {
            $experience = 'senior';
        } elseif (strpos($title, 'junior') !== false || strpos($title, 'entry') !== false || strpos($title, 'associate') !== false) {
            $experience = 'entry';
        } elseif (strpos($title, 'director') !== false || strpos($title, 'manager') !== false || strpos($title, 'head') !== false) {
            $experience = 'executive';
        } else {
            $experience = 'mid';
        }

        // Detect remote
        $isRemote = strpos($title, 'remote') !== false;

        // Detect department
        $department = null;
        if (strpos($title, 'engineer') !== false || strpos($title, 'developer') !== false || strpos($title, 'software') !== false) {
            $department = 'engineering';
        } elseif (strpos($title, 'design') !== false || strpos($title, 'ux') !== false || strpos($title, 'ui') !== false) {
            $department = 'design';
        } elseif (strpos($title, 'product') !== false) {
            $department = 'product';
        } elseif (strpos($title, 'marketing') !== false) {
            $department = 'marketing';
        } elseif (strpos($title, 'sales') !== false) {
            $department = 'sales';
        } elseif (strpos($title, 'data') !== false || strpos($title, 'analyst') !== false) {
            $department = 'data';
        }

        // Update job
        $updateStmt = $pdo->prepare("
            UPDATE jobs
            SET job_type = ?, experience_level = ?, is_remote = ?, department = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$jobType, $experience, $isRemote, $department, $job['id']]);
        $updated++;
    }

    echo "<p>✅ Updated {$updated} existing jobs with enhanced data</p>";
}

function addSampleDataIfEmpty($pdo) {
    // Check if we have any companies
    $stmt = $pdo->query("SELECT COUNT(*) FROM companies");
    $companyCount = $stmt->fetchColumn();

    if ($companyCount == 0) {
        echo "<p>🔄 Adding sample companies for testing...</p>";

        $sampleCompanies = [
            [
                'name' => 'GitHub',
                'careers_url' => 'https://github.com/about/careers',
                'selector' => 'a[href*="job"]',
                'website_url' => 'https://github.com',
                'industry' => 'Technology',
                'logo_url' => 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png'
            ],
            [
                'name' => 'Netflix',
                'careers_url' => 'https://jobs.netflix.com/',
                'selector' => 'a[href*="job"]',
                'website_url' => 'https://netflix.com',
                'industry' => 'Entertainment',
                'logo_url' => 'https://logos-world.net/wp-content/uploads/2020/04/Netflix-Logo.png'
            ],
            [
                'name' => 'Shopify',
                'careers_url' => 'https://www.shopify.com/careers',
                'selector' => '.job-listing a',
                'website_url' => 'https://shopify.com',
                'industry' => 'E-commerce',
                'logo_url' => 'https://cdn.shopify.com/assets2/brand-assets/shopify-logo-main-8ee1e0052baf87fd9698ceff7cbc01cc36a89170212f2900165012b5940bd44f.svg'
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO companies (name, careers_url, selector, website_url, industry, logo_url, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");

        foreach ($sampleCompanies as $company) {
            $stmt->execute([
                $company['name'],
                $company['careers_url'],
                $company['selector'],
                $company['website_url'],
                $company['industry'],
                $company['logo_url']
            ]);
        }

        echo "<p>✅ Added " . count($sampleCompanies) . " sample companies</p>";
    }
}
?>
