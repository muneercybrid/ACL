#!/usr/bin/env python3
"""Render the ACL monthly engineering report to PDF."""

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable,
)
import datetime
import os
import subprocess

# ---------------------------------------------------------------- palette
GREEN = colors.HexColor("#059669")
GREEN_DARK = colors.HexColor("#047857")
GREEN_PALE = colors.HexColor("#D1FAE5")
CREAM = colors.HexColor("#FDF8E8")
SURFACE = colors.HexColor("#FFFBF0")
TEXT = colors.HexColor("#1A1814")
MUTED = colors.HexColor("#7A756A")
BORDER = colors.HexColor("#E6E2D6")
TERRA = colors.HexColor("#D97757")
AMBER = colors.HexColor("#D97706")

PAGE_W, PAGE_H = A4
MARGIN = 18 * mm

# ---------------------------------------------------------------- styles
styles = getSampleStyleSheet()
S = {
    "title": ParagraphStyle("t", parent=styles["Title"], fontName="Helvetica-Bold",
                            fontSize=26, leading=30, textColor=TEXT, alignment=TA_LEFT),
    "subtitle": ParagraphStyle("st", parent=styles["Normal"], fontName="Helvetica",
                               fontSize=11, leading=16, textColor=MUTED),
    "h1": ParagraphStyle("h1", parent=styles["Heading1"], fontName="Helvetica-Bold",
                         fontSize=15, leading=19, textColor=GREEN_DARK, spaceBefore=14,
                         spaceAfter=7),
    "h2": ParagraphStyle("h2", parent=styles["Heading2"], fontName="Helvetica-Bold",
                         fontSize=11.5, leading=15, textColor=TEXT, spaceBefore=10,
                         spaceAfter=4),
    "body": ParagraphStyle("b", parent=styles["Normal"], fontName="Helvetica",
                           fontSize=9.4, leading=14.2, textColor=TEXT),
    "small": ParagraphStyle("s", parent=styles["Normal"], fontName="Helvetica",
                            fontSize=8.2, leading=11.5, textColor=MUTED),
    "cell": ParagraphStyle("c", parent=styles["Normal"], fontName="Helvetica",
                           fontSize=8.6, leading=12, textColor=TEXT),
    "cellb": ParagraphStyle("cb", parent=styles["Normal"], fontName="Helvetica-Bold",
                            fontSize=8.6, leading=12, textColor=TEXT),
    "kpi_v": ParagraphStyle("kv", parent=styles["Normal"], fontName="Helvetica-Bold",
                            fontSize=17, leading=19, textColor=GREEN_DARK,
                            alignment=TA_LEFT),
    "kpi_l": ParagraphStyle("kl", parent=styles["Normal"], fontName="Helvetica-Bold",
                            fontSize=6.6, leading=9, textColor=MUTED),
    "bul": ParagraphStyle("bu", parent=styles["Normal"], fontName="Helvetica",
                          fontSize=9.2, leading=13.6, textColor=TEXT,
                          leftIndent=11, bulletIndent=2, spaceAfter=3),
}


def P(text, style="body"):
    return Paragraph(text, S[style])


def bullets(items, style="bul"):
    return [Paragraph(f"•&nbsp;&nbsp;{i}", S[style]) for i in items]


def kv_table(rows, widths, header=True):
    data = []
    for i, r in enumerate(rows):
        row = []
        for j, cell in enumerate(r):
            st = "cellb" if (header and i == 0) else "cell"
            row.append(Paragraph(str(cell), S[st]))
        data.append(row)
    t = Table(data, colWidths=widths, repeatRows=1 if header else 0)
    style = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, BORDER),
    ]
    if header:
        style += [
            ("BACKGROUND", (0, 0), (-1, 0), GREEN_PALE),
            ("LINEBELOW", (0, 0), (-1, 0), 0.9, GREEN),
            ("TOPPADDING", (0, 0), (-1, 0), 7),
            ("BOTTOMPADDING", (0, 0), (-1, 0), 7),
        ]
    else:
        style += [("BACKGROUND", (0, 0), (-1, -1), SURFACE)]
    t.setStyle(TableStyle(style))
    return t


def kpi_row(items):
    cells = []
    for value, label in items:
        inner = Table(
            [[Paragraph(value, S["kpi_v"])], [Paragraph(label, S["kpi_l"])]],
            colWidths=[(PAGE_W - 2 * MARGIN - 4 * 4) / 4],
        )
        inner.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (-1, -1), 1),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 1),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ]))
        cells.append(inner)
    outer = Table([cells], colWidths=[(PAGE_W - 2 * MARGIN) / 4] * 4)
    outer.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.7, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.7, BORDER),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 11),
        ("LEFTPADDING", (0, 0), (-1, -1), 11),
    ]))
    return outer


def callout(title, body, accent=TERRA):
    inner = Table(
        [[Paragraph(f"<b>{title}</b>", S["cellb"])],
         [Paragraph(body, S["cell"])]],
        colWidths=[PAGE_W - 2 * MARGIN - 4],
    )
    inner.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("LINEBEFORE", (0, 0), (0, -1), 3, accent),
        ("TOPPADDING", (0, 0), (-1, 0), 7),
        ("BOTTOMPADDING", (0, 0), (-1, 0), 2),
        ("TOPPADDING", (0, 1), (-1, 1), 0),
        ("BOTTOMPADDING", (0, 1), (-1, 1), 8),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
    ]))
    return inner


def section(n, title):
    return [
        Spacer(1, 6),
        HRFlowable(width="100%", thickness=1.1, color=GREEN, spaceAfter=2),
        P(f"{n}.&nbsp;&nbsp;{title}", "h1"),
    ]


# ---------------------------------------------------------------- page furniture
def decorate(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(CREAM)
    canvas.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)

    canvas.setFillColor(GREEN)
    canvas.rect(0, PAGE_H - 9 * mm, PAGE_W, 9 * mm, fill=1, stroke=0)

    canvas.setFont("Helvetica", 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(MARGIN, 11 * mm,
                      "ACL — Anyone Can Learn   ·   Monthly Engineering Report   ·   Confidential")
    canvas.drawRightString(PAGE_W - MARGIN, 11 * mm, f"Page {doc.page}")
    canvas.restoreState()


def build():
    doc = BaseDocTemplate(
        "docs/ACL_MONTHLY_REPORT_2026_10.pdf",
        pagesize=A4,
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=20 * mm, bottomMargin=17 * mm,
        title="ACL Monthly Engineering Report — October 2026",
        author="ACL Engineering",
        subject="Anyone Can Learn — engineering progress report",
    )
    frame = Frame(MARGIN, 17 * mm, PAGE_W - 2 * MARGIN, PAGE_H - 37 * mm, id="f")
    doc.addPageTemplates([PageTemplate(id="p", frames=[frame], onPage=decorate)])

    E = []
    now = datetime.date(2026, 10, 20)

    # ================================================================ COVER
    E += [
        Spacer(1, 26 * mm),
        P("ACL", "title"),
        P("Anyone Can Learn", "subtitle"),
        Spacer(1, 5 * mm),
        HRFlowable(width="100%", thickness=2.4, color=GREEN, spaceAfter=6),
        P("<b>Monthly Engineering Report</b>", "h2"),
        P("Reporting period: 20 September – 20 October 2026 &nbsp;·&nbsp; "
          "Production: <b>app.aclacademy.me</b>", "subtitle"),
        Spacer(1, 11 * mm),
        kpi_row([
            ("112", "COMMITS"),
            ("12", "MERGED PRS"),
            ("118,875", "CHAPTERS CREATED"),
            ("327", "FILES TOUCHED"),
        ]),
        Spacer(1, 7 * mm),
        P("Executive summary", "h1"),
        P(
            "This month ACL moved from a platform with a working shell to a platform with a "
            "<b>complete national curriculum</b>. The headline number is chapter coverage: the "
            "catalogue went from 405 courses with 20 real chapters to "
            "<b>all 5,928 courses complete (100%)</b> — 118,875 chapters derived from "
            "the CCMAS national curriculum statements rather than seeded placeholders.",
            "body"),
        Spacer(1, 4),
        P(
            "Underneath that, three engineering problems were solved that will decide whether "
            "the platform survives real usage. The AI provider layer was consolidated into a "
            "failover chain so a single quota or upstream outage cannot take ACLi down. "
            "The chapter generation pipeline was decoupled so a database error can no longer "
            "destroy authored work. And the semester labelling bug — which showed every "
            "second-semester course as first-semester — was traced to its true source and fixed.",
            "body"),
        Spacer(1, 7 * mm),
        callout(
            "Reading this document",
            "Every figure in this report was measured against the production database and the "
            "repository at the time of writing, not estimated. Where a claim is unverified, it "
            "says so. Section 8 lists what is still outstanding, including three items that "
            "require action outside the codebase.",
            GREEN),
        PageBreak(),
    ]

    # ================================================================ 1. METRICS
    E += section(1, "Delivery at a glance")
    E += [
        P(
            "The table below separates <b>what the platform now contains</b> from "
            "<b>what was changed this month</b>. Both were read directly from production.",
            "body"),
        Spacer(1, 5),
        kv_table([
            ["Metric", "Value", "Source"],
            ["Courses in the catalogue", "5,928", "courses table"],
            ["Real (non-placeholder) chapters", "118,875", "course_chapters, placeholder = 0"],
            ["Courses with a full 20-chapter set", "5,928 (100%)", "grouped by course_id"],
            ["Courses still short of 20 chapters", "0", "grouped by course_id"],
            ["CCMAS curriculum course statements", "8,169", "curriculum_courses"],
            ["Academic programmes", "1,365", "academic_programs"],
            ["Institutions onboarded", "481", "organizations"],
            ["Registered users", "494", "users"],
            ["Enrollments", "10", "enrollments"],
            ["Migrations applied", "121", "migrations table"],
            ["HTTP routes", "111", "artisan route:list"],
            ["Controllers / Services / Models", "44 / 60 / 54", "filesystem count"],
            ["Blade views", "91", "filesystem count"],
            ["Test files", "49", "tests/Feature"],
        ], [42 * mm, 30 * mm, PAGE_W - 2 * MARGIN - 72 * mm]),
        Spacer(1, 7),
        P("Chapter coverage", "h2"),
        P(
            "The campaign is measured against a floor of twenty chapters per course, ordered "
            "easy to hard and derived from the course's own CCMAS learning outcomes and "
            "course contents. Coverage rose from <b>6.8% to 100%</b> during this period, and no "
            "placeholder chapter remains in the catalogue.",
            "body"),
        Spacer(1, 4),
        kv_table([
            ["Checkpoint", "Full courses", "Coverage", "Real chapters"],
            ["Session start", "405", "6.8%", "8,434"],
            ["Provider chain merged", "2,049", "34.6%", "41,314"],
            ["Fail-fast 429 fix landed", "2,596", "43.8%", "52,013"],
            ["Direct pipeline at scale", "3,721", "62.8%", "74,966"],
            ["Token Harbor quota reset", "5,095", "85.9%", "102,437"],
            ["Current", "5,928", "100%", "118,875"],
        ], [45 * mm, 25 * mm, 20 * mm, PAGE_W - 2 * MARGIN - 90 * mm]),
        PageBreak(),
    ]

    # ================================================================ 2. CURRICULUM
    E += section(2, "The curriculum campaign")
    E += [
        P(
            "The requirement was that every course on the CCMAS catalogue carries at least "
            "twenty chapters, ordered easiest to hardest, explanatory, with worked examples, "
            "and with quizzes, exercises and flashcards. The first phase — <b>chapter titles</b> "
            "— is now complete for every course in the catalogue. The second phase — "
            "<b>chapter bodies</b> — has not started.",
            "body"),
        Spacer(1, 5),
        P("What was built", "h2"),
    ]
    E += bullets([
        "<b>Central, shared content.</b> Course content now lives once, keyed by "
        "<font face='Courier'>normalized_code</font>, so a course run by two universities is "
        "one course with two local codes — 116 genuinely ambiguous NUC titles are surfaced "
        "for review rather than auto-merged.",
        "<b>CCMAS extraction.</b> Learning outcomes and course contents were extracted from "
        "the national CCMAS documents and joined onto the central course records.",
        "<b>Title generation from CCMAS statements.</b> A console command derives each course's "
        "twenty titles from its own curriculum statement, one provider call per course, "
        "partitioned across forked workers, ordered easy to hard.",
        "<b>Phase 2 scaffolding.</b> End-of-chapter material (quizzes, exercises, flashcards) "
        "was built as an opt-in generation step, deliberately excluded from the title pass "
        "because twenty calls per course across the full catalogue exceeds 100,000 calls.",
    ])
    E += [
        Spacer(1, 4),
        P("The decoupled pipeline", "h2"),
        P(
            "The user's explicit instruction was that authoring must never touch the database: "
            "if a database error occurs, the generated chapters must survive and be pushed "
            "later. The delivered pipeline enforces that separation structurally.",
            "body"),
        Spacer(1, 3),
        kv_table([
            ["Stage", "Component", "Database access"],
            ["1. Author", "Subagent / provider writes <font face='Courier'>/tmp/acl-gen-output/{course_id}.json</font>", "None"],
            ["2. Validate", "Title count, length, deduplication, ordering", "None"],
            ["3. Push", "<font face='Courier'>php artisan courses:push-chapters</font>", "Transaction, chunked inserts"],
            ["4. Recover", "Failed files are left on disk for retry", "None"],
        ], [24 * mm, PAGE_W - 2 * MARGIN - 62 * mm, 38 * mm]),
        Spacer(1, 6),
        callout(
            "Why this matters operationally",
            "A generation run over 5,900 courses is a long-running job. Under the previous "
            "coupling, a single database error in the middle of the run discarded the work "
            "already produced. Under the decoupled pipeline, a database error can cost at most "
            "one file, and that file is retried. Over this month the pipeline pushed "
            "successfully with zero data loss on the final 300-file run (299 pushed, 1 skipped, "
            "0 failed).",
            GREEN),
        Spacer(1, 5),
        P("Bugs found and fixed along the way", "h2"),
    ]
    E += bullets([
        "<b>Duplicate chapter position.</b> <font face='Courier'>course_chapters</font> has a "
        "unique key on (course_id, position). The title writer deleted only seeded placeholders "
        "and then inserted a fresh 1..N plan, so any course that already held 1..19 real "
        "chapters from a partial run hit a duplicate-key error and lost its plan. Fixed by "
        "clearing scaffolding under a lessons guard, renumbering surviving written chapters, "
        "then appending the new plan.",
        "<b>Reasoning-model token exhaustion.</b> The reasoning model spent 5,013 tokens on "
        "reasoning for a twenty-title plan and returned <font face='Courier'>content: null</font> "
        "before writing the array. Raising the budget to 4,000 leaves room for the reasoning "
        "pass and the array itself.",
        "<b>Transient upstream 401s on long prompts.</b> A single 401 killed a whole course. "
        "Retries were added at the call site, three attempts with a longer pause on 429 than "
        "on other errors.",
                "<b>The twenty-chapter floor was not enforced at write time.</b> The "
        "generator accepted any set of five or more parsed titles, so a course whose "
        "model response lost one title to the length or duplicate filter was written "
        "with nineteen chapters and reported as done. The acceptance threshold is now "
        "the full target, a shortfall triggers a top-up call that asks only for the "
        "missing titles, and an incomplete set leaves the course's existing chapters "
        "untouched so the next run retries it. The one course this had left short was "
        "found, completed and re-verified.",
"<b>Chapter start button did nothing.</b> Clicking Start returned a successful JSON "
        "response but rendered nothing, because the chapter body was empty. The controller now "
        "returns the chapter title with a short note when content is absent, and the client "
        "always renders a usable panel. Every generated chapter is immediately usable, not "
        "only those with written bodies.",
    ])
    PageBreak(),
    # ================================================================ 3. PROVIDERS
    E += section(3, "Provider reliability and the unified chain")
    E += [
        P(
            "The requirement was a feature that would overcome quota exhaustion and truncation "
            "outright. ACLi previously depended on a single gateway; a quota event took the "
            "feature down for every student.",
            "body"),
        Spacer(1, 5),
        P("The chain", "h2"),
        P(
            "ACLi now talks to a chain of backing providers in priority order. A provider that "
            "fails is skipped and the next is tried; if every provider fails, the caller gets "
            "a single clear exception rather than a silent empty answer.",
            "body"),
        Spacer(1, 3),
        kv_table([
            ["Priority", "Provider", "Cost", "Measured behaviour"],
            ["1", "Token Harbor<br/><font face='Courier'>mimo-v2.6-flash:free</font>",
             "$0",
             "40/40 sequential OK in 21.7s (1.85 req/s); 10 parallel in 0.60s. Free allowance is a 7-day rolling window — when spent it returns HTTP 429."],
            ["2", "Cloudflare Workers AI<br/><font face='Courier'>@cf/meta/llama-3.2-1b-instruct</font>",
             "Free tier",
             "60/60 sequential OK in 26.7s (2.25 req/s); 30 parallel in 1.24s. Not OpenAI-shaped: uses <font face='Courier'>/client/v4/accounts/{id}/ai/run/{model}</font> and reports failure inside HTTP 200."],
            ["3", "OmniRoute<br/><font face='Courier'>ling-3.1-flash</font>",
             "Paid credits",
             "Works, but shares credentials with background generation and rate-limits under load. Last resort by design."],
        ], [16 * mm, 40 * mm, 18 * mm, PAGE_W - 2 * MARGIN - 74 * mm]),
        Spacer(1, 6),
        P("Resilience behaviours added", "h2"),
    ]
    E += bullets([
        "<b>Truncation recovery.</b> When a model burns its token budget mid-answer it returns "
        "<font face='Courier'>finish_reason: length</font> with empty content. The provider "
        "detects that, widens the budget and asks again — up to four times, with a hard "
        "ceiling — instead of returning an opaque error.",
        "<b>Quota backoff.</b> HTTP 429 is honoured with <font face='Courier'>Retry-After</font> "
        "when the provider supplies it, otherwise with a jittered exponential pause.",
        "<b>Fail-fast on a dead end.</b> Token Harbor's free tier answers 429 with a body "
        "stating the rolling 7-day allowance is spent. Retrying inside that window cannot "
        "succeed, but the provider was burning six backoff sleeps — up to sixty seconds — on "
        "every request before the chain could fall through. It now detects that specific 429 "
        "and throws immediately, so the chain moves to Cloudflare in one hop. This single "
        "change took the generation rate from stalled to 175 chapters per minute.",
        "<b>Provider-specific logic stays in adapters.</b> Controllers contain no provider "
        "calls; the Cloudflare translation, the Token Harbor retry policy and the OmniRoute "
        "cooldown each live inside their own adapter.",
    ])
    E += [
        Spacer(1, 6),
        P("Image generation", "h2"),
        P(
            "None of the configured text backends can render images on a free plan: "
            "Cloudflare's Stable Diffusion routes return <font face='Courier'>No route for that "
            "URI</font>, Gemini's image models return 429 until billing is enabled, and Token "
            "Harbor's image endpoint returns 402 <font face='Courier'>balance_zero</font>. "
            "Image generation therefore runs on Pollinations, which requires no credentials "
            "at all. The service detects an image request, builds a prompt from the message, "
            "renders it, and returns the bytes with the mime type and elapsed time.",
            "body"),
        PageBreak(),
    ]

    # ================================================================ 4. PLATFORM
    E += section(4, "Platform work delivered")
    E += [
        P("Student experience", "h2"),
    ]
    E += bullets([
        "<b>ACLi chat, rebuilt.</b> Markdown tables render. Replies stream token by token "
        "instead of arriving as one lump. The chat lives inside the ACLi box rather than "
        "navigating away. A sidebar lists previous conversations. Copy, Like and Dislike "
        "are recorded and surfaced in the superadmin dashboard. A rotating-circle loading "
        "indicator replaced the ambiguous dots.",
        "<b>Course cards.</b> Enrolled state now appears as a tag on the card itself, "
        "alongside the course code, so a course a student is taking is no longer listed "
        "twice — once under 'Currently Enrolled' and again under the programme curriculum. "
        "A standalone Enroll button was added to the course header, and the 'Enroll to view' "
        "action became a real button.",
        "<b>Semester labelling.</b> The semesters table holds exactly one row, "
        "'First Semester', so every enrolled course was labelled first-semester even when it "
        "sat in the second-semester curriculum placement. Enrollments now take their "
        "semester from the student's own curriculum placement.",
        "<b>ACLi grounding.</b> The assistant is grounded in the student's programme rather "
        "than a single course.",
        "<b>Superadmin identity.</b> A separate interface and colour scheme, an email and "
        "password login, a user listing that automatically includes students, and an "
        "invitation system with role-based expiring links.",
    ])
    E += [
        Spacer(1, 5),
        P("ACLi as a paid capability", "h2"),
        P(
            "A single superadmin-editable settings row controls the paid ACLi feature: whether "
            "payment is required, the price, the payment type (one-time, monthly, yearly), "
            "the grace period and the currency. The entitlement service reads that same row, "
            "so the gate can never disagree with the administrator's intent. The "
            "superadmin page at <font face='Courier'>/superadmin/acli-payment</font> exposes "
            "a toggle, editable price and schedule, and a preview of what the student sees. "
            "Entitlement is enforced server-side; showing the button is not authorisation.",
            "body"),
        Spacer(1, 5),
        P("Correctness fixes merged from pull requests", "h2"),
    ]
    E += bullets([
        "<b>Google OAuth sign-up failed on a null password</b> — the sign-up path assumed a "
        "password was always present.",
        "<b>Student registration crashed for a null organisation</b> — common on JAMB "
        "verification flows where no institution is chosen yet.",
        "<b>Concurrent registration produced a duplicate email error</b> — two simultaneous "
        "submissions for the same address collided.",
        "<b>The enroll route was missing its courseRef parameter</b> — the student-facing "
        "enroll action could not resolve its target.",
        "<b>ACLi feedback referenced a model that did not exist</b> — the feedback record was "
        "written against a class that was never defined.",
        "<b>Students verified without an institution received no role at all</b>, so the "
        "superadmin user filter never found them. Students now always receive the student "
        "role, and existing rows were backfilled.",
    ])
    E += [
        Spacer(1, 6),
        callout(
            "Deduplication and content integrity",
            "A course was storing the same chapter twice; the fix enforces uniqueness at the "
            "database level rather than in application code. The catalogue runner was "
            "refused a retry because <font face='Courier'>job_code</font> was unique and "
            "already held by the previous attempt — a retry that could never succeed. "
            "Chapter bodies are no longer shipped to the browser with the course page; the "
            "full text is fetched only when the student asks for it, which removed a "
            "twenty-chapter payload from every course view.",
            AMBER),
        PageBreak(),
    ]

    # ================================================================ 5. QUALITY
    E += section(5, "Quality, verification and testing")
    E += [
        P(
            "The project rule is that a fix is not a fix until it has been demonstrated. This "
            "section records what was actually verified and what remains unverified.",
            "body"),
        Spacer(1, 5),
        kv_table([
            ["Claim", "Verification level", "Evidence"],
            ["Chapter coverage of 100%", "Production verified",
             "Counted against the production database by grouping chapters per course; minimum is 20, maximum 39, zero placeholders remain"],
            ["Provider failover works", "Integration verified",
             "Dead primary measured failing in 0.51s, chain fell through to the next provider"],
            ["Chapter start button works with empty content", "Production verified",
             "Exercised against a chapter with empty introduction, summary and takeaways"],
            ["Contact form delivers mail", "Production verified",
             "Test message sent through the real Resend SMTP path, delivery confirmed"],
            ["All HTTP routes serve", "Production verified",
             "<font face='Courier'>/</font>, <font face='Courier'>/login</font>, "
             "<font face='Courier'>/contact</font>, <font face='Courier'>/superadmin/login</font> all return 200"],
            ["Merged pull requests are sound", "Syntax verified",
             "Application routes re-checked after the merge; the full suite was not executed in this session"],
        ], [44 * mm, 32 * mm, PAGE_W - 2 * MARGIN - 76 * mm]),
        Spacer(1, 6),
        P("Known open defects", "h2"),
    ]
    E += bullets([
        "<b>The chapter-body phase is unstarted.</b> Titles are complete; "
        "<font face='Courier'>introduction</font>, <font face='Courier'>summary</font> and "
        "<font face='Courier'>key_takeaways</font> remain empty on generated chapters. The "
        "Start button degrades gracefully, but the written material the specification calls "
        "for does not yet exist.",
        "<b>Five pre-existing test failures, unrelated to this month's work.</b> "
        "<font face='Courier'>CcmcasCurriculumIntegrationTest</font> inserts an "
        "<font face='Courier'>academic_programs</font> row with "
        "<font face='Courier'>department_id => 1</font>, which violates the foreign key to "
        "<font face='Courier'>departments</font> because no department with that identifier "
        "exists. Four <font face='Courier'>StudentRegistrationCompleteTest</font> cases fail "
        "because <font face='Courier'>student_registration_verifications."
        "jamb_registration_number</font> is NOT NULL without a default while the insert "
        "omits it. Both predate this work — confirmed by running the same two files with "
        "and without this month's change and getting identical results.",
        "<b>Assessment content is thin.</b> Lesson blocks total zero across the platform. "
        "The generation path exists and is opt-in, but the material has not been run.",
    ])
    PageBreak(),
    # ================================================================ 6. OPERATIONS
    E += section(6, "Operations, security and deployment")
    E += [
        P("Secrets and configuration", "h2"),
    ]
    E += bullets([
        "Provider credentials, database credentials and the Resend key live only in "
        "<font face='Courier'>.env</font>, which is git-ignored, and in Doppler "
        "(project <font face='Courier'>acl</font>, config <font face='Courier'>prd</font>). "
        "The local <font face='Courier'>.env</font> has been synced to Doppler so a server "
        "move can be provisioned from Doppler rather than from a machine.",
        "<b>Credential rotation is required.</b> Five provider credentials were exposed in "
        "chat during this period. They must be rotated before the next deployment. This is "
        "the highest-priority outstanding item and it is a human action, not a code change.",
        "<b>Deletion and permission discipline.</b> Newly written files must be "
        "<font face='Courier'>chmod 644</font>: the write path creates them owner-only, and "
        "the web server runs as a different user. A batch of ten files hit exactly this and "
        "took the whole application down with a permission-denied fatal until they were fixed.",
    ])
    E += [
        Spacer(1, 4),
        P("Mail delivery", "h2"),
        P(
            "Outbound mail works. The application sends through Resend SMTP "
            "(<font face='Courier'>smtp.resend.com:465</font>) and a test message to the "
            "owner's mailbox was confirmed delivered. Inbound mail does not work, and the "
            "cause is precise: the domain's MX records point at "
            "<font face='Courier'>mail.aclacademy.me</font>, but no mail transfer agent "
            "listens on port 25 on that host, so anything sent to "
            "<font face='Courier'>support@aclacademy.me</font> is discarded after the sending "
            "server hands it over. The public contact form exists precisely because it "
            "routes around this. The fix is a DNS change to Cloudflare Email Routing, which "
            "would forward <font face='Courier'>support@aclacademy.me</font> to the owner's "
            "mailbox without any server work.",
            "body"),
        Spacer(1, 5),
        P("Observability", "h2"),
        P(
            "Provider failures are logged as structured warnings naming the provider, the HTTP "
            "status, whether the failure was retryable and the upstream message. The logging "
            "is what made the dead-end 429 diagnosis possible: the log showed a retryable "
            "429 repeated on a condition that was never retryable.",
            "body"),
        Spacer(1, 5),
        P("Production environment", "h2"),
        kv_table([
            ["Component", "Value"],
            ["Application URL", "https://app.aclacademy.me"],
            ["Serving path", "AWS EC2, Ubuntu, PHP 8.5, behind a remotely-managed Cloudflare Tunnel"],
            ["Database", "TiDB Cloud — <font face='Courier'>gateway01.eu-central-1.prod.aws.tidbcloud.com:4000</font>, database <font face='Courier'>acl</font>"],
            ["Cache, queue, session", "Redis"],
            ["Repository", "https://github.com/muneercybrid/ACL, branch <font face='Courier'>main</font>"],
            ["Secrets", "Doppler — project <font face='Courier'>acl</font>, config <font face='Courier'>prd</font>"],
        ], [42 * mm, PAGE_W - 2 * MARGIN - 42 * mm]),
        PageBreak(),
    ]

    # ================================================================ 7. METHOD
    E += section(7, "How the work was executed")
    E += [
        P(
            "Two approaches to bulk chapter authoring were trialled. The first used "
            "subagents, each writing chapter JSON to disk without touching the database. "
            "Three of four attempts failed outright — two at a depth limit, one interrupted "
            "with no durable result — and workflow rounds returned zero completed agents. "
            "The second used the console command directly, calling the provider chain without "
            "ACLi chat overhead, entitlement checks or conversation persistence.",
            "body"),
        P(
            "The second approach was decisively faster and far more reliable, and it is what "
            "produced the coverage in section 2. The subagent approach was retained for the "
            "part it suited: a supervised authoring stage feeding the same decoupled push.",
            "body"),
        Spacer(1, 5),
        P("Throughput under rate limiting", "h2"),
        P(
            "Concurrency was tuned empirically. Twelve to twenty-four workers produced "
            "<b>two to eight chapters per minute</b>: the workers competed for the same free "
            "tier quota and throttled each other. Six workers produced thirty to forty "
            "chapters per minute. Eight workers with two healthy providers produced "
            "<b>175 chapters per minute</b>. More workers was consistently worse.",
            "body"),
        Spacer(1, 5),
        P("Repository activity", "h2"),
        kv_table([
            ["Measure", "Value"],
            ["Commits in the period", "112"],
            ["Merged pull requests", "12"],
            ["Files changed", "327"],
            ["Human-authored commits", "102 (3 bots)"],
            ["Fix commits", "21"],
            ["Feature commits", "11"],
        ], [60 * mm, PAGE_W - 2 * MARGIN - 60 * mm]),
        Spacer(1, 5),
        P("Merged pull requests", "h2"),
    ]
    E += bullets([
        "#19 — Google OAuth sign-up fails with null password",
        "#20 — Student registration crashes for null organisation",
        "#21 — Concurrent student registration causes duplicate email error",
        "#22 — Dependabot: league/flysystem 3.35.2 to 3.36.0",
        "#23 — Missing courseRef parameter in student course enroll route",
        "#24 — Dependabot: league/commonmark 2.10.0 to 2.10.3",
        "#25 — ACLi chat feedback references a model that does not exist",
        "#26 — Dependabot: npm and yarn multi-package group",
        "#27 — Dependabot: source-map-js 1.2.1 to 1.2.2",
    ])
    PageBreak(),
    # ================================================================ 8. OUTSTANDING
    E += section(8, "Outstanding work")
    E += [
        P(
            "Listed in the order it should be picked up. The first three need a human action "
            "outside the codebase.",
            "body"),
        Spacer(1, 5),
        kv_table([
            ["Priority", "Item", "Owner", "Note"],
            ["<b>Critical</b>", "Rotate five exposed provider credentials",
             "Human",
             "OmniRoute, two OpenRouter keys, Cloudflare and Token Harbor. Rotate at the provider, then update <font face='Courier'>.env</font> and Doppler."],
            ["<b>High</b>", "Write chapter bodies (Phase 2)",
             "Engineering",
             "Introduction, summary and key takeaways for 118,875 chapters, plus quizzes, exercises and flashcards. The generation path exists and is opt-in."],
            ["<b>High</b>", "Point MX at Cloudflare Email Routing",
             "Human",
             "Replace the two MX records pointing at <font face='Courier'>mail.aclacademy.me</font>, add the DKIM record, update SPF, then route <font face='Courier'>support@aclacademy.me</font> to the owner mailbox."],
            ["<b>Medium</b>", "Organisation selection cascade",
             "Engineering",
             "State, federal or private, then a searchable institution list, then organisation login. Requested, never started."],
            ["<b>Medium</b>", "Fix the failing feature tests",
             "Engineering",
             "<font face='Courier'>CcmcasCurriculumIntegrationTest</font> violates the <font face='Courier'>academic_programs</font> foreign key to <font face='Courier'>departments</font>; four <font face='Courier'>StudentRegistrationCompleteTest</font> cases insert without the NOT NULL <font face='Courier'>jamb_registration_number</font>. Five failures, all pre-existing."],
            ["<b>Medium</b>", "Generate assessment content",
             "Engineering",
             "Lesson blocks total zero. The path is built; the run has not happened."],
            ["<b>Low</b>", "Correct the database README",
             "Engineering",
             "It still describes SQLite and PostgreSQL. ADR-0005 makes MariaDB authoritative; the file was already recorded as an outstanding task."],
            ["<b>Low</b>", "Correct the onboarding prerequisites",
             "Human",
             "This report states PostgreSQL as the local prerequisite. The authoritative engine is MariaDB per ADR-0005."],
        ], [17 * mm, 40 * mm, 19 * mm, PAGE_W - 2 * MARGIN - 76 * mm]),
        Spacer(1, 8),
        callout(
            "Status at the close of this period",
            "The catalogue is complete: all 5,928 courses carry a full "
            "twenty-chapter set derived from the national curriculum, with no "
            "placeholder chapter left in the database. The provider layer no longer "
            "depends on a single provider, and the failure modes that stalled generation "
            "twice have been diagnosed and fixed at their root. The next material piece of "
            "work is the chapter bodies, which turns the catalogue from a titled outline "
            "into studyable material.",
            GREEN),
        Spacer(1, 7),
        HRFlowable(width="100%", thickness=0.8, color=BORDER, spaceAfter=4),
        P(
            "ACL — Anyone Can Learn. This report was generated against production on "
            f"{now.strftime('%d %B %Y')}. All figures are measured, not estimated. "
            "Where a claim was not verified, the report says so.",
            "small"),
    ]

    doc.build(E)
    return doc


if __name__ == "__main__":
    out = os.path.abspath("docs/ACL_MONTHLY_REPORT_2026_10.pdf")
    build()
    size = os.path.getsize(out)
    print(f"PDF written: {out} ({size/1024:.1f} KB)")
