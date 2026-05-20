# Vite Fundamentals

If you've been serving assets the traditional PHP way — dropping files in `public/`, maybe running a Gulp or Webpack task — Vite works differently. This page explains the mental model so nothing kindling does feels like magic.

## What Vite actually is

Vite is a build tool for frontend assets (JavaScript, CSS, images). Think of it as a smarter replacement for a Webpack or Laravel Mix setup. It does two jobs:

1. **Development server** — serves your source files with instant hot updates
2. **Production builder** — bundles, minifies, and fingerprints everything for deployment

You write your JS and CSS in `resources/`. Vite handles the rest.

## Entry points

An **entry point** is the JS file where your bundle starts. Vite follows its `import` statements to find everything else.

```js
// resources/js/app.js
import './bootstrap'
import '../css/app.css'   // CSS imported from JS — Vite handles this
import Alpine from 'alpinejs'

Alpine.start()
```

You can have multiple entry points — for example `app` for your public-facing site and `admin` for a back-office panel. Each becomes its own independent bundle.

In kindling, entry points are named in your config:

```php
// app/Config/Kindling.php
public array $entryPoints = [
    'app'   => 'resources/js/app.js',
    'admin' => 'resources/js/admin.js',
];
```

Then in your views you reference them by name, not by file path:

```php
<?= vite_tags('app') ?>
```

## The dev server and HMR

When you run `npm run dev`, Vite starts a local server on port 5173. Your CI4 app stays on its normal port (8080 or similar) — the two servers run side by side.

**Hot Module Replacement (HMR)** is what makes the dev experience fast. When you save a file, Vite pushes only the changed module to the browser over a WebSocket. The page doesn't reload; the module is swapped in place. Edit a CSS file and the styles update in milliseconds.

kindling detects that the Vite dev server is running by checking for a sentinel file at `public/build/.vite-dev-running`. The Vite plugin (`plugin.mjs`) creates this file when the dev server starts and removes it when it stops. This means your PHP app always knows which mode it's in without any manual configuration.

In dev mode, `vite_tags('app')` emits two `<script>` tags:

```html
<!-- The HMR client — connects to the Vite WebSocket -->
<script type="module" src="http://localhost:5173/@vite/client"></script>

<!-- Your entry point, served directly from Vite -->
<script type="module" src="http://localhost:5173/resources/js/app.js"></script>
```

These point directly at the Vite dev server. Your source files are served raw — no bundling happens in dev mode. This is why dev feedback is instant.

!!! tip "Multiple `vite_tags()` calls"
    If you call `vite_tags()` twice in one request (e.g. in a layout and a subview), the HMR `<script>` is only emitted once. kindling deduplicates it automatically.

## The manifest and fingerprinting

When you run `npm run build`, Vite does several things:

1. **Bundles** — combines all your imports into output files
2. **Minifies** — removes whitespace and shortens variable names
3. **Fingerprints** — adds a content hash to every filename, e.g. `app-Bz4fABvE.js`
4. **Writes a manifest** — creates `public/build/.vite/manifest.json` mapping each source entry to its hashed output

The manifest looks like this:

```json
{
  "resources/js/app.js": {
    "file": "assets/app-Bz4fABvE.js",
    "css": ["assets/app-CqYdKUiY.css"],
    "imports": ["assets/shared-DzK0eDqj.js"]
  }
}
```

kindling reads this manifest and translates `vite_tags('app')` into the correct hashed URLs:

```html
<!-- Shared chunk preloaded so the browser fetches it in parallel -->
<link rel="modulepreload" href="/build/assets/shared-DzK0eDqj.js">

<!-- The stylesheet -->
<link rel="stylesheet" href="/build/assets/app-CqYdKUiY.css">

<!-- The entry point -->
<script type="module" src="/build/assets/app-Bz4fABvE.js"></script>
```

### Why fingerprinting matters

The hash in the filename is derived from the file's content. If the file doesn't change between deployments, its hash doesn't change — so browsers can cache it indefinitely. When you ship a fix, only the changed files get new hashes. Users download the minimum necessary on each deployment.

## Shared chunks

If you have two entry points that both import the same library (say, Alpine.js), Vite's bundler extracts that shared code into a separate chunk rather than duplicating it in both bundles. kindling emits `<link rel="modulepreload">` tags for these chunks so the browser can fetch them in parallel with the entry point.

If you call `vite_tags('app')` and `vite_tags('admin')` in the same request, shared chunk preloads are deduplicated — each chunk appears in the HTML exactly once.

## Dev vs production at a glance

| | Development (`npm run dev`) | Production (`npm run build`) |
|---|---|---|
| Source files | Served raw from Vite at port 5173 | Bundled, minified, hashed in `public/build/` |
| CSS | Injected by JS via HMR | Extracted to separate `.css` files |
| How kindling knows | Sentinel file present | Reads `manifest.json` |
| Page reload on change | No — HMR updates in place | N/A |
| Suitable for | Local development | Staging and production servers |

## Next steps

- [Configuration](configuration.md) — all the knobs you can turn
- [Using in Views](views.md) — the full `vite_tags()` API including nonce support
