<footer id="contact" class="border-t border-border bg-surface/50">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="flex flex-col gap-8 md:flex-row md:justify-between">
            <div class="max-w-sm">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
                    <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
                </div>
                <p class="mt-3 max-w-sm text-sm leading-6 text-muted">Anyone Can Learn — a connected learning ecosystem for students, educators, tutors, universities, and the curious.</p>
            </div>

            <div class="grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
                <div>
                    <p class="font-semibold text-text">Learn</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li><a href="#courses" class="transition hover:text-primary">Courses</a></li>
                        <li><a href="#how" class="transition hover:text-primary">Learning journey</a></li>
                        <li><a href="{{ route('login') }}" class="transition hover:text-primary">Sign in</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-text">Teach</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li><a href="#how" class="transition hover:text-primary">For educators</a></li>
                        <li><a href="#universities" class="transition hover:text-primary">For universities</a></li>
                        <li><a href="mailto:hello@aclacademy.me" class="transition hover:text-primary">Contact ACL</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-text">About</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li><a href="#what-is-acl" class="transition hover:text-primary">Our approach</a></li>
                        <li><a href="mailto:hello@aclacademy.me" class="transition hover:text-primary">Help</a></li>
                        <li><a href="mailto:hello@aclacademy.me" class="transition hover:text-primary">Privacy and terms</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-2 border-t border-border pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} ACL — Anyone Can Learn. Built in Nigeria.</p>
            <p>Made for every learner.</p>
        </div>
    </div>
</footer>
