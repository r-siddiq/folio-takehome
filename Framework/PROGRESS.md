# PROGRESS.md — Timestamped Completion Log

## Purpose

This is a short, timestamped log of completed tasks. Each entry is concise — like a well-written git commit message but with slightly more detail. Entries are written to this log immediately after a task is verified complete, BEFORE the commit.

---

## Entry Format

```markdown
## YYYY-MM-DDTHH:MM:SSZ — Phase X: Phase Title

Brief summary of what was accomplished. Focus on the "why" and the key
technical decision, not just listing what was done. One paragraph max.

Files: list/of/changed/files.php, another/file.php
```

**Fields:**
- **Timestamp:** ISO 8601 UTC (`Z` suffix)
- **Phase ID:** Matches TASKS.md phase (e.g., `Phase 1`, `Phase 2`)
- **Summary:** What was done, why, key technical decisions — one paragraph
- **Files Changed:** List of modified files (comma-separated or one per line)

---

## Guidelines

1. **Log BEFORE commit** — entry should exist before running git commit
2. **Concise but informative** — one paragraph, specific about what was done and why
3. **Include phase ID** — so TASKS.md can be referenced for details
4. **Timestamps are UTC** — ISO 8601 format with `Z` suffix
5. **Focus on the "why"** — not just what was done, but why that approach was taken

---

## Log

## 2026-05-14T20:04:00Z — Phase 1: Migration System

Added PHP migration system for schema changes. Created `migrations_runner.php` with `run_migrations()` function that tracks applied migrations in a `migrations` table and runs pending migrations in `BEGIN IMMEDIATE` transactions for atomicity. `seed.php` now calls `run_migrations()` after loading `schema.sql`. Migration files in `migrations/` directory use numbered prefix pattern (`001_initial.php`). Docker compose and tests pass.

Files: seed.php, lib/migrations_runner.php, migrations/001_initial.php

## 2026-05-14T20:30:00Z — Phase 2: Scheduled Publishing

Added scheduled publishing feature with `scheduled_at` column for documents. Staff can set a future datetime when creating documents; share links show "not yet available" message before that time. Implemented via `ALTER TABLE documents ADD COLUMN scheduled_at TEXT` migration, datetime-local input in admin.php, and availability check in view.php. Audit logging uses `AUDIT_ACTION_SCHEDULE` constant when documents are scheduled.

Files: migrations/003_add_scheduled_at.php, lib/bootstrap.php, public/admin.php, public/view.php, tests/test.php

## 2026-05-14T20:50:00Z — Phase 3: Human-Readable IDs

Added human-readable share IDs with format `slug-random6` (e.g., `welcome-7kx2pm`). The `readable_id` column was added to the `shares` table via migration, generated at share creation time using `generate_readable_id()` function in bootstrap.php. View.php now accepts `rid` parameter alongside `token` for URL-friendly document access. Share success page displays both token and rid URLs.

Files: migrations/004_add_readable_id.php, lib/bootstrap.php, public/share.php, public/view.php, tests/test.php

## 2026-05-14T21:00:00Z — Phase 4: Share by Name

Added document search by title in admin.php. Staff can type a search term to filter documents by title prefix match using `WHERE title LIKE ?` query. Search is case-insensitive, persists in URL via `?search=term`, and shows "No documents match" message when no results. Empty search shows all documents. The share flow continues to work with filtered results.

Files: public/admin.php, tests/test.php

## 2026-05-14T22:20:00Z — Phase 5: Authentication, CSRF, and Security

Added session-based authentication with login/logout, CSRF protection on POST forms, and security headers. Staff table now has `password_hash` column; login.php verifies bcrypt passwords and sets `$_SESSION['staff_id']`. `require_auth()` gates admin.php and share.php. CSRF tokens use `hash_equals()` for timing-safe comparison. Security headers (`X-Content-Type-Options`, `X-Frame-Options`) added to `render_header()`. Five staff seeded with password "password"; two documents created.

Files: public/login.php, public/logout.php, migrations/006_add_auth.php, lib/bootstrap.php, public/admin.php, public/share.php, lib/layout.php, seed.php, tests/test.php