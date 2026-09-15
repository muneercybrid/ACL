@extends('layouts.auth')
@section('title', 'Privacy Policy')
@section('content')
<div class="mx-auto max-w-4xl px-4 py-12">
    <h1 class="adm-serif text-3xl font-semibold text-text">Privacy Policy</h1>
    <p class="mt-2 text-xs text-muted">Last updated: {{ now()->format('F j, Y') }}</p>
    <div class="prose mt-6 max-w-none text-sm text-text/80 space-y-4">
        <p>ACL ("Anyone Can Learn") collects account data (name, email), academic data (institution, faculty, programme, level, matriculation number), identity verification data (JAMB registration number, institutional ID documents) and learning data (enrolments, progress, assessments, certificates) solely to deliver and verify your learning experience.</p>
        <p>We do not sell personal data. Data is shared only with your institution (where you enrol through it), service providers (hosting, email), or where legally required. Identity documents are stored privately and exposed only through authorised, signed URLs.</p>
        <p>You may request access, correction, export or deletion of your data at any time by contacting privacy@aclacademy.me. Data is retained while your account is active; certificate verification records are retained indefinitely.</p>
        <p>We use cookies for authentication, preferences and analytics. We implement encryption in transit and at rest, hashed passwords and regular security review. In the event of a breach we will notify affected users and the regulator where required.</p>
    </div>
</div>
@endsection
