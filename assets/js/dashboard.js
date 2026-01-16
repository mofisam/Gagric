// Mobile sidebar functionality
class DashboardSidebar {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.backdrop = document.querySelector('.sidebar-backdrop');
        this.init();
    }

    init() {
        this.createBackdrop();
        this.setupEventListeners();
    }

    createBackdrop() {
        if (!this.backdrop) {
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(this.backdrop);
        }
    }

    setupEventListeners() {
        // Toggle sidebar on mobile
        document.querySelectorAll('[data-bs-toggle="sidebar"]').forEach(btn => {
            btn.addEventListener('click', () => this.toggle());
        });

        // Close sidebar when clicking backdrop
        this.backdrop.addEventListener('click', () => this.hide());

        // Close sidebar when clicking close button
        const closeBtn = document.getElementById('closeSidebar');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hide());
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && 
                this.sidebar.classList.contains('show') &&
                !this.sidebar.contains(e.target) &&
                !e.target.closest('[data-bs-toggle="sidebar"]')) {
                this.hide();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                this.hide();
            }
        });
    }

    toggle() {
        this.sidebar.classList.toggle('show');
        this.backdrop.classList.toggle('show');
        document.body.style.overflow = this.sidebar.classList.contains('show') ? 'hidden' : '';
    }

    show() {
        this.sidebar.classList.add('show');
        this.backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    hide() {
        this.sidebar.classList.remove('show');
        this.backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize dashboard sidebar
    if (document.getElementById('sidebar')) {
        window.dashboardSidebar = new DashboardSidebar();
    }

    // Update active sidebar item based on current page
    const currentPath = window.location.pathname;
    document.querySelectorAll('#sidebar .nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
            // Also activate parent dropdown if exists
            const parentCollapse = link.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
            }
        }
    });
});

// Mobile menu toggle button for navbar
document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function() {
            document.body.classList.toggle('navbar-expanded');
        });
    }
});