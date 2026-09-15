@extends('layouts.app')

@section('title', 'Dashboard — ACL')

@section('header')
    <x-ui.page-header title="Student Dashboard" subtitle="Your academic profile and progress" />
@endsection

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">

    <!-- Profile Header -->
    <div class="mb-8 rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <!-- Profile avatar / logo -->
            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-emerald-500 shadow-md overflow-hidden">
                <img src="{{ asset('images/logo.svg') }}" alt="ACL" class="h-12 w-12 object-contain filter brightness-0 invert">
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-extrabold tracking-tight text-text">{{ $user->name }}</h2>
                <p class="mt-1 text-sm text-muted">{{ $user->email }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Student</span>
                    @if ($student && $student->nationality)
                        <span class="inline-flex items-center rounded-full bg-raised px-3 py-1 text-xs font-semibold text-muted">{{ $student->nationality }}</span>
                    @endif
                    @if ($student && $student->admission_year)
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Admitted {{ $student->admission_year }}</span>
                    @endif
                </div>
                @if ($verification && $verification->verified_name)
                    <p class="mt-3 text-sm font-medium text-text">JAMB Verified: <span class="font-bold">{{ $verification->verified_name }}</span>
                        @if ($verification->verified_institution)· {{ $verification->verified_institution }}@endif
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Stat Cards (active, no "coming soon") -->
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Enrolled courses" :value="$enrollments->count()" icon="book-open" hint="Currently active" />
        <x-ui.stat-card label="Programme courses" :value="$programmeCourses->count()" tone="primary" icon="academic-cap" :hint="$academicProgramme ? $academicProgramme->name : 'Not linked yet'" />
        <x-ui.stat-card label="Institution" value="{{ isset($institutionRecord) && $institutionRecord?->organization?->name ? $institutionRecord->organization->name : '—' }}" tone="info" icon="building-library" hint="{{ isset($institutionRecord) && $institutionRecord?->matric_number ? $institutionRecord->matric_number : 'No matric number' }}" />
    </div>

    <!-- Navigation Tabs -->
    <div class="mb-6 flex gap-1 overflow-x-auto rounded-xl bg-surface p-1 shadow-sm border border-border">
        <a href="#courses" onclick="showSection('courses')" class="tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap bg-primary text-primary-fg shadow-sm" id="tab-courses">My Courses</a>
        <a href="#biodata" onclick="showSection('biodata')" class="tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap" id="tab-biodata">Biodata</a>
        <a href="#academic" onclick="showSection('academic')" class="tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap" id="tab-academic">Academic Status</a>
        <a href="#security" onclick="showSection('security')" class="tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap" id="tab-security">Security</a>
        <a href="{{ route('student.my-courses') }}" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap">Full course list →</a>
    </div>

    <!-- MY COURSES SECTION (default visible) -->
    <section id="section-courses" class="profile-section mb-8 rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h3 class="text-lg font-extrabold text-text">My Courses</h3>
            <p class="mt-0.5 text-xs text-muted">Click any course to open chapters and outline</p>
        </div>
        <div class="p-6">
            @if ($enrollments->count() > 0)
                <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-muted">Enrolled</h4>
                <div class="mb-8 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($enrollments as $enrollment)
                        <a href="{{ route('courses.show', $enrollment->courseOffering) }}"
                           class="group block rounded-xl border border-emerald-300/60 bg-emerald-50/40 p-5 transition hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-ring">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="font-bold text-text group-hover:text-primary transition">{{ $enrollment->courseOffering->course->title }}</h4>
                                <span class="inline-flex rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase tracking-wide">Enrolled</span>
                            </div>
                            <p class="mt-2 text-sm text-muted">{{ $enrollment->courseOffering->course->description ?? 'No description available.' }}</p>
                            <div class="mt-4 flex items-center gap-3 text-xs text-muted">
                                <span>{{ $enrollment->courseOffering->course->credit_units ?? '-' }} credits</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ $enrollment->courseOffering->semester?->name ?? 'Current semester' }}</span>
                            </div>
                            <div class="mt-4 text-sm font-semibold text-primary">Open course →</div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($programmeCourses->count() > 0)
                <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-muted">{{ $academicProgramme ? 'Programme curriculum' : 'Curriculum courses' }}</h4>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($programmeCourses as $cc)
                        <a href="{{ route('student.course.show', $cc->id) }}"
                           class="group block rounded-xl border border-border bg-bg/60 p-5 transition hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-ring">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="font-bold text-text group-hover:text-primary transition">{{ $cc->course->title }}</h4>
                                <span class="inline-flex rounded-full border border-border bg-raised px-2.5 py-0.5 text-[10px] font-semibold text-muted uppercase tracking-wide">{{ $cc->course->code ?? 'N/A' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-muted">{{ $cc->course->description ?? 'No description available.' }}</p>
                            <div class="mt-4 flex items-center gap-3 text-xs text-muted">
                                <span>{{ $cc->course->credit_units ?? '-' }} credits</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ $cc->current_offering?->semester?->name ?? 'Available' }}</span>
                            </div>
                            <div class="mt-4 text-sm font-semibold text-primary">View course →</div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($enrollments->count() === 0 && $programmeCourses->count() === 0)
                <div class="rounded-xl border border-dashed border-border bg-raised/40 p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <h4 class="mt-4 text-base font-semibold text-text">No courses available yet</h4>
                    <p class="mt-1 text-sm text-muted">Once your institution enrols you for the semester, your courses will appear here automatically.</p>
                    <a href="{{ route('student.my-courses') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">Browse your programme</a>
                </div>
            @endif
        </div>
    </section>

    <!-- BIODATA SECTION -->
    <section id="section-biodata" class="profile-section mb-8 hidden rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h3 class="text-lg font-extrabold text-text">Biodata</h3>
            <p class="mt-0.5 text-xs text-muted">Your personal identification and registration information</p>
        </div>
        <div class="p-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @php $biodata = [
                ['label' => 'Full Name', 'value' => $user->name],
                ['label' => 'Email Address', 'value' => $user->email],
                ['label' => 'Nationality', 'value' => $student?->nationality ?? 'Not set'],
                ['label' => 'State of Origin', 'value' => $student?->state ?? 'Not set'],
                ['label' => 'Local Government Area', 'value' => $student?->lga ?? 'Not set'],
                ['label' => 'Registration Status', 'value' => ($student?->verification_status ?? 'pending') . ' · ' . ($student?->verification_method ?? 'N/A')],
                ['label' => 'Account Created', 'value' => $user->created_at?->format('M d, Y') ?? 'Unknown'],
                ['label' => 'Account ID', 'value' => '#' . $user->id],
            ]; @endphp
            @foreach ($biodata as $item)
                <div class="rounded-xl border border-border bg-bg/60 p-4 hover:border-primary/20 transition">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">{{ $item['label'] }}</p>
                    <p class="mt-1.5 text-sm font-bold text-text">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- ACADEMIC STATUS SECTION -->
    <section id="section-academic" class="profile-section mb-8 hidden rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h3 class="text-lg font-extrabold text-text">Academic Status</h3>
            <p class="mt-0.5 text-xs text-muted">Your verified university identity and programme details</p>
        </div>
        <div class="p-6 grid gap-4 md:grid-cols-2">
            @if ($verification && $verification->verified_name)
                <div class="rounded-xl border border-border bg-bg/60 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Verified Full Name (JAMB)</p>
                    <p class="mt-2 text-xl font-extrabold text-text">{{ $verification->verified_name }}</p>
                </div>
            @endif
            @if ($verification && $verification->verified_institution)
                <div class="rounded-xl border border-border bg-bg/60 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Verified Institution (JAMB)</p>
                    <p class="mt-2 text-base font-bold text-text">{{ $verification->verified_institution }}</p>
                </div>
            @endif
            @if ($verification && $verification->verified_programme)
                <div class="rounded-xl border border-border bg-bg/60 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Verified Programme (JAMB)</p>
                    <p class="mt-2 text-base font-bold text-text">{{ $verification->verified_programme }}</p>
                </div>
            @endif
            @if ($student && $student->admission_year)
                <div class="rounded-xl border border-border bg-bg/60 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Admission Year</p>
                    <p class="mt-2 text-xl font-extrabold text-text">{{ $student->admission_year }}</p>
                </div>
            @endif
            @if ($verification && $verification->jamb_status)
                <div class="rounded-xl border border-border bg-bg/60 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">JAMB Verification Status</p>
                    <p class="mt-2 inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-sm font-bold text-emerald-700">{{ $verification->jamb_status }}</p>
                </div>
            @endif
            @if ($academicProgramme)
                <div class="rounded-xl border border-border bg-bg/60 p-5 md:col-span-2 lg:col-span-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Institution Programme</p>
                    <p class="mt-2 text-base font-bold text-text">{{ $academicProgramme->name }}</p>
                </div>
            @endif
            @if ($institutionRecord?->matric_number)
                <div class="rounded-xl border border-border bg-bg/60 p-5 md:col-span-2 lg:col-span-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">School Matric Number</p>
                    <p class="mt-2 font-mono text-base font-bold text-text">{{ $institutionRecord->matric_number }}</p>
                </div>
            @endif
        </div>
    </section>

    <!-- SECURITY SECTION -->
    <section id="section-security" class="profile-section mb-8 hidden rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h3 class="text-lg font-extrabold text-text">Security</h3>
            <p class="mt-0.5 text-xs text-muted">Your account protection and sign-in details</p>
        </div>
        <div class="p-6 grid gap-4 md:grid-cols-2">
            @php $securityInfo = [
                ['label' => 'Email Verified', 'value' => $user->email_verified_at ? 'Yes — ' . $user->email_verified_at->format('M d, Y') : 'No'],
                ['label' => 'Account Created', 'value' => $user->created_at ? $user->created_at->format('M d, Y') : 'Unknown'],
                ['label' => 'Last Updated', 'value' => $user->updated_at ? $user->updated_at->format('M d, Y') : 'Unknown'],
                ['label' => 'Verification Method', 'value' => $student?->verification_method ?? 'Not verified'],
            ]; @endphp
            @foreach ($securityInfo as $item)
                <div class="rounded-xl border border-border bg-bg/60 p-4 hover:border-primary/20 transition">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">{{ $item['label'] }}</p>
                    <p class="mt-1.5 text-sm font-bold text-text">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="border-t border-border bg-raised/30 px-6 py-4">
            <p class="text-xs text-muted">Your account uses server-side authorization checks. Only verified students receive course access.</p>
        </div>
    </section>

    <script>
        function showSection(sectionId) {
            ['courses', 'biodata', 'academic', 'security'].forEach(function(id) {
                document.getElementById('section-' + id).classList.add('hidden');
                document.getElementById('tab-' + id).classList.remove('bg-primary', 'text-primary-fg', 'shadow-sm');
                document.getElementById('tab-' + id).classList.add('text-text');
            });
            document.getElementById('section-' + sectionId).classList.remove('hidden');
            document.getElementById('tab-' + sectionId).classList.remove('text-text');
            document.getElementById('tab-' + sectionId).classList.add('bg-primary', 'text-primary-fg', 'shadow-sm');
        }
    </script>
</div>
@endsection