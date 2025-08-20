/**
 * Daily Briefs JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Brief building and preview
 * - Send/resend functionality
 * - Archive management
 * - Subscriber management
 * - Activity logging
 */

class DailyBriefs {
    constructor() {
        this.briefsData = window.briefsData || {};
        this.nextSendTime = this.briefsData.next_send_time;
        this.subscriberCount = this.briefsData.subscriber_count;
        this.todayBriefSent = this.briefsData.today_brief_sent;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupBriefs());
        } else {
            this.setupBriefs();
        }
    }

    setupBriefs() {
        // Set up brief building
        this.setupBriefBuilder();
        
        // Set up send/resend functionality
        this.setupSendFunctionality();
        
        // Set up subscriber management
        this.setupSubscriberManagement();
        
        // Set up filter management
        this.setupFilterManagement();
        
        // Set up activity logging
        this.setupActivityLogging();
        
        // Set up countdown timer
        this.setupCountdownTimer();
        
        console.log('✅ Daily briefs initialized');
    }

    setupBriefBuilder() {
        const buildBtn = document.getElementById('build-brief-btn');
        const previewBtn = document.getElementById('preview-brief-btn');

        if (buildBtn) {
            buildBtn.addEventListener('click', () => this.buildBrief());
        }
        
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this.previewBrief());
        }
    }

    async buildBrief() {
        try {
            this.showLoadingState('build-brief-btn', 'Building...');

            const response = await fetch('/api/briefs/build', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    force_build: true,
                    include_all_programs: true
                })
            });

            if (!response.ok) {
                throw new Error('Brief building failed');
            }

            const result = await response.json();
            
            // Show success and offer to send
            this.showBuildSuccess(result);
            
            // Log activity
            this.logActivity('brief_built', {
                message: 'Built daily brief manually',
                brief_id: result.brief_id,
                sections_count: result.sections_count || 0,
                headlines_count: result.headlines_count || 0
            });

            this.showToast('Brief built successfully', 'success');

        } catch (error) {
            console.error('Brief building failed:', error);
            this.showToast('Failed to build brief. Please try again.', 'error');
        } finally {
            this.hideLoadingState('build-brief-btn', 'Build Brief Now');
        }
    }

    async previewBrief() {
        try {
            this.showLoadingState('preview-brief-btn', 'Generating...');

            const response = await fetch('/api/briefs/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    include_all_programs: true
                })
            });

            if (!response.ok) {
                throw new Error('Brief preview failed');
            }

            const result = await response.json();
            
            // Show preview modal
            this.showBriefPreview(result);
            
            // Log activity
            this.logActivity('brief_previewed', {
                message: 'Generated brief preview',
                sections_count: result.sections?.length || 0
            });

        } catch (error) {
            console.error('Brief preview failed:', error);
            this.showToast('Failed to generate preview. Please try again.', 'error');
        } finally {
            this.hideLoadingState('preview-brief-btn', 'Preview');
        }
    }

    showBuildSuccess(result) {
        // Update the today's brief status to show the new brief
        const statusCard = document.querySelector('.brief-status-card');
        if (statusCard && result.brief_id) {
            // Show option to send immediately
            const confirmSend = confirm(`Brief built successfully with ${result.sections_count || 0} sections. Send to ${this.subscriberCount} subscribers now?`);
            
            if (confirmSend) {
                this.sendBrief(result.brief_id);
            } else {
                // Refresh page to show new draft
                setTimeout(() => window.location.reload(), 1000);
            }
        }
    }

    showBriefPreview(preview) {
        // Create preview modal
        const modal = document.createElement('div');
        modal.className = 'brief-preview-modal-overlay';
        modal.innerHTML = `
            <div class="brief-preview-modal">
                <div class="brief-preview-header">
                    <h3>Brief Preview</h3>
                    <button class="brief-preview-close" aria-label="Close">&times;</button>
                </div>
                <div class="brief-preview-content">
                    <div class="brief-preview-meta">
                        <span class="preview-date">${new Date().toLocaleDateString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        })}</span>
                        <span class="preview-sections">${preview.sections?.length || 0} sections</span>
                    </div>
                    <div class="brief-preview-body">
                        ${this.formatBriefContent(preview)}
                    </div>
                </div>
                <div class="brief-preview-actions">
                    <button class="btn-primary" id="build-and-send-btn">Build & Send</button>
                    <button class="btn-secondary" id="build-only-btn">Build Only</button>
                    <button class="btn-text brief-preview-close">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Set up modal interactions
        const closeButtons = modal.querySelectorAll('.brief-preview-close');
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
        const buildAndSendBtn = modal.querySelector('#build-and-send-btn');
        const buildOnlyBtn = modal.querySelector('#build-only-btn');

        buildAndSendBtn.addEventListener('click', async () => {
            document.body.removeChild(modal);
            await this.buildBrief();
        });

        buildOnlyBtn.addEventListener('click', async () => {
            document.body.removeChild(modal);
            await this.buildBrief();
        });
    }

    formatBriefContent(preview) {
        let content = '<div class="brief-sections">';
        
        if (preview.sections) {
            preview.sections.forEach(section => {
                content += `
                    <div class="brief-section">
                        <h4 class="brief-section-title">${section.title}</h4>
                        <div class="brief-section-content">
                            ${section.content || 'No content available'}
                        </div>
                        ${section.signals_and_rumors ? `
                            <div class="brief-signals">
                                <strong>Signals & Rumors:</strong>
                                <p>${section.signals_and_rumors}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
        }
        
        content += '</div>';
        return content;
    }

    setupSendFunctionality() {
        // Resend buttons for individual briefs
        document.querySelectorAll('.resend-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const briefId = e.target.dataset.briefId;
                this.resendBrief(briefId);
            });
        });

        // Resend today's brief
        const resendTodayBtn = document.getElementById('resend-brief-btn');
        if (resendTodayBtn) {
            resendTodayBtn.addEventListener('click', (e) => {
                const briefId = e.target.dataset.briefId;
                this.resendBrief(briefId);
            });
        }

        // Duplicate buttons
        document.querySelectorAll('.duplicate-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const briefId = e.target.dataset.briefId;
                this.duplicateBrief(briefId);
            });
        });
    }

    async sendBrief(briefId) {
        try {
            const response = await fetch('/api/briefs/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    brief_id: briefId
                })
            });

            if (!response.ok) {
                throw new Error('Brief sending failed');
            }

            const result = await response.json();
            
            // Log activity
            this.logActivity('brief_sent', {
                message: 'Sent daily brief',
                brief_id: briefId,
                recipient_count: result.recipient_count || 0,
                send_time: new Date().toISOString()
            });

            this.showToast(`Brief sent to ${result.recipient_count || 0} subscribers`, 'success');
            
            // Refresh page to show updated status
            setTimeout(() => window.location.reload(), 2000);

        } catch (error) {
            console.error('Brief sending failed:', error);
            this.showToast('Failed to send brief. Please try again.', 'error');
        }
    }

    async resendBrief(briefId) {
        const confirm = window.confirm(`Resend this brief to ${this.subscriberCount} subscribers?`);
        if (!confirm) return;

        try {
            const response = await fetch('/api/briefs/resend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    brief_id: briefId
                })
            });

            if (!response.ok) {
                throw new Error('Brief resending failed');
            }

            const result = await response.json();
            
            // Log activity
            this.logActivity('brief_resent', {
                message: 'Resent daily brief',
                brief_id: briefId,
                recipient_count: result.recipient_count || 0
            });

            this.showToast(`Brief resent to ${result.recipient_count || 0} subscribers`, 'success');

        } catch (error) {
            console.error('Brief resending failed:', error);
            this.showToast('Failed to resend brief. Please try again.', 'error');
        }
    }

    setupSubscriberManagement() {
        const manageBtn = document.getElementById('manage-subscribers-btn');
        if (manageBtn) {
            manageBtn.addEventListener('click', () => this.showSubscriberManagement());
        }
    }

    showSubscriberManagement() {
        // Navigate to subscriber management page
        window.location.href = '/settings/subscribers';
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
                input.addEventListener('change', () => {
                    this.updateFilters();
                });
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
            message: 'Applied filters to brief archive',
            filters: newFilters
        });

        window.location.href = newURL;
    }

    setupCountdownTimer() {
        if (this.todayBriefSent || !this.nextSendTime) return;

        // Update countdown every minute
        setInterval(() => {
            this.updateCountdown();
        }, 60000);

        this.updateCountdown();
    }

    updateCountdown() {
        const nextSend = new Date(this.nextSendTime);
        const now = new Date();
        const diff = nextSend.getTime() - now.getTime();

        if (diff <= 0) {
            // Time has passed, refresh page
            window.location.reload();
            return;
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        const countdownElements = document.querySelectorAll('.countdown-timer');
        countdownElements.forEach(element => {
            element.textContent = `${hours}h ${minutes}m until next send`;
        });
    }

    setupActivityLogging() {
        // Log page view
        this.logActivity('page_view', {
            message: 'Viewed daily briefs archive',
            total_briefs: window.sfbaiContext?.total_briefs || 0,
            today_brief_sent: this.todayBriefSent
        });

        // Track brief link clicks
        document.querySelectorAll('.brief-item-title a').forEach(link => {
            link.addEventListener('click', () => {
                const title = link.textContent.trim();
                this.logActivity('brief_viewed', {
                    message: `Viewed brief: ${title}`,
                    brief_title: title,
                    link_url: link.href
                });
            });
        });
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
                    entity_type: 'briefs',
                    entity_id: 'archive',
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
        console.log('Daily briefs destroyed');
    }
}

// Initialize daily briefs
let dailyBriefs;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        dailyBriefs = new DailyBriefs();
    });
} else {
    dailyBriefs = new DailyBriefs();
}

// Export for global access
window.DailyBriefs = DailyBriefs;
window.dailyBriefs = dailyBriefs;

console.log('✅ Daily briefs script loaded');