# myth/kindling

myth/kindling gives CodeIgniter 4 apps a first-class [Vite](https://vitejs.dev/) asset pipeline — hot module replacement in development, fingerprinted chunk-aware output in production, and zero manual wiring thanks to CI4's auto-discovery.

## What you get

- **One Spark command** (`kindling:install`) scaffolds your entire asset setup: `vite.config.js`, entry point stubs, and `app/Config/Kindling.php`
- **HMR in development** — edit a JS or CSS file, see it update instantly without a page reload
- **Fingerprinted builds in production** — Vite hashes every output file; kindling reads the manifest so your views always reference the right URLs
- **Shared chunk deduplication** — if two entry points import the same module, the preload tag is emitted once per request, not twice
- **Tailwind CSS v4 opt-in** — pass `--tailwind` at install time and everything is wired up for you
- **CSP nonce support** — thread a nonce through every emitted `<script>` tag with a single argument

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- Node.js 18+ (for Vite)

## Quick links

- [Getting Started](getting-started.md) — install and see it working in under 5 minutes
- [Vite Fundamentals](vite-fundamentals.md) — new to Vite? Start here
- [Configuration](configuration.md) — every config option explained
- [Using in Views](views.md) — the `vite_tags()` helper and nonce support
- [Tailwind CSS](tailwind.md) — opt-in Tailwind v4 integration
