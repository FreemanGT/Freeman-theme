# PR Template

Copy this into every PR description. Fill in every section. Do not delete sections — write "N/A" if a section truly doesn't apply, and explain why.

---

## Roadmap item
Roadmap #___ — <short description>

## Decision dependencies
List which entries in `/docs/decisions-2026-04-28.md` this PR relies on. Write "none" only if truly independent.

## Files touched
- `path/to/file1.php`
- `path/to/file2.css`
- ...

(If >12 files or >3 modules, STOP — ask the human whether to split this PR.)

## Backward compatibility
For every existing surface this PR could affect, confirm it still works identically. Be specific.

- **Option keys**: list every `freeman_core_*` and `etucart_*` key the touched modules read/write. State which still work.
- **Hooks/filters**: list every existing hook in scope. State which still fire with the same name and payload.
- **Shortcodes**: list each one. State that they still render the same output.
- **CSS classes**: list every class consumers might target. State they still exist.
- **JS globals**: any `window.*` exposed by touched modules. State they still exist.
- **Admin URLs / REST routes**: state they still resolve.

## Feature flag
- Flag name: `freeman_core_<module>_<feature>_enabled`
- Default: `false`
- How to enable: `wp option update <flag> 1`
- If no flag: explain why this is additive-only and risk-free.

## Tests
- [ ] Unit tests for new functions
- [ ] Integration tests for new hooks (verify firing + payload)
- [ ] Snapshot tests where output changes — diff with flag OFF must be empty

Test command: `vendor/bin/phpunit tests/path/to/new/tests`

## i18n
- [ ] All new user-facing strings wrapped in `__()` / `_e()` / `esc_html__()`
- [ ] Text domain matches package
- [ ] Ran `wp i18n make-pot`
- [ ] Committed updated `.pot` file

## Logging
- [ ] New code paths log via `Freeman\Core\Core\Logger`
- [ ] No `error_log()`, `var_dump()`, `console.log()` left in
- [ ] Log levels appropriate (info/warning/error)

## Database
- [ ] No schema changes, OR
- [ ] `dbDelta()` upgrade routine added in `Plugin::maybe_upgrade()`
- [ ] Plugin version bumped in package header
- [ ] Downgrade procedure documented below:

(downgrade procedure here, or "N/A — no schema change")

## Manual QA checklist
- [ ] Activated freeman-core on a clean WP install — no fatal errors
- [ ] Activated alongside WooCommerce — no fatal errors
- [ ] Flag OFF: snapshot of relevant module output matches pre-PR snapshot byte-for-byte
- [ ] Flag ON: new behavior works as described
- [ ] Toggled flag OFF again: behavior reverts cleanly, no orphaned options
- [ ] Checked Logger output for unexpected warnings/errors
- [ ] Tested in admin with `manage_woocommerce` capability
- [ ] Tested as subscriber role: no admin access leaked
- [ ] Tested in Hebrew locale (RTL): no layout breaks
- [ ] Tested in English locale: defaults are English

## Rollback plan
One paragraph. Usually:

> To disable in production without deactivating the plugin, run:
> `wp option update freeman_core_<module>_<feature>_enabled 0`
> This reverts to pre-PR behavior immediately. No data migration needed.

## Future work (optional)
List anything you noticed that "should" come along but is deliberately out of scope. Do not implement here.
