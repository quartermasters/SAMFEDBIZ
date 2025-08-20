/**
 * Outreach Email Composer JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles email composition, AI draft generation, and sending
 */

class OutreachComposer {
    constructor() {
        this.initializeElements();
        this.initializeEventListeners();
        this.setupFormValidation();
        this.initializeWordCounter();
    }

    initializeElements() {
        this.contextForm = document.getElementById('context-form');
        this.emailForm = document.getElementById('email-form');
        this.generateBtn = document.querySelector('.generate-btn');
        this.sendBtn = document.getElementById('send-email-btn');
        this.saveDraftBtn = document.getElementById('save-draft-btn');
        this.contentTextarea = document.getElementById('content');
        this.wordCountElement = document.getElementById('word-count');
        this.charCountElement = document.getElementById('char-count');
        this.primeSelect = document.getElementById('prime_id');
    }

    initializeEventListeners() {
        // Context form submission (AI draft generation)
        this.contextForm.addEventListener('submit', (e) => {
            this.handleDraftGeneration(e);
        });

        // Email form submission (send email)
        this.emailForm.addEventListener('submit', (e) => {
            this.handleEmailSending(e);
        });

        // Save draft functionality
        this.saveDraftBtn.addEventListener('click', () => {
            this.saveDraft();
        });

        // Prime selection change
        this.primeSelect.addEventListener('change', () => {
            this.handlePrimeSelection();
        });

        // Content area changes
        this.contentTextarea.addEventListener('input', () => {
            this.updateWordCount();
            this.validateContent();
        });

        // Auto-populate subject based on context
        document.getElementById('purpose').addEventListener('change', () => {
            this.updateSubjectSuggestion();
        });

        // Real-time email validation
        document.getElementById('recipient_email').addEventListener('blur', () => {
            this.validateEmail();
        });
    }

    handleDraftGeneration(e) {
        e.preventDefault();
        
        // Show loading state
        this.setGenerateButtonLoading(true);
        
        // Submit form via AJAX to prevent page reload
        const formData = new FormData(this.contextForm);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Parse the response to extract the draft content
            // In a real implementation, you might return JSON instead
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const draftContent = doc.querySelector('#content');
            
            if (draftContent && draftContent.value) {
                this.contentTextarea.value = draftContent.value;
                this.updateWordCount();
                this.showSuccessMessage('AI draft generated successfully!');
                
                // Animate the content appearance
                gsap.fromTo(this.contentTextarea, 
                    { opacity: 0.5, scale: 0.98 },
                    { opacity: 1, scale: 1, duration: 0.4, ease: "back.out(1.7)" }
                );
            } else {
                this.showErrorMessage('Failed to generate draft. Please check your AI API configuration.');
            }
        })
        .catch(error => {
            console.error('Draft generation error:', error);
            this.showErrorMessage('Network error occurred while generating draft.');
        })
        .finally(() => {
            this.setGenerateButtonLoading(false);
        });
    }

    handleEmailSending(e) {
        if (!this.validateEmailForm()) {
            e.preventDefault();
            return;
        }
        
        // Show loading state
        this.setSendButtonLoading(true);
        
        // Add confirmation if email is going to external domain
        const recipientEmail = document.getElementById('recipient_email').value;
        if (recipientEmail && !this.isInternalEmail(recipientEmail)) {
            const confirmed = confirm(
                `You're about to send an email to ${recipientEmail}. This will be sent via your configured SMTP server. Continue?`
            );
            
            if (!confirmed) {
                e.preventDefault();
                this.setSendButtonLoading(false);
                return;
            }
        }
    }

    setGenerateButtonLoading(loading) {
        if (loading) {
            this.generateBtn.disabled = true;
            this.generateBtn.innerHTML = `
                <svg class="animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
                Generating...
            `;
        } else {
            this.generateBtn.disabled = false;
            this.generateBtn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 6V4m0 2a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m-6 8a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4"/>
                </svg>
                Generate AI Draft
            `;
        }
    }

    setSendButtonLoading(loading) {
        if (loading) {
            this.sendBtn.disabled = true;
            this.sendBtn.innerHTML = `
                <svg class="animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Sending...
            `;
        } else {
            this.sendBtn.disabled = false;
            this.sendBtn.innerHTML = 'Send Email';
        }
    }

    setupFormValidation() {
        // Add validation styles
        const inputs = this.emailForm.querySelectorAll('input[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    validateField(field) {
        const isValid = field.checkValidity();
        
        if (!isValid) {
            field.classList.add('field-error');
            this.showFieldError(field, field.validationMessage);
        } else {
            field.classList.remove('field-error');
            this.clearFieldError(field);
        }
        
        return isValid;
    }

    validateEmailForm() {
        const requiredFields = this.emailForm.querySelectorAll('input[required], textarea[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        // Additional content length validation
        const content = this.contentTextarea.value;
        const wordCount = this.countWords(content);
        
        if (wordCount < 50) {
            this.showFieldError(this.contentTextarea, 'Email content should be at least 50 words for effective outreach.');
            isValid = false;
        } else if (wordCount > 200) {
            this.showFieldError(this.contentTextarea, 'Email content should be under 200 words for better engagement.');
            isValid = false;
        }
        
        return isValid;
    }

    showFieldError(field, message) {
        // Remove existing error
        this.clearFieldError(field);
        
        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error-message';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.classList.remove('field-error');
        const errorMessage = field.parentNode.querySelector('.field-error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    initializeWordCounter() {
        this.updateWordCount();
    }

    updateWordCount() {
        const content = this.contentTextarea.value;
        const wordCount = this.countWords(content);
        const charCount = content.length;
        
        this.wordCountElement.textContent = wordCount;
        this.charCountElement.textContent = charCount;
        
        // Update color based on target range (120-150 words)
        if (wordCount >= 120 && wordCount <= 150) {
            this.wordCountElement.className = 'count-optimal';
        } else if (wordCount < 120) {
            this.wordCountElement.className = 'count-low';
        } else {
            this.wordCountElement.className = 'count-high';
        }
    }

    countWords(text) {
        return text.trim().split(/\s+/).filter(word => word.length > 0).length;
    }

    handlePrimeSelection() {
        const selectedPrimeId = this.primeSelect.value;
        
        if (selectedPrimeId) {
            // Update URL to reflect selection
            const url = new URL(window.location);
            url.searchParams.set('prime', selectedPrimeId);
            window.history.replaceState({}, '', url);
            
            // Reload to show prime info
            // In a real implementation, you might fetch this via AJAX
            window.location.reload();
        }
    }

    updateSubjectSuggestion() {
        const purpose = document.getElementById('purpose').value;
        const primeSelect = document.getElementById('prime_id');
        const primeName = primeSelect.options[primeSelect.selectedIndex].text;
        const subjectField = document.getElementById('subject');
        
        let suggestedSubject = 'TLS Program - ';
        
        switch (purpose) {
            case 'introduction':
                suggestedSubject += 'Partnership Opportunity';
                break;
            case 'follow_up':
                suggestedSubject += 'Following Up on Our Discussion';
                break;
            case 'proposal_request':
                suggestedSubject += 'Request for Proposal';
                break;
            case 'capability_inquiry':
                suggestedSubject += 'Capability Inquiry';
                break;
            case 'meeting_request':
                suggestedSubject += '15-Minute Introduction Call';
                break;
            default:
                suggestedSubject += 'Partnership Opportunity';
        }
        
        if (primeName && primeName !== 'Select Prime...') {
            suggestedSubject += ` - ${primeName}`;
        }
        
        if (!subjectField.value) {
            subjectField.value = suggestedSubject;
        }
    }

    validateEmail() {
        const emailField = document.getElementById('recipient_email');
        const email = emailField.value;
        
        if (email && !this.isValidEmail(email)) {
            this.showFieldError(emailField, 'Please enter a valid email address.');
            return false;
        }
        
        return true;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isInternalEmail(email) {
        // Define internal domains if any
        const internalDomains = ['samfedbiz.com', 'axivai.com'];
        const domain = email.split('@')[1];
        return internalDomains.includes(domain);
    }

    saveDraft() {
        const draftData = {
            recipient_email: document.getElementById('recipient_email').value,
            recipient_name: document.getElementById('recipient_name').value,
            subject: document.getElementById('subject').value,
            content: this.contentTextarea.value,
            prime_id: document.getElementById('prime_id').value
        };
        
        // Save to localStorage
        localStorage.setItem('outreach_draft', JSON.stringify(draftData));
        
        this.showSuccessMessage('Draft saved locally.');
        
        // Animate save button
        gsap.to(this.saveDraftBtn, {
            scale: 0.95,
            duration: 0.1,
            yoyo: true,
            repeat: 1
        });
    }

    loadDraft() {
        const savedDraft = localStorage.getItem('outreach_draft');
        
        if (savedDraft) {
            try {
                const draftData = JSON.parse(savedDraft);
                
                // Populate form fields
                document.getElementById('recipient_email').value = draftData.recipient_email || '';
                document.getElementById('recipient_name').value = draftData.recipient_name || '';
                document.getElementById('subject').value = draftData.subject || '';
                this.contentTextarea.value = draftData.content || '';
                
                if (draftData.prime_id) {
                    document.getElementById('prime_id').value = draftData.prime_id;
                }
                
                this.updateWordCount();
                this.showSuccessMessage('Draft loaded from previous session.');
            } catch (error) {
                console.error('Error loading draft:', error);
            }
        }
    }

    showSuccessMessage(message) {
        this.showMessage(message, 'success');
    }

    showErrorMessage(message) {
        this.showMessage(message, 'error');
    }

    showMessage(message, type) {
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.textContent = message;
        
        // Insert at top of main content
        const mainContent = document.querySelector('.composer-main-content');
        mainContent.insertBefore(messageDiv, mainContent.firstChild);
        
        // Animate in
        gsap.fromTo(messageDiv,
            { opacity: 0, y: -20 },
            { opacity: 1, y: 0, duration: 0.3 }
        );
        
        // Remove after 5 seconds
        setTimeout(() => {
            gsap.to(messageDiv, {
                opacity: 0,
                y: -20,
                duration: 0.3,
                onComplete: () => messageDiv.remove()
            });
        }, 5000);
    }

    // Initialize on load
    init() {
        // Load any saved draft
        this.loadDraft();
        
        // Set up auto-save
        setInterval(() => {
            if (this.contentTextarea.value.trim()) {
                this.saveDraft();
            }
        }, 30000); // Auto-save every 30 seconds
    }
}

// Export for external use
window.OutreachComposer = OutreachComposer;