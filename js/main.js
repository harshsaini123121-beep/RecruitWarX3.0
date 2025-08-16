// Main JavaScript functionality
class RecruitmentPortal {
    constructor() {
        this.apiBase = window.location.origin;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeComponents();
        this.loadDashboardData();
    }

    bindEvents() {
        // Login form handling
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', this.handleLogin.bind(this));
        }

        // Registration form handling
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', this.handleRegistration.bind(this));
        }

        // Job creation form handling
        const createJobForm = document.getElementById('createJobForm');
        if (createJobForm) {
            createJobForm.addEventListener('submit', this.handleJobCreation.bind(this));
        }

        // Job search functionality
        const searchInput = document.getElementById('jobSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.handleJobSearch.bind(this), 300));
        }

        // Filter handling
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', this.handleFilterChange.bind(this));
        });

        // Job application buttons
        document.querySelectorAll('.apply-job-btn').forEach(button => {
            button.addEventListener('click', this.handleJobApplication.bind(this));
        });

        // Application status update buttons
        document.querySelectorAll('.update-status-btn').forEach(button => {
            button.addEventListener('click', this.handleStatusUpdate.bind(this));
        });

        // Modal handling
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', this.handleModalOpen.bind(this));
        });
    }

    initializeComponents() {
        // Initialize tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Initialize charts if on dashboard
        if (document.getElementById('analyticsChart')) {
            this.initializeCharts();
        }

        // Load jobs if on jobs page
        if (document.querySelector('.job-listings')) {
            this.loadJobs();
        }

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    }

    async loadDashboardData() {
        if (!document.querySelector('.stats-card')) return;

        try {
            const response = await fetch('handlers/job_handler.php?action=get_dashboard_stats');
            const result = await response.json();

            if (result.success) {
                this.updateDashboardStats(result.stats);
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    updateDashboardStats(stats) {
        const statsCards = document.querySelectorAll('.stats-card');
        
        if (stats.total_users !== undefined) {
            // Admin dashboard
            if (statsCards[0]) statsCards[0].querySelector('.stats-number').textContent = stats.total_users;
            if (statsCards[1]) statsCards[1].querySelector('.stats-number').textContent = stats.active_jobs;
            if (statsCards[2]) statsCards[2].querySelector('.stats-number').textContent = stats.total_applications;
            if (statsCards[3]) statsCards[3].querySelector('.stats-number').textContent = stats.hires_this_month;
        } else if (stats.applications_sent !== undefined) {
            // Candidate dashboard
            if (statsCards[0]) statsCards[0].querySelector('.stats-number').textContent = stats.applications_sent;
            if (statsCards[1]) statsCards[1].querySelector('.stats-number').textContent = stats.interviews_scheduled;
            if (statsCards[2]) statsCards[2].querySelector('.stats-number').textContent = stats.profile_views;
            if (statsCards[3]) statsCards[3].querySelector('.stats-number').textContent = stats.profile_complete + '%';
        } else if (stats.active_jobs !== undefined) {
            // Recruiter dashboard
            if (statsCards[0]) statsCards[0].querySelector('.stats-number').textContent = stats.active_jobs;
            if (statsCards[1]) statsCards[1].querySelector('.stats-number').textContent = stats.total_applications;
            if (statsCards[2]) statsCards[2].querySelector('.stats-number').textContent = stats.interviews_scheduled;
            if (statsCards[3]) statsCards[3].querySelector('.stats-number').textContent = stats.offers_extended;
        }
    }

    async loadJobs(filters = {}) {
        const jobsContainer = document.getElementById('jobsContainer');
        const jobCountElement = document.getElementById('jobCount');
        
        if (!jobsContainer) return;

        try {
            const params = new URLSearchParams(filters);
            const response = await fetch(`api/jobs.php?${params}`);
            const result = await response.json();

            if (result.success) {
                this.renderJobs(result.jobs);
                if (jobCountElement) {
                    jobCountElement.textContent = result.jobs.length;
                }
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            console.error('Error loading jobs:', error);
            this.showMessage('Error loading jobs', 'error');
        }
    }

    renderJobs(jobs) {
        const jobsContainer = document.getElementById('jobsContainer');
        if (!jobsContainer) return;

        let jobsHTML = '';
        
        jobs.forEach(job => {
            const salaryText = job.salary_range || 'Salary not specified';
            const companyName = job.company_name || 'Company';
            const daysAgo = job.days_ago === 0 ? 'Today' : 
                           job.days_ago === 1 ? '1 day ago' : 
                           `${job.days_ago} days ago`;

            jobsHTML += `
                <div class="job-card card mb-3" data-job-type="${job.job_type}" data-experience-level="${job.experience_level}" data-location="${job.location}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="me-3">
                                        <div class="bg-primary rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="fas fa-briefcase text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="job-title mb-1">${job.title}</h5>
                                        <p class="job-company mb-2">${companyName} • ${job.location}</p>
                                        <div class="job-details">
                                            <span class="job-detail-item">${job.job_type.charAt(0).toUpperCase() + job.job_type.slice(1).replace('-', ' ')}</span>
                                            <span class="job-detail-item">${job.remote_allowed ? 'Remote' : 'On-site'}</span>
                                            <span class="job-detail-item">${salaryText}</span>
                                            <span class="job-detail-item">${job.experience_level.charAt(0).toUpperCase() + job.experience_level.slice(1)} Level</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="job-description text-muted mb-3">
                                    ${job.description.substring(0, 200)}${job.description.length > 200 ? '...' : ''}
                                </p>
                                <div class="d-flex align-items-center text-muted">
                                    <small><i class="fas fa-clock me-1"></i>${daysAgo}</small>
                                    <small class="ms-3"><i class="fas fa-users me-1"></i>${job.application_count || 0} applicants</small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary mb-2 w-100 apply-job-btn" data-job-id="${job.id}">
                                    <i class="fas fa-paper-plane me-2"></i>Apply Now
                                </button>
                                <button class="btn btn-outline-primary w-100">
                                    <i class="fas fa-heart me-2"></i>Save Job
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        jobsContainer.innerHTML = jobsHTML;

        // Re-bind event listeners for new apply buttons
        document.querySelectorAll('.apply-job-btn').forEach(button => {
            button.addEventListener('click', this.handleJobApplication.bind(this));
        });
    }

    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        
        this.showLoading(submitBtn);
        
        try {
            const response = await fetch('handlers/auth_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = this.getDashboardUrl(result.role);
                }, 1500);
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('Login failed. Please try again.', 'error');
        } finally {
            this.hideLoading(submitBtn);
        }
    }

    async handleRegistration(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        
        // Validate passwords match
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (password !== confirmPassword) {
            this.showMessage('Passwords do not match!', 'error');
            return;
        }
        
        this.showLoading(submitBtn);
        
        try {
            const response = await fetch('handlers/auth_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('Registration successful! Please login.', 'success');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('Registration failed. Please try again.', 'error');
        } finally {
            this.hideLoading(submitBtn);
        }
    }

    async handleJobCreation(e) {
        e.preventDefault();
        const formData = new FormData();
        
        // Get form data
        formData.append('action', 'create_job');
        formData.append('title', document.getElementById('jobTitle').value);
        formData.append('description', document.getElementById('jobDescription').value);
        formData.append('requirements', document.getElementById('jobRequirements').value);
        formData.append('location', document.getElementById('location').value);
        formData.append('job_type', document.getElementById('jobType').value);
        formData.append('experience_level', document.getElementById('experienceLevel').value);
        formData.append('salary_min', document.getElementById('salaryMin').value);
        formData.append('salary_max', document.getElementById('salaryMax').value);
        formData.append('status', 'active');
        
        try {
            const response = await fetch('handlers/job_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('Job created successfully!', 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('createJobModal'));
                modal.hide();
                // Reset form
                e.target.reset();
                // Reload page or update job list
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('Failed to create job. Please try again.', 'error');
        }
    }

    async handleJobApplication(e) {
        const jobId = e.target.dataset.jobId;
        if (!jobId) return;

        // Show application modal or directly apply
        const coverLetter = prompt('Please enter a brief cover letter (optional):') || '';
        
        const formData = new FormData();
        formData.append('action', 'apply_job');
        formData.append('job_id', jobId);
        formData.append('cover_letter', coverLetter);

        try {
            const response = await fetch('handlers/job_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('Application submitted successfully!', 'success');
                e.target.innerHTML = '<i class="fas fa-check me-2"></i>Applied';
                e.target.disabled = true;
                e.target.classList.remove('btn-primary');
                e.target.classList.add('btn-success');
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('Failed to submit application. Please try again.', 'error');
        }
    }

    handleJobSearch(e) {
        const searchTerm = e.target.value;
        const filters = { search: searchTerm };
        
        // Get other active filters
        const activeFilters = this.getActiveFilters();
        Object.assign(filters, activeFilters);
        
        this.loadJobs(filters);
    }

    handleFilterChange() {
        const filters = this.getActiveFilters();
        const searchTerm = document.getElementById('jobSearch')?.value;
        if (searchTerm) {
            filters.search = searchTerm;
        }
        
        this.loadJobs(filters);
    }

    getActiveFilters() {
        const filters = {};
        
        // Job type filters
        const jobTypes = [];
        document.querySelectorAll('input[data-filter-type="jobType"]:checked').forEach(checkbox => {
            jobTypes.push(checkbox.value);
        });
        if (jobTypes.length > 0) {
            filters.job_type = jobTypes.join(',');
        }
        
        // Experience level filters
        const expLevels = [];
        document.querySelectorAll('input[data-filter-type="experienceLevel"]:checked').forEach(checkbox => {
            expLevels.push(checkbox.value);
        });
        if (expLevels.length > 0) {
            filters.experience_level = expLevels.join(',');
        }
        
        return filters;
    }

    handleModalOpen(e) {
        const modal = document.querySelector(e.target.dataset.bsTarget);
        if (modal) {
            // Load dynamic content if needed
            const contentUrl = e.target.dataset.contentUrl;
            if (contentUrl) {
                this.loadModalContent(modal, contentUrl);
            }
        }
    }

    async loadModalContent(modal, url) {
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = '<div class="text-center"><div class="spinner"></div></div>';
        
        try {
            const response = await fetch(url);
            const content = await response.text();
            modalBody.innerHTML = content;
        } catch (error) {
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load content</div>';
        }
    }

    initializeCharts() {
        // Initialize Chart.js charts for analytics
        if (typeof Chart !== 'undefined') {
            const ctx = document.getElementById('analyticsChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Active Jobs', 'Applications', 'Hired', 'Pending'],
                        datasets: [{
                            data: [25, 150, 12, 45],
                            backgroundColor: [
                                'var(--primary-blue)',
                                'var(--secondary-blue)',
                                'var(--success-green)',
                                'var(--warning-orange)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }
    }

    getDashboardUrl(role) {
        const dashboards = {
            'admin': 'admin-dashboard.html',
            'recruiter': 'recruiter-dashboard.html',
            'hiring_manager': 'recruiter-dashboard.html',
            'candidate': 'candidate-dashboard.html'
        };
        return dashboards[role] || 'candidate-dashboard.html';
    }

    showMessage(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);
    }

    showLoading(button) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    }

    hideLoading(button) {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || 'Submit';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

function formatSalary(min, max) {
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0
    });
    
    if (min && max) {
        return `${formatter.format(min)} - ${formatter.format(max)}`;
    } else if (min) {
        return `From ${formatter.format(min)}`;
    } else if (max) {
        return `Up to ${formatter.format(max)}`;
    }
    return 'Salary not specified';
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new RecruitmentPortal();
});

// Export for use in other files
window.RecruitmentPortal = RecruitmentPortal;