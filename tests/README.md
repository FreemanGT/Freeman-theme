# Tests

PHPUnit test suite for Freeman Core. Added in 1.5.0 alongside a GitHub Actions CI config (`.github/workflows/ci.yml`).

## Running locally

```bash
composer install   # installs phpunit + phpcs + wpcs
composer test      # runs the full suite
composer test:smoke        # the module-instantiation smoke script
composer test:activation   # the activation simulation
composer lint              # PHPCS against WP coding standards
```

## What's covered

- **`DetectionResultTest`** — typed DTO that every importer returns. Constructor coercion, `from()` static factory, `ArrayAccess` read/immutable-write, `JsonSerializable`.
- **`ImporterShapesTest`** — contract enforcement for every `src/Modules/*/Importer.php`: must extend `Base_Importer`, declare `LEGACY_PLUGIN_FILE`, and return a `Detection_Result` (or coercible legacy array) from `detect()`; `import()` returns `array{ok, message}`.
- **`ModuleRegistryTest`** — `Module_Registry::discover()` finds every module on disk, each implements `Module_Interface` + extends `Module_Base`, ids are snake_case, discovery is idempotent and alphabetically sorted.
- **`ProductFeedSplitTest`** — regression guard for the 1.4.0 Generator / Server / Module split. Module's BC proxies still delegate to the new collaborators, the deprecated class constants still resolve.
- **`SecurityTest`** — `Security::rate_limit()` allows within budget, rejects over budget, isolates by bucket. `Security::sanitize_recursive()` flattens nested arrays and drops non-scalars.

## Bootstrap

`tests/bootstrap.php` stubs the WordPress globals every `Freeman\Core\*` class touches: identity-style `apply_filters`, pass-through sanitizers, an in-memory option + transient store, a `wp_upload_dir` that points at `sys_get_temp_dir()`, plus null-return shims for everything else. The bootstrap also wires the same PSR-4 autoloader the plugin uses at runtime.

No database required. Tests run in <10 ms locally.

## Adding a test

1. Drop a `NewThingTest.php` in this directory.
2. Extend `PHPUnit\Framework\TestCase`.
3. Use the global `$GLOBALS['fr_opts']` / `$GLOBALS['fr_transients']` if you need to reset the in-memory stores in `setUp()`.
