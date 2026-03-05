<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 24px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0">Your subscription is expiring soon</h2>
        <p>Hi {{ $subscription->user->name }},</p>
        <p>
            Your subscription to <strong>{{ $subscription->community->name }}</strong>
            expires on <strong>{{ $subscription->expires_at->format('F j, Y') }}</strong>.
        </p>
        <p>Renew now to keep your access:</p>
        <a href="{{ $renewalUrl }}" class="btn">Renew Subscription</a>
        <p class="footer">
            If you no longer wish to stay subscribed, simply ignore this email and your access will end on the expiry date.
        </p>
    </div>
</body>
</html>
