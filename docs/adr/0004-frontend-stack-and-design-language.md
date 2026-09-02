# ADR-0004: Frontend Stack & Design Language

## Status
Accepted

## Context
ACL needs a distinctive, modern learning interface. Product direction:
visual language inspired by TryHackMe (dark, card-based course "rooms",
learning paths, clean sidebar) with Hack The Box-style UX reactions
(terminal aesthetics, neon-green feedback, micro-interactions, gamification).
Must remain maintainable, accessible and server-rendered per the brief.

## Decision
1. Stack: Laravel Blade + Tailwind CSS v4 (via @tailwindcss/vite) + Alpine.js.
   No SPA rewrite now; API-ready backend remains the source of truth.
2. Design tokens live in resources/css/app.css (@theme), not scattered hexes.
3. Dark-first theme: deep navy surfaces, red primary CTA (THM-inspired),
   terminal-green success/feedback (HTB-inspired), cyan hover glow.
4. Typography: Inter (UI) + JetBrains Mono (code, labels, terminal widgets).
5. Motion: 150-200ms ease-out transitions, press-scale on buttons,
   glow on focus, green flash on success. No motion without purpose.
6. Accessibility: WCAG AA contrast, visible focus rings, reduced-motion respected.
7. Original design only - inspired by THM/HTB, never copied assets/branding.

## Consequences
- All views extend layouts/app or layouts/auth and use token utilities only.
- Gamification reactions (XP, streaks, "course owned" moments) hook into
  the same token/motion system when Phase 2 arrives.
