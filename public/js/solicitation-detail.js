/**
 * Solicitation Detail JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - AI summary generation
 * - Compliance checklist management
 * - Next actions tracking
 * - Activity logging
 */

class SolicitationDetail {
    constructor() {
        this.solicitationData = window.solicitationData || {};
        this.oppNo = this.solicitationData.opp_no;
        this.title = this.solicitationData.title;
        this.program = this.solicitationData.program;
        this.sourceUrl = this.solicitationData.url;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupDetail());
        } else {
            this.setupDetail();
        }
    }

    setupDetail() {
        // Set up AI summary functionality
        this.setupAISummary();
        
        // Set up compliance checklist
        this.setupComplianceChecklist();
        
        // Set up next actions
        this.setupNextActions();
        
        // Set up activity logging
        this.setupActivityLogging();
        
        console.log('✅ Solicitation detail initialized');
    }

    setupAISummary() {
        // Generate/refresh summary buttons
        const generateBtn = document.getElementById('generate-summary-btn');
        const refreshBtn = document.getElementById('refresh-summary-btn');

        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.generateAISummary());
        }
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.generateAISummary(true));
        }
    }

    async generateAISummary(refresh = false) {
        try {
            const buttonId = refresh ? 'refresh-summary-btn' : 'generate-summary-btn';
            this.showLoadingState(buttonId, 'Generating...');

            const response = await fetch('/api/ai/summarize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    program: this.program,
                    source_url: this.sourceUrl,
                    refresh: refresh
                })
            });

            if (!response.ok) {
                throw new Error('Failed to generate summary');
            }

            const result = await response.json();
            
            // Update the summary display
            this.updateSummaryDisplay(result.summary, result.source_links);
            
            // Log activity
            this.logActivity('summary_generated', {
                message: `${refresh ? 'Refreshed' : 'Generated'} AI summary`,
                opp_no: this.oppNo,
                summary_length: result.summary.length
            });

            this.showToast('AI summary generated successfully', 'success');

        } catch (error) {
            console.error('Summary generation failed:', error);
            this.showToast('Failed to generate summary. Please try again.', 'error');
        } finally {
            const buttonId = refresh ? 'refresh-summary-btn' : 'generate-summary-btn';
            this.hideLoadingState(buttonId, refresh ? 'Refresh' : 'Generate Summary');
        }
    }

    updateSummaryDisplay(summary, sourceLinks) {
        const summaryCard = document.getElementById('ai-summary-card');
        const summaryContent = summaryCard.querySelector('.ai-summary-content');
        
        const newContent = `
            <div class="summary-text">
                ${summary.replace(/\n/g, '<br>')}
            </div>
            <div class="summary-meta">
                <small class="summary-updated">
                    Last updated: ${new Date().toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit'
                    })}
                </small>
                <small class="summary-source">
                    Source: <a href="${this.sourceUrl}" target="_blank" rel="noopener">Original Solicitation</a>
                </small>
            </div>
        `;
        
        summaryContent.innerHTML = newContent;
    }

    setupComplianceChecklist() {
        // Generate checklist button
        const generateBtn = document.getElementById('generate-checklist-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.generateComplianceChecklist());
        }

        // Update checklist button
        const updateBtn = document.getElementById('update-checklist-btn');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => this.updateComplianceChecklist());
        }

        // Handle checkbox changes
        document.querySelectorAll('.compliance-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.updateComplianceItem(e.target.dataset.itemId, e.target.checked);
            });
        });
    }

    async generateComplianceChecklist() {
        try {
            this.showLoadingState('generate-checklist-btn', 'Generating...');

            const response = await fetch('/api/ai/compliance_checklist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    program: this.program,
                    source_url: this.sourceUrl
                })
            });

            if (!response.ok) {
                throw new Error('Failed to generate checklist');
            }

            const result = await response.json();
            
            // Update the checklist display
            this.updateChecklistDisplay(result.checklist);
            
            // Log activity
            this.logActivity('checklist_generated', {
                message: 'Generated compliance checklist',
                opp_no: this.oppNo,
                items_count: result.checklist.length
            });

            this.showToast('Compliance checklist generated successfully', 'success');

        } catch (error) {
            console.error('Checklist generation failed:', error);
            this.showToast('Failed to generate checklist. Please try again.', 'error');
        } finally {
            this.hideLoadingState('generate-checklist-btn', 'Generate Checklist');
        }
    }

    updateChecklistDisplay(checklist) {
        const checklistCard = document.getElementById('compliance-card');
        const checklistContent = checklistCard.querySelector('.compliance-content');
        
        let checklistHTML = '<div class="checklist-items">';
        
        checklist.forEach(item => {
            checklistHTML += `
                <div class="checklist-item ${item.completed ? 'completed' : ''}">
                    <div class="checklist-checkbox">
                        <input type="checkbox" 
                               id="check-${item.id}" 
                               ${item.completed ? 'checked' : ''}
                               data-item-id="${item.id}"
                               class="compliance-checkbox">
                        <label for="check-${item.id}"></label>
                    </div>
                    <div class="checklist-content">
                        <span class="checklist-title">${item.title}</span>
                        ${item.description ? `<span class="checklist-description">${item.description}</span>` : ''}
                    </div>
                    ${item.due_date ? `<span class="checklist-due-date">Due: ${new Date(item.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>` : ''}
                </div>
            `;
        });
        
        checklistHTML += '</div>';
        checklistContent.innerHTML = checklistHTML;
        
        // Re-setup event listeners
        this.setupComplianceChecklist();
    }

    async updateComplianceItem(itemId, completed) {
        try {
            const response = await fetch('/api/compliance/update_item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    item_id: itemId,
                    completed: completed
                })
            });

            if (!response.ok) {
                throw new Error('Failed to update checklist item');
            }

            // Log activity
            this.logActivity('checklist_item_updated', {
                message: `${completed ? 'Completed' : 'Unchecked'} checklist item`,
                opp_no: this.oppNo,
                item_id: itemId
            });

        } catch (error) {
            console.error('Failed to update checklist item:', error);
            this.showToast('Failed to update checklist item', 'error');
        }
    }

    setupNextActions() {
        // Generate actions button
        const generateBtn = document.getElementById('generate-actions-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.generateNextActions());
        }

        // Add action button
        const addBtn = document.getElementById('add-action-btn');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.addNextAction());
        }

        // Handle action checkbox changes
        document.querySelectorAll('.action-checkbox-input').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.updateActionItem(e.target.dataset.actionId, e.target.checked);
            });
        });
    }

    async generateNextActions() {
        try {
            this.showLoadingState('generate-actions-btn', 'Generating...');

            const response = await fetch('/api/ai/next_actions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    program: this.program,
                    source_url: this.sourceUrl
                })
            });

            if (!response.ok) {
                throw new Error('Failed to generate next actions');
            }

            const result = await response.json();
            
            // Update the actions display
            this.updateActionsDisplay(result.actions);
            
            // Log activity
            this.logActivity('actions_generated', {
                message: 'Generated next actions',
                opp_no: this.oppNo,
                actions_count: result.actions.length
            });

            this.showToast('Next actions generated successfully', 'success');

        } catch (error) {
            console.error('Next actions generation failed:', error);
            this.showToast('Failed to generate next actions. Please try again.', 'error');
        } finally {
            this.hideLoadingState('generate-actions-btn', 'Generate Actions');
        }
    }

    updateActionsDisplay(actions) {
        const actionsCard = document.getElementById('next-actions-card');
        const actionsContent = actionsCard.querySelector('.next-actions-content');
        
        let actionsHTML = '<div class="actions-list">';
        
        actions.forEach(action => {
            actionsHTML += `
                <div class="action-item ${action.completed ? 'completed' : ''}">
                    <div class="action-checkbox">
                        <input type="checkbox" 
                               id="action-${action.id}" 
                               ${action.completed ? 'checked' : ''}
                               data-action-id="${action.id}"
                               class="action-checkbox-input">
                        <label for="action-${action.id}"></label>
                    </div>
                    <div class="action-content">
                        <span class="action-title">${action.title}</span>
                        ${action.description ? `<span class="action-description">${action.description}</span>` : ''}
                    </div>
                    <div class="action-meta">
                        <span class="action-priority priority-${(action.priority || 'medium').toLowerCase()}">
                            ${action.priority || 'Medium'}
                        </span>
                        ${action.due_date ? `<span class="action-due-date">${new Date(action.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>` : ''}
                    </div>
                </div>
            `;
        });
        
        actionsHTML += '</div>';
        actionsContent.innerHTML = actionsHTML;
        
        // Re-setup event listeners
        this.setupNextActions();
    }

    async addNextAction() {
        const actionTitle = prompt('Enter action title:');
        if (!actionTitle || !actionTitle.trim()) return;

        const actionDescription = prompt('Enter action description (optional):');

        try {
            const response = await fetch('/api/actions/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    title: actionTitle.trim(),
                    description: actionDescription?.trim() || '',
                    priority: 'medium'
                })
            });

            if (!response.ok) {
                throw new Error('Failed to add action');
            }

            // Log activity
            this.logActivity('action_added', {
                message: `Added action: ${actionTitle.trim()}`,
                opp_no: this.oppNo
            });

            this.showToast('Action added successfully', 'success');
            
            // Refresh page to show new action
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            console.error('Failed to add action:', error);
            this.showToast('Failed to add action. Please try again.', 'error');
        }
    }

    async updateActionItem(actionId, completed) {
        try {
            const response = await fetch('/api/actions/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    opp_no: this.oppNo,
                    action_id: actionId,
                    completed: completed
                })
            });

            if (!response.ok) {
                throw new Error('Failed to update action');
            }

            // Log activity
            this.logActivity('action_updated', {
                message: `${completed ? 'Completed' : 'Unchecked'} action`,
                opp_no: this.oppNo,
                action_id: actionId
            });

        } catch (error) {
            console.error('Failed to update action:', error);
            this.showToast('Failed to update action', 'error');
        }
    }

    setupActivityLogging() {
        // Log page view
        this.logActivity('page_view', {
            message: `Viewed solicitation details: ${this.title}`,
            opp_no: this.oppNo,
            program: this.program
        });

        // Track external link clicks
        document.querySelectorAll('a[target="_blank"]').forEach(link => {
            link.addEventListener('click', () => {
                this.logActivity('external_link_clicked', {
                    message: `Clicked external link: ${link.href}`,
                    opp_no: this.oppNo,
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
                    entity_type: 'solicitation',
                    entity_id: this.oppNo,
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
        console.log('Solicitation detail destroyed');
    }
}

// Initialize solicitation detail
let solicitationDetail;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        solicitationDetail = new SolicitationDetail();
    });
} else {
    solicitationDetail = new SolicitationDetail();
}

// Export for global access
window.SolicitationDetail = SolicitationDetail;
window.solicitationDetail = solicitationDetail;

console.log('✅ Solicitation detail script loaded');