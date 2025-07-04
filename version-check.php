<?php
// version-check.php - Quick PHP version and compatibility checker
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Version Check - Job Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .ok { color: #28a745; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        h1 { color: #007bff; }
        h2 { color: #495057; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Job Monitor - PHP Version Check</h1>

        <div class="section">
            <h2>Current PHP Version</h2>
            <p><strong>You are running PHP <?php echo PHP_VERSION; ?></strong></p>

            <?php if (version_compare(PHP_VERSION, '7.0.0', '>=')): ?>
                <p class="ok">✅ Your PHP version supports modern syntax (PHP 7.0+)</p>
                <p>You can use the <strong>original fixed files</strong> I provided.</p>
            <?php elseif (version_compare(PHP_VERSION, '5.6.0', '>=')): ?>
                <p class="warning">⚠️  Your PHP version is older (PHP 5.6-6.x)</p>
                <p>You need to use the <strong>PHP 5.6+ Compatible versions</strong> of the files.</p>
            <?php else: ?>
                <p class="error">❌ Your PHP version is too old (PHP <?php echo PHP_VERSION; ?>)</p>
                <p>This application requires <strong>PHP 5.6 or higher</strong>. Please upgrade your PHP version.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>What Files Do You Need?</h2>

            <?php if (version_compare(PHP_VERSION, '7.0.0', '>=')): ?>
                <p class="ok"><strong>Use these files (original fixed versions):</strong></p>
                <ul>
                    <li>✅ Fixed index.php - Main Application Interface</li>
                    <li>✅ Fixed Database.php with Better Error Handling</li>
                    <li>✅ Fixed JobMonitor.php with Compatible SQL</li>
                    <li>✅ Fixed test.php with Better Error Handling</li>
                    <li>✅ Fixed Company.php with Better Error Handling</li>
                    <li>✅ Fixed Emailer.php with Better Error Handling</li>
                </ul>

            <?php elseif (version_compare(PHP_VERSION, '5.6.0', '>=')): ?>
                <p class="warning"><strong>Use these PHP 5.6+ Compatible files:</strong></p>
                <ul>
                    <li>⚠️  PHP 5.6+ Compatible index.php</li>
                    <li>⚠️  PHP 5.6+ Compatible Emailer.php</li>
                    <li>⚠️  PHP 5.6+ Compatible JobMonitor.php</li>
                    <li>⚠️  PHP 5.6+ Compatible Company.php</li>
                    <li>✅ Fixed Database.php (works with all versions)</li>
                    <li>✅ Manual config/config.php Template</li>
                </ul>

            <?php else: ?>
                <p class="error"><strong>Your PHP version is not supported.</strong></p>
                <p>Contact your hosting provider to upgrade to PHP 5.6 or higher.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Required PHP Extensions</h2>
            <?php
            $requiredExtensions = array('pdo', 'pdo_mysql', 'curl', 'dom', 'libxml');
            foreach ($requiredExtensions as $ext):
                $loaded = extension_loaded($ext);
            ?>
                <p class="<?php echo $loaded ? 'ok' : 'error'; ?>">
                    <?php echo $loaded ? '✅' : '❌'; ?> <?php echo $ext; ?>
                    <?php echo $loaded ? ' (loaded)' : ' (missing)'; ?>
                </p>
            <?php endforeach; ?>
        </div>

        <div class="section">
            <h2>Next Steps</h2>

            <?php if (version_compare(PHP_VERSION, '5.6.0', '>=')): ?>
                <ol>
                    <li><strong>Replace your files</strong> with the compatible versions listed above</li>
                    <li><strong>Create config/config.php</strong> using the manual template</li>
                    <li><strong>Visit debug.php</strong> to check for remaining issues</li>
                    <li><strong>Test the application</strong> with index.php</li>
                </ol>

                <h3>Quick File Check</h3>
                <div class="code">
                    Current files in your directory:<br>
                    <?php
                    $files = array('index.php', 'test.php', 'debug.php', 'setup.php', 'config/config.php', 'src/Database.php', 'src/Company.php', 'src/JobScraper.php', 'src/Emailer.php', 'src/JobMonitor.php');
                    foreach ($files as $file):
                        $exists = file_exists(__DIR__ . '/' . $file);
                        echo ($exists ? '✅' : '❌') . ' ' . $file . '<br>';
                    endforeach;
                    ?>
                </div>

            <?php else: ?>
                <p class="error">Contact your hosting provider to upgrade PHP to version 5.6 or higher.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Manual Configuration Template</h2>
            <p>Create <code>config/config.php</code> with this content:</p>
            <div class="code">
&lt;?php<br>
return array(<br>
&nbsp;&nbsp;&nbsp;&nbsp;'database' =&gt; array(<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'host' =&gt; 'localhost',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'name' =&gt; 'your_database_name',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'user' =&gt; 'your_database_user',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'pass' =&gt; 'your_database_password'<br>
&nbsp;&nbsp;&nbsp;&nbsp;),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'email' =&gt; array(<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'host' =&gt; 'smtp.gmail.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'port' =&gt; 587,<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'user' =&gt; 'your_email@gmail.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'pass' =&gt; 'your_email_password',<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'to' =&gt; 'alerts@yourdomain.com'<br>
&nbsp;&nbsp;&nbsp;&nbsp;)<br>
);<br>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;">
            <p><strong>📧 Need Help?</strong></p>
            <p>If you're still having issues after following these steps, the debug.php file will show you exactly what's wrong.</p>
        </div>
    </div>
</body>
</html>
