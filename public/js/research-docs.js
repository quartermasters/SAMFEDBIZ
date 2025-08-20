/**
 * Research Documents JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Google Drive sync
 * - AI summarization
 * - Notes management
 * - Document preview
 * - Activity logging
 */

class ResearchDocs {
    constructor() {
        this.currentView = 'cards';
        this.filters = this.getFiltersFromURL();
        this.selectedDocs = new Set();
        this.searchTimeout = null;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupDocs());
        } else {
            this.setupDocs();
        }
    }

    setupDocs() {
        // Set up view toggles
        this.setupViewToggles();
        
        // Set up Google Drive sync
        this.setupDriveSync();
        
        // Set up AI summarization
        this.setupAISummarization();
        
        // Set up notes management
        this.setupNotesManagement();
        
        // Set up document selection
        this.setupDocumentSelection();
        
        // Set up filter management
        this.setupFilterManagement();
        
        // Set up activity logging
        this.setupActivityLogging();
        
        console.log('✅ Research docs initialized');
    }

    setupViewToggles() {
        const viewToggleBtns = document.querySelectorAll('.view-toggle');
        const cardsView = document.getElementById('cards-view');

        viewToggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                this.switchView(view);
            });
        });
    }

    switchView(view) {
        const cardsView = document.getElementById('cards-view');
        const viewToggleBtns = document.querySelectorAll('.view-toggle');

        // Update button states
        viewToggleBtns.forEach(btn => {
            if (btn.dataset.view === view) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // For now, only cards view is implemented
        this.currentView = 'cards';

        // Log activity
        this.logActivity('view_changed', {
            message: `Switched to ${view} view`,
            view: view
        });

        // Save preference to localStorage
        localStorage.setItem('research_view', view);
    }

    setupDriveSync() {
        const syncBtn = document.getElementById('trigger-sync-btn');
        if (syncBtn) {
            syncBtn.addEventListener('click', () => this.triggerDriveSync());
        }
    }

    async triggerDriveSync() {
        try {
            this.showLoadingState('trigger-sync-btn', 'Syncing...');

            const response = await fetch('/api/drive/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    force_refresh: true
                })
            });

            if (!response.ok) {
                throw new Error('Drive sync failed');
            }

            const result = await response.json();
            
            // Update sync status banner
            this.updateSyncStatus(result);
            
            // Log activity
            this.logActivity('drive_sync_triggered', {
                message: 'Triggered Google Drive sync',
                documents_synced: result.documents_synced || 0,
                new_documents: result.new_documents || 0
            });

            this.showToast(`Drive sync completed - ${result.documents_synced || 0} documents synced`, 'success');
            
            // Refresh page after successful sync
            setTimeout(() => window.location.reload(), 2000);

        } catch (error) {
            console.error('Drive sync failed:', error);
            this.showToast('Drive sync failed. Please try again.', 'error');
        } finally {
            this.hideLoadingState('trigger-sync-btn', 'Sync Now');
        }
    }

    updateSyncStatus(result) {
        const syncBanner = document.querySelector('.sync-status-banner');
        if (!syncBanner) return;

        const statusElement = syncBanner.querySelector('.sync-status');
        const iconElement = statusElement.querySelector('.sync-status-icon svg');
        const textElement = statusElement.querySelector('.sync-status-text');
        const timeElement = statusElement.querySelector('.sync-status-time');

        // Update status class
        statusElement.className = `sync-status ${result.status}`;
        
        // Update text
        if (result.status === 'success') {
            textElement.textContent = `Drive sync successful - ${result.documents_synced} documents synced`;
        } else {
            textElement.textContent = `Drive sync failed - ${result.error}`;
        }
        
        // Update timestamp
        timeElement.textContent = new Date().toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    setupAISummarization() {
        // Individual document summarization
        document.querySelectorAll('.ai-summarize-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const docId = e.target.dataset.docId;
                this.summarizeDocument(docId);
            });
        });

        // Bulk summarization
        const bulkSummarizeBtn = document.getElementById('bulk-summarize-btn');
        if (bulkSummarizeBtn) {
            bulkSummarizeBtn.addEventListener('click', () => this.bulkSummarizeDocuments());
        }
    }

    async summarizeDocument(docId) {
        try {
            const btn = document.querySelector(`[data-doc-id="${docId}"].ai-summarize-btn`);
            if (btn) {
                this.showButtonLoading(btn, 'Summarizing...');
            }

            const response = await fetch('/api/ai/summarize_document', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    document_id: docId
                })
            });

            if (!response.ok) {
                throw new Error('Summarization failed');
            }

            const result = await response.json();
            
            // Show summary in modal or create note
            this.showSummaryResult(docId, result);
            
            // Log activity
            this.logActivity('document_summarized', {
                message: `Generated AI summary for document ${docId}`,
                document_id: docId,
                note_id: result.note_id,
                summary_length: result.summary?.length || 0
            });

            this.showToast('AI summary generated and saved as note', 'success');

        } catch (error) {
            console.error('Document summarization failed:', error);
            this.showToast('Failed to generate summary. Please try again.', 'error');
        } finally {
            const btn = document.querySelector(`[data-doc-id="${docId}"].ai-summarize-btn`);
            if (btn) {
                this.hideButtonLoading(btn, 'AI Summary');
            }
        }
    }

    async bulkSummarizeDocuments() {
        const selectedDocs = Array.from(this.selectedDocs);
        
        if (selectedDocs.length === 0) {
            this.showToast('Please select documents to summarize', 'warning');
            return;
        }

        if (selectedDocs.length > 10) {
            this.showToast('Please select 10 or fewer documents for bulk summarization', 'warning');
            return;
        }

        try {
            this.showLoadingState('bulk-summarize-btn', `Summarizing ${selectedDocs.length} docs...`);

            const response = await fetch('/api/ai/bulk_summarize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    document_ids: selectedDocs
                })
            });

            if (!response.ok) {
                throw new Error('Bulk summarization failed');
            }

            const result = await response.json();
            
            // Log activity
            this.logActivity('bulk_summarization', {
                message: `Generated AI summaries for ${selectedDocs.length} documents`,
                document_ids: selectedDocs,
                notes_created: result.notes_created || 0,
                errors: result.errors || 0
            });

            this.showToast(`Bulk summarization completed - ${result.notes_created} summaries created`, 'success');
            
            // Clear selection
            this.clearDocumentSelection();
            
            // Refresh page to show new notes
            setTimeout(() => window.location.reload(), 2000);

        } catch (error) {
            console.error('Bulk summarization failed:', error);
            this.showToast('Bulk summarization failed. Please try again.', 'error');
        } finally {
            this.hideLoadingState('bulk-summarize-btn', 'AI Summarize Selected');
        }
    }

    showSummaryResult(docId, result) {
        // For now, just show a toast. In a full implementation, this would show a modal
        this.showToast(`Summary saved as note with tags: ${result.tags?.join(', ') || 'document'}`, 'success');
    }

    setupNotesManagement() {
        document.querySelectorAll('.view-notes-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const docId = e.target.dataset.docId;
                this.viewDocumentNotes(docId);
            });
        });
    }

    async viewDocumentNotes(docId) {
        try {
            const response = await fetch(`/api/notes/document/${docId}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch notes');
            }

            const notes = await response.json();
            
            // Show notes in modal or sidebar
            this.showNotesModal(docId, notes);
            
            // Log activity
            this.logActivity('notes_viewed', {
                message: `Viewed notes for document ${docId}`,
                document_id: docId,
                notes_count: notes.length
            });

        } catch (error) {
            console.error('Failed to fetch notes:', error);
            this.showToast('Failed to load notes. Please try again.', 'error');
        }
    }

    showNotesModal(docId, notes) {
        // Create a simple modal to display notes
        const modal = document.createElement('div');
        modal.className = 'notes-modal-overlay';
        modal.innerHTML = `
            <div class="notes-modal">
                <div class="notes-modal-header">
                    <h3>Document Notes</h3>
                    <button class="notes-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="notes-modal-content">
                    ${notes.length > 0 ? notes.map(note => `
                        <div class="note-item">
                            <div class="note-content">${note.content}</div>
                            <div class="note-meta">
                                <span class="note-tags">${note.tags?.join(', ') || ''}</span>
                                <span class="note-date">${new Date(note.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    `).join('') : '<p>No notes found for this document.</p>'}
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close modal functionality
        const closeBtn = modal.querySelector('.notes-modal-close');
        closeBtn.addEventListener('click', () => {
            document.body.removeChild(modal);
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
    }

    setupDocumentSelection() {
        document.querySelectorAll('.research-select-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const docId = e.target.dataset.docId;
                if (e.target.checked) {
                    this.selectedDocs.add(docId);
                } else {
                    this.selectedDocs.delete(docId);
                }
                this.updateBulkActionButton();
            });
        });
    }

    updateBulkActionButton() {
        const bulkBtn = document.getElementById('bulk-summarize-btn');
        if (bulkBtn) {
            const count = this.selectedDocs.size;
            if (count > 0) {
                bulkBtn.textContent = `AI Summarize Selected (${count})`;
                bulkBtn.disabled = false;
            } else {
                bulkBtn.textContent = 'AI Summarize Selected';
                bulkBtn.disabled = true;
            }
        }
    }

    clearDocumentSelection() {
        this.selectedDocs.clear();
        document.querySelectorAll('.research-select-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateBulkActionButton();
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

            // Real-time search
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
            message: 'Applied new filters to research documents',
            filters: newFilters
        });

        window.location.href = newURL;
    }

    setupActivityLogging() {
        // Log page view
        this.logActivity('page_view', {
            message: 'Viewed research documents page',
            total_count: window.sfbaiContext?.total_documents || 0,
            filters: this.filters
        });

        // Track document link clicks
        document.querySelectorAll('.research-card-title a').forEach(link => {
            link.addEventListener('click', () => {
                const title = link.textContent.trim();
                this.logActivity('document_viewed', {
                    message: `Viewed document: ${title}`,
                    document_title: title,
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
                    entity_type: 'research',
                    entity_id: 'docs',
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

    showButtonLoading(button, loadingText) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = loadingText;
        button.classList.add('loading');
    }

    hideButtonLoading(button, originalText) {
        button.disabled = false;
        button.textContent = originalText || button.dataset.originalText || button.textContent;
        button.classList.remove('loading');
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
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        console.log('Research docs destroyed');
    }
}

// Initialize research docs
let researchDocs;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        researchDocs = new ResearchDocs();
        
        // Restore view preference
        const savedView = localStorage.getItem('research_view');
        if (savedView) {
            researchDocs.switchView(savedView);
        }
    });
} else {
    researchDocs = new ResearchDocs();
    
    // Restore view preference
    const savedView = localStorage.getItem('research_view');
    if (savedView) {
        researchDocs.switchView(savedView);
    }
}

// Export for global access
window.ResearchDocs = ResearchDocs;
window.researchDocs = researchDocs;

console.log('✅ Research docs script loaded');