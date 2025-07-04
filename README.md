# Job Monitor

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg)

A PHP web application that automatically monitors company career pages for new job postings and sends email notifications when new positions are found.

## 🚀 Features

- **🏢 Company Management** - Add and monitor multiple companies through a web interface
- **🔍 Smart Scraping** - Auto-detects job listings or uses custom CSS selectors
- **📧 Email Alerts** - Automatic notifications when new jobs are discovered
- **📊 Job Tracking** - Monitors job status (new/existing/removed) and prevents duplicates
- **⚡ Manual Run** - Instant monitoring with detailed results via web interface
- **🧪 Testing Tools** - Built-in tools to test and debug job scraping
- **📱 Responsive Design** - Modern Bootstrap interface that works on all devices
- **⏰ Automated** - Runs via cron jobs for hands-off monitoring

## 📋 Requirements

- **PHP 7.4+** with extensions:
  - PDO MySQL
  - cURL
  - DOM
  - libxml
- **MySQL 5.7+** or MariaDB 10.2+
- **Web Server** (Apache/Nginx)
- **Email Server** (SMTP access for notifications)

## 📁 Installation

### 1. Download and Upload Files

```bash
# Clone or download the repository
git clone https://github.com/yourusername/job-monitor.git

# Upload to your web server
# Example structure:
/public_html/job-monitor/
├── index.php
├── test.php
├── config/
│   ├── config.example.php
│   └── config.php (create this)
├── src/
│   ├── Database.php
│   ├── Company.php
│   ├── JobScraper.php
│   ├── Emailer.php
│   └── JobMonitor.php
└── scripts/
    └── monitor.php
```

### 2. Create Database

**Via cPanel:**
1. Go to **MySQL Databases**
2. Create database: `job_monitor`
3. Create user with **ALL PRIVILEGES** on the database

**Via Command Line:**
```sql
CREATE DATABASE job_monitor;
CREATE USER 'job_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON job_monitor.* TO 'job_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure Application

```bash
# Copy example config
cp config/config.example.php config/config.php

# Edit with your settings
nano config/config.php
```

## ⚙️ Configuration

### Database Configuration

Edit `config/config.php`:

```php
<?php
return [
    'database' => [
        'host' => 'localhost',              // Database host
        'name' => 'job_monitor',            // Database name
        'user' => 'your_db_username',       // Database username
        'pass' => 'your_db_password'        // Database password
    ],
    'email' => [
        'host' => 'smtp.gmail.com',         // SMTP server
        'port' => 587,                      // SMTP port
        'user' => 'your_email@gmail.com',   // Your email
        'pass' => 'your_app_password',      // Email password/app password
        'to' => 'alerts@yourdomain.com'     // Where to send alerts
    ]
];
```

### Email Setup Examples

**Gmail:**
```php
'email' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'user' => 'your_email@gmail.com',
    'pass' => 'your_app_password',    // Use App Password, not regular password
    'to' => 'alerts@yourdomain.com'
]
```

**Yahoo:**
```php
'email' => [
    'host' => 'smtp.mail.yahoo.com',
    'port' => 587,
    'user' => 'your_email@yahoo.com',
    'pass' => 'your_password',
    'to' => 'alerts@yourdomain.com'
]
```

**Outlook/Hotmail:**
```php
'email' => [
    'host' => 'smtp-mail.outlook.com',
    'port' => 587,
    'user' => 'your_email@outlook.com',
    'pass' => 'your_password',
    'to' => 'alerts@yourdomain.com'
]
```

## 🎯 Usage

### Adding Companies

1. **Access the web interface**: `https://yourdomain.com/job-monitor/`
2. **Fill out the form**:
   - **Company Name**: Any descriptive name
   - **Careers URL**: Direct link to the company's job listings page
   - **CSS Selector** (optional): Custom selector for better accuracy

### CSS Selector Examples

| Selector | Description | Use Case |
|----------|-------------|----------|
| `a[href*="job"]` | Links containing "job" | Generic job links |
| `a[href*="career"]` | Links containing "career" | Career page links |
| `.job-listing a` | Links inside job containers | Structured job boards |
| `[data-job-id]` | Elements with job IDs | Modern job platforms |
| `.posting-title a` | Job title links | Common class name |
| `h3 a` | Links in heading tags | Job titles in headers |

### Testing Job Scraping

**Method 1: Built-in Tester**
1. Visit `https://yourdomain.com/job-monitor/test.php`
2. Enter any careers URL to test scraping
3. View results and adjust CSS selectors as needed

**Method 2: Manual Run**
1. Add companies via the main interface
2. Click the **"Run Now"** button
3. View detailed results for each company

### Setting Up Automation

**HostGator/cPanel:**
1. Go to **cPanel → Cron Jobs**
2. Set up with these settings:
   ```
   Minute: */30
   Hour: *
   Day: *
   Month: *
   Weekday: *
   Command: /usr/local/bin/php /home/yourusername/public_html/job-monitor/scripts/monitor.php
   ```

**Common Schedules:**
```bash
# Every 30 minutes
*/30 * * * * php /path/to/scripts/monitor.php

# Every hour
0 * * * * php /path/to/scripts/monitor.php

# Twice daily (9 AM and 6 PM)
0 9,18 * * * php /path/to/scripts/monitor.php

# Business hours only (9 AM - 5 PM, weekdays)
0 9-17 * * 1-5 php /path/to/scripts/monitor.php
```

**Linux/macOS:**
```bash
# Edit crontab
crontab -e

# Add line for every 30 minutes
*/30 * * * * php /path/to/job-monitor/scripts/monitor.php
```

## 🧪 Testing & Debugging

### Debug Tool
If experiencing issues, use the debug tool:
```
https://yourdomain.com/job-monitor/step-debug.php
```

### Manual Testing
```bash
# Test monitor script directly
php scripts/monitor.php

# Test with debugging
php -d display_errors=1 scripts/monitor.php
```

### Common Test URLs
Use these to test your setup:

| Company | URL | Suggested Selector |
|---------|-----|-------------------|
| Netflix | `https://jobs.netflix.com/` | `a[href*="job"]` |
| Shopify | `https://www.shopify.com/careers` | `.job-listing a` |
| GitHub | `https://github.com/about/careers` | `[data-analytics-event*="job"]` |
| Spotify | `https://www.lifeatspotify.com/jobs` | `.posting-title` |

## 🔧 Troubleshooting

### Common Issues

**500 Internal Server Error**
- Check file permissions (644 for files, 755 for directories)
- Verify all required files are uploaded
- Check config/config.php exists and is valid
- Review error logs in cPanel

**Database Connection Failed**
- Verify database credentials in config.php
- Ensure database exists
- Check user permissions
- Test database connection manually

**No Jobs Found**
- Test with different CSS selectors
- Check if website blocks scrapers
- Verify the careers URL is correct
- Some sites require JavaScript (not supported)

**Email Not Sending**
- Verify SMTP settings
- Use app passwords for Gmail
- Check spam folders
- Test email settings manually

**Cron Job Not Running**
- Verify cron syntax
- Check PHP path: `which php`
- Ensure script has execute permissions
- Check cron logs: `/var/log/cron`

### File Permissions
```bash
# Set correct permissions
chmod 644 *.php
chmod 644 config/*.php
chmod 644 src/*.php
chmod 755 scripts/
chmod 644 scripts/*.php
```

### Memory and Timeout Issues
Add to your PHP configuration:
```php
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
```

## 📊 Database Schema

The application creates these tables automatically:

**companies**
```sql
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    careers_url VARCHAR(500) NOT NULL,
    selector VARCHAR(500) DEFAULT NULL,
    last_checked DATETIME DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**jobs**
```sql
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    title VARCHAR(500) NOT NULL,
    url VARCHAR(500) DEFAULT NULL,
    content_hash VARCHAR(64),
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'existing', 'removed') DEFAULT 'new',
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job (company_id, content_hash)
);
```

## 🎨 Customization

### Changing Email Templates
Edit `src/Emailer.php`:
```php
public function sendJobAlert($companyName, $newJobs) {
    $subject = "🎯 New Jobs at $companyName";
    $body = "Great news! New positions found:\n\n";
    // Customize message format here
}
```

### Adding New Scrapers
Extend `src/JobScraper.php` for site-specific logic:
```php
private function customScraper($url) {
    // Add custom scraping logic for specific sites
}
```

### Custom CSS Selectors by Domain
```php
private function getDefaultSelector($url) {
    $domain = parse_url($url, PHP_URL_HOST);

    $selectors = [
        'lever.co' => '[data-qa="posting-name"]',
        'greenhouse.io' => '.posting-title',
        'workday.com' => '[data-automation-id="jobTitle"]'
    ];

    return $selectors[$domain] ?? 'a';
}
```

## 🔒 Security Considerations

- **Keep config.php secure** - never commit database credentials
- **Use HTTPS** for the web interface
- **Validate input** - especially URLs and selectors
- **Rate limiting** - respect target websites' resources
- **User agents** - use appropriate User-Agent headers
- **robots.txt** - respect website scraping policies

## 📈 Performance Tips

- **Optimize selectors** - specific selectors are faster
- **Use delays** - don't overwhelm target servers
- **Monitor resources** - watch for memory/CPU usage
- **Log monitoring** - track success/failure rates
- **Database maintenance** - clean old job records periodically

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -am 'Add feature'`
4. Push to branch: `git push origin feature-name`
5. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details.

## 🙋 Support

- **Issues**: Create an issue on GitHub
- **Documentation**: Check this README
- **Email**: your-email@domain.com

## 🔄 Updates

### Version 1.1
- Added Bootstrap interface
- Manual run feature
- Enhanced testing tools
- Improved error handling

### Version 1.0
- Initial release
- Basic job monitoring
- Email notifications
- Cron job automation

---

**⚠️ Legal Notice:** Always respect website terms of service and robots.txt files. This tool is for educational and legitimate job searching purposes only.
