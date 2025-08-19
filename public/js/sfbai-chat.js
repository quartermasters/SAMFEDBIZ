/**
 * SFBAI Chat Interface
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Features:
 * - Streaming responses
 * - Slash commands support
 * - 2-click actions (Copy to Outreach, Schedule Meeting, Save Note)
 * - Context injection
 * - Accessibility support
 */

class SFBAIChat {
    constructor() {
        this.chatInput = null;
        this.chatResponse = null;
        this.chatActions = null;
        this.chatSend = null;
        this.isStreaming = false;
        this.currentContext = {};
        this.messageHistory = [];
        this.lastResponse = '';
        
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupChat());
        } else {
            this.setupChat();
        }
    }

    setupChat() {
        // Get DOM elements
        this.chatInput = document.getElementById('chat-input');
        this.chatResponse = document.getElementById('chat-response');
        this.chatActions = document.getElementById('chat-actions');
        this.chatSend = document.querySelector('.chat-send');

        if (!this.chatInput || !this.chatResponse) {
            console.error('SFBAI chat elements not found');
            return;
        }

        // Set up event listeners
        this.setupEventListeners();
        
        // Initialize context
        this.updateContext();
        
        // Set up slash commands
        this.setupSlashCommands();

        console.log('✅ SFBAI chat initialized');
    }

    setupEventListeners() {
        // Send message on Enter key
        this.chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Send button click
        this.chatSend?.addEventListener('click', () => this.sendMessage());

        // Suggestion buttons
        document.querySelectorAll('.suggestion-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.handleSuggestion(action);
            });
        });

        // Program chip clicks for context
        document.querySelectorAll('.program-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                const program = e.target.dataset.program;
                if (program) {
                    this.setContext({ program });
                    this.updateProgramChips(program);
                }
            });
        });

        // Input focus management
        this.chatInput.addEventListener('focus', () => {
            this.chatInput.parentElement.classList.add('focused');
        });

        this.chatInput.addEventListener('blur', () => {
            this.chatInput.parentElement.classList.remove('focused');
        });
    }

    setupSlashCommands() {
        this.slashCommands = {
            '/brief': (args) => this.executeSlashCommand('brief', args),
            '/summarize': (args) => this.executeSlashCommand('summarize', args),
            '/draft': (args) => this.executeSlashCommand('draft', args),
            '/opps': (args) => this.executeSlashCommand('opps', args),
            '/catalog': (args) => this.executeSlashCommand('catalog', args),
            '/schedule': (args) => this.executeSlashCommand('schedule', args),
            '/?': () => this.showHelp()
        };

        // Listen for slash command typing
        this.chatInput.addEventListener('input', (e) => {
            const value = e.target.value;
            if (value.startsWith('/') && value.length > 1) {
                this.showSlashCommandHints(value);
            } else {
                this.hideSlashCommandHints();
            }
        });
    }

    async sendMessage() {
        const message = this.chatInput.value.trim();
        if (!message || this.isStreaming) return;

        // Check for slash commands
        if (message.startsWith('/')) {
            this.handleSlashCommand(message);
            return;
        }

        // Clear input and show user message
        this.chatInput.value = '';
        this.addMessage('user', message);
        
        // Show streaming indicator
        this.startStreaming();

        try {
            const response = await this.callSFBAI(message);
            this.handleResponse(response);
        } catch (error) {
            console.error('SFBAI request failed:', error);
            this.addMessage('system', 'Sorry, I encountered an error. Please try again.');
        } finally {
            this.stopStreaming();
        }
    }

    handleSlashCommand(command) {
        const parts = command.split(' ');
        const cmd = parts[0];
        const args = parts.slice(1);

        if (this.slashCommands[cmd]) {
            this.slashCommands[cmd](args);
        } else {
            this.addMessage('system', `Unknown command: ${cmd}. Type /? for help.`);
        }

        this.chatInput.value = '';
    }

    async executeSlashCommand(command, args) {
        this.addMessage('user', `/${command} ${args.join(' ')}`);
        this.startStreaming();

        try {
            const response = await this.callSFBAI(`/${command} ${args.join(' ')}`, true);
            this.handleResponse(response);
        } catch (error) {
            console.error('Slash command failed:', error);
            this.addMessage('system', 'Command failed. Please try again.');
        } finally {
            this.stopStreaming();
        }
    }

    async callSFBAI(message, isSlashCommand = false) {
        const payload = {
            message: message,
            context: this.currentContext,
            is_slash_command: isSlashCommand,
            history: this.messageHistory.slice(-10) // Last 10 messages for context
        };

        const response = await fetch('/api/ai/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCSRFToken()
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    handleResponse(response) {
        if (response.error) {
            this.addMessage('system', response.error);
            return;
        }

        // Add AI response
        this.addMessage('assistant', response.response);
        this.lastResponse = response.response;

        // Show action buttons if available
        if (response.actions && response.actions.length > 0) {
            this.showActions(response.actions);
        }

        // Handle any special commands
        if (response.commands) {
            this.handleCommands(response.commands);
        }
    }

    addMessage(role, content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${role}`;
        messageDiv.innerHTML = this.formatMessage(content);
        
        this.chatResponse.appendChild(messageDiv);
        this.chatResponse.scrollTop = this.chatResponse.scrollHeight;

        // Store in history
        this.messageHistory.push({ role, content, timestamp: Date.now() });

        // Limit history size
        if (this.messageHistory.length > 20) {
            this.messageHistory = this.messageHistory.slice(-10);
        }

        // Announce to screen readers
        if (role === 'assistant') {
            this.announceToScreenReader(content);
        }
    }

    formatMessage(content) {
        // Basic markdown-like formatting
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>')
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    }

    startStreaming() {
        this.isStreaming = true;
        this.chatSend.disabled = true;
        
        // Add streaming indicator
        const streamingDiv = document.createElement('div');
        streamingDiv.className = 'chat-message assistant streaming';
        streamingDiv.innerHTML = '<span class="streaming-dots"></span>';
        streamingDiv.id = 'streaming-indicator';
        
        this.chatResponse.appendChild(streamingDiv);
        this.chatResponse.scrollTop = this.chatResponse.scrollHeight;
    }

    stopStreaming() {
        this.isStreaming = false;
        this.chatSend.disabled = false;
        
        // Remove streaming indicator
        const streamingIndicator = document.getElementById('streaming-indicator');
        if (streamingIndicator) {
            streamingIndicator.remove();
        }
    }

    showActions(actions) {
        this.chatActions.innerHTML = '';
        
        actions.forEach(action => {
            const button = document.createElement('button');
            button.className = `action-btn ${action.type || 'secondary'}`;
            button.textContent = action.label;
            button.addEventListener('click', () => this.executeAction(action));
            this.chatActions.appendChild(button);
        });

        this.chatActions.style.display = 'flex';
    }

    executeAction(action) {
        switch (action.action) {
            case 'copy_to_outreach':
                this.copyToOutreach(action.data);
                break;
            case 'schedule_meeting':
                this.scheduleMeeting(action.data);
                break;
            case 'save_note':
                this.saveNote(action.data);
                break;
            case 'open_link':
                window.open(action.data.url, '_blank', 'noopener');
                break;
            default:
                console.warn('Unknown action:', action.action);
        }
    }

    async copyToOutreach(data) {
        try {
            const response = await fetch('/api/outreach/draft', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    content: this.lastResponse,
                    context: this.currentContext,
                    ...data
                })
            });

            if (response.ok) {
                this.showToast('Draft copied to outreach', 'success');
                // Optionally redirect to outreach page
                setTimeout(() => window.location.href = '/outreach', 1500);
            } else {
                throw new Error('Failed to copy to outreach');
            }
        } catch (error) {
            console.error('Copy to outreach failed:', error);
            this.showToast('Failed to copy to outreach', 'error');
        }
    }

    async scheduleMeeting(data) {
        try {
            const response = await fetch('/api/calendar/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    title: data.title || 'SFBAI Suggested Meeting',
                    context: this.currentContext,
                    notes: this.lastResponse,
                    ...data
                })
            });

            if (response.ok) {
                this.showToast('Meeting scheduled', 'success');
            } else {
                throw new Error('Failed to schedule meeting');
            }
        } catch (error) {
            console.error('Schedule meeting failed:', error);
            this.showToast('Failed to schedule meeting', 'error');
        }
    }

    async saveNote(data) {
        try {
            const response = await fetch('/api/notes/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRFToken()
                },
                body: JSON.stringify({
                    title: data.title || 'SFBAI Note',
                    body: this.lastResponse,
                    context: this.currentContext,
                    tags: data.tags || [],
                    ...data
                })
            });

            if (response.ok) {
                this.showToast('Note saved', 'success');
            } else {
                throw new Error('Failed to save note');
            }
        } catch (error) {
            console.error('Save note failed:', error);
            this.showToast('Failed to save note', 'error');
        }
    }

    handleSuggestion(action) {
        switch (action) {
            case 'brief':
                this.chatInput.value = '/brief all';
                this.sendMessage();
                break;
            case 'summarize':
                this.chatInput.value = '/summarize';
                this.chatInput.focus();
                break;
            case 'opps':
                this.chatInput.value = '/opps';
                this.sendMessage();
                break;
        }
    }

    setContext(context) {
        this.currentContext = { ...this.currentContext, ...context };
        console.log('Context updated:', this.currentContext);
    }

    updateContext() {
        // Auto-detect context from current page
        const path = window.location.pathname;
        const context = {};

        // Extract program from URL
        const programMatch = path.match(/\/programs\/([^\/]+)/);
        if (programMatch) {
            context.program = programMatch[1];
        }

        // Extract holder from URL
        const holderMatch = path.match(/\/holders\/(\d+)/);
        if (holderMatch) {
            context.holder_id = parseInt(holderMatch[1]);
        }

        this.setContext(context);
    }

    updateProgramChips(activeProgram) {
        document.querySelectorAll('.program-chip').forEach(chip => {
            chip.classList.remove('active');
            if (chip.dataset.program === activeProgram) {
                chip.classList.add('active');
            }
        });
    }

    showSlashCommandHints(input) {
        // Could implement autocomplete dropdown here
        console.log('Slash command hints for:', input);
    }

    hideSlashCommandHints() {
        // Hide autocomplete dropdown
    }

    showHelp() {
        const helpText = `
**Available Commands:**
• \`/brief [tls|oasis+|sewp|all]\` - Today's intelligence & next actions
• \`/summarize <doc_id>\` - Executive summary + action items  
• \`/draft <outreach|followup> [holder] [topic]\` - 130-word email
• \`/opps [program] [filter]\` - Opportunities closing soon
• \`/catalog [holder]\` - Show micro-catalog highlights
• \`/schedule [title] [attendees]\` - Draft meeting event

**Examples:**
• Draft an email to SupplyCore about TLS SOE kits
• Show OASIS+ Pool 1 items closing within 30 days
• Summarize the latest SEWP ordering guide update
        `;
        
        this.addMessage('system', helpText);
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

    announceToScreenReader(message) {
        // Create temporary element for screen reader announcement
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = `SFBAI: ${message}`;
        
        document.body.appendChild(announcement);
        
        // Remove after announcement
        setTimeout(() => announcement.remove(), 1000);
    }

    // Public methods for external control
    focus() {
        this.chatInput?.focus();
    }

    clear() {
        this.chatResponse.innerHTML = '';
        this.messageHistory = [];
        this.chatActions.style.display = 'none';
    }

    destroy() {
        // Cleanup event listeners and elements
        console.log('SFBAI chat destroyed');
    }
}

// Initialize SFBAI Chat
let sfbaiChat;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        sfbaiChat = new SFBAIChat();
    });
} else {
    sfbaiChat = new SFBAIChat();
}

// Export for global access
window.SFBAIChat = SFBAIChat;
window.sfbaiChat = sfbaiChat;