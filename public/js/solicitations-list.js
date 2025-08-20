/**
 * Solicitations List JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - View toggle (cards/list)
 * - CSV export
 * - Filter management
 * - Real-time search
 * - Activity logging
 */

class SolicitationsList {
    constructor() {
        this.currentView = 'cards';
        this.filters = this.getFiltersFromURL();
        this.searchTimeout = null;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupList());
        } else {
            this.setupList();
        }
    }

    setupList() {
        // Set up view toggles
        this.setupViewToggles();
        
        // Set up export functionality
        this.setupExportFunctionality();
        
        // Set up real-time search
        this.setupRealTimeSearch();
        
        // Set up filter management
        this.setupFilterManagement();
        
        // Set up activity logging
        this.setupActivityLogging();
        
        console.log('✅ Solicitations list initialized');
    }

    setupViewToggles() {
        const viewToggleBtns = document.querySelectorAll('.view-toggle');
        const cardsView = document.getElementById('cards-view');
        const listView = document.getElementById('list-view');

        viewToggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                this.switchView(view);
            });
        });
    }

    switchView(view) {
        const cardsView = document.getElementById('cards-view');
        const listView = document.getElementById('list-view');
        const viewToggleBtns = document.querySelectorAll('.view-toggle');

        // Update button states
        viewToggleBtns.forEach(btn => {
            if (btn.dataset.view === view) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Switch views
        if (view === 'list') {
            cardsView.style.display = 'none';
            listView.style.display = 'block';
            this.currentView = 'list';
        } else {
            cardsView.style.display = 'grid';
            listView.style.display = 'none';
            this.currentView = 'cards';
        }

        // Log activity
        this.logActivity('view_changed', {
            message: `Switched to ${view} view`,
            view: view
        });

        // Save preference to localStorage
        localStorage.setItem('solicitations_view', view);
    }

    setupExportFunctionality() {
        const exportBtn = document.getElementById('export-results');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportResults());
        }
    }

    async exportResults() {
        try {
            // Show loading state
            this.showLoadingState('export-results', 'Exporting...');

            // Build export URL with current filters
            const params = new URLSearchParams(this.filters);
            params.append('export', 'csv');

            const response = await fetch(`/api/export/solicitations?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `solicitations_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Log activity
            this.logActivity('export_completed', {
                message: 'Exported solicitations to CSV',
                export_format: 'csv',
                filters: this.filters
            });

            this.showToast('Export completed successfully', 'success');

        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Export failed. Please try again.', 'error');
        } finally {
            this.hideLoadingState('export-results', 'Export CSV');
        }
    }

    setupRealTimeSearch() {
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.updateFilters();
                }, 500);
            });
        }
    }

    setupFilterManagement() {
        const form = document.querySelector('.filters-form');
        if (form) {
            // Handle form submission
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updateFilters();
            });

            // Handle filter changes
            const filterInputs = form.querySelectorAll('select, input');
            filterInputs.forEach(input => {
                if (input.type !== 'text') { // Don't auto-submit on text input
                    input.addEventListener('change', () => {
                        this.updateFilters();
                    });
                }
            });
        }
    }

    updateFilters() {
        const form = document.querySelector('.filters-form');
        if (!form) return;

        const formData = new FormData(form);
        const newFilters = {};

        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                newFilters[key] = value.trim();
            }
        }

        // Update URL and reload
        const params = new URLSearchParams(newFilters);
        const newURL = window.location.pathname + '?' + params.toString();
        
        // Log activity
        this.logActivity('filters_applied', {
            message: 'Applied new filters',
            filters: newFilters
        });

        window.location.href = newURL;
    }

    setupActivityLogging() {
        // Log page view
        this.logActivity('page_view', {
            message: 'Viewed solicitations list',
            total_count: window.sfbaiContext?.total_opportunities || 0,
            filters: this.filters
        });

        // Track solicitation clicks
        document.querySelectorAll('.solicitation-card a, .solicitation-title-table a').forEach(link => {
            link.addEventListener('click', () => {
                const title = link.textContent.trim();
                this.logActivity('solicitation_clicked', {
                    message: `Viewed solicitation: ${title}`,
                    solicitation_title: title,
                    link_url: link.href
                });
            });
        });
    }

    getFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);
        const filters = {};
        
        for (let [key, value] of params.entries()) {
            if (value) {
                filters[key] = value;
            }
        }
        
        return filters;
    }

    async logActivity(activityType, data) {
        try {
            const response = await fetch('/api/activity/log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    entity_type: 'solicitations',
                    entity_id: 'list',
                    activity_type: activityType,
                    activity_data: data
                })
            });

            if (!response.ok) {
                console.warn('Failed to log activity:', activityType);
            }

        } catch (error) {
            console.warn('Activity logging failed:', error);
        }
    }

    showLoadingState(buttonId, loadingText) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = loadingText;
            button.classList.add('loading');
        }
    }

    hideLoadingState(buttonId, originalText) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = false;
            button.textContent = originalText || button.dataset.originalText || button.textContent;
            button.classList.remove('loading');
        }
    }

    showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        `;

        switch (type) {
            case 'success':
                toast.style.backgroundColor = '#14B8A6';
                break;
            case 'error':
                toast.style.backgroundColor = '#F43F5E';
                break;
            default:
                toast.style.backgroundColor = '#5B708B';
        }

        document.body.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Cleanup method
    destroy() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        console.log('Solicitations list destroyed');
    }
}

// Initialize solicitations list
let solicitationsList;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        solicitationsList = new SolicitationsList();
        
        // Restore view preference
        const savedView = localStorage.getItem('solicitations_view');
        if (savedView) {
            solicitationsList.switchView(savedView);
        }
    });
} else {
    solicitationsList = new SolicitationsList();
    
    // Restore view preference
    const savedView = localStorage.getItem('solicitations_view');
    if (savedView) {
        solicitationsList.switchView(savedView);
    }
}

// Export for global access
window.SolicitationsList = SolicitationsList;
window.solicitationsList = solicitationsList;

console.log('✅ Solicitations list script loaded');