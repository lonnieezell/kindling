# Getting Started

You'll have a working Vite pipeline in your CI4 app in about five minutes.

## 1. Install the package

```bash
composer require myth/kindling
```

CI4 auto-discovers the package via Composer — no manual registration needed.

## 2. Scaffold the asset setup

Run the Kindling install command from your project root:

```bash
php spark kindling:install
```

The command will ask two questions interactively:

- **Entry points** — names for your JS bundles (default: `app`). Comma-separate multiple names, e.g. `app, admin`.
- **Tailwind CSS v4?** — answer `y` to include Tailwind, `n` to skip.

You can also pass these as flags to skip the prompts:

```bash
# Single entry point, no Tailwind
php spark kindling:install --entry=app --no-tailwind

# Multiple entry points with Tailwind
php spark kindling:install --entry=app,admin --tailwind
```

### What gets generated

| File | Purpose |
|------|---------|
| `vite.config.js` | Vite config with the kindling plugin wired up |
| `package.json` | npm manifest with `vite` (and `@tailwindcss/vite`) as dev dependencies |
| `resources/js/app.js` | JS entry point stub |
| `resources/css/app.css` | CSS entry point stub |
| `app/Config/Kindling.php` | Your app's kindling config |
| `.gitignore` (updated) | `node_modules/` and `public/build/` appended if missing |

All writes are idempotent — if a file already exists it's skipped with a notice, so you can re-run safely.

## 3. Install npm dependencies

```bash
npm install
```

## 4. Start the dev server

```bash
npm run dev
```

Vite starts on `http://localhost:5173`. It writes a sentinel file at `public/build/.vite-dev-running` so kindling knows dev mode is active.

## 5. Add the helper to your view

Open any CI4 view and add `vite_tags()` inside `<head>`:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <?= vite_tags('app') ?>
</head>
<body>
    <!-- your content -->
</body>
</html>
```

That's it. Edit `resources/js/app.js` or `resources/css/app.css` and the browser updates instantly.

## Building for production

When you're ready to deploy:

```bash
npm run build
```

Vite writes fingerprinted files to `public/build/` and generates `public/build/.vite/manifest.json`. kindling reads that manifest automatically — `vite_tags()` in your views emits the correct hashed URLs with no changes needed.

## Next steps

- [Vite Fundamentals](vite-fundamentals.md) — understand what's happening under the hood
- [Configuration](configuration.md) — customize entry points, build paths, and more
- [Tailwind CSS](tailwind.md) — add Tailwind if you skipped it at install time
