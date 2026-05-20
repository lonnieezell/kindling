# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A CodeIgniter 4 Composer package (`myth/kindling`) that provides a first-class Vite asset pipeline for CI4 apps — HMR in development, fingerprinted chunk-aware output in production. PHP namespace root: `Myth\Kindling\`.

## General Guidelines
- When creating new classes, or editing existing ones, ensure that a class-level docblock is present, describing the purpose of the class and any important details. Method-level docblocks should be added for all public methods, describing their parameters, return values, and any exceptions they may throw.
- Follow the existing code style and conventions used throughout the repository. This includes naming conventions, indentation, and spacing. Refer to the `.php-cs-fixer.dist.php` file for specific coding style rules

## Commands

All quality checks should be run via Docker. Prefix any `composer` script with `docker:` to run inside the container.

```bash
composer docker:test            # run PHPUnit
composer docker:cs              # check coding style (dry-run)
composer docker:cs-fix          # auto-fix coding style
composer docker:analyze         # PHPStan (level 5) + Rector dry-run
composer docker:rector          # apply Rector changes
composer docker:ci              # cs → analyze → test (phpcpd not available in Docker)
composer docker:shell           # bash shell inside container
composer docker:build           # rebuild image after Dockerfile changes
```

Run a single test file inside Docker:
```bash
composer docker:test -- tests/SomeTest.php
```

## Architecture

**CI4 Auto-Discovery** — CI4 discovers this package automatically via Composer autoload. No manual wiring is needed in the host app.

### Key files

- `src/Config/Kindling.php` — base config class users extend in `app/Config/Kindling.php`. Properties: `$devServerUrl`, `$manifestPath`, `$buildPath`, `$forceMode`, `$entryPoints`.
- `src/Config/Services.php` — registers `ViteService` under the `vite` key as a shared singleton; CI4 discovers it via namespace scanning.
- `src/Config/Registrar.php` — CI4 calls static methods here during bootstrap to register filter aliases and config hooks.
- `src/Services/ViteService.php` — main entry point; detects dev vs prod, emits `<script>`/`<link>` tags, deduplicates chunks across multiple `tags()` calls per request.
- `src/Services/ManifestReader.php` — parses `manifest.json`, resolves entry points to `ResolvedEntry` DTOs with depth-first chunk traversal.
- `src/Services/ResolvedEntry.php` — readonly DTO: `string $file`, `array $css`, `array $imports` (all raw manifest paths relative to build root).
- `src/Helpers/vite_helper.php` — loaded via `autoload.files`; exposes `vite_tags(string $entry, ?string $nonce = null): string`.
- `src/Exceptions/KindlingException.php` — base exception; factories: `forNoViteRunning()`, `forUnknownEntry(name, valid[])`.
- `src/Exceptions/KindlingManifestException.php` — extends base; factories: `forMissingManifest(path)`, `forMissingEntry(key, valid[])`.
- `src/plugin.js` — ESM Vite plugin bundled with the package; imported directly from `vendor/` in generated `vite.config.js`.

**Dev/prod detection** uses a sentinel file (`public/build/.vite-dev-running`) written by `plugin.js` on dev server start and deleted on shutdown/build. `Config\Kindling::$forceMode` (`'dev'`|`'prod'`|`null`) overrides detection.

**Namespace**: `Myth\Kindling\` → `src/`. Test namespaces: `Tests\` → `tests/`, `Tests\Support\` → `tests/_support/`.

**PHPUnit bootstrap**: `vendor/codeigniter4/framework/system/Test/bootstrap.php` — required for CI4 test helpers; must remain in `phpunit.xml.dist`.

## Code Style Rules

- **No file-level copyright/license docblocks** — `header_comment` is disabled in `.php-cs-fixer.dist.php`. Do not add them.
- All files use `declare(strict_types=1)`.
- Error messages always prefixed with `Kindle:` and include actionable instructions (e.g. `"Kindle: No manifest found at '{path}'. Run 'npm run build' to generate it."`).

## PHPStan

Level 5 with strict rules (`phpstan.neon.dist`). When adding new `Config\` classes or `Services`, register them under `parameters.codeigniter.additionalConfigNamespaces` / `additionalServices` in `phpstan.neon.dist`.

## CI Workflows

Workflows run on `main` branch PRs/pushes. PHPUnit matrix: PHP 8.2–8.5 × MySQL / SQLite / PostgreSQL / SQLSRV / OCI8.

## Pre-commit Hook

`composer install`/`update` installs a pre-commit hook (`admin/pre-commit → .git/hooks/pre-commit`) that lints staged `.php` files and auto-runs `php-cs-fixer` on them.
