# ACL Design System (v0.1)

## Language
- Look: TryHackMe-inspired - dark, focused, card-based learning interface.
- Feel: Hack The Box-inspired - terminal flourishes, instant feedback,
  gamified reactions (XP, streaks, achievements) in later phases.

## Tokens (defined in resources/css/app.css)
| Token | Hex | Use |
|---|---|---|
| abyss | #0b1120 | page background |
| surface | #0f1a2e | cards, sidebar |
| raised | #16223c | inputs, hovered cards |
| edge | #1f2c4a | borders |
| brand | #ff2e4d | primary CTA, errors, brand accent |
| terminal | #9fef00 | success, active states, mono labels |
| glow | #22d3ee | hover glow, links, progress |

## Type
- Inter: headings, body. JetBrains Mono: codes, labels, terminal widgets.

## Components (build once, reuse)
- Room card (course card): code in mono green, title, CU + semester meta,
  progress bar, CTA.
- Stat card: mono label, big number, subtle glow.
- Badge: uppercase mono, 10px, tinted border.
- Terminal widget: mono block with `$` prompt lines for status/flavour.

## Motion & Reactions
- Buttons: .press (scale .97 on active).
- Focus: terminal-green ring.
- Success: green flash + toast (Alpine) - e.g. "COURSE UNLOCKED".
- Card hover: edge -> glow border + soft cyan shadow.
- Respect prefers-reduced-motion.

## Rules
1. No raw hex values in Blade files - tokens only.
2. Every interactive element needs hover + focus + active states.
3. Dark-first; light theme only if a future requirement demands it.
