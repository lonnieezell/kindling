# Alpine.js

kindling has built-in support for [Alpine.js](https://alpinejs.dev/) — a lightweight framework for adding reactive behavior directly in your HTML. It's a natural fit for CI4 apps that want interactivity without pulling in a full frontend framework.

## At install time

Pass `--alpine` when you run the install command:

```bash
php spark kindling:install --alpine
```

Or answer `y` at the interactive prompt. kindling adds Alpine to your `package.json` and writes the initialization code into your JS entry file automatically.

### What gets generated

**`resources/js/app.js`** includes the Alpine setup:

```js
import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()
```

The `window.Alpine = Alpine` line is intentional — it lets Alpine's devtools browser extension detect the instance, and makes `Alpine` accessible from inline scripts if you need it.

**`package.json`** includes `alpinejs` as a dev dependency:

```json
{
  "devDependencies": {
    "vite": "^6.0",
    "alpinejs": "^3.0"
  }
}
```

Run `npm install` after kindling generates these files.

## Adding Alpine to an existing install

If you already ran `kindling:install` without `--alpine`, here's how to add it manually.

### 1. Install the package

```bash
npm install --save-dev alpinejs
```

### 2. Update your JS entry file

Open your JS entry point (e.g. `resources/js/app.js`) and add:

```js
import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()
```

That's all the wiring Alpine needs. No config file, no build plugin.

## Using Alpine in your views

Once Alpine is running, you can use `x-` directives directly in your Blade or PHP view files:

```html
<div x-data="{ open: false }">
    <button @click="open = !open">Toggle</button>
    <p x-show="open">Hello from Alpine!</p>
</div>
```

Alpine scans the DOM on load and activates any element with `x-data`. In dev mode, Vite's HMR keeps your JS bundle live — save your JS entry file and Alpine reinitializes without a page reload.

!!! tip "Alpine plugins"
    Alpine has an official plugin ecosystem — Collapse, Focus, Persist, and more. Install them via npm and register them before `Alpine.start()`:

    ```js
    import Alpine from 'alpinejs'
    import Collapse from '@alpinejs/collapse'

    window.Alpine = Alpine
    Alpine.plugin(Collapse)
    Alpine.start()
    ```

## Next steps

- [HTMX](htmx.md) — pair Alpine with HTMX for server-driven UI without a full SPA
- [Using in Views](views.md) — how kindling emits `<script>` tags in your CI4 layouts
