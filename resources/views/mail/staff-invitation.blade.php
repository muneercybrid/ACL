<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invitation</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 600px; margin: 0 auto; padding: 2rem; background: #f8fafc; color: #1e293b;">
    <h2 style="margin-top: 0;">You have been invited</h2>
    <p>Hello,</p>
    <p>You have been invited as <strong>{{ $invitation->role?->name ?? 'Administrator' }}</strong> at <strong>{{ $invitation->organization?->name ?? 'ACL Academy' }}</strong>.</p>

    @if (!empty($invitation->scope))
        <p><strong>Scope:</strong> {{ $invitation->getScopeDescription() }}</p>
    @endif

    <p>This link is secure and expires in <strong>7 days</strong>. It can only be used once.</p>

    <a href="{{ $url }}" style="display: inline-block; padding: 0.75rem 1.5rem; background: #b45309; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 600; margin: 1rem 0;">Accept invitation</a>

    <p style="color: #64748b; font-size: 0.875rem;">If you did not expect this invitation, you can safely ignore it.</p>

    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 2rem 0;">
    <p style="color: #64748b; font-size: 0.75rem;">Sent from ACL Academy (Anyone Can Learn)</p>
</body>
</html>
