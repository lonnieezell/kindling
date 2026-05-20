# myth/kindling

[![Tests](https://github.com/myth/kindling/actions/workflows/test.yml/badge.svg)](https://github.com/myth/kindling/actions)
[![PHP Version](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A [Vite](https://vitejs.dev/) asset pipeline for [CodeIgniter 4](https://codeigniter.com/) — HMR in development, fingerprinted chunk-aware output in production, and zero manual wiring thanks to CI4's auto-discovery.

**[Full documentation →](https://myth.github.io/kindling/)**

## What you get

- **One Spark command** — `kindling:install` scaffolds your entire asset setup
- **HMR in development** — edit a JS or CSS file and the browser updates instantly, no reload
- **Fingerprinted builds in production** — Vite hashes every output file; kindling reads the manifest so your views always reference the right URLs
- **Shared chunk deduplication** — if two entry points share a module, the preload tag is emitted once per request
- **Optional integrations** — [Tailwind CSS v4](#tailwind-css), [Alpine.js](#alpinejs), and [HTMX v2](#htmx) all opt-in at install time
- **CSP nonce support** — pass a nonce to `vite_tags()` and it's threaded through every emitted `<script>` tag

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- Node.js 18+

## Installation

```bash
composer require myth/kindling
```

CI4 discovers the package automatically via Composer — no manual registration needed.

## Quick start

Scaffold your asset setup with a single command:

```bash
php spark kindling:install
```

The command asks which entry points you want and whether to include Tailwind, Alpine, or HTMX. Pass flags to skip the prompts:

```bash
php spark kindling:install --entry=app --tailwind --alpine --no-htmx
```

Then install npm dependencies and start the dev server:

```bash
npm install
npm run dev
```

Add `vite_tags()` to your CI4 layout:

```html
<head>
    <?= vite_tags('app') ?>
</head>
```

That's it. See the [Getting Started guide](https://myth.github.io/kindling/getting-started/) for the full walkthrough.

## Optional integrations

### Tailwind CSS

Pass `--tailwind` at install time to include [Tailwind CSS v4](https://tailwindcss.com/) via the official Vite plugin. kindling generates a Tailwind-aware `vite.config.js` and a CSS entry stub with the import already included.

```bash
php spark kindling:install --tailwind
```

[Tailwind CSS docs →](https://myth.github.io/kindling/tailwind/)

### Alpine.js

Pass `--alpine` to include [Alpine.js](https://alpinejs.dev/). kindling adds it to `package.json` and writes the initialization code into your JS entry file.

```bash
php spark kindling:install --alpine
```

[Alpine.js docs →](https://myth.github.io/kindling/alpine/)

### HTMX

Pass `--htmx` to include [HTMX v2](https://htmx.org/). Works great alongside [michalsn/codeigniter-htmx](https://github.com/michalsn/codeigniter-htmx) for CI4-aware HTMX helpers.

```bash
php spark kindling:install --htmx
```

[HTMX docs →](https://myth.github.io/kindling/htmx/)

## Documentation

Full documentation is at **[myth.github.io/kindling](https://myth.github.io/kindling/)**.

- [Getting Started](https://myth.github.io/kindling/getting-started/) — working in under 5 minutes
- [Vite Fundamentals](https://myth.github.io/kindling/vite-fundamentals/) — new to Vite? Start here
- [Configuration](https://myth.github.io/kindling/configuration/) — every config option explained
- [Using in Views](https://myth.github.io/kindling/views/) — the `vite_tags()` helper and nonce support

## Contributing

Pull requests are welcome. The project uses Docker for a consistent dev environment.

```bash
composer docker:test        # run PHPUnit
composer docker:cs-fix      # fix coding style
composer docker:analyze     # PHPStan + Rector
composer docker:ci          # run all checks
composer docker:shell       # bash inside the container
```

## License

MIT — see [LICENSE](LICENSE).
