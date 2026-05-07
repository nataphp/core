# Nata Frontend

jQuery-independent vanilla JS + CSS library for NataPHP forms and UI components.

## Structure

```
Nata/frontend/
  src/
    js/          — source JS (ES modules)
    css/form/    — source CSS
  dist/          — build output, copy to `public/nata/`
```

## Install

From the project root:

```bash
npm install
```

## Build

```bash
# Build JS and CSS once
npm run build:nata

# Build JS only
npm run build:nata:js

# Build CSS only
npm run build:nata:css

# Watch JS for changes
npm run watch:nata
```

Output goes to `Nata/frontend/dist/`. Copy the contents to `public/nata/` to deploy.

## Deploy

```bash
cp -r Nata/frontend/dist/* public/nata/
```

Also copy any third-party dependencies (e.g. `input-mask.min.js`) to `public/nata/`.
Third-party vendor libs (Dropzone, flexdatalist, rateYo, etc.) remain under `public/nata/vendor/` as tracked assets.

## Usage in PHP templates

```html
<!-- Core -->
<script src="/nata/nata.min.js"></script>

<!-- Forms (includes all form components) -->
<link rel="stylesheet" href="/nata/nata.form.min.css">
<script src="/nata/nata.form.min.js"></script>
```

Component CSS (`nata.upload.css`, `nata.wizard.css`) is injected automatically by each component when it detects its elements on the page — no manual `<link>` needed.

Source maps (`*.min.js.map`) are included in `dist/` for browser devtools debugging.

## Adding a new component

1. Create `src/js/form/components/mycomponent.js` and export a default object with at least an `init()` method.
2. Register it in `src/js/form/form.js`.
3. If it needs its own CSS, create `src/css/form/nata.mycomponent.css` and add a `_injectCss(_base() + '/nata/nata.mycomponent.css')` call inside `init()`.
4. Add the CSS to the `build:nata:css` script in `package.json`.
