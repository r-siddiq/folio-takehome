# TASKS.md — Active Task Tracking

## Purpose

TASKS.md is an **outline pointer** to the framework documents, not a verbose task list. It bridges PLAN.md phases to execution.

```
PLAN.md phase → TASKS.md outline → RESEARCH agent (A) → IMPLEMENT agent (B) → PROGRESS.md log
```

## Document Chain

| Document | Role | Tag |
|----------|------|-----|
| CLAUDE.md | Orchestrator patterns, agent dispatch template | FIXED |
| GOALS.md | What + priority (milestone criteria) | FIXED |
| PLAN.md | How (architecture, phase sequence) | FIXED |
| TASKS.md | Doing (ephemeral task breakdown) | FIXED |
| PROGRESS.md | Done (timestamped log) | FIXED |

## Workflow

```
Orchestrator reads PLAN.md → identifies next phase
→ Dispatches RESEARCH agent (A) to investigate how to complete phase
→ RESEARCH agent reads README.md spec + searches online for best practices
→ Returns findings with architectural recommendations
→ Orchestrator synthesizes research → writes detailed IMPLEMENT task to TASKS.md
→ Orchestrator dispatches IMPLEMENT agent (B) to execute
→ IMPLEMENT agent executes → reports → orchestrator verifies
→ On success: log to PROGRESS.md → commit → clear from TASKS.md
→ On failure: mark blocked → await guidance
→ Repeat until all phases complete (TASKS.md is empty)
```

**CRITICAL:** The workflow has TWO distinct agent dispatches per phase:
1. **RESEARCH (A)** — Investigates HOW to implement before any code is written
2. **IMPLEMENT (B)** — Executes based on research findings, with full context

NEVER skip the research phase. The README is deliberately vague and requires architectural decisions.

## Task State Legend

```
pending     → in_progress → completed
                        ↘ blocked (dependency or error)
```

| State | Who Sets | Meaning |
|-------|----------|---------|
| `pending` | Orchestrator | Task queued, not yet dispatched |
| `in_progress` | Orchestrator | Agent dispatched, working on task |
| `completed` | Agent → Orchestrator | Task done, verified, logged to PROGRESS.md, committed |
| `blocked` | Agent → Orchestrator | Dependency not met or error encountered; await guidance |

## Phase Sequence (from PLAN.md)

| Phase | Task | Commit |
|-------|------|--------|
| Phase 1: Foundation | 1A (research), 1B (implement) | `feat: add PHP migration system` |
| Phase 2: Scheduling | 2A, 2B | `feat: add scheduled publishing` |
| Phase 3: Readable IDs | 3A, 3B | `feat: add human-readable document IDs` |
| Phase 4: Search | 4A, 4B | `feat: add document search by title` |

## Pending Tasks

---

## Phase 1: Foundation — Migration System

### Task 1A: Research Migration Approach
**State:** pending
**Purpose:** Verify migration approach from PLAN.md, research best practices

**Research Checklist:**
- [ ] Confirm `migrations/` directory structure (001_description.php pattern)
- [ ] Confirm batch runner uses `BEGIN IMMEDIATE` transaction
- [ ] Confirm migrations table tracks applied migrations
- [ ] Search online for PHP migration best practices for SQLite
- [ ] Confirm seed.php calls run_migrations() before seeding

**Deliverable:** Written confirmation of approach with any refinements needed.

### Task 1B: Implement Migration System
**State:** pending
**Purpose:** Add working migration system to the project

**Files to create:**
- `migrations/001_initial.php` — base schema creation

**Files to modify:**
- `seed.php` — call run_migrations() before seeding

**Success Criteria:**
- `docker compose up` succeeds from fresh clone
- `docker compose exec app php tests/test.php` exits 0
- Migrations table created on fresh seed
- Migration runner applies pending migrations atomically

---

## Phase 2: Scheduled Publishing

### Task 2A: Research Scheduling Implementation
**State:** pending
**Purpose:** Confirm approach for scheduled publishing feature

### Task 2B: Implement Scheduled Publishing
**State:** pending
**Purpose:** Add scheduled_at column, scheduling input, availability check

---

## Phase 3: Human-Readable IDs

### Task 3A: Research Readable ID Implementation
**State:** pending
**Purpose:** Confirm ID format decision and URL structure

### Task 3B: Implement Human-Readable IDs
**State:** pending
**Purpose:** Add readable_id column, generation, id parameter resolution

---

## Phase 4: Share by Name

### Task 4A: Research Search Implementation
**State:** pending
**Purpose:** Confirm prefix search approach and UI

### Task 4B: Implement Share by Name
**State:** pending
**Purpose:** Add search input and prefix filtering in admin.php

---

## Phase 5: Authentication, CSRF, and Security

### Task 5A: Research Auth Implementation
**State:** completed (findings synthesized)
**Purpose:** Confirm session-based auth, CSRF token, and security header approach

### Task 5B: Implement Authentication and Security Hardening
**State:** completed
**Purpose:** Add login/logout, session auth, CSRF protection, security headers

**Files to create:**
- `public/login.php` — email + password form, password_verify(), set session
- `public/logout.php` — session_destroy(), redirect
- `migrations/006_add_auth.php` — ALTER TABLE staff ADD COLUMN password_hash TEXT

**Files to modify:**
- `lib/bootstrap.php` — add session_start(), require_auth(), csrf_token(), validate_csrf(), rewrite current_staff() to use session
- `public/admin.php` — require_auth() at top, CSRF hidden field, search wildcard escape
- `public/share.php` — require_auth() at top, CSRF hidden field
- `lib/layout.php` — security headers (X-Content-Type-Options, X-Frame-Options)
- `seed.php` — 5 staff with password_hash('password'), 2 docs, print credentials
- `tests/test.php` — +5 tests: login ok, login fail, unauthenticated redirect, CSRF rejection, search wildcard escape

**Success Criteria:**
- `php tests/test.php` exits 0 (12 tests total: 7 existing + 5 new)
- Login with correct email/password → redirects to /admin.php with session
- Login with wrong password → error shown, no session
- Access admin.php without session → Location: /login.php header
- POST to admin.php without CSRF token → 419 response
- Search for % → matches literal % not all documents
- `php seed.php` prints credential table
- `docker compose up` succeeds from fresh clone

---

## Phase Completion Checklist

For each phase:
- [ ] Research task (A) completed and findings synthesized by orchestrator
- [ ] Implementation task (B) completed with verification
- [ ] PROGRESS.md entry written
- [ ] Commit made with descriptive message
- [ ] TASKS.md entry cleared (set state to completed)

---

## How to Use /goal

A `/goal` command maps to one phase from the Phase Sequence above. The orchestrator:

1. Reads GOALS.md → identifies priority
2. Reads PLAN.md → finds phase for that goal
3. Dispatches RESEARCH agent (A) → waits for findings
4. Synthesizes research → writes detailed task to TASKS.md
5. Dispatches IMPLEMENTATION agent (B) → waits for results
6. Verifies → logs to PROGRESS.md → commits

Example:
```
/goal Implement scheduled publishing
→ Phase 2 from PLAN.md
→ Task 2A (research) → orchestrator synthesizes → Task 2B (implement) → commit
```