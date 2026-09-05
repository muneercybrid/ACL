{{-- Public marketing footer. --}}
<footer class="border-t border-border bg-surface/50">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="flex flex-col gap-8 md:flex-row md:justify-between">
            <div class="max-w-sm">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
                    <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
                </div>
                <p class="mt-3 text-sm text-muted">
                    Anyone Can Learn — open learning for Nigerian institutions. Your courses follow your enrolment, not your wallet.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
                <div>
                    <p class="font-semibold text-text">Platform</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li><a href="#courses" class="transition hover:text-primary">Courses</a></li>
                        <li><a href="#vision" class="transition hover:text-primary">Vision</a></li>
                        <li><a href="{{ route('login') }}" class="transition hover:text-primary">Login</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-text">Institutions</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li>Universities</li>
                        <li>Polytechnics</li>
                        <li>Colleges of Education</li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-text">About</p>
                    <ul class="mt-3 space-y-2 text-muted">
                        <li>Open source</li>
                        <li>Privacy</li>
                        <li>Contact</li>
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
