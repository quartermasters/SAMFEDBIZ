/**
 * Holder Profile JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Email drafting with AI assistance
 * - Google Calendar meeting scheduling
 * - Activity logging
 * - Print functionality
 * - Export capabilities
 */

class HolderProfile {
    constructor() {
        this.holderContext = window.sfbaiContext || {};
        this.holderId = this.holderContext.holder_id;
        this.holderName = this.holderContext.holder_name;
        this.program = this.holderContext.program;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupProfile());
        } else {
            this.setupProfile();
        }
    }

    setupProfile() {
        // Set up action buttons
        this.setupActionButtons();
        
        // Set up catalog/capability interactions
        this.setupCatalogInteractions();
        
        // Set up activity logging
        this.setupActivityLogging();
        
        // Set up print functionality
        this.setupPrintFunctionality();
        
        // Set up export functionality
        this.setupExportFunctionality();
        
        console.log('✅ Holder profile initialized');
    }

    setupActionButtons() {
        // Draft email button
        const draftEmailBtn = document.getElementById('draft-email-btn');
        if (draftEmailBtn) {
            draftEmailBtn.addEventListener('click', () => this.draftEmail());
        }

        // Schedule meeting button
        const scheduleMeetingBtn = document.getElementById('schedule-meeting-btn');
        if (scheduleMeetingBtn) {
            scheduleMeetingBtn.addEventListener('click', () => this.scheduleMeeting());
        }

        // Add note button
        const addNoteBtn = document.getElementById('add-note-btn');
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', () => this.addNote());
        }
    }

    setupCatalogInteractions() {
        // Request info buttons for capabilities
        document.querySelectorAll('.request-info-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const capability = e.target.dataset.capability;
                this.requestCapabilityInfo(capability);
            });
        });

        // Catalog item interactions
        document.querySelectorAll('.catalog-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectCatalogItem(item);
            });
        });
    }

    setupActivityLogging() {
        // Log page view
        this.logActivity('page_view', {
            message: `Viewed ${this.holderName} profile`,
            holder_id: this.holderId,
            program: this.program
        });
    }

    setupPrintFunctionality() {
        const printBtn = document.getElementById('print-catalog-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => this.printProfile());
        }
    }

    setupExportFunctionality() {
        const exportBtn = document.getElementById('export-catalog');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportCatalog());
        }
    }

    async draftEmail() {
        try {
            this.showLoadingState('draft-email-btn', 'Drafting...');

            // Create email context with holder and program details
            const emailContext = {
                ...this.holderContext,
                action: 'outreach',
                template_type: 'initial_contact',
                word_limit: 150 // 120-150 word requirement
            };

            const response = await fetch('/api/ai/draft_email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify(emailContext)
            });

            if (!response.ok) {
                throw new Error('Failed to draft email');
            }

            const result = await response.json();
            
            // Show email draft modal or copy to clipboard
            this.showEmailDraft(result.draft, result.subject);
            
            // Log activity
            this.logActivity('email_drafted', {
                message: `Drafted email to ${this.holderName}`,
                holder_id: this.holderId,
                email_subject: result.subject
            });

            this.showToast('Email draft generated successfully', 'success');

        } catch (error) {
            console.error('Email draft failed:', error);
            this.showToast('Failed to draft email. Please try again.', 'error');
        } finally {
            this.hideLoadingState('draft-email-btn', 'Draft Email');
        }
    }

    async scheduleMeeting() {
        try {
            this.showLoadingState('schedule-meeting-btn', 'Scheduling...');

            // Create meeting context
            const meetingContext = {
                ...this.holderContext,
                title: `Meeting with ${this.holderName}`,
                description: `15-minute introductory call to discuss ${this.program.toUpperCase()} opportunities`,
                duration: 15,
                type: 'introduction'
            };

            const response = await fetch('/api/calendar/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify(meetingContext)
            });

            if (!response.ok) {
                throw new Error('Failed to schedule meeting');
            }

            const result = await response.json();
            
            // Log the gcal_event_id to database
            await this.logActivity('meeting_scheduled', {
                message: `Scheduled meeting with ${this.holderName}`,
                holder_id: this.holderId,
                gcal_event_id: result.event_id,
                meeting_link: result.meeting_link
            });

            this.showToast('Meeting scheduled successfully', 'success');
            
            // Optionally open calendar link
            if (result.calendar_link) {
                window.open(result.calendar_link, '_blank', 'noopener');
            }

        } catch (error) {
            console.error('Meeting scheduling failed:', error);
            this.showToast('Failed to schedule meeting. Please try again.', 'error');
        } finally {
            this.hideLoadingState('schedule-meeting-btn', 'Schedule Meeting');
        }
    }

    async addNote() {
        const noteText = prompt('Add a note about ' + this.holderName + ':');
        if (!noteText || !noteText.trim()) return;

        try {
            await this.logActivity('note_added', {
                message: noteText.trim(),
                holder_id: this.holderId,
                note_type: 'manual'
            });

            this.showToast('Note added successfully', 'success');
            
            // Refresh page to show new activity
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            console.error('Failed to add note:', error);
            this.showToast('Failed to add note. Please try again.', 'error');
        }
    }

    async requestCapabilityInfo(capability) {
        try {
            // Use SFBAI to get capability information
            if (window.sfbaiChat) {
                const message = `Tell me more about ${this.holderName}'s ${capability} capabilities`;
                window.sfbaiChat.chatInput.value = message;
                window.sfbaiChat.sendMessage();
                
                // Focus the chat
                document.querySelector('.sfbai-chatbox').scrollIntoView({ behavior: 'smooth' });
            }

            // Log activity
            this.logActivity('capability_info_requested', {
                message: `Requested info about ${capability} capability`,
                holder_id: this.holderId,
                capability: capability
            });

        } catch (error) {
            console.error('Failed to request capability info:', error);
        }
    }

    selectCatalogItem(item) {
        // Remove previous selections
        document.querySelectorAll('.catalog-item.selected').forEach(el => {
            el.classList.remove('selected');
        });

        // Add selection to clicked item
        item.classList.add('selected');

        // Get part number or capability name
        const partNumber = item.querySelector('.catalog-part-number')?.textContent ||
                          item.querySelector('.capability-title')?.textContent;

        if (partNumber) {
            // Log selection
            this.logActivity('catalog_item_selected', {
                message: `Viewed details for ${partNumber}`,
                holder_id: this.holderId,
                part_number: partNumber
            });
        }
    }

    printProfile() {
        // Add print-specific class to body
        document.body.classList.add('printing');
        
        // Log activity
        this.logActivity('profile_printed', {
            message: `Printed ${this.holderName} profile`,
            holder_id: this.holderId
        });

        // Trigger print
        window.print();

        // Remove print class after printing
        setTimeout(() => {
            document.body.classList.remove('printing');
        }, 1000);
    }

    async exportCatalog() {
        try {
            const response = await fetch(`/api/export/catalog?holder_id=${this.holderId}&program=${this.program}`, {
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
            a.download = `${this.holderName.replace(/[^a-zA-Z0-9]/g, '_')}_catalog_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Log activity
            this.logActivity('catalog_exported', {
                message: `Exported ${this.holderName} catalog`,
                holder_id: this.holderId,
                export_format: 'csv'
            });

            this.showToast('Catalog exported successfully', 'success');

        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Export failed. Please try again.', 'error');
        }
    }

    showEmailDraft(draftContent, subject) {
        // For now, copy to clipboard and show notification
        // In a full implementation, this would open an email modal
        
        const fullDraft = `Subject: ${subject}\n\n${draftContent}`;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(fullDraft).then(() => {
                this.showToast(`Email draft copied to clipboard`, 'success');
            }).catch(() => {
                this.showToast('Failed to copy draft to clipboard', 'error');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = fullDraft;
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showToast('Email draft copied to clipboard', 'success');
            } catch (err) {
                this.showToast('Failed to copy draft to clipboard', 'error');
            }
            
            document.body.removeChild(textArea);
        }
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
                    entity_type: 'holder',
                    entity_id: this.holderId,
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
        console.log('Holder profile destroyed');
    }
}

// Initialize holder profile
let holderProfile;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        holderProfile = new HolderProfile();
    });
} else {
    holderProfile = new HolderProfile();
}

// Export for global access
window.HolderProfile = HolderProfile;
window.holderProfile = holderProfile;

console.log('✅ Holder profile script loaded');