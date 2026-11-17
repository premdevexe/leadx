# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.
>
> **This file describes the system AS IMPLEMENTED in `public/assets/css/`
> (tokens in `core/variables.css`, built into `app.css`).** If you change a
> token value in the CSS, update this file in the same commit. Do not
> introduce tokens that don't exist in `variables.css`.

---

**Project:** LeadX
**Updated:** 2026-11-28 (aligned with app token system)
**Category:** CRM & Client Management
**Design Dials:** Variance 6/10 (Balanced / Modern) | Motion 4/10 (Standard) | Density 8/10 (Dense / Dashboard)

---

## Global Rules

### Color Palette

All colors are consumed through the `--c-*` tokens defined in
`public/assets/css/core/variables.css`. Never hardcode hex values in
component CSS; reference the token.

| Role | Token | Value | Notes |
|------|-------|-------|-------|
| Brand / Primary button | `--c-neutral-900` | `#171717` | `.btn-primary` background; header logo mark |
| On Primary | `--c-white` | `#ffffff` | Text on neutral-900 |
| Information / Links | `--c-blue-600` | `#2563eb` | `--c-blue-50/100/200/500/800/900` scale available |
| Deal / Success | `--c-green-700` | `#15803d` | Paid deals, active subscriptions; `--c-green-50/100/200/800/900` scale |
| Warning | `--c-amber-500/600/800/900` | `#f59e0b` … | Pending states, key-reveal block |
| Destructive | `--c-red-600` | `#dc2626` | `--c-red-50/100/200/700/800/900` scale |
| Indigo / Purple / Orange / Pink | `--c-indigo-*` … | — | Secondary accent scales, use sparingly |
| Background (page) | `--c-neutral-50` | `#fafafa` | `<body>` |
| Foreground (text) | `--c-neutral-900` | `#171717` | |
| Muted text | `--c-neutral-500` / `--c-neutral-400` | `#737373` / `#a3a3a3` | Labels, footnotes |
| Card / Surface | `--c-white` | `#ffffff` | |
| Border | `--c-neutral-200` (`--border-color`) | `#e5e5e5` | Card + form borders |
| Focus ring | `--focus-ring-color` | `rgb(23 23 23 / 0.1)` | 2px ring via box-shadow, neutral not blue |

**Color notes:** Neutral (black/white) carries branding and actions;
blue is reserved for information/link affordances; green is the "deal"
color (money, active subscriptions).

### Typography

- **Font stack:** `var(--font-sans)` = `'Inter', 'Instrument Sans', ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif`
- **Mood:** clean, dense, SaaS dashboard; Inter where installed locally, system fallback everywhere
- **No external font loading.** No Google Fonts `<link>` or `@import`
  anywhere — the customer-facing share layout enforces
  `Content-Security-Policy: default-src 'none'; style-src 'self'` and the
  portal layouts stay dependency-free for the same reason.
- **Type scale:** `--text-xs` 12 · `--text-sm` 14 · `--text-base` 16 ·
  `--text-lg` 18 · `--text-xl` 20 · `--text-2xl` 24 px. Dashboard surfaces
  default to `--text-sm`; `--text-xs` is the floor for labels/badges.
- **Monospace:** `--font-mono` via the `.font-mono` utility
  (ui-monospace stack) — used for order numbers, API keys, tokens.

### Spacing Variables

*Density: 8/10 — Dense / Dashboard*

The app uses a 4px-base `--sp-*` scale, not `--space-*`:

| Token | Value | Usage |
|-------|-------|-------|
| `--sp-1` | 4px | Icon gaps, inline spacing |
| `--sp-1-5` | 6px | |
| `--sp-2` | 8px | Standard gaps |
| `--sp-2-5` | 10px | |
| `--sp-3` | 12px | Section padding, table cells |
| `--sp-4` | 16px | Card padding, large gaps |
| `--sp-5` | 20px | `.card-body` padding |
| `--sp-6` | 24px | Section margins, page padding |
| `--sp-8` | 32px | Hero / major section padding |
| `--sp-10` / `--sp-12` / `--sp-16` | 40 / 48 / 64px | Page-level breathing room |

### Radius & Shadow

Radii: `--radius-sm` 4 · `--radius-md` 8 · `--radius-lg` 12 ·
`--radius-xl` 16 · `--radius-full` 9999 px.

| Level | Token | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgb(0 0 0 / 0.05)` | Standalone cards (e.g. public share page) |
| `--shadow-md` | `0 4px 6px …` | `.card-elevated` |
| `--shadow-lg` | `0 10px 15px …` | Lifted interactive cards |
| `--shadow-2xl` | `0 25px 50px -12px rgb(0 0 0 / 0.25)` | Modals and dropdowns only |

**Card surfaces:** `.card` = 1px `--c-neutral-200` border + subtle
`--shadow-sm`. Truly flat is opt-in via `.card-flat`; heavier lift is
`.card-elevated` (`--shadow-md`); `--shadow-lg` is hover-lift only;
`--shadow-2xl` is reserved for modals/dropdowns.

### Motion

- **Transitions:** `--transition-fast` 150ms · `--transition-base` 200ms ·
  `--transition-slow` 300ms, all `ease`. Hover states animate
  `background` / `color` / `border-color` — not layout.
- **Standard entrance:** opacity 0→1 + 8–16px rise, 300–450ms, staggered
  60ms per item on grid loads.
- **Dense data tables:** fade-only, no overshoot — bouncy easing reads as
  sloppy on informational UI.
- **Accessibility:** honor `prefers-reduced-motion` — render final state
  immediately; no autoplay.

---

## Component Specs (as implemented in `app.css`)

### Buttons

Base `.btn`: 8/16px padding, `--radius-md`, `--text-sm`, 500 weight,
transparent border by default; `--transition-fast` on color/background.
Sizes: `.btn-sm` (4/12px, `--text-xs`) · `.btn-lg` (12/24px, `--text-base`).

```css
.btn-primary { background: var(--c-neutral-900); color: var(--c-white); }
.btn-primary:hover { background: var(--c-neutral-800); }

.btn-secondary { background: var(--c-white); color: var(--c-neutral-700);
                border: 1px solid var(--border-color); }

.btn-danger  { background: var(--c-red-600); color: var(--c-white); }
.btn-success { background: var(--c-green-700); color: var(--c-white); }
.btn-warning { background: var(--c-amber-100); color: var(--c-amber-800);
              border: 1px solid var(--c-amber-200); }
.btn-info    { background: var(--c-blue-100); color: var(--c-blue-800); }
.btn-outline { background: transparent; color: var(--c-neutral-700);
              border: 1px solid var(--border-color); }
```

Focus is always visible: `.btn:focus-visible` gets the neutral focus ring
(box-shadow), never `outline: none` without a replacement.

### Cards

```css
.card {
    background: var(--c-white);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);          /* subtle by default */
}
/* .card-header / .card-body / .card-footer provide the inner structure */
.card-flat { box-shadow: none; }                                   /* opt-out */
.card-elevated { box-shadow: var(--shadow-md); }
.card-interactive:hover { box-shadow: var(--shadow-lg); transform: translateY(-1px); }
```

Stats use `.stat-card`. Cards in dense lists get tighter padding via the
`.p-4` / `.p-3` utilities rather than bespoke classes.

### Inputs

The app class is `.form-input` (not `.input`):

```css
.form-input {
    padding: var(--sp-2) var(--sp-3);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}
.form-input:focus {
    border-color: var(--c-neutral-900);
    box-shadow: 0 0 0 3px rgb(23 23 23 / 0.1);   /* neutral focus ring */
}
```

### Badges & Status

`.badge` variants map to status semantics used across order pages:
`badge-success` (green scale) = active/paid · `badge-info` (blue) =
partial · `badge-warning` (amber) = pending/suspended · `badge-danger`
(red) = cancelled/expired/failed · `badge-neutral` (gray) = refunded/
unknown. `.badge-dot` prepends a 5px status dot.

### Modals

```css
.modal-overlay { background: rgb(0 0 0 / 0.5); }   /* + blur where supported */
.modal {
    background: var(--c-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-2xl);
    width: 100%; max-width: 32rem; max-height: 90vh;
}
```

---

## Style Guidelines

**Style:** Minimal dashboard. 1px borders, subtle `--shadow-sm` on cards,
no gradients, no 3D, type-forward. Motion is functional (hover/entrance),
not decorative.

**Best for:** web + mobile SaaS dashboards, dense operational tables,
corporate tooling.

**Key effects:** color/opacity hover shifts, 150–200ms ease transitions,
minimal icon set (Lucide/Heroicons — never emoji).

### Page Patterns

- **Data-dense dashboard (default for this app):** summary stat cards →
  filter/toolbar row → dense table (12–14px, compact rows) → detail
  panels. One primary action per view.
- **Detail / share pages (customer-facing):** single centered card,
  max-width ~42rem, status-first facts, one clear affordance. See
  `pages/public-order.md`.

---

## Anti-Patterns (Do NOT Use)

- ❌ Excessive decoration, 3D effects, gradients, heavy shadows
  (`--shadow-lg`/`--shadow-2xl` on ordinary cards)
- ❌ External font or asset requests (CSP on the public surface;
  dependency-free portal layouts)
- ❌ **Emojis as icons** — use the SVG icon set
- ❌ **Missing `cursor: pointer`** on clickables
- ❌ **Layout-shifting hovers** — animate color/opacity, not size shifts
- ❌ **Low-contrast text** — 4.5:1 minimum in light mode (`--c-neutral-500`
  on white is the floor for secondary text)
- ❌ **Instant state changes** — always 150–300ms transitions
- ❌ **Invisible focus states** — focus ring must be visible (neutral ring)
- ❌ Hardcoded hex values where a `--c-*` / `--sp-*` token exists

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from a consistent SVG icon set
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150–300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Colors/spacing via `--c-*` / `--sp-*` tokens, not raw values
- [ ] No external font/asset requests (CSP-safe)
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No horizontal scroll on mobile
- [ ] If a token value changed, `MASTER.md` updated in the same commit
