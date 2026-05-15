# PLAN.md — Folio Take-Home Architectural Overview

## Project Overview

**Folio** is a small PHP/SQLite document-sharing application running in Docker. Staff create documents, generate one-time share links, and recipients view documents via secure tokens.

**Stack:** PHP 8+, SQLite, Docker Compose, vanilla PHP (no framework)

**Source:** `folio-takehome/README.md` — the authoritative spec for this project.

**Project Location:** `folio-takehome/` subdirectory of this orchestration directory.

---

## Current Architecture

### Database Schema (`schema.sql`)

```sql
staff        (id INTEGER PRIMARY KEY, email TEXT UNIQUE, name TEXT)
documents    (id INTEGER PRIMARY KEY, title TEXT, body TEXT, created_by INTEGER, created_at TEXT)
shares       (id INTEGER PRIMARY KEY, document_id INTEGER, token TEXT UNIQUE, recipient_email TEXT, created_at TEXT)
audit_log    (id INTEGER PRIMARY KEY, staff_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, details TEXT, created_at TEXT)
```

### Key Files

| File | Purpose |
|------|---------|
| `lib/bootstrap.php` | DB connection (`db()`), current user (`current_staff()`), audit logging (`audit_log()`), token generation (`random_token()`), escaping (`h()`) |
| `lib/layout.php` | Shared HTML header/footer rendering |
| `public/index.php` | Redirects to `/admin.php` |
| `public/admin.php` | Document list, document creation form, share link generation |
| `public/share.php` | Creates share link for a document, generates token, records audit log |
| `public/view.php` | Recipient view — resolves token to document, renders content |
| `seed.php` | Creates initial staff, document, and share for development |
| `tests/test.php` | Simple test harness; runs seed.php then executes test functions |

### How Shares Work (Current)

1. Staff creates document in `admin.php`
2. Staff clicks "Create share" → `share.php?doc=N`
3. Staff enters recipient email → share record created with `random_token()` (32-char hex)
4. Share link rendered as `http://host/view.php?token=TOKEN`
5. Recipient visits → `view.php` looks up token, joins to document, renders

### Audit Log Pattern

From `lib/bootstrap.php`:
```php
audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void
```

Actions are defined as constants to prevent typos:
```php
const AUDIT_ACTION_CREATE = 'create';
const AUDIT_ACTION_SCHEDULE = 'schedule';
const AUDIT_ACTION_UPDATE = 'update';
const AUDIT_ACTION_DELETE = 'delete';
const AUDIT_ACTION_SHARE = 'share';
```

---

## Migration Strategy

**Decision:** Simple PHP migration files in `migrations/` directory with batch transaction support.

**Approach:**
1. Create `migrations/` directory
2. Each migration: `001_description.php`, `002_description.php`, etc.
3. Migration runner uses `BEGIN IMMEDIATE` transaction — if any migration fails, entire batch rolls back
4. Each migration:
   - Checks if already applied (via `migrations` tracking table)
   - If not applied: runs ALTER/CREATE, inserts record into `migrations` table
5. `seed.php` calls `run_migrations()` before seeding

**Schema Source of Truth:** `schema.sql` is canonical — always reflects current state. Migrations update it or it is kept in sync manually. Reviewers can read one file instead of all migrations in order.

**Why simple PHP files with batch transactions:**
- This is a tiny app — simplicity wins
- SQLite doesn't support many migration tools
- PHP files are readable and debuggable
- Batch transactions prevent partial migration state on failure

**Migration file structure:**
```php
<?php
// migrations/001_add_scheduled_at.php
$migrations = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchAll();
$applied = array_column($migrations, 'name');
if (!in_array('001_add_scheduled_at', $applied)) {
    db()->exec("ALTER TABLE documents ADD COLUMN scheduled_at TEXT");
    db()->exec("INSERT INTO migrations (name) VALUES ('001_add_scheduled_at')");
}
?>
```

**Batch runner (improved):**
```php
$pdo->beginTransaction();
try {
    foreach ($pendingMigrations as $file) {
        $pdo->exec(file_get_contents($file));
        $pdo->exec("INSERT INTO schema_migrations ...");
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## Feature Plan: Scheduled Publishing

### Overview

Add ability to schedule a document to become visible at a specific datetime. Before that time, share link shows "not yet available."

### Schema Change

**Migration file:** `migrations/001_add_scheduled_at.php`

```sql
ALTER TABLE documents ADD COLUMN scheduled_at TEXT;
```

### Backend Changes

**`view.php`** — Check scheduled_at before rendering:
- If `scheduled_at IS NULL` or `scheduled_at <= now()`: show document
- If `scheduled_at > now()`: show "not yet available" message

**`admin.php`** — Add scheduling input:
- Add datetime input field when creating documents
- Pass `scheduled_at` through POST; default to `NULL` if empty

**`seed.php`** — Seed data uses `NULL` scheduled_at (existing behavior)

### Audit Logging

Log scheduling actions using constant:
```php
audit_log(AUDIT_ACTION_SCHEDULE, 'document', $docId, ['scheduled_at' => $scheduledAt])
```

### UI "Not Yet Available" Message

```html
<div class="centered-message">
    <h1>Document not yet available</h1>
    <p>This document is scheduled to become available at [datetime].</p>
</div>
```

### Testing

```php
test('scheduled document shows not-yet-available', function () {
    // Create doc with future scheduled_at
    // Fetch view.php output
    // Assert "not yet available" message present
});
test('null scheduled_at shows content', function () {
    // Create doc with NULL scheduled_at
    // Assert content shown
});
```

---

## Feature Plan: Human-Readable Document IDs

### Overview

Assign each document a short, readable, unique identifier (e.g., `welcome-2026`, `onboarding-pkg-3k`) for URLs and human communication.

### Design Decisions Required

**ID Format:**
- 6–12 characters: `[a-zA-Z0-9\-]`
- Collision-resistant: check uniqueness before insert, regenerate on conflict (max 3 attempts)
- Examples: `welcome-2026`, `onboarding-pkg-3k`, `folio-7qx4`

**Token Decision (README: intentionally not specified — decide and justify):**
- **Complement (recommended):** Keep existing token + add readable_id. Pro: no breaking changes. Con: two ID systems.
- **Replace:** Replace token with readable_id. Pro: simpler. Con: existing links break.

**URL Structure (your call):**
- Option A: `/view.php?id=welcome-2026` — replaces token param
- Option B: `/view.php?rid=welcome-2026` — new param, coexist with token
- Option C: `/w/welcome-2026` — short URL prefix

**Recommendation:** Option B (`rid`) — coexists with token, no breaking changes, clear intent.

### Schema Change

**Migration file:** `migrations/002_add_readable_id.php`

```sql
ALTER TABLE documents ADD COLUMN readable_id TEXT UNIQUE;
```

### ID Generation Algorithm

```
1. Generate candidate: [slug]-[random6]
   - slug: first word of title, lowercase, alphanumeric only
   - random6: 6 chars from [a-zA-Z0-9]
2. Check uniqueness in DB
3. If collision: regenerate (max 3 attempts), then fail with error
4. Store on insert
```

### Backend Changes

**Document creation** (`admin.php`):
- On insert: generate and assign readable_id
- Display readable_id in admin document list

**Document view** (`view.php`):
- Accept `rid` parameter (readable_id)
- Resolve document by readable_id if `rid` param present
- Fall back to token lookup if no `rid` param

### Testing

```php
test('readable_id is generated and unique', function () {
    // Create two documents
    // Assert both have readable_id
    // Assert readable_ids are unique
    // Assert format matches ^[a-zA-Z0-9\-]{6,12}$
});
test('view resolves by readable_id', function () {
    // Create doc, get its readable_id
    // Fetch view.php?rid={readable_id}
    // Assert document content shown
});
```

---

## Feature Plan: Share by Name

### Overview

Staff can search for a document by title before creating a share link, instead of scrolling a list.

### Design Decision

**Search type:** Prefix match — balances usability and precision.

**Upgrade path:** If search quality degrades beyond ~100 documents, migrate to SQLite FTS5 for natural language query support, snippet highlighting, and relevance ranking.

### Schema Change

No schema change required for basic prefix search — `LIKE 'term%'` works without index on small tables.

Consider adding index when document count grows:
```sql
CREATE INDEX idx_documents_title ON documents(title);
```

### Backend Changes

**`admin.php`** — Add search:
- Add search input field above document list
- Filter query: `WHERE title LIKE ?` with prefix pattern (`term%`)
- Case-insensitive search
- Preserve search term in form after submit

**Share flow with search:**
1. Staff types search term → filtered list appears
2. Each result has "Create share →" link
3. Staff clicks → goes to `share.php?doc=N` → normal share flow

### Testing

```php
test('search returns prefix matches', function () {
    // Seed docs with titles "Welcome Packet", "Welcome Guide", "Other Doc"
    // Search for "Welcome"
    // Assert "Welcome Packet" and "Welcome Guide" returned
    // Assert "Other Doc" not returned
});
test('empty search returns all', function () {
    // Empty search term
    // Assert all documents returned
});
test('no match returns empty', function () {
    // Search for "xyzzy"
    // Assert no documents returned
});
```

---

## Implementation Sequence (Recommended)

### Phase 1: Foundation
1. Set up `migrations/` directory
2. Create migration runner with batch transaction support (`BEGIN IMMEDIATE`)
3. Verify `schema.sql` reflects current state
4. Verify docker compose up still works
5. Commit: `docs: add migration system`

### Phase 2: Scheduled Publishing
1. Create `migrations/001_add_scheduled_at.php`
2. Define `AUDIT_ACTION_SCHEDULE` constant in bootstrap.php
3. Update `admin.php` to accept `scheduled_at` input
4. Update `view.php` to check availability
5. Add "not yet available" UI
6. Add audit logging for scheduling
7. Write test
8. Commit: `feat: add scheduled publishing`

### Phase 3: Human-Readable IDs
1. Create `migrations/002_add_readable_id.php`
2. Implement ID generation with slug + random format
3. Update document creation to assign readable_id
4. Update `view.php` to resolve by `rid` parameter
5. Display readable_id in admin list
6. Write test
7. Commit: `feat: add human-readable document IDs`

### Phase 4: Share by Name
1. Add search input to `admin.php`
2. Implement prefix search filtering
3. Verify search works in share flow
4. Write test
5. Commit: `feat: add document search by title`

---

## Key Files to Modify

| File | Changes |
|------|---------|
| `migrations/001_add_scheduled_at.php` | New — add scheduled_at column |
| `migrations/002_add_readable_id.php` | New — add readable_id column |
| `lib/bootstrap.php` | Add `AUDIT_ACTION_*` constants |
| `public/admin.php` | Add scheduling input, search input |
| `public/view.php` | Check scheduled_at, resolve by `rid` parameter |
| `seed.php` | Call migrations before seeding |
| `tests/test.php` | Add tests per feature |

---

## TASKS.md Reference

See `TASKS.md` for task breakdown by phase. Each phase has two tasks (A=research, B=implement) and ends with a commit:

| Phase | Research Task | Implement Task | Commit Message |
|-------|--------------|----------------|---------------|
| Phase 1: Foundation | 1A | 1B | `feat: add PHP migration system for schema changes` |
| Phase 2: Scheduling | 2A | 2B | `feat: add scheduled publishing` |
| Phase 3: Readable IDs | 3A | 3B | `feat: add human-readable document IDs` |
| Phase 4: Search | 4A | 4B | `feat: add document search by title` |

**Phase completion workflow:**
1. Verify task completion against success criteria
2. Update PROGRESS.md entry (timestamp, phase ID, summary, files changed)
3. `cd folio-takehome && git add . && git commit -m "<commit message>"`
4. Update TASKS.md to mark phase tasks completed

---

## Architecture Improvements (from first attempt)

| Issue | Before | After |
|-------|--------|-------|
| Schema source of truth | `001_initial_schema.sql` clones schema.sql | `schema.sql` is canonical, always current |
| Migration failures | Partial state on failure | Batch transaction with `BEGIN IMMEDIATE` — all or nothing |
| Audit actions | Arbitrary strings | Constants + `in_array()` enforcement |
| Search upgrade path | Not documented | FTS5 documented as upgrade path when scale demands |

These improvements informed this plan and should be applied consistently across all phases.

---

## Phase 5: Authentication, CSRF, and Security Hardening

### Overview

Add proper session-based authentication, CSRF protection on POST forms, search wildcard escaping, and security headers. This closes the security gaps identified in the architecture review.

### Schema Change

**Migration file:** `migrations/006_add_auth.php`

```sql
ALTER TABLE staff ADD COLUMN password_hash TEXT;
```

### Files to Create (2)

| File | Purpose |
|------|---------|
| `public/login.php` | Email + password form; on POST: `SELECT * FROM staff WHERE email = ?` → `password_verify()` → set `$_SESSION['staff_id']` → redirect to `/admin.php` |
| `public/logout.php` | `session_destroy()` + redirect to `/login.php` |

### Files to Modify (5)

| File | Changes |
|------|---------|
| `lib/bootstrap.php` | Add `require_auth()`, session-aware `current_staff()`, `csrf_token()`, `validate_csrf()` helpers |
| `public/admin.php` | Add `require_auth()` at top; add CSRF hidden field on create form |
| `public/share.php` | Add `require_auth()` at top; add CSRF hidden field on share form |
| `lib/layout.php` | Add security headers in `render_header()`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` |
| `seed.php` | Insert 5 staff with `password_hash('password')`; insert "Welcome Packet" (Freddy) and "Q3 Budget" (Alice); print credential table |

### Auth Design

```
login.php (GET)  → render form
login.php (POST) → SELECT * FROM staff WHERE email = ?
                 → password_verify($input, $row['password_hash'])
                 → $_SESSION['staff_id'] = $row['id']
                 → header('Location: /admin.php')

current_staff()  → read $_SESSION['staff_id']
                 → SELECT * FROM staff WHERE id = ?
                 → return row (same shape as before)
                 → throw if no session (caller should have used require_auth())

require_auth()   → if empty($_SESSION['staff_id'])
                 → header('Location: /login.php') + exit
```

### CSRF Helpers (bootstrap.php)

```php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

Each POST form gets: `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">`

Each POST handler starts:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        $error = 'Session expired, please try again.';
    }
}
```

### Seed Data

5 staff, all password = `"password"` (bcrypt hash via `password_hash()`):
- Freddy Folio · freddy@folio.example
- Alice Admin · alice@folio.example
- Bob Builder · bob@folio.example
- Carol Creator · carol@folio.example
- Dave Designer · dave@folio.example

2 documents:
- "Welcome Packet" owned by Freddy
- "Q3 Budget" owned by Alice

Print credential table to stdout after seeding.

### view.php — No Changes

Recipients don't authenticate. Share links work as before.

### Testing

Add 4 new tests to `tests/test.php`:
1. Login with correct creds → `$_SESSION['staff_id']` set, `current_staff()` name matches
2. Login with wrong password → no session, no exception thrown
3. Access `admin.php` without session → `Location:` header → `/login.php`
4. POST to `admin.php` with bad CSRF token → 419 response

---

## Deferred: Timezone Fix for scheduled_at

Store INTEGER Unix timestamp instead of TEXT for timezone-independent scheduling. Deferred to own phase — requires migration, updated admin.php conversion, and updated view.php comparison.

**Changes:** `ALTER TABLE documents ADD COLUMN scheduled_at INTEGER` (new column), migrate existing TEXT values, drop old column, rename new. Both admin.php and view.php compare integers directly.

**