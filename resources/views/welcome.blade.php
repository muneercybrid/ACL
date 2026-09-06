<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    <meta name="description" content="@yield('description', 'ACL — Anyone Can Learn. An educational ecosystem connecting learners, courses, tutors, and universities.')">
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg font-display text-text antialiased">

@x-site-header

<main class="flex-1">

    {{-- ============================= HERO ============================= --}}
    <section class="relative py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4 grid max-w-6xl items-center gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Eyebrow --}}
            <span class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-muted">
                <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                Open learning for Nigerian institutions
            </span>

            <div class="text-center text-sm sm:text-left">
                <h1 class="mt-4 text-4xl md:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight text-text sm:mt-5">
                    A better way to learn, teach, and grow.
                </h1>
                <p class="mt-5 max-w-xl text-lg sm:mt-4 sm:text-base lg:text-xl text-muted">
                    ACL brings courses, academic learning, tutors, resources, assessments, and intelligent learning tools together in one connected learning ecosystem.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3 justify-center sm:justify-start">
                    <a href="{{ route('login') }}"
                       class="press rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                        Start Learning
                    </a>
                    <a href="#what-is-acl"
                       class="press rounded-lg border border-border bg-surface px-6 py-3 text-sm font-semibold text-text transition hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring">
                        Explore ACL
                    </a>
                </div>
            </div>

            {{-- Visual - editorial composition, not cliché stock photo --}}
            <div class="relative flex justify-center sm:justify-end lg:justify-center">
                <div class="flex flex-col items-center gap-4 px-4 sm:px-6 lg:w-2/3">
                    <!-- Simulated visual: editorial-style stacked elements -->
                    <div class="flex flex-col items-center gap-3 w-full max-w-md">
                        <div class="w-full h-12 rounded-2xl bg-surface border border-border relative overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-br from-primary/10 to-transparent"></div>
                        </div>
                        <div class="w-14 h-1.5 rounded-bg bg-primary"></div>
                        <div class="w-12 h-1.5 rounded-bg bg-accent"></div>
                        <div class="w-10 h-1.5 rounded-bg bg-surface"></div>
                    </div>

                    <p class="text-xs sm:text-muted text-center">
                        Federal, state & private universities, polytechnics and colleges of education.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= WHAT IS ACL? ============================= --}}
    <section id="what-is-acl" class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Learning is bigger than a classroom.
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    Students learn from lectures, notes, practical work, tutors, discussions, assessments, and independent exploration.
                    Lecturers create knowledge. Tutors provide guidance. Universities organize academic learning.
                    ACL connects these experiences.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Learners -->
                <article class="reveal rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� learners</span>
                    <h3 class="text-xl font-semibold text-text mb-3">Students</h3>
                    <p class="text-muted">
                        Learn alongside your academic journey. Every course your programme, level and semester entitle you to appears automatically — no purchase, no hunting.
                    </p>
                </article>

                <!-- External Learners -->
                <article class="reveal group rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 100ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� explore</span>
                    <h3 class="text-xl font-semibold text-text mb-3">External Learners</h3>
                    <p class="text-muted group-hover:text-primary transition">
                        Explore subjects, skills, and learning paths at your own pace. ACL is built for anyone who wants to learn, regardless of institution.
                    </p>
                </article>

                <!-- Structure -->
                <article class="reveal rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 200ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� structure</span>
                    <h3 class="text-xl font-semibold text-text mb-3">Organized Learning</h3>
                    <p class="text-muted">
                        University → Faculty → Department → Programme → Level → Semester → Course → Module → Chapter → Lesson. Structured pathways make large subjects easier to navigate.
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- ============================= AUDIENCE CARDS ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Built for different kinds of learners
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    ACL serves everyone from students following their academic journey to professionals exploring new skills.
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                <!-- Students -->
                <article class="reveal rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� students</span>
                    <h3 class="text-xl font-semibold text-text">Students</h3>
                    <p class="mt-3 text-muted">
                        Learn alongside your academic journey. Every course your programme, level and semester entitle you to appears automatically.
                    </p>
                </article>

                <!-- External Learners -->
                <article class="reveal group rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 100ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� explore</span>
                    <h3 class="text-xl font-semibold text-text">External Learners</h3>
                    <p class="mt-3 group-hover:text-primary transition text-muted">
                        Explore subjects and skills at your own pace, anytime.
                    </p>
                </article>

                <!-- Tutors -->
                <article class="reveal rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 200ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� tutors</span>
                    <h3 class="text-xl font-semibold text-text">Tutors</h3>
                    <p class="mt-3 text-muted">
                        Share knowledge and help learners move forward. ACL provides the structure; you provide the guidance.
                    </p>
                </article>

                <!-- Lecturers -->
                <article class="reveal group rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 300ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� lecturers</span>
                    <h3 class="text-xl font-semibold text-text">Lecturers</h3>
                    <p class="mt-3 group-hover:text-primary transition text-muted">
                        Build structured courses and guide students through them. ACL handles the complexity; you focus on teaching.
                    </p>
                </article>

                <!-- Universities -->
                <article class="reveal rounded-2xl border border-border bg-white p-6 sm:p-8 lg:p-10" style="transition-delay: 400ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary mb-4">� universities</span>
                    <h3 class="text-xl font-semibold text-text">Universities</h3>
                    <p class="mt-3 text-muted">
                        Create a connected digital learning environment. ACL links institutional academic structures with digital learning.
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- ============================= LEARNING JOURNEY ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="relative">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-6">
                    The ACL learning journey
                </h2>

                <div class="reveal space-y-4 md:space-y-0 md:space-x-8">
                    <!-- Vertical journey steps -->
                    <div class="flex flex-col md:flex-row items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-primary-fg">D</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Discover</h3>
                            <p class="text-muted">
                                Browse courses, explore learning paths, discover topics that match your interests and programme.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-start gap-4 border-t pt-8 md:border-0">
                        <div class="w-12 h-12 rounded-full bg-primary/60 flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-primary">L</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Learn</h3>
                            <p class="text-muted">
                                Work through lessons at your pace. ACL remembers what you've completed and suggests what's next.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-start gap-4 border-t pt-8 md:border-0">
                        <div class="w-12 h-12 rounded-full bg-accent flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-accent">P</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Practice</h3>
                            <p class="text-muted">
                                Complete exercises and assessments that reinforce what you've learned. Immediate feedback, no pressure.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-start gap-4 border-t pt-8 md:border-0">
                        <div class="w-12 h-12 rounded-full bg-info flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-info">A</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Ask</h3>
                            <p class="text-muted">
                                Get help when you're stuck. Intelligent tools and human tutors available to clarify concepts.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-start gap-4 border-t pt-8 md:border-0">
                        <div class="w-12 h-12 rounded-full bg-primary/60 flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-primary">I</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Improve</h3>
                            <p class="text-muted">
                                Track your progress, review weak areas, and improve continuously. Your data, your pace.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-start gap-4 border-t pt-8 md:border-0">
                        <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center flex-shrink-0 md:mt-0 md:mr-4">
                            <span class="text-xs font-bold text-primary-fg">A</span>
                        </div>
                        <div class="w-full md:w-1/2 reveal-text">
                            <h3 class="font-semibold text-text mb-2">Achieve</h3>
                            <p class="text-muted">
                                Earn certificates for completed courses. Verify your achievements. Celebrate what you've learned.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= COURSES ============================= --}}
    <section id="courses" class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Courses for every faculty
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    Not just computing — the sciences, arts, management, engineering, health and more.
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach (['Introduction to Economics', 'Human Anatomy', 'Business Management', 'Computer Networks', 'Agricultural Science', 'Digital Marketing', 'Mathematics', 'Entrepreneurship'] as $i => $course)
                <article class="reveal rounded-xl border border-border bg-white p-5" style="transition-delay: {{ $i * 40 }}ms">
                    <div class="h-1.5 w-10 rounded-full bg-primary"></div>
                    <p class="mt-3 font-semibold text-text">{{ $course }}</p>
                    <p class="mt-1 text-xs text-muted">Courses by semester & level</p>
                </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================= LEARNING WITHOUT LIMITS ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Learning without limits
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    ACL should work for learners who may have limited bandwidth, mobile-only access, or different academic backgrounds.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-text mb-3">Designed for reality</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Mobile-first — works on any device, from flagship phones to basic smartphones</li>
                        <li>Low-bandwidth aware — content loads fast, images are optimized, no heavy video backgrounds</li>
                        <li>Structured learning — clear pathways make it easy to pick up where you left off</li>
                        <li>Flexible pacing — learn at your own speed, on your own schedule</li>
                        <li>Academic + professional — courses for degree students and skill-builders alike</li>
                        <li>Human educators at the center — intelligent tools assist, never replace</li>
                    </ul>
                </div>

                <div class="reveal">
                    <!-- Simulated: grounded visual, no AI artifacts -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-muted text-center">
                            Grounded, realistic. Learning should work for everyone.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= INTELLIGENT LEARNING ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Technology should help you learn — not replace learning.
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    ACL can use intelligent tools to help learners understand difficult concepts, summarize material, practice questions, explore topics, and receive learning assistance. Lecturers and tutors can use AI to assist with content creation, lesson preparation, assessment generation, explanations, and learning resources. Educational content remains subject to human review.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-semibold text-text mb-3">Intelligent assistance</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Understand difficult concepts with guided breakdowns</li>
                        <li>Summarize long-form material into key takeaways</li>
                        <li>Practice questions tailored to your progress</li>
                        <li>Explore topics with related resources and readings</li>
                        <li>Receive learning assistance when you're stuck</li>
                    </ul>
                </div>

                <div class="reveal">
                    <!-- Subtle visual: no glowing brains, no robots, no holograms -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-center text-muted">
                            Subtle. Human-centered. Tools that serve the learning, not the other way around.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= FOR EDUCATORS ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid lg:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                        Teach with structure. Reach your learners.
                    </h2>
                    <p class="text-muted mb-6">
                        ACL helps educators organize courses, build modules and chapters, create learning resources and assessments, track learner progress, and communicate with learners. Use intelligent tools to accelerate preparation — while keeping educational content subject to human review.
                    </p>
                    <a href="/universities"
                       class="press rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                        Teach on ACL
                    </a>
                </div>

                <div class="reveal">
                    <!-- Grounded visual: no circuits, no code screenshots -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-center text-muted">
                            Structure. Guidance. Human review. Technology as assistant.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= FOR UNIVERSITIES ============================= --}}
    <section id="universities" class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid lg:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                        A digital learning layer for modern universities.
                    </h2>
                    <p class="text-muted mb-6">
                        ACL can connect institutional academic structures with digital learning. Showcasing:
                        University → Faculty → Department → Level → Courses → Modules → Chapters → Students.
                    </p>

                    <ul class="list-disc list-inside text-muted space-y-3 mb-6">
                        <li>Structured academic courses</li>
                        <li>Learning materials and resources</li>
                        <li>Assessments and progress tracking</li>
                        <li>Lecturer tools and administration</li>
                        <li>Certificates and verifiable achievements</li>
                        <li>Controlled access — enrollment derived from institutional record</li>
                        <li>Analytics for informed decision-making</li>
                    </ul>

                    <a href="#contact"
                       class="press rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                        For Universities →
                    </a>
                </div>

                <div class="reveal">
                    <!-- Institutional visual: clean, structured, no fake logos -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-center text-muted">
                            Structured. Institutional. Built for real universities.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= CERTIFICATES & ACHIEVEMENT ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Certificates & achievement
                </h2>
                <p class="text-muted">
                    Learning should produce meaningful evidence of achievement.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-text mb-3">Course completion</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Earn a certificate for each completed course</li>
                        <li>Verifiable achievements you can share</li>
                        <li>Record progress in your personal learning profile</li>
                    </ul>

                    <h3 class="font-semibold text-text mt-6 mb-3">Assessments</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Assessments woven into the learning journey</li>
                        <li>Immediate feedback to support growth</li>
                        <li>Designed by educators, not algorithms alone</li>
                    </ul>
                </div>

                <div class="reveal">
                    <!-- Realistic certificate mockup -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-6 text-center text-muted">
                            Professional certificate mockup. Earned, not given.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= COMMUNITY ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                    Learning is better when you don't learn alone.
                </h2>
                <p class="max-w-2xl mx-auto text-lg text-muted">
                    Discussions, tutors, lecturers, peer learning, questions, shared resources.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-text mb-3">The human side of learning</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Discussions with peers and educators</li>
                        <li>Tutors available to guide you forward</li>
                        <li>Lecturers who build and lead courses</li>
                        <li>Peer learning — share insights and resources</li>
                        <li>Ask questions, get answers</li>
                    </ul>
                </div>

                <div class="reveal">
                    <!-- Warm, human visual — no avatars, no fake user counts -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-center text-muted">
                            Warm and human. Learning thrives together.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= WHY ACL? ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-bg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                        Why ACL?
                    </h2>
                    <p class="text-muted mb-6">
                        Learning shouldn't depend on where you are, what you study, or who you know. ACL brings people and learning resources together in one place.
                    </p>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="reveal p-4 bg-surface rounded-lg">
                            <div class="text-primary text-2xl mb-2">📚</div>
                            <p class="text-sm text-text">Connected ecosystem</p>
                        </div>
                        <div class="reveal p-4 bg-surface rounded-lg" style="transition-delay: 100ms">
                            <div class="text-primary text-2xl mb-2">🎓</div>
                            <p class="text-sm text-text">Academic + professional</p>
                        </div>
                        <div class="reveal p-4 bg-surface rounded-lg" style="transition-delay: 200ms">
                            <div class="text-primary text-2xl mb-2">🌍</div>
                            <p class="text-sm text-text">Globally scalable</p>
                        </div>
                        <div class="reveal p-4 bg-surface rounded-lg" style="transition-delay: 300ms">
                            <div class="text-primary text-2xl mb-2">🤝</div>
                            <p class="text-sm text-text">Human-first</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-text mb-3">Traditional fragmented learning</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Notes here, videos there, questions somewhere else</li>
                        <li>Tutor contacts elsewhere, progress difficult to track</li>
                        <li>No central place for resources or achievements</li>
                    </ul>

                    <h3 class="font-semibold text-text mt-6 text-primary">ACL</h3>
                    <ul class="list-disc list-inside text-primary text-muted space-y-3">
                        <li>Learning in one place</li>
                        <li>Courses + Resources + Assessments + Tutors</li>
                        <li>Progress + Certificates + Community</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= BUILT FOR THE REAL WORLD ============================= --}}
    <section class="py-24 md:py-32 lg:py-48 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-text mb-3">Built for the real world</h3>
                    <ul class="list-disc list-inside text-muted space-y-3">
                        <li>Mobile access — learn from any device</li>
                        <li>Low-bandwidth awareness — fast loading, optimized assets</li>
                        <li>Structured learning — clear pathways, easy to navigate</li>
                        <li>Flexible pacing — learn at your own speed</li>
                        <li>Academic + professional learning</li>
                        <li>Human educators at the center</li>
                        <li>Intelligent assistance that serves, not replaces</li>
                    </ul>
                </div>

                <div class="reveal">
                    <!-- Grounded visual, no technical diagrams -->
                    <div class="aspect-square rounded-2xl bg-white p-8 border border-border">
                        <div class="h-1.5 w-10 rounded-bg bg-primary"></div>
                        <div class="h-1.5 w-8 rounded-bg bg-accent mt-3"></div>
                        <p class="mt-4 text-center text-muted">
                            Real. Grounded. Designed for real learners.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= CALL TO ACTION ============================= --}}
    <section class="relative py-24 md:py-32 lg:py-48 bg-bg overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-br from-surface/5 to-bg/30"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight text-text mb-4">
                There is always something new to learn.
            </h2>
            <p class="max-w-2xl mx-auto text-lg text-muted mb-8">
                Whether you're studying for a degree, teaching a course, building a skill, or simply curious about something new, ACL gives you a place to begin.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('login') }}"
                   class="press rounded-lg bg-primary px-8 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                    Start Learning
                </a>
                <a href="#what-is-acl"
                   class="press rounded-lg border border-border bg-surface px-8 py-3 text-sm font-semibold text-text transition hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring">
                    Explore Courses
                </a>
            </div>
        </div>
    </section>

    {{-- ============================= FOOTER ============================= --}}
    <footer class="bg-bg border-t border-border">
        <div class="max-w-7xl mx-auto px-4 py-12 sm:px-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">
                <div class="col-span-2 md:col-start-1 md:col-end-3">
                    <h3 class="text-xl font-semibold text-text mb-4">ACL — Anyone Can Learn</h3>
                    <p class="text-muted">
                        An educational ecosystem connecting learners, courses, tutors, and universities.
                    </p>
                </div>

                <div>
                    <h4 class="font-semibold text-text mb-3">Learn</h4>
                    <ul class="space-y-2">
                        <li class="hover:text-primary transition cursor-pointer">Courses</li>
                        <li class="hover:text-primary transition cursor-pointer">Learning Paths</li>
                        <li class="hover:text-primary transition cursor-pointer">Resources</li>
                        <li class="hover:text-primary transition cursor-pointer">Certificates</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-text mb-3">Teach</h4>
                    <ul class="space-y-2">
                        <li class="hover:text-primary transition cursor-pointer">Become a Tutor</li>
                        <li class="hover:text-primary transition cursor-point">Lecturer Resources</li>
                        <li class="hover:text-primary transition cursor-pointer">Create Courses</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-text mb-3">Institutions</h4>
                    <ul class="space-y-2">
                        <li class="hover:text-primary transition cursor-pointer">Universities</li>
                        <li class="hover:text-primary transition cursor-point">Institutional Learning</li>
                        <li class="hover:text-primary transition cursor-pointer">Contact ACL</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-text mb-3">ACL</h4>
                    <ul class="space-y-2">
                        <li class="hover:text-primary transition cursor-pointer">About</li>
                        <li class="hover:text-primary transition cursor-point">Mission</li>
                        <li class="hover:text-primary transition cursor-pointer">Help</li>
                        <li class="hover:text-primary transition cursor-pointer">Privacy</li>
                        <li class="hover:text-primary transition cursor-pointer">Terms</li>
                    </ul>
                </div>
            </div>

            <div class="pt-8 border-t border-border text-center text-xs text-muted">
                <p>&copy; {{ now()->year }} ACL — Anyone Can Learn</p>
            </div>
        </div>
    </footer>

</main>

@x-site-footer

<script>
    (function () {
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || !els.length) {
            els.forEach(function (el) { el.classList.add('is-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });
        els.forEach(function (el) { io.observe(el); });
    })();
</script>
</body>
</html>