# Product Requirements Document (PRD)

**Product:** samfedbiz.com  
**Owner:** Quartermasters FZC (Copyright © Quartermasters FZC)  
**Stakeholders:** AXIVAI.COM DEVELOPER AND COPYRIGHT OWNERS IN 2025  
**Initial Programs:** DLA **TLS** (SOE/F&ESE), **OASIS+**, **NASA SEWP**  
**Hosting:** Hostinger *Business* (shared)  
**Tech:** HTML5, PHP 8.x, Vanilla JS, MySQL/MariaDB, Cron jobs (no React)  
**Timezone:** Asia/Dubai (UTC+4)

---

## 1. Vision & Goals
**Vision:** A single, secure portal where St. Michael LLC runs federal BD: research programs, track primes/holders, generate AI strategies and outreach, schedule meetings, and publish a Daily Brief—all modular so new vehicles (e.g., SEWP, OASIS+) are plug‑and‑play.

**Primary Goals**
1. Centralize program intelligence (TLS, OASIS+, SEWP) and research docs.
2. Automate **outreach & meeting scheduling** (Google Calendar) with AI‑drafted emails.
3. Maintain vendor/prime/holder micro‑catalogs and capability sheets.
4. Daily Briefs: scan news/rumors, summarize with AI, email subscribers.
5. Be **modular**: onboard new programs without rewriting core.
6. Enforce **security best practices** for API keys, OAuth tokens and data.

**Non‑Goals**
- Not a replacement for primes’ ERPs or government ordering systems.  
- No in‑browser AI key exposure; all AI calls are server‑side.

---

## 2. Personas & Key Jobs‑to‑Be‑Done
**Haroon (BDM):** Decides niches, reviews briefs, leads calls, signs off on pilots.  
**Sana (Outreach/Ops):** Curates research, manages contacts, schedules meetings, drives follow‑ups.  
**Leadership (Quartermasters FZC):** Oversees delivery quality, metrics, compliance, and IP.

**Top JTBD**
- “Show me today’s intel across TLS/OASIS+/SEWP and what we should do next.”
- “Give me a one‑pager I can email to **this** prime/holder **now**.”
- “Schedule a meeting, attach the right micro‑catalog/capability sheet, log it.”
- “Summarize this research doc and extract action items.”
- “Track solicitations by program; what’s closing soon?”

---

## 3. Scope
### 3.1 Programs (modular)
- **TLS (DLA):** SOE/F&ESE focus; prime‑vendor model; part‑numbered kits.  
- **OASIS+:** Services GWAC; awardees/holders by pool & domain.  
- **SEWP:** NASA GWAC for IT/AV solutions; contract holders and catalog scope.

> The platform must let Admins toggle programs on/off and add new ones via a simple **adapter** pattern.

### 3.2 Prime/Holder Catalogs
- TLS: ADS, Federal Resources, Quantico Tactical, SupplyCore, TSSi, W.S. Darley & Co. *(Internal policy: exclude Noble Supply & Logistics from outreach)*.  
- OASIS+/SEWP: maintain **holders** lists with key attributes (pool/domain for OASIS+, SEWP group/contract no./NAICS/PSC).

### 3.3 Research Repository
- Sync designated Google Drive folders; full‑text search; AI summaries; tagging by program/holder.

### 3.4 Outreach & Meetings
- Contact manager (prime/holder/OEM/Gov POC).  
- AI‑drafted outreach emails (120–150 words) using micro‑catalog/capability context.  
- Google Calendar event creation + storage of `gcal_event_id`; meeting notes.

### 3.5 Daily Briefs
- Scheduled scans of public sources (RSS/APIs/curated links).  
- AI summary per program with sections: *Headlines · Signals & Rumors · What It Means · Next Actions*.  
- Newsletter email to subscribers; web archive.

---

## 4. Functional Requirements
### 4.1 Program Framework
- **Adapter Interface** (PHP):
  - `code(): string` → `tls` | `oasisplus` | `sewp`  
  - `name(): string` → display label  
  - `keywords(): array` → for brief scans  
  - `listPrimesOrHolders(): array` → structured list  
  - `fetchSolicitations(): array` → raw list from source  
  - `normalize(array $raw): array` → `{opp_no,title,agency,status,close_date,url}`  
  - `extraFields(): array` → program‑specific fields (e.g., TLS scope; OASIS+ domain; SEWP group)
- **Program Registry**: associative map `{code => Adapter}`; admin can toggle availability.

### 4.2 Data Model (keywords only)
- `programs(id, code, name, is_active)`  
- `holders(id, program_id, name, type, status)` *(TLS primes; SEWP/OASIS+ holders)*  
- `holder_meta(holder_id, k, v)` *(e.g., tls_scope, awards, sewp_group, contract_no, naics)*  
- `oems(id, name)`; `parts(id, oem_id, part_no, short_desc, category)`  
- `prime_catalog(id, holder_id, part_id)` *(TLS micro‑catalogs)*  
- `opportunities(id, program_id, opp_no, title, agency, status, close_date, url)`  
- `opportunity_meta(id, opportunity_id, k, v)` *(pool, domain, psc, set_aside, labor_cats)*  
- `research_docs(id, drive_file_id, title, program_id, holder_id)`  
- `outreach(id, program_id, holder_id, contact, email, subject, status)`  
- `meetings(id, program_id, holder_id, gcal_event_id, start_iso, end_iso, summary)`  
- `news_items(id, program_id, source, url, title, tag, score)`  
- `daily_briefs(id, program_id, date, html_path)`  
- `subscribers(id, email, status)`  
- `users(id, name, email, role, pass_hash)`; `oauth_tokens(id, provider, access_token_enc, refresh_token_enc, expires_at)`

### 4.3 TLS Module
- **Entities**: Primes, OEMs, Parts, Micro‑catalogs (per prime), Research.
- **Micro‑catalog Builder**: one‑page HTML per prime with PN lists, use cases, lead‑time notes.  
- **Kitting Support**: allow grouping parts into kits with BOM + label schema.  
- **Exclude List**: configurable holder blacklist (default: Noble Supply & Logistics).  
- **Keywords**: “DLA TLS, SOE, F&ESE, ADS, Federal Resources, Quantico Tactical, SupplyCore, TSSi, W.S. Darley”.

### 4.4 OASIS+ Module
- **Entities**: Holders, Pools (SB/UR), Domains (Business/Exec, Technical, etc.).  
- **Capabilities Sheets** (instead of SKUs): PMO, Cyber, Data/AI, Training, etc.  
- **Solicitations**: Normalize pool/domain, evaluation factors, submission format.

### 4.5 SEWP Module
- **Entities**: Contract Holders, Groups (A/B/C etc.), Contract Nos, NAICS/PSC, OEM auths.  
- **Capabilities Sheets**: IT/AV solutions, COTS, Services, Tech Refresh notes.  
- **Ordering Guide** shortcuts, quote/delivery expectations, tool links.  
- **Keywords**: “NASA SEWP, SEWP V, GWAC IT/AV, ordering guide, marketplace”.

### 4.6 Outreach & Meetings
- **Contacts** with tags (Prime/OEM/Gov).  
- **Email Draft** → AI uses holder + program context (micro‑catalog or capability sheet).  
- **Meeting Create** → Google Calendar; attach link to PDF/HTML one‑pager; store `gcal_event_id`.  
- **Activity Log** per holder.

### 4.7 Research Repository
- **Drive Sync**: folder‑level pull; title, link, mime, modified time.  
- **AI Summaries** with action items; add tags (program, holder, topic).

### 4.8 Daily Brief Engine
- **Scan** (hourly): query sources by each adapter’s keywords.  
- **Build** (06:00 Dubai): compile headlines → AI executive summary + “What it means” + “Next actions”.  
- **Send** (06:05 Dubai): email subscribers; archive HTML.

### 4.9 Admin & Settings
- Program toggles; keyword editor per program.  
- SMTP settings; subscriber management (double opt‑in).  
- OAuth setup status (Calendar/Drive).  
- API keys via env vars only (no plaintext display).  
- Footer branding: “© Quartermasters FZC. All rights reserved.”

---

## 5. Integrations
**Google Calendar**: create events, attendees, reminders; store event IDs and notes.  
**Google Drive**: read‑only sync of research folders; file links surface in UI.  
**OpenAI & Gemini**: server‑side only; summarization, outreach drafts, strategy suggestions.  
**Email (SMTP/PHPMailer)**: send outreach and daily briefs.  
**News/Solicitation Sources**: RSS/APIs; SAM.gov/SearchAPI/SERPAPI (rate‑limit aware).  
**(Optional)** Telegram bot for pushing Daily Brief headlines to a private channel.

---

## 6. Security, Privacy & Compliance
- Keys in **Hostinger hPanel environment**; never commit to repo; no client‑side AI calls.  
- Encrypt OAuth refresh tokens; rotate secrets; least‑privilege scopes (Calendar Events, Drive Readonly).  
- CSRF tokens, PDO prepared statements, bcrypt for passwords.  
- Logging: redact PII/secrets; store model name & prompt hash with AI outputs.  
- Compliance posture: TAA/Berry/ITAR/EAR flags at SKU level (TLS); NIST 800‑171/CUI handling basics; GDPR/CAN‑SPAM for subscriber mailings.

---

## 7. Information Architecture (no React)
- `/` Dashboard: program tiles (TLS/OASIS+/SEWP); today’s briefs; upcoming meetings.  
- `/programs/{code}` Overview: holders, solicitations, research, keywords.  
- `/programs/{code}/holders/{id}`: profile, micro‑catalog (TLS) or capability sheet (SEWP/OASIS+), activity & meetings.  
- `/programs/{code}/solicitations`: filters; detail with AI summary/next steps.  
- `/briefs/{code}/{date}`: Daily Brief archive.  
- `/settings`: programs, API, OAuth, subscribers.

---

## 8. UX Requirements
- Clean one‑page micro‑catalogs (print‑to‑PDF friendly).  
- 2‑click outreach: **Draft email** → **Send**.  
- Meeting creation in one modal (date/time/attendees + attach doc).  
- Daily Briefs have scannable sections; highlight “What it means” and “Next actions”.

---

## 9. Acceptance Criteria (MVP)
1. Admin can enable TLS/OASIS+/SEWP; tiles appear on dashboard.  
2. TLS primes show with micro‑catalogs; OASIS+/SEWP show holders with capability sheets.  
3. Research docs from Drive list and can be AI‑summarized with action items.  
4. Outreach email draft generated from a holder page; user can edit and send.  
5. Meeting scheduled on Google Calendar from a holder page; event ID stored.  
6. Daily Brief generated and emailed at 06:05 Dubai; archive available.  
7. All secrets stored as env vars; no client‑side AI calls; basic auth/roles in place.

---

## 10. Metrics & Reporting
- **Outreach funnel**: drafts sent, replies, meetings booked, pilots created.  
- **Brief engagement**: open rate, click rate, time‑to‑read.  
- **Ops**: OTD for pilots (when tracked), quote turnaround time, prime coverage breadth.  
- **Content**: # of research docs ingested, % summarized, search CTR.

---

## 11. Roadmap
**Phase 0 (Prep):** DB schema, adapter interfaces, auth, env/secrets.  
**Phase 1 (TLS MVP):** TLSAdapter, primes, micro‑catalogs, outreach, meetings, Drive sync.  
**Phase 2 (Briefs):** news scan, AI brief build/send, archive.  
**Phase 3 (OASIS+):** adapter, holders, capability sheets, solicitations.  
**Phase 4 (SEWP):** adapter, holders/groups, ordering guide links, capability sheets.  
**Phase 5:** analytics dashboards, Telegram bot, CSV importers, KPI exports.

---

## 12. Risks & Mitigations
- **API key leakage** → env vars only; rotate; masked UI; no logs of secrets.  
- **Shared hosting limits** → cron cadence tuned; heavy tasks chunked; optional future VPS for workers.  
- **Source rate limits** → caching, exponential backoff, multiple feeds.  
- **Data freshness** → visible timestamps; manual refresh button per program.  
- **Program variance** → adapter abstraction; `*_meta` tables for specifics.  
- **Internal policy conflicts** (e.g., exclude Noble) → maintain blacklist in settings.

---

## 13. Open Questions
1. Exact Drive folder IDs for each program?  
2. Initial list of OASIS+ domains/pools and SEWP holders we want to preload?  
3. SMTP sender and DNS (SPF/DKIM/DMARC) ready for newsletters?  
4. Any OEM logos/brand guidelines for micro‑catalog headers?  
5. Do we want Telegram alerts in MVP or Phase 5?

---

## 14. Appendix
### 14.1 Cron Schedule (Asia/Dubai)
- `news_scan.php` → hourly at :15  
- `solicitations_ingest.php` → 00:20, 06:20, 12:20, 18:20  
- `brief_build.php` → 06:00  
- `brief_send.php` → 06:05  
- `drive_sync.php` → every 6 hours (01:00/07:00/13:00/19:00)

### 14.2 Environment Variables (examples; set in hPanel only)
```
APP_ENV=production
TIMEZONE=Asia/Dubai
OPENAI_API_KEY=__SET_IN_HPANEL__
GEMINI_API_KEY=__SET_IN_HPANEL__
GOOGLE_CLIENT_ID=__SET_IN_HPANEL__
GOOGLE_CLIENT_SECRET=__SET_IN_HPANEL__
GOOGLE_REDIRECT_URI=https://samfedbiz.com/oauth/google/callback
SMTP_HOST=__SET_IN_HPANEL__
SMTP_USER=__SET_IN_HPANEL__
SMTP_PASS=__SET_IN_HPANEL__
```

### 14.3 Sample Outreach Prompt (server‑side)
> “Draft a 130‑word professional intro email to {{holder_name}} about {{program_code}}. Use the attached micro‑catalog/capability list, suggest a 15‑minute discovery call, and mention we can schedule via Google Calendar. Keep tone concise and action‑oriented.”

---

**End of PRD**

