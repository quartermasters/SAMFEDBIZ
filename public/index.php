<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>samfedbiz.com - Federal BD Intelligence Platform</title>
    <meta name="description" content="Federal business development platform for TLS, OASIS+, and SEWP programs">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    <link rel="stylesheet" href="/styles/hero.css">
    <link rel="stylesheet" href="/styles/print.css" media="print">
    
    <!-- GSAP 3.x -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
    
    <!-- Lenis Smooth Scroll -->
    <script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis/dist/lenis.min.js"></script>
</head>

<body class="dashboard">
    <!-- Navigation -->
    <nav class="nav-main" role="navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>samfedbiz</h1>
                <span class="nav-tagline">Federal BD Intelligence</span>
            </div>
            
            <div class="nav-links">
                <a href="/" class="nav-link active" aria-current="page">Dashboard</a>
                <a href="/programs" class="nav-link">Programs</a>
                <a href="/briefs" class="nav-link">Briefs</a>
                <a href="/settings" class="nav-link">Settings</a>
            </div>
            
            <div class="nav-user">
                <span class="user-name">Welcome, Admin</span>
                <a href="/logout" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section with SFBAI Chatbox -->
    <section class="hero reveal" role="main">
        <div class="hero-container">
            <div class="hero-content">
                <h2 class="hero-title">What do you want to get done?</h2>
                <div class="hero-programs">
                    <button class="program-chip tilt-card" data-program="tls">
                        <svg class="program-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        TLS
                    </button>
                    <button class="program-chip tilt-card" data-program="oasisplus">
                        <svg class="program-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <path d="M9 9h6v6H9z"/>
                        </svg>
                        OASIS+
                    </button>
                    <button class="program-chip tilt-card" data-program="sewp">
                        <svg class="program-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                        SEWP
                    </button>
                </div>
            </div>

            <!-- SFBAI Chatbox -->
            <div class="sfbai-chatbox glassmorphism" role="complementary" aria-label="SFBAI Chat Assistant">
                <div class="chat-header">
                    <h3>Ask SFBAI...</h3>
                    <button class="chat-help" aria-label="Help">?</button>
                </div>
                
                <div class="chat-input-container">
                    <input 
                        type="text" 
                        id="chat-input" 
                        class="chat-input" 
                        placeholder="e.g., 'Draft an email to SupplyCore about TLS SOE kits'"
                        aria-label="Chat with SFBAI"
                        maxlength="500"
                    >
                    <button class="chat-send" aria-label="Send message">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                        </svg>
                    </button>
                </div>
                
                <div class="chat-suggestions">
                    <button class="suggestion-btn" data-action="brief">Today's Brief</button>
                    <button class="suggestion-btn" data-action="summarize">Summarize Research</button>
                    <button class="suggestion-btn" data-action="opps">Find Opps</button>
                </div>
                
                <div class="chat-response" id="chat-response" role="log" aria-live="polite" aria-label="Chat responses">
                    <!-- Streaming AI responses appear here -->
                </div>
                
                <div class="chat-actions" id="chat-actions" style="display: none;">
                    <button class="action-btn primary">Copy to Outreach</button>
                    <button class="action-btn secondary">Schedule Meeting</button>
                    <button class="action-btn secondary">Save Note</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Content -->
    <section class="dashboard-content">
        <div class="content-container">
            <!-- Today's Briefs -->
            <div class="dashboard-section reveal">
                <h3 class="section-title">Today's Briefs</h3>
                <div class="briefs-grid">
                    <div class="brief-card tilt-card" data-program="tls">
                        <div class="card-header">
                            <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <h4>TLS Brief</h4>
                            <span class="timestamp">6:05 AM</span>
                        </div>
                        <p class="brief-summary">2 new SOE opportunities, 1 F&ESE update...</p>
                        <div class="card-actions">
                            <button class="btn-text">Open</button>
                            <button class="btn-text">Share</button>
                        </div>
                    </div>
                    
                    <div class="brief-card tilt-card" data-program="oasisplus">
                        <div class="card-header">
                            <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <path d="M9 9h6v6H9z"/>
                            </svg>
                            <h4>OASIS+ Brief</h4>
                            <span class="timestamp">6:05 AM</span>
                        </div>
                        <p class="brief-summary">Pool 1 cybersecurity RFP, new domain guidance...</p>
                        <div class="card-actions">
                            <button class="btn-text">Open</button>
                            <button class="btn-text">Share</button>
                        </div>
                    </div>
                    
                    <div class="brief-card tilt-card" data-program="sewp">
                        <div class="card-header">
                            <svg class="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                            <h4>SEWP Brief</h4>
                            <span class="timestamp">6:05 AM</span>
                        </div>
                        <p class="brief-summary">Cloud infrastructure RFQ, Group B updates...</p>
                        <div class="card-actions">
                            <button class="btn-text">Open</button>
                            <button class="btn-text">Share</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Meetings -->
            <div class="dashboard-section reveal">
                <h3 class="section-title">Upcoming Meetings</h3>
                <div class="meetings-list">
                    <div class="meeting-item">
                        <div class="meeting-time">2:00 PM</div>
                        <div class="meeting-details">
                            <h4>Discovery Call - SupplyCore</h4>
                            <p>TLS SOE kit capabilities discussion</p>
                        </div>
                        <button class="btn-text">Reschedule</button>
                    </div>
                    
                    <div class="meeting-item">
                        <div class="meeting-time">3:30 PM</div>
                        <div class="meeting-details">
                            <h4>OASIS+ Strategy Session</h4>
                            <p>Pool 1 positioning review</p>
                        </div>
                        <button class="btn-text">Reschedule</button>
                    </div>
                </div>
            </div>

            <!-- Recent Outreach -->
            <div class="dashboard-section reveal">
                <h3 class="section-title">Recent Outreach</h3>
                <div class="outreach-list">
                    <div class="outreach-item">
                        <div class="outreach-contact">
                            <h4>Federal Resources</h4>
                            <p>Fire suppression equipment inquiry</p>
                        </div>
                        <span class="status-badge sent">Sent</span>
                        <span class="timestamp">2 hours ago</span>
                    </div>
                    
                    <div class="outreach-item">
                        <div class="outreach-contact">
                            <h4>TSSi</h4>
                            <p>SOE tactical gear capabilities</p>
                        </div>
                        <span class="status-badge replied">Replied</span>
                        <span class="timestamp">4 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <p>&copy; 2025 Quartermasters FZC. All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/animations.js"></script>
    <script src="/js/sfbai-chat.js"></script>
</body>
</html>