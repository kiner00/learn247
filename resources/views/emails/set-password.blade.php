<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #ecfdf5; color: #059669; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 24px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">PAYMENT CONFIRMED</div>
        <h2 style="margin-top:0">You're in! One last step.</h2>

        <p>Hi {{ $user->name }},</p>

        <p>
            Your payment for <strong>{{ $community->name }}</strong> was successful.
            Set your password to access the community:
        </p>

        <a href="{{ url('/set-password') }}?token={{ $token }}&email={{ urlencode($user->email) }}" class="btn">
            Set Your Password
        </a>

        <p class="footer">
            This link expires in 60 minutes. If you did not make this purchase, please contact support.
        </p>
    </div>
</body>
</html>
