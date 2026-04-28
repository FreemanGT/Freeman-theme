# Snapshot harness (Wave 0.5)

Golden-file snapshot tests for module output. The harness exists so every Wave-1+ PR can prove "with the feature flag OFF, output is byte-identical to the pre-PR baseline" — the bug-proofing rule from `/docs/roadmap.md`.

This is **server-side string-snapshot testing**, not browser-based visual regression. PHPUnit captures HTML / XML / JSON output from a function call, scrubs volatile fields (timestamps, nonces, version strings), and diffs against a committed golden file.

## Layout

| Path | Purpose |
|---|---|
| `SnapshotTestCase.php` | Trait providing `assertSnapshotMatches( $name, $actual )`. |
| `Scrubber.php` | Pure functions for replacing volatile content with sentinels. |
| `__golden__/` | Committed golden files. Edit only via `UPDATE_SNAPSHOTS=1`. |
| `__fixtures__/` | Hand-written stubs and inputs (e.g. `wc_product_stub.php`). |
| `*Test.php` | One file per snapshot type. Three example tests ship with this wave: `HtmlSnapshotTest`, `XmlSnapshotTest`, `JsonSnapshotTest`. |

## Running

```bash
PATH="/opt/homebrew/opt/php@8.3/bin:$PATH" composer test
```

Snapshot tests run as part of the normal suite. No extra command needed.

## Updating goldens

When a deliberate source change alters module output, regenerate goldens:

```bash
UPDATE_SNAPSHOTS=1 PATH="/opt/homebrew/opt/php@8.3/bin:$PATH" composer test
```

Then `git diff tests/snapshots/__golden__/` to inspect the change, and commit the updated goldens **alongside** the source change. PR reviewers use the golden diff to verify the source change is intentional.

If you forget to set `UPDATE_SNAPSHOTS=1`, the test fails with a unified diff in the message and a one-line hint to set the env var.

## Format invariants

Goldens must be:

- **LF-only line endings.** No CRLF, no CR.
- **No UTF-8 BOM.**
- **Exactly one trailing newline.**

`SnapshotTestCase` enforces all three on both read and write — a manual edit that breaks any of these throws a `RuntimeException` rather than producing a confusing byte-mismatch.

## Scrubbing

Snapshot tests must scrub volatile content before comparing. Use the `Scrubber` class:

| Method | Replaces |
|---|---|
| `Scrubber::timestamps( $s )` | ISO-8601 / RFC 3339 / MySQL `Y-m-d H:i:s`. |
| `Scrubber::nonces( $s )` | WP nonce values inside `name="..._nonce" value="..."` and `_wpnonce=...`. |
| `Scrubber::versions( $s )` | Semver inside `version="x.y.z"` attributes. |
| `Scrubber::site_url( $s, $url )` | Exact-match string replacement of a known site URL. |
| `Scrubber::json_keys( $json, $keys, $sentinel )` | Top-level keys of a decoded JSON envelope. |

## Adding a new snapshot

1. Create `tests/snapshots/MyModuleSnapshotTest.php`.
2. `use SnapshotTestCase;` and call `$this->assertSnapshotMatches( 'my_output.html', $actual )`.
3. If the source code calls WP/WC functions not stubbed by `tests/bootstrap.php`, add `if ( ! function_exists() )` shims at the top of the test file (see `XmlSnapshotTest.php` for the pattern). Do **not** edit `bootstrap.php` — that affects the other 48+ tests.
4. Run `UPDATE_SNAPSHOTS=1 composer test` to write the initial golden, then commit it.
5. If you need a hand-built object (e.g. a `\WC_Product` stub), put it in `__fixtures__/` and document which methods you covered — when a future PR extends the source, the next maintainer needs to know which getters the stub already implements.

## Why no Playwright / no headless browser

The "diff must be empty when flag is OFF" requirement is about whether the *server* output drifted, not whether the *rendered page* drifted pixel-for-pixel. Server-side golden files catch:

- Renamed shortcode/hook output
- Changed XML schema (renamed elements, attributes, ordering)
- Changed JSON envelope shape (added/removed/renamed keys)
- HTML structural drift (form action URLs, button labels, conditional rendering)

These are the regressions Wave 1+ actually risks introducing. Visual regression on rendered CSS/layout would be a separate (and much heavier) effort, deferred until a P3 client need surfaces.
