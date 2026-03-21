<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 560px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #eef2ff; color: #4f46e5; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .body { font-size: 15px; line-height: 1.7; white-space: pre-wrap; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
        .sender { font-size: 13px; color: #6b7280; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">📢 ANNOUNCEMENT · CURZZO</div>
        <h2 style="margin-top:0; margin-bottom: 8px;">{{ $announcementSubject }}</h2>
        <div class="sender">From <strong>{{ $sender->name }}</strong> · Curzzo Team</div>

        <div class="body" style="margin-top: 24px;">{{ $message }}</div>

        <a href="{{ config('app.url') }}/communities" class="btn">Explore Communities →</a>

        <div class="footer">
            You received this platform-wide announcement from the Curzzo team.<br>
            To manage your notification preferences, visit your account settings.
        </div>
    </div>
</body>
</html>
