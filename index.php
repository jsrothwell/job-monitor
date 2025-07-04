<?php
// index.php - Main entry point for Job Feed Aggregator

// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in production
ini_set('log_errors', 1);

// Check if setup is needed and redirect accordingly
$setupNeeded = checkIfSetupNeeded();
if ($setupNeeded['needed']) {
    header('Location: ' . $setupNeeded['redirect']);
    exit;
}

// All good, load the main interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Feed Aggregator</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .job-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            background: white;
        }

        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
            border-color: var(--primary-color);
        }

        .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .job-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .search-box {
            border-radius: 50px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .stats-card {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: none;
        }

        .job-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .skeleton {
            animation: skeleton-loading 1s linear infinite alternate;
        }

        @keyframes skeleton-loading {
            0% { opacity: 1; }
            100% { opacity: 0.4; }
        }

        .remote-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }

        .new-badge {
            background: linear-gradient(45deg, #fd7e14, #dc3545);
            color: white;
        }

        .company-filter {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark hero-gradient sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-briefcase-fill me-2"></i>
                Job Feed Aggregator
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-list-ul me-1"></i>All Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="filterRemote()"><i class="bi bi-house me-1"></i>Remote</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="filterRecent()"><i class="bi bi-clock me-1"></i>Recent</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="manage.php"><i class="bi bi-gear me-1"></i>Manage Feeds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="test.php"><i class="bi bi-tools me-1"></i>Test Tool</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="toggleSaved()"><i class="bi bi-bookmark me-1"></i>Saved</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Welcome Message for First Time Users -->
        <div id="welcomeMessage" class="alert alert-info alert-dismissible fade show" role="alert" style="display: none;">
            <h5 class="alert-heading">
                <i class="bi bi-star me-2"></i>Welcome to Job Feed Aggregator!
            </h5>
            <p class="mb-2">
                Transform your job search with our intelligent feed aggregation system.
                Monitor multiple company career pages automatically and get notified about new opportunities.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="manage.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Add Your First Feed
                </a>
                <a href="test.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-tools me-1"></i>Test the System
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-primary mb-1" id="totalJobs">-</h4>
                        <small class="text-muted">Total Jobs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-success mb-1" id="remoteJobs">-</h4>
                        <small class="text-muted">Remote Jobs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-warning mb-1" id="newJobs">-</h4>
                        <small class="text-muted">New This Week</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h4 class="text-info mb-1" id="activeCompanies">-</h4>
                        <small class="text-muted">Active Feeds</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="filter-section p-4">
                    <h5 class="mb-4"><i class="bi bi-funnel me-2"></i>Filters</h5>

                    <!-- Search -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Search Jobs</label>
                        <div class="position-relative">
                            <input type="text" class="form-control search-box" id="searchInput"
                                   placeholder="Job title, keywords..." onkeyup="debounceSearch()">
                            <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-3 text-muted"></i>
                        </div>
                    </div>

                    <!-- Location Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" class="form-control" id="locationFilter"
                               placeholder="City, State or Remote" onkeyup="filterJobs()">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remoteOnly" onchange="filterJobs()">
                            <label class="form-check-label" for="remoteOnly">
                                Remote Only
                            </label>
                        </div>
                    </div>

                    <!-- Job Type Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Job Type</label>
                        <select class="form-select" id="jobTypeFilter" onchange="filterJobs()">
                            <option value="">All Types</option>
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                            <option value="contract">Contract</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>

                    <!-- Experience Level -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Experience Level</label>
                        <select class="form-select" id="experienceFilter" onchange="filterJobs()">
                            <option value="">All Levels</option>
                            <option value="entry">Entry Level</option>
                            <option value="mid">Mid Level</option>
                            <option value="senior">Senior Level</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Department</label>
                        <select class="form-select" id="departmentFilter" onchange="filterJobs()">
                            <option value="">All Departments</option>
                            <option value="engineering">Engineering</option>
                            <option value="design">Design</option>
                            <option value="product">Product</option>
                            <option value="marketing">Marketing</option>
                            <option value="sales">Sales</option>
                            <option value="data">Data</option>
                            <option value="support">Support</option>
                            <option value="hr">HR</option>
                        </select>
                    </div>

                    <!-- Company Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Companies</label>
                        <div class="company-filter" id="companyFilter">
                            <div class="text-muted small">Loading companies...</div>
                        </div>
                    </div>

                    <!-- Sort Options -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Sort By</label>
                        <select class="form-select" id="sortBy" onchange="filterJobs()">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="company">Company A-Z</option>
                            <option value="title">Job Title A-Z</option>
                        </select>
                    </div>

                    <button class="btn btn-outline-danger btn-sm w-100" onclick="clearFilters()">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </button>
                </div>
            </div>

            <!-- Job Listings -->
            <div class="col-lg-9">
                <!-- Results Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <span id="resultsCount">Loading...</span>
                        <small class="text-muted">jobs found</small>
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshJobs()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots me-1"></i>More
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="exportJobs()">
                                    <i class="bi bi-download me-2"></i>Export CSV
                                </a></li>
                                <li><a class="dropdown-item" href="manage.php">
                                    <i class="bi bi-plus-lg me-2"></i>Add Feed
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="runAllFeeds()">
                                    <i class="bi bi-play-circle me-2"></i>Update All Feeds
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Job Cards Container -->
                <div id="jobsList">
                    <!-- Loading skeletons -->
                    <div class="skeleton-jobs">
                        <div class="card job-card mb-3 skeleton">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="company-logo bg-light"></div>
                                    </div>
                                    <div class="col">
                                        <div class="bg-light rounded" style="height: 20px; width: 60%;"></div>
                                        <div class="bg-light rounded mt-2" style="height: 16px; width: 40%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Repeat for more skeleton cards -->
                        <div class="card job-card mb-3 skeleton">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="company-logo bg-light"></div>
                                    </div>
                                    <div class="col">
                                        <div class="bg-light rounded" style="height: 20px; width: 70%;"></div>
                                        <div class="bg-light rounded mt-2" style="height: 16px; width: 50%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card job-card mb-3 skeleton">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="company-logo bg-light"></div>
                                    </div>
                                    <div class="col">
                                        <div class="bg-light rounded" style="height: 20px; width: 55%;"></div>
                                        <div class="bg-light rounded mt-2" style="height: 16px; width: 45%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Load More Button -->
                <div class="text-center mt-4">
                    <button class="btn btn-outline-primary" id="loadMoreBtn" onclick="loadMoreJobs()" style="display: none;">
                        <i class="bi bi-arrow-down-circle me-1"></i>Load More Jobs
                    </button>
                </div>

                <!-- No Results -->
                <div id="noResults" class="text-center py-5" style="display: none;">
                    <i class="bi bi-search display-4 text-muted mb-3"></i>
                    <h5 class="text-muted">No jobs found</h5>
                    <p class="text-muted">Try adjusting your filters or search terms</p>
                    <a href="manage.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Add Job Feeds
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Detail Modal -->
    <div class="modal fade" id="jobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jobModalTitle">Job Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="jobModalBody">
                    <!-- Job details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-warning" id="saveJobBtn">
                        <i class="bi bi-bookmark me-1"></i>Save Job
                    </button>
                    <a href="#" class="btn btn-primary" id="applyJobBtn" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Apply Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Global variables
        let allJobs = [];
        let filteredJobs = [];
        let currentPage = 0;
        const jobsPerPage = 20;
        let searchTimeout;

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadCompanies();
            loadJobs();
        });

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/stats.php');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalJobs').textContent = data.stats.total_jobs || 0;
                    document.getElementById('remoteJobs').textContent = data.stats.remote_jobs || 0;
                    document.getElementById('newJobs').textContent = data.stats.new_jobs || 0;
                    document.getElementById('activeCompanies').textContent = data.stats.active_companies || 0;

                    // Show welcome message if no jobs
                    if (data.stats.total_jobs === 0) {
                        document.getElementById('welcomeMessage').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load companies for filter
        async function loadCompanies() {
            try {
                const response = await fetch('api/companies.php');
                const data = await response.json();

                if (data.success) {
                    const companyFilter = document.getElementById('companyFilter');
                    companyFilter.innerHTML = '';

                    if (data.companies.length === 0) {
                        companyFilter.innerHTML = '<div class="text-muted small">No companies added yet</div>';
                        return;
                    }

                    data.companies.forEach(company => {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input" type="checkbox" value="${company.id}"
                                   id="company${company.id}" onchange="filterJobs()">
                            <label class="form-check-label" for="company${company.id}">
                                ${company.name} <small class="text-muted">(${company.job_count})</small>
                            </label>
                        `;
                        companyFilter.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Error loading companies:', error);
            }
        }

        // Load jobs
        async function loadJobs() {
            try {
                document.querySelector('.skeleton-jobs').style.display = 'block';

                const response = await fetch('api/jobs.php?limit=100');
                const data = await response.json();

                if (data.success) {
                    allJobs = data.jobs || [];
                    filteredJobs = [...allJobs];
                    currentPage = 0;

                    document.querySelector('.skeleton-jobs').style.display = 'none';
                    displayJobs();
                    updateResultsCount();
                } else {
                    throw new Error(data.error || 'Failed to load jobs');
                }
            } catch (error) {
                console.error('Error loading jobs:', error);
                document.querySelector('.skeleton-jobs').style.display = 'none';
                showError('Failed to load jobs. Please check your setup.');
            }
        }

        // Display jobs (same implementation as the previous job feed interface)
        function displayJobs() {
            const jobsList = document.getElementById('jobsList');
            const startIndex = currentPage * jobsPerPage;
            const endIndex = startIndex + jobsPerPage;
            const jobsToShow = filteredJobs.slice(startIndex, endIndex);

            if (currentPage === 0) {
                jobsList.innerHTML = '';
            }

            if (jobsToShow.length === 0 && currentPage === 0) {
                document.getElementById('noResults').style.display = 'block';
                document.getElementById('loadMoreBtn').style.display = 'none';
                return;
            }

            document.getElementById('noResults').style.display = 'none';

            jobsToShow.forEach(job => {
                const jobCard = createJobCard(job);
                jobsList.appendChild(jobCard);
            });

            const loadMoreBtn = document.getElementById('loadMoreBtn');
            if (endIndex < filteredJobs.length) {
                loadMoreBtn.style.display = 'block';
            } else {
                loadMoreBtn.style.display = 'none';
            }
        }

        // Create job card element (same as before)
        function createJobCard(job) {
            const card = document.createElement('div');
            card.className = 'card job-card mb-3';
            card.onclick = () => showJobDetails(job);

            const timeAgo = getTimeAgo(job.first_seen);
            const isNew = new Date(job.first_seen) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);

            card.innerHTML = `
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <img src="${job.logo_url || 'https://via.placeholder.com/48x48?text=' + (job.company_name ? job.company_name[0] : 'J')}"
                                 alt="${job.company_name || 'Company'}" class="company-logo">
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 fw-semibold">${job.title || 'Job Title'}</h6>
                                <div class="d-flex gap-1">
                                    ${isNew ? '<span class="badge new-badge job-badge">NEW</span>' : ''}
                                    ${job.is_remote ? '<span class="badge remote-badge job-badge">REMOTE</span>' : ''}
                                    ${job.job_type && job.job_type !== 'unknown' ? `<span class="badge bg-secondary job-badge">${job.job_type.toUpperCase()}</span>` : ''}
                                </div>
                            </div>
                            <div class="job-meta mb-2">
                                <i class="bi bi-building me-1"></i>${job.company_name || 'Unknown Company'}
                                ${job.location ? `<i class="bi bi-geo-alt ms-3 me-1"></i>${job.location}` : ''}
                                ${job.department ? `<i class="bi bi-tag ms-3 me-1"></i>${job.department}` : ''}
                            </div>
                            ${job.description ? `<p class="text-muted small mb-2">${job.description.substring(0, 150)}...</p>` : ''}
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>${timeAgo}
                                </small>
                                ${job.salary_range ? `<small class="text-success fw-semibold">${job.salary_range}</small>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            return card;
        }

        // Show job details in modal (same as before)
        function showJobDetails(job) {
            document.getElementById('jobModalTitle').textContent = job.title || 'Job Details';
            document.getElementById('applyJobBtn').href = job.url || '#';

            const modalBody = document.getElementById('jobModalBody');
            modalBody.innerHTML = `
                <div class="row mb-3">
                    <div class="col-auto">
                        <img src="${job.logo_url || 'https://via.placeholder.com/64x64?text=' + (job.company_name ? job.company_name[0] : 'J')}"
                             alt="${job.company_name || 'Company'}" class="company-logo" style="width: 64px; height: 64px;">
                    </div>
                    <div class="col">
                        <h5 class="mb-1">${job.company_name || 'Unknown Company'}</h5>
                        <div class="text-muted mb-2">
                            ${job.location || 'Location not specified'}
                            ${job.is_remote ? ' • Remote' : ''}
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            ${job.job_type && job.job_type !== 'unknown' ? `<span class="badge bg-primary">${job.job_type}</span>` : ''}
                            ${job.experience_level && job.experience_level !== 'unknown' ? `<span class="badge bg-info">${job.experience_level}</span>` : ''}
                            ${job.department ? `<span class="badge bg-success">${job.department}</span>` : ''}
                        </div>
                    </div>
                </div>

                ${job.salary_range ? `
                    <div class="alert alert-success">
                        <i class="bi bi-currency-dollar me-2"></i><strong>Salary:</strong> ${job.salary_range}
                    </div>
                ` : ''}

                ${job.description ? `
                    <div class="mb-3">
                        <h6>Job Description</h6>
                        <div class="border p-3 rounded bg-light" style="max-height: 300px; overflow-y: auto;">
                            ${job.description.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                ` : ''}

                <div class="row text-center">
                    <div class="col-4">
                        <small class="text-muted">Posted</small>
                        <div>${getTimeAgo(job.first_seen)}</div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Last Updated</small>
                        <div>${getTimeAgo(job.last_seen)}</div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Status</small>
                        <div><span class="badge bg-${job.status === 'new' ? 'warning' : 'success'}">${job.status}</span></div>
                    </div>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('jobModal')).show();
        }

        // Filter functions (implement the same filtering logic as the previous interface)
        function filterJobs() {
            // Same implementation as previous
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const locationFilter = document.getElementById('locationFilter').value.toLowerCase();
            const remoteOnly = document.getElementById('remoteOnly').checked;
            const jobType = document.getElementById('jobTypeFilter').value;
            const experience = document.getElementById('experienceFilter').value;
            const department = document.getElementById('departmentFilter').value;
            const sortBy = document.getElementById('sortBy').value;

            const selectedCompanies = Array.from(document.querySelectorAll('#companyFilter input:checked'))
                .map(cb => parseInt(cb.value));

            filteredJobs = allJobs.filter(job => {
                if (searchTerm && !job.title.toLowerCase().includes(searchTerm) &&
                    !(job.description && job.description.toLowerCase().includes(searchTerm))) {
                    return false;
                }

                if (locationFilter && job.location && !job.location.toLowerCase().includes(locationFilter)) {
                    return false;
                }

                if (remoteOnly && !job.is_remote) {
                    return false;
                }

                if (jobType && job.job_type !== jobType) {
                    return false;
                }

                if (experience && job.experience_level !== experience) {
                    return false;
                }

                if (department && job.department !== department) {
                    return false;
                }

                if (selectedCompanies.length > 0 && !selectedCompanies.includes(job.company_id)) {
                    return false;
                }

                return true;
            });

            // Sort jobs
            filteredJobs.sort((a, b) => {
                switch (sortBy) {
                    case 'oldest':
                        return new Date(a.first_seen) - new Date(b.first_seen);
                    case 'company':
                        return (a.company_name || '').localeCompare(b.company_name || '');
                    case 'title':
                        return (a.title || '').localeCompare(b.title || '');
                    default:
                        return new Date(b.first_seen) - new Date(a.first_seen);
                }
            });

            currentPage = 0;
            displayJobs();
            updateResultsCount();
        }

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterJobs, 300);
        }

        function loadMoreJobs() {
            currentPage++;
            displayJobs();
        }

        function updateResultsCount() {
            document.getElementById('resultsCount').textContent = filteredJobs.length;
        }

        function filterRemote() {
            document.getElementById('remoteOnly').checked = true;
            filterJobs();
        }

        function filterRecent() {
            document.getElementById('sortBy').value = 'newest';
            filterJobs();
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('locationFilter').value = '';
            document.getElementById('remoteOnly').checked = false;
            document.getElementById('jobTypeFilter').value = '';
            document.getElementById('experienceFilter').value = '';
            document.getElementById('departmentFilter').value = '';
            document.getElementById('sortBy').value = 'newest';

            document.querySelectorAll('#companyFilter input').forEach(cb => cb.checked = false);

            filterJobs();
        }

        async function refreshJobs() {
            await loadJobs();
            await loadStats();
        }

        function toggleSaved() {
            alert('Saved jobs functionality coming soon!');
        }

        function exportJobs() {
            window.open('api/export-jobs.php', '_blank');
        }

        async function runAllFeeds() {
            if (confirm('This will check all active feeds for new jobs. Continue?')) {
                try {
                    const response = await fetch('api/run-all-feeds.php', { method: 'POST' });
                    const result = await response.json();

                    if (result.success) {
                        alert(`Feed update complete! Found ${result.total_new_jobs} new jobs from ${result.companies_checked} companies.`);
                        refreshJobs();
                    } else {
                        throw new Error(result.error);
                    }
                } catch (error) {
                    alert('Error updating feeds: ' + error.message);
                }
            }
        }

        function getTimeAgo(dateString) {
            if (!dateString) return 'Unknown';

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

        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').prepend(alert);
        }
    </script>
</body>
</html>

<?php
function checkIfSetupNeeded() {
    // Check 1: Config file exists
    if (!file_exists(__DIR__ . '/config/config.php')) {
        return [
            'needed' => true,
            'redirect' => 'setup.php?reason=no_config',
            'message' => 'Configuration file missing'
        ];
    }

    // Check 2: Required files exist
    $requiredFiles = [
        'src/Database.php',
        'src/Company.php',
        'src/JobScraper.php',
        'src/JobMonitor.php',
        'src/Emailer.php'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            return [
                'needed' => true,
                'redirect' => 'setup.php?reason=missing_files',
                'message' => 'Core files missing'
            ];
        }
    }

    // Check 3: Database connection and tables
    try {
        require_once __DIR__ . '/src/Database.php';
        $db = new Database();
        $pdo = $db->getConnection();

        // Check if tables exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
        if (!$stmt->fetch()) {
            // Check if this looks like an existing Job Monitor (v1) installation
            $stmt = $pdo->query("SHOW TABLES");
            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            if (in_array('companies', $tables) || in_array('jobs', $tables)) {
                // Has some tables but not the new structure - needs migration
                return [
                    'needed' => true,
                    'redirect' => 'migrate.php?reason=upgrade_needed',
                    'message' => 'Database upgrade needed'
                ];
            } else {
                // Fresh database - needs full setup
                return [
                    'needed' => true,
                    'redirect' => 'setup.php?step=3&reason=no_tables',
                    'message' => 'Database tables missing'
                ];
            }
        }

        // Check if we have the enhanced structure
        $stmt = $pdo->query("DESCRIBE companies");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        if (!in_array('industry', $columns) || !in_array('logo_url', $columns)) {
            // Old structure detected - needs migration
            return [
                'needed' => true,
                'redirect' => 'migrate.php?reason=old_structure',
                'message' => 'Database structure needs upgrade'
            ];
        }

    } catch (Exception $e) {
        // Database connection failed
        return [
            'needed' => true,
            'redirect' => 'setup.php?reason=db_error&error=' . urlencode($e->getMessage()),
            'message' => 'Database connection failed'
        ];
    }

    // All checks passed - no setup needed
    return [
        'needed' => false,
        'message' => 'System ready'
    ];
}

function showSetupPage($error = null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Job Feed Setup Required</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-gear display-1 text-warning mb-4"></i>
                            <h2 class="mb-3">Setup Required</h2>
                            <p class="text-muted mb-4">
                                Job Feed Aggregator needs to be configured before you can start using it.
                            </p>

                            <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2 col-md-6 mx-auto">
                                <?php if (file_exists(__DIR__ . '/migrate.php')): ?>
                                <a href="migrate.php" class="btn btn-primary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Run Migration
                                </a>
                                <?php endif; ?>

                                <?php if (file_exists(__DIR__ . '/config/config.example.php')): ?>
                                <a href="config/config.example.php" class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-file-text me-2"></i>View Config Example
                                </a>
                                <?php endif; ?>

                                <a href="test.php" class="btn btn-outline-info">
                                    <i class="bi bi-tools me-2"></i>Test System
                                </a>
                            </div>

                            <hr class="my-4">

                            <div class="text-start">
                                <h5>Setup Checklist:</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-<?= file_exists(__DIR__ . '/config/config.php') ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                        Configuration file (config/config.php)
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-<?= file_exists(__DIR__ . '/src/Database.php') ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                        Core files uploaded
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-question-circle text-warning me-2"></i>
                                        Database tables created
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
