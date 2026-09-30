@extends('emails.layout')

@section('title', 'Welcome to ACL — your account is verified')

@section('body')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#111827;">
        Welcome to ACL, {{ $name }}!
    </h1>

    <p style="margin:0 0 18px;color:#4b5563;font-size:15px;">
        Your JAMB verification was <strong style="color:#2f7d4f;">successful</strong> and your
        academic identity is now confirmed.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin:0 0 20px;width:100%;max-width:420px;">
        <tr>
            <td style="padding:8px 16px 8px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Name</td>
            <td style="padding:8px 0;font-weight:600;color:#111827;">{{ $name }}</td>
        </tr>
        <tr>
            <td style="padding:8px 16px 8px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Institution</td>
            <td style="padding:8px 0;color:#111827;">{{ $institution }}</td>
        </tr>
        <tr>
            <td style="padding:8px 16px 8px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Programme</td>
            <td style="padding:8px 0;color:#111827;">{{ $programme }}</td>
        </tr>
    </table>

    <p style="margin:0 0 6px;font-weight:600;color:#111827;">Confirm your email address</p>
    <p style="margin:0;color:#4b5563;font-size:14px;">
        Tap the button below to confirm this address. This is what clears the
        reminder from your dashboard and keeps your account secure.
    </p>

    <x-emails.button :url="$verify_url" label="Confirm my email address" />

    {{-- The URL is repeated as plain text so it is still usable if a mail
         gateway strips the button, which some corporate filters do. --}}
    <p style="margin:0;color:#6b7280;font-size:12px;word-break:break-all;">
        If the button does not work, copy this link into your browser:<br>
        <a href="{{ $verify_url }}" style="color:#2f7d4f;">{{ $verify_url }}</a>
    </p>
@endsection
