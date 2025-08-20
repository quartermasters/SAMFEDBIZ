/**
 * Settings Admin JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - SMTP testing
 * - System health checks
 * - Subscriber export
 * - Real-time status updates
 */

class SettingsAdmin {
    constructor() {
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSettings());
        } else {
            this.setupSettings();
        }
    }

    setupSettings() {
        // Set up SMTP testing
        this.setupSMTPTesting();
        
        // Set up system health checks
        this.setupSystemHealth();
        
        // Set up subscriber export
        this.setupSubscriberExport();
        
        // Set up real-time updates
        this.setupRealTimeUpdates();
        
        // Load last cron run time
        this.loadLastCronTime();
        
        console.log('✅ Settings admin initialized');
    }

    setupSMTPTesting() {
        const testBtn = document.getElementById('test-smtp-btn');
        if (testBtn) {
            testBtn.addEventListener('click', () => this.testSMTPConnection());
        }
    }

    async testSMTPConnection() {
        const testBtn = document.getElementById('test-smtp-btn');
        if (!testBtn) return;

        try {
            this.showLoadingState(testBtn, 'Testing...');

            const response = await fetch('/api/settings/test-smtp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('SMTP test failed');
            }

            const result = await response.json();
            
            if (result.success) {
                this.showToast('SMTP connection successful', 'success');
            } else {
                this.showToast(`SMTP test failed: ${result.message}`, 'error');
            }

        } catch (error) {
            console.error('SMTP test error:', error);
            this.showToast('SMTP test failed. Check configuration.', 'error');
        } finally {
            this.hideLoadingState(testBtn, 'Test SMTP Connection');
        }
    }

    setupSystemHealth() {
        const healthBtn = document.getElementById('check-system-health-btn');
        if (healthBtn) {
            healthBtn.addEventListener('click', () => this.checkSystemHealth());
        }
    }

    async checkSystemHealth() {
        const healthBtn = document.getElementById('check-system-health-btn');
        if (!healthBtn) return;

        try {
            this.showLoadingState(healthBtn, 'Checking...');

            const response = await fetch('/api/settings/system-health', {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (!response.ok) {
                throw new Error('Health check failed');
            }

            const result = await response.json();
            
            this.showSystemHealthModal(result);

        } catch (error) {
            console.error('System health check error:', error);
            this.showToast('System health check failed', 'error');
        } finally {
            this.hideLoadingState(healthBtn, 'Check System Health');
        }
    }

    showSystemHealthModal(healthData) {
        const modal = document.createElement('div');
        modal.className = 'system-health-modal-overlay';
        modal.innerHTML = `
            <div class="system-health-modal">
                <div class="system-health-header">
                    <h3>System Health Report</h3>
                    <button class="system-health-close" aria-label="Close">&times;</button>
                </div>
                <div class="system-health-content">
                    <div class="health-summary">
                        <div class="health-status ${healthData.overall_status}">
                            <span class="health-indicator"></span>
                            Overall Status: ${healthData.overall_status.toUpperCase()}
                        </div>
                    </div>
                    
                    <div class="health-checks">
                        ${this.formatHealthChecks(healthData.checks)}
                    </div>
                    
                    <div class="health-metrics">
                        <h4>System Metrics</h4>
                        <div class="metrics-grid">
                            <div class="metric-item">
                                <span class="metric-label">Database</span>
                                <span class="metric-value ${healthData.database.status}">${healthData.database.status}</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">File Permissions</span>
                                <span class="metric-value ${healthData.file_permissions.status}">${healthData.file_permissions.status}</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Cron Jobs</span>
                                <span class="metric-value ${healthData.cron_jobs.status}">${healthData.cron_jobs.status}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="system-health-actions">
                    <button class="btn-secondary system-health-close">Close</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Set up modal interactions
        const closeButtons = modal.querySelectorAll('.system-health-close');
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
    }

    formatHealthChecks(checks) {
        let content = '';
        checks.forEach(check => {
            content += `
                <div class="health-check-item ${check.status}">
                    <div class="health-check-name">${check.name}</div>
                    <div class="health-check-status">${check.status}</div>
                    ${check.message ? `<div class="health-check-message">${check.message}</div>` : ''}
                </div>
            `;
        });
        return content;
    }

    setupSubscriberExport() {
        const exportBtn = document.getElementById('export-subscribers-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportSubscribers());
        }
    }

    async exportSubscribers() {
        try {
            const response = await fetch('/api/settings/export-subscribers', {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
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
            a.download = `subscribers_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showToast('Subscriber list exported successfully', 'success');

        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed. Please try again.', 'error');
        }
    }

    setupRealTimeUpdates() {
        // Update status indicators periodically
        setInterval(() => {
            this.updateStatusIndicators();
        }, 30000); // Every 30 seconds
    }

    async updateStatusIndicators() {
        try {
            const response = await fetch('/api/settings/status', {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (response.ok) {
                const status = await response.json();
                this.updateUIStatus(status);
            }

        } catch (error) {
            console.warn('Status update failed:', error);
        }
    }

    updateUIStatus(status) {
        // Update OAuth connection status
        const googleStatus = document.querySelector('.oauth-service-item .connection-indicator');
        if (googleStatus && status.oauth && status.oauth.google_connected !== undefined) {
            googleStatus.className = `connection-indicator ${status.oauth.google_connected ? 'connected' : 'disconnected'}`;
            googleStatus.textContent = status.oauth.google_connected ? 'Connected' : 'Disconnected';
        }

        // Update subscriber counts
        if (status.subscribers) {
            const activeCount = document.querySelector('.subscriber-stat-item:nth-child(2) .subscriber-stat-value');
            if (activeCount) {
                activeCount.textContent = status.subscribers.active.toLocaleString();
            }
        }
    }

    async loadLastCronTime() {
        try {
            const response = await fetch('/api/settings/last-cron', {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCSRFToken()
                }
            });

            if (response.ok) {
                const result = await response.json();
                const lastCronElement = document.getElementById('last-cron-time');
                if (lastCronElement && result.last_run) {
                    const lastRun = new Date(result.last_run);
                    const now = new Date();
                    const diffMinutes = Math.floor((now - lastRun) / (1000 * 60));
                    
                    if (diffMinutes < 60) {
                        lastCronElement.textContent = `${diffMinutes} minutes ago`;
                    } else if (diffMinutes < 1440) {
                        const hours = Math.floor(diffMinutes / 60);
                        lastCronElement.textContent = `${hours} hour${hours !== 1 ? 's' : ''} ago`;
                    } else {
                        lastCronElement.textContent = lastRun.toLocaleDateString();
                    }
                } else if (lastCronElement) {
                    lastCronElement.textContent = 'Never';
                }
            }

        } catch (error) {
            console.warn('Failed to load last cron time:', error);
            const lastCronElement = document.getElementById('last-cron-time');
            if (lastCronElement) {
                lastCronElement.textContent = 'Unknown';
            }
        }
    }

    showLoadingState(button, loadingText) {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = loadingText;
            button.classList.add('loading');
        }
    }

    hideLoadingState(button, originalText) {
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
        console.log('Settings admin destroyed');
    }
}

// Initialize settings admin
let settingsAdmin;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        settingsAdmin = new SettingsAdmin();
    });
} else {
    settingsAdmin = new SettingsAdmin();
}

// Export for global access
window.SettingsAdmin = SettingsAdmin;
window.settingsAdmin = settingsAdmin;

console.log('✅ Settings admin script loaded');