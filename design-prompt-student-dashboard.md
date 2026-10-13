# ACL Student Dashboard — Design Specification

Prepared for graphics designer. Source: `resources/views/student/dashboard.blade.php`, `course-detail.blade.php`, AGENTS.md (light theme: yellowish bg / light green accent; no dominant blue).

## Brand / Palette

| Token | Hex / Style | Usage |
|---|---|---|
| `bg` / page bg | Light warm yellow-cream (#FDFAE8 or `#FDF8E8`) | Entire page background |
| `surface` | White / very light cream (#FFFBF0) | Card backgrounds |
| `primary` | Emerald green (#059669 / `#10B981`) | Buttons, active tabs, primary links, badges |
| `accent` / `terracotta` | Warm salmon / clay (#E07A5F / `#D97757`) | Hero gradient stop, decorative |
| `primary/5` | 5% emerald tint | Soft highlight cards |
| `primary/10` | 10% emerald tint | Badge backgrounds |
| `primary/40` | 40% emerald tint | Border accents |
| `raised` | Slight warm gray (#F3EFEA) | Secondary card bg, tab bars |
| `text` | Near-black (#1A1814) | All body/headings |
| `muted` | Warm gray (#7A756A) | Secondary text, metadata |
| `border` | Soft warm gray (#E6E2D6) | All card borders |
| `emerald-600` | Emerald | Verified status badge |
| `amber-400/500/600` | Amber / gold | Email verification banner (warning) |
| `white` | Pure white | Buttons on dark/primary |

**No dominant blue anywhere.** Light yellowish background is mandatory per AGENTS.md.

## Typography

- **Headings:** Extra-bold (`font-extrabold` / `text-3xl` → `text-5xl`), tracking-tightly (`tracking-tight`), leading-tight (`leading-tight`).
- **Body:** Normal weight, readable size (`text-base` / `text-sm`), warm gray (`text-muted`).
- **Labels / meta:** Tiny, uppercase, bold, wide tracking (`text-[10px]`, `uppercase`, `tracking-wider`).
- **Monospace:** Course codes (`font-mono`) in small caps.

## Layout Structure (Dashboard)

```
┌──────────────────────────────────────────────────────────────┐
│  Header (page-header component)                              │
│  Title: "Student Dashboard"  Subtitle: "Your academic progress" │
├──────────────────────────────────────────────────────────────┤
│  [Amber verification banner] — only if !email_verified       │
│  Rounded-2xl, gradient amber-100 → amber-50, thick amber-400 │
│  border, shadow-amber-200/50, flex row (icon + text + button)│
├──────────────────────────────────────────────────────────────┤
│  HERO (rounded-3xl, border-[3px] border-border, gradient)    │
│  from-primary/10 → via-emerald-50 → to-terracotta/10        │
│  Inset shadow, overflow-hidden, relative                     │
│  H1: "Anyone Can Learn. Your course. Your pace."             │
│  Subtitle paragraph                                          │
│  Buttons: "My Courses" (primary), "My Profile" (outline)     │
│  Decorative: logo SVG absolute right, rotate-12, opacity-10  │
├──────────────────────────────────────────────────────────────┤
│  STATS GRID (4 col → 2 col → 1 col)                         │
│  [Programme] [Level] [Enrolled] [Status]                    │
│  Each: rounded-2xl card, border, bg-surface, shadow-sm       │
│  Enrolled count: primary tint bg, emerald text               │
│  Status = "Verified" in emerald-600                          │
├──────────────────────────────────────────────────────────────┤
│  COURSES AT A GLANCE (rounded-3xl, p-6 sm:p-8)              │
│  Section heading + "Open My Courses →" link                  │
│  Two stat cards inside (National / Institutional curricula)  │
├──────────────────────────────────────────────────────────────┤
│  CONTINUE LEARNING (enrolled cards only)                     │
│  2–3 column grid, rounded-2xl cards with border             │
│  Badge: course code (rounded-full, border, bg-surface, 10px)  │
│  Title (bold), semester + units (small muted), Continue →    │
└──────────────────────────────────────────────────────────────┘
```

## Component Details

### Verification Banner (amber)
- **Shape:** `rounded-2xl`, `border-2 border-amber-400`
- **Bg:** `bg-gradient-to-r from-amber-100 to-amber-50`
- **Shadow:** `shadow-lg shadow-amber-200/50`
- **Icon:** `h-10 w-10 rounded-full bg-amber-500 text-white` with `⚠` glyph
- **Text:** `text-amber-900` (title), `text-amber-800` (body)
- **Button:** `rounded-xl bg-amber-600 text-white` with SVG resen icon
- **Only shows** when `! $user->email_verified_at`

### Hero Section
- **Shape:** `rounded-3xl`, `border-[3px]`, `bg-gradient-to-br`
- **Content:** Left-aligned max-w-2xl; decorative logo SVG absolute right (hidden on mobile `hidden lg:block`)
- **Title:** `text-3xl sm:text-5xl font-extrabold tracking-tight text-text leading-tight`
- **Subtitle:** `text-base sm:text-lg text-muted leading-relaxed`
- **CTA buttons:** `rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-fg shadow transition hover:bg-accent`
- **Secondary:** `rounded-xl border border-border bg-surface text-text hover:border-primary`
- **Badge on primary button:** `rounded-full bg-white/25 px-2 py-0.5 text-xs` showing course count

### Stats Cards
- **Grid:** `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`
- **Card:** `rounded-2xl border border-border bg-surface p-6 shadow-sm`
- **Label:** `text-xs font-bold uppercase tracking-wider text-muted`
- **Value:** `text-2xl font-extrabold text-text` (level uses `text-lg` for programme)
- **Enrolled card:** `bg-primary/5` + `border-primary/40` for emphasis
- **Status:** `text-2xl font-extrabold text-emerald-600`

### Course Cards (Continue Learning)
- **Container:** `rounded-2xl border border-border bg-bg p-5 shadow-sm`
- **Hover:** `hover:border-primary hover:shadow-md transition`
- **Header:** Title (`font-bold text-text`) + code badge (`rounded-full border border-border bg-surface px-2 py-0.5 text-[10px] font-semibold text-muted`)
- **Meta:** `text-sm text-muted` — semester + credit units
- **CTA:** `text-sm font-semibold text-primary` with arrow (`→`)
- **Enrolled tag:** `inline-flex rounded-full bg-emerald-100 px-2 py-1 text-sm font-semibold text-emerald-700`

### Course Detail Page (additional for designer)

**Header bar:** Course card with credits badge (`bg-primary/10 text-primary text-[10px]`), title (`text-2xl sm:text-3xl`), code (`font-mono`), enrollment tag (`bg-emerald-100 text-emerald-700`), semester/session tags (`bg-raised`), Enroll button (`bg-primary text-primary-fg rounded-full`).

**Tabs:** Rounded pill bar (`rounded-xl bg-surface p-1 shadow-sm border`) with three tabs: Outline / Chapters / Outcomes. Active = `bg-primary text-primary-fg shadow-sm`, inactive = `text-text hover:bg-raised`.

**Chapter cards:** Each has position number (`h-8 w-8 rounded-lg bg-primary/10 text-primary font-bold`), title, slug preview, optional 90-char intro preview + "Read full explanation" button, Start button (`bg-primary text-white rounded-lg`), Quiz button (`bg-raised border-border text-text`), progress indicator (`Chapter N of M`).

**Study panel:** Hidden div, rounded-xl, `bg-raised`, with prev/next navigation, loading indicator, close button.

## Interactions / Behaviors

- **Studying:** Click "Start" → panel opens with loading spinner → fetches `student.chapter.content` → renders introduction/summary/key_takeaways as paragraphs. Fallback if empty content: title + "Full explanation is being developed."
- **Tab switching:** JS `showContentTab()` adds/removes `hidden` and toggles `bg-primary`/`text-text` classes.
- **Chapter load:** Uses `fetch()` to endpoint; caches result in DOM (`dataset.loaded`) so re-opening is instant.
- **Enrollment:** If not enrolled, shows "Enroll" button; if enrolled shows "Enrolled" tag + quiz + continue buttons.
- **Email banner:** Only appears if unverified; disappears after verification.

## Responsive

- Mobile-first: `sm:` and `lg:` breakpoints used throughout
- Grid collapses: 4-col stats → 2-col → 1-col
- Course cards: 2-col (`lg:grid-cols-2`) → 1-col on mobile
- Hero decorative logo hidden below `lg`
- All cards use `rounded-2xl` or `rounded-3xl` for consistent soft corners

## Assets / Icons

- Logo: `images/logo.svg` (decorative, rotated, low opacity)
- Icons: inline SVGs for book, clock, check, arrows, resen warning, enrollment, quiz, closing
- All icons are stroke-based (`stroke-width="2"`, no fill except badges)

## Design rules to enforce

1. Light warm yellow-cream background (`bg`) everywhere — never pure white page bg
2. Emerald `primary` for actions / active states — never blue
3. Terracotta/salmon gradient only in hero decorative stop
4. All cards have `rounded-2xl` + `border-border` + subtle shadow
5. Typography: extra-bold headings, tight tracking, warm gray body
6. Amber ONLY for verification/reminder banners
7. Emerald ONLY for confirmed/verified status
8. Study panel must work even when chapter `introduction`/`summary`/`key_takeaways` are all empty (controller fix delivered)

Ready for design execution.
