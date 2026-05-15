# Folio Live Demo Walkthrough

This document reproduces the end-to-end user-facing demo of the Folio
document-sharing app. Follow each phase in order. The app runs in Docker on
`http://localhost:8000`.

## Prerequisites

```sh
cd folio-takehome
docker compose up -d --build
# Wait for seed: "php seed.php && php -S 0.0.0.0:8000 -t public/"
```

**Credentials:**

| Role | Email | Password |
|------|-------|----------|
| Staff | freddy@folio.example | password |
| Staff | alice@folio.example | password |

## Phase 1 — Staff Login

1. Navigate to `http://localhost:8000/login.php`
2. Verify the login form renders with "Email" and "Password" fields and a "Log in" button
3. Fill the form: email = `freddy@folio.example`, password = `password`
4. Submit the form

**Expected result:** Browser redirects to `http://localhost:8000/admin.php`

**Verification checks on admin page:**
- Page title is "Admin · Folio"
- Navigation bar shows `Freddy Folio · freddy@folio.example`
- "New document" form is visible (Title, Body, Schedule fields)
- Documents table lists seeded documents (Welcome Packet, Q3 Budget)

## Phase 2 — Share a Document (Staff)

1. From admin, click "Create share →" next to the Welcome Packet document
   - Or navigate directly to `http://localhost:8000/share.php?doc=1`
2. Verify the share page renders with title "Share 'Welcome Packet'"
3. Fill recipient email: `alice@recipient.example`
4. Submit the form

**Expected result:** Success banner appears showing two share URLs:
- Token URL: `http://localhost:8000/view.php?token=<32-char-hex>`
- Readable ID URL: `http://localhost:8000/view.php?rid=<slug>-<6-char-alphanumeric>`

**Verification checks:**
- Banner text includes "Share link ready:"
- Token is 32 hex characters
- Readable ID matches pattern `welcome-XXXXXX` (6 alphanumeric chars)

## Phase 3 — Recipient Views Document (Token URL)

1. In a new browser tab (or clearing cookies to simulate a fresh user),
   navigate to the token URL from Phase 2
   - e.g. `http://localhost:8000/view.php?token=<token>`

**Expected result:** Document renders for the recipient.

**Verification checks:**
- Page title is "Welcome Packet · Folio"
- Heading "Welcome Packet" is visible
- "Shared with alice@recipient.example" is displayed
- Document body "Welcome to Folio! This is the body of your welcome packet." is visible
- Navigation bar shows only "F Folio" link (no staff name — recipient is NOT authenticated)

## Phase 4 — Recipient Views Document (Readable ID URL)

1. Navigate to the readable ID URL from Phase 2
   - e.g. `http://localhost:8000/view.php?rid=<readable_id>`

**Expected result:** Identical content to the token URL (Phase 3).

**Verification checks:**
- All checks from Phase 3 pass identically
- Page content matches token URL page content

## Phase 5 — Auth Guard Verification

1. From the recipient tab, navigate to `http://localhost:8000/admin.php`

**Expected result:** Redirected to `http://localhost:8000/login.php`

**Verification checks:**
- Login form is displayed (unauthenticated users cannot reach admin)
- No staff name appears in navigation

## Phase 6 — Logout

1. Log in as staff (repeat Phase 1)
2. Click "Log out" link or navigate to `http://localhost:8000/logout.php`
3. Navigate to `http://localhost:8000/admin.php`

**Expected result:** Redirected to `http://localhost:8000/login.php`

**Verification checks:**
- Login form is displayed
- Staff session is fully destroyed

## Phase 7 — Scheduled Document (Not Yet Available)

1. Log in as staff
2. Create a new document with the Schedule field set to a future date/time
3. Create a share link for that document
4. As recipient, navigate to the share link

**Expected result:** "Document not yet available" message is shown with the scheduled
availability time.

## Automated Test Suite

```sh
docker exec folio-takehome-app-1 php tests/test.php
```

**Expected:** Exit code 0. All tests marked `[ok]`.

---

## Summary of Expected URL Patterns

| Page | URL Pattern | Auth Required |
|------|------------|---------------|
| Login | `/login.php` | No |
| Admin | `/admin.php` | Yes (staff session) |
| Share | `/share.php?doc=<id>` | Yes (staff session) |
| View (token) | `/view.php?token=<32-hex>` | No |
| View (readable) | `/view.php?rid=<slug>-<6-chars>` | No |
| Logout | `/logout.php` | No |
