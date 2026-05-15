# GOALS.md — Project Goals (Outline)

## Purpose

GOALS.md is an **outline pointing to framework documents**. It defines what needs to be built and priority, but detailed acceptance criteria live in PLAN.md.

## Document Chain

```
/goal [brief] → CLAUDE.md → PLAN.md (phase sequence) → TASKS.md (doing) → PROGRESS.md (done)
```

## Project Vision

Folio is a document-sharing app with three features: **scheduled publishing**, **human-readable IDs**, and **share-by-name search**.

Source: `folio-takehome/README.md` (authoritative spec)

## Architecture Decisions

See PLAN.md for architectural decisions and implementation sequence.

## Milestones & Priority

| Priority | Feature | Phase | Key Criterion |
|----------|---------|-------|---------------|
| HIGH | Scheduled Publishing | Phase 2 | Documents with future `scheduled_at` show "not yet available" |
| MEDIUM | Share by Name | Phase 4 | Prefix search returns matching documents |
| MEDIUM | Human-Readable IDs | Phase 3 | `view.php?id=welcome-2026` resolves correctly |

## Cross-Feature Requirements

| Req | Description |
|-----|-------------|
| R1 | Schema changes via migrations; `schema.sql` kept in sync |
| R2 | `docker compose up` works from fresh clone |
| R3 | Audit log uses constants (`AUDIT_ACTION_*`) |
| R4 | `docker compose exec app php tests/test.php` exits 0 |

## Verification

Tests pass, docker works, commit history tells coherent story.

See CLAUDE.md for orchestration patterns.

---

## Orchestrator Workflow

The orchestrator follows this strict sequence for each phase:

```
1. READY   → Read CLAUDE.md, check PLAN.md for next phase
2. RESEARCH → Dispatch research agent to investigate how to best implement the phase
             - Research agent reads README.md spec + searches for best practices online
             - Returns findings with architectural recommendations
3. PLAN     → Orchestrator synthesizes research, writes detailed task to TASKS.md
4. IMPLEMENT → Dispatch execution agent with full context from research
             - Agent reads CLAUDE.md patterns, executes task, verifies
             - Reports structured results back to orchestrator
5. VERIFY   → Orchestrator reads modified files, verifies against success criteria
6. LOG      → On success: write entry to PROGRESS.md
7. COMMIT   → git add . && git commit -m "<descriptive message>"
8. CLEAR    → Remove completed task from TASKS.md
9. REPEAT   → Return to step 1 for next phase, until TASKS.md is empty
```

**Key Rule:** Research is done BEFORE implementation for each phase. The README spec is deliberately vague to force architectural decisions — the orchestrator must research and decide HOW to implement, not just WHAT to build.

### Phase-to-Task Mapping

| Phase | Research Task | Implement Task | Commit |
|-------|--------------|---------------|--------|
| Phase 1: Foundation | 1A | 1B | `feat: add PHP migration system` |
| Phase 2: Scheduled Publishing | 2A | 2B | `feat: add scheduled publishing` |
| Phase 3: Human-Readable IDs | 3A | 3B | `feat: add human-readable document IDs` |
| Phase 4: Share by Name | 4A | 4B | `feat: add document search by title` |

### Research Agent Instructions (2A, 3A, 4A)

Each research task agent receives:
- The README.md spec section for the feature
- The PLAN.md phase description
- Instructions to search online for best practices relevant to the implementation

Research agent returns:
- Confirmation or refinement of the approach in PLAN.md
- Any additional architectural recommendations
- Specific decisions needed (e.g., ID format, search algorithm)

### Implementation Agent Instructions (1B, 2B, 3B, 4B)

Each implementation agent receives:
- The orchestrator-synthesized task from TASKS.md
- Success criteria (specific, measurable)
- Files to modify (exact paths)
- Constraints (what NOT to change)
- CLAUDE.md pattern reference

Implementation follows snapshot/verify pattern:
1. Read file before modification
2. Execute change
3. Read file after modification
4. Verify expected content