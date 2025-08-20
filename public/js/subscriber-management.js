/**
 * Subscriber Management JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Bulk selection and actions
 * - Export functionality
 * - Real-time filtering
 */

class SubscriberManagement {
    constructor() {
        this.selectedSubscribers = new Set();
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSubscriberManagement());
        } else {
            this.setupSubscriberManagement();
        }
    }

    setupSubscriberManagement() {
        // Set up bulk selection
        this.setupBulkSelection();
        
        // Set up bulk actions
        this.setupBulkActions();
        
        // Set up export functionality
        this.setupExportFunctionality();
        
        // Set up table interactions
        this.setupTableInteractions();
        
        console.log('✅ Subscriber management initialized');
    }

    setupBulkSelection() {
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const selectAllBtn = document.getElementById('select-all-btn');
        const subscriberCheckboxes = document.querySelectorAll('.subscriber-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                subscriberCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                    const subscriberId = parseInt(checkbox.value);
                    if (isChecked) {
                        this.selectedSubscribers.add(subscriberId);
                    } else {
                        this.selectedSubscribers.delete(subscriberId);
                    }
                });
                this.updateBulkActionsState();
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = !selectAllCheckbox.checked;
                    selectAllCheckbox.dispatchEvent(new Event('change'));
                }
            });
        }

        // Individual checkbox handlers
        subscriberCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const subscriberId = parseInt(e.target.value);
                if (e.target.checked) {
                    this.selectedSubscribers.add(subscriberId);
                } else {
                    this.selectedSubscribers.delete(subscriberId);
                }

                // Update select all checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(subscriberCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(subscriberCheckboxes).every(cb => !cb.checked);
                    
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
                }

                this.updateBulkActionsState();
            });
        });
    }

    setupBulkActions() {
        const bulkActionsBtn = document.getElementById('bulk-actions-btn');
        if (bulkActionsBtn) {
            bulkActionsBtn.addEventListener('click', () => this.showBulkActionsModal());
        }
    }

    showBulkActionsModal() {
        if (this.selectedSubscribers.size === 0) {
            this.showToast('Please select subscribers first', 'warning');
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'bulk-actions-modal-overlay';
        modal.innerHTML = `
            <div class="bulk-actions-modal">
                <div class="bulk-actions-header">
                    <h3>Bulk Actions</h3>
                    <span class="selected-count">${this.selectedSubscribers.size} subscriber(s) selected</span>
                    <button class="bulk-actions-close" aria-label="Close">&times;</button>
                </div>
                <div class="bulk-actions-content">
                    <div class="bulk-action-grid">
                        <button class="bulk-action-btn activate" data-action="activate">
                            <span class="bulk-action-icon">✅</span>
                            <span class="bulk-action-text">Activate Selected</span>
                        </button>
                        
                        <button class="bulk-action-btn deactivate" data-action="deactivate">
                            <span class="bulk-action-icon">🚫</span>
                            <span class="bulk-action-text">Deactivate Selected</span>
                        </button>
                        
                        <button class="bulk-action-btn verify" data-action="verify">
                            <span class="bulk-action-icon">✔️</span>
                            <span class="bulk-action-text">Verify Selected</span>
                        </button>
                        
                        <button class="bulk-action-btn export" data-action="export">
                            <span class="bulk-action-icon">📁</span>
                            <span class="bulk-action-text">Export Selected</span>
                        </button>
                        
                        <button class="bulk-action-btn delete" data-action="delete">
                            <span class="bulk-action-icon">🗑️</span>
                            <span class="bulk-action-text">Delete Selected</span>
                        </button>
                    </div>
                </div>
                <div class="bulk-actions-footer">
                    <button class="btn-text bulk-actions-close">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Set up modal interactions
        const closeButtons = modal.querySelectorAll('.bulk-actions-close');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                document.body.removeChild(modal);
            });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });

        // Set up action buttons
        const actionButtons = modal.querySelectorAll('.bulk-action-btn');
        actionButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.currentTarget.dataset.action;
                document.body.removeChild(modal);
                this.performBulkAction(action);
            });
        });
    }

    async performBulkAction(action) {
        const subscriberIds = Array.from(this.selectedSubscribers);
        
        if (action === 'delete') {
            const confirmDelete = confirm(`Are you sure you want to delete ${subscriberIds.length} subscriber(s)? This action cannot be undone.`);
            if (!confirmDelete) return;
        }

        if (action === 'export') {
            this.exportSelectedSubscribers(subscriberIds);
            return;
        }

        try {
            const response = await fetch('/api/subscribers/bulk-action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    action: action,
                    subscriber_ids: subscriberIds
                })
            });

            if (!response.ok) {
                throw new Error('Bulk action failed');
            }

            const result = await response.json();
            
            this.showToast(`Successfully ${action}d ${result.affected_count} subscriber(s)`, 'success');
            
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            console.error('Bulk action error:', error);
            this.showToast(`Failed to ${action} subscribers. Please try again.`, 'error');
        }
    }

    async exportSelectedSubscribers(subscriberIds) {
        try {
            const response = await fetch('/api/subscribers/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    subscriber_ids: subscriberIds
                })
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            // Download the CSV file
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `selected_subscribers_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showToast(`Exported ${subscriberIds.length} subscribers`, 'success');

        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed. Please try again.', 'error');
        }
    }

    setupExportFunctionality() {
        const exportSelectedBtn = document.getElementById('export-selected-btn');
        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', () => {
                if (this.selectedSubscribers.size === 0) {
                    this.showToast('Please select subscribers to export', 'warning');
                    return;
                }
                
                const subscriberIds = Array.from(this.selectedSubscribers);
                this.exportSelectedSubscribers(subscriberIds);
            });
        }
    }

    setupTableInteractions() {
        // Add hover effects and accessibility improvements
        const tableRows = document.querySelectorAll('.subscriber-row');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.classList.add('hovered');
            });
            
            row.addEventListener('mouseleave', () => {
                row.classList.remove('hovered');
            });
        });

        // Handle form submissions with confirmation
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const subscriberRow = e.target.closest('.subscriber-row');
                const email = subscriberRow.querySelector('.email-text').textContent;
                
                if (!confirm(`Are you sure you want to delete subscriber "${email}"? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            });
        });

        // Handle verification resend with feedback
        const resendButtons = document.querySelectorAll('.btn-resend');
        resendButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const subscriberRow = e.target.closest('.subscriber-row');
                const email = subscriberRow.querySelector('.email-text').textContent;
                
                // Show immediate feedback
                btn.textContent = 'Sending...';
                btn.disabled = true;
                
                // Note: The actual form submission will handle the server-side logic
                // This is just for immediate UI feedback
                setTimeout(() => {
                    this.showToast(`Verification email sent to ${email}`, 'success');
                }, 500);
            });
        });
    }

    updateBulkActionsState() {
        const bulkActionsBtn = document.getElementById('bulk-actions-btn');
        const exportSelectedBtn = document.getElementById('export-selected-btn');
        
        const hasSelections = this.selectedSubscribers.size > 0;
        
        if (bulkActionsBtn) {
            bulkActionsBtn.disabled = !hasSelections;
            bulkActionsBtn.textContent = hasSelections 
                ? `Bulk Actions (${this.selectedSubscribers.size})` 
                : 'Bulk Actions';
        }
        
        if (exportSelectedBtn) {
            exportSelectedBtn.disabled = !hasSelections;
            exportSelectedBtn.textContent = hasSelections 
                ? `Export Selected (${this.selectedSubscribers.size})` 
                : 'Export Selected';
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
            case 'warning':
                toast.style.backgroundColor = '#F59E0B';
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
        this.selectedSubscribers.clear();
        console.log('Subscriber management destroyed');
    }
}

// Initialize subscriber management
let subscriberManagement;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        subscriberManagement = new SubscriberManagement();
    });
} else {
    subscriberManagement = new SubscriberManagement();
}

// Export for global access
window.SubscriberManagement = SubscriberManagement;
window.subscriberManagement = subscriberManagement;

console.log('✅ Subscriber management script loaded');