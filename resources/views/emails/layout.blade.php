<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'ACL — Anyone Can Learn')</title>
</head>
{{--
    Branded HTML shell for ACL mail.

    Table-based and inline-styled on purpose: Gmail, Outlook and Yahoo all strip
    <style> blocks and ignore most CSS, so a stylesheet-based button renders as
    plain text in the inbox. Everything here is what those clients honour.

    The logo is referenced over HTTPS rather than embedded as a base64 data URI
    because several webmail clients block data URIs in mail bodies outright.
--}}
<body style="margin:0;padding:0;background:#f6f7f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f5;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">

                {{-- Brand header --}}
                <tr>
                    <td style="padding:24px 32px;border-bottom:1px solid #e5e7eb;background:#fbfcf9;">
                        <img src="{{ url('/images/logo.svg') }}" alt="ACL — Anyone Can Learn" width="132" height="40" style="display:block;width:132px;height:40px;border:0;outline:none;text-decoration:none;">
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:28px 32px;font-size:15px;">
                        @yield('body')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 32px;border-top:1px solid #e5e7eb;background:#fbfcf9;font-size:12px;color:#6b7280;">
                        <p style="margin:0 0 6px;">
                            <strong>ACL</strong> — Anyone Can Learn.<br>
                            Need help? Reply to this email or use the
                            <a href="{{ url('/contact') }}" style="color:#2f7d4f;text-decoration:underline;">contact form</a>.
                        </p>
                        <p style="margin:0;color:#9ca3af;">
                            You are receiving this because an account was created with this address.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
