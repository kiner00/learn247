<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #e0e7ff; color: #4338ca; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">VERIFY YOUR EMAIL</div>
        <h2 style="margin-top:0">Confirm your email address</h2>

        <p>Hi {{ $user->name }},</p>

        <p>Tap the button below to verify this email address. The link expires in 60 minutes.</p>

        <a href="{{ $verifyUrl }}" class="btn">Verify Email</a>

        <div class="footer">
            <p>If you didn't request this, you can safely ignore this email.</p>
        </div>
    </div>
</body>
</html>
