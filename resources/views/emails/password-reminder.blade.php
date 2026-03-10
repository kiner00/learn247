<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #fef3c7; color: #d97706; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">SECURITY REMINDER</div>
        <h2 style="margin-top:0">Change your temporary password</h2>

        <p>Hi {{ $user->name }},</p>

        <p>
            You're still using the temporary password we sent you when you joined.
            For your security, please log in and set a permanent password.
        </p>

        <a href="{{ config('app.url') }}/login" class="btn">Log In & Change Password</a>

        <div class="footer">
            <p>If you've already changed your password, you can ignore this email.</p>
        </div>
    </div>
</body>
</html>
