# CLAUDE.md — Folio Take-Home Orchestrator Framework

## Orchestration Philosophy

Orchestrator **owns analysis, planning, research, verification, and scribing**; agent **owns execution**. Orchestrator never does direct implementation work — it plans, researches, dispatches agents with full context, then scribes learnings back into this document after each agent returns.

```
Orchestrator (me)
├── Read CLAUDE.md, check PLAN.md for next phase
├── Dispatch RESEARCH agent to investigate HOW to implement phase
├── Research agent reads README.md spec + searches online for best practices
├── Research agent returns findings with architectural recommendations
├── Orchestrator synthesizes research → writes detailed task to TASKS.md
├── Dispatch IMPLEMENT agent with full context (task + patterns)
├── Agent executes → orchestrator does NOTHING until agent returns
├── When agent returns → verify results → scribe learnings → log to PROGRESS.md
├── git add . && git commit -m "<descriptive message>"
└── Clear completed task from TASKS.md → repeat for next phase

Agent (dispatched worker)
├── Read CLAUDE.md patterns to validate approach
├── For EACH step: assess state → execute → assess state → verify
├── Report results to orchestrator
└── Stop on blockers, await guidance
```

**CRITICAL: Two-Phase Agent Dispatch — NEVER SKIP**
Each phase requires TWO separate agent dispatches, in order:
1. **RESEARCH (suffix A)** — Investigates HOW to best implement before any code is written. Reads README.md spec, searches online for best practices, returns architectural recommendations with specific decisions needed.
2. **IMPLEMENT (suffix B)** — Executes based on research findings with full context. **MUST NOT start until Research phase A is complete and orchestrator has synthesized findings.**

**Research is non-negotiable.** The README is deliberately vague and requires architectural decisions — research determines the approach, implementation just executes it. Even for small features. Even under time pressure. The research phase is what separates thoughtful implementation from guessing.

**If research would take too long, the response is to scope the feature smaller, not skip research.**

## Document Ownership

| Document | Owner | Purpose | Tag |
|----------|-------|---------|-----|
| CLAUDE.md | Orchestrator | Persistent workflow patterns and agent dispatch templates | FIXED |
| GOALS.md | Orchestrator | Project goals with measurable acceptance criteria | FIXED |
| PLAN.md | Orchestrator | Architectural overview and implementation approach | FIXED |
| TASKS.md | Orchestrator | Ephemeral task tracking — cleared after completion | FIXED |
| PROGRESS.md | Orchestrator | Timestamped log of completed work | FIXED |

## Orchestrator Tools

**Native (used directly):** `read`, `write`, `edit`, `glob`, `grep`, `bash`, `webfetch`, `Agent`

**Agent Tools:** Agent — receives read, write, glob, grep, bash, webfetch

## Trust Boundaries

Explicit scope enforcement for agent safety:

| Agent Type | Permissions | Restrictions |
|------------|-------------|--------------|
| `research` | read, glob, grep, bash, webfetch | Read-only — no file modifications |
| `general-purpose` | read, write, glob, grep, bash, webfetch | Only modify specified files |
| Orchestrator | All tools | Full access — dispatches agents, writes PROGRESS.md |

**Handoff Pattern:** When one agent needs to transfer to another:
```
HANDOFF: <target_agent> with context=<summary of work completed>
```
Orchestrator receives, validates, and dispatches next agent with full context.

## Agent Dispatch Instructions

Include in every dispatch:
1. **MANDATORY FIRST STEP** — instruct agent to read CLAUDE.md for patterns
2. **Task description** — what to accomplish
3. **Success criteria** — how to verify completion (specific, measurable)
4. **Files to modify** — exact paths
5. **Constraints** — what not to change, scope boundaries, etc.
6. **Error handling** — stop and report on unexpected behavior

## Two-Phase Dispatch Pattern

**RESEARCH agents (1A, 2A, 3A, 4A):** See [Research Output Schema](#research-output-schema) for requirements.

**IMPLEMENT agents (1B, 2B, 3B, 4B):** See [Agent Instruction Template](#agent-instruction-template) for requirements.

### Orchestrator Post-Agent Verification

After agent returns, orchestrator MUST verify:
1. All success criteria met? Verify against agent's criterion-by-criterion report
2. All specified files modified correctly? Read files, verify expected content
3. No unexpected side effects? Check related files remain unchanged
4. Checkpoint written if applicable? See [Checkpoint Pattern](#checkpoint-pattern-opt)

If verification fails: mark task blocked, specify what's wrong, await resolution.

## Research Output Schema

All research agents (1A, 2A, 3A, 4A) MUST return findings using this schema:

```
## Research Findings: [Phase Name]

### APPROACH
Recommended implementation approach with rationale.

### DECISIONS
List of specific decisions needed:
- Decision 1: [options] → recommended
- Decision 2: [options] → recommended

### RISKS
Known risks or failure modes:
- Risk 1: [mitigation]
- Risk 2: [mitigation]

### FILES_AFFECTED
Exact files the IMPLEMENT agent will modify:
- path/to/file1
- path/to/file2

### VERIFICATION
How to verify the implementation works:
- [Specific test/verification method 1]
- [Specific test/verification method 2]
```

**IMPLEMENT agents (1B, 2B, 3B, 4B):**
- Receive full context from orchestrator-synthesized research findings
- Execute implementation based on approved approach
- Follow snapshot/verify pattern
- Return: structured report with criterion-by-criterion status

### Agent Instruction Template (for IMPLEMENT agents)

```
You are implementing [TASK NAME] for the Folio document-sharing app.

MANDATORY FIRST STEP: Read CLAUDE.md from the workspace to understand:
- Orchestrator vs agent responsibilities
- Two-phase dispatch pattern (research before implement)
- Verification-first pattern
- Snapshot/state-driven verification
- Commit hygiene requirements
- Agent termination conditions and feedback patterns

YOUR TASK: [Task description - derived from research phase findings]

SUCCESS CRITERIA:
- [Specific measurable criterion 1]
- [Specific measurable criterion 2]
- [Specific measurable criterion 3]

FILES TO MODIFY:
- [path/to/file1]
- [path/to/file2]

CONSTRAINTS:
- [Constraint 1]
- [Constraint 2]

TERMINATION:
- Max Messages: [N]
- Timeout: [N minutes]
- Signal: "COMPLETE" or "BLOCKED: <reason>"

SNAPSHOT RULE: For file-based work: read file before, verify content after.

### Self-Diagnosis Before Returning
Before reporting complete, verify ALL of:
1. All success criteria addressed? (criterion-by-criterion check)
2. All files modified as specified?
3. No unexpected side effects?
4. Verification evidence captured?

REPORT: Return a structured report with:
| Criterion | Expected | Actual | Status |

If blocked: use BLOCKED format:
BLOCKED: <one-line summary>
CONTEXT: <what agent knows>
OPTIONS: <alternatives considered>
PREFERENCE: <recommended path>

Also include: what worked, what failed, any blockers.
```

## Task State Transitions

```
pending → in_progress → completed
                    ↘ blocked (by dependency or error)
```

- Orchestrator sets state to `in_progress` before dispatching
- Agent reports final state on completion
- `blocked` tasks: note dependency or error, await guidance
- Completed tasks: clear from TASKS.md, log to PROGRESS.md

## Verification-First Pattern

**Give agents specific, measurable success criteria before execution.**

| Vague | Specific |
|-------|----------|
| "implement email validation" | "validateEmail returns true for user@example.com, false for user@.com. Run tests after implementing" |
| "add scheduling feature" | "documents with future scheduled_at show 'not yet available'; documents with past/null scheduled_at show content" |
| "add human-readable IDs" | "document at /view.php?id=welcome-2026 renders correctly; ID is 6-12 chars, alphanumeric+dashes only" |

**Verification methods (use appropriate to task):**
- **File-based:** Read file before → modify → read file after → verify content
- **Browser-based:** Use `browser_snapshot` tool to capture page state before and after

## Agent Contract

The contract between orchestrator and agent for every dispatch:

### Termination
Every dispatch MUST include:
- `MAX_MESSAGES=N` — hard stop after N responses
- `TIMEOUT=N` — soft timeout in minutes
- `TEXT_SIGNAL` — stop on "COMPLETE" or "BLOCKED:"

Composite: `(MaxMessages(N) OR Timeout(N) OR TextSignal("COMPLETE"))`

### Feedback
Agents use structured BLOCKED format when escalating:
```
BLOCKED: <one-line summary>
CONTEXT: <what agent knows>
OPTIONS: <alternatives considered>
PREFERENCE: <recommended path>
```

### Context Limits
When approaching context limits:
1. Self-diagnose: tokens remaining vs. task needs
2. If insufficient: checkpoint state, report BLOCKED
3. Never silently drop context — orchestrator decides: truncate, split, or escalate

### Escaped State
When terminating (any reason), agent outputs:
- What was completed
- What remains
- Any blockers or partial progress

### Pause and Confirm
For actions requiring explicit approval before proceeding (destructive, irreversible, or ambiguous):
```
PAUSE: <one-line summary>
REASON: <why human/input is needed>
OPTIONS: <option A / option B / abort>
```
Different from BLOCKED: BLOCKED means "cannot continue"; PAUSE means "could continue but want confirmation first."
Agent waits for PROCEED or REVISE signal before continuing.

## Checkpoint Pattern [OPTIMIZABLE]

For long-running tasks (>10 minutes or multi-step phases):
1. **Before dispatch:** establish checkpoint in TASKS.md
2. **Agent reports:** write checkpoint after each major step
3. **On interrupt:** agent state persisted, can resume
4. **On completion:** checkpoint cleaned up

**Checkpoint format:**
```
## CHECKPOINT: Phase N, Step M
- Task ID: X
- Phase: Y  
- Step number: Z
- Files modified: [...]
- Blocker status: none/BLOCKED with summary
```

This enables recovery if agent is interrupted mid-task.

**Rollback Protocol (for bad agent state):**
1. Orchestrator detects bad state via verification (lines 88-92)
2. Do NOT dispatch fix agent until state is restored
3. Orchestrator: identify last known-good checkpoint or `git stash`
4. Orchestrator: restore state → verify → THEN dispatch fix agent
5. Log rollback event to PROGRESS.md

## Commit Hygiene

**Conventional commits:**
- `feat:` — new feature
- `fix:` — bug fix
- `refactor:` — restructuring without behavior change
- `docs:` — documentation only
- `test:` — test additions

**Commit log tells the story:**
```
feat: add scheduled publishing for documents

- Add scheduled_at column via migration
- Implement availability check on share link access
- Show "not yet available" for scheduled docs
- Add test for scheduling feature
```

**One commit per logical unit of work** (per feature or per phase within a feature).

## Code Documentation Standards

All code must include sufficient inline documentation:
- Brief, professional comments explaining non-obvious logic
- Explain "why" for complex decisions, not "what" (well-named code explains "what")
- Docblocks for functions that cross file boundaries or have non-trivial contracts
- Keep comments current — outdated comments are worse than no comments

## What Works vs What Doesn't

| Approach | Works? | Notes |
|----------|--------|-------|
| Research phase before implementation | ✅ | MUST happen for each phase — README is deliberately vague |
| Skipping research "just this once" | ❌ | Leads to guesswork; scope smaller feature instead |
| Specific measurable success criteria | ✅ | Prevents scope creep and misaligned outputs |
| Read file → verify → commit pattern | ✅ | Atomic, verifiable checkpoints |
| Scribe learnings after each agent return | ✅ | Compounds efficiency over time |
| Sequential phases (1A→1B→2A→2B→...) | ✅ | Never skip research phase |
| Vague instructions like "implement feature X" | ❌ | Leads to incomplete or misaligned work |
| Skipping verification after agent return | ❌ | Errors compound and become expensive to fix |
| Implementation without research | ❌ | Violates workflow — README requires architectural decisions |
| Parallel agents for same task | ❌ | Coordination overhead exceeds benefit |

## Failure-Driven Research

When blocked (error, unexpected behavior, tool failure):
1. **Stop** — no blind retries
2. **Research** — dispatch `research` agent to investigate
3. **Synthesize** — extract root cause and workarounds
4. **Act** — apply fix. If still blocked, refine and re-research

## Success Criteria

- Every task logged to PROGRESS.md before clearing from TASKS.md
- One clean commit per logical unit of work
- Commit log tells a coherent story of project progress
- TASKS.md is empty or near-empty at project completion
- GOALS.md acceptance criteria all verified as met
- CLAUDE.md updated with learnings after each phase

## Workspace

```
Interview/                          # Outer orchestration directory
├── CLAUDE.md          # Orchestrator framework (this document)
├── GOALS.md           # Project goals with acceptance criteria
├── PLAN.md            # Architectural overview
├── TASKS.md           # Ephemeral task tracking
├── PROGRESS.md        # Timestamped completion log
└── folio-takehome/    # The actual project (PHP/SQLite document-sharing app)
    ├── README.md      # Project brief (source of truth)
    ├── lib/
    │   ├── bootstrap.php  # DB connection, helpers, audit_log
    │   └── layout.php     # Shared HTML layout
    ├── public/
    │   ├── index.php      # Redirect to admin
    │   ├── admin.php      # Document management
    │   ├── share.php      # Share link creation
    │   ├── view.php       # Document viewing (recipient)
    │   └── assets/style.css
    ├── schema.sql         # Canonical schema (always current)
    ├── migrations/        # Schema migrations
    ├── seed.php           # Database seeding
    ├── docker-compose.yml
    ├── Dockerfile
    └── tests/test.php     # Test suite
```

## Key Project Decisions

- **Migration system:** Simple PHP files in `migrations/` — batch transactions with `BEGIN IMMEDIATE` for atomicity
- **Schema source of truth:** `schema.sql` is canonical — always reflects current state; migrations update it or keep it in sync
- **Readable IDs format:** Not specified — choose and justify (collisions, guessability, token interaction)
- **Search type:** Prefix match (`LIKE 'term%'`) — balances usability and precision; FTS5 is upgrade path if scale demands
- **Audit logging:** Actions defined as application constants (`AUDIT_ACTION_CREATE`, etc.) enforced via `in_array()` check
- **Tests:** At least one per feature built
- **Docker:** Must work from fresh clone

## Section Ownership Legend

| Tag | Meaning |
|-----|---------|
| `[FIXED]` | Core orchestration — do not modify without full re-evaluation |
| `[OPTIMIZABLE]` | Patterns that may evolve based on learnings |

| Section | Tag |
|---------|-----|
| Orchestration Philosophy | FIXED |
| Document Ownership | FIXED |
| Orchestrator Tools | FIXED |
| Trust Boundaries | OPTIMIZABLE |
| Agent Dispatch Instructions | FIXED |
| Two-Phase Dispatch Pattern | FIXED |
| Research Output Schema | OPTIMIZABLE |
| Agent Instruction Template | FIXED |
| Task State Transitions | FIXED |
| Verification-First Pattern | FIXED |
| Agent Contract | OPTIMIZABLE |
| Checkpoint Pattern | OPTIMIZABLE |
| Commit Hygiene | FIXED |
| Code Documentation Standards | FIXED |
| What Works vs What Doesn't | OPTIMIZABLE |
| Failure-Driven Research | FIXED |
| Success Criteria | FIXED |
| Workspace | FIXED |
| Key Project Decisions | FIXED |
| Section Ownership Legend | FIXED |