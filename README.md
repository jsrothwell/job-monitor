# Job Feed Aggregator

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg)

**Transform your job search with intelligent feed aggregation!**

A powerful PHP-based system that automatically monitors multiple company career pages, extracts job listings with rich metadata, and provides advanced filtering capabilities. Perfect for job seekers, recruiters, and market researchers.

## 🚀 New Features (v2.0)

### 🎯 **Smart Job Detection**
- **Location Intelligence** - Automatically extracts and normalizes job locations
- **Remote Work Detection** - Identifies remote-friendly positions using AI patterns
- **Job Type Classification** - Categorizes positions (full-time, part-time, contract, internship)
- **Experience Level Detection** - Determines seniority (entry, mid, senior, executive)
- **Department Recognition** - Sorts jobs by department (engineering, design, marketing, etc.)
- **Salary Extraction** - Captures salary ranges when available

### 🔍 **Advanced Filtering & Search**
- **Multi-dimensional Filtering** - Location, remote status, job type, experience level, department
- **Full-text Search** - Search across job titles and descriptions
- **Company-specific Filtering** - Focus on specific companies or industries
- **Real-time Results** - Instant filtering without page reloads
- **Smart Sorting** - Sort by date, company, title, or relevance

### 📧 **Intelligent Alert System**
- **Targeted Job Alerts** - Custom alerts based on keywords, location, and preferences
- **Smart Notifications** - Only get alerted about jobs that match your criteria
- **Weekly Digests** - Summary emails with market trends and top opportunities
- **Multiple Alert Types** - Company-specific, keyword-based, or location-based alerts

### 📊 **Enhanced Analytics**
- **Job Market Insights** - Track trends, popular locations, and in-demand skills
- **Company Performance** - Monitor which companies are hiring most actively
- **Department Breakdown** - See hiring patterns across different teams
- **Growth Tracking** - Historical data on job posting volumes

### 🏢 **Company Management**
- **Industry Categorization** - Organize companies by sector
- **Enhanced Scraping** - Support for location and description selectors
- **Logo Integration** - Automatic logo detection and display
- **Bulk Operations** - Manage multiple feeds efficiently

## 📋 Quick Start

### Option 1: Fresh Installation
1. **Download** the Job Feed Aggregator files
2. **Upload** to your web server
3. **Visit** `your-domain.com/job-feed/setup.php`
4. **Follow** the guided setup process

### Option 2: Upgrade from Job Monitor
1. **Backup** your existing database
2. **Upload** the new files (keeping your config)
3. **Visit** `your-domain.com/job-feed/migrate.php`
4. **Follow** the migration wizard

## 🛠️ Manual Installation

### Requirements
- **PHP 7.4+** with extensions: PDO MySQL, cURL, DOM, libxml
- **MySQL 5.7+** or MariaDB 10.2+
- **Web Server** (Apache/Nginx)
- **Email Server** (SMTP access for notifications)

### Step 1: Database Setup
```sql
CREATE DATABASE job_feed_aggregator;
CREATE USER 'job_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON job_feed_aggregator.* TO 'job_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 2: Configuration
```bash
# Copy configuration template
cp config/config.example.php config/config.php

# Edit with your settings
nano config/config.php
```

### Step 3: File Structure
```
job-feed-aggregator/
├── index.php              # Main dashboard
├── manage.php              # Company management
├── test.php               # Testing tools
├── setup.php              # Installation wizard
├── migrate.php            # Upgrade wizard
├── config/
│   ├── config.example.php
│   └── config.php         # Your configuration
├── src/
│   ├── Database.php       # Enhanced database class
│   ├── Company.php        # Company management
│   ├── JobScraper.php     # Intelligent scraping engine
│   ├── JobMonitor.php     # Monitoring system
│   └── Emailer.php        # Alert system
├── api/
│   ├── jobs.php           # Jobs API endpoint
│   ├── companies.php      # Companies API
│   ├── stats.php          # Statistics API
│   └── *.php              # Other API endpoints
└── scripts/
    └── monitor.php        # Cron job script
```

## ⚙️ Configuration

### Database Configuration
```php
'database' => [
    'host' => 'localhost',
    'name' => 'job_feed_aggregator',
    'user' => 'job_user',
    'pass' => 'secure_password'
]
```

### Email Configuration
```php
'email' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'user' => 'your_email@gmail.com',
    'pass' => 'your_app_password',
    'to' => 'alerts@yourdomain.com',
    'alerts_enabled' => true,
    'admin_notifications' => true,
    'admin_email' => 'admin@yourdomain.com'
]
```

## 🎯 Usage Guide

### Adding Company Feeds
1. **Navigate** to Manage Feeds
2. **Click** "Add New Feed"
3. **Enter** company details:
   - Company Name & Industry
   - Careers page URL
   - CSS selectors (optional)
4. **Test** the configuration
5. **Save** and activate

### Advanced Selectors
| Selector Type | Example | Purpose |
|---------------|---------|---------|
| Job Links | `a[href*="job"]` | Find job posting links |
| Location | `.location, [data-location]` | Extract job locations |
| Description | `.description, .job-summary` | Get job descriptions |

### Setting Up Automation
```bash
# Add to crontab for every 30 minutes
*/30 * * * * php /path/to/job-feed/scripts/monitor.php

# For hourly updates
0 * * * * php /path/to/job-feed/scripts/monitor.php

# Business hours only (9 AM - 5 PM, weekdays)
0 9-17 * * 1-5 php /path/to/job-feed/scripts/monitor.php
```

### Creating Job Alerts
1. **Access** the job feed dashboard
2. **Use filters** to define your criteria
3. **Click** "Create Alert"
4. **Enter** your email and preferences
5. **Save** to start receiving notifications

## 🔧 API Reference

### Jobs Endpoint
```bash
GET /api/jobs.php?search=developer&location=remote&job_type=full-time
```

**Parameters:**
- `search` - Search term for title/description
- `location` - Location filter
- `remote_only` - Boolean for remote jobs only
- `job_type` - Filter by job type
- `experience` - Filter by experience level
- `department` - Filter by department
- `company_ids` - Comma-separated company IDs
- `limit` - Results per page (max 100)
- `offset` - Pagination offset

### Statistics Endpoint
```bash
GET /api/stats.php
```

**Response:**
```json
{
  "success": true,
  "stats": {
    "total_jobs": 1250,
    "remote_jobs": 340,
    "new_jobs": 89,
    "active_companies": 25,
    "departments": [...],
    "locations": [...]
  }
}
```

### Companies Endpoint
```bash
GET /api/companies.php
```

## 🔍 Advanced Features

### Custom CSS Selectors
The system supports advanced CSS selectors for better data extraction:

```javascript
// Common patterns for job boards
{
  "lever.co": {
    "jobs": "[data-qa='posting-name']",
    "location": "[data-qa='posting-location']"
  },
  "greenhouse.io": {
    "jobs": ".posting-title",
    "location": ".location"
  },
  "workday.com": {
    "jobs": "[data-automation-id='jobTitle']",
    "location": "[data-automation-id='locations']"
  }
}
```

### Job Classification
The system automatically detects:

- **Remote Work**: Keywords like "remote", "work from home", "distributed"
- **Job Types**: Full-time, part-time, contract, internship patterns
- **Experience Levels**: Junior, senior, lead, director indicators
- **Departments**: Engineering, design, marketing, sales patterns
- **Salary Information**: Various salary format patterns

### Data Export
Export job data in multiple formats:
- **CSV Export** - All job data with filters applied
- **JSON API** - Programmatic access to job data
- **RSS Feeds** - Subscribe to job updates

## 📊 Database Schema

### Enhanced Tables
- **companies** - Company information with industry categorization
- **jobs** - Job listings with rich metadata
- **job_tags** - Flexible job categorization system
- **saved_jobs** - User-saved job listings
- **job_alerts** - Email alert configurations
- **tags** - Common skills and categories
- **search_analytics** - Usage tracking and insights

### Key Indexes
- Full-text search on job titles and descriptions
- Geographic indexes for location-based queries
- Performance indexes for filtering and sorting

## 🔐 Security Considerations

- **Input Validation** - All user inputs are sanitized
- **SQL Injection Prevention** - Prepared statements throughout
- **Rate Limiting** - Respectful scraping with delays
- **User Agent Rotation** - Avoid detection as bot
- **Error Handling** - Graceful failure management
- **Data Privacy** - Secure handling of email addresses

## 📈 Performance Optimization

### Caching Strategy
- **Database Views** - Pre-computed common queries
- **Index Optimization** - Strategic database indexes
- **Query Optimization** - Efficient SQL patterns
- **Pagination** - Large result set handling

### Monitoring
- **Success Rates** - Track scraping success by company
- **Response Times** - Monitor API performance
- **Error Tracking** - Log and alert on failures
- **Usage Analytics** - Track popular searches and filters

## 🧪 Testing & Debugging

### Built-in Test Tools
- **URL Tester** - Test any careers page
- **Selector Validator** - Verify CSS selectors
- **Company Tester** - Test individual company configurations
- **Bulk Tester** - Test all active companies

### Debug Mode
Enable detailed logging:
```php
// In config.php
'debug' => [
    'enabled' => true,
    'log_file' => 'debug.log',
    'email_errors' => true
]
```

## 🆙 Migration from v1.0

If you're upgrading from the original Job Monitor:

1. **Backup** your existing database
2. **Run** the migration script: `migrate.php`
3. **Review** upgraded company configurations
4. **Test** enhanced scraping capabilities
5. **Configure** new alert preferences

The migration preserves:
- ✅ All existing companies and job data
- ✅ Company status and last checked timestamps
- ✅ Historical job discovery data

New features added:
- 🆕 Location and remote work detection
- 🆕 Job type and experience level classification
- 🆕 Department categorization
- 🆕 Enhanced search and filtering
- 🆕 Smart alert system

## 🤝 Contributing

We welcome contributions! Here's how to help:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature-name`
3. **Make** your changes with tests
4. **Commit** with clear messages: `git commit -am 'Add feature'`
5. **Push** to your branch: `git push origin feature-name`
6. **Submit** a pull request

### Development Setup
```bash
# Clone the repository
git clone https://github.com/yourusername/job-feed-aggregator.git

# Set up development environment
cp config/config.example.php config/config.dev.php

# Run tests
php tests/run_tests.php
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙋 Support & Community

- **📧 Email Support**: support@jobfeedaggregator.com
- **📖 Documentation**: [docs.jobfeedaggregator.com](https://docs.jobfeedaggregator.com)
- **🐛 Bug Reports**: [GitHub Issues](https://github.com/yourusername/job-feed-aggregator/issues)
- **💬 Community Forum**: [community.jobfeedaggregator.com](https://community.jobfeedaggregator.com)

## 🔮 Roadmap

### Upcoming Features
- **🤖 AI-Powered Matching** - Machine learning job recommendations
- **📱 Mobile App** - Native iOS and Android applications
- **🔗 ATS Integration** - Connect with popular applicant tracking systems
- **📊 Advanced Analytics** - Salary trends and market insights
- **🌐 Multi-language Support** - International job market support
- **🔔 Slack/Discord Integration** - Team notifications and alerts

### Version History
- **v2.0** - Smart filtering, enhanced scraping, alert system
- **v1.1** - Bootstrap interface, manual run features
- **v1.0** - Basic job monitoring and email notifications

---

**⚠️ Legal Notice:** Always respect website terms of service and robots.txt files. This tool is intended for legitimate job searching and market research purposes only. Users are responsible for ensuring compliance with applicable laws and website policies.

**🚀 Ready to transform your job search?** [Get started with the setup wizard](setup.php) or [view the live demo](https://demo.jobfeedaggregator.com)!
