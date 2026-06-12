# iOS site background fix — rollback guide

Added May 2026. Production-ready after testing on iPhone (Safari/Chrome).

## Problem

WordPress Customizer body background uses `background-attachment: fixed`. iOS WebKit
zooms/crops that image (mostly shadow, cars cut off). Desktop and Chrome DevTools mobile
emulation look fine; real iPhones do not.

## What we did (keep unless rolling back)

| Area | Change |
|------|--------|
| **iOS (iPhone/iPad)** | Fixed full-viewport `<img>` via `bst_render_ios_background_image()` — same URL/position as Customizer. Body CSS background disabled on iOS. |
| **iOS inner pages** | `<span class="bst-ios-bg-tint">` on the img layer (replaces `.translucent-overlay::before` on iOS; CSS pseudo-elements on fixed layers are unreliable). |
| **iOS homepage** | Same `<img>` layer, **no tint** (matches desktop: background only, no overlay). |
| **Android touch** | `background-attachment: scroll` only via `bst_non_ios_touch_background_fix()`. |
| **Page cache** | `js/bst-ios-background.js` re-applies the iOS layer in the browser when cached HTML was built for a non-iOS user agent (logged-in users bypass cache and often look fine). |
| **Tour listing templates** | Single `.translucent-overlay` wrapper (banner + breadcrumbs + listings) so iOS tint covers the full listing area without a bright gap. |

## Files touched

1. `functions.php` — lines from `bst_is_ios_request()` through `bst_enqueue_ios_background_client_fallback()` (before `// END ENQUEUE PARENT ACTION`)
2. `style.css` — block from `body.bst-ios-bg.custom-background` through `body.bst-ios-bg-tinted … ::before` (just above `/* Create the translucent overlay effect */`)
3. `js/bst-ios-background.js` — client fallback for cached pages
3. `archive-tour-type.php` — one continuous `.translucent-overlay` (see comment `BST iOS bg:` in file)
4. `taxonomy-tour-type-code.php` — same; removed an extra stray `</div>` before listings

Search the theme for: `bst-ios`, `bst_is_ios`, `bst_should_use_ios`, `BST iOS bg`

---

## Quick disable (emergency, no file deletion)

In `functions.php`, make `bst_should_use_ios_background_layer()` always return `false`:

```php
function bst_should_use_ios_background_layer() {
    return false;
}
```

Also comment out the `add_action` hooks for `bst_render_ios_background_image` if you want zero HTML output.

**After quick disable on iPhone:** background framing reverts to the old zoom/crop bug; desktop unchanged.

---

## Full rollback (remove the fix entirely)

### 1. `functions.php`

Delete (or revert via git) everything from the block comment `BST iOS site background fix` through
`add_action( 'wp_enqueue_scripts', 'bst_non_ios_touch_background_fix', 20 );`

That removes:

- `bst_is_ios_request()`
- `bst_should_use_ios_background_layer()`
- `bst_should_use_ios_background_tint()`
- `bst_ios_body_class()` + `body_class` filter
- `bst_render_ios_background_image()` + `wp_body_open` / `wp_footer` hooks
- `bst_non_ios_touch_background_fix()` + enqueue hook

Leave `// END ENQUEUE PARENT ACTION` and everything below it intact.

### 2. `style.css`

Delete the block from `/* BST iOS site background fix` through the closing `body.bst-ios-bg-tinted … content: none;` rule (just before `/* Create the translucent overlay effect */`).

### 3. `archive-tour-type.php`

Restore **two** `.translucent-overlay` wrappers:

- Close the first overlay immediately after `top-banner-container` (`</div>` after banner).
- Open a **new** `<div class="translucent-overlay">` immediately before `<!-- TOUR LISTINGS -->`.

See git history before the “tour listing overlay merge” commit if unsure.

### 4. `taxonomy-tour-type-code.php`

Same as archive: close overlay after banner; open second overlay before tour listings.
(Previously there was also a stray `</div>` before listings — do **not** re-add that.)

### 5. Optional

Delete this file: `IOS-BACKGROUND-FIX-ROLLBACK.md`

### 6. Deploy

Push to `main` (testing slot auto-deploys). Run the **production** GitHub Actions workflow manually when ready.

Verify on a real iPhone: inner page (e.g. driving tour listing) and homepage.

---

## After full rollback — expected behavior

| | Desktop | iPhone |
|---|---------|--------|
| Background | Customizer fixed body bg | Same CSS, zoom/crop bug returns |
| Inner page overlay | `.translucent-overlay::before` | Same as desktop (may look wrong with zoomed bg) |
| Homepage | Background, no overlay | Same |
| Tour listing gap | May show small untinted strip if split overlays restored | Same |

---

## If a Colibri / parent theme update breaks this fix

1. Check `#colibri` / `wp_body_open` still fire (img layer outputs at body start).
2. Check nothing sets `z-index` or opaque `background` on `html`/`body` that covers `z-index: -1` layer.
3. Check Customizer background image still set (Appearance → Background Image).
4. Try **quick disable** first to confirm the regression is this code vs theme.
5. Do **not** re-add approaches that failed earlier:
   - `body { background-image: none }` + `body::before` fixed pseudo-element
   - CSS `background-image` on a fixed `<div>` without `<img>`
   - CSS `::after` tint on fixed layers (use real `.bst-ios-bg-tint` element instead)

---

## Testing notes

- Chrome DevTools mobile mode does **not** run this fix (desktop user agent). Test on a real iPhone or set UA to iPhone in DevTools → Network conditions.
- Homepage: `bst-ios-bg` yes, `bst-ios-bg-tinted` no.
- Inner pages: both classes; View Source should show `.bst-ios-bg-layer` with img + tint span.
