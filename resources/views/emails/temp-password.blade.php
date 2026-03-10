<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #ecfdf5; color: #059669; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .password-box { background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 8px; padding: 16px 24px; text-align: center; margin: 20px 0; font-size: 22px; font-weight: 700; letter-spacing: 3px; color: #111827; font-family: monospace; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">PAYMENT CONFIRMED</div>
        <h2 style="margin-top:0">Welcome to {{ $community->name }}!</h2>

        <p>Hi {{ $user->name }},</p>

        <p>Your payment was successful. Use the temporary password below to log in:</p>

        <div class="password-box">{{ $tempPassword }}</div>

        <p style="font-size:13px; color:#6b7280; margin-top: -8px;">Login email: <strong>{{ $user->email }}</strong></p>

        <a href="{{ config('app.url') }}/login" class="btn">Log In Now</a>

        <p>Once logged in, you'll be prompted to set a permanent password.</p>

        <div class="footer">
            <p>For security, please change your password as soon as you log in. If you didn't make this purchase, please contact support immediately.</p>
        </div>
    </div>
</body>
</html>
