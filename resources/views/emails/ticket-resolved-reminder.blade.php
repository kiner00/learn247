<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 560px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .btn { display: inline-block; background: #f59e0b; color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">TICKET #{{ $ticket->id }} · RESOLVED</div>
        <h2 style="margin-top:0; margin-bottom: 8px;">{{ $ticket->subject }}</h2>

        <p style="font-size: 15px; line-height: 1.7;">
            Your support ticket was marked as <strong>resolved</strong>. Could you take a moment to verify the fix?
        </p>

        <ul style="font-size: 14px; color: #4b5563; line-height: 1.8; padding-left: 20px;">
            <li><strong>Close</strong> the ticket if everything looks good.</li>
            <li><strong>Reopen</strong> the ticket if you still need help.</li>
        </ul>

        <a href="{{ config('app.url') }}/support/{{ $ticket->id }}" class="btn">Review Ticket →</a>

        <div class="footer">
            You received this reminder because your support ticket has been resolved but not yet closed.
        </div>
    </div>
</body>
</html>
