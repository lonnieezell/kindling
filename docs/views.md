# Using in Views

kindling provides a global helper function and a service you can call directly. Both do the same thing — pick whichever fits your code style.

## The `vite_tags()` helper

The helper is loaded automatically via CI4's autoload. Call it inside `<head>` for each entry point you want on that page:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My App</title>
    <?= vite_tags('app') ?>
</head>
<body>
    <!-- your content -->
</body>
</html>
```

In dev mode this emits the HMR client and a module script pointing at the Vite dev server. In production it emits modulepreload links, stylesheet links, and a hashed module script — all resolved from the Vite manifest.

### Signature

```php
vite_tags(string $entry, ?string $nonce = null): string
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$entry` | `string` | The entry point name, matching a key in `$entryPoints` |
| `$nonce` | `string\|null` | Optional CSP nonce applied to all `<script>` tags |

### Calling the service directly

If you prefer to avoid global functions, use the CI4 service directly:

```php
<?= service('vite')->tags('app') ?>
```

Both call the same underlying `ViteService::tags()` method.

## Multiple entry points per page

If a page needs more than one bundle, call `vite_tags()` multiple times:

```html
<head>
    <?= vite_tags('app') ?>
    <?= vite_tags('admin') ?>
</head>
```

kindling deduplicates across calls within the same request:

- The HMR client `<script>` (dev mode) is emitted once, no matter how many times you call `vite_tags()`
- Shared chunk `<link rel="modulepreload">` tags (prod mode) are emitted once per chunk, even if multiple entry points share the same dependency

## CSP nonce support

kindling accepts a nonce attribute string as the second argument and applies it to every `<script>` tag it emits. The second argument should be the full attribute string, not just the value — this lets you pass CI4's built-in CSP helpers directly.

CI4 offers two approaches depending on whether `$autoNonce` is enabled in your `Config\ContentSecurityPolicy`:

=== "Placeholder (autoNonce enabled)"
    When `$autoNonce = true` (the default), CI4 replaces `{csp-script-nonce}` with the real nonce attribute in the response body automatically. Pass the placeholder as a string:

    ```html
    <?= vite_tags('app', '{csp-script-nonce}') ?>
    ```

    kindling emits:

    ```html
    <script type="module" src="..." {csp-script-nonce}></script>
    ```

    CI4's output filter then replaces `{csp-script-nonce}` with `nonce="abc123"` before sending the response.

=== "Function (autoNonce disabled)"
    When `$autoNonce = false`, use `csp_script_nonce()` which returns the full `nonce="..."` attribute string:

    ```html
    <?= vite_tags('app', csp_script_nonce()) ?>
    ```

    kindling emits the attribute directly:

    ```html
    <script type="module" src="..." nonce="abc123"></script>
    ```

!!! note
    The nonce is applied to `<script>` tags only, not `<link>` tags. `<link rel="stylesheet">` and `<link rel="modulepreload">` don't take a nonce — they're governed by `style-src` and `default-src` in your CSP header.

## Error handling

`vite_tags()` throws a `KindlingException` in two situations:

| Situation | How to fix |
|-----------|-----------|
| The entry name isn't in `$entryPoints` | Check the name matches a key in `app/Config/Kindling.php` |
| Neither the dev server nor a built manifest is available | Run `npm run dev` or `npm run build` first |

In development you'll see these as CI4's standard error page. In production, let your global exception handler catch them — they indicate a deployment problem (missing build artifacts), not a user error.
