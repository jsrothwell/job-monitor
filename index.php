<?php
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Company.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $company = new Company($db);

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $company->add($_POST['name'], $_POST['careers_url'], $_POST['selector']);
                header('Location: index.php');
                exit;

            case 'delete':
                $company->delete($_POST['id']);
                header('Location: index.php');
                exit;
        }
    }
}

$db = new Database();
$db->createTables();
$company = new Company($db);
$companies = $company->getAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Job Monitor</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>🔍 Job Monitor Dashboard</h1>

        <div class="card">
            <h2>Add Company</h2>
            <form method="post">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Company Name:</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label>Careers Page URL:</label>
                    <input type="url" name="careers_url" required placeholder="https://company.com/careers">
                </div>

                <div class="form-group">
                    <label>CSS Selector (optional):</label>
                    <input type="text" name="selector" placeholder="a[href*='job'], .job-listing a, etc.">
                    <small>Leave empty to auto-detect job links</small>
                </div>

                <button type="submit" class="btn btn-primary">Add Company</button>
            </form>
        </div>

        <div class="card">
            <h2>Monitored Companies</h2>
            <?php if (empty($companies)): ?>
                <p>No companies added yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Careers URL</th>
                                <th>Selector</th>
                                <th>Last Checked</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $comp): ?>
                            <tr>
                                <td><?= htmlspecialchars($comp['name']) ?></td>
                                <td><a href="<?= htmlspecialchars($comp['careers_url']) ?>" target="_blank" class="link">View</a></td>
                                <td><code><?= htmlspecialchars($comp['selector'] ?: 'auto-detect') ?></code></td>
                                <td><?= $comp['last_checked'] ? date('M j, Y g:i A', strtotime($comp['last_checked'])) : 'Never' ?></td>
                                <td>
                                    <span class="status status-<?= $comp['status'] ?>">
                                        <?= ucfirst($comp['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $comp['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this company?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>📋 Setup Instructions</h2>
            <ol>
                <li>Copy <code>config/config.example.php</code> to <code>config/config.php</code></li>
                <li>Update database and email credentials in the config file</li>
                <li>Add companies using the form above</li>
                <li>Set up a cron job: <code>*/30 * * * * php /path/to/scripts/monitor.php</code></li>
            </ol>

            <p><strong>Test scraping:</strong> <code>php scripts/quick-test.php https://company.com/careers</code></p>
        </div>
    </div>
</body>
</html>

# assets/style.css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f8f9fa;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

h1 {
    color: #2c3e50;
    margin-bottom: 30px;
    text-align: center;
}

h2 {
    color: #34495e;
    margin-bottom: 20px;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
}

input[type="text"],
input[type="url"],
input[type="email"] {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

input:focus {
    outline: none;
    border-color: #3498db;
}

small {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 12px;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #555;
}

tr:hover {
    background-color: #f8f9fa;
}

.link {
    color: #3498db;
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}

.status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

code {
    background-color: #f1f2f6;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

ol {
    padding-left: 20px;
}

ol li {
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .container {
        padding: 10px;
    }

    .card {
        padding: 15px;
    }

    table {
        font-size: 14px;
    }

    th, td {
        padding: 8px;
    }
}
