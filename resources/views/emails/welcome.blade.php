<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e5e5e5; border-radius:6px; overflow:hidden;">
                    <tr>
                        <td style="padding:18px 24px; border-bottom:1px solid #e5e5e5;">
                            <a href="{{ config('app.url') }}" style="color:#2563eb; font-weight:bold; text-decoration:none;">
                                {{ config('app.name') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 10px; font-size:14px; color:#111827;">
                                Hi {{ $user->name }},
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; color:#111827;">
                                Welcome to {{ config('app.name') }}! Your account has been created successfully.
                            </p>

                            <p style="margin:0 0 18px; font-size:14px; color:#111827;">
                                Here are your details:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; font-size:14px;">
                                <tr>
                                    <td style="padding:8px 0; font-weight:bold; width:180px; color:#374151;">Full Name</td>
                                    <td style="padding:8px 0; color:#111827;">{{ $user->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-weight:bold; color:#374151;">Email Address</td>
                                    <td style="padding:8px 0; color:#111827;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-weight:bold; color:#374151;">Role</td>
                                    <td style="padding:8px 0; color:#111827;">{{ $user->role ?? 'seeker' }}</td>
                                </tr>

                                @if(optional($user->profile)->phone)
                                    <tr>
                                        <td style="padding:8px 0; font-weight:bold; color:#374151;">Phone Number</td>
                                        <td style="padding:8px 0; color:#111827;">{{ $user->profile->phone }}</td>
                                    </tr>
                                @endif
                            </table>

                            <p style="margin:18px 0 0; font-size:14px; color:#111827;">
                                Thanks,<br>
                                {{ config('app.name') }} Team
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px; border-top:1px solid #e5e5e5; font-size:12px; color:#6b7280;">
                            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
