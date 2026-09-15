@extends('layouts.auth')
@section('title', 'Terms of Use')
@section('content')
<div class="mx-auto max-w-4xl px-4 py-12">
    <h1 class="adm-serif text-3xl font-semibold text-text">Terms of Use</h1>
    <p class="mt-2 text-xs text-muted">Last updated: {{ now()->format('F j, Y') }}</p>
    <div class="prose mt-6 max-w-none text-sm text-text/80 space-y-4">
        <p>By using ACL you agree to these terms. You must provide accurate registration information and keep your credentials secure. You are responsible for all activity under your account.</p>
        <p>Academic integrity is mandatory: complete your own assessments, do not share credentials, and do not attempt to circumvent platform controls. Violations may result in suspension and certificate revocation.</p>
        <p>Course content, platform software and AI-generated materials are owned by ACL and licensed for your personal, non-commercial learning use. You retain ownership of content you create.</p>
        <p>Certificates represent successful completion of course requirements, are publicly verifiable, and are not formal academic awards unless explicitly stated. We may suspend or terminate accounts that breach these terms. These terms are governed by the laws of the Federal Republic of Nigeria.</p>
    </div>
</div>
@endsection
