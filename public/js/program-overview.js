/**
 * Program Overview JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Table sorting without layout shift
 * - Context injection for SFBAI
 * - Export functionality
 * - Email drafting
 */

class ProgramOverview {
    constructor() {
        this.currentSort = { column: null, direction: 'asc' };
        this.originalTableHTML = '';
        this.programContext = window.sfbaiContext || {};
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupOverview());
        } else {
            this.setupOverview();
        }
    }

    setupOverview() {
        // Store original table HTML for sorting
        const table = document.querySelector('.data-table tbody');
        if (table) {
            this.originalTableHTML = table.innerHTML;
        }

        // Set up table sorting
        this.setupTableSorting();
        
        // Set up export functionality
        this.setupExport();
        
        // Set up email drafting
        this.setupEmailDrafting();
        
        // Update SFBAI context
        this.updateSFBAIContext();
        
        console.log('✅ Program overview initialized');
    }

    setupTableSorting() {
        const sortButtons = document.querySelectorAll('.table-sort');
        
        sortButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const column = button.dataset.column;
                this.sortTable(column);
            });
        });
    }

    sortTable(column) {
        const tbody = document.querySelector('.data-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr:not(.no-data)'));
        
        if (rows.length === 0) return;

        // Determine sort direction
        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.column = column;
            this.currentSort.direction = 'asc';
        }

        // Sort rows
        const sortedRows = this.sortRows(rows, column, this.currentSort.direction);
        
        // Prevent layout shift by preserving table dimensions
        const tableContainer = document.querySelector('.table-container');
        const originalHeight = tableContainer.offsetHeight;
        
        // Update table content without layout shift
        tbody.innerHTML = '';
        sortedRows.forEach(row => tbody.appendChild(row));
        
        // Ensure table height remains consistent
        requestAnimationFrame(() => {
            if (Math.abs(tableContainer.offsetHeight - originalHeight) > 5) {
                tableContainer.style.minHeight = originalHeight + 'px';
                setTimeout(() => {
                    tableContainer.style.minHeight = '';
                }, 300);
            }
        });

        // Update sort indicators
        this.updateSortIndicators(column, this.currentSort.direction);
        
        // Announce to screen readers
        this.announceSortChange(column, this.currentSort.direction);
    }

    sortRows(rows, column, direction) {
        return rows.sort((a, b) => {
            let aValue = this.getCellValue(a, column);
            let bValue = this.getCellValue(b, column);
            
            // Handle different data types
            if (column === 'name') {
                aValue = aValue.toLowerCase();
                bValue = bValue.toLowerCase();
            } else if (column === 'contract') {
                // Sort contract numbers alphanumerically
                aValue = aValue.replace(/[^a-zA-Z0-9]/g, '');
                bValue = bValue.replace(/[^a-zA-Z0-9]/g, '');
            }
            
            let comparison = 0;
            if (aValue < bValue) {
                comparison = -1;
            } else if (aValue > bValue) {
                comparison = 1;
            }
            
            return direction === 'desc' ? comparison * -1 : comparison;
        });
    }

    getCellValue(row, column) {
        const columnMap = {
            'name': 0,
            'location': 1,
            'contract': 2,
            'status': 3
        };
        
        // Adjust indices if this is TLS (no contract column)
        const isTLS = !row.querySelector('code.contract-number');
        if (isTLS && column === 'status') {
            columnMap.status = 2;
        }
        
        const cellIndex = columnMap[column];
        if (cellIndex === undefined) return '';
        
        const cell = row.cells[cellIndex];
        if (!cell) return '';
        
        // Extract text content, handling special cases
        if (column === 'name') {
            const nameElement = cell.querySelector('.holder-name');
            return nameElement ? nameElement.textContent.trim() : cell.textContent.trim();
        } else if (column === 'contract') {
            const contractElement = cell.querySelector('.contract-number');
            return contractElement ? contractElement.textContent.trim() : cell.textContent.trim();
        } else if (column === 'status') {
            const statusElement = cell.querySelector('.status-badge');
            return statusElement ? statusElement.textContent.trim() : cell.textContent.trim();
        }
        
        return cell.textContent.trim();
    }

    updateSortIndicators(activeColumn, direction) {
        // Reset all indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.className = 'sort-indicator';
            indicator.textContent = '';
        });
        
        // Set active indicator
        const activeButton = document.querySelector(`[data-column="${activeColumn}"]`);
        if (activeButton) {
            const indicator = activeButton.querySelector('.sort-indicator');
            indicator.className = `sort-indicator sort-${direction}`;
            indicator.textContent = direction === 'asc' ? '↑' : '↓';
            indicator.setAttribute('aria-label', `Sorted ${direction === 'asc' ? 'ascending' : 'descending'}`);
        }
    }

    announceSortChange(column, direction) {
        const columnNames = {
            'name': 'company name',
            'location': 'location',
            'contract': 'contract number',
            'status': 'status'
        };
        
        const message = `Table sorted by ${columnNames[column]} in ${direction === 'asc' ? 'ascending' : 'descending'} order`;
        
        if (window.accessibilityManager) {
            window.accessibilityManager.announce(message);
        }
    }

    setupExport() {
        const exportButton = document.getElementById('export-holders');
        if (!exportButton) return;
        
        exportButton.addEventListener('click', () => this.exportToCSV());
    }

    async exportToCSV() {
        try {
            const response = await fetch(`/api/export/holders?program=${this.programContext.program}`, {
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
            a.download = `${this.programContext.program}_holders_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showToast('Export completed successfully', 'success');
        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Export failed. Please try again.', 'error');
        }
    }

    setupEmailDrafting() {
        const draftButtons = document.querySelectorAll('.draft-email-btn');
        
        draftButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const holderId = button.dataset.holderId;
                this.draftEmail(holderId);
            });
        });
    }

    async draftEmail(holderId) {
        try {
            // Get holder information
            const holderRow = document.querySelector(`[data-holder-id="${holderId}"]`).closest('tr');
            const holderName = holderRow.querySelector('.holder-name').textContent.trim();
            
            // Create draft email context
            const emailContext = {
                ...this.programContext,
                holder_id: holderId,
                holder_name: holderName,
                action: 'initial_outreach'
            };
            
            // Call AI draft endpoint
            const response = await fetch('/api/ai/draft_email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify(emailContext)
            });
            
            if (!response.ok) {
                throw new Error('Draft failed');
            }
            
            const result = await response.json();
            
            // Open email draft modal or redirect to email composer
            this.openEmailDraft(result.draft, holderName);
            
        } catch (error) {
            console.error('Email draft failed:', error);
            this.showToast('Failed to draft email. Please try again.', 'error');
        }
    }

    openEmailDraft(draftContent, holderName) {
        // For now, copy to clipboard and show notification
        // In a full implementation, this would open an email modal
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(draftContent).then(() => {
                this.showToast(`Email draft for ${holderName} copied to clipboard`, 'success');
            }).catch(() => {
                this.showToast('Failed to copy draft to clipboard', 'error');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = draftContent;
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showToast(`Email draft for ${holderName} copied to clipboard`, 'success');
            } catch (err) {
                this.showToast('Failed to copy draft to clipboard', 'error');
            }
            
            document.body.removeChild(textArea);
        }
    }

    updateSFBAIContext() {
        // Update SFBAI chat context with program-specific information
        if (window.sfbaiChat) {
            window.sfbaiChat.setContext(this.programContext);
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

    // Handle responsive table behavior
    handleResize() {
        const table = document.querySelector('.data-table');
        const container = document.querySelector('.table-container');
        
        if (window.innerWidth < 768) {
            // Mobile view - make table scrollable horizontally
            container.style.overflowX = 'auto';
            table.style.minWidth = '600px';
        } else {
            // Desktop view - normal layout
            container.style.overflowX = 'visible';
            table.style.minWidth = 'auto';
        }
    }

    // Cleanup method
    destroy() {
        console.log('Program overview destroyed');
    }
}

// Initialize program overview
let programOverview;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        programOverview = new ProgramOverview();
    });
} else {
    programOverview = new ProgramOverview();
}

// Handle window resize
window.addEventListener('resize', () => {
    if (programOverview) {
        programOverview.handleResize();
    }
});

// Export for global access
window.ProgramOverview = ProgramOverview;
window.programOverview = programOverview;

console.log('✅ Program overview script loaded');