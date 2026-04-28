# CLAUDE.md

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

---

# Project: Freeman Plugin Suite

The guidelines above apply universally. The rules below are specific to this repo and override defaults where they conflict.

## Repo state

- Path: `/Users/freemansmain/Ai Projects/Freeman Theme/`
- Packages: `freeman-core` (v1.11.0), `freeman-digital` (v1.7.3), `freeman-theme` (v1.10.8)
- Audit: `/docs/audit-2026-04-28.md`
- Decisions: `/docs/decisions-2026-04-28.md` — read this before any roadmap work
- Roadmap: `/docs/roadmap.md`
- PR template: `/docs/pr-template.md`

## Current infrastructure state

*Last verified: 2026-04-29.*

- **PHPUnit**: configured for freeman-core (root `phpunit.xml.dist`, `tests/bootstrap.php`, 110 tests reported / 79 test methods, all green). PHPUnit's reported count exceeds the method count because `@dataProvider` cases expand to one reported test per dataset (notably `FeatureFlagsTest`). When updating this number, run `vendor/bin/phpunit` and copy the reported total. Runs in CI on PHP 7.4–8.3 lanes. Locally: `composer install` once, then `PATH="/opt/homebrew/opt/php@8.3/bin:$PATH" composer test` (system `php` is 8.5, outside PHPUnit 10's supported range). freeman-digital has its own separate test config; freeman-theme has none.
- **Snapshot harness**: shipped in Wave 0.5. See `/tests/snapshots/` (`SnapshotTestCase` trait, `Scrubber`, three example tests with committed goldens) and `/tests/snapshots/README.md`.
- **Staging**: not yet provisioned. Manual local testing on a separate WP install for now.
- **WP-CLI**: not currently installed locally. Wave 0.4 needs it installed (or a substitute) before regression baselines can be captured.
- **Commit format**: Conventional Commits, scope = package. Example: `feat(freeman-core): add logger hooks`.

If any of the above changes, update this section before starting work.

## Hard rules — NEVER violate

These extend §3 (Surgical Changes) with project-specific surfaces:

1. NEVER ship a roadmap item without a feature flag (`freeman_core_<module>_<feature>_enabled`, default `false`) unless purely additive (new hook, new filter, new CSS variable with backward-compatible fallback, or a new helper class with no callers).
2. NEVER remove an existing hook, filter, option key, shortcode, REST route, or admin URL. Deprecate via `_deprecated_function()` / `_deprecated_hook()` with a 2-minor-version sunset.
3. NEVER edit `legacy/` directories or `etucart_*` keys without a written migration plan AND human approval.
4. NEVER touch more than ONE roadmap item per PR. Exception: the 18-hooks PR (Wave 1.1) — state it explicitly.
5. NEVER bump a major version without explicit instruction.
6. NEVER modify the database schema without `dbDelta()` upgrade routine + downgrade note.
7. NEVER change `Logger`'s `final` keyword. Do not extract a `Logger_Interface`. Add hooks only inside `log()`.
8. NEVER use `error_log()`, `var_dump()`, or `console.log()` in shipped code. Use `Freeman\Core\Core\Logger`.

## STOP and ask before coding if

This extends §1 (Think Before Coding) with concrete project triggers:

- The roadmap item depends on something not resolved in `/docs/decisions-2026-04-28.md`.
- The plan requires touching `legacy/` or `etucart_*` keys.
- The plan requires a database schema change.
- The plan requires a major version bump.
- Backward-compat cannot be honored without trade-offs.
- A single change request implies more than one roadmap item.

## Per-PR contract

Every PR must include the sections from `/docs/pr-template.md`. Reviewers reject PRs missing the Backward Compatibility section, the Feature Flag declaration, or the Rollback Plan.

If touching >12 files or >3 modules: STOP and ask whether to split.

## Output format for roadmap work

When asked to implement a roadmap item, follow §4 (Goal-Driven Execution) with this concrete shape:

1. **Pre-flight**: which decisions in `/docs/decisions-2026-04-28.md` does this depend on; which Wave-0/1 prerequisites are satisfied. **If the wave adds tests**, note that the "Current infrastructure state" PHPUnit count above must be updated in the same PR — copy the new total from `vendor/bin/phpunit`'s reported count, not the method count.
2. **Plan**: file list, hook list, option keys, feature flag name, default value, test list. **No code yet.**
3. *Wait for human approval of the plan before proceeding.*
4. **Execution**: code changes, one logical commit per file group.
5. **Verification**: snapshot diff, test results, manual QA checklist filled in.
6. **Rollback**: exact `wp option update` command to disable.

Skipping step 1 or 2 violates §1.

## First task on a fresh session

1. Confirm you've read this file, `/docs/decisions-2026-04-28.md`, and `/docs/roadmap.md`.
2. Verify the "Current infrastructure state" section above matches reality. Flag drift.
3. Ask which roadmap item to start on.
4. Once given an item, follow the Output format — pre-flight + plan first, no code.
