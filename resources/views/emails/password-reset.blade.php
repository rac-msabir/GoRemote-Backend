<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Reset Password</title>
</head>
<body style="font-family: Arial, sans-serif;">

    <h2>Password Reset Request</h2>

    <p>You requested to reset your password.</p>

    <p>
        <a href="{{ $resetUrl }}"
           style="display:inline-block;padding:12px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Reset Password
        </a>
    </p>

    <p>If you didn’t request this, please ignore this email.</p>

</body>
</html>