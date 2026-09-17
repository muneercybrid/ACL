@extends('layouts.app')
@section('title', 'Institution Portal — ACL')
@section('content')
<div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-extrabold tracking-tight text-text">Institution Portal</h1>
        <p class="mt-3 text-sm text-muted">Choose your role. Use default credentials. First login requires editing your details.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <a href="{{ route('institution.admin.login') }}" class="group rounded-2xl border border-border bg-surface p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
            <h2 class="text-xl font-extrabold text-text">Admin Login</h2>
            <p class="mt-2 text-sm text-muted">Institution administrator. Edit institution details on first login.</p>
            <div class="mt-4 text-sm font-bold text-primary">Email: admin@aclacademy.me / Pass: default123</div>
        </a>
        <a href="{{ route('institution.coordinator.login') }}" class="group rounded-2xl border border-border bg-surface p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
            <h2 class="text-xl font-extrabold text-text">Level Coordinator</h2>
            <p class="mt-2 text-sm text-muted">Coordinate courses for your level. Must edit profile first.</p>
            <div class="mt-4 text-sm font-bold text-primary">Email: coordinator@aclacademy.me / Pass: default123</div>
        </a>
        <a href="{{ route('institution.moderator.login') }}" class="group rounded-2xl border border-border bg-surface p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
            <h2 class="text-xl font-extrabold text-text">Moderator Login</h2>
            <p class="mt-2 text-sm text-muted">Moderate courses and content. Must edit profile first.</p>
            <div class="mt-4 text-sm font-bold text-primary">Email: moderator@aclacademy.me / Pass: default123</div>
        </a>
    </div>
</div>
@endsection