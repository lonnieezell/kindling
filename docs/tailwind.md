# Tailwind CSS

kindling has built-in support for [Tailwind CSS v4](https://tailwindcss.com/) via the official `@tailwindcss/vite` plugin. Tailwind integrates at the Vite layer — you import it in your CSS and Vite handles the rest.

## At install time

The easiest path: pass `--tailwind` when you run the install command.

```bash
php spark kindling:install --tailwind
```

Or answer `y` at the interactive prompt. kindling generates a Tailwind-aware `vite.config.js` and a CSS entry stub with the Tailwind import already included.

### What's different with Tailwind enabled

**`vite.config.js`** includes the Tailwind plugin:

```js
import { defineConfig } from 'vite'
import codeigniter from './vendor/myth/kindling/src/plugin.js'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        codeigniter({
            input: ['resources/js/app.js'],
        }),
        tailwindcss(),
    ],
})
```

**`resources/css/app.css`** starts with the Tailwind import:

```css
@import "tailwindcss";

/* your styles below */
```

**`package.json`** includes `@tailwindcss/vite` as a dev dependency alongside `vite`.

## Adding Tailwind to an existing install

If you already ran `kindling:install` without `--tailwind`, here's how to add it manually.

### 1. Install the package

```bash
npm install --save-dev @tailwindcss/vite
```

### 2. Update `vite.config.js`

```js
import { defineConfig } from 'vite'
import codeigniter from './vendor/myth/kindling/src/plugin.js'
import tailwindcss from '@tailwindcss/vite'   // add this

export default defineConfig({
    plugins: [
        codeigniter({
            input: ['resources/js/app.js'],
        }),
        tailwindcss(),   // add this
    ],
})
```

### 3. Add the Tailwind import to your CSS

Open your CSS entry point (e.g. `resources/css/app.css`) and add this as the first line:

```css
@import "tailwindcss";
```

That's it. Tailwind v4 doesn't need a `tailwind.config.js` — it scans your project for class names automatically.

## How it works

Tailwind v4's Vite plugin processes your CSS as part of the build. In dev mode, Vite injects styles via HMR — add a class to your HTML and the browser updates without a page reload. In production, Vite extracts the final CSS to a hashed `.css` file referenced in the manifest, which kindling emits as a `<link rel="stylesheet">` tag.

You don't need to configure anything in `app/Config/Kindling.php` — Tailwind is transparent at the PHP layer.

!!! tip "No config file needed"
    Tailwind CSS v4 dropped the `tailwind.config.js` file. Content scanning is automatic. If you're upgrading from v3, you can delete that config file — it won't be read.
