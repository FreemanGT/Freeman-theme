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
- Packages: `freeman-core` (v1.11.42), `freeman-digital` (v1.7.3), `freeman-theme` (v1.11.22)
- Audit: `/docs/audit-2026-04-28.md`
- Decisions: `/docs/decisions-2026-04-28.md` — read this before any roadmap work
- Roadmap: `/docs/roadmap.md`
- PR template: `/docs/pr-template.md`

## Current infrastructure state

*Last verified: 2026-05-11.*

- **PHPUnit**: configured for freeman-core (root `phpunit.xml.dist`, `tests/bootstrap.php`, **372 tests / 1065 assertions**, all green; Wave 3.2a (1.11.29) added 8 reported tests, +8 reported total; Wave 3.2b (1.11.30) added 10 reported tests, +37 reported assertions; Wave 3.3 (1.11.32) added 9 reported tests, +10 reported assertions; Wave 3.1a (1.11.33) added 8 reported tests, +23 reported assertions; Wave 3.1b (1.11.35) added 10 reported tests, +17 reported assertions; Wave 4.3 (1.11.36) added 6 reported tests, +35 reported assertions; Wave 4.1a (1.11.37) added 11 reported tests, +32 reported assertions; Wave 4.1b (1.11.38) added 10 reported tests, +16 reported assertions; Wave 4.2 (1.11.39) added 10 reported tests, +27 reported assertions; Wave 4.5 (1.11.40) added 4 reported tests, +8 reported assertions; ProductSlider popularity bugfix (1.11.42) added 7 reported tests, +14 reported assertions). The standing reporting unit going forward is `<reported tests> / <assertions>`. When updating this number, run `vendor/bin/phpunit` and copy the reported total verbatim. Runs in CI on PHP 8.0–8.3 lanes (PHP 7.4 dropped 2026-05-03 / 1.11.22 — shipped freeman-core already used PHP 8.0+ idioms baked in by Wave 2.3a–c, so the 7.4 PHPUnit lane was de-facto failing; aligning CI to reality rather than retrofitting polyfills, since no client site runs PHP 7.4). Locally: `composer install` once, then `PATH="/opt/homebrew/opt/php@8.3/bin:$PATH" composer test` (system `php` is 8.5, outside PHPUnit 10's supported range). freeman-digital has its own separate test config; freeman-theme has none.
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
9. NEVER ship a wave PR without updating `/docs/roadmap.md` in the same PR — mark the wave's section with `✅ shipped <version> (#<PR>, <YYYY-MM-DD>)` and bump the **Last updated** line. Sub-PRs (e.g. 1.1a, 2.3a) update only their own sub-bullet; the parent wave stays open until all sub-PRs ship. If the same PR also lands roadmap edits beyond the shipped-marker (promotion, scope change, new wave entry), state that separately in the PR description.

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

1. **Pre-flight**: which decisions in `/docs/decisions-2026-04-28.md` does this depend on; which Wave-0/1 prerequisites are satisfied. Include a **Roadmap delta** section listing roadmap changes, or `none` if truly none, as a flag to double-check. Roadmap freshness: every wave's PR must update `/docs/roadmap.md` in the same PR by marking the wave shipped with version + date, bumping the "Last updated" line, and reconciling any scope drift between predicted and actual. **If the wave adds tests**, note that the "Current infrastructure state" PHPUnit count above must be updated in the same PR — copy the new totals (reported tests / assertions) from `vendor/bin/phpunit` verbatim.
2. **Plan**: file list, hook list, option keys, feature flag name, default value, test list. **No code yet.** When sealing the file list, anticipate these mechanical files up front so they don't surface mid-implementation:
   - **Version bump touches two PHP files**, not one. `tools/release.sh`'s `bump_core` always edits both `freeman-core/freeman-core.php` (header + `FREEMAN_CORE_VERSION` constant) AND `freeman-core/src/Core/Plugin.php` (`const VERSION`). Any wave bumping `freeman-core` must list both.
   - **Hook-bearing file mods imply baseline regeneration.** `tests/BaselinesIntegrityTest` re-runs `tools/capture-baselines.sh` and asserts byte-identity against committed baselines. Modifying any file containing the corresponding surface drifts line numbers and breaks the assertion — mechanical, no behavioral change. Anticipate the matching baseline file in §5 ahead of implementation:
     - file contains `apply_filters` or `do_action` → `tests/baseline-hooks.txt`
     - file contains `register_rest_route` → `tests/baseline-rest.txt`
     - file contains `WP_CLI::add_command` → `tests/baseline-cli.txt`
     - file declares a `freeman_` or `etucart_` option key → `tests/baseline-options-declared.txt` and/or `tests/baseline-options-legacy.txt`
     Regenerate via `bash tools/capture-baselines.sh` and commit verbatim; never hand-edit. The committed diff must consist only of line-number / position changes on existing entries — anything else means a real surface change snuck in and the §6 inventory is stale.
   - **Version bumps and test-count changes touch CLAUDE.md.** The "Current infrastructure state" section names the current `freeman-core` version (Repo state line) and `<reported tests> / <assertions>` (PHPUnit line). Any wave that bumps the version or adds tests moves these values; CLAUDE.md must appear in §5 alongside the version-bump file pair so the staleness doesn't get treated as a follow-up chore. Pre-flight §1 already calls this out for tests; §5 must echo it so the file actually gets sealed in. Bump the *Last verified* date in the same edit — the date is the truthfulness stamp on the values, not adjacent content.
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
