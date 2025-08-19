# samfedbiz.com Implementation Todo List

**Project:** samfedbiz.com  
**Owner:** Quartermasters FZC  
**Stakeholder:** AXIVAI.COM  
**Date:** 2025-08-19  

## Phase 0 - Foundations

- [ ] **phase0_db_schema**: Create database schema with all required tables (programs, holders, holder_meta, oems, parts, prime_catalog, opportunities, opportunity_meta, research_docs, outreach, meetings, news_items, daily_briefs, subscribers, users, oauth_tokens, chats, chat_messages, chat_actions, notes)

- [ ] **phase0_adapter_interface**: Create PHP adapter interface with required methods (code(), name(), keywords(), listPrimesOrHolders(), fetchSolicitations(), normalize(), extraFields())

- [ ] **phase0_auth_roles**: Implement authentication system with roles (admin, ops, viewer) using bcrypt password hashing

- [ ] **phase0_env_secrets**: Set up environment variables system (APP_ENV, TIMEZONE, OPENAI_API_KEY, GEMINI_API_KEY, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, SMTP_HOST, SMTP_USER, SMTP_PASS) - never expose in client-side

- [ ] **phase0_program_registry**: Create program registry system with toggles for TLS/OASIS+/SEWP adapters

- [ ] **phase0_review_gate**: Pass review gate 'gate_foundations' - ensure php -l passes, roles enforced, program toggles working, secrets only from env

## Section 1 - Dashboard Hero

- [ ] **section1_hero_design**: Create dashboard hero with SFBAI chatbox using glassmorphism design (rgba(255,255,255,0.6), backdrop-filter: saturate(180%) blur(16px), border: 1px solid rgba(255,255,255,0.35))

- [ ] **section1_gsap_setup**: Implement GSAP 3.x animations with reveal (staggered fade/slide-up, duration: 0.6, stagger: 0.08) and hover tilt (max 6°, max translate 6px, power2.out easing)

- [ ] **section1_lenis_scroll**: Integrate Lenis smooth scroll (duration: 1.0, smoothWheel: true) - initialize once only

- [ ] **section1_chat_interface**: Build SFBAI chat interface with streaming responses, slash commands support, and 2-click actions (Copy to Outreach, Schedule Meeting, Save Note)

- [ ] **section1_contrast_check**: Implement and verify contrast check function - all text/background pairs must have >=80 grayscale point difference and meet WCAG AA (4.5:1 normal, 3:1 large)

- [ ] **section1_a11y_focus**: Implement accessibility focus system (2px solid #14B8A6 outline, 2px offset, proper tab order, ARIA roles for chat)

- [ ] **section1_review_gate**: Pass review gate 'gate_dashboard_hero' - HTML W3C validation, GSAP animations without errors, Lenis initialized once, contrast check pass, keyboard accessibility

## Section 2 - Program Overview

- [ ] **section2_program_overview**: Create program overview pages (/programs/{code}) with holders/solicitations/research tables and inline SFBAI panel

- [ ] **section2_tabler_icons**: Implement Tabler Icons as inline SVG only (shield for TLS, layout-grid for OASIS+, device-desktop for SEWP, news for briefs, send for outreach, calendar-event for calendar, book-2 for research, settings for settings)

- [ ] **section2_tilt_animations**: Implement hover tilt on program tiles (mousemove calculates rotateX/rotateY from element bounds, mouseleave returns to neutral, max 6° rotation)

- [ ] **section2_context_injection**: Implement context injection for SFBAI based on current route (program, holder, document context)

- [ ] **section2_review_gate**: Pass review gate 'gate_program_overview' - adapters toggle correctly, tables sortable without layout shift, SFBAI receives program context, hover tilt within 6° max, contrast & WCAG AA pass

## Section 3 - Holder Pages

- [ ] **section3_holder_pages**: Create holder profile pages (/programs/{code}/holders/{id}) with micro-catalog/capability sheets, activity log, and action buttons

- [ ] **section3_print_css**: Create print.css for micro-catalogs - single-page, hide nav, show meta footer with AXIVAI.COM branding

- [ ] **section3_email_drafting**: Implement Draft Email functionality with 120-150 word AI-generated emails using holder & program context

- [ ] **section3_calendar_integration**: Implement Google Calendar integration for meeting scheduling, store gcal_event_id, attach one-pager links

- [ ] **section3_review_gate**: Pass review gate 'gate_holder_page' - micro-catalog prints to one page, email drafts prefilled correctly, calendar stores event_id, activity log records actions, glassmorphism meets contrast rule

## Section 4 - Solicitations

- [ ] **section4_solicitations**: Create solicitations list and detail pages with AI summaries, compliance checklists, and next actions

- [ ] **section4_normalize_function**: Implement normalize() function to output required fields (opp_no, title, agency, status, close_date, url)

- [ ] **section4_slash_commands**: Implement /opps slash command to return closing-soon opportunities sorted by date

- [ ] **section4_performance**: Ensure no blocking JS on first contentful paint >2s (performance budget: FCP <2000ms, total JS <250KB)

- [ ] **section4_review_gate**: Pass review gate 'gate_solicitations' - normalize() outputs required fields, AI summary shows source links, /opps command works, performance budget met

## Section 5 - Research Docs

- [ ] **section5_research_docs**: Create research docs page with Google Drive sync, preview, and AI summarization to Notes

- [ ] **section5_drive_sync**: Implement Google Drive readonly integration to list title/link/mime/modified data

- [ ] **section5_ai_summaries**: Implement AI summarization that creates Notes with tags (program, holder, topic)

- [ ] **section5_pii_protection**: Ensure no PII leaks in logs - redact sensitive information, log only model name + prompt hash

- [ ] **section5_review_gate**: Pass review gate 'gate_research' - Drive sync lists metadata, summaries create tagged Notes, search highlights matches, no PII in logs

## Section 6 - Daily Briefs

- [ ] **section6_brief_engine**: Create daily brief engine with build/send/archive functionality and dashboard cards

- [ ] **section6_cron_jobs**: Set up cron jobs in Asia/Dubai timezone - news_scan.php (hourly :15), solicitations_ingest.php (00:20,06:20,12:20,18:20), brief_build.php (06:00), brief_send.php (06:05), drive_sync.php (01:00,07:00,13:00,19:00)

- [ ] **section6_email_templates**: Create email templates for daily briefs that render correctly on common email clients

- [ ] **section6_signals_labeling**: Implement clear labeling of 'Signals & Rumors' vs confirmed information in briefs

- [ ] **section6_review_gate**: Pass review gate 'gate_briefs' - cron timestamps in Asia/Dubai, archive shows sections with dates, email renders correctly, signals clearly labeled

## Section 7 - Settings & Admin

- [ ] **section7_settings_admin**: Create settings/admin panel with program toggles, OAuth setup status, SMTP config, subscriber management

- [ ] **section7_env_masking**: Implement environment variable masking - never display actual values, only masked placeholders

- [ ] **section7_blacklist**: Implement configurable blacklist for holders (default: Noble Supply & Logistics excluded from outreach)

- [ ] **section7_double_optin**: Implement double opt-in system for newsletter subscribers with CAN-SPAM compliance

- [ ] **section7_review_gate**: Pass review gate 'gate_settings' - env vars masked, program toggles persist, double opt-in working, blacklisted holders excluded

## Phase 1 - TLS MVP

- [ ] **phase1_tls_adapter**: Implement TLSAdapter with primes (ADS, Federal Resources, Quantico Tactical, SupplyCore, TSSi, W.S. Darley & Co), exclude Noble Supply & Logistics

- [ ] **phase1_micro_catalogs**: Build micro-catalog builder for TLS primes with part numbers, use cases, lead-time notes, kit support with BOM

- [ ] **phase1_outreach_compose**: Create outreach email composer with AI drafts (120-150 words, 15-min call CTA, micro-catalog context)

- [ ] **phase1_review_gate**: Pass review gate 'gate_tls_mvp' - micro-catalog prints cleanly, outreach sends via SMTP, gcal_event_id stored

## Phase 2 - Briefs

- [ ] **phase2_news_scan**: Implement news scanning system with hourly RSS/API queries using program-specific keywords

- [ ] **phase2_brief_build**: Create brief builder that aggregates headlines, labels signals & rumors, adds 'What it means' and 'Next actions'

- [ ] **phase2_brief_send**: Implement brief sending system via SMTP at 06:05 Dubai time with archive functionality

- [ ] **phase2_review_gate**: Pass review gate 'gate_briefs_phase' - brief emails at 06:05 Dubai, archive shows timestamps, signals & rumors labeled

## Phase 3 - OASIS+

- [ ] **phase3_oasis_adapter**: Implement OASIS+ adapter with holders, pools (SB/UR), domains (Business/Exec, Technical, etc.)

- [ ] **phase3_capability_sheets**: Create capability sheets for OASIS+ (PMO, Cyber, Data/AI, Training) instead of SKU-based catalogs

- [ ] **phase3_review_gate**: Pass review gate 'gate_oasis' - pools/domains visible, capability sheets templated

## Phase 4 - SEWP

- [ ] **phase4_sewp_adapter**: Implement SEWP adapter with contract holders, groups (A/B/C), contract numbers, NAICS/PSC, OEM authorizations

- [ ] **phase4_ordering_guides**: Create ordering guide shortcuts and marketplace links for SEWP IT/AV solutions

- [ ] **phase4_review_gate**: Pass review gate 'gate_sewp' - groups + contract numbers displayed, ordering guide shortcuts live

## Phase 5 - Analytics & Polish

- [ ] **phase5_analytics**: Implement analytics dashboards for engagement, conversion, content, and reliability metrics

- [ ] **phase5_telegram_bot**: Optional Telegram bot integration for pushing daily brief headlines to private channel

- [ ] **phase5_csv_importers**: Create CSV importers for bulk data management and KPI exports

- [ ] **phase5_final_a11y**: Final accessibility polish - ensure all contrast ratios pass, keyboard navigation complete, ARIA roles proper

- [ ] **phase5_review_gate**: Pass review gate 'gate_polish' - performance budgets met, A11y contrast & keyboard pass, no console errors

## Cross-Cutting Implementation

- [ ] **quality_gates_setup**: Set up automated quality gates - PHP lint, JS ESLint, CSS StyleLint, HTML5 validator, contrast checker, axe-core A11y, performance budget checks

- [ ] **api_endpoints**: Create all required API endpoints with CSRF protection and rate limiting: /ai/chat, /ai/draft_email, /ai/summarize, /ai/brief, /calendar/create, /outreach/send

- [ ] **security_implementation**: Implement comprehensive security: encrypt OAuth refresh tokens, PDO prepared statements, bcrypt passwords, least-privilege scopes, no client-side AI keys

- [ ] **footer_branding**: Add footer branding: 'All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.'

- [ ] **final_stakeholder_signoff**: Obtain final stakeholder sign-off from AXIVAI.COM after all phases complete

---

**Total Tasks:** 55  
**Review Gates:** 12  
**Security Priority:** High  
**Performance Budget:** FCP <2s, JS <250KB  
**Accessibility:** WCAG AA + 80 grayscale point contrast rule