<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>ACL contact message</title></head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',sans-serif;line-height:1.6;color:#1f2937;margin:0;padding:24px">
    <h2 style="margin:0 0 4px;font-size:18px">New message from the ACL contact form</h2>
    <p style="margin:0 0 20px;color:#6b7280;font-size:13px">
        Reply goes straight to {{ $email }}.
    </p>

    <table cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin-bottom:20px">
        <tr>
            <td style="padding:2px 12px 2px 0;color:#6b7280">From</td>
            <td style="padding:2px 0"><strong>{{ $name }}</strong> &lt;{{ $email }}&gt;</td>
        </tr>
        <tr>
            <td style="padding:2px 12px 2px 0;color:#6b7280">Subject</td>
            <td style="padding:2px 0">{{ $subject }}</td>
        </tr>
        @if ($ip)
            <tr>
                <td style="padding:2px 12px 2px 0;color:#6b7280">IP</td>
                <td style="padding:2px 0;color:#6b7280">{{ $ip }}</td>
            </tr>
        @endif
    </table>

    <div style="white-space:pre-wrap;border-left:3px solid #d1d5db;padding:12px 16px;background:#f9fafb;border-radius:0 4px 4px 0">{{ $body }}</div>
</body>
</html>
