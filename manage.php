<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job Feeds</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .company-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .company-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .status-badge {
            font-size: 0.75rem;
        }

        .stats-card {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: none;
        }

        .form-floating label {
            color: #6c757d;
        }

        .selector-help {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin: 1rem 0;
        }

        .test-results {
            max-height: 300px;
            overflow-y: auto;
        }

        .advanced-toggle {
            cursor: pointer;
            color: var(--primary-color);
        }

        .advanced-toggle:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark hero-gradient sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Job Feed
            </a>

            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="test.php">
                    <i class="bi bi-tools me-1"></i>Test Tool
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">Manage Job Feeds</h2>
                        <p class="text-muted mb-0">Add and configure company career pages to monitor</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                        <i class="bi bi-plus-lg me-2"></i>Add New Feed
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-primary mb-1" id="totalCompanies">-</h4>
                        <small class="text-muted">Total Feeds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-success mb-1" id="activeCompanies">-</h4>
                        <small class="text-muted">Active Feeds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-info mb-1" id="totalJobs">-</h4>
                        <small class="text-muted">Total Jobs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-warning mb-1" id="lastUpdate">-</h4>
                        <small class="text-muted">Last Update</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchCompanies" placeholder="Search companies...">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Companies List -->
        <div class="row" id="companiesList">
            <!-- Loading placeholder -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading companies...</p>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Bulk Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="runAllFeeds()">
                                    <i class="bi bi-play-circle me-2"></i>Run All Active Feeds
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-success w-100" onclick="activateAllFeeds()">
                                    <i class="bi bi-check-circle me-2"></i>Activate All Feeds
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-warning w-100" onclick="cleanOldJobs()">
                                    <i class="bi bi-trash me-2"></i>Clean Old Jobs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Company Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Job Feed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="companyForm">
                    <div class="modal-body">
                        <input type="hidden" id="companyId" name="id">

                        <!-- Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="companyName" name="name" required>
                                    <label for="companyName">Company Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="industry" name="industry">
                                        <option value="">Select Industry</option>
                                        <option value="Technology">Technology</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Education">Education</option>
                                        <option value="Retail">Retail</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Entertainment">Entertainment</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <label for="industry">Industry</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="websiteUrl" name="website_url">
                                    <label for="websiteUrl">Company Website</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="logoUrl" name="logo_url">
                                    <label for="logoUrl">Logo URL</label>
                                </div>
                            </div>
                        </div>

                        <!-- Careers Configuration -->
                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="url" class="form-control" id="careersUrl" name="careers_url" required>
                                <label for="careersUrl">Careers Page URL</label>
                            </div>
                        </div>

                        <!-- CSS Selectors -->
                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="selector" name="selector"
                                       placeholder="a[href*='job'], .job-listing a">
                                <label for="selector">Job Links Selector (Optional)</label>
                            </div>
                            <div class="form-text">CSS selector to find job links. Leave empty for auto-detection.</div>
                        </div>

                        <!-- Advanced Selectors -->
                        <div class="mb-3">
                            <span class="advanced-toggle" onclick="toggleAdvancedSelectors()">
                                <i class="bi bi-chevron-right" id="advancedIcon"></i>
                                Advanced Selectors (Optional)
                            </span>
                        </div>

                        <div id="advancedSelectors" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="locationSelector" name="location_selector">
                                        <label for="locationSelector">Location Selector</label>
                                    </div>
                                    <div class="form-text">CSS selector to find job location information</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="descriptionSelector" name="description_selector">
                                        <label for="descriptionSelector">Description Selector</label>
                                    </div>
                                    <div class="form-text">CSS selector to find job descriptions</div>
                                </div>
                            </div>
                        </div>

                        <!-- Selector Help -->
                        <div class="selector-help">
                            <h6><i class="bi bi-lightbulb me-2"></i>Common Selectors:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small>
                                        <strong>Job Links:</strong><br>
                                        • <code>a[href*="job"]</code><br>
                                        • <code>.job-listing a</code><br>
                                        • <code>.posting-title a</code>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small>
                                        <strong>Location:</strong><br>
                                        • <code>.location</code><br>
                                        • <code>[data-location]</code><br>
                                        • <code>.job-location</code>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Test Section -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-info w-100" onclick="testCompanySelectors()">
                                <i class="bi bi-play-circle me-2"></i>Test Selectors
                            </button>
                        </div>

                        <div id="testResults" style="display: none;" class="mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Test Results</h6>
                                </div>
                                <div class="card-body test-results" id="testResultsBody">
                                    <!-- Test results will appear here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Save Feed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Action Results Modal -->
    <div class="modal fade" id="bulkResultsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Action Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bulkResultsBody">
                    <!-- Results will appear here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let companies = [];
        let filteredCompanies = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadCompanies();

            // Event listeners
            document.getElementById('searchCompanies').addEventListener('keyup', filterCompanies);
            document.getElementById('filterStatus').addEventListener('change', filterCompanies);
            document.getElementById('companyForm').addEventListener('submit', saveCompany);
        });

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/stats.php');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalJobs').textContent = data.stats.total_jobs;
                    document.getElementById('activeCompanies').textContent = data.stats.active_companies;
                }

                // Load company-specific stats
                const compResponse = await fetch('api/companies.php');
                const compData = await compResponse.json();

                if (compData.success) {
                    document.getElementById('totalCompanies').textContent = compData.companies.length;

                    // Find last update
                    let lastUpdate = null;
                    compData.companies.forEach(comp => {
                        if (comp.last_checked && (!lastUpdate || comp.last_checked > lastUpdate)) {
                            lastUpdate = comp.last_checked;
                        }
                    });

                    document.getElementById('lastUpdate').textContent = lastUpdate ?
                        getTimeAgo(lastUpdate) : 'Never';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load companies
        async function loadCompanies() {
            try {
                const response = await fetch('api/companies.php');
                const data = await response.json();

                if (data.success) {
                    companies = data.companies;
                    filteredCompanies = [...companies];
                    displayCompanies();
                }
            } catch (error) {
                console.error('Error loading companies:', error);
                showError('Failed to load companies');
            }
        }

        // Display companies
        function displayCompanies() {
            const container = document.getElementById('companiesList');

            if (filteredCompanies.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                        <h5 class="text-muted">No companies found</h5>
                        <p class="text-muted">Add your first job feed to get started</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                            <i class="bi bi-plus-lg me-2"></i>Add Job Feed
                        </button>
                    </div>
                `;
                return;
            }

            let html = '';
            filteredCompanies.forEach(company => {
                html += createCompanyCard(company);
            });

            container.innerHTML = html;
        }

        // Create company card
        function createCompanyCard(company) {
            const lastChecked = company.last_checked ? getTimeAgo(company.last_checked) : 'Never';
            const statusBadge = company.status === 'active' ?
                '<span class="badge bg-success status-badge">Active</span>' :
                '<span class="badge bg-secondary status-badge">Inactive</span>';

            return `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card company-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <img src="${company.logo_url || 'https://via.placeholder.com/48x48?text=' + company.name[0]}"
                                     alt="${company.name}" class="company-logo me-3">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${company.name}</h6>
                                    <small class="text-muted">${company.industry || 'Unknown Industry'}</small>
                                </div>
                                ${statusBadge}
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block">
                                    <i class="bi bi-briefcase me-1"></i>${company.job_count} jobs
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-clock me-1"></i>Last checked: ${lastChecked}
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-link-45deg me-1"></i>
                                    <a href="${company.careers_url}" target="_blank" class="text-decoration-none">
                                        Careers Page
                                    </a>
                                </small>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm flex-fill"
                                        onclick="testSingleCompany(${company.id})">
                                    <i class="bi bi-play-circle me-1"></i>Test
                                </button>
                                <button class="btn btn-outline-secondary btn-sm flex-fill"
                                        onclick="editCompany(${company.id})">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </button>
                                <button class="btn btn-outline-${company.status === 'active' ? 'warning' : 'success'} btn-sm"
                                        onclick="toggleCompanyStatus(${company.id})">
                                    <i class="bi bi-${company.status === 'active' ? 'pause' : 'play'}-circle"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm"
                                        onclick="deleteCompany(${company.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Filter companies
        function filterCompanies() {
            const search = document.getElementById('searchCompanies').value.toLowerCase();
            const status = document.getElementById('filterStatus').value;

            filteredCompanies = companies.filter(company => {
                const matchesSearch = !search ||
                    company.name.toLowerCase().includes(search) ||
                    (company.industry && company.industry.toLowerCase().includes(search));

                const matchesStatus = !status || company.status === status;

                return matchesSearch && matchesStatus;
            });

            displayCompanies();
        }

        // Save company
        async function saveCompany(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('api/save-company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Company saved successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('addCompanyModal')).hide();
                    loadCompanies();
                    loadStats();
                } else {
                    showError(result.error || 'Failed to save company');
                }
            } catch (error) {
                console.error('Error saving company:', error);
                showError('Failed to save company');
            }
        }

        // Edit company
        function editCompany(id) {
            const company = companies.find(c => c.id === id);
            if (!company) return;

            document.getElementById('modalTitle').textContent = 'Edit Job Feed';
            document.getElementById('companyId').value = company.id;
            document.getElementById('companyName').value = company.name;
            document.getElementById('careersUrl').value = company.careers_url;
            document.getElementById('selector').value = company.selector || '';
            document.getElementById('locationSelector').value = company.location_selector || '';
            document.getElementById('descriptionSelector').value = company.description_selector || '';
            document.getElementById('websiteUrl').value = company.website_url || '';
            document.getElementById('logoUrl').value = company.logo_url || '';
            document.getElementById('industry').value = company.industry || '';

            new bootstrap.Modal(document.getElementById('addCompanyModal')).show();
        }

        // Test single company
        async function testSingleCompany(id) {
            try {
                showLoading('Testing company...');

                const response = await fetch(`api/test-company.php?id=${id}`);
                const result = await response.json();

                hideLoading();

                if (result.success) {
                    showTestResults(result);
                } else {
                    showError(result.error || 'Test failed');
                }
            } catch (error) {
                hideLoading();
                console.error('Error testing company:', error);
                showError('Test failed');
            }
        }

        // Test company selectors in modal
        async function testCompanySelectors() {
            const url = document.getElementById('careersUrl').value;
            const selector = document.getElementById('selector').value;

            if (!url) {
                showError('Please enter a careers URL first');
                return;
            }

            try {
                const response = await fetch('api/test-url.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        url: url,
                        selector: selector
                    })
                });

                const result = await response.json();

                document.getElementById('testResults').style.display = 'block';
                document.getElementById('testResultsBody').innerHTML = formatTestResults(result);

            } catch (error) {
                console.error('Error testing selectors:', error);
                showError('Test failed');
            }
        }

        // Format test results
        function formatTestResults(result) {
            if (!result.success) {
                return `<div class="alert alert-danger">Error: ${result.error}</div>`;
            }

            if (result.jobs.length === 0) {
                return `<div class="alert alert-warning">No jobs found. Try adjusting your selector.</div>`;
            }

            let html = `
                <div class="alert alert-success">
                    Found ${result.jobs.length} job(s) in ${result.duration}s
                </div>
                <div class="list-group">
            `;

            result.jobs.slice(0, 5).forEach((job, index) => {
                html += `
                    <div class="list-group-item">
                        <strong>${index + 1}. ${job.title}</strong>
                        ${job.location ? `<br><small class="text-muted">📍 ${job.location}</small>` : ''}
                        ${job.url ? `<br><small><a href="${job.url}" target="_blank">${job.url}</a></small>` : ''}
                    </div>
                `;
            });

            if (result.jobs.length > 5) {
                html += `<div class="list-group-item text-muted">...and ${result.jobs.length - 5} more</div>`;
            }

            html += '</div>';
            return html;
        }

        // Toggle company status
        async function toggleCompanyStatus(id) {
            const company = companies.find(c => c.id === id);
            const newStatus = company.status === 'active' ? 'inactive' : 'active';

            try {
                const response = await fetch('api/update-company-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        status: newStatus
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(`Company ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
                    loadCompanies();
                    loadStats();
                } else {
                    showError(result.error || 'Failed to update status');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showError('Failed to update status');
            }
        }

        // Delete company
        async function deleteCompany(id) {
            if (!confirm('Are you sure you want to delete this company? This will also delete all associated jobs.')) {
                return;
            }

            try {
                const response = await fetch(`api/delete-company.php?id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Company deleted successfully');
                    loadCompanies();
                    loadStats();
                } else {
                    showError(result.error || 'Failed to delete company');
                }
            } catch (error) {
                console.error('Error deleting company:', error);
                showError('Failed to delete company');
            }
        }

        // Toggle advanced selectors
        function toggleAdvancedSelectors() {
            const section = document.getElementById('advancedSelectors');
            const icon = document.getElementById('advancedIcon');

            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.className = 'bi bi-chevron-down';
            } else {
                section.style.display = 'none';
                icon.className = 'bi bi-chevron-right';
            }
        }

        // Bulk actions
        async function runAllFeeds() {
            showLoading('Running all active feeds...');

            try {
                const response = await fetch('api/run-all-feeds.php', {
                    method: 'POST'
                });

                const result = await response.json();
                hideLoading();

                showBulkResults(result);
                loadStats();
                loadCompanies();
            } catch (error) {
                hideLoading();
                console.error('Error running feeds:', error);
                showError('Failed to run feeds');
            }
        }

        async function activateAllFeeds() {
            if (!confirm('Are you sure you want to activate all feeds?')) return;

            try {
                const response = await fetch('api/activate-all-feeds.php', {
                    method: 'POST'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('All feeds activated');
                    loadCompanies();
                    loadStats();
                } else {
                    showError(result.error || 'Failed to activate feeds');
                }
            } catch (error) {
                console.error('Error activating feeds:', error);
                showError('Failed to activate feeds');
            }
        }

        async function cleanOldJobs() {
            if (!confirm('This will mark jobs older than 30 days as removed. Continue?')) return;

            try {
                const response = await fetch('api/clean-old-jobs.php', {
                    method: 'POST'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(`Cleaned ${result.removed_count} old jobs`);
                    loadStats();
                } else {
                    showError(result.error || 'Failed to clean jobs');
                }
            } catch (error) {
                console.error('Error cleaning jobs:', error);
                showError('Failed to clean jobs');
            }
        }

        // Show bulk results
        function showBulkResults(results) {
            const modal = new bootstrap.Modal(document.getElementById('bulkResultsModal'));
            const body = document.getElementById('bulkResultsBody');

            let html = `
                <div class="row mb-3">
                    <div class="col-md-3 text-center">
                        <h4 class="text-primary">${results.companies_checked || 0}</h4>
                        <small>Feeds Checked</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-success">${results.total_new_jobs || 0}</h4>
                        <small>New Jobs</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-info">${results.emails_sent || 0}</h4>
                        <small>Alerts Sent</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-danger">${results.errors || 0}</h4>
                        <small>Errors</small>
                    </div>
                </div>
            `;

            if (results.details) {
                html += '<h6>Details:</h6><div class="list-group">';

                Object.entries(results.details).forEach(([company, details]) => {
                    const statusClass = details.success ? 'success' : 'danger';
                    const icon = details.success ? 'check-circle' : 'x-circle';

                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-${icon} text-${statusClass} me-2"></i>
                                <strong>${company}</strong>
                                ${details.error ? `<br><small class="text-danger">${details.error}</small>` : ''}
                            </div>
                            <span class="badge bg-${statusClass}">${details.new_jobs || 0} jobs</span>
                        </div>
                    `;
                });

                html += '</div>';
            }

            body.innerHTML = html;
            modal.show();
        }

        // Utility functions
        function showSuccess(message) {
            // You can implement a toast notification here
            alert(message);
        }

        function showError(message) {
            alert('Error: ' + message);
        }

        function showLoading(message) {
            // You can implement a loading overlay here
            console.log('Loading:', message);
        }

        function hideLoading() {
            console.log('Loading complete');
        }

        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return '1 day ago';
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
            return `${Math.floor(diffDays / 30)} months ago`;
        }

        // Reset modal when closed
        document.getElementById('addCompanyModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('companyForm').reset();
            document.getElementById('companyId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Job Feed';
            document.getElementById('testResults').style.display = 'none';
            document.getElementById('advancedSelectors').style.display = 'none';
            document.getElementById('advancedIcon').className = 'bi bi-chevron-right';
        });
    </script>
</body>
</html>
