<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 560px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #eff6ff; color: #3b82f6; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .body { font-size: 15px; line-height: 1.7; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; margin: 24px 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">INVITATION · {{ strtoupper($invite->community->name) }}</div>
        <h2 style="margin-top:0; margin-bottom: 8px;">You're invited! 🎉</h2>

        <div class="body">
            <p>You've been personally invited to join <strong>{{ $invite->community->name }}</strong> on Curzzo.</p>
            @if($invite->community->description)
                <p style="color:#6b7280;">{{ $invite->community->description }}</p>
            @endif
            <p>Click the button below to accept your invitation and get instant access.</p>
        </div>

        <a href="{{ config('app.url') }}/invite/{{ $invite->token }}" class="btn">Accept Invitation →</a>

        <div class="footer">
            This invite was sent to <strong>{{ $invite->email }}</strong> and expires in 7 days.<br>
            If you did not expect this email, you can safely ignore it.
        </div>
    </div>
</body>
</html>
