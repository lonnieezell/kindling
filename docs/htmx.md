# HTMX

kindling has built-in support for [HTMX v2](https://htmx.org/) — a library that lets you access modern browser features directly from HTML using attributes. It's ideal for CI4 apps that want to add dynamic behavior by returning HTML fragments from the server rather than JSON.

!!! tip "CI4 companion library"
    For the best HTMX experience in CodeIgniter 4, pair this with [michalsn/codeigniter-htmx](https://github.com/michalsn/codeigniter-htmx). It adds request detection helpers, response utilities, and HTMX-aware redirects to your CI4 controllers.

## At install time

Pass `--htmx` when you run the install command:

```bash
php spark kindling:install --htmx
```

Or answer `y` at the interactive prompt. kindling adds HTMX to your `package.json` and imports it in your JS entry file.

### What gets generated

**`resources/js/app.js`** includes the HTMX import:

```js
import 'htmx.org'
```

That's it. HTMX is a side-effect import — it attaches itself to `window.htmx` automatically and starts listening for `hx-*` attributes.

**`package.json`** includes `htmx.org` as a dev dependency:

```json
{
  "devDependencies": {
    "vite": "^6.0",
    "htmx.org": "^2.0"
  }
}
```

Run `npm install` after kindling generates these files.

## Adding HTMX to an existing install

If you already ran `kindling:install` without `--htmx`, here's how to add it manually.

### 1. Install the package

```bash
npm install --save-dev htmx.org
```

### 2. Update your JS entry file

Open your JS entry point (e.g. `resources/js/app.js`) and add:

```js
import 'htmx.org'
```

## Using HTMX in your views

With HTMX imported, you can use `hx-*` attributes in your CI4 view files:

```html
<button hx-get="/messages" hx-target="#inbox" hx-swap="innerHTML">
    Load messages
</button>

<div id="inbox"></div>
```

When the button is clicked, HTMX sends a `GET` request to `/messages` and swaps the response HTML into `#inbox` — no JavaScript required on your part.

Your CI4 controller returns a partial view:

```php
public function messages(): string
{
    $messages = $this->messageModel->findAll();
    return view('partials/messages', ['messages' => $messages]);
}
```

!!! warning "Return HTML, not JSON"
    HTMX expects your endpoints to return HTML fragments, not JSON. If you're used to building REST APIs, this is the key mindset shift. Your controller actions render partial views instead of calling `$this->response->setJSON(...)`.

## Next steps

- [Alpine.js](alpine.md) — add reactive client-side behavior alongside HTMX
- [Using in Views](views.md) — how kindling emits `<script>` tags in your CI4 layouts
