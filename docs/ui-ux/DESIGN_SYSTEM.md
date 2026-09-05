# ACL Design System (v0.2)

## Language
- **Look:** warm, calm, Nigerian-green learning interface — card-based, generous
  whitespace, one primary action per screen.
- **Feel:** friendly and legible for students on any device. Terminal flourishes
  from v0.1 have been retired.
- **Theme:** ships light and dark together. Light is the default (a warm off-white
  canvas with a green interface); dark is a deep navy canvas with a brighter green.
  A **light / dark / system** toggle (`<x-theme-toggle />`) persists the choice in
  `localStorage['acl-theme']` and an inline `<head>` script
  (`partials/theme-script`) applies it before first paint, so there is no flash.

## Tokens (defined in resources/css/app.css)
Components reference **semantic tokens only** — never a raw hex. Each token is a
runtime CSS variable surfaced as a Tailwind utility through `@theme inline`, so
toggling `.dark` on `<html>` reskins the whole app with no rebuild.

| Token utility | Role | Light | Dark |
|---|---|---|---|
| `bg` | page background | `#fdf9e7` | `#0a0f1c` |
| `surface` | cards, sidebar, header | `#ffffff` | `#111a2e` |
| `raised` | inputs, hovered rows | `#f4f7e8` | `#17223a` |
| `border` | hairlines, dividers | `#e4e2c4` | `#263349` |
| `text` | primary text | `#14210a` | `#e8f0e3` |
| `muted` | secondary text | `#5a6a4a` | `#93a3b5` |
| `primary` / `primary-fg` | CTAs, active state / text on primary | `#15803d` / `#fff` | `#22c55e` / `#05210f` |
| `accent` | emphasis, headings accent | `#166534` | `#4ade80` |
| `ring` | focus ring | `#15803d` | `#22c55e` |
| `info` | links, secondary accent | `#0e7490` | `#22d3ee` |

Legacy v0.1 names (`abyss`, `edge`, `brand`, `terminal`, `glow`) are aliased to the
new tokens so nothing breaks mid-migration; do not use them in new markup.

## Type
- **Inter** — headings and body. **JetBrains Mono** — codes and numeric labels.
- Base 16px, body line-height ~1.5. Loaded from Google Fonts in each layout head.

## Layouts & components
- `layouts/public` — marketing shell (`<x-site-header />` + `<x-site-footer />`),
  used by the landing page.
- `layouts/app` — authenticated shell: sidebar, header with `<x-theme-toggle />`,
  footer.
- `layouts/auth` — centred single-card shell for sign-in.
- `x-site-header` / `x-site-footer` — public chrome; logo mark, nav, Login CTA.
- `x-theme-toggle` — accessible light/dark button (`aria-label`, `:aria-pressed`).
- Cards: `rounded-2xl border border-border bg-surface`; hover lifts the border to
  `border-primary/50`.

## Motion & reactions
- `.press` — subtle scale-down on `:active` for buttons and card CTAs.
- `.reveal` — scroll-in for landing sections via a vanilla IntersectionObserver
  that adds `.is-in`. **Progressive enhancement:** `.reveal` is only hidden when
  the inline boot script has added `.js` to `<html>`, so content is never hidden
  without JavaScript.
- `.flash-success` — brief green flash on lesson completion.
- `prefers-reduced-motion` disables `.press`, `.flash-success` and all `.reveal`
  motion.

## Rules
1. **No raw hex in Blade — semantic tokens only.**
2. Every interactive element needs visible hover, focus (`focus:ring-2
   focus:ring-ring`) and active states.
3. **Design light and dark together**; verify both. Never leave `text-white` or a
   fixed slate on a token background — it disappears in the other theme.
4. Foreground/background pairs meet WCAG AA (≥ 4.5:1 for body text).
5. One primary CTA per screen; SVG icons, never emoji.
