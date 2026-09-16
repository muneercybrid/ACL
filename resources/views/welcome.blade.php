@extends('layouts.public')

@section('title', 'ACL — Anyone Can Learn')
@section('description', 'ACL connects learners, educators, courses, resources, and universities in one structured learning ecosystem — for every discipline in Nigeria.')

@php
    $audiences = [
        ['label' => 'Students', 'title' => 'Learn alongside your academic journey.', 'copy' => 'Follow courses connected to your programme, level, and semester, then keep your progress in one place.', 'icon' => 'academic-cap'],
        ['label' => 'External learners', 'title' => 'Explore at your own pace.', 'copy' => 'Build a learning path around a subject, a new skill, or a question you are ready to pursue.', 'icon' => 'book-open'],
        ['label' => 'Tutors', 'title' => 'Help someone move forward.', 'copy' => 'Share practical guidance, answer questions, and make difficult ideas easier to approach.', 'icon' => 'user-group'],
        ['label' => 'Lecturers', 'title' => 'Teach with structure.', 'copy' => 'Build courses, organise resources, and guide learners through a clear academic experience.', 'icon' => 'clipboard-document-check'],
        ['label' => 'Universities', 'title' => 'Connect learning across the institution.', 'copy' => 'Bring academic structures and digital learning together without losing the shape of the institution.', 'icon' => 'rectangle-stack'],
    ];

    $disciplines = [
        ['name' => 'Computing', 'emoji' => '💻', 'examples' => 'Computer Science, Cyber Security, Software Engineering', 'tone' => 'bg-primary/10 text-primary'],
        ['name' => 'Medicine & Dentistry', 'emoji' => '🩺', 'examples' => 'Medicine, Surgery, Dentistry', 'tone' => 'bg-red-50 text-red-600'],
        ['name' => 'Engineering', 'emoji' => '⚙️', 'examples' => 'Civil, Electrical, Mechanical Engineering', 'tone' => 'bg-amber-50 text-amber-600'],
        ['name' => 'Sciences', 'emoji' => '🔬', 'examples' => 'Biochemistry, Physics, Mathematics, Microbiology', 'tone' => 'bg-blue-50 text-blue-600'],
        ['name' => 'Health Sciences', 'emoji' => '💊', 'examples' => 'Nursing, Medical Lab Science, Pharmacy', 'tone' => 'bg-emerald-50 text-emerald-600'],
        ['name' => 'Law', 'emoji' => '⚖️', 'examples' => 'LL.B. Law, Jurisprudence', 'tone' => 'bg-violet-50 text-violet-600'],
        ['name' => 'Arts', 'emoji' => '🎨', 'examples' => 'English, History, Languages, Philosophy', 'tone' => 'bg-pink-50 text-pink-600'],
        ['name' => 'Education', 'emoji' => '🎓', 'examples' => 'Science Education, Arts Education, Educational Management', 'tone' => 'bg-teal-50 text-teal-600'],
        ['name' => 'Social Sciences', 'emoji' => '📊', 'examples' => 'Economics, Political Science, Sociology', 'tone' => 'bg-orange-50 text-orange-600'],
        ['name' => 'Administration & Management', 'emoji' => '💼', 'examples' => 'Accounting, Business Admin, Public Admin', 'tone' => 'bg-indigo-50 text-indigo-600'],
        ['name' => 'Agriculture', 'emoji' => '🌾', 'examples' => 'Animal Science, Crop Science, Agronomy', 'tone' => 'bg-green-50 text-green-600'],
        ['name' => 'Architecture', 'emoji' => '🏛️', 'examples' => 'Architecture, Urban Planning, Quantity Surveying', 'tone' => 'bg-stone-50 text-stone-600'],
        ['name' => 'Environmental Sciences', 'emoji' => '🌍', 'examples' => 'Environmental Management, Geography', 'tone' => 'bg-cyan-50 text-cyan-600'],
        ['name' => 'Communication & Media', 'emoji' => '📡', 'examples' => 'Mass Communication, Journalism', 'tone' => 'bg-fuchsia-50 text-fuchsia-600'],
        ['name' => 'Basic Medical Sciences', 'emoji' => '🫀', 'examples' => 'Anatomy, Physiology, Pharmacology', 'tone' => 'bg-rose-50 text-rose-600'],
        ['name' => 'Veterinary Medicine', 'emoji' => '🐾', 'examples' => 'Veterinary Surgery, Animal Health', 'tone' => 'bg-lime-50 text-lime-600'],
    ];

    $courses = [
        ['name' => 'Human Anatomy', 'field' => 'Health Sciences', 'tone' => 'bg-red-50', 'accent' => 'text-red-600', 'icon' => '🫀'],
        ['name' => 'Introduction to Economics', 'field' => 'Social Sciences', 'tone' => 'bg-orange-50', 'accent' => 'text-orange-600', 'icon' => '📊'],
        ['name' => 'Organic Chemistry', 'field' => 'Sciences', 'tone' => 'bg-blue-50', 'accent' => 'text-blue-600', 'icon' => '⚗️'],
        ['name' => 'Structural Analysis', 'field' => 'Engineering', 'tone' => 'bg-amber-50', 'accent' => 'text-amber-600', 'icon' => '🏗️'],
        ['name' => 'Cyber Security Fundamentals', 'field' => 'Computing', 'tone' => 'bg-primary/10', 'accent' => 'text-primary', 'icon' => '🛡️'],
        ['name' => 'Constitutional Law', 'field' => 'Law', 'tone' => 'bg-violet-50', 'accent' => 'text-violet-600', 'icon' => '⚖️'],
        ['name' => 'Agricultural Economics', 'field' => 'Agriculture', 'tone' => 'bg-green-50', 'accent' => 'text-green-600', 'icon' => '🌾'],
        ['name' => 'Business Management', 'field' => 'Administration', 'tone' => 'bg-indigo-50', 'accent' => 'text-indigo-600', 'icon' => '💼'],
        ['name' => 'Architecture Design Studio', 'field' => 'Architecture', 'tone' => 'bg-stone-50', 'accent' => 'text-stone-600', 'icon' => '🏛️'],
    ];

    $journey = [
        ['step' => '01', 'name' => 'Discover', 'copy' => 'Find a course, a learning path, or a question worth following.'],
        ['step' => '02', 'name' => 'Learn', 'copy' => 'Move through lessons and resources with a clear sense of what comes next.'],
        ['step' => '03', 'name' => 'Practice', 'copy' => 'Use exercises and assessments to turn understanding into confidence.'],
        ['step' => '04', 'name' => 'Improve', 'copy' => 'Ask for help, review your progress, and keep building from where you are.'],
        ['step' => '05', 'name' => 'Achieve', 'copy' => 'Recognise the work you have completed with a meaningful learning record.'],
    ];

    $features = [
        ['title' => 'Mobile access', 'copy' => 'A focused experience that works wherever you begin.'],
        ['title' => 'Low-bandwidth awareness', 'copy' => 'Fast, purposeful pages without heavy media getting in the way.'],
        ['title' => 'Human educators', 'copy' => 'Tutors and lecturers remain part of the learning relationship.'],
        ['title' => 'Intelligent assistance', 'copy' => 'ACLi helps explain concepts and generate study materials for every discipline.'],
    ];
@endphp

@section('content')
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-primary-fg">Skip to content</a>

    <div id="main-content">
        {{-- HERO --}}
        <section class="relative overflow-hidden border-b border-border bg-bg">
            <div class="mx-auto grid max-w-6xl gap-14 px-4 pb-20 pt-16 sm:px-6 lg:grid-cols-[0.92fr_1.08fr] lg:items-center lg:gap-16 lg:pb-28 lg:pt-24">
                <div class="relative z-10">
                    <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-primary"><span class="h-2 w-2 rounded-full bg-primary"></span>Anyone can learn</p>
                    <h1 class="mt-6 max-w-xl text-5xl font-extrabold leading-[1.02] tracking-tight text-text sm:text-6xl lg:text-7xl">Every discipline. One learning ecosystem.</h1>
                    <p class="mt-7 max-w-xl text-lg leading-8 text-muted sm:text-xl">From Medicine to Engineering, Law to Agriculture, Computing to the Arts — ACL connects courses, people, resources, and progress into one platform built for every Nigerian university.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a href="{{ route('register') }}" class="press inline-flex min-h-12 items-center justify-center rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg">Start learning <x-ui.icon name="chevron-right" class="ml-2 h-4 w-4" /></a>
                        <a href="#disciplines" class="press inline-flex min-h-12 items-center justify-center rounded-lg border border-border bg-surface px-6 py-3 text-sm font-semibold text-text transition hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg">Explore disciplines</a>
                    </div>
                    <p class="mt-5 text-sm text-muted">Access follows your learning context, not your wallet.</p>
                </div>

                <div class="relative min-h-104 sm:min-h-124" aria-label="A visual map of connected learning" role="img">
                    <div class="absolute right-0 top-0 h-56 w-56 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                    <div class="absolute bottom-4 left-0 h-40 w-40 rounded-full bg-info/10 blur-3xl" aria-hidden="true"></div>

                    {{-- Main dashboard card --}}
                    <div class="absolute inset-x-3 top-6 -rotate-3 rounded-4xl border border-border bg-surface p-5 shadow-lg sm:inset-x-8 sm:p-7">
                        <div class="flex items-center justify-between border-b border-border pb-4">
                            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Your learning space</p><p class="mt-1 text-sm font-semibold text-text">A clear next step, every time.</p></div>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-fg">A</span>
                        </div>
                        <div class="mt-6 grid grid-cols-[1fr_0.8fr] gap-4">
                            <div class="rounded-xl bg-raised p-4">
                                <div class="flex items-center justify-between text-xs text-muted"><span>Current course</span><span class="font-mono text-primary">68%</span></div>
                                <p class="mt-4 max-w-48 text-lg font-bold leading-tight text-text">Human Anatomy</p>
                                <div class="mt-7 h-2 rounded-full bg-border"><div class="h-2 w-[68%] rounded-full bg-primary"></div></div>
                                <p class="mt-2 text-xs text-muted">Module 3 of 5</p>
                            </div>
                            <div class="flex flex-col justify-between rounded-xl bg-primary/10 p-4 text-accent">
                                <x-ui.icon name="book-open" class="h-7 w-7" />
                                <p class="text-sm font-bold leading-tight">Keep your curiosity close.</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            @foreach ([['05', 'Courses'], ['12', 'Lessons'], ['04', 'Checks']] as $stat)
                                <div class="rounded-xl border border-border p-3"><p class="text-xl font-bold text-text">{{ $stat[0] }}</p><p class="mt-1 text-[0.65rem] uppercase tracking-wider text-muted">{{ $stat[1] }}</p></div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Discipline badge cards --}}
                    <div class="absolute bottom-20 right-0 w-48 rotate-2 rounded-2xl border border-border bg-red-50 p-4 shadow-lg sm:bottom-16 sm:right-2 sm:w-52">
                        <div class="flex items-center gap-2"><span class="text-xl">🫀</span><span class="text-xs font-bold uppercase tracking-[0.14em] text-red-600">Medicine</span></div>
                        <p class="mt-3 text-sm font-bold leading-tight text-text">Clinical Anatomy & Physiology</p>
                        <p class="mt-1 text-xs text-muted">200L · First Semester</p>
                    </div>

                    <div class="absolute bottom-2 left-2 w-44 -rotate-2 rounded-2xl border border-border bg-amber-50 p-4 shadow-lg sm:bottom-0 sm:left-6 sm:w-52">
                        <div class="flex items-center gap-2"><span class="text-xl">⚙️</span><span class="text-xs font-bold uppercase tracking-[0.14em] text-amber-600">Engineering</span></div>
                        <p class="mt-3 text-sm font-bold leading-tight text-text">Thermodynamics & Heat Transfer</p>
                        <p class="mt-1 text-xs text-muted">300L · Second Semester</p>
                    </div>

                    <div class="absolute top-0 right-2 w-40 rotate-1 rounded-2xl border border-border bg-primary/5 p-4 shadow-lg sm:top-4 sm:right-6 sm:w-48">
                        <div class="flex items-center gap-2"><span class="text-xl">💻</span><span class="text-xs font-bold uppercase tracking-[0.14em] text-primary">Computing</span></div>
                        <p class="mt-3 text-sm font-bold leading-tight text-text">Data Structures & Algorithms</p>
                        <p class="mt-1 text-xs text-muted">200L · First Semester</p>
                    </div>
                </div>
            </div>
            <div class="mx-auto max-w-6xl px-4 pb-8 sm:px-6"><p class="border-t border-border pt-5 text-xs font-medium uppercase tracking-[0.16em] text-muted">For students, educators, tutors, universities, and the curious — across every discipline.</p></div>
        </section>

        {{-- WHAT IS ACL --}}
        <section id="what-is-acl" class="bg-surface py-20 sm:py-28"><div class="mx-auto max-w-6xl px-4 sm:px-6"><div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-start"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">What is ACL?</p><h2 class="mt-4 max-w-md text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Learning is bigger than a classroom.</h2></div><div><p class="max-w-2xl text-lg leading-8 text-muted">Students learn from lectures, notes, practical work, tutors, discussions, assessments, and independent exploration. ACL connects those experiences so the path feels less fragmented and more possible to follow — no matter what you study.</p><div class="mt-10 grid gap-3 sm:grid-cols-7 sm:items-start">@foreach (['Learners', 'Courses', 'Modules', 'Chapters', 'Resources', 'Assessments', 'Progress'] as $index => $item)<div class="flex items-center gap-3 sm:block"><div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $index === 6 ? 'bg-primary text-primary-fg' : 'bg-raised text-primary' }} text-sm font-bold">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div><p class="mt-0 text-sm font-semibold text-text sm:mt-3">{{ $item }}</p>@if ($index < 6)<span class="hidden text-muted sm:mt-2 sm:block" aria-hidden="true">→</span>@endif</div>@endforeach</div></div></div></div></section>

        {{-- AUDIENCES --}}
        <section class="border-y border-border bg-bg py-20 sm:py-28"><div class="mx-auto max-w-6xl px-4 sm:px-6"><div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">One ecosystem, many starting points</p><h2 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Built for the people who make learning happen.</h2></div><div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">@foreach ($audiences as $audience)<article class="flex min-h-64 flex-col rounded-2xl border border-border bg-surface p-5 transition duration-300 hover:-translate-y-1 hover:border-primary/50"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"><x-ui.icon name="{{ $audience['icon'] }}" class="h-5 w-5" /></span><p class="mt-8 text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ $audience['label'] }}</p><h3 class="mt-2 text-xl font-bold leading-tight text-text">{{ $audience['title'] }}</h3><p class="mt-3 text-sm leading-6 text-muted">{{ $audience['copy'] }}</p></article>@endforeach</div></div></section>

        {{-- DISCIPLINES --}}
        <section id="disciplines" class="bg-surface py-20 sm:py-28"><div class="mx-auto max-w-6xl px-4 sm:px-6"><div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">16 national disciplines</p><h2 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Every field of study, connected.</h2><p class="mt-4 text-lg leading-8 text-muted">ACLi supports the full NUC CCMAS curriculum — from Computing to Veterinary Medicine, with tailored content for each discipline.</p></div><div class="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach ($disciplines as $disc)<div class="rounded-2xl border border-border bg-bg p-5 transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm"><div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $disc['tone'] }} text-lg">{{ $disc['emoji'] }}</span><h3 class="text-sm font-bold text-text">{{ $disc['name'] }}</h3></div><p class="mt-3 text-xs leading-5 text-muted">{{ $disc['examples'] }}</p></div>@endforeach</div></div></section>

        {{-- LEARNING JOURNEY --}}
        <section id="how" class="bg-bg py-20 sm:py-28"><div class="mx-auto max-w-6xl px-4 sm:px-6"><div class="grid gap-12 lg:grid-cols-[0.72fr_1.28fr]"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">The learning experience</p><h2 class="mt-4 max-w-md text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">A journey with room to grow.</h2><p class="mt-6 max-w-md text-base leading-7 text-muted">ACL is designed around the complete learning process, not simply a place to store course files.</p></div><ol class="divide-y divide-border border-y border-border">@foreach ($journey as $item)<li class="grid gap-3 py-5 sm:grid-cols-[4rem_10rem_1fr] sm:items-center"><span class="font-mono text-sm text-primary">{{ $item['step'] }}</span><h3 class="text-lg font-bold text-text">{{ $item['name'] }}</h3><p class="text-sm leading-6 text-muted">{{ $item['copy'] }}</p></li>@endforeach</ol></div></div></section>

        {{-- COURSE SHOWCASE --}}
        <section id="courses" class="border-y border-border bg-surface py-20 sm:py-28"><div class="mx-auto max-w-6xl px-4 sm:px-6"><div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Across disciplines</p><h2 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Learning should not be one-shaped.</h2></div><p class="max-w-sm text-sm leading-6 text-muted">From clinical anatomy to structural analysis, constitutional law to cyber security — every discipline deserves a great learning platform.</p></div><div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach ($courses as $course)<article class="overflow-hidden rounded-2xl border border-border bg-bg transition hover:-translate-y-0.5 hover:shadow-md"><div class="h-24 {{ $course['tone'] }} p-5 flex items-end"><span class="text-3xl">{{ $course['icon'] }}</span><span class="ml-auto inline-flex rounded-full bg-white/80 px-3 py-1 text-xs font-semibold {{ $course['accent'] }}">{{ $course['field'] }}</span></div><div class="p-5"><h3 class="text-lg font-bold text-text">{{ $course['name'] }}</h3><p class="mt-2 text-sm text-muted">Course · Modules · Chapters · Exercises</p></div></article>@endforeach</div></div></section>

        {{-- BUILT FOR REAL LEARNING --}}
        <section class="bg-bg py-20 sm:py-28"><div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Built for real learning</p><h2 class="mt-4 max-w-xl text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Designed for the way people actually learn.</h2><p class="mt-6 max-w-xl text-lg leading-8 text-muted">Whether you are learning from a phone, working with limited bandwidth, balancing a degree, or building a new skill, ACL keeps the experience clear, flexible, and grounded.</p></div><div class="grid gap-3 sm:grid-cols-2">@foreach ($features as $item)<div class="rounded-2xl border border-border bg-surface p-5"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-ui.icon name="check" class="h-4 w-4" /></span><h3 class="mt-5 font-bold text-text">{{ $item['title'] }}</h3><p class="mt-2 text-sm leading-6 text-muted">{{ $item['copy'] }}</p></div>@endforeach</div></div></section>

        {{-- UNIVERSITIES --}}
        <section id="universities" class="bg-primary py-20 text-primary-fg sm:py-28"><div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[1fr_0.9fr] lg:items-center"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary-fg/70">For universities</p><h2 class="mt-4 max-w-2xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">A digital learning layer for modern universities.</h2><p class="mt-6 max-w-xl text-lg leading-8 text-primary-fg/80">Connect University, Faculty, Department, Level, Courses, Modules, Chapters, and Students in a learning environment that respects the institution's structure — across every NUC discipline.</p><a href="mailto:hello@aclacademy.me" class="press mt-8 inline-flex min-h-12 items-center rounded-lg bg-surface px-6 py-3 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-primary-fg">For universities <span class="ml-2" aria-hidden="true">→</span></a></div><div class="rounded-2xl border border-primary-fg/20 bg-primary-fg/10 p-5 sm:p-7"><p class="text-xs font-bold uppercase tracking-[0.16em] text-primary-fg/70">A connected structure</p><div class="mt-6 space-y-3 text-sm font-semibold"><div class="rounded-lg bg-surface/15 p-3">University</div><div class="ml-5 rounded-lg bg-surface/15 p-3">Faculty · Department</div><div class="ml-10 rounded-lg bg-surface/15 p-3">Level · Courses</div><div class="ml-16 rounded-lg bg-surface p-3 text-text">Modules · Chapters · Students</div></div></div></div></section>

        {{-- TECHNOLOGY --}}
        <section class="bg-surface py-20 sm:py-28"><div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-center"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Technology in its place</p><h2 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">Technology should help you learn, not replace learning.</h2></div><div class="grid gap-6 sm:grid-cols-2"><p class="text-lg leading-8 text-muted">Intelligent tools can help explain difficult concepts, summarise material, generate practice questions, and help educators prepare. The learning stays human, and generated content can remain subject to review.</p><div class="rounded-2xl border border-border bg-bg p-6"><span class="text-4xl font-extrabold text-primary">01</span><p class="mt-10 text-sm font-bold text-text">Assist understanding.</p><p class="mt-2 text-sm leading-6 text-muted">Keep curiosity moving when a difficult idea needs another explanation.</p></div></div></div></section>

        {{-- CTA --}}
        <section class="border-t border-border bg-bg py-24 sm:py-32"><div class="mx-auto max-w-3xl px-4 text-center sm:px-6"><p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Begin anywhere</p><h2 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-6xl">There is always something new to learn.</h2><p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-muted">Whether you are studying Medicine, Engineering, Law, Agriculture, Computing, or the Arts — ACL gives you a place to begin.</p><div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row"><a href="{{ route('register') }}" class="press inline-flex min-h-12 items-center justify-center rounded-lg bg-primary px-7 py-3 text-sm font-semibold text-primary-fg transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring">Start learning <x-ui.icon name="chevron-right" class="ml-2 h-4 w-4" /></a><a href="#courses" class="press inline-flex min-h-12 items-center justify-center rounded-lg border border-border px-7 py-3 text-sm font-semibold text-text transition hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Explore courses</a></div></div></section>
    </div>
@endsection
