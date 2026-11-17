# Page Spec — Public Order Share

> **Overrides:** this file overrides `MASTER.md` for the unauthenticated
> customer share page (`GET /public/orders/{token}` →
> `resources/views/public/orders/show.blade.php`, layout
> `layouts/public-order.blade.php`, styles in
> `public/assets/css/pages/public-order.css`, built into `app.css`).
> Everything not covered here follows MASTER.md.

**Surface:** customer-facing, unauthenticated, bearer-token access. The
layout enforces a strict CSP — `default-src 'none'; style-src 'self';
img-src 'self' data:; frame-ancestors 'none'`. **No external fonts,
scripts, images, or asset requests of any kind.** Styles come from the
single same-origin `app.css`; the logo/favicon, when configured, must be
same-origin too.

---

## Layout

- Page background: `--c-neutral-50`.
- Single centered column `.public-order-wrap`: `max-width: 42rem`,
  vertical stack, gap `--sp-5`.
- Three vertical zones, in order:
  1. **Brand strip** (`.public-order-brand`): logo (or 2rem
     neutral-900 monogram square `.public-order-brand-icon`) + site
     name. No navigation, no header bar, no portal chrome.
  2. **Main card** (`.public-order-card`): white, 1px `--c-neutral-200`
     border, `--radius-lg`, `--shadow-sm`, `--sp-5` padding.
  3. **Footnote** (`.public-order-foot`): 12px `--c-neutral-400`,
     centered reassurance line ("secure, customer-only view").

## Card content

- **Header row** (`.public-order-head`): order title
  ("Order" + order number in `.font-mono`) on the left; a status
  `.badge` with `.badge-dot` on the right. Row wraps on narrow screens
  (`flex-wrap`).
- **Fact grid** (`.public-order-grid`): 2 columns, gap `--sp-3`;
  collapses to 1 column below 40rem (640px). Each fact tile is a
  `.card .p-4` (subtle `--shadow-sm`, matching the default card spec).
- **Fact tile anatomy:**
  - label `.public-label` — 12px, 500 weight, uppercase, 0.04em
    letter-spacing, `--c-neutral-500`.
  - value `.public-value` — 14px, 500 weight, `--c-neutral-900`,
    top margin `--sp-1`.
  - secondary line `.public-muted` — 12px, `--c-neutral-500`, top
    margin `--sp-0-5`.
- Fixed fact set: Product (with duration), Subscription (status +
  start/expiry), Payment (due, collected, method, payment badge),
  Created (date, renewal).
- Money: `Setting::currencySymbol()` + 2-decimal formatting; order
  number and any key material always in `.font-mono`.

## Status badge mapping

| Order status | Badge class |
|---|---|
| active, confirmed | `badge-success` |
| pending, suspended | `badge-warning` |
| cancelled, expired | `badge-danger` |
| anything else | `badge-neutral` |

| Payment status | Badge class |
|---|---|
| paid | `badge-success` |
| partially_paid | `badge-info` |
| pending | `badge-warning` |
| failed | `badge-danger` |
| refunded, partially_refunded, other | `badge-neutral` |

## API key block (opt-in only)

Rendered only when the share opted in AND the order is share-eligible.
The customer's key **never renders by default** — masked indicator
(`.font-mono`) + a `.btn.btn-outline.btn-sm` "Reveal my API key" POST.

- Block: `.public-key-card` (bordered, no extra shadow).
- Masked state: masked key in the head line, muted copy explaining the
  reveal is view-only and not stored.
- Revealed state: `.public-key-reveal` — amber card
  (`--c-amber-50` bg, `--c-amber-200` border, `--radius-md`), key in
  `.public-key-text` (white chip, `--radius-sm`, `word-break: break-all`)
  with a `.btn.btn-primary.btn-sm` "Copy key" button
  (`navigator.clipboard`, same-origin JS-free — the button is inline
  `onclick`, no script tag, which the CSP allows).
- One-time semantics: the reveal lives in the session for exactly one
  view; success/notice lines use `.public-key-note` with
  `text-emerald-800` / `text-amber-800`.

## Motion & interaction

- No entrance animations on this surface (informational, unauthenticated
  page — prefer zero motion; if one is added, fade-only per MASTER.md).
- Buttons: default `.btn` hover states (`--transition-fast`, color
  shifts only), visible neutral focus ring.
- Copy/Reveal buttons: `cursor: pointer` (inherited from `.btn`
  base/reset).

## Accessibility

- Contrast floor: `--c-neutral-500` labels on white = 4.6:1 ✓; footnote
  `--c-neutral-400` is the one deliberate exception (reassurance copy
  only, non-essential).
- Badge text uses the 700–900 shade of its scale for 4.5:1 on its
  tinted background.
- `prefers-reduced-motion`: no motion to suppress by default; keep it
  that way.

## Do NOT

- ❌ No external font/asset requests (CSP blocks them; no exceptions).
- ❌ No navigation, breadcrumbs, or links that leak internal structure.
- ❌ No plaintext key in the initial HTML — masked or hidden only.
- ❌ No raw margin/spacing values — `--sp-*` tokens, per MASTER.md.
- ❌ No layout-shifting hover transforms.
