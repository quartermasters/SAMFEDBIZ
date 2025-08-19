# SFBAI — Design & User Journey (samfedbiz.com MVP)

**Date:** 2025‑08‑19 (Asia/Dubai)  
**Owner:** Quartermasters FZC  
**Product:** samfedbiz.com  
**AI Concierge Name:** **SFBAI**  

---

## 0) Purpose & Scope
This document defines the **design**, **user journeys**, and **implementation details** for embedding **SFBAI**, an AI chat concierge, into samfedbiz.com, and aligning it with existing AI features (outreach drafts, research summaries, daily briefs). It is written for design, product, and engineering to align on MVP.

---

## 1) Principles
- **Mission‑first:** Every SFBAI response should move BD work forward (insight → action).
- **Server‑side AI only:** Keys stay on server; prompts/outputs are logged minimally (hashes + metadata).
- **Context‑aware by default:** SFBAI automatically uses page context (program, holder, doc).
- **2‑click to action:** From answer → outreach draft / meeting / saved note.
- **Printable & lightweight:** PHP templates, minimal JS, fast on shared hosting.

---

## 2) Information Architecture (with SFBAI)
- **/** Dashboard
  - **Hero with SFBAI Chatbox** (primary CTA)
  - Program Tiles: **TLS**, **OASIS+**, **SEWP**
  - Today’s Briefs (cards)
  - Upcoming Meetings (mini‑agenda)
  - Recent Outreach
- **/programs/{code}** Overview
  - Holders / Primes table
  - Solicitations (filters)
  - Research docs (Drive sync)
  - Keywords & Signals
  - *Inline SFBAI panel (context = {program})*
- **/programs/{code}/holders/{id}** Holder
  - Profile + Micro‑catalog/Capability Sheet
  - Activity & Meetings
  - Buttons: **Draft Email**, **Schedule Meeting**, **Open in SFBAI**
  - *Inline SFBAI (context = {program, holder})*
- **/programs/{code}/solicitations**
  - Table (status/close_date filters)
  - Detail: AI summary + Next Actions
  - *Inline SFBAI (context = {program, solicitation})*
- **/briefs/{code}/{date}** Brief Archive
- **/settings** Programs, OAuth, SMTP, Subscribers, Keywords

---

## 3) SFBAI Chatbox — UX & States
### 3.1 Hero Layout (Dashboard)
```
┌──────────────────────────────────────────────────────────────┐
│  Welcome back, Sana.                                          │
│  What do you want to get done?                                │
│  [ TLS ] [ OASIS+ ] [ SEWP ]                                  │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Ask SFBAI…                                             │ │
│  │  e.g., “Draft an email to SupplyCore about TLS SOE kits”│ │
│  └──────────────────────────────────────────────────────────┘ │
│  [ Summarize Research ] [ Today’s Brief ] [ Find Opps ]       │
│  Response pane (streaming)                                     │
│  ─ Suggested actions: [Copy to Outreach] [Schedule Meeting]    │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 Core Interactions
- **Input:** free text; supports slash commands.
- **Streaming output:** token‑by‑token; show stop/cancel.
- **Quick actions (contextual):**
  - Copy to Outreach → prefill email composer
  - Schedule Meeting → opens Calendar modal with suggested title
  - Save as Note → attaches to holder/opportunity
  - Open Source Links → for brief/news/solicitation references
- **History:** last 10 messages per user (locally pinned), full thread in DB.

### 3.3 Slash Commands (discoverable via `/?`)
- `/brief [tls|oasis+|sewp|all]` → Today’s intel & next actions
- `/summarize <doc_id|url>` → Exec summary + action items
- `/draft <outreach|followup> [holder_name] [topic]` → 130‑word email
- `/opps [program] [filter]` → Opportunities closing soon
- `/catalog [holder]` → Show micro‑catalog highlights
- `/schedule [title] [attendees?]` → Draft meeting event

### 3.4 Error & Empty States
- **No context:** “I can help—select a program first [TLS/OASIS+/SEWP].”
- **Rate limit/timeout:** “That took too long. Try a shorter request or use a slash command.”
- **OAuth missing:** “Connect Google Calendar/Drive in Settings to enable this action.”

---

## 4) User Journeys (End‑to‑End)
### 4.1 Sana (Outreach/Ops) — “Daily Ops in 12 Minutes”
1) **Dashboard → SFBAI**: `/brief all`  
   **SFBAI** returns: Headlines, Signals, What it Means, Next Actions.  
2) Click **Next Action** → “Prioritize TLS primes with new SOE RFQ”  
3) Open **TLS → Holders → SupplyCore**  
4) **Draft Email** (or `/draft outreach SupplyCore SOE kits`)  
   - Edit → **Send** (SMTP) → logged in Outreach.  
5) **Schedule Meeting** (auto‑suggest title + link to micro‑catalog).  
6) **Save summary as Note** on SupplyCore holder page.

**Outcome:** 1 brief consumed, 1 email sent, 1 meeting scheduled, 1 note logged.

### 4.2 Haroon (BDM) — “Weekly Review & Direction”
1) **Dashboard → SFBAI**: “Where should we focus in OASIS+ this week?”  
2) SFBAI proposes **3 priorities** with rationale (pools/domains & closing dates).  
3) Click a priority → shows relevant **holders** and **solicitations**.  
4) Use **SFBAI** on a solicitation detail: “List compliance items & gaps.”  
5) Assign **Next Steps** as Notes; create **pilot** meeting via Schedule.

### 4.3 Leadership — “Roll‑up Snapshot”
- Dashboard shows KPIs; SFBAI answers: “How many outreach emails were sent last 7 days? Meeting conversions?”

---

## 5) Visual Design System (Lean)
- **Typography:**
  - Headings: Inter 700
  - Body/UI: Inter 400/500
  - Monospace (logs/prompts): JetBrains Mono 400
- **Color Palette:**
  - Navy #0B2A4A (primary), Slate #5B708B (secondary), Sky #CDE7FF (tint)
  - Accent: Emerald #14B8A6 (positive), Amber #F59E0B (warning), Rose #F43F5E (error)
  - Backgrounds: White #FFFFFF, Off‑White #F8FAFC
- **Spacing:** 8‑pt scale (4/8/16/24/32/48)
- **Components:** Tiles, DataTable, Chips/Tags, Modals, Toasts, Empty‑states, Chat bubble, Code block, Copy button
- **Print CSS:** micro‑catalog/capability sheets single‑page; hide nav; show meta footer
- **Accessibility:** WCAG AA contrast; focus rings; ARIA for chat roles

---

## 6) Screen Specs
### 6.1 Dashboard
- **Hero**: greeting, program chips, **SFBAI chatbox** (focus on page load)
- **Today’s Briefs**: three cards (TLS/OASIS+/SEWP) with “Open” & “Share”
- **Upcoming Meetings**: next 5 with quick reschedule
- **Recent Outreach**: last 5 emails with status badges

### 6.2 Program Overview
- Filters: status, close_date, tags
- Tables: Holders, Solicitations, Research
- Right rail: **SFBAI panel** with program context pre‑loaded

### 6.3 Holder Page
- Header: name, tags (Prime/OEM/Gov), program badge
- **Micro‑catalog/Capability** section (HTML → print)
- Actions: **Draft Email**, **Schedule Meeting**, **Open in SFBAI**
- Timeline: Outreach & Meetings

### 6.4 Solicitations
- Table: opp_no, title, agency, status, close_date, URL
- Detail: AI summary, compliance checklist, Next actions
- Inline SFBAI for questions: “What’s the go/no‑go?”

### 6.5 Research Docs
- Drive list with quick preview
- Button: **Summarize with SFBAI**
- Save summary → Notes (with tags)

---

## 7) AI Behaviors & Prompts
### 7.1 System Prompts (conceptual)
- **Global:** “You are SFBAI, a federal BD assistant for TLS/OASIS+/SEWP. Be concise, action‑oriented, compliant‑aware.”
- **Outreach Draft:** “Write 120–150 words, propose 15‑min call, reference micro‑catalog capability.”
- **Doc Summary:** “Return: Summary (5 bullets) + Action Items (3 bullets). Avoid speculation; cite doc sections if possible.”
- **Brief Builder:** “Aggregate headlines; label Signals & Rumors; add ‘What it means’ and ‘Next actions’ per program.”

### 7.2 Guardrails
- No client PII beyond necessary contact names; redact emails in logs.
- Label rumor‑level intel; avoid definitive claims without source.
- Always propose a **next step**.

### 7.3 Examples (Hero)
- “Draft a concise intro to **SupplyCore** on TLS **SOE** kit availability; include schedule link.”
- “Summarize the latest **SEWP** ordering guide update and tell me what it changes for us.”
- “Show **OASIS+ Pool 1** items closing within 30 days; sort by feasibility.”

---

## 8) Data Model Additions (for SFBAI)
```
chats(id, user_id, started_at, context_json)
chat_messages(id, chat_id, role, content, created_at, tokens, model)
chat_actions(id, chat_id, type, payload_json, created_at)
notes(id, program_id, holder_id, opportunity_id, author_id, title, body, created_at)
```
- **context_json**: `{"program":"tls","holder_id":123,"doc_id":null}`
- **chat_actions**: `copy_to_outreach`, `schedule_meeting`, `save_note`, `open_link`

**Indexes:** `chat_messages(chat_id, created_at)`, `notes(holder_id, created_at)`

---

## 9) API Endpoints (Server‑side)
- `POST /ai/chat` → body: `{message, context}`; returns `{response, actions[]}`
- `POST /ai/draft_email` → `{holder_id, topic}`
- `POST /ai/summarize` → `{doc_id|url}`
- `POST /ai/brief` → `{program|all}` (cron normally triggers)
- `POST /calendar/create` → `{title, when, attendees[], link?}`
- `POST /outreach/send` → `{to, subject, body}`

**Security:** CSRF on all POST; role checks; rate limit per user.

---

## 10) Component Specs (Key)
- **Chat Input:** 1‑line grows to 5; `/` opens command list; `Esc` blurs.
- **Response Bubble:** markdown + link chips; inline code for commands emitted.
- **Action Bar:** primary (Copy to Outreach), secondary (Schedule, Save Note)
- **Toast:** success/error confirmations for downstream actions

---

## 11) Metrics & Telemetry
- **Engagement:** daily chats/user, commands used, actions taken
- **Conversion:** outreach drafts → sent; chats → meetings
- **Content:** docs summarized/day; brief opens/clicks
- **Reliability:** error rate, avg response latency, token usage by model

---

## 12) Accessibility & Internationalization
- All interactive elements reachable via keyboard; ARIA roles for `log` and `status` in chat.
- Date/time localized to **Asia/Dubai**; 24‑hour time.

---

## 13) Acceptance Criteria (SFBAI‑specific)
1) **Hero chatbox** loads, streams responses, and supports slash commands.
2) From a **Holder** page, SFBAI answers use `{program, holder}` context.
3) **Copy to Outreach** creates a prefilled email draft; **Schedule Meeting** opens modal with suggested title.
4) **Summarize with SFBAI** creates a Note attached to the doc/holder.
5) `/brief all` returns the latest generated brief sections with timestamps.

---

## 14) Delivery Plan (MVP)
**Sprint 1 (Backend foundations)**  
- DB migrations for chat tables + notes  
- `/ai/chat` controller with provider client (OpenAI first)  
- Rate limiting + logging (model, tokens, hash)

**Sprint 2 (Hero Chat + Program Context)**  
- Dashboard hero UI + streaming  
- Context injection (program/holder inferred from route)  
- Quick actions (copy/schedule/save)

**Sprint 3 (Integrations & Docs)**  
- Drive summaries → Notes  
- Outreach compose flow hookup  
- Calendar create modal hookup

**Sprint 4 (Briefs & Slash Commands)**  
- `/brief`, `/draft`, `/summarize`, `/opps`  
- Timestamps + archive links

**Sprint 5 (Polish & A11y)**  
- Empty states, errors, i18n time  
- Print CSS for micro‑catalogs  
- QA + acceptance criteria pass

---

## 15) Risks & Mitigations (SFBAI)
- **Token cost spikes** → enforce per‑message max tokens; use shorter templates.
- **Latency** → stream responses; prefetch briefs; cache summaries.
- **Context leakage** → strict server context resolver; never accept client‑supplied IDs blindly.
- **Shared hosting limits** → compact PHP; debounce; cron spreading.

---

## 16) Open Questions
1) Branding: keep “SFBAI” badge in chat header or only in placeholder text?  
2) Which models to enable at launch? (gpt‑4.1‑mini vs enterprise; Gemini fallback)  
3) Default length for outreach drafts—130 words or vary by program?  
4) Should `/schedule` support natural language times ("tomorrow 10:00")?

---

## 17) Appendix — Copy Blocks
**Hero Placeholder:** “Ask **SFBAI**… e.g., ‘Draft an email to SupplyCore about TLS SOE kits’.”  
**Empty State (Program page):** “No context set. Select a program or try `/brief all`.”  
**Quick Action Labels:** Copy to Outreach · Schedule Meeting · Save as Note · Open Source

